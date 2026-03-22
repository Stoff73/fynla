# TODO — Fynla

*Last updated: 22 March 2026 — session (onboarding updates, liabilities dashboard, will builder fixes)*

## Completed This Session

### Onboarding Updates (PR #157 — 50 commits, merged)
- [x] Journey resumption — go to first incomplete step, not beginning
- [x] Clickable step indicators in progress bar
- [x] Replaced legacy `onboarding_focus_area` with `life_stage` (enum->varchar migration)
- [x] Dashboard refreshes journey completeness on mount
- [x] Multiple executors in will planning and onboarding
- [x] Goals step: skip confirmation modal instead of validation error
- [x] Assets step: close forms on tab switch
- [x] NS&I: hide irrelevant fields (interest rate, access type, checkboxes)
- [x] Useful resources moved to sidebar card below learning milestones
- [x] Family member form: red asterisks on required fields
- [x] DC pension: planned access age field for SIPP/personal pensions
- [x] Scroll to top on step and tab changes (mobile fix)
- [x] Property form: leasehold expiry date with auto-calculated remaining years
- [x] Expenditure step added to all 5 life stage journeys
- [x] Expenditure inputs: fixed display (parseFloat + displayValue normalisation)
- [x] Partner vs Spouse based on marital status
- [x] Family members card layout fix (no overlapping)

### Will Builder
- [x] Draft save/resume via findResumeStep()
- [x] Success modal after Review step completion
- [x] Will dashboard shows full WillDocument data (beneficiaries, gifts, funeral, executors)
- [x] markComplete() syncs executor names from WillDocument JSON to Will record
- [x] "View Will" button links to /estate/will-builder?view=document
- [x] Fixed beneficiary name (ben.beneficiary_name not ben.name)
- [x] Route query watcher for ?view=document

### Liabilities Dashboard
- [x] Sidebar nav item with credit card icon under Finances section
- [x] Dashboard shows user liabilities + mortgages from Property module
- [x] Mortgage cards: "Property" source badge, "Edit in Property" link, not clickable
- [x] Info banner explaining mortgages are managed in Property
- [x] Filter dropdown includes Mortgages option
- [x] Fixed interest rate display (was dividing by 100 erroneously)
- [x] Fixed mortgage notes underscore ("Interest only" not "Interest_only")
- [x] Liabilities onboarding step ("Debts") in journeys 3 and 4
- [x] Top navigation bar on onboarding wizard (Fynla logo, "Your Journey", Exit)

## Not Yet Deployed

All changes are on main branch but NOT deployed to production. Deploy guide at March/March21Updates/deployFix21.md.

### To Deploy
- [ ] Run ./deploy/fynla-org/build.sh locally
- [ ] Upload public/build/ to server
- [ ] Upload changed PHP files (see deploy guide for full list)
- [ ] Run migration: php artisan migrate (focus_area enum to varchar)
- [ ] Run composer dump-autoload on server
- [ ] Seed: php artisan db:seed
- [ ] Clear caches on server

## Known Issues (Carried Forward)
- [ ] PropertyForm edit 422 — editing a property via the UI form returns 422 validation error
- [ ] Sidebar journey %: intermittently shows 0% on some pages (race condition)
- [ ] AI form fill: remaining entity types untested (DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow) — see fynTest.md

## Tech Debt
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking, cosmetic)
- [ ] LiabilitiesStep.vue: DEPRECATED comment — will be replaced by unified form with context="onboarding"
- [ ] IncomeStatementTab.vue is orphaned (never imported) — decide: wire into a view or delete

## Context for Next Session

PR #157 merged 50 commits covering the full onboarding update batch. All changes on main but not deployed to production. The liabilities dashboard is complete with mortgage integration. Will builder is end-to-end functional. Onboarding now has 9 steps for journey 3 and 8 for journey 4 (with Debts step). Top nav bar gives users context during onboarding.

Deploy guide: March/March21Updates/deployFix21.md
AI form fill state: March/March21Updates/currentFormFillState.md
AI form fill testing: March/March21Updates/fynTest.md
