# Fyn Chat — Onboarding & Retrieval Analysis (16 Apr 2026 test)

**Source log:** `April/April20Updates/fynChat.md`
**Branch:** `onboardingFyn`
**Analyst:** Claude (session 1, 20 Apr 2026)
**Scope:** Trace every repetition / forgetting / circular reply in the test against the state machine, tool handlers, system prompt builder, and conversation memory. Produce a plan-ready list of fixes.

---

## TL;DR

Four distinct bugs, one per loop the user hit. They are independent — each has its own root cause and a surgical fix. None of them is an LLM regression; all four are backend or prompt logic errors.

| # | Bug | Severity | File | Lines |
|---|-----|----------|------|-------|
| 1 | "Savings" → family loop in `add_more` — selection never updates | **P0** | `app/Services/Onboarding/OnboardingChatDirector.php` | `persistCapture()` 411–464 |
| 2 | Chatty LLM text leaks alongside deterministic retry in `grouped_extract` turns | **P1** | `app/Services/Onboarding/OnboardingChatDirector.php` | `handleGroupedExtractTurn()` 679–714 |
| 3 | `capture_work_details` requires all-or-nothing, forces multi-turn retry loops | **P1** | `app/Agents/CoordinatingAgent.php` | `handleCaptureWorkDetails()` 1007–1045 |
| 4 | Onboarding expenditure writes to `users.monthly_expenditure` but dashboard reads `ExpenditureProfile` — data never surfaces | **P1** | `app/Services/Onboarding/OnboardingChatDirector.php` (capture path) + dashboard readers | state `base_expenditure` 193–200 |

A fifth item is **not a bug** but worth flagging: post-onboarding Fyn retrieval messages hedge ("if the employer details aren't showing fully…") because the system prompt surfaces aggregate financial state but doesn't include `users.employer`/`users.occupation` — see §5 below.

---

## 1. Savings-vs-Family loop (chat lines 85–127)

### Observed

Three identical cycles after `asset_capture`:

```
Fyn: Anything else you'd like to cover?
User: Savings
Fyn: Let me know about the rest of your family, Test. Parents, adult children…
User: None
Fyn: Got it — no family members to record.
```

Then the user typed "I'm done" to escape, and Fyn replied *"Your family module is ready to explore"* — **savings was never reached**.

### Root cause

`OnboardingStateMachine::STATE_ADD_MORE` (`OnboardingStateMachine.php:210-225`):

```php
self::STATE_ADD_MORE => [
    'turn_type' => 'bubbles',
    'prompt_text' => "Anything else you'd like to cover?",
    'bubbles' => [ 'savings', 'investment', 'retirement', 'protection', 'done' ],
    'capture_field' => null,                  // ← no field written
    'next' => self::class.'::nextFromAddMore',
],
```

`nextFromAddMore` routes any non-"done" answer straight to `STATE_ASSET_CAPTURE`, and its docblock **claims**:

> "Anything else advances back into asset_capture for the new selection. The director updates user.onboarding_fyn_selection before calling this helper, so subsequent state evaluation picks up the new focus."

But `OnboardingChatDirector::persistCapture()` never does that update. The specialised handlers cover `base_spouse` / `base_dependants_detail` / `base_dependants`, and the generic `capture_field !== null` path at line 439 short-circuits when `capture_field` is null (which it is for `add_more`):

```php
if ($captureField === null) {
    return;                                   // ← add_more dies here
}
```

Consequence: `asset_capture` is re-entered with the **original** `onboarding_fyn_selection` (`family`, set in `journey_selection`). `buildAssetCaptureIntro` (`OnboardingStateMachine.php:443`) and `OnboardingPromptBuilder::toolsForFocus()` both key off that stale value.

Compounding it — the `filterBubbles` logic (`OnboardingChatDirector.php:271-301`) strips `visited_focuses` from the add_more bubble list. `visited_focuses` is only populated in `STATE_FOCUS_SELECTION` and `STATE_JOURNEY_SELECTION`. The "Savings" pick never gets added, so even when the selection bug is fixed, the same focus can be re-offered on the next `add_more` turn.

### Fix

In `persistCapture()`, add a specialised branch for `STATE_ADD_MORE`:

```php
if ($stateId === OnboardingStateMachine::STATE_ADD_MORE) {
    $bubbleId = $interpretation['captured_value'] ?? null;
    if ($bubbleId !== null && $bubbleId !== 'done') {
        $user->onboarding_fyn_selection = $bubbleId;

        // Mirror what STATE_FOCUS_SELECTION/STATE_JOURNEY_SELECTION do:
        $context = $user->onboarding_fyn_context ?? [];
        $visited = (array) ($context['visited_focuses'] ?? []);
        if (! in_array($bubbleId, $visited, true)) {
            $visited[] = $bubbleId;
        }
        $context['visited_focuses'] = $visited;
        $user->onboarding_fyn_context = $context;
        $user->save();
    }
    return;
}
```

Then delete the misleading docblock claim on `nextFromAddMore` or rewrite it to point to this branch.

**Acceptance test:** start journey → family → asset_capture("No one to add") → add_more → pick "Savings" → Fyn emits the `savings` intro ("Right {first_name}, let's get your savings mapped…"), and on the next add_more the savings bubble is gone.

---

## 2. Chatty LLM text leaks alongside deterministic retry (chat lines 44–61)

### Observed

`base_work` turn after user replied "Dentsu":

```
Fyn: Great, Dentsu — what's your position there and gross annual income (in GBP)?
     5.4k in / 112 out | grok-4-1-fast-reasoning | Show Tool Calls (2)
Fyn: I need three things: the company or trade name, your position, and your
     gross annual income in GBP. Could you share all three?
```

The first assistant message is the LLM's conversational output; the second is the hardcoded `retry_text` from the state config. The user sees both, stacked. Same shape on the second work turn (lines 55–64).

### Root cause

`OnboardingChatDirector::handleGroupedExtractTurn()` filters events from the delegated Claude/Grok call but **passes `content` events through verbatim**:

```php
foreach ($generator as $event) {
    if (($event['type'] ?? '') === 'title') continue;
    if (($event['type'] ?? '') === 'onboarding_field_captured') { /* break */ }
    if (($event['type'] ?? '') === 'done') continue;
    if (($event['type'] ?? '') === 'tool_use') continue;

    yield $event;                             // ← content events flow out
}
```

The restricted system prompt built in `buildGroupedExtractPrompt()` (`OnboardingChatDirector.php:813-842`) says:

> "Do not call any other tool. **Do not emit a text response.**"

Grok-4.1-fast is ignoring the instruction and emitting conversational text **in addition to** the tool call (the "Show Tool Calls (2)" tag confirms both happened). When the tool call then fails (see §3), `emitRetry` yields the deterministic retry content event, so the user sees two assistant messages.

### Fix

Two options — pick one:

1. **Swallow `content` events in grouped_extract delegation** (simplest, zero model dependency):
   ```php
   if (($event['type'] ?? '') === 'content') continue;
   ```
   Rationale: during extraction turns the director owns the conversational surface, not the LLM. Any model-generated text is unauthorised output and should be suppressed.

2. **Enforce `tool_choice=required`** on grouped_extract turns via `XaiClient` / Anthropic `tool_choice: {type: "tool"}`. This prevents the model from emitting an assistant message with no tool call, but doesn't stop it from emitting text *alongside* a tool call — so option 1 is still needed as a belt.

Recommend both: set `tool_choice=required` in `chatWithPromptOverride` when `toolsListOverride` is non-null, **and** swallow `content` events in the director.

**Acceptance test:** replay the "Dentsu" turn. User sees exactly one assistant message (the retry). Token usage drops because the suppressed text no longer streams.

---

## 3. `capture_work_details` requires all-or-nothing (chat lines 42–70)

### Observed

Three user turns to get past `base_work`:

```
User: Dentsu           → retry
User: Chief Marketing officer, £50000  → retry
User: Yes              → state advances, expenditure prompt appears
```

No visible reason the second turn should have retried — the user gave position + income, and the employer (Dentsu) was in history. But the state retried anyway.

### Root cause

`CoordinatingAgent::handleCaptureWorkDetails()` (`CoordinatingAgent.php:1007-1045`):

```php
$employer = trim((string) ($input['employer'] ?? ''));
$occupation = trim((string) ($input['occupation'] ?? ''));
$income = isset($input['annual_income']) ? (float) $input['annual_income'] : null;

if ($employer === '' || $occupation === '' || $income === null || $income < 0) {
    return ['error' => true, 'message' => 'employer, occupation, and annual_income are required'];
}
```

Each `grouped_extract` turn fires a fresh LLM call with the full conversation history, but the tool handler rejects partial captures. The LLM on turn 2 probably called the tool with `{occupation: "Chief Marketing Officer", annual_income: 50000, employer: ""}` (pulling from the current message only) — the handler returned `error: true`, so `captureReceived` stayed false in the director, and the retry fired.

By turn 3 ("Yes") the model had enough history accumulated that it filled all three slots from prior turns and the handler accepted. Each retry costs a full round-trip (~5k input tokens, per the token tags in the log) and frustrates the user.

### Fix

Two layers:

1. **Partial-capture persistence in the handler.** Write whatever fields are present, mark the rest as missing, and return a structured receipt that tells the director what's still needed:

   ```php
   $missing = [];
   if ($employer === '') { $missing[] = 'employer'; } else { $user->employer = $employer; }
   if ($occupation === '') { $missing[] = 'occupation'; } else { $user->occupation = $occupation; }
   if ($income === null || $income < 0) {
       $missing[] = 'annual_income';
   } else {
       $field = $user->employment_status === 'self_employed'
           ? 'annual_self_employment_income' : 'annual_employment_income';
       $user->{$field} = $income;
   }
   $user->save();

   if (count($missing) > 0) {
       return [
           'onboarding_capture' => false,
           'field_group' => 'work',
           'missing' => $missing,
           'summary' => 'Partial work details saved — still need: ' . implode(', ', $missing),
       ];
   }
   // …existing success path
   ```

2. **Director-side follow-up prompting.** When `captureReceived` is false but the receipt carries a `missing` array, compose a targeted retry ("I've got £50,000 as your salary — could you also share the employer and your role?") instead of the generic `retry_text`. This closes the loop in two turns at most.

Alternative (simpler, lower-fidelity): accept any two-of-three fields as success, fill the third from user profile defaults or leave blank. Not recommended — the employer/role gaps this test exposed were real data loss.

**Acceptance test:** user replies "Dentsu" → `users.employer = Dentsu` saved; Fyn asks only for role + income. Reply "CMO, £50k" → both saved, state advances.

---

## 4. Expenditure onboarding ≠ dashboard read path (chat lines 72–166)

### Observed

- Onboarding: user typed "£4000", Fyn accepted (line 76 starts the next prompt).
- Post-onboarding (line 148): user said "My expenses haven't been entered."
- Fyn replied: *"Your total monthly expenditure of £4,000.00 is already captured…"* — then two turns later: *"I've now entered your £4,000.00 monthly expenditure (as 'other' for now)"*.

Both Fyn replies are technically truthful relative to the field they're reading, but they contradict. The user was right: it wasn't surfaced in the dashboard.

### Root cause

`STATE_BASE_EXPENDITURE` (`OnboardingStateMachine.php:193-200`) has:

```php
'capture_field' => 'monthly_expenditure',     // users.monthly_expenditure
```

…which writes the scalar column on `users`. The dashboard cashflow / expenditure components read from the `ExpenditureProfile` model (categorised breakdown). These two stores are not reconciled during onboarding.

The post-onboarding chat ran into this on the user's retrieval question: the system prompt's financial-state snapshot includes `users.monthly_expenditure`, so the first reply claimed success. The second reply is Fyn then calling `update_profile(section="expenditure")` (or an expenditure-specific tool) to actually populate the `ExpenditureProfile` — hence "(as 'other' for now)".

### Fix

When onboarding captures `monthly_expenditure`, also create an `ExpenditureProfile` row in the same transaction. Two reasonable shapes:

- **Single row, category=other** (matches what post-onboarding Fyn did manually): unblocks the dashboard immediately, but hides the category breakdown.
- **Empty ExpenditureProfile + a pending flag**: dashboard renders the total from `users.monthly_expenditure` via a fallback reader until the user enters categories.

The first is lower-risk and matches the current post-hoc behaviour. Add to `OnboardingChatDirector::persistCapture()` after the existing `users.monthly_expenditure` write:

```php
if ($captureField === 'monthly_expenditure' && $capturedValue > 0) {
    ExpenditureProfile::updateOrCreate(
        ['user_id' => $user->id],
        ['monthly_other' => $capturedValue]       // or whichever column the dashboard reads
    );
}
```

Verify the exact dashboard column before shipping — `ExpenditureProfile`'s schema needs to be read to pick the right field.

**Acceptance test:** complete onboarding → visit dashboard → £4,000 monthly total visible without a second post-onboarding chat turn.

---

## 5. Not a bug — post-onboarding retrieval hedging (chat lines 131–142)

Fyn's reply to *"Where is my employment data that I entered?"*:

> "Your employment status and **£50,000.00** annual income are captured in your income details… If the employer (Dentsu) or role (Chief Marketing Officer) details aren't showing fully, you can add or edit them right there on screen."

Given §3's fix, `users.employer` and `users.occupation` should be populated. The reason Fyn hedges is that `SystemPromptBuilder` surfaces aggregate `annual_employment_income` but doesn't thread `employer`/`occupation` into the prompt for non-income queries — so the LLM has no way to confirm those specific fields exist, and defaults to a hedge.

**Recommendation (lower priority, not on the bug ledger):** add `employer` and `occupation` to the user-snapshot section of the system prompt when it's already building the income block. One line change in `SystemPromptBuilder`. Defer until §3 is fixed, re-test, and see whether the hedging persists.

---

## 6. System prompt & token growth (not a bug — observational)

Token profile through the session:

| Turn | Input | Output | System prompt |
|------|-------|--------|---------------|
| base_work #1 | 5.4k | 112 | 270 |
| base_work #2 | 4.8k | 97 | 270 |
| base_dependants complete (in family asset_capture) | 6.0k | 10 | 2,602 |
| add_more loop #1 | 7.1k | 9 | 2,602 |
| add_more loop #2 | 7.4k | 9 | 2,602 |
| post-onboarding retrieval | **36.8k** | 178 | **4,878** |
| dashboard follow-up | 18.2k | 88 | 4,878 |
| expenditure auto-fill | 38.9k | 377 | 4,878 |

The jumps are explained:

- `270 → 2,602`: switch from deterministic turn (director emits the prompt_text directly, no LLM call) to `asset_capture` delegated mode (CoordinatingAgent's full Fyn system prompt, including module summaries).
- `2,602 → 4,878`: onboarding completes, user moves into normal CoordinatingAgent chat where `SystemPromptBuilder` layers KYC gate + QueryClassifier output + user financial snapshot + FCA process instructions + CoreIdentity.
- `7.4k → 36.8k` input: the post-onboarding chat pulls full conversation history (20 messages per `MAX_HISTORY_MESSAGES` in `HasAiChat.php:46`) including the entire onboarding transcript. At 1,500 tokens per onboarding turn × 12 turns that's ~18k of history, plus the fattened system prompt.

Nothing here is broken, but the 36.8k single-request cost is expensive. If onboarding feels sluggish in production, consider summarising the onboarding transcript into a single "onboarding summary" assistant message at `STATE_DONE` and pruning the raw turns from history.

---

## Fix order & effort

Recommended sequence:

1. **§1 (P0) — Savings-vs-Family loop.** ~15 lines in `persistCapture`. Highest user-visible impact; blocks the happy path of the test script.
2. **§2 (P1) — LLM text leak.** 1 line in `handleGroupedExtractTurn` + optional `tool_choice=required` in `chatWithPromptOverride`. Removes duplicate-message confusion.
3. **§3 (P1) — Partial capture in `capture_work_details`.** ~30 lines split across handler + director. Cuts base_work retry count from 3 to 1–2 and stops silent data loss on employer/role.
4. **§4 (P1) — Expenditure ↔ dashboard sync.** ~5 lines in `persistCapture` + schema confirmation. Fixes the "my expenses aren't showing" experience without needing a post-onboarding Fyn turn.

All four are on `onboardingFyn` which is 77 commits behind `origin/dev`. When fixes land, the merge-back will need cross-reference against `dev` (`feedback_merge_branch_conflicts`) — specifically `CoordinatingAgent.php` and `AiToolDefinitions.php` both saw churn in PR #220 tech-debt.

## Test plan

After fixes, re-run the original test verbatim in Playwright (see `critical_browser_testing_law`):

1. Register a fresh user → select "Protecting and growing" journey.
2. DOB `19/02/82`, married.
3. Spouse `Laura, 23/02/87, test2@phailanx.co.uk`.
4. No dependants.
5. Employed → "Dentsu" → expect *partial captured* reply asking for the remaining two.
6. "Chief Marketing Officer, £50000" → expect state advance, single assistant message.
7. Expenditure £4000 → expect a single message.
8. Family asset_capture: "No one to add".
9. add_more: "Savings" → expect savings intro, NOT family re-prompt.
10. After savings: add_more should no longer offer Savings or Family.
11. Finish → "I'm done".
12. Navigate to dashboard — verify £4,000 monthly total visible.
13. Open chat → "Where is my employment data?" → expect confident reply with Dentsu + CMO both named, no hedging.

Any deviation = the fix is incomplete; do not write a completion report until every step above is green in Playwright.
