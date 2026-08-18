# Registry — Tools

**Status:** Drafted from discovery 2026-08-13, session 2. Awaiting CSJ correction.
**Owner:** CSJ. Amendments gated.

Provisioning is just-in-time (workforce design §3.4). This is a map of what
*exists*, not a list to go and action.

---

## 1. MCP servers — project (`.mcp.json`)

| Server | Purpose | Notes |
|---|---|---|
| `mysql` | Local database queries | `127.0.0.1:3306`, database `fynla`, root/no password |
| `playwright` | Browser automation | **The E2E evidence tool** (`08-process.md` §2.2) |
| `ssh-fynla` | Production shell | Local server at `mcp-servers/ssh/server.mjs`. **PROD ONLY.** Guarded by `prod-guard.sh` for destructive commands, but the guard does not check target host. |

## 2. Plugins (`.claude/settings.json`)

`superpowers@claude-plugins-official` · `github@claude-plugins-official`

## 3. Local agent tooling

8 agents in `.claude/agents/` · 18 skills in `.claude/skills/` · 9 hooks in
`.claude/hooks/`. Only four hooks deny; the rest are automation or informational.

**OpenAI Codex — dispatchable backend, expires 18 August 2026.** Ratified session 2.
The Chief of Staff may dispatch execution to Codex or Claude Code until that date;
after it, Claude Code only. Codex is a toolchain, not an actor with its own remit,
and its output passes the same evidence gate. Transition rules in `charter.md` §11.

## 4. External services — from `.env.example` key names

Names only. **No value is ever recorded here or anywhere in the tree.**

| Domain | Service | Keys |
|---|---|---|
| Payments | **Revolut** | `REVOLUT_API_KEY`, `REVOLUT_PUBLIC_KEY`, `REVOLUT_WEBHOOK_SECRET`, `REVOLUT_SANDBOX`, `PAYMENT_ENABLED` |
| AI | **Anthropic**, **xAI**, **OpenAI** | `ANTHROPIC_API_KEY`, `XAI_API_KEY`, `OPENAI_API_KEY`, plus model selectors and `AI_PROVIDER` |
| AI audit | — | `AI_AUDIT_HMAC_KEY` |
| **Analytics** | **Plausible** | `ANALYTICS_ENABLED`, `PLAUSIBLE_DOMAIN`, `VITE_PLAUSIBLE_DOMAIN`. Config at `config/analytics.php`. |
| App Store | **Apple** | `APPLE_STORE_*`, `NATIVE_*`, StoreKit flags |
| Push | **FCM** + **APNS** | `FCM_*`, `APNS_*` |
| Storage | **AWS S3** | `AWS_*` |
| Realtime | **Pusher** | `PUSHER_*` |
| Mail | SMTP | `MAIL_*`, plus a separate marketing sender |
| Affiliate | **Awin** | `config/awin.php`, `deploy/awin/` |
| Issues | **GitHub** | `GITHUB_BUG_ISSUE_TOKEN`, `GITHUB_BUG_ISSUE_REPO` |
| Internal | — | `AGENT_INTERNAL_TOKEN`, `ADMIN_EMAILS`, `SSH_PASSPHRASE` |

## 5. Google — already integrated via service account

**Material finding.** A Google service-account pipeline is **already merged**
(PR #691, `codex/google-service-account-pipeline`). It powers the marketing
content pipeline: Word document in Drive → `InsightArticle`, with real-time Drive
triggers, polling, an approver step and cross-environment publishing.

**So Fynla already has Google Drive access.** What is *not* connected is the
Claude **Drive connector** — a separate thing.

**Ratified 2026-08-13, session 2 A2: the workforce reads Drive through the existing
service account.** No second credential for the same system, no new authorisation,
no spend. The Drive provisioning request is withdrawn. If the service account's
scope does not cover the folder where Meet recordings land, widening that scope is
the request — not a new connector.

## 6. Slack — a log driver exists

`config/logging.php:76` defines a `slack` channel driven by
`LOG_SLACK_WEBHOOK_URL`. That variable is **absent from `.env.example`**, so the
channel is almost certainly unconfigured. It is a Laravel log sink, not a two-way
integration — it does not satisfy the triage requirement (§7 of the workforce
design), which needs the Slack MCP.

## 7. Known gaps

| Gap | Effect |
|---|---|
| Slack MCP unauthorised | No triage, no gate queue, no output channels. Blocks Phases 3–4. |
| GitHub connector unauthorised | Control centre cannot read live state. |
| No `codex/*` toolchain documentation | The workforce cannot reason about work it doesn't know is happening. |
