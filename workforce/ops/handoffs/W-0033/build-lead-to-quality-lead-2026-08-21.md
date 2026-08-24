# W-0033 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md` §2.

## Done

- **Decision made and written into the code:** the protection profile is the
  authoritative source for smoking and health status in protection advice. The two dead
  reads (`isset($user->smoker)`, `isset($user->good_health)`) are deleted.
- The reasoning is recorded in the file, with the file:line evidence, so it is not
  re-litigated by the next reader.
- Unanswered questions now render "Not provided" rather than "Non-smoker" / "Good".
- New test file, 6 passed. Wider protection regression: **133 passed, 388 assertions**.
  Pint clean.
- **W-0141 raised** — the reason the "Not provided" branch is currently unreachable.

## Not done, and why

- **No schema change.** `protection_profiles.smoker_status` and `health_status` are
  `NOT NULL` with defaults of `0` and `'good'`, so an unanswered question cannot be
  stored. Making them nullable changes protection advice for every existing user and
  needs compliance-lead on whether the favourable assumption is defensible at all. That
  is W-0141.
- Not browser-verified. No commit, no PR, no deploy.

## What you need that isn't obvious from the artefacts

1. **The test file contains a deliberate characterisation test** — `it currently cannot
   store an unanswered smoking or health question at all` asserts the two column
   definitions. **It pins a defect, not a desired behaviour, and it will fail when W-0141
   is fixed.** That is the signal. Do not widen the assertion to make it pass.
2. **The user-visible change is "Non-smoker" → "Not provided" and "Good" → "Not
   provided", and today it cannot fire**, because the database defaults mean no profile
   ever holds a null. It becomes live the moment W-0141 lands. I did not want to leave the
   code asserting a fact it had not been told, even where the assertion is currently
   unreachable.
3. **`buildUserProfile` is private**, so the test invokes it by reflection — the pattern
   the `HasAiChat` trait tests already use. The public entry point needs a full protection
   analysis, which would have made the test about everything except the decision.
4. **Do not "restore" the user-record reads.** Someone reading the old comment ("check
   user table first, fallback to profile") would reasonably assume the user record was
   meant to win. Two tests exist specifically to stop that.

## Assumptions I made

- **That "Not provided" is in scope.** The item asked me to delete or wire the dead
  branches; changing the null rendering is adjacent. I judged it part of "make the code
  say so", and it matches the idiom already in that method (`$age = 'Not provided'`). If
  you disagree, it is two lines to revert and the decision itself stands without it.
- That nothing downstream branches on the exact strings "Smoker"/"Non-smoker"/"Good". I
  checked — `planPrintMixin.js:2051` only tests truthiness and prints the value — but I
  did not exhaustively trace every plan renderer.
- That the enforcing layer's answer is the right basis for the decision, rather than a
  product view about which form the user "should" fill in. If product-lead wants the user
  record to become authoritative, that is a larger change: it needs a vocabulary mapping
  between two incompatible enums, and `RecommendationEngine` would have to move with it.

## Surfaces covered / not covered

- **Backend — covered.** The service is shared, so web, `/m` and iOS all read the
  corrected values through `/api/protection` and the plan endpoints.
- **No per-surface frontend work was needed**; the strings are server-composed.
- **Not verified in any browser or on any device.**
