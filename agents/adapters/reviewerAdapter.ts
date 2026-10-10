import type { AgentConfig, CoderReport, CommandResult, DirectorTask, ReviewReport } from "../types.ts";

interface ReviewerAdapterOptions {
  config?: AgentConfig;
}

const FORBIDDEN_PATTERNS = [
  /\border_send\b/i,
  /\bmt5\.order/i,
  /\bplace[_-]?order\b/i,
  /\bexecute\s+real\s+order\b/i,
  /\breal\s+mt5\s+order\b/i,
  /"allowLiveTrading"\s*:\s*true/i,
  /"allowMt5OrderExecution"\s*:\s*true/i,
  /\blive\s+trading\s+enabled\b/i,
];

const SENSITIVE_FILE_PATTERNS = [
  /(^|[/\\])\.env($|[./\\])/i,
  /(^|[/\\])\.ssh($|[/\\])/i,
  /secret/i,
  /credential/i,
  /api[_-]?key/i,
  /private[_-]?key/i,
];

export async function reviewTask(
  task: DirectorTask,
  coderReport: CoderReport,
  commandResults: CommandResult[],
  options: ReviewerAdapterOptions = {},
): Promise<ReviewReport> {
  const config = options.config;
  const issues: string[] = [];

  const buildResult = commandResults.find((result) => result.name === "build");
  const testResult = commandResults.find((result) => result.name === "test");
  const backtestResult = commandResults.find((result) => result.name === "backtest");

  if (config?.requireBuildPass && buildResult && !buildResult.skipped && buildResult.exitCode !== 0) {
    issues.push("Build command failed.");
  }

  if (testResult && !testResult.skipped && testResult.exitCode !== 0) {
    issues.push("Test command failed.");
  }

  if (config?.requireBacktestPass && backtestResult && !backtestResult.skipped && backtestResult.exitCode !== 0) {
    issues.push("Backtest command failed.");
  }

  const forbiddenHits = findForbiddenHits(task, coderReport);
  if (forbiddenHits.length > 0) {
    issues.push(`Forbidden trading action pattern detected: ${forbiddenHits.join(", ")}.`);
  }

  const sensitiveFiles = coderReport.filesChanged.filter((file) =>
    SENSITIVE_FILE_PATTERNS.some((pattern) => pattern.test(file)),
  );
  if (sensitiveFiles.length > 0) {
    issues.push(`Sensitive file changes detected: ${sensitiveFiles.join(", ")}.`);
  }

  if (config?.allowLiveTrading === true) {
    issues.push("Config allows live trading. This project must keep live trading locked off.");
  }

  if (config?.allowMt5OrderExecution === true) {
    issues.push("Config allows MT5 order execution. This project must keep order execution locked off.");
  }

  if (config?.allowDeleteData === true) {
    issues.push("Config allows data deletion. This project must keep data deletion locked off.");
  }

  const passed = issues.length === 0;
  const buildFailed = Boolean(buildResult && !buildResult.skipped && buildResult.exitCode !== 0);
  const nextAction = passed ? "approve" : config?.stopIfBuildFails && buildFailed ? "stop" : "fix";

  return {
    passed,
    summary: passed
      ? "Mock reviewer approved the run. No failed required commands or forbidden actions were detected."
      : "Mock reviewer found issues that must be addressed before approval.",
    issues,
    nextAction,
  };
}

function findForbiddenHits(task: DirectorTask, coderReport: CoderReport): string[] {
  const searchable = [
    task.title,
    task.goal,
    ...task.scope,
    ...task.acceptanceCriteria,
    ...task.testPlan,
    coderReport.summary,
    coderReport.blockedReason ?? "",
    coderReport.plannedCommand ?? "",
    ...coderReport.filesChanged,
  ].join("\n");

  return FORBIDDEN_PATTERNS
    .filter((pattern) => pattern.test(searchable))
    .map((pattern) => pattern.source);
}
