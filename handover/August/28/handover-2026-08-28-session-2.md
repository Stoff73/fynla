---
type: handover
mode: session-end
date: 2026-08-28
session: 2
repo: fynla
branch: fix/w-0482-unused-pension-fund-in-projected-estate
---

# Session Handover — 2026-08-28, Session 2

## Where things stand

**This was a Hermes multi-agent infrastructure session, not fynla product work.** No board
item was touched and no fynla code was changed. The subject was the two-agent setup on this
Mac: the default profile (now displaying as **Mary**) and **Myrtle**, the Fynla development
assistant on Slack.

**Myrtle is working and CSJ confirmed it.** She now replies as a flat post in `#new-myrtle`
instead of threading, she is genuinely de-cloned from the default profile, her Slack app has
the right scopes for the first time, and she has no WhatsApp access. The Slack app was
reinstalled by CSJ and every previously-broken API call was verified working.

**Mary is half-renamed and CSJ has deferred it to tomorrow.** `hermes profile rename default
Mary` changed only the display name; her `SOUL.md` was rewritten to name her Mary, but she
still introduces herself as "Hermes Agent" in WhatsApp. Two concrete leads are recorded below
under *Decisions and dead ends* — this is the first thing to pick up.

Note the fynla tree is dirty on `fix/w-0482-unused-pension-fund-in-projected-estate` with
W-0482 work plus two new untracked board files (W-0512, W-0513). **That is not from this
session and was deliberately left untouched.** Only the handover was committed.

## Priorities for the next session

1. **Mary still calls herself "Hermes Agent" — DEFERRED BY CSJ to today.** Display name is
   correct everywhere (`hermes profile list` shows `◆Mary (default)`), and
   `~/.hermes/SOUL.md` now opens "You are Mary…". Two leads, cheapest first:
   - **The frozen-prompt explanation.** Hermes assembles the system prompt once per session
     and never re-renders it; SOUL.md sits in the *stable* tier. Her existing WhatsApp
     conversation is still running the old prompt. **Send `/new` in WhatsApp and re-test
     before debugging anything else.** CSJ was told this but it is unconfirmed whether it
     was tried.
   - **The codex fallback.** Mary's provider is `openai-codex`. In
     `agent/codex_responses_adapter.py:1045`, `instructions` falls back to
     `DEFAULT_AGENT_IDENTITY` when empty — and that constant
     (`agent/prompt_builder.py:150`) is literally "You are Hermes Agent, an intelligent AI
     assistant created by Nous Research…", i.e. exactly the string CSJ is seeing. If the
     codex responses path is sending empty instructions, SOUL.md is being bypassed
     entirely. Check which runtime path is live (`compression.codex_responses_native` is
     `false`, `codex_app_server_auto` is `native` in her config).

2. **`/kick @Myrtle` from five Slack channels.** She is still a *member* of `marketing`,
   `all-fynla`, `bugs`, `fyn-decisions`, `fyn-brief`. Her Hermes-side whitelist
   (`SLACK_ALLOWED_CHANNELS=C0BSXLPKG5R`) already stops her responding there, so this is
   defence-in-depth, not urgent. It cannot be done by API — `channels:leave` is not a real
   Slack scope (see dead ends).

3. **Decide what to do with `CLAUDE.md` in the hermes-agent clone.** Created this session via
   `/init` at `/Users/CSJ/Desktop/hermes-agent/CLAUDE.md`, currently untracked. That repo is
   an upstream clone sitting on `main` with no personal `handover/` convention, so it was
   deliberately not committed. Either keep it untracked, or branch before committing.

4. **Consider reporting three bugs in Hermes' Slack manifest generator upstream.** All three
   were found and worked around this session; details in *Decisions and dead ends*. Together
   they mean `hermes slack manifest` currently cannot produce a manifest Slack will accept,
   which is why Myrtle's app had no slash commands at all until today.

5. **Optionally re-audit the Multi-Agent guide against today's findings.** `~/Downloads/
   Hermes_Multi-Agent_System_Guide_v2.docx` is at revision 2.1 and is accurate, but it was
   written before the live-config audit, so it does not mention the manifest generator bugs
   or the `hermes-slack` monolithic-toolset issue.

## Context to load

- `/Users/CSJ/Downloads/Hermes_Multi-Agent_System_Guide_v2.docx` — the corrected multi-agent
  guide (rev 2.1). Appendix C lists every correction made against the original and why.
- `/Users/CSJ/.hermes/profiles/myrtle/config.yaml` — Myrtle's config. The five deliberate
  deltas from Mary's are `terminal.cwd`, the granular `platform_toolsets.slack` list,
  `platforms.slack.extra.reply_in_thread`, `slack.allowed_channels`, and the *absence* of
  any WhatsApp block.
- `/Users/CSJ/.hermes/SOUL.md` — Mary's identity, rewritten this session. Backup alongside it
  as `SOUL.md.bak.*`. This is priority 1.
- `/Users/CSJ/Downloads/myrtle-slack-manifest-fixed.json` — the manifest actually applied to
  the Slack app. Use this as the baseline for any future Slack change; do NOT regenerate from
  `hermes slack manifest` without re-applying the three fixes.
- `/Users/CSJ/Desktop/hermes-agent/CLAUDE.md` — repo guide written this session; see
  priority 3.
- `agent/codex_responses_adapter.py:1045` and `agent/prompt_builder.py:150` (in the
  hermes-agent clone) — the identity-fallback lead for priority 1.

## Completed this session

**Documentation**
- Wrote `/Users/CSJ/Desktop/hermes-agent/CLAUDE.md` — a pointer file deferring to the repo's
  existing 1,784-line `AGENTS.md` rather than duplicating it, plus verified commands
  (`scripts/run_tests.sh`, `ruff check .`, workspace `npm run check`) and a task→section map.
- Published a diagram artefact covering Hermes' memory system, config layering and the
  `HERMES_HOME` profile boundary: https://claude.ai/code/artifact/059f532c-83e0-4437-abd3-fa7695e4018f
- Audited CSJ's `Hermes_Multi-Agent_System_Guide.docx` against the actual source tree and
  produced rev 2.1 at `~/Downloads/Hermes_Multi-Agent_System_Guide_v2.docx`. Substantive
  corrections: the delegation-cannot-cross-profiles error, the `--no-skills` trap, the
  irreversible `--agent-view` flag, missing scopes, and two new sections on Slack reply
  placement vs. message initiation.

**Myrtle — de-cloned and fixed**
- Proved she was a `--clone`: her `.env` was Mary's `.env` with exactly 5 lines appended,
  all 512 preceding lines byte-identical including comments.
- Rewrote her `.env` from 24 inherited keys down to 6 (5× Slack + `GITHUB_TOKEN`). Dropped
  all `WHATSAPP_*`, `XAI_*`, `BROWSERBASE_*`, `BROWSER_*` and four `*_TOOLS_DEBUG` flags.
  Backup at `~/.hermes/profiles/myrtle/.env.bak.20260828_121517`.
- Removed the inherited `platforms.whatsapp` block carrying CSJ's personal chat ID, plus
  three WhatsApp toolset lists, from her `config.yaml`.
- `platforms.slack.extra.reply_in_thread: false` — flat channel replies instead of threads.
- `terminal.cwd: /Users/CSJ/Desktop/fynla` — she previously started in her own profile
  directory and had no project context at all. She now loads fynla's `AGENTS.md`/`CLAUDE.md`.
- `slack.allowed_channels: C0BSXLPKG5R` — confined to `#new-myrtle`; verified it reaches the
  adapter as `SLACK_ALLOWED_CHANNELS`.
- Replaced the monolithic `hermes-slack` toolset with a granular 13-toolset list, dropping
  `image_gen` (CSJ's request) plus `computer_use`, `tts` and `stt`. 32 tools, terminal/file/
  web/skills intact.

**Slack app (A0BR2SFB740, workspace Fynla)**
- Audited the live manifest and cross-checked against the installed token's granted scopes.
  Found `users:read`, `files:write` and `commands` all missing, no `slash_commands` block at
  all, and `messages_tab_enabled: false` (DMs disabled).
- Produced a corrected manifest; CSJ applied it and reinstalled the app.
- Verified post-reinstall: token did not rotate, 25 scopes granted, `users.info` works (was
  `missing_scope`), `files.getUploadURLExternal` returns ok, 50 slash commands registered.

**Cleanup**
- Renamed the default profile's display name to Mary and rewrote `~/.hermes/SOUL.md`.
- Killed PID 59133 — a stray interactive `hermes` CLI running 1h46m on the *default* profile
  with no `HERMES_HOME` set, i.e. a second concurrent writer to Mary's memory. This was the
  "old instance" CSJ could not find. Process table verified clean afterwards: two supervised
  gateways, one WhatsApp bridge under Mary only.
- Identified the two unknown `#new-myrtle` members once `users:read` worked — **Brett
  Isenberg** and **Azlan Raj**, both humans, both already on Myrtle's `SLACK_ALLOWED_USERS`.
  No duplicate bot exists.

## Verification state

- Myrtle end-to-end in Slack: **confirmed working by CSJ**.
- Slack token scopes: verified via `auth.test` response headers after reinstall — all 25
  present including the three that were missing.
- `users.info`: verified against 4 user IDs, all resolve.
- `files:write`: verified via `files.getUploadURLExternal`.
- `SLACK_ALLOWED_CHANNELS`: verified by running the adapter's own `_apply_yaml_config` and
  reading the resulting env var.
- Both gateways: verified running, Mary with `whatsapp: connected`, Myrtle with
  `slack: connected`.
- **Not verified:** that Myrtle's flat replies survive a *thread* reply correctly (a message
  originating inside an existing thread should still be answered in-thread — this is by
  design but was not tested). Not verified that the 50 slash commands actually execute in
  Slack, only that they registered. Not verified that Mary's `/new` fixes her identity.
- **No test suite was run.** No fynla code changed, so `scripts/run_tests.sh` and the fynla
  gates were not exercised.

## Decisions and dead ends

- **`send_message` is deliberately not a model tool.** Hermes registers it in zero toolsets
  by design (`tools/send_message_tool.py`, and the comment in `toolsets.py`). An agent
  answers where it was addressed and cannot originate a message. Do not go looking for it in
  `hermes tools` — the supported routes are `hermes send`, cron `--deliver`, and the kanban
  notifier. **This was initially misdiagnosed as the cause of CSJ's problem; it was not.**
- **The actual reply-placement fix was `reply_in_thread`.** `platforms.slack.extra.
  reply_in_thread` defaults to `true`. Setting it `false` makes top-level channel messages
  get flat channel replies. **Side effect worth remembering:** session scoping changes with
  it — the whole channel becomes one session `(slack, channel_id, None)` rather than one per
  thread, so context accumulates channel-wide.
- **`platforms.slack.reply_to_mode` is a red herring for Slack.** It is documented in the
  Slack config reference but only ever read by the Discord and Telegram adapters. Setting it
  will not change Slack threading.
- **`delegate_task` cannot cross profiles.** It spawns a subagent inside the *same* profile.
  Any "Mary delegates to Myrtle" design must use the kanban board (`hermes kanban create
  --assignee myrtle`, whose dispatcher spawns the assigned profile) or A2A. The original
  guide had this wrong.
- **`channels:leave` is not a real Slack scope.** It was invented while trying to remove
  Myrtle from channels programmatically, and it was one of the manifest errors. Removing a
  bot from a channel is a manual `/kick`.
- **Three bugs in `hermes slack manifest` output.** (a) every slash command carries
  `"url": "https://hermes-agent.local/slack/commands"` while the same file sets
  `socket_mode_enabled: true` — Slack rejects a `url` under Socket Mode, and `.local` is not
  a valid public endpoint anyway; (b) `/compress` and `/context` descriptions exceed Slack's
  100-char limit; (c) `/goal` and `/loop` `usage_hint`s exceed the 64-char limit. All three
  had to be fixed by hand before Slack would accept the manifest.
- **De-clone in place beat a full rebuild.** CSJ chose it. The only genuinely cloned
  artefacts were `.env` and `config.yaml`; rebuilding would have destroyed her sessions and
  memory and required reinstalling the launchd service for no benefit.
- **Myrtle's model auth is not in her profile.** It comes from `~/.codex/auth.json`, shared
  and outside `HERMES_HOME`. Rewriting her `.env` was therefore safe. Do not assume a profile
  is self-contained for provider credentials.

## Things that will bite you

- **Prompt state is frozen per session.** SOUL.md, memory and the skills index are read once
  at session start; the assembled prompt is never re-rendered mid-conversation (this is what
  protects the upstream prompt cache). Any identity or memory edit needs a new session —
  `/new` — before it has any effect. This is the likely explanation for priority 1.
- **`hermes profile rename` is presentation-only.** It sets a `display_name` in
  `profile.yaml` shown in `profile list`, the dashboard and `/profile`. It does **not** touch
  the agent's self-identity, which lives in `SOUL.md`. Renaming an agent means editing both.
- **`hermes profile create --no-skills` is a trap.** It writes a permanent
  `.no-bundled-skills` marker, seeds only the essential `hermes-agent` skill, and makes every
  future `hermes update` skip that profile. `hermes skills install` takes hub identifiers or
  URLs and cannot re-add bundled skills by path. A plain `hermes profile create <name>`
  already copies no secrets.
- **The `hermes-slack` toolset is monolithic.** It is `_HERMES_CORE_TOOLS` wholesale with no
  per-tool switch. To drop a single tool you must replace it with an explicit granular
  toolset list, as was done for Myrtle.
- **A profile is not a sandbox.** On the local terminal backend the agent has the same
  filesystem access as the user account. `terminal.cwd` sets where it starts, not where it is
  confined.
- **Never run two processes against one profile.** Both write memory automatically and each
  loads the other's writes at the next session start, compounding into state nobody authored.
  This is exactly what PID 59133 was doing to Mary.
- **The Chrome MCP extension can only see tabs in its own tab group.** It cannot read a tab
  CSJ has open. Asking CSJ to paste content into a tab the extension created is the way to
  inspect something on their screen.
- **The auto-mode classifier blocks keyboard input into third-party settings pages.** Pasting
  into the Slack app-manifest editor was denied. Read-only inspection is fine.

## Tech debt deferred

No fynla code changed this session, so the tech-debt pass was not applicable. The Hermes
manifest-generator bugs listed above are upstream debt in `hermes_cli/slack_cli.py`, not
fynla debt — see priority 4.

## Branch and deploy state

- Repo: fynla, branch `fix/w-0482-unused-pension-fund-in-projected-estate` at `80e0b3077`
- **The working tree is dirty with W-0482 work that is NOT from this session** — 8 modified
  files plus untracked board items W-0512 and W-0513. Left deliberately untouched. Only
  `handover/August/28/` was staged and committed.
- Unpushed commits before this handover: none
- Deploy status: unchanged; nothing was deployed
- Hermes side: both gateways running under launchd (`ai.hermes.gateway`,
  `ai.hermes.gateway-myrtle`). Myrtle's Slack app reinstalled today.
