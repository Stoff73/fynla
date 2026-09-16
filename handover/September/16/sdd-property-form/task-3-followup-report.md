# Task 3 follow-up: stale test expectations after `campaign_property` form turn

Branch: `feat/savetax-property-capture-form`
Commit: `1b8458089ab8cbbe8b2cda47686a9f728d2eaac5`

## Change

1. `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php:83` — added `'form'` to the
   `turn_type` allow-list used by `it('only uses known turn_types')`.
2. `tests/Unit/Services/Onboarding/PensioncheckStatesTest.php:939` — changed the expected
   substring in the `campaign_property` prompt-text assertion from `'buy-to-let'` to
   `'buy to let'`, matching the corpus wording at
   `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:171`:
   `"Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**"`

No application code or corpus files touched.

## Before (confirmed failures)

```
⨯ it walks the property section after investments when the funnel tic… 0.11s
✓ it gives no advice during onboarding — every advice state is skippe… 0.11s
✓ it the investments prompt no longer asks for purchase cost or divid… 0.04s
────────────────────────────────────────────────────────────────────────────
 FAILED  Tests\Unit\Services\Onboarding\OnboardingStateMachineTest > `Onbo…
 Failed asserting that an array contains 'form'.
   at tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php:83

 FAILED  Tests\Unit\Services\Onboarding\PensioncheckStatesTest > it walks…
 Failed asserting that 'Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**' [UTF-8](length: 107) contains "buy-to-let" [ASCII](length: 10).
   at tests/Unit/Services/Onboarding/PensioncheckStatesTest.php:939

Tests:    2 failed, 133 passed (528 assertions)
Duration: 28.83s
```

## After (green)

```
✓ it never asks for a declined pension value twice — the second pass…  0.78s
✓ it walks the property section after investments when the funnel tic… 0.18s
✓ it gives no advice during onboarding — every advice state is skippe… 0.14s
✓ it the investments prompt no longer asks for purchase cost or divid… 0.05s

Tests:    135 passed (597 assertions)
Duration: 31.99s
```

Pint on both files: `{"tool":"pint","result":"passed"}` (no changes needed).
