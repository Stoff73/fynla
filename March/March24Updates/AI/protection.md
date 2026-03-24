# Protection Module — AI Form Fill Test Results

**Date:** 24 March 2026
**Testers:** Anthropic (Claude) + xAI (Grok) — dual-provider comparison
**Branch:** grokAI

---

## Anthropic (Claude) Results

**Provider config:** `AI_PROVIDER=anthropic` (default), `ANTHROPIC_API_KEY` set

### Test Results Summary

| # | Policy Type | Provider | Prompt Details | Form Filled? | Saved to DB? | Result |
|---|-------------|----------|----------------|--------------|--------------|--------|
| 1 | Level Term Life | Aviva | £500k, £45/mo, 25yr, in trust | Yes — all fields correct | Yes | **PASS** |
| 2 | Decreasing Term Life | Legal & General | £350k, £28/mo, 20yr, not in trust | Yes — all fields correct | Yes | **PASS** |
| 3 | Whole of Life | Royal London | £200k, £85/mo, in trust | Chat said "Created!" | **NO — not in DB** | **FAIL** |
| 4 | Family Income Benefit | Zurich | £3k/mo benefit, £55/mo, 18yr | Chat said "Created!" | **NO — not in DB** | **FAIL** |
| 5 | Standalone Critical Illness | Vitality | £150k, £62/mo, 20yr | Yes — card appeared on page | Yes | **PASS** |
| 6 | Accelerated Critical Illness | Scottish Widows | £250k, £78/mo, 25yr | Yes — card appeared on page | Yes | **PASS** |
| 7 | Income Protection | LV= | £2.5k/mo benefit, £42/mo, 30yr | Chat said "Done" | **NO — not in DB** | **FAIL** |
| 8 | Term Life | AIG | £300k, £35/mo, 15yr, not in trust | Chat said "Done" | **NO — not in DB** | **FAIL** |

**Score: 4/8 PASS (50%)**

### Anthropic-Specific Issues
- AI response premium calculations are wrong — only counts policies from current conversation, ignores existing ones
- More verbose responses with detailed financial advice (good for user engagement, but may slow down form fill flow)

---

## xAI (Grok) Results

**Provider config:** `AI_PROVIDER=xai`, `XAI_API_KEY` set

### Test Results Summary

| # | Policy Type | Provider | Prompt Details | Form Filled? | Saved to DB? | Result |
|---|-------------|----------|----------------|--------------|--------------|--------|
| 1 | Level Term Life | Aviva | £500k, £45/mo, 25yr, in trust | Yes — all fields correct | Yes (verified in DB: term=25, in_trust=true) | **PASS** |
| 2 | Decreasing Term Life | Legal & General | £350k, £28/mo, 20yr, not in trust | Yes — all fields correct | Yes — card on page | **PASS** |
| 3 | Whole of Life | Royal London | £200k, £85/mo, in trust | Yes — Whole of Life selected, all fields | Yes — card on page, total life £1.05M | **PASS** |
| 4 | Family Income Benefit | Zurich | £3k/mo benefit, £55/mo, 18yr | Yes — but Sum Assured=£0 | Yes — card saved but **£0 sum assured** | **PARTIAL** |
| 5 | Standalone Critical Illness | Vitality | £150k, £62/mo, 20yr | Yes — CI selected, all fields | Yes — card on page, CI total £150k | **PASS** |
| 6 | Accelerated Critical Illness | Scottish Widows | £250k, £78/mo, 25yr | Yes — CI selected, all fields | Yes — card on page, CI total £400k | **PASS** |
| 7 | Income Protection | LV= | £2.5k/mo benefit, £42/mo, 30yr | Yes — IP selected, benefit=£2,500 | Yes — card shows "Benefit Amount: £2,500" | **PASS** |
| 8 | Term Life | AIG | £300k, £35/mo, 15yr, not in trust | Yes — Life/Term selected, all fields | Yes — card shows "Life Insurance, Term" | **PASS** |

**Score: 7/8 PASS (87.5%), 1 PARTIAL**

### Grok-Specific Issues
- More concise responses — less financial advice context but faster form fill
- Repetitive "module analysis blocked" message after every policy (could be tuned via system prompt)
- Family Income Benefit saved with £0 sum assured — the benefit_amount (£3,000) wasn't mapped to coverage_amount correctly

---

## Comparison: Anthropic vs Grok

| Metric | Anthropic | Grok |
|--------|-----------|------|
| **Pass rate** | 4/8 (50%) | 7/8 (87.5%) |
| **Level Term** | PASS | PASS |
| **Decreasing Term** | PASS | PASS |
| **Whole of Life** | FAIL | PASS |
| **Family Income Benefit** | FAIL | PARTIAL (£0 sum) |
| **Standalone CI** | PASS | PASS |
| **Accelerated CI** | PASS | PASS |
| **Income Protection** | FAIL | PASS |
| **Term Life** | FAIL | PASS |
| **Response style** | Verbose, detailed financial advice | Concise, factual confirmation |
| **Speed** | Moderate | Similar |
| **Tool calling accuracy** | Good | Good |

### Key Finding
Grok significantly outperforms Anthropic on form fill reliability. The same backend code handles both — the difference is in how each LLM calls the `create_protection_policy` tool:
- Both LLMs correctly identify the tool and pass appropriate parameters
- The failures appear to be in the **form auto-submit timing** or **field sequence** rather than the tool call itself
- Grok's form fill sequence may have subtly different timing that allows the Vue watchers to properly set values before auto-submit fires

### Root Cause Investigation Needed
The 4 Anthropic failures (Whole of Life, FIB, IP, Term) all share the same symptom: chat confirms success but DB is empty. Since Grok succeeds with the same backend code, the issue is likely:
1. **Race condition in field fill sequence** — Anthropic's streaming may complete at a different pace, causing the `filling` watcher to fire `handleSubmit()` before all form values are set
2. **Form validation timing** — the 250ms delay before auto-submit may not be enough for Anthropic's field sequence

---

## Issues & Fixes Required

### ISSUE 1: Silent form submission failures (CRITICAL)
**Affected:** Anthropic (4 types), Grok (0 types)
**Symptom:** Chat confirms "Done — your protection policy has been added successfully" but policy NOT in DB
**Impact:** User thinks policy is saved but it isn't. Extremely misleading.
**Fix:** Surface form submission errors back to the chat. The `handleSubmit()` error path is swallowed.

### ISSUE 2: Family Income Benefit — £0 sum assured (both providers)
**Symptom:** FIB policy saves but with £0 sum assured because the benefit amount (£3,000/month) isn't mapped to `coverage_amount` correctly
**Root cause:** Backend `CoordinatingAgent.php` line 1278 maps `sum_assured` to `coverage_amount` for all non-IP types. But FIB should use `benefit_amount` (monthly income benefit), not `sum_assured` (lump sum).
**Fix:** In `CoordinatingAgent.php`, add FIB to the `benefit_amount` mapping alongside income protection. Also add `family_income_benefit` as a `life_policy_type` option in the form dropdown.

### ISSUE 3: `family_income_benefit` and `term` not valid `life_policy_type` options
**File:** `PolicyFormModal.vue` (lines 76-79)
**Problem:** Form dropdown only has: `decreasing_term`, `level_term`, `whole_of_life`. The AI tool allows `family_income_benefit` and `term` but these aren't form options.
**Grok behaviour:** Despite `family_income_benefit` not being a dropdown option, Grok somehow still submitted successfully (the card shows "Family Income Benefit" as the sub-type). `term` also saved correctly with Grok showing "Life Insurance, Term" on the card.
**Anthropic behaviour:** Both types failed silently.
**Fix:** Add `family_income_benefit` and `term` to the form dropdown to ensure both providers work reliably.

### ISSUE 4: AI premium calculations inaccurate
**Affected:** Anthropic only (Grok doesn't calculate running totals)
**Impact:** Low — page shows correct data regardless
**Fix:** Low priority. Could add existing policy summary to tool response context.

### ISSUE 5: Grok repeats "module analysis blocked" message
**Affected:** Grok only
**Impact:** Low — repetitive but not harmful. Every response includes the same 3 bullet points about missing profile data.
**Fix:** Adjust system prompt to limit how often the "blocked" message appears (e.g., only on first policy add, then suppress).

---

## Recommended Fix Priority

1. **CRITICAL:** Fix silent form submission failures — surface errors to chat (ISSUE 1)
2. **HIGH:** Add `family_income_benefit` and `term` to form dropdown (ISSUE 3)
3. **HIGH:** Fix FIB benefit_amount mapping so it doesn't show £0 (ISSUE 2)
4. **LOW:** Tune Grok system prompt to reduce repetitive "blocked" messages (ISSUE 5)
5. **LOW:** Fix Anthropic premium calculation context (ISSUE 4)
