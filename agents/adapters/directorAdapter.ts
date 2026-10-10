import { existsSync, readFileSync } from "node:fs";
import path from "node:path";
import type { AgentConfig, DirectorTask } from "../types.ts";

interface DirectorAdapterOptions {
  rootDir?: string;
}

const DEFAULT_TASK_TITLE = "Audit project and create PROJECT_STATUS.md";

export async function getNextTask(
  config: AgentConfig,
  options: DirectorAdapterOptions = {},
): Promise<DirectorTask> {
  const rootDir = options.rootDir ?? process.cwd();

  if (config.directorProvider !== "mock") {
    return getProviderPlaceholderTask(config.directorProvider);
  }

  const taskTitle = readFirstTaskFromMarkdown(path.join(rootDir, "TASKS.md")) ?? DEFAULT_TASK_TITLE;
  return createMockTask(taskTitle);
}

function readFirstTaskFromMarkdown(filePath: string): string | null {
  if (!existsSync(filePath)) {
    return null;
  }

  const content = readFileSync(filePath, "utf8");
  const lines = content
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  for (const line of lines) {
    const task = line
      .replace(/^[-*]\s+/, "")
      .replace(/^\d+[.)]\s+/, "")
      .replace(/^["']|["']$/g, "")
      .trim();

    if (task && !task.startsWith("#")) {
      return task;
    }
  }

  return null;
}

function createMockTask(title: string): DirectorTask {
  const normalizedTitle = title.endsWith(".") ? title.slice(0, -1) : title;

  return {
    taskId: `mock-${slugify(normalizedTitle).slice(0, 40) || "task"}`,
    title: normalizedTitle,
    priority: "medium",
    goal: normalizedTitle,
    scope: [
      "Inspect the current project structure.",
      "Do not change business logic.",
      "Produce a small, reviewable result.",
    ],
    filesLikelyToChange: ["PROJECT_STATUS.md"],
    acceptanceCriteria: [
      "A concise project status document exists.",
      "The document summarizes current modules, data files, and known safety constraints.",
      "No live trading or MT5 order execution is introduced.",
    ],
    testPlan: [
      "Run available syntax/build checks.",
      "Run available lint or test commands if present.",
      "Confirm no trading execution code was added.",
    ],
    forbiddenActions: ["no live trading", "no MT5 order execution", "no secret changes"],
  };
}

function getProviderPlaceholderTask(provider: string): DirectorTask {
  return {
    taskId: `provider-${slugify(provider || "director")}`,
    title: `Director provider '${provider}' is not wired yet`,
    priority: "low",
    goal: "Wire the selected Director provider before requesting AI-generated tasks.",
    scope: ["Keep the orchestrator in dry-run mode until provider wiring is explicit."],
    filesLikelyToChange: ["agents/adapters/directorAdapter.ts"],
    acceptanceCriteria: ["Provider integration remains disabled by default."],
    testPlan: ["Run the orchestrator in dry-run mode."],
    forbiddenActions: ["no live trading", "no MT5 order execution", "no secret changes"],
  };
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}
