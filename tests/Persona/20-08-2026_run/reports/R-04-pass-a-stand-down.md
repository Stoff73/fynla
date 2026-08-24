# R-04 — Pass A stand-down: handed to build-lead, awaiting Playwright re-run

**Run:** `peak_earners`, Pass A · **Environment:** local `http://localhost:8000`
**Stood down:** 2026-08-21 08:20 · **Status:** Pass A **closed incomplete, by decision**

---

## Done

CSJ decisions received and actioned:

1. **Entry stopped.** No further persona data entered after the tier blocker in R-03.
2. **Fix batch handed over.** W-0006 … W-0017 (twelve items) to `build-lead`.
3. **Paperwork completed** — the durable output of this pass:
   - R-01, R-02, R-03 written (08:09–08:11), R-04 (this file).
   - `RUN-LOG.md` at run-folder root with the tooling header block.
   - All twelve board items audited against `FORMATS.md` and gaps closed:
     - **Evidence section added to every item** naming the screenshot where one
       exists, and stating plainly where none does and why.
     - **W-0015 restructured** — it had a bespoke "The contradiction" table but no
       `### Expected` / `### Actual` headings and no persona-file line. Both added.
     - **W-0017 created**, folding the four Defined Benefit form gaps into one item.
4. **Household left in place** — David (16) and Sarah (17) NOT torn down, so
   build-lead can reproduce each defect against real rows.

### Board audit result

All twelve items now carry: frontmatter (`id`/`owner`/`status`/`surfaces`), Intent,
Expected, Actual, Evidence, Repro, Acceptance, Working notes, a persona-file line
reference, and a `file:line` root cause.

| | Screenshot | Why |
|---|---|---|
| W-0006, W-0014, W-0015, W-0016 | yes | found during the verification phase, after the capture rule |
| W-0007…W-0013, W-0017 | **none** | found during entry, which predates the capture rule |

For the no-screenshot items the evidence is stronger than a screenshot would have
been anyway: captured HTTP request/response pairs (W-0009, W-0011, W-0013), verbatim
DB rows (W-0012, W-0017), or a full DOM button enumeration (W-0010). W-0009 is the
clearest case — a screenshot would show a modal closing successfully, which is exactly
the deception being reported.

---

## Not done, and why

- **Pass A is incomplete and is not being finished on this tooling.** By decision it
  restarts from scratch on genuine Playwright MCP once the fix batch lands.
- **Teardown not performed** — explicitly deferred to immediately before the re-run so
  build-lead has live rows to reproduce against.
- **No screenshots for the entry phase, and none backfilled.** Stated in R-01 and
  repeated here because it is the single biggest evidence gap in this pass.
- **The DB-projection claim in W-0017's closing note is unverified** — with the form
  unable to record CPI at all there was nothing to test it against.

---

## Assumptions

- That `build-lead` will work against the live household (users 16 and 17) rather than
  a fresh fixture; every board item quotes real row ids on that basis (e.g.
  `investment_accounts.id 14`, `db_pensions.id 4`, `holdings.id 32`).
- That the re-run will re-enter everything from zero, so nothing in the current data
  needs correcting in place.

---

## Needs

Nothing blocking from me. Two things to land before a faithful Pass A is possible:

1. The must-fix subset of the batch (ranked in the final report — W-0013, W-0014,
   W-0015, W-0011, W-0010, W-0009).
2. Premium provisioning, or a decision that the persona runs at free tier with the
   caps treated as the defect.

---

## Noticed

- **The Playwright root cause was worth having.** A corrupted npx cache
  (`ENOTEMPTY` on `~/.npm/_npx/9833c18b2d85bc59`) meant the server never connected —
  which is why the tools were absent from my session all run, despite `.mcp.json`
  being correct. I reported the absence but did not diagnose the cause; team-lead did.
  Worth remembering as a first check when an MCP server is configured but its tools
  never appear.
- **The dispatched-DOM-click caveat is a real coverage gap, not just a formality.**
  Genuine pointer clicks would have exercised overlay, z-index, pointer-events and
  disabled-state paths that my approach bypassed entirely. Any defect of that class in
  this app is still undiscovered — this pass could not have found it.
