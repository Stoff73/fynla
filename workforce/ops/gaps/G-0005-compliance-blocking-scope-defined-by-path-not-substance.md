---
id: G-0005
class: context
agent: compliance-lead
severity: degrading
opened: 2026-08-21
action: fix
blocking: []
status: open
---

## The gap

**`compliance-lead`'s second blocking surface is defined by a directory path that no
longer matches the substance it is meant to cover.**

Its definition blocks on `app/Services/AI/Prompts/**`. But `CLAUDE.md`'s canonical Fyn
contract states that **both** Fyn states send the static prompt **plus** the per-turn
context assembler — so `app/Services/AI/Fyn/**` is a prompt surface in substance, and
`FynContextAssembler.php` in particular composes text that reaches the model on every
turn.

**A file whose content reaches the model as prompt is a prompt file**, whatever
directory it lives in.

## Evidence

Found by `compliance-lead` during the weekly scan, 2026-08-21, **on itself**:
`app/Services/AI/Prompts/**` was unchanged in the day's work, so a scan scoped to the
definition would have reported "no prompt changes". `FynContextAssembler.php` **had**
changed, and scanning it produced the scan's §2 — including a **fail-open** finding:
the will-structure directive is gated on a narrow regex, and when the regex misses,
**no will-structure policy reaches the model at all**, because no baseline exists in
`FynSystemPrompt`, `ComplianceRules` or `FcaProcessInstructions`. Fail-open against a
stated fail-closed posture.

**So the gap has already cost a real finding once, and the finding was only made because
the agent scanned outside its own written scope.**

## Why this is open rather than fixed

The coordinator instructed `compliance-lead` to correct its own definition file. **That
instruction was wrong and is withdrawn.**

`compliance-lead` declined, on the ground that **no agent's instruction is authority to
change an agent's configuration** — only the permission system or the human — and that
the direction of the change being *safer* makes no difference, because "it was benign"
is what every such edit would say. **That reasoning is correct**, it is the same line the
agent held on the CSJ-gated trunk all day, and it should not bend because the file
happens to be its own.

The risk being carried is **not** today's scan, which was run to the corrected scope. It
is that **a replacement agent reading only the definition inherits the wrong one.**

## Resolution

- **Needs CSJ**, or whoever owns agent definitions, to widen the blocking surface in
  `.claude/agents/compliance-lead.md` from a directory to the substance test: **every
  file whose content reaches the model as prompt**, which today is
  `app/Services/AI/Prompts/**` and `app/Services/AI/Fyn/**`.
- Exact proposed wording is in §5 of
  `workforce/ops/reports/2026-08-21-weekly-compliance-scan.md`.
- **Interim, already in force:** `compliance-lead` is operating to the corrected scope
  and has recorded it durably in that report.
- Filed by team-lead on the agent's behalf: it holds no `G` block and correctly declined
  to take a number, for the same reason it declines `W` numbers.

**General lesson worth carrying beyond this gap:** a scope defined by *where a file lives*
drifts the moment the architecture moves, and drifts **silently**, because the directory
still exists and still contains files. A scope defined by *what a file does* does not.
This is the same class as the source register's Class B — a value living somewhere other
than the thing you would check.

## Refinement — a substance-defined scope is not self-maintaining

Added 2026-08-21 on `compliance-lead`'s own qualification of the fix, which is worth
recording because the lesson above is the kind that gets over-read:

**A scope defined by what a file does is only as durable as the definition of the
substance.** The corrected scope depends on `CLAUDE.md`'s canonical Fyn contract staying
accurate about which files reach the model as prompt. **If that contract drifts, the
scope drifts with it — silently, in exactly the same way a path does.**

A better dependency than a directory, because it moves far less often and moving it is a
deliberate act rather than a side effect of a refactor. **Not free.** Nobody should read
"defined by what it does" as self-maintaining.
