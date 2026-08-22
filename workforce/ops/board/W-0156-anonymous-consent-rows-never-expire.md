---
id: W-0156
title: An anonymous consent row for a visitor who never registers is kept indefinitely — no purge, no expiry, and neither retention path reaches it
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
owner: build-lead
reviewers: [compliance-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-21T20:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0049 consent not enforced or recorded server-side - fixed by fix-batch-F; this is a consequence of that fix", "W-0155 cookie consent cannot be withdrawn", "workforce/ops/reports/2026-08-21-section-6-backlog-triage.md Q14", "05-perimeter.md §2 retention and erasure"]
prior_art_outcome: extend
constitution_refs: [05-perimeter]
source: found by compliance-lead during the §6 backlog triage, 2026-08-21, and checked rather than inferred; routed to team-lead because compliance holds no ID block
---

## Intent

**F-0007 introduced a consent row that belongs to no user, and nothing ever removes it.**

`claimAnonymousConsents()` nulls the `subject_token` at registration, so a visitor who
goes on to register is fine — their row becomes theirs and inherits every retention rule
that applies to a user. **A visitor who never registers keeps theirs indefinitely.**

Checked, not assumed:

- **No purge and no expiry** exists for unclaimed rows.
- **`fyn:user:erase` cannot reach them** — it operates on a user, and these have none.
- **The six-year episodic purge cannot reach them** — different store, different table.

`05-perimeter.md` §2 records retention and erasure as the **covered** half of data
protection. **These rows sit outside both halves of the covered half.**

**This is not a criticism of F-0007.** It is the consequence of a good decision — an
anonymous subject with a server-issued token, so consent keeps the date it was actually
given rather than being invented at registration. Nothing in this codebase previously
held a record with no user attached, so the retention question had never had to be asked.

## Acceptance

- [ ] An unclaimed anonymous consent row has a defined lifetime, and something enforces it.
- [ ] The chosen lifetime is **written down with its reason**, not just implemented — the
      next person must be able to see why it is that number rather than another.
- [ ] Whatever removes them is reachable from an existing scheduled command rather than a
      new parallel mechanism (Rule 20). Note the existing family: `fyn:episodic:purge`,
      `fyn:episodic:cold-archive`, `fyn:episodic:reconcile`, `audit:purge`,
      `sessions:cleanup`, `registrations:cleanup` — **`registrations:cleanup` is the
      closest shape**, since it already removes stale pre-account state.
- [ ] Claiming still works for anyone who registers **within** the lifetime, and the claim
      path is tested against a row close to expiry.
- [ ] **Evidence is not destroyed for anyone who did register.** F-0007's own principle —
      a row is left unclaimed rather than deleted where the user already holds the same
      type and version — must not be broken by a purge that cannot tell the two apart.
- [ ] Does not reopen W-0050 (parked) or depend on W-0155.

## Working notes

(append-only)

- 2026-08-21 team-lead: filed on compliance-lead's Q14 finding, in its framing, from the
  coordinator block. Compliance verified the three negatives directly rather than
  inferring them from the absence of a command.
- 2026-08-21 team-lead: **the native consent question raised alongside this is answered
  and is NOT a fourth mechanism.** Checked directly: `ios-native/Fynla/Features/Privacy/`
  posts to `api/auth/gdpr/consents` and `.../history` — the GDPR consent endpoints — and
  **cookie consent is deliberately excluded from that PUT** (`GDPRController`), which was
  F-0007's design and is correct. Native carries **no** cookie handling at all; the only
  match for "cookie" in the Swift sources is a redaction key in the diagnostics client.
  So native is a separate surface for the **other** consent types, not a competing
  implementation of this one, and F-0007 converging web and the funnel left nothing
  native behind.
  **The real open question it exposes is different and is NOT this item's:** whether the
  native app runs any analytics or attribution that would need an equivalent consent —
  because if it does, it has no mechanism for it, and if it does not, there is nothing to
  ask. Worth a look before native ships anything with tracking in it.
