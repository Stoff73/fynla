### Task 3: The corpus makes `campaign_property` a form turn

**Files:**
- Modify: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (the `campaign_property` block, ~line 168)
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` docblock line 36 (list `form` as a turn type; no state change)
- Test: `tests/Unit/Services/Onboarding/CaptureFormsTest.php` (append), plus the existing `OnboardingWorkflowTableGoldenMasterTest` and `CampaignSectionFlowTest` must stay green

**Interfaces:**
- Produces: `OnboardingStateMachine::getState('campaign_property')` returns `turn_type => 'form'`, `form => 'property'`, `prompt_text` (new copy), and keeps `capture_focus => 'property'`, `next`, `skip_if`.

- [ ] **Step 1: Write the failing test** (append to `CaptureFormsTest.php`)

```php
it('the property step is a form turn owned by the corpus', function (): void {
    \App\Services\Onboarding\OnboardingStateMachine::flushTransitionTableCache();
    $state = \App\Services\Onboarding\OnboardingStateMachine::getState(\App\Services\Onboarding\OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);

    expect($state['turn_type'])->toBe('form')
        ->and($state['form'])->toBe('property')
        ->and($state['capture_focus'])->toBe('property')
        ->and($state['prompt_text'])->toBe('Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php --filter="form turn"`
Expected: FAIL — `turn_type` is `delegated`.

- [ ] **Step 3: Edit the corpus block**

In `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` replace the `campaign_property` block with:

```yaml
campaign_property:
  turn_type: form
  form: property
  prompt_text: "Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**"
  capture_field: null
  next: { branch: enterCampaignVerify }
```

In `OnboardingStateMachine.php` line 36 change the docblock to:

```
 *   turn_type:    'bubbles' | 'free_text' | 'delegated' | 'grouped_extract' | 'form' | 'terminal'
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php tests/Unit/Services/Onboarding/CampaignSectionFlowTest.php tests/Unit/Services/Onboarding/PensioncheckSectionsTest.php && php artisan fyn:procedural:validate | grep onboarding.workflow`
Expected: all pass; the validator lists `onboarding.workflow.fyn-onboarding v1` as active. If a golden-master fixture for the corpus text exists and fails, regenerate it the way its docblock says and commit the fixture with this task.

- [ ] **Step 5: Commit**

```bash
git add fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/CaptureFormsTest.php
git commit -m "feat(onboarding): the Save Tax property step is a form turn"
```

---

