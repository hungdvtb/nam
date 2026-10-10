# Multi-Agent System

This project now has an Agent Orchestrator v1 scaffold. It is designed for dry-run planning first, with live trading and MT5 order execution locked off.

## Roles

AI Director:
- Acts as Product Manager and Architect.
- Reads project status files when they exist.
- Selects exactly one small next task.
- Returns a JSON task only.
- Does not code and does not request live trading.

Codex Coder:
- Receives one Director task.
- Works only inside that task scope.
- Does not change secrets.
- Does not call MT5 order execution APIs.
- In the default v1 config, writes a coder prompt only and does not edit code.

Orchestrator:
- Loads `agents/agent.config.json`.
- Gets one task from the Director adapter.
- Writes run artifacts under `agents/runs/{runId}/`.
- Sends the task to the Coder adapter.
- Runs available build, lint, test, and backtest commands when a `package.json` exists.
- Calls the Reviewer adapter.
- Updates `agents/runs/latest.json`.

Reviewer:
- Checks command results.
- Checks forbidden trading patterns.
- Checks sensitive file changes.
- Returns `approve`, `fix`, or `stop`.

## Safety Rules

These locks must stay false unless a future human review explicitly changes the architecture:
- `allowLiveTrading`
- `allowMt5OrderExecution`
- `allowDeleteData`

The current orchestrator stops if those locks are enabled. This project may fetch MT5 market data, but it must not place real orders or call real order execution APIs.

Do not change:
- API keys
- Secrets
- Credentials
- `.env` files
- SSH keys
- Deployment keys

## Commands

This is currently a PHP project with no `package.json`, so no npm scripts were added.

Run one dry-run loop:

```powershell
node --disable-warning=MODULE_TYPELESS_PACKAGE_JSON scripts/agent-orchestrator.ts --dry-run
```

Run one loop using `agents/agent.config.json`:

```powershell
node --disable-warning=MODULE_TYPELESS_PACKAGE_JSON scripts/agent-orchestrator.ts
```

Create only the next Director task:

```powershell
node --disable-warning=MODULE_TYPELESS_PACKAGE_JSON scripts/agent-orchestrator.ts --plan-only
```

Review the latest run again:

```powershell
node --disable-warning=MODULE_TYPELESS_PACKAGE_JSON scripts/agent-orchestrator.ts --review-latest
```

Node 24 can run these `.ts` files directly. On older Node versions, use a TypeScript runner such as `tsx` or upgrade Node.

## Turning On Code Changes Later

Default config:

```json
{
  "mode": "dry-run",
  "allowCodeChange": false
}
```

Future code-change flow:
1. Keep `allowLiveTrading=false`.
2. Keep `allowMt5OrderExecution=false`.
3. Keep `allowDeleteData=false`.
4. Set `allowCodeChange=true`.
5. Change `mode` to a non-dry-run mode such as `execute`.
6. Review `agents/tasks/current-coder-task.md`.
7. Run the prepared Codex command manually or wire an approved Codex execution step.

In v1, the Coder adapter prepares a safe Codex CLI command when Codex CLI is detected, but it does not auto-run Codex against the workspace.

## OpenAI Director Integration

The Director adapter currently supports `mock`. To wire OpenAI later:
1. Add an OpenAI provider branch in `agents/adapters/directorAdapter.ts`.
2. Load the prompt from `agents/prompts/director.prompt.md`.
3. Send project status context only, not secrets.
4. Validate the model response against the `DirectorTask` JSON shape.
5. Keep one-task-per-loop behavior.

Do not store OpenAI API keys in this repo. Use the local environment or a secret manager.

## Codex CLI or Codex Cloud Integration

The Coder adapter currently detects Codex CLI and writes `agents/tasks/current-coder-task.md`.

Future Codex CLI integration should:
- Use workspace-write sandboxing.
- Ask for approval before risky commands.
- Pass the generated task prompt as input.
- Refuse live trading or MT5 order execution requests.
- Report changed files and command results.

Future Codex Cloud integration should keep the same `CoderReport` shape so the Orchestrator and Reviewer do not need to change.

## Reading Reports

Each run writes:
- `agents/runs/{runId}/task.json`
- `agents/runs/{runId}/coder-report.json`
- `agents/runs/{runId}/commands.log`
- `agents/runs/{runId}/review.json`
- `agents/runs/{runId}/run.json`

The latest run is copied to:

```text
agents/runs/latest.json
```

The dry-run coder prompt is written to:

```text
agents/tasks/current-coder-task.md
```

## Mock Parts

Current v1 mock parts:
- Director provider is `mock`.
- Reviewer provider is `mock`.
- Coder does not auto-run Codex.
- No OpenAI API calls are made.
- No Codex Cloud calls are made.
- Build/lint/test/backtest are skipped when no `package.json` exists.

## Trading Project Constraints

Allowed:
- Auditing project files.
- Writing planning documents.
- Backtesting and simulated trade logs.
- Reading market data from local files.
- Fetching MT5 market data without order execution.

Forbidden:
- Live trading.
- Real MT5 order execution.
- `order_send` or equivalent trading execution calls.
- Secret changes.
- Destructive data deletion.
