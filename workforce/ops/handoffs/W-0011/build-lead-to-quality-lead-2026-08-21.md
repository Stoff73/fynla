# W-0011 — build-lead → quality-lead

## Done

- `resources/js/components/UserProfile/ExpenditureForm.vue:2254-2262` — Simple View
  no longer appends the 22 detailed category keys. Same guard on both spouse
  payloads (`:2280-2284`, `:2296-2300`).
- `app/Http/Controllers/Api/UserProfileController.php:497-533` —
  `guardDetailedExpenditure()` replaces the key-presence test at both call sites.
  Simple entry strips the category keys and proceeds; a genuine detailed attempt
  still gets the 403.
- `:26-37` — `use_simple_entry` and `use_separate_expenditure` removed from
  `DETAILED_EXPENDITURE_FIELDS`.
- `resources/js/store/modules/auth.js:36-42` — one `hasCapability` getter mirroring
  `TeaserGate::allows` (including the admin/preview bypass), used to hide the
  Detailed View toggle before entry rather than 403ing after submit.
- Tests: `tests/Feature/Fyn/DetailedExpenditureGateTest.php` (7 passed),
  `resources/js/components/__tests__/UserProfile/ExpenditureSimpleEntry.spec.js` (5 passed).

## Not done, and why

- **No live browser verification.** Scoped out; Rule 14's loop is not closed by me.
- **No throwaway free-tier user was created**, so there is nothing to tear down.
  The tier behaviour is reproducible in tests with `User::factory()->create()`
  (free) versus `->withActivePremiumSubscription()`, which is stronger evidence
  than a hand-made account and leaves no residue. If the persona re-run wants a
  live free account for the browser pass, it will need to make one — David (16)
  and Sarah (17) are premium and I did not downgrade them.
- I did not change `ExpenditureOverview.vue`'s error handling. It already surfaces
  the 403 message (`:123-125`, rethrown by `userProfile.js:289-293`) in a banner at
  the top of the card. The item's "silent" reading is, I think, a banner above the
  fold rather than an absent one — worth confirming visually, but the 403 should
  not occur at all now.

## What you need that isn't obvious from the artefacts

- **The commercial question the item raised is already settled in the enforcing
  layer.** `database/seeders/TierConfigurationSeeder.php:36-37` — Free gets
  `'expenditure' => 'full'`, `'expenditure_detailed' => 'none'`; line 79 gives
  Premium both. Simple expenditure is free by design. Do not re-raise it with CSJ.
- **Fyn already behaved correctly and the HTTP controller was the odd one out.**
  `CoordinatingAgent::handleUpdateProfile` with `section === 'expenditure'` and a
  monthly total writes `expenditure_entry_mode = 'simple'` for any tier with no
  gate; only `handleSetExpenditure` (`:4952`) checks `expenditure_detailed`. The
  fix makes the controller agree with the handler and the seeder — one behaviour,
  three call sites.
- The frontend now decides Simple-vs-Detailed from `tier_flags.capabilities`
  (`AuthController.php:465-475`). That payload is null until `fetchUser` completes,
  so a component mounted before auth resolves defaults to Simple View. That is the
  safe direction, but it means a premium user who somehow renders the form
  pre-auth would see Simple View only until the store fills. Worth a look in the
  live pass.
- `retired_budget_overrides` / `widowed_budget_overrides` are still in the gated
  set, so a free user's simple save has them stripped too. That is intended — they
  are detailed-budget data.

## Assumptions I made

- I assumed a free-tier user's previously stored category values are **not** theirs
  to clear through a form that never showed them, so the guard strips the zeros
  rather than writing them. A test pins this. If CSJ wants a downgrade to zero the
  categories, that is the opposite behaviour and needs saying.
- I assumed `use_separate_expenditure` (joint vs separate expenditure) is a mode
  flag rather than a Premium capability. Nothing in `TierConfigurationSeeder`
  gates it, so removing it from the detailed set does not open a paid feature —
  but it does mean a free user can now set the sharing mode, where previously the
  whole request would have been refused.
- I assumed hiding the Detailed View toggle entirely is better than showing it
  disabled with an upgrade prompt. That is a design call I took; if CSJ wants an
  upsell there instead, it is a small change in one place.

## Surfaces covered / not covered

- **web** — fixed, tested both halves, NOT browser-verified.
- **`/m`** — checked, no change needed. `resources/mobile/views/Expenditure.vue` is
  read-only (104 lines, `apiGet` only); entry is through Fyn, which already allowed
  the simple total at any tier. The downstream symptom the item mentions (Savings
  and Investment showing LOCKED with "Monthly expenditure is required") should
  clear once a free user can actually record a total — worth re-checking on csjones.
- **iOS** — same Fyn mechanism as `/m`; not separately built or verified.
