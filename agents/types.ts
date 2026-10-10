export type AgentMode = "dry-run" | "execute" | "mock";
export type AgentPriority = "low" | "medium" | "high";
export type ReviewNextAction = "approve" | "fix" | "stop";

export interface AgentConfig {
  maxIterations: number;
  mode: AgentMode | string;
  allowCodeChange: boolean;
  allowLiveTrading: boolean;
  allowMt5OrderExecution: boolean;
  allowDeleteData: boolean;
  requireBuildPass: boolean;
  requireBacktestPass: boolean;
  stopIfBuildFails: boolean;
  directorProvider: "mock" | "openai" | string;
  coderProvider: "codex-cli" | "codex-cloud" | "mock" | string;
  reviewerProvider: "mock" | "openai" | string;
}

export interface DirectorTask {
  taskId: string;
  title: string;
  priority: AgentPriority;
  goal: string;
  scope: string[];
  filesLikelyToChange: string[];
  acceptanceCriteria: string[];
  testPlan: string[];
  forbiddenActions: string[];
}

export interface CommandResult {
  name: string;
  command: string;
  skipped: boolean;
  exitCode: number | null;
  stdout: string;
  stderr: string;
  durationMs: number;
}

export interface CoderReport {
  taskId: string;
  provider: string;
  mode: string;
  dryRun: boolean;
  codexCliAvailable: boolean;
  promptFile: string;
  plannedCommand: string | null;
  filesChanged: string[];
  summary: string;
  blockedReason: string | null;
  startedAt: string;
  completedAt: string;
}

export interface ReviewReport {
  passed: boolean;
  summary: string;
  issues: string[];
  nextAction: ReviewNextAction;
}

export interface AgentRunLog {
  runId: string;
  startedAt: string;
  completedAt: string;
  config: AgentConfig;
  task: DirectorTask;
  coderReport: CoderReport;
  commandResults: CommandResult[];
  review: ReviewReport;
}
