# Tech Debt Report — Session 66, 23 April 2026

**Scope:** session 66 commits (`a6cfa5a` … `eaea285`)
**Files analysed:** 17 (1 PHP service, 15 frontend Vue/JS, 1 router)
**Issues found:** 3 (0 critical, 1 warning, 2 suggestions)

## Critical Issues

None. All 17 changed files are clean against the design system, no hardcoded tax values, no banned colour tokens, no duplicated mixin methods, no dead code introduced, no security surface added. Backend MC cache key change is self-invalidating by content. The unified pension form branches to the correct `createDCPension` / `createDBPension` / `updateStatePension` dispatch based on `_pensionType` — backend records stay 1:1 shape-identical to legacy form output, verified via live DB inspection on local dev.

## Warnings

### 1. `DCPensionForm.vue` grew from 839 → ~1080 lines

- **File:** `resources/js/components/Retirement/DCPensionForm.vue`
- **Category:** Complexity & Maintainability
- **What's wrong:** The form was already over the project's 500-line threshold before session 66. Adding the Final Salary and State Pension conditional field groups pushed it further. The file now carries three unrelated form shapes (DC, DB, State) behind branched `handleSubmit` logic.
- **Why it's a warning not critical:** The structure is intentionally flat — each conditional block is independently readable and the three `submit*` methods are short (≈30 lines each). Splitting prematurely would introduce cross-component prop/event plumbing for no clear benefit. Flag for future refactor when a fourth pension type or a third form variant lands.
- **Suggested fix:** If this form continues to grow, extract `DBFieldsFragment.vue` and `StateFieldsFragment.vue` sub-components that DCPensionForm composes, keeping the header / dropdown / save logic in the shell. Not urgent.

## Suggestions

### S1. Inline CTA row markup repeated across 7 pages

- **Files:**
  - `resources/js/components/NetWorth/BusinessInterestsList.vue` (lines ~41–51)
  - `resources/js/components/NetWorth/ChattelsList.vue` (lines ~52–75)
  - `resources/js/components/NetWorth/InvestmentList.vue` (lines ~116–139)
  - `resources/js/components/NetWorth/LiabilitiesList.vue` (lines ~62–76)
  - `resources/js/components/NetWorth/PensionList.vue` (lines ~313–344)
  - `resources/js/components/NetWorth/PropertyList.vue` (lines ~36–49)
  - `resources/js/views/Trusts/TrustsDashboard.vue` (lines ~17–39)
- **Category:** Duplicate Code / Consistency
- **What's wrong:** Each page carries the same `<button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-semibold …">` markup for the Add / Upload primary + secondary buttons. 14 button instances total. If button style ever changes (new design system revision, different icon set), we'd need to edit 7 files.
- **Why it's a suggestion not a warning:** Per CLAUDE.md "three similar lines is better than a premature abstraction" — and each page's handlers are unique (`addProperty` vs `addLiability` vs `openAddModal` etc.), so a fully generic component would need slots or heavy prop-drilling. Current duplication is readable and local.
- **Suggested fix (optional):** A simple `<InlineCtaRow :buttons="[{label, icon, style, onClick}]" />` component in `components/Shared/` would reduce each row from ~10 lines to ~3. Defer until the design system actually changes.

### S2. Minor spacing inconsistency between CTA rows

- **Files:**
  - `resources/js/components/NetWorth/PensionList.vue` — `mt-1` (under pension cards, next to projection chart)
  - `resources/js/components/NetWorth/InvestmentList.vue` — `mt-1` (under account cards)
  - Property-type pages (Property / Liabilities / Chattels / Business / Trusts) — `mb-4` (above list grid)
- **Category:** Consistency
- **What's wrong:** Retirement/Investments use `mt-1` (4px above the buttons); property-type pages use `mb-4` (16px below the buttons). The semantics differ (below cards vs above list) so the values aren't wrong, just slightly inconsistent visually.
- **Suggested fix:** When the extraction in S1 lands, pick one convention. Not worth touching on its own.

## Notes

- `RetirementProjectionService.php` — the new cache-key change intentionally keeps the Monte Carlo DB cache table schema untouched; new-format rows coexist with legacy-format rows. Legacy rows age out via the existing 24h TTL, or can be purged immediately via the optional SQL in `deployPensionFix.md`. No migration needed.
- `UnifiedPensionForm.vue` went from a 3-tile router to a near-empty thin router. It still serves a purpose for the EDIT flow (routes `initialPensionType='db'` and `'state'` to the legacy per-type forms). If those legacy forms are ever folded into DCPensionForm too, this wrapper can be deleted entirely.
- `SubNavBar.vue` and `subNavConfig.js` are kept alive despite being unreferenced from the render tree — intentional per the user's "hide, don't delete" instruction. One-char revert (`v-if="false"` → `v-if="true"`) brings them back.

---

*Generated by tech-debt-session skill — session 66 audit*
