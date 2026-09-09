# Fyn wiring Batch E — evidence (F5, F6, F10, F11, F14)

Branch `fix/fyn-wiring-batch-e` off dev `1201bf7f1`, 9 September 2026. CSJ's decisions (08:31): F4 one home (Batch F); F5 one home, no duplicates; F6 one place; F10 drop; F11 the tools answer every tax question from the composed plan; F14 nullable.

| Finding | Fix | Evidence |
|---|---|---|
| F5 | `FynSystemPrompt::fcaProcess()` is the one home; `FcaProcessInstructions::getFcaProcess()` reads it | Block byte-identical before the change (1,288 chars both sides); `FynSystemPromptTest`, `PromptOverlayGoldenMasterTest`, `UnifiedPromptAdviceSeamTest`, `Rule9NoAcronymsInFynVocabularyTest`: 16 passed |
| F6 (part) | `campaign_verify_more` state, `nextFromVerifyMore`, `verifyPromptMore` and the corpus entry deleted; tests moved to `verifyPromptAnnounce` | Onboarding families in the consolidated pass. **Open for CSJ:** the `profile_review_expenditure` → `campaign_intro` chain (see the artifact) |
| F10 | `(urgency: N/100)` removed from the ranked list; "overall score" removed from the `generate_financial_plan` description; four tool-schema fixtures re-recorded with `CAPTURE_TOOL_SCHEMA_GOLDEN=1` / `CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1` | Both golden-master tests green without the flag (14 passed) |
| F11 | Composed tax plan feeds Fyn's tax recommendations on both engine levels with `definition_key = strategy_{type}`; `tax` analysed on the module path; `get_recommendations()` mandatory for `tax_optimisation` and `investment_tax`; triggers name seeded `strategy_*` rows; tax phrasing patterns widened | Tinker, peak earners persona (user 65), "How can I reduce my tax bill this year?" → `tax_optimisation`; `analyzeRelevantModules` returns six tax recs (`strategy_additional_rate_avoidance` first); `<financial_context>` renders "Shift income out of the 45% additional-rate band … Estimated saving: £17,330". `orchestrateAnalysis` carries the same six. `QuerySchemasTest` guards the trigger lists |
| F14 | Migration: twenty category columns nullable, all-zero rows backfilled to NULL | Run locally: 9 of 21 users backfilled; web Expenditure tab and `/m` expenditure screen checked for a NULL-category user (below) |

## F14 live check

John (`john@example.com`, user 11, Free; every category NULL after the backfill, `monthly_expenditure` NULL):

- **Web** `/valuable-info?section=expenditure` (Playwright, 1280 px): the Expenditure section renders Simple Total mode with £0 and no script errors (the five console errors on the page are the pre-existing 403s on the letter-to-spouse and will endpoints for a Free account). Clicked Edit, entered £2,500 in the simple monthly field, clicked Save Changes: the summary shows £1,250 monthly / £15,000 a year (the household is joint 50/50, so John's share), and the row reads `monthly_expenditure = 1250.00`, `expenditure_entry_mode = simple`, `childcare = NULL`. The category columns were not touched by a simple-mode save.
- The Detailed Breakdown view is behind the `expenditure_detailed` capability, which no local NULL-category user holds; its loader is `parseFloat(initialData[key]) || 0` (`ExpenditureForm.vue:2223`), so NULL renders as an empty 0 there too.
- **`/m`** `/m/app/expenditure` as John (token minted with `createToken`, confirmed via `GET /api/auth/user`; 390 × 844): "Monthly expenditure £1,250 · £15,000 a year · Only a monthly summary has been entered. Add category details to improve your insights." No console errors. That sentence is the never-asked signal the finding wanted.
