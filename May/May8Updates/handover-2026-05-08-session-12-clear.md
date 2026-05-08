---
type: handover
mode: context-clear
date: 2026-05-08
session: 12
branch: fyn-net-worth-tool
trigger: context-handover skill — context tripwire fired at ~227k tokens (>97.5% of 200k budget) DURING CSJ's correction message
previous_session: 2026-05-08 session 11 (net-worth bug surfaced + admin-merge process violation reverted)
---

# Context Clear Handover — 2026-05-08, Session 12

## Immediate state

**Investigation-only session — no code changes shipped.** Root-caused the £260k net-worth hallucination as a query-classifier bug (NOT the missing-tool theory the session-11 handover proposed), and confirmed it end-to-end on csjones DB. CSJ then corrected two specific things in my synthesis report and asked for a proper plan based on actual xAI grok-4.3 docs — context tripwire fired before I could re-do that work. **Branch `fyn-net-worth-tool` was created but is empty (same tip as dev, f180d39); will be reused for the actual fix once the corrected plan is approved.**

## The thread

- Auto-resumed session 11's STEP 1 (add `get_net_worth` tool to fix net-worth bug). CSJ INTERRUPTED before I touched code: said "I dont want you just sending requests to the LLM, as it costs money, and is not effecient" and pointed me to the admin AI tab observation that the system prompt is missing — instructed me to investigate via logs/code, present a concrete plan BEFORE doing anything.
- Presented a 6-phase read-only diagnostic plan (xAI docs, code trace, audit trace, csjones DB/log inspection, optional admin UI snapshot, synthesis). CSJ approved.
- Phase 1 (xAI docs) — fetched generic xAI chat-completions docs. **MISTAKE:** generic doc said reasoning_effort accepts only `low`/`high`; I did not read the grok-4.3-specific reasoning-effort page. CSJ's URL `https://docs.x.ai/developers/model-capabilities/text/reasoning#effort-levels` was NOT consulted this session.
- Phase 2 (code trace) — read `AiChatController.php`, `AdviceFyn.php`, `HasAiChat.php`, `AdvicePromptBuilder.php`, `XaiClient.php`, `XaiToolDefinitions.php` (partial), `QueryClassifier.php`, `QuerySchemas.php`. **System prompt IS being sent** (`HasAiChat.php:240-243`, `[['role'=>'system','content'=>$systemPrompt]]` then merged with messages). Confirmed via DB cache-hit data (52.7% on turn 2).
- Phase 3 (audit trace) — found that `ai_messages.system_prompt` stores SHA-256 hash, not full text (`HasAiChat.php:715-720`, April30 F-8 rationale). That's why the admin AI audit shows no prompt content. By design.
- Phase 4 (csjones DB inspection via SSH) — confirmed in real data:
  - chris's conversation 31, message 342 (assistant, "Your net worth is **£260,000.00**...") has `system_prompt = sha256:43c24e710000123bdfceb79f9f431c2b6c5431c96521bac2f3f5327d7236f792`, identical hash to message 340 ("Hi Fyn" reply) — so SAME prompt for both turns.
  - tool_call audit (events 114-127) shows 7x `list_records` for: savings_account, investment_account, dc_pension, db_pension, property, mortgage, estate_liability. **Zero calls for business_interest or chattel.**
  - canonical DB has `BusinessInterest id=3 "Jones Consulting" sole_trader £150,000` and `Chattel id=12 "vintage Rolex Submariner watch" jewelry £15,000` — exactly the £165k Fyn missed.
  - `AiAdviceLog` count for conversation 31 = **0** (factual classifications don't write advice logs per `HasAiChat.php:745-746`).
- Phase 5 (admin UI snapshot) — SKIPPED. DB evidence was conclusive; no need to click around the admin UI to confirm what tinker already showed. CSJ approved skipping.
- Phase 6 (synthesis) — produced root-cause report. **The classifier finding is solid:**
  - `QueryClassifier::classify("What is my net worth?", null)` returns `{primary: "general", related: [], modules: []}` — verified live on csjones via tinker.
  - `general` → `AdviceFyn::engineCallLevelFor("general")` → `"factual"`.
  - `AdvicePromptBuilder.php:149-169` strips Layer 5 (`<financial_context>`) AND Layer 6 (`<existing_records>`) when `isFactual=true`. Layer 5 is what calls `NetWorthService::calculateNetWorth($user)` to put "Total net worth: £598,250" in the prompt. Layer 6 is what lists `BUSINESS: [ID:3 Jones Consulting £150,000]` and `CHATTELS: [ID:12 …Rolex… £15,000]` in the prompt.
  - Net effect: chris's prompt for "What is my net worth?" had NEITHER the canonical answer NOR even mentions of business/chattel. LLM tried to compute via list_records, didn't think to query types it never saw, hallucinated £260k.
  - April30 F-14 was the optimisation that introduced the strip (`AdvicePromptBuilder.php:144-148` comment). The intent was sound for "where's my invoice?" / "take me to goals" but inadvertently breaks "what is my net worth?" because that misclassifies as `general`.
  - I tested 6 phrasings live on csjones: "What is my net worth?" / "Net worth" / "How much am I worth?" → `general` (factual). "What's my financial position" / "How am I doing financially?" → `holistic_health` (holistic). Same user, same DB, very different prompts.
  - **The recommended fix is FOUR LINES in `app/Constants/QuerySchemas.php`:** move dead `'/\bnet\s+worth\b/i'` and similar from the unreachable GENERAL block (line 374-380, dead because `findAllMatches` skips GENERAL) into HOLISTIC_HEALTH (line 229). NOT a new tool.
- **CSJ corrections (mid-tripwire):**
  - **(1) "Why are you referencing OpenAI, we are using xAI?"** — Correct. The Fynla code uses the OpenAI PHP SDK pointed at xAI's base URL (`XaiClient.php:71-74` `withBaseUri($baseUrl)`), so the SDK's request shape is OpenAI-compatible by SDK choice — but when reasoning about parameter semantics and contract for grok-4.3, I MUST read xAI's grok-4.3-specific docs, not assume OpenAI parity. My report's "OpenAI-compatible shape" framing was sloppy.
  - **(2) "reasoning_effort is documented at https://docs.x.ai/developers/model-capabilities/text/reasoning#effort-levels"** — I claimed it was undocumented based on a generic xAI doc page. CSJ correctly pointed me to the model-capabilities reasoning page which DOES document effort levels. **My claim that `'none'` is invalid is unverified — I did not read the right doc.** Next session must read that page in full and verify whether `'none'` is a valid value for grok-4.3 (or what the valid values actually are for grok-4.3 specifically).
- Tripwire fired before I could re-do the report against the proper xAI grok-4.3 docs.

## Files touched this session

**Code: ZERO.** No edits, no writes, no migrations. Investigation only.

Branch created: `fyn-net-worth-tool` off `dev` at `f180d39` — empty (same tip as dev). Will be reused for the actual fix.

Untracked carry-over from session 2 still present (FCA/, FCAsuperchargeApp.md, FCA-Supercharged-Sandbox-Application-Draft.md, Fynla-Narrative-Memo-Template.docx, May/May1Updates/deployFynFix.md, campaigns/, fyn/, personas/, prompts/, tools/). Same standing decision as sessions 2-11 — NOT for committing.

## WIP commit

**No WIP commit made this session.** Investigation produced no working-tree changes — only the empty `fyn-net-worth-tool` branch and the standing untracked carry-over (deliberately uncommitted across sessions 2-11). Skill rule "always WIP-commit" exists to capture in-flight work; there was none.

Next session will land its work on `fyn-net-worth-tool` (or a fresh branch off dev — see Pick up from here for branching guidance).

## Open decisions (auto-resume defaults)

1. **Where does the fix live?** Default is `fyn-net-worth-tool` (already exists, empty). Could also be a fresher name like `fyn-net-worth-classifier` since the fix is in the classifier, not a new net-worth tool. **Auto-resume default: rename branch to `fyn-net-worth-classifier` for clarity, OR stay on `fyn-net-worth-tool` if that feels close enough — CSJ to redirect if wrong.**
2. **`reasoning_effort` follow-up — same PR or separate?** Once next session reads the grok-4.3 docs and decides what `reasoning_effort` value is correct (`'none'` may be valid for grok-4.3 — I was wrong to claim it isn't), CSJ may want a corrective PR if `'none'` is invalid for grok-4.3 specifically. **Auto-resume default: keep separate from the classifier fix — two atomic PRs, classifier first because it's the user-facing bug.**
3. **Admin AI audit prompt visibility (sha256 hash vs full text)** — F-8 PII rationale at `HasAiChat.php:700-714` is sound; CSJ asked about visibility. **Auto-resume default: do NOT touch this — it's a deliberate tradeoff, can revisit later if CSJ wants to inspect prompts.**
4. **`get_net_worth` tool that session-11 handover recommended** — **REJECTED by this session's investigation.** The data is already in NetWorthService and would already reach the prompt if the classification were right. Adding a new tool would mask the real bug. Auto-resume default: do NOT add the tool; fix the classifier instead.

## Pick up from here (auto-continue contract)

**STEP 1 — Re-do the xAI doc read with the correct URLs (CSJ's explicit ask).**

1. WebFetch `https://docs.x.ai/developers/model-capabilities/text/reasoning#effort-levels` and read in full.
2. WebFetch other grok-4.3-specific pages: `https://docs.x.ai/docs/models` (find grok-4.3 entry), `https://docs.x.ai/developers/model-capabilities/text/` (any text capability page that mentions grok-4.3), `https://docs.x.ai/docs/api-reference#chat-completions` (rest of the chat completions reference).
3. Verify against the live code in `app/Traits/HasAiChat.php` lines 245-258:
   - `'temperature' => 0` — valid for grok-4.3?
   - `'reasoning_effort' => 'none'` — valid effort level for grok-4.3? Or must it be `'low'` / `'high'` / `'minimal'` / something else? **CSJ specifically pointed me at this page; the previous report was wrong on this point.**
   - `'stream' => true` + `'stream_options' => ['include_usage' => true]` — supported on grok-4.3?
   - `'tools' + 'tool_choice' => 'auto'` — supported per grok-4.3 capabilities?
   - `x-grok-conv-id` header (`XaiClient.php:77`) — verify this is documented and correct.
4. Document findings as a 5-line "xAI grok-4.3 contract verdict" block. Each line cites the specific doc URL/section it came from. NO assumptions, NO OpenAI-parity reasoning.

**STEP 2 — Present the CORRECTED synthesis report to CSJ.**

Same structure as the report I wrote at the end of session 12 (root cause is the classifier, fix is 4 lines in QuerySchemas.php), but with the xAI section rewritten against the proper docs. Specifically:

- Replace "OpenAI-compatible shape" framing with "xAI's chat-completions API expects this shape per their docs at <URL>".
- Replace "reasoning_effort='none' is undocumented" with whatever the xAI grok-4.3 reasoning-effort page actually says about `'none'` (it MAY be valid — CSJ implied it is).
- Either reaffirm or retract the H7 ("temperature=0 + reasoning_effort=none degenerate output") hypothesis based on what the docs actually say grok-4.3 does at each effort level.

The classifier finding stands as-is — it's independent of the xAI question and was verified live on csjones DB.

**STEP 3 — Wait for CSJ approval, THEN implement the classifier fix.**

The fix (when approved):

In `app/Constants/QuerySchemas.php`:

(a) ADD to `KEYWORD_PATTERNS[HOLISTIC_HEALTH]` (line 229), at the top of that array:

```php
'/\bnet\s+worth\b/i',
'/\b(how\s+much|what)\s+(am|are)\s+(i|we)\s+worth\b/i',
'/\b(total|combined)\s+wealth\b/i',
'/\bwhat\s+do\s+i\s+own\b/i',
'/\bshow\s+me\s+my\s+net\s+worth\b/i',
```

(b) REMOVE the dead duplicates from `KEYWORD_PATTERNS[GENERAL]` (line 374-380) — those patterns never trigger because `QueryClassifier::findAllMatches` skips GENERAL at line 157. Leaving them there is misleading for future readers.

(c) Verify locally in tinker that `QueryClassifier::classify("What is my net worth?", null)` now returns `primary: "holistic_health"` (was `"general"`).

(d) Open PR `fyn-net-worth-tool → dev` (or whichever branch name STEP 1's first decision settled), title something like `fix(ai): route net-worth queries to holistic_health so financial_context layer is included`. Body must explain the £260k symptom + the F-14 strip cause + the 4-line fix. **DO NOT admin-merge.** Wait for CSJ.

(e) After CSJ merges to dev: SSH csjones, `git pull origin dev`, optimize cycle, then ASK CSJ for the MFA code (per CLAUDE.md) and Playwright-verify with chris@fynla.org → "What is my net worth?" → assistant must reply £598,250 (matching dashboard). Test 5 phrasings to confirm coverage: "Net worth", "What is my net worth?", "How much am I worth?", "Show me my net worth", "Combined wealth".

(f) If GREEN on csjones: open release PR dev → main, do not admin-merge. Coordinate with the still-open PR #263 (temperature=0) — both need to land before the prod xAI grok-4-1 cutoff on **2026-05-15 (7 days from this handover)**.

**STEP 4 — Independent of STEP 1-3: PR #263 (temperature=0)** still awaiting CSJ review. Don't admin-merge.

**STEP 5 — Independent: prod deploy.** Once classifier fix + PR #263 are on main AND csjones-verified, the upload list grows from session-10's 8 files to ~10:

1. `app/Http/Controllers/Api/AdminController.php`
2. `app/Services/AI/ConversationSummariser.php` (PR #263)
3. `app/Services/AI/XaiClient.php`
4. `app/Services/AI/XaiToolDefinitions.php`
5. `app/Services/Documents/AIExtractionService.php` (PR #263)
6. `app/Services/Documents/DocumentProcessor.php`
7. `app/Traits/HasAiChat.php`
8. `app/Traits/HasAiGuardrails.php`
9. `app/Agents/CoordinatingAgent.php`
10. `config/services.php`
11. `app/Constants/QuerySchemas.php` ← classifier fix
12. (possibly) `app/Services/AI/HasAiChat.php` — if reasoning_effort needs adjustment based on STEP 1 doc reading

Browser-verify on prod with CSJ-supplied MFA — "What is my net worth?" must match prod-DB canonical figure.

## What the next Claude needs to know

- **CSJ corrected me on TWO points and I owe a clean re-do, not a hand-wave.** The classifier root-cause finding is solid (verified live on csjones DB); the xAI / reasoning_effort half of my report was sloppy. Read the docs CSJ pointed at BEFORE writing anything.
- **The session-11 handover's recommended fix (`get_net_worth` tool) was wrong** — investigation showed the data is already produced by `NetWorthService::calculateNetWorth()` and would already be in the prompt if the classification were right. Adding a tool would mask the real bug. The recommended fix is the 4-line classifier change.
- **DB evidence on csjones is conclusive and reproducible** — the tinker queries I ran in session 12 will reproduce. If next session needs to re-verify, run:
  ```bash
  ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && php artisan tinker --execute='echo json_encode(app(\App\Services\AI\QueryClassifier::class)->classify(\"What is my net worth?\", null));'"
  ```
  Should return `{"primary":"general","related":[],"modules":[]}` until the fix lands.
- **Memory file `feedback_never_minimize_bugs.md`** — applies. Don't say "the temperature thing is minor" if next session's docs reading shows it's actually broken. CSJ would rightly call that out.
- **Memory file `feedback_loop_until_correct.md`** — once the classifier fix is approved, loop until £598,250 shows in the browser, no early exit.
- **Memory file `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`** — open PR, wait for CSJ, deploy, browser-verify, THEN admin-merge if and only if CSJ has authorised. Two open PRs (classifier + #263) need separate green-lights.
- **csjones is a git checkout tracking origin/dev** — deploy via `git pull origin dev`, NOT rsync. Only `public/build/` SPA bundle uploaded manually (this fix is PHP-only, no SPA bundle needed).
- **fynla.org production is NOT a git checkout.** SiteGround File Manager upload only. Confirmed session 10.
- **xAI grok-4-1 retires 2026-05-15 (7 days)** — prod still on grok-4-1-fast-reasoning. Classifier fix + PR #263 + the original session-10 grok-4.3 + reasoning_effort bundle all need to land on prod before then or chat hard-fails.
- **Vault-sync deferred — sessions 6, 7, 8, 9, 10, 11, 12 of May 8 not synced.** 7 sessions to batch via Haiku 4.5 subagent on next eod wrap.
- **CONTEXT TRIPWIRE FIRED MID-CSJ-MESSAGE.** CSJ's correction asked me to redo the xAI half properly; the tripwire interrupted before I could. Next session must NOT skip STEP 1 doc reading thinking "the previous instance probably already covered it" — they didn't.

## Branch / deploy state

- **Branch:** `fyn-net-worth-tool` at `f180d39` (same as `dev`, EMPTY — no commits this session)
- **Behind/ahead origin:** new branch, not pushed
- **dev** at `f180d39` — unchanged from session 11
- **main** at `2edeb27` — unchanged
- **temperature-zero-everywhere-v2** at `5ca7118` — PR #263 still open, REVIEW_REQUIRED, BLOCKED
- **csjones.co/fynla:** at `2575ce3` (per session 11 deploy; doc-only delta on dev `f180d39` not yet pulled but that's fine — handover doc only)
- **fynla.org production:** UNCHANGED at `3c47e2a` — still on grok-4-1-fast-reasoning, **hard cutoff 2026-05-15**
- **Deploy status:** Nothing deployed this session. Investigation only.
