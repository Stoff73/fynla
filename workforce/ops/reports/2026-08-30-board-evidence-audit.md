# Board audit by evidence, not by stamp — 2026-08-30

Written because the status column could not be trusted. On 2026-08-30 four stamps were
wrong at once: a CSJ decision that never got pushed, an item still `in_progress` a day
after merging, an item `blocked` with no blocker recorded, and an item `blocked` on a
decision that had stopped being needed six days earlier. Counting that register and
reporting the total as fact is worse than not counting it.

**Method.** For every one of the 327 items, take the id and ask whether it is named
anywhere in the evidence: commit subjects and bodies on `origin/dev`, and the contents of
`app/`, `tests/`, `resources/`, `database/`, `routes/`, `config/`. Then read its own
acceptance checklist.

**The limit, stated plainly.** "Named somewhere" measures ATTENTION, not completeness — a
broad item attracts a citation for its first slice and keeps it forever. "Named nowhere"
is the stronger signal, and even that is not proof: a fix can land without citing the id.

**A first pass of this audit searched commit messages ONLY and reported 132 untouched
items. The real figure is 37.** Most citations live in code and tests, not commit
subjects. That error is recorded because it is the same shape as the one this audit
exists to correct: measuring one channel and quoting the number.

## The numbers

| | count |
|---|---|
| items on the board | **327** |
| done | 120 |
| closed invalid or duplicate | 5 |
| deferred | 2 |
| **outstanding** | **202** |

### Outstanding, by evidence

| | count |
|---|---|
| never named in a commit, in code, or in a test | **37** — 4 high, 33 medium |
| named somewhere | **165** — 3 critical, 77 high, 64 medium, 11 low, 10 unrated |

### The 165 touched, by their own acceptance checklist

| | count |
|---|---|
| every criterion ticked | 3 |
| partially ticked | 13 |
| criteria written, none ticked | 31 |
| **no acceptance checklist at all** | **118** |

**118 of 202 outstanding items cannot be finished as written**, because nothing states
what finishing means. That is the single biggest obstacle on this board — larger than any
individual defect on it.

### The four HIGH items nothing has touched

- **W-0144** — the generated will revokes every earlier will and imposes a 28-day
  survivorship period
- **W-0222** — the headline projected tax figure moves by £305,727 depending on whether a
  cache is warm
- **W-0227** — the protection debt gap panel discloses "mortgage balance £0, other debts
  £0" as the input
- **W-0462** — "Save £74,987" is attached to an action that leaves the beneficiaries
  £37,891 worse off

## On the `gated` bucket

101 items sit at `gated`. The 2026-08-29 triage audited 96 of them and found **not one**
with fully evidenced acceptance. `gated` means "nobody has certified it", NOT "nearly
done". It is the same size of work as `queued`, further along.

## On `done`

39 of the 120 done items are named in no commit. Sampling them, the ones checked are vault
and documentation items (`W-0002` reconcile the vault, `W-0003` correct stale vault docs)
that legitimately produce no application commit. Not evidence of false completion, and not
audited exhaustively either — stated so the 39 is not read as either clean or damning.

## Reproducing this

`workforce/ops/reports/2026-08-30-board-evidence-audit.tsv` carries one row per item:
`id | status | severity | touched|none | criteria ticked | criteria unticked`.
