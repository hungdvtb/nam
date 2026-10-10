import { execFile } from "node:child_process";
import { randomBytes } from "node:crypto";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { promisify } from "node:util";
import { runCoderTask } from "../agents/adapters/coderAdapter.ts";
import { getNextTask } from "../agents/adapters/directorAdapter.ts";
import { reviewTask } from "../agents/adapters/reviewerAdapter.ts";
import type { AgentConfig, AgentRunLog, CoderReport, CommandResult, DirectorTask } from "../agents/types.ts";

const execFileAsync = promisify(execFile);
const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT_DIR = path.resolve(SCRIPT_DIR, "..");
const CONFIG_PATH = path.join(ROOT_DIR, "agents", "agent.config.json");
const RUNS_DIR = path.join(ROOT_DIR, "agents", "runs");

const DEFAULT_CONFIG: AgentConfig = {
  maxIterations: 3,
  mode: "dry-run",
  allowCodeChange: false,
  allowLiveTrading: false,
  allowMt5OrderExecution: false,
  allowDeleteData: false,
  requireBuildPass: true,
  requireBacktestPass: false,
  stopIfBuildFails: true,
  directorProvider: "mock",
  coderProvider: "codex-cli",
  reviewerProvider: "mock",
};

interface CliArgs {
  dryRun: boolean;
  planOnly: boolean;
  reviewLatest: boolean;
}

async function main(): Promise<void> {
  const args = parseArgs(process.argv.slice(2));
  const config = loadConfig(args);
  assertSafetyLocks(config);

  if (args.planOnly) {
    await runPlanOnly(config);
    return;
  }

  if (args.reviewLatest) {
    await runReviewLatest(config);
    return;
  }

  await runOneLoop(config);
}

async function runPlanOnly(config: AgentConfig): Promise<void> {
  const task = await getNextTask(config, { rootDir: ROOT_DIR });
  const taskPath = path.join(ROOT_DIR, "agents", "tasks", "current-director-task.json");
  writeJson(taskPath, task);
  printResult("Director task created", {
    taskId: task.taskId,
    title: task.title,
    file: normalizeForReport(taskPath),
  });
}

async function runReviewLatest(config: AgentConfig): Promise<void> {
  const latestPath = path.join(RUNS_DIR, "latest.json");
  if (!existsSync(latestPath)) {
    throw new Error("No latest run exists yet. Run the orchestrator first.");
  }

  const latest = readJson<AgentRunLog>(latestPath);
  const review = await reviewTask(latest.task, latest.coderReport, latest.commandResults, { config });
  const reviewPath = path.join(RUNS_DIR, latest.runId, "review.json");
  writeJson(reviewPath, review);
  writeJson(latestPath, { ...latest, review, completedAt: new Date().toISOString() });

  printResult("Latest run reviewed", {
    runId: latest.runId,
    passed: review.passed,
    nextAction: review.nextAction,
    issues: review.issues,
  });
}

async function runOneLoop(config: AgentConfig): Promise<void> {
  const startedAt = new Date().toISOString();
  const runId = createRunId();
  const runDir = path.join(RUNS_DIR, runId);
  mkdirSync(runDir, { recursive: true });

  const task = await getNextTask(config, { rootDir: ROOT_DIR });
  const taskPath = path.join(runDir, "task.json");
  writeJson(taskPath, task);

  const coderReport = await runCoderTask(task, config, { rootDir: ROOT_DIR });
  const coderReportPath = path.join(runDir, "coder-report.json");
  writeJson(coderReportPath, coderReport);

  const commandResults = await runProjectCommands(config);
  writeCommandLog(path.join(runDir, "commands.log"), commandResults);

  const review = await reviewTask(task, coderReport, commandResults, { config });
  const reviewPath = path.join(runDir, "review.json");
  writeJson(reviewPath, review);

  const runLog: AgentRunLog = {
    runId,
    startedAt,
    completedAt: new Date().toISOString(),
    config,
    task,
    coderReport,
    commandResults,
    review,
  };

  writeJson(path.join(runDir, "run.json"), runLog);
  writeJson(path.join(RUNS_DIR, "latest.json"), runLog);

  printResult("Agent orchestrator run complete", {
    runId,
    mode: config.mode,
    task: task.title,
    dryRun: coderReport.dryRun,
    reviewPassed: review.passed,
    nextAction: review.nextAction,
    files: {
      task: normalizeForReport(taskPath),
      coderReport: normalizeForReport(coderReportPath),
      commands: normalizeForReport(path.join(runDir, "commands.log")),
      review: normalizeForReport(reviewPath),
      latest: normalizeForReport(path.join(RUNS_DIR, "latest.json")),
    },
  });
}

async function runProjectCommands(config: AgentConfig): Promise<CommandResult[]> {
  const packagePath = path.join(ROOT_DIR, "package.json");

  if (!existsSync(packagePath)) {
    return [
      skippedCommand("build", "No package.json found; npm build skipped."),
      skippedCommand("lint", "No package.json found; npm lint skipped."),
      skippedCommand("test", "No package.json found; npm test skipped."),
      skippedCommand("backtest", "No package.json found; npm backtest skipped."),
    ];
  }

  const packageJson = readJson<{ scripts?: Record<string, string> }>(packagePath);
  const scripts = packageJson.scripts ?? {};
  const results: CommandResult[] = [];

  results.push(scripts.build ? await runNpmScript("build") : skippedCommand("build", "package.json has no build script."));
  if (config.stopIfBuildFails && results[0].exitCode !== 0 && results[0].skipped === false) {
    results.push(skippedCommand("lint", "Build failed and stopIfBuildFails=true."));
    results.push(skippedCommand("test", "Build failed and stopIfBuildFails=true."));
    results.push(skippedCommand("backtest", "Build failed and stopIfBuildFails=true."));
    return results;
  }

  results.push(scripts.lint ? await runNpmScript("lint") : skippedCommand("lint", "package.json has no lint script."));
  results.push(scripts.test ? await runNpmScript("test") : skippedCommand("test", "package.json has no test script."));
  results.push(
    scripts.backtest ? await runNpmScript("backtest") : skippedCommand("backtest", "package.json has no backtest script."),
  );

  return results;
}

async function runNpmScript(name: string): Promise<CommandResult> {
  const npmCommand = process.platform === "win32" ? "npm.cmd" : "npm";
  const started = Date.now();

  try {
    const result = await execFileAsync(npmCommand, ["run", name], {
      cwd: ROOT_DIR,
      timeout: 300000,
      maxBuffer: 1024 * 1024 * 10,
    });

    return {
      name,
      command: `${npmCommand} run ${name}`,
      skipped: false,
      exitCode: 0,
      stdout: result.stdout,
      stderr: result.stderr,
      durationMs: Date.now() - started,
    };
  } catch (error) {
    const err = error as { code?: number; stdout?: string; stderr?: string; message?: string };
    return {
      name,
      command: `${npmCommand} run ${name}`,
      skipped: false,
      exitCode: typeof err.code === "number" ? err.code : 1,
      stdout: err.stdout ?? "",
      stderr: err.stderr ?? err.message ?? "",
      durationMs: Date.now() - started,
    };
  }
}

function loadConfig(args: CliArgs): AgentConfig {
  const loaded = existsSync(CONFIG_PATH) ? readJson<Partial<AgentConfig>>(CONFIG_PATH) : {};
  const config = { ...DEFAULT_CONFIG, ...loaded };

  if (args.dryRun) {
    config.mode = "dry-run";
    config.allowCodeChange = false;
  }

  return config;
}

function assertSafetyLocks(config: AgentConfig): void {
  const violations: string[] = [];

  if (config.maxIterations !== 1 && config.maxIterations > 3) {
    violations.push("maxIterations must stay at 3 or lower for v1.");
  }
  if (config.allowLiveTrading !== false) {
    violations.push("allowLiveTrading must be false.");
  }
  if (config.allowMt5OrderExecution !== false) {
    violations.push("allowMt5OrderExecution must be false.");
  }
  if (config.allowDeleteData !== false) {
    violations.push("allowDeleteData must be false.");
  }

  if (violations.length > 0) {
    throw new Error(`Unsafe agent config:\n- ${violations.join("\n- ")}`);
  }
}

function parseArgs(argv: string[]): CliArgs {
  return {
    dryRun: argv.includes("--dry-run"),
    planOnly: argv.includes("--plan-only"),
    reviewLatest: argv.includes("--review-latest"),
  };
}

function skippedCommand(name: string, reason: string): CommandResult {
  return {
    name,
    command: reason,
    skipped: true,
    exitCode: null,
    stdout: "",
    stderr: "",
    durationMs: 0,
  };
}

function writeCommandLog(filePath: string, results: CommandResult[]): void {
  const lines = results.flatMap((result) => [
    `# ${result.name}`,
    `command: ${result.command}`,
    `skipped: ${String(result.skipped)}`,
    `exitCode: ${String(result.exitCode)}`,
    `durationMs: ${String(result.durationMs)}`,
    "stdout:",
    result.stdout.trim() || "(empty)",
    "stderr:",
    result.stderr.trim() || "(empty)",
    "",
  ]);

  mkdirSync(path.dirname(filePath), { recursive: true });
  writeFileSync(filePath, lines.join("\n"), "utf8");
}

function readJson<T>(filePath: string): T {
  return JSON.parse(readFileSync(filePath, "utf8")) as T;
}

function writeJson(filePath: string, value: unknown): void {
  mkdirSync(path.dirname(filePath), { recursive: true });
  writeFileSync(filePath, `${JSON.stringify(value, null, 2)}\n`, "utf8");
}

function createRunId(): string {
  const timestamp = new Date().toISOString().replace(/[-:.TZ]/g, "").slice(0, 14);
  return `${timestamp}-${randomBytes(3).toString("hex")}`;
}

function normalizeForReport(filePath: string): string {
  return path.relative(ROOT_DIR, filePath).replace(/\\/g, "/");
}

function printResult(title: string, payload: Record<string, unknown>): void {
  console.log(title);
  console.log(JSON.stringify(payload, null, 2));
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
