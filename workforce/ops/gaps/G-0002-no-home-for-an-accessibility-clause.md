---
id: G-0002
class: knowledge
agent: archivist
severity: degrading
opened: 2026-08-21
action: interview
blocking: []
status: open
---

# No trunk document has an accessibility clause, and no document owns the subject

## The gap

Fynla ships **three clients** — `resources/js/` (web), `resources/mobile/` (`/m`)
and `ios-native/` (SwiftUI). **No trunk document contains a single accessibility
clause.** Not a standard, not a target, not a "we have decided not to".

The subject has no owner, which is why the silence has survived: an agent looking
for the rule finds nothing in the file it would naturally check, concludes there is
nothing to find, and moves on.

## Evidence

Verified 2026-08-21 by grepping both candidate files for `accessib`, `wcag` and
`aria`:

- `core/constitution/05-perimeter.md` — no match before this amendment.
- `core/constitution/07-quality-bar.md` — **no match.** This is the file that
  defines what "good enough to ship" means, and it is silent.

`compliance-lead` raised the same point when drafting the regime map
(`ops/reports/2026-08-21-perimeter-regime-map-proposal.md` §3), stating explicitly
that where the clause lives was not its call. CSJ's adoption ruling of 2026-08-21
carried the question forward rather than resolving it.

## What has been done, and its deliberate limit

`05-perimeter.md` §1.1 now carries an **Equality Act 2010 row marked Unmapped**, and
§1.2 records the three clients and the fact that neither file has a clause. That
makes the silence visible. **It does not make it a rule** — the map records coverage
and never content, per §7.3.

So the gap is narrower than it was but not closed: an agent now knows accessibility
is unmapped, and still has nothing to apply.

## Resolution

**Not the Archivist's to settle, and not `compliance-lead`'s either.** Two questions,
in order:

1. **Which document owns accessibility?** `07-quality-bar.md` is the stronger
   candidate — accessibility is a quality standard that applies to every surface,
   not only to regulated communication, and the perimeter file is scoped to the
   regulatory boundary. It also has the room: **4,503 characters against an 8,000
   budget**, where `05-perimeter.md` is now 19,758 and over. But the map row belongs
   in `05-perimeter.md` regardless, because that is where the absence is legible.
2. **What does the clause say?** A standard (WCAG level and version), the surfaces
   it binds, and what happens when a surface fails it. Whether the Equality Act
   *requires* any of that is a legal question and stays out of scope
   (`05-perimeter.md` §7.3).

Amendments to both candidate files are gated. **Route: interview, then CSJ.**

**Update 2026-08-21:** team-lead is batching this question for CSJ rather than sending it
alone, alongside the §6 questions from the W-0019 and W-0050 reports that the regime-map
installation left outstanding. **Do not re-raise it separately** — it is queued, not
dropped.
