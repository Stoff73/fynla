# W-0103 (folding W-0102 and W-0151) — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0008-batch-g-lpa.md` §4.
**Read the compliance rulings on W-0102, W-0103 and W-0151 first** — every user-facing
string here is theirs verbatim, and the reasoning for what is *not* said is the point.

## Done

- One check, `LpaComplianceService::checkPartyRoles()`, covering all five party-role
  conflicts across the three items. Name comparison **routes to
  `WillDocumentService::isSameParty()`**, the existing one home.
- Two `LpaCheckPolicy::NOT_CHECKED` entries: the name-matching reliability limit, and
  W-0151's disclosure-only disqualification line.
- 28 tests in `LpaComplianceServiceTest`; whole estate suite **292 passed / 946
  assertions**. Pint clean.
- W-0102 and W-0151 marked `done` / `folded_into: W-0103`.

## Not done, and why

- **No completion gate.** Neither acceptance asks for one, and blocking the three
  W-0103 conflicts would refuse a save on an arrangement compliance searched for and
  could not find prohibited. Recommendation and scope are in the branch document.
- **No family-member check** (reg 8(3)(a)) — compliance ruled it disclosure-only
  because the term is undefined in reg 8(4), reg 2 and MCA 2005 s.64.
- **The other six reg 8(3) limbs** are not checked; they turn on facts Fynla does not
  hold. They are covered by the one disclosure entry.
- Not browser-verified. No commit, no PR, no deploy.

## What you need that isn't obvious from the artefacts

1. **The two statuses are the one build decision I want challenged.** Statutory limbs
   `fail`, everything else `warning`. If you think a donor naming themselves as their own
   attorney should be a `fail`, note that compliance looked for the prohibition and did
   not find one — making it a failure asserts a rule that may not exist, which is
   W-0100's overclaim pointing the other way. It is deliberate, not a soft option.
2. **The pass description under-claims on purpose.** It names only the
   certificate-provider-versus-attorney comparison while the check covers five conflicts.
   Compliance's sentence was used verbatim rather than widened by me. Under-claiming is
   the safe direction — do not "fix" it without compliance.
3. **`it does not catch a differently spelled name` is not a failing test.** It asserts
   "Dave Jones" against "David Jones" passes, which is the documented limit of
   `isSameParty()`. It exists to keep the `NOT_CHECKED` disclosure honest. **If that
   matching is ever made fuzzier, that test and the disclosure line change together.**
4. **The check returns a list.** If you see it refactored to return one result, the
   "reports every conflict at once" test will catch it — that shape prevents a user
   correcting one conflict at a time and being shown the next.
5. `WillDocumentService` was **not edited**. Calling `isSameParty()` is the Rule 20 route,
   and that file belongs to F-0003.

## Assumptions I made

- **That `fail` and `warning` are statements about the check, not the instrument.** The
  outcome labels already read "Some checks did not pass" / "raised a point to look at",
  which is act-shaped. If a reviewer reads a `fail` as a claim about the document, the
  status split needs rethinking rather than the wording.
- That reg 8(3)(c)'s "any other power of attorney executed by the donor" maps to other
  `lasting_powers_of_attorney` rows for the same `user_id`. Fynla holds one row per
  instrument type per user; it does not model enduring powers of attorney at all, so
  those are invisible to this check and fall under the disclosure.
- That breaking on the first match per conflict type is right — the message names one
  person, and listing every duplicate would be noisier without being more useful.

## Surfaces covered / not covered

- **Backend — covered.** The check is server-composed, so any surface rendering the
  compliance payload gets it.
- **Web — covered**, unverified in a browser. The checklist renders only for drafts
  (`LpaDetailView.vue:48`), which is where the conflicts are correctable.
- **`/m` and iOS — no Lasting Power of Attorney surface exists** (W-0110). Nothing to
  cover; nothing missing.
