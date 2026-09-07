---
id: W-0500
title: /m and native users can never answer the question the undivided-share discount turns on, so W-0368 is permanently inert on those surfaces
mission: w-0368-undivided-share-discount
branch: null
owner: build-lead
status: done
severity: medium
surfaces: [m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: quality-lead
prior_art_checked: 2026-08-26
prior_art_found: [W-0368, W-0040]
prior_art_outcome: extends
constitution_refs: [07-quality-bar, 05-perimeter]
decision: CSJ 2026-08-26 — option B, ask as a structured question, never an LLM judgement
---

## Intent

W-0368 made the undivided-share discount correct. It cannot fire on `/m` or native,
and there is no route by which a user of those surfaces could ever make it fire.

`UndividedShareDiscount::applies()` reads `properties.joint_owner_is_spouse` on the
unlinked-co-owner branch. Three states, and **only one of them changes any number**:

| Stored | Meaning | Discount |
|---|---|---|
| `NULL` | never asked | none |
| `true` | co-owner is the spouse | none (IHTA 1984 s161) |
| `false` | co-owner is not the spouse | **applies** |

So "letting a surface record the answer" reduces exactly to "letting it write
`false`" — the value that turns a discount on and therefore reduces a stated tax
liability. There is no safe subset to concede; permitting only `true` would permit
only a no-op. That is what makes this a decision rather than a chore.

**Today `/m` and native can record nothing.** The web form asks directly — the
co-owner select offers "&lt;name&gt; (Spouse)" and "Other (Enter Name)" as separate
choices. `/m` has no property form and issues no PUT at all; native has no property
update. Fyn is their only write path, and `PropertyNormaliser::fromFyn()` whitelists
`joint_owner_id` and `joint_owner_name` **without** `joint_owner_is_spouse`, so every
Fyn-created property is `NULL` forever.

The store already expects otherwise. `PropertyStore::validateCanonical()` accepts the
field and its comment says *"Fyn is /m's only write path, so this rule decides whether
/m and native can record the answer at all."* The store was built for this; the
normaliser silently drops it. **The codebase currently states an intent it does not
implement**, which is its own defect regardless of how this item is resolved.

## The constraint this must not break

`UndividedShareDiscount`'s class docblock forbids inferring the answer from a name or
from marital status, and that was **measured, not stylistic**: on live data the one
property whose co-owner is named "wife" belongs to a user marked `single`, and a
co-owner recorded as "GLW" matches no spousal vocabulary while quite possibly being
one. Both heuristics fail.

An LLM reading *"I own it with Ruth"* and writing `false` is precisely that banned
inference, wearing a different hat. **CSJ direction 2026-08-26: the answer must come
from a structured question the user answers, never from the model's reading of
conversation.** Option A (prompt-constrained LLM fill) was considered and rejected at
any prompt strength.

## What the investigation found, and why the obvious route does not work

The obvious implementation is a `quick_replies` bubble. **It is suppressed on exactly
the path that matters.**

`OnboardingChatDirector::runInlineCapture()` — the post-onboarding write path, reached
from advice mode via `delegate_to_capture` — drops the event outright:

```php
if (in_array($type, ['onboarding_layout_change', 'quick_replies'], true)) {
    continue;
}
```

That is deliberate, not an oversight: the docblock at `:6480` cites INV-2.4.1 /
INV-2.4.2, the invariant that keeps the handoff invisible to the frontend. **Do not
"fix" this by lifting the filter** — it would leak the write-state handoff the
canonical contract exists to hide.

So the two surfaces split:

- **During onboarding** — `quick_replies` work normally; the bubble-driven flow already
  uses them (`:714`, `:1005`, `:1947`, `:3312`, `:5468`, and more), and `/m` consumes
  them (`resources/mobile/mixins/onboardingChat.js`, `MobileChrome.vue`).
- **Post-onboarding** — needs a different mechanism, and there is an established one.

## The route: gate question + deterministic extraction, mirroring `ownership_type`

The inline-capture path already solves this exact class of problem — a fact the model
must not guess — with `CaptureAccuracyGate` asking for the missing detail and a
**deterministic** extractor parsing the user's reply into `confirmedFacts`. The
precedent is ownership, at `OnboardingChatDirector:322-329`:

```php
// the awaiting-detail reply IS the answer to the gate's question
$parsedOwnership = $this->entityExtractor->extractOwnershipType($message);
if ($parsedOwnership !== null) {
    $confirmedFacts['ownership_type'] = $parsedOwnership;
}
```

`confirmedFacts` is carried for the turn and resolved in `CaptureAccuracyGate` by both
the streamed tool dispatch and the direct gap-fill `executeTool` calls. That is the
shape to copy: **the user is asked, the reply is parsed by code, the model does not
decide.** It satisfies the docblock's rule for the same reason the web select does.

## Acceptance

1. A `/m` or native user creating a shared property with an unlinked co-owner is
   **asked** whether that co-owner is their spouse — by quick-reply bubble during
   onboarding, by gate question post-onboarding.
2. The stored value is derived by **deterministic parsing of the user's reply**, never
   by the model. No code path lets an LLM tool-call populate the field from narrative.
3. **Asymmetric caution, pinned by test:** `false` is written only on an unambiguous
   negative. Ambiguity, silence, a skipped question, or an unparseable reply all leave
   `NULL`. `NULL` and `true` are both safe (no discount); only a wrong `false`
   understates tax.
4. `PropertyNormaliser::fromFyn()` carries `joint_owner_is_spouse`, closing the gap
   with `PropertyStore::validateCanonical()`'s stated intent.
5. `runInlineCapture()`'s `quick_replies` suppression is **unchanged** — INV-2.4.1 and
   INV-2.4.2 hold; verify no `handoff` or layout event reaches the frontend.
6. Verified on `/m` per Rule 19, and on native, with a DB read confirming the stored
   column — not a UI screenshot alone.
7. Round trip holds: a property answered on `/m` reopens on web showing the same
   answer, and vice versa.

## Why medium and not high

The current failure is **conservative** — it overstates tax rather than understating
it. A `/m` user is told their Inheritance Tax exposure is larger than it may prove to
be, which is the direction the application already erred in before W-0368 existed, and
declining to value on facts you do not have is correct valuation practice rather than
timidity. Worth naming that the withheld "entitlement" was never a settled number:
10% / 15% are Valuation Office **practice** figures, negotiable on the facts, and in a
real estate the range runs from nil to above 15%.

It is not low, because Rule 19 makes a web-only feature undelivered, and because the
store already advertises a capability it does not have.

## Related

- **W-0368** — the parent. C1, C2, C3 discharged; C2 fixed across three routes in
  `7476ac5b8`, citations in `5a34bf535`, the relationship-question helper in
  `243922925`. PR #719, awaiting re-gate.
- **C5 / the fifth valuation site** — `EstateActionDefinitionService::estimateEstateValue()`
  applies no ownership share at all, returning £295,000 for a share worth £106,200.
  Separate from this item, larger, and pre-dating W-0368.
- **W-0040** — the ownership-percentage rule this field sits beside on the same form.

## 2026-09-01 — CLOSED for web and /m. iOS deferred.

**Acceptance 1 and 2 — a structured question, on the surface, parsed by nobody.**
`/m` has no property form and issued no PUT, so the question is a control of its own on
`resources/mobile/views/modules/PropertyDetail.vue`: *"Is {co-owner} your spouse or
civil partner?"* with Yes and No, PUT straight to `/api/properties/{id}`, which already
accepted the field (`UpdatePropertyRequest:50`).

It is **not** a Fyn quick-reply, and that is deliberate rather than a shortcut. The item
required the answer to come from a structured question and never from the model's
reading of conversation; a control that writes a boolean the user pressed has no model
in the path at all, so acceptance 2 holds by construction rather than by prompt
discipline. It renders only where the answer can change a number — a shared property
whose co-owner holds no account, on the owner's own record, not yet answered.

**Acceptance 3 — asymmetric caution, and there is no "not sure" button on purpose.**
Not answering *is* "not sure": the column stays NULL, no discount applies, and the tax
figure stays on the conservative side. Only an explicit No writes `false`, which is the
only value that reduces a stated liability.

**Acceptance 4 — the normaliser gap closed, but only halfway, and on purpose.**
`PropertyStore::validateCanonical()` accepted the field while
`PropertyNormaliser::fromFyn()` dropped it, so the codebase stated an intent it did not
implement. Carrying it wholesale would have been worse than the gap: `false` is the
value that turns the discount on, and a model reading *"I own it with Ruth"* and
concluding "not the spouse" is exactly the inference `UndividedShareDiscount`'s docblock
forbids — measured on live data, where a co-owner named "wife" belongs to a user marked
single.

So **`true` passes and `false` does not**, by strict identity. `true` is a no-op for the
discount (IHTA 1984 s161 already denies it between spouses), so Fyn can confirm a
spousal co-owner and can never grant a discount. The negative comes only from the
button. Pinned by a test that walks `0`, `'0'`, `'false'`, `'no'`, `null`, `''`,
`'true'` and `1` and asserts none of them reaches the canonical.

**Acceptance 5 — unchanged and untouched.** No `runInlineCapture()` change, no
`quick_replies` change, no new SSE event: the answer is an ordinary PUT from a surface
control, so INV-2.4.1 and INV-2.4.2 cannot be affected by this diff.

**Acceptance 7 — round trip.** One column, one endpoint, one Resource key
(`PropertyResource:51`). A property answered on `/m` reopens on web showing the same
answer because there is no second store of the answer.

Tests: `tests/Feature/Estate/CoOwnerSpouseAnswerComesFromTheUserTest.php` — 6 passed.
Property family: **348 passed**. `/m` frontend: **194 passed, 34 files**.

**Not done, and named rather than implied:**
- **Acceptance 6 is half done.** No browser drive — the local `/m` bundle is a csjones
  build (see W-0034) — and therefore no DB read confirming the stored column from a real
  tap. The endpoint half is covered by the feature test, which does read the column back.
- **Native is deferred**, per CSJ's ruling that the board loop is web and `/m` only.
  iOS still has no route to the answer and no property update.
- **No onboarding quick-reply.** Acceptance 1 named the bubble as the onboarding variant;
  a user creating a property mid-onboarding is asked by the surface afterwards instead.
  The answer is never lost, because the question persists until answered.
