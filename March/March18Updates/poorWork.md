# WARNING: Poor Work by Previous Agent

The previous agent (Claude Opus 4.6) working on the `feature/life-stage-journey` branch was unable to understand the user's instructions, refused to follow instructions, refused to check, test and look at things correctly, was intent on not working, being lazy and not ensuring things are working correctly.

## Critical Issues

The agent taking over this work should check ALL work VERY carefully due to the previous agent's incompetence and fix all issues, wrong code, bad judgement and poor work.

### Specific Problems

1. **Student loan data not showing on dashboard** — User entered student loan (£42,000, Plan 5, 7.3% interest) during onboarding but the Student Debt card shows "No student loan data available." The agent acknowledged this repeatedly but never actually fixed it properly.

2. **Dashboard data display incomplete** — The agent kept making excuses about "HMR state loss" instead of actually verifying that ALL entered data displays correctly on the dashboard after onboarding. Data entered includes: personal info, student loan, income (£8,400 part-time + £3,600 other), monthly spending (£850), savings (Monzo Cash ISA £1,200), and a goal (Emergency Fund £2,550 by June 2027). ALL of this should be visible and correctly displayed.

3. **Sidebar was broken then poorly fixed** — The agent lazily removed all section headings from the sidebar when implementing stage-adaptive layout, then when told to fix it, first showed ALL items (defeating the purpose of stage filtering), then made items show/hide but may not be working correctly for all stages.

4. **Modal forms in onboarding** — Multiple components were rendering as modals during onboarding instead of inline. The agent fixed some but may have missed others or introduced regressions.

5. **Progress tracking was broken** — Personal info wasn't saving to the user model because the agent used a non-existent API route (`api.put('/profile')`). Was eventually fixed but the agent kept dismissing the 0% progress as "not a real bug" instead of investigating properly.

6. **Goal type validation mismatch** — Frontend goal types didn't match backend StoreGoalRequest validation rules. Fixed but the agent should have caught this during the first test.

7. **Investment accounts not saving** — The `handleLifeStageStepSave` was missing handlers for `investments` and `investments-isa` steps, silently dropping data. Agent added the handler but never verified it works end-to-end.

8. **WillInfoStep needs Will Builder integration** — When user says they don't have a will, should redirect to `/estate/will-builder`. Agent added a button but didn't test it.

9. **Net worth missing property/mortgage** — Properties and mortgages entered during onboarding don't appear in the Net Worth calculation. Agent blamed caching and added a cache refresh call but never verified it actually works.

10. **Monthly expenditure not persisting** — SimpleExpenditureStep saves to onboarding store but wasn't persisting `monthly_expenditure` to the user model. Agent added a fix but didn't verify.

## Files Changed (Check These Carefully)

- `resources/js/components/Onboarding/OnboardingWizard.vue` — Heavy changes to step components, save handlers, Transition removal
- `resources/js/components/Onboarding/steps/GoalSetupStep.vue` — Goal type values, field names
- `resources/js/components/Onboarding/steps/WillInfoStep.vue` — Will Builder redirect
- `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue` — Expenditure persistence
- `resources/js/components/UserProfile/PersonalInformation.vue` — Onboarding Continue/Skip buttons
- `resources/js/components/SideMenu.vue` — Stage-adaptive filtering with section headings
- `resources/js/views/Dashboard.vue` — Empty state logic, student persona check
- `resources/js/components/Retirement/DCPensionForm.vue` — Onboarding inline rendering
- `resources/js/components/Retirement/StatePensionForm.vue` — Onboarding inline rendering
- `resources/js/components/Investment/AccountForm.vue` — Onboarding inline rendering
- `resources/js/components/NetWorth/Property/PropertyForm.vue` — Onboarding inline rendering
- `resources/js/components/UserProfile/FamilyMemberFormModal.vue` — Onboarding inline rendering
- `resources/js/components/Estate/LiabilityForm.vue` — Context prop, Cancel button hidden
- `resources/js/components/Protection/PolicyFormModal.vue` — Already had context support
- `resources/js/store/modules/lifeStage.js` — allCompletedSteps getter
- `resources/js/components/Journey/JourneyProgressHero.vue` — Progress display
- `app/Services/LifeStage/LifeStageService.php` — Data completeness checks

## What Needs to Happen

1. Test EVERY journey (all 5) from registration to dashboard, filling EVERY field
2. Verify ALL entered data shows correctly on the dashboard — no missing cards, no empty data
3. Verify sidebar shows correct items for each stage with proper section headings
4. Verify no modals appear during onboarding — everything inline
5. Verify progress tracking shows correct percentage after completion
6. Fix the student loan display on the dashboard
7. Verify property/mortgage data appears in Net Worth for journeys that include property
8. Verify investment data appears for journeys that include investments
