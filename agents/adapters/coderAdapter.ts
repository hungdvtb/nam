import { execFile } from "node:child_process";
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { promisify } from "node:util";
import type { AgentConfig, CoderReport, DirectorTask } from "../types.ts";

const execFileAsync = promisify(execFile);

interface CoderAdapterOptions {
  rootDir?: string;
}

export async function runCoderTask(
  task: DirectorTask,
  config: AgentConfig,
  options: CoderAdapterOptions = {},
): Promise<CoderReport> {
  const rootDir = options.rootDir ?? process.cwd();
  const startedAt = new Date().toISOString();
  const promptFile = path.join(rootDir, "agents", "tasks", "current-coder-task.md");
  const codexCliAvailable = await commandExists(process.platform === "win32" ? "codex.exe" : "codex");
  const dryRun = config.mode === "dry-run" || config.allowCodeChange !== true;
  const prompt = buildCoderPrompt(task, config);
  const plannedCommand = codexCliAvailable
    ? [
        "codex",
        "exec",
        "--sandbox",
        "workspace-write",
        "--ask-for-approval",
        "on-request",
        "--",
        "Read agents/tasks/current-coder-task.md and implement exactly that task.",
      ].join(" ")
    : null;

  mkdirSync(path.dirname(promptFile), { recursive: true });
  writeFileSync(promptFile, prompt, "utf8");

  if (dryRun) {
    return {
      taskId: task.taskId,
      provider: config.coderProvider,
      mode: config.mode,
      dryRun: true,
      codexCliAvailable,
      promptFile: normalizeForReport(rootDir, promptFile),
      plannedCommand,
      filesChanged: [],
      summary: "Dry-run only. The coder prompt was written, but no code changes were executed.",
      blockedReason: null,
      startedAt,
      completedAt: new Date().toISOString(),
    };
  }

  if (!codexCliAvailable) {
    return {
      taskId: task.taskId,
      provider: config.coderProvider,
      mode: config.mode,
      dryRun: false,
      codexCliAvailable,
      promptFile: normalizeForReport(rootDir, promptFile),
      plannedCommand: null,
      filesChanged: [],
      summary: "Codex CLI is not available on this machine.",
      blockedReason: "Install or configure Codex CLI before enabling code-change execution.",
      startedAt,
      completedAt: new Date().toISOString(),
    };
  }

  return {
    taskId: task.taskId,
    provider: config.coderProvider,
    mode: config.mode,
    dryRun: false,
    codexCliAvailable,
    promptFile: normalizeForReport(rootDir, promptFile),
    plannedCommand,
    filesChanged: [],
    summary: "Code-change execution is gated in v1. A safe Codex CLI command was prepared but not auto-run.",
    blockedReason: "Manual approval is required before running Codex against the workspace.",
    startedAt,
    completedAt: new Date().toISOString(),
  };
}

function buildCoderPrompt(task: DirectorTask, config: AgentConfig): string {
  return [
    "# Current Coder Task",
    "",
    "You are the AI Coder for this project.",
    "",
    "Safety rules:",
    "- Code only for the assigned task.",
    "- Do not change the task goal.",
    "- Do not add live trading.",
    "- Do not call MT5 order execution APIs.",
    "- Do not change secrets, API keys, credentials, `.env` files, or deployment keys.",
    "- Do not delete user data.",
    "",
    "Runtime gates:",
    `- mode: ${config.mode}`,
    `- allowCodeChange: ${String(config.allowCodeChange)}`,
    `- allowLiveTrading: ${String(config.allowLiveTrading)}`,
    `- allowMt5OrderExecution: ${String(config.allowMt5OrderExecution)}`,
    `- allowDeleteData: ${String(config.allowDeleteData)}`,
    "",
    "Task JSON:",
    "",
    "```json",
    JSON.stringify(task, null, 2),
    "```",
    "",
    "After coding, report:",
    "- Files changed.",
    "- Commands run and results.",
    "- Remaining issues or follow-up work.",
    "",
  ].join("\n");
}

async function commandExists(command: string): Promise<boolean> {
  const lookupCommand = process.platform === "win32" ? "where" : "which";
  const args = [command];

  try {
    await execFileAsync(lookupCommand, args, { timeout: 5000 });
    return true;
  } catch {
    return false;
  }
}

function normalizeForReport(rootDir: string, filePath: string): string {
  return path.relative(rootDir, filePath).replace(/\\/g, "/");
}
