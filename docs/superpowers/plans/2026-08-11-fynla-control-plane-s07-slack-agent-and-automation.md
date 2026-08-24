# Fynla Control Plane S07 Slack Agent and Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Slack a permission-aware conversation and orchestration surface that answers with sources, participates selectively, creates/steers Codex or Claude jobs, and automatically turns proven green fixes into tested draft PRs.

**Architecture:** The SiteGround gateway verifies and persists Slack events, acknowledges immediately, maps Slack identity/channel/thread to one principal and policy context, then dispatches asynchronous reasoning/jobs. All answers use S03 authorised retrieval; all mutations use S02 envelopes/risk/approval/jobs and S04 workers. One Slack thread controls one job lineage.

**Tech Stack:** PHP 8.2 Slack ingress/services, MariaDB, Slack Events/Web APIs, GitHub Actions reasoning workers, PHPUnit, pytest, Slack fixture workspace/channel acceptance.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Depends on S02 core, S03 retrieval, S04 workers, S05 releases and S06 job/approval views.
- Repository: `Fynla/FynlaMCP`.
- Slack signatures are checked against raw bytes and events are acknowledged within three seconds; long work is asynchronous.
- Slack user, workspace, channel, thread and DM identities never imply platform access without explicit mapping/policy.
- DM content is not added to shared knowledge by default.
- Green automation may push its generated branch and open/update a draft PR only. It cannot merge or deploy.
- Proactive participation is confidence-gated, rate-limited, thread-only and independently disableable.

---

## File Structure

```text
gateway/database/{010_slack_agent.sql,011_shadow_automation.sql}
gateway/src/Slack/
├── SlackController.php
├── SignatureVerifier.php
├── EventRouter.php
├── SlackContext.php
├── AnswerService.php
├── ConversationPolicy.php
├── TaskIntentService.php
├── SteeringService.php
├── ShadowDecisionService.php
├── AutomationService.php
├── ParticipationService.php
└── SlackClient.php
gateway/tests/{Unit,Feature,Integration}/Slack/
src/fynla_agent/slack/{reasoning,normalise,participation}.py
tests/{unit,acceptance}/slack/
docs/implementation-evidence/s07/
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S07-PR01 | Verified mapped channel/DM/thread ingress | S02-PR02 | Not started |
| S07-PR02 | Permission-filtered sourced answer mode | S03-PR05, S07-PR01 | Not started |
| S07-PR03 | Explicit dispatch, state reporting and steering | S04-PR06, S07-PR01 | Not started |
| S07-PR04 | Human-reviewed shadow risk mode | S07-PR02, S07-PR03 | Not started |
| S07-PR05 | Green automatic tested draft PR path | S07-PR04 | Not started |
| S07-PR06 | Proactive participation and rate limits | S07-PR02, S07-PR05 | Not started |

## S07-PR01 — Generalise verified Slack ingress and identity context

**Branch:** `codex/icp-s07-pr01-slack-ingress`

**Traceability:** `SLK-02..09`, `SEC-27`.

**Acceptance:** Mentions, allowlisted channel messages, mapped DMs and thread replies are signature/timestamp/replay checked, deduplicated, persisted, acknowledged under three seconds and resolved to an active principal/context before work dispatch.

### Task S07-PR01-T01 — Persist Slack conversations and delivery receipts

**Files:** `gateway/database/010_slack_agent.sql`, `gateway/tests/Integration/Slack/SlackMigrationTest.php`.

Create `slack_workspaces`, `slack_channels`, `slack_events`, `slack_threads`, `slack_messages`, `slack_delivery_receipts`, `slack_job_bindings`, with unique `(workspace_id,event_id)`, channel policy and no shared-index flag for DMs.

- [ ] Add MariaDB schema tests for keys, timestamps, retention flags and one-job-lineage-per-controlling-thread constraint.
- [ ] Run the focused migration test; expect failure.
- [ ] Add migration 010 and source classification fields; store Slack text only according to configured short job-context retention.
- [ ] Re-run test; expect pass.
- [ ] Commit `[ICP S07/PR01/T01] Add bounded Slack conversation state`.

### Task S07-PR01-T02 — Verify, map and acknowledge all supported events

**Files:** `gateway/src/Slack/SignatureVerifier.php`, `EventRouter.php`, `SlackContext.php`, `gateway/tests/Feature/Slack/SlackIngressTest.php`.

```php
final readonly class SlackContext {
    public function __construct(
        public string $workspaceId,
        public string $channelId,
        public string $threadTs,
        public string $slackUserId,
        public string $principalId,
        public bool $isDirectMessage,
    ) {}
}
```

- [ ] Test Slack URL verification, valid/invalid signature, timestamp skew, duplicate event, unmapped workspace/user, disallowed channel, DM, mention, thread message, bot loop and edited/deleted event.
- [ ] Run focused tests; expect failures against current limited routing.
- [ ] Verify raw body before JSON decode, persist/deduplicate, enqueue a lightweight internal job and return 2xx before external API/model calls.
- [ ] Drop bot/self events and never index DM content into shared knowledge.
- [ ] Re-run tests with a response-time assertion below 500 ms locally.
- [ ] Commit `[ICP S07/PR01/T02] Map and acknowledge Slack events safely`.

### PR S07-PR01 review gate

- [ ] Replay and timestamp attacks fail without queued work.
- [ ] Run 1,000-event burst; measure acknowledgement p95 and deduplication.
- [ ] Confirm unmapped/denied events expose no repository or knowledge names.
- [ ] Record Slack app scopes and verify they are least privilege.

## S07-PR02 — Answer Slack questions with authorised citations

**Branch:** `codex/icp-s07-pr02-sourced-answers`

**Traceability:** `SLK-10..15`, `KNW-31`, `SEC-28`.

**Acceptance:** Direct mentions, relevant questions in allowlisted channels and mapped DMs receive concise answers whose retrieval was filtered by the Slack principal and whose citations/freshness are canonical; unsupported/degraded answers say so.

### Task S07-PR02-T01 — Create answer eligibility and retrieval service

**Files:** `gateway/src/Slack/ConversationPolicy.php`, `AnswerService.php`, `gateway/tests/Unit/Slack/ConversationPolicyTest.php`, `gateway/tests/Integration/Slack/AnswerServiceTest.php`.

- [ ] Test mention, relevant allowlisted question, mapped DM, social message, low confidence, denied source and direct job/status question.
- [ ] Run focused tests; expect failure.
- [ ] Resolve effective classifications/resources through S02 before calling S03 search; attach principal/correlation IDs to the reasoning job.
- [ ] Require every factual context item to include canonical URL/version/freshness; no context means a transparent limitation response.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S07/PR02/T01] Retrieve Slack answers within requester access`.

### Task S07-PR02-T02 — Render and deliver answer threads idempotently

**Files:** `gateway/src/Slack/SlackClient.php`, `AnswerRenderer.php`, `gateway/tests/Feature/Slack/SlackAnswerDeliveryTest.php`, `tests/acceptance/slack/test_sourced_answers.py`.

- [ ] Test escaped Slack markup, citation links, freshness labels, degraded state, message length splitting, retry, duplicate delivery and inaccessible source omission.
- [ ] Run focused tests; expect failure.
- [ ] Post in the source thread with one delivery idempotency key and store Slack message receipt.
- [ ] Include concise answer, source list and dashboard job link; never expose hidden source titles.
- [ ] Run acceptance cases for founder/developer/product fixtures.
- [ ] Commit `[ICP S07/PR02/T02] Deliver cited Slack answers once`.

### PR S07-PR02 review gate

- [ ] Run S03 forbidden-source goldens through Slack identities.
- [ ] Confirm DM answer context can use permitted shared sources but DM text is not indexed.
- [ ] Disable/degrade knowledge and verify transparent response.
- [ ] Record source/freshness and duplicate-delivery evidence.

## S07-PR03 — Dispatch and steer Codex/Claude jobs from one Slack thread

**Branch:** `codex/icp-s07-pr03-dispatch-steering`

**Traceability:** `SLK-16..27`, `JOB-48..53`.

**Acceptance:** An explicit Slack request becomes a reviewed envelope, green/amber/red outcome and one job; meaningful events return to its controlling thread; authorised users can cancel, narrow, add criteria/tests, switch assistant or ask status without silently mutating an attempt.

### Task S07-PR03-T01 — Normalise explicit coding intent into an envelope

**Files:** `gateway/src/Slack/TaskIntentService.php`, `gateway/tests/Unit/Slack/TaskIntentServiceTest.php`, `tests/fixtures/slack/task-intents.json`.

- [ ] Add fixtures for explicit fix, vague complaint, status question, assistant choice, repository mention, finance/auth/migration work and malicious branch/path text.
- [ ] Run focused tests; expect failure.
- [ ] Derive requester/source/callback/repository/permissions/release/risk through S02; ask one focused clarification when repository or testable acceptance cannot be determined.
- [ ] Keep Slack text as task data and generate idempotency from workspace/event plus resolved repository.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S07/PR03/T01] Convert Slack requests into safe envelopes`.

### Task S07-PR03-T02 — Report job state and handle steering commands

**Files:** `gateway/src/Slack/SteeringService.php`, `JobStatusRenderer.php`, `gateway/tests/Integration/Slack/SteeringServiceTest.php`, `tests/acceptance/slack/test_job_thread.py`.

- [ ] Test accepted, investigating, implementing, validating, needs approval, draft PR, failed, cancel requested/cancelled and timeout rendering.
- [ ] Test authorised/unauthorised cancel, narrower scope, new acceptance, more tests, assistant switch and explanation request.
- [ ] Run focused tests; expect failure.
- [ ] Bind one controlling thread to the job lineage; material scope/assistant changes cancel safely and create a revised envelope/replacement attempt.
- [ ] Coalesce noisy events and post only meaningful state transitions idempotently.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S07/PR03/T02] Steer jobs without mutating active scope`.

### PR S07-PR03 review gate

- [ ] Dispatch fixture jobs to Codex and Claude and trace every event to dashboard/audit.
- [ ] Attempt steering by an unassigned user and from another thread; expected denial.
- [ ] Change assistant mid-run; confirm old attempt cancels and new envelope pins its own release.
- [ ] Record clarification, approval and cancellation evidence.

## S07-PR04 — Run human-reviewed shadow autonomy

**Branch:** `codex/icp-s07-pr04-shadow-autonomy`

**Traceability:** `SLK-28`, `JOB-54..58`, `OPS-09`.

**Acceptance:** The agent predicts whether an eligible Slack request could auto-start and creates a complete proposed envelope, but never launches mutation; human reviewers record agreement/correction and metrics are immutable.

### Task S07-PR04-T01 — Persist shadow decisions and reviewer outcomes

**Files:** `gateway/database/011_shadow_automation.sql`, `gateway/src/Slack/ShadowDecisionService.php`, `gateway/tests/Integration/Slack/ShadowDecisionServiceTest.php`.

- [ ] Add `shadow_decisions` with envelope/risk hashes, classifier release, decision, hard-rule hits, confidence, reviewer decision/reason and timestamp.
- [ ] Test no shadow record can dispatch a write job and later policy changes do not rewrite historical decisions.
- [ ] Run focused tests; expect failure.
- [ ] Implement record/review/metrics APIs with immutable original payload.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S07/PR04/T01] Record immutable shadow decisions`.

### Task S07-PR04-T02 — Add shadow review UI and evaluation corpus

**Files:** `gateway/ui/src/views/slack/ShadowReview.vue`, `tests/integration/shadow-review.test.ts`, `tests/golden/risk/slack-shadow.yaml`, `tests/acceptance/slack/test_shadow_mode.py`.

- [ ] Test reviewer sees task, acceptance, repository, risk reason, allowed/prohibited actions, tests and confidence before agreeing/correcting.
- [ ] Add at least 40 fixtures, including all protected domains and ambiguous prompts.
- [ ] Ensure workflow dispatch spy remains zero for every shadow case.
- [ ] Calculate consecutive agreement, false-green count and category breakdown.
- [ ] Run Chrome acceptance and corpus evaluation.
- [ ] Commit `[ICP S07/PR04/T02] Review shadow autonomy decisions`.

### PR S07-PR04 review gate

- [ ] Collect at least 20 consecutive real shadow decisions with human agreement.
- [ ] Require zero amber/red false-green results in approved corpus and real sequence.
- [ ] Confirm classifier release and envelope hash are reconstructable.
- [ ] Obtain founder/security approval before S07-PR05 feature enablement.

## S07-PR05 — Enable bounded green automation to tested draft PR

**Branch:** `codex/icp-s07-pr05-green-automation`

**Traceability:** `SLK-29..34`, `JOB-59..65`, `OPS-10`, `SEC-29`.

**Acceptance:** When enabled for a repository and category, an explicit concrete green Slack fix automatically launches the selected approved adapter and returns a tested draft PR; every unmet gate becomes clarification/approval/denial and no merge/deploy capability exists.

### Task S07-PR05-T01 — Enforce all automatic-start gates

**Files:** `gateway/src/Slack/AutomationService.php`, `gateway/tests/Unit/Slack/AutomationServiceTest.php`, `gateway/tests/Security/Slack/GreenAutomationBoundaryTest.php`.

- [ ] Test feature switch, repository allowlist, requester permission, concrete task, testable acceptance, known tests, local/reversible paths, confidence, cost/quota and protected-domain hard rules.
- [ ] Run focused tests; expect failure.
- [ ] Implement `AutomaticStartDecision` with stable deny/clarify/approve/start reason codes and a complete audit payload.
- [ ] Require a current release and valid worker bootstrap; never downgrade amber/red.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S07/PR05/T01] Enforce conservative green-start policy`.

### Task S07-PR05-T02 — Complete Slack-to-draft-PR acceptance

**Files:** `tests/acceptance/slack/test_green_to_draft_pr.py`, `tests/fixtures/coding/green-repository/`, `gateway/tests/Feature/Slack/AutomaticDraftPrTest.php`.

- [ ] Create fixture cases for text defect, narrow presentation bug, deterministic lint fix, docs fix and bounded regression with existing test.
- [ ] Add non-green neighbouring cases for financial display calculation, auth text with logic impact, migration and multi-repo request.
- [ ] Run acceptance before feature wiring; expect no automatic dispatch.
- [ ] Wire S07 to S02 job creation and S04 result thread updates behind default-off repository/category switches.
- [ ] For green fixtures assert one branch, passing required tests, one draft PR, assistant/release/test details and no merge/deploy permission.
- [ ] Commit `[ICP S07/PR05/T02] Deliver green fixes as tested draft PRs`.

### PR S07-PR05 review gate

- [ ] Re-run all shadow/high-risk/malicious prompt fixtures; false-green count must be zero.
- [ ] Fail required tests and prove no PR/branch push.
- [ ] Inspect GitHub token permissions and protected-branch rules.
- [ ] Enable only one fixture/pilot repository after founder approval; leave production repository switches off until S08 rollout.

## S07-PR06 — Participate proactively without becoming noisy or unsafe

**Branch:** `codex/icp-s07-pr06-proactive-participation`

**Traceability:** `SLK-35..44`, `OPS-11`, `SEC-30`.

**Acceptance:** In explicitly enabled channels the agent interjects only when it has a high-confidence material contribution, posts in-thread, respects per-channel/user/time budgets and can be disabled instantly.

### Task S07-PR06-T01 — Decide when participation is useful

**Files:** `gateway/src/Slack/ParticipationService.php`, `src/fynla_agent/slack/participation.py`, `gateway/tests/Unit/Slack/ParticipationServiceTest.php`, `tests/golden/slack/participation.yaml`.

- [ ] Add positive fixtures for sourced technical/company question, defect, SOP contradiction, unresolved durable decision, known incident/issue/PR and concrete routable task.
- [ ] Add negative fixtures for social chat, speculation, duplicate help, inaccessible evidence, recently answered thread, sensitive/non-allowlisted channel and low confidence.
- [ ] Run tests; expect failure.
- [ ] Require approved evidence, materiality reason, confidence threshold and active channel feature switch.
- [ ] Return silence as an explicit audited classifier outcome, not an error.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S07/PR06/T01] Gate proactive Slack participation`.

### Task S07-PR06-T02 — Enforce rate limits, threading and kill switch

**Files:** `gateway/src/Slack/ParticipationRateLimiter.php`, `gateway/tests/Integration/Slack/ParticipationRateLimiterTest.php`, `tests/acceptance/slack/test_proactive_participation.py`.

- [ ] Test per-thread once-per-window, per-channel hourly/daily cap, user quiet period, duplicate semantic event, global disable and channel disable.
- [ ] Run focused tests; expect failure.
- [ ] Persist rate windows transactionally, always reply in thread, and check kill switch immediately before delivery.
- [ ] Expose participation counts/silence reasons in dashboard without message bodies by default.
- [ ] Run fixture-channel acceptance and verify no top-level unsolicited messages.
- [ ] Commit `[ICP S07/PR06/T02] Rate-limit and control Slack interjections`.

### PR S07-PR06 review gate

- [ ] Run the full participation corpus with human reviewer and record precision.
- [ ] Toggle global/channel switches during queued delivery; expected no post.
- [ ] Confirm denied sources cannot influence participation or citations.
- [ ] Enable one test channel only; expand through S08 staged rollout.

## Section S07 Completion Gate

- [ ] All six PRs are merged with valid evidence.
- [ ] Slack acknowledgement p95 is below 2.5 seconds under agreed load.
- [ ] Answers have permitted citations/freshness and DMs are not shared-indexed.
- [ ] Explicit dispatch and steering work for both assistants with complete job lineage.
- [ ] Shadow gate has 20 consecutive agreements and zero protected false-green outcomes.
- [ ] Green automation creates only tested draft PRs; merge/deploy remains unavailable.
- [ ] Proactive participation is useful, thread-only, rate-limited and independently disableable.
