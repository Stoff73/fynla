---
id: G-0004
class: capability
agent: archivist
severity: degrading
opened: 2026-08-21
action: fix
blocking: []
status: open
---

# Four of five branch documents have no frontmatter, so trunk-change propagation cannot run

## The gap

`FORMATS.md` requires every branch document to open with frontmatter carrying `id`,
`type`, `parent`, `applies`, `surfaces`, `consistency_checked` and `status`, and
states plainly: **"A branch with no resolving parent is invalid and blocks until
linked."**

**Four of the five branch documents in `workforce/branches/fixes/` have no
frontmatter at all** — not a malformed `parent`, no YAML block whatsoever.

That breaks the Archivist's staleness check at the mechanism, not at the margin. The
check works by comparing a branch's `consistency_checked` date against the trunk
clauses it declares in `applies`. **With neither field present there is nothing to
compare, so a trunk amendment reaches no branch.** This is the propagation mechanism
that is supposed to carry a decision made today back to work written three months
ago; right now it carries it nowhere.

## Evidence

Checked 2026-08-21, immediately after installing the regime map into
`core/constitution/05-perimeter.md` §1.1–1.3 — the first real test of propagation
since the trunk was ratified.

| Branch document | Owner | Frontmatter |
|---|---|---|
| `F-0001-batch-c-retirement-profile-gates.md` | `fix-batch-C` | **Present** — `parent: core/constitution/07-quality-bar.md`, `applies: [06-commercials]`, `consistency_checked: 2026-08-21T11:20:00Z` |
| `F-0002-batch-a-ownership-net-worth.md` | `fix-batch-A` | **Absent** — opens on an H1 |
| `F-0003-batch-b-estate-wills.md` | `fix-batch-B` | **Absent** — opens on an H1 |
| `F-0005-design-lead-palette-and-copy.md` | `design-lead` | **Absent** — opens on an H1 |
| `F-0006-batch-d-protection-goals.md` | `fix-batch-D` | **Absent** — opens on an H1 |

**F-0003 is the case that proves the cost.** It discusses `05-perimeter.md` in prose
at line 497, recording a perimeter point against W-0019 — the very board item the
regime map was built out of. Because that citation lives in a paragraph rather than
in `applies`, amending `05-perimeter.md` today did not and could not flag it. A human
reading both would see the link at once; the sweep cannot see it at all.

## Not the Archivist's to fix, and deliberately not fixed

Two reasons, both load-bearing:

1. **`applies` is a declaration by the branch's owner**, not a guess by the indexer.
   Choosing which trunk clauses a piece of work applies is the substance of the
   branch document. Filling it in on an owner's behalf would manufacture the very
   evidence the quarterly practice-drift check depends on — twelve branches doing Y
   against a trunk saying X is only meaningful if the twelve declared Y themselves.
2. **These documents were live and uncommitted when this was found.** Several fix
   agents were running against the same working tree. `FORMATS.md` records a
   near-miss on 2026-08-21 where two agents were one edit away from clobbering each
   other; editing another agent's in-flight handover document is exactly that shape.

## Resolution

**Each owner adds frontmatter to its own branch document at close-out**, before the
consolidation commit. Minimum: `id`, `type: fix`, a `parent` that resolves,
`applies` listing the trunk clauses actually applied, `surfaces`, `consistency_checked`
and `status`. F-0001 is a working template.

**F-0003 should list `core/constitution/05-perimeter.md` in `applies`** and set
`consistency_checked` no earlier than 2026-08-21, since §1 changed today.

**Route: the coordinator, at the consolidation point** — this is a close-out
condition, not a mid-flight interruption. Worth a check in whatever gates the
consolidation commit, since a branch document without frontmatter is invisible to
every sweep that follows it.
