# 08 — Process and Release

**Status:** Complete, 2026-08-13. §1–4 from session 1 Q2; §5–9 from session 9.
§10 open.
**Owner:** CSJ. Amendments are gated.

---

## 1. Merge authority — evidence, not approval

**Ratified 2026-08-13 — NOT YET APPLIED.** CSJ ruled that `.github/CODEOWNERS` and
branch protection on `dev` and `main` are removed, and the workforce merges to both.

> **Current reality, verified 2026-08-13:** `.github/CODEOWNERS` **still exists**
> (`* @Stoff73`), and `CLAUDE.md` still states that `dev` and `main` are protected
> and only `@Stoff73` may merge. **Under `00-precedence.md` §1, `CLAUDE.md` is
> rank 1 and therefore governs.** Until the removal is applied and `CLAUDE.md`
> amended, **merging remains CSJ's** and this section describes the intended state,
> not the operative one.
>
> The evidence gate below is live and binding regardless — it is what the workforce
> must satisfy before *asking* for a merge today, and what it must satisfy before
> *performing* one afterwards. Queued at `00-precedence.md` §2.6.

**The human approval gate is replaced by a mechanical evidence gate, not deleted.**
No merge to any protected branch happens without a complete, independently produced
evidence pack. A missing or incomplete pack blocks the merge exactly as a missing
approval used to.

This is not new doctrine. It is `CLAUDE.md:449–456` — already binding, already
explicit that "browser tested" means interacted, that unfilled forms mean not done,
and that every checked box needs a corresponding interaction — moved from prose
that agents are asked to honour into a gate they cannot pass without honouring.

---

## 2. The evidence pack

Two parts. Both required. Stored at
`workforce/branches/<type>/<slug>/evidence/` and permalinked from the PR body
before merge.

### 2.1 Code quality

| Artefact | Source |
|---|---|
| Test run | `./vendor/bin/pest` — full output, exit code, counts |
| Format | `./vendor/bin/pint` — clean |
| Security review | `security-reviewer` agent, on any diff touching auth, financial data, or user input |
| Tax compliance | `tax-compliance-reviewer` agent, on any diff touching tax services or projections |
| Design compliance | `design-lint.sh` result, on any UI diff |
| Tech debt | `tech-debt-session` on the changed files |
| Hook results | `tax-hardcode-check`, `m-parity-check` output |

### 2.2 End-to-end — as a user, not as a developer

The journey is performed, in a browser, by interacting: clicking, filling,
submitting, and reading back the result. **Primary tool: the Playwright MCP,
already configured in `.mcp.json` and already the established path.** Claude in
Chrome or computer use where Playwright cannot reach.

Required per journey the change touches:

- Every form **filled and submitted**, not merely rendered
- The resulting state **read back from the database**, not inferred from the UI
- Console captured — errors and warnings, in the shape already used by
  `.smoke-evidence/`
- Screenshots at each step, timestamped
- **Every surface the change touches**, per Rule 19 — web, `/m`, and iOS

### 2.3 Evidence must be hard to fabricate

`CLAUDE.md` already forbids fabricated success. The gate makes it structural:
an evidence pack is only valid if it contains artefacts that cannot be written
from imagination.

- Test-runner **exit codes and raw output**, not a summary of them
- **Database rows before and after**, queried directly
- **Timestamped screenshots** whose content matches the database state claimed
- Console logs as captured, unedited

The Chief of Staff spot-checks correspondence: does the screenshot show the record
the database says was written? A pack that only asserts is not a pack.

**"I COULD NOT TEST THIS" is a valid and expected entry.** It blocks the merge
rather than failing the agent — the correct response is to say so, not to claim
coverage. Silently omitting an untested journey is the serious offence.

### 2.4 The author never verifies their own work

The agent that wrote the code does not produce the evidence pack. **Build writes;
Quality runs and authors the evidence; the Chief of Staff judges the pack before
merge.** Self-certification is not evidence, and a gate that permits it is
decoration.

---

## 3. iOS — the surface that cannot self-certify

Playwright cannot drive the native SwiftUI app. There is no automated path to a
real iOS end-to-end test today, which is precisely why `.goal:12` gates PR #303 on
"CSJ iOS device verification".

**Therefore:** any change touching the native iOS client still requires CSJ's
device verification before merge. This is not a policy choice — it is the absence
of a tool. It is recorded as a standing provisioning gap; if an automated path
appears, the gap closes and the exception with it.

Web and `/m` are fully automatable and carry no such exception.

---

## 4. Production — what replaces the human eye

With no gate on `main`, the safety net moves after the deploy rather than before it.

- **Post-deploy verification is part of the deploy**, not a follow-up. The
  release is not complete until prod has been smoke-tested through the browser.
- **Log monitoring** for 10–15 minutes after deploy, as `CLAUDE.md` already requires.
- **Automatic rollback** on error-rate breach during the monitoring window,
  without waiting for CSJ.
- The daily brief reports every production deploy, whether or not anything went
  wrong.

### 4.1 Open — the blast-radius carve-out

> **Flagged by the Chief of Staff, awaiting CSJ.** Removing the `main` gate makes
> the workforce able to reach real customers, real money and real regulatory
> exposure with no human in the loop. Most prod changes are low-risk and this is
> fine. A few are not.
>
> **Proposal:** autonomous to `main` when the evidence pack passes **and** the diff
> is outside a named high-risk set. Still gated:
>
> - Database migrations
> - Authentication and session handling
> - Payments and subscription (Revolut)
> - Tax calculation services
> - `app/Services/AI/Prompts/**` — the perimeter lives there
> - Anything altering a public claim about what Fynla is or does
>
> That is roughly 90% of deploys autonomous, with the tail that could cost money,
> customers or a regulatory finding still passing your desk. The set is checkable
> from the diff, so it is not a judgement call.
>
> **RATIFIED 2026-08-14 — CSJ: yes.** The list above is gated. Roughly 10% of
> deploys need CSJ; everything else goes automatically once the evidence pack
> passes. The set is checkable from the diff, so it is not a judgement call.

---

## 5. Definition of done

`07-quality-bar.md`. Not restated here.

In one line: **nothing is claimed that has not been demonstrated, and every gap is
named.** A named gap is a pass; a hidden gap is a failure.

## 6. Release flow

**`.claude/skills/release/SKILL.md` is canonical for release mechanics** and is not
restated. Its discipline stands in full:

- Deploy the **feature branch to csjones BEFORE any merge** — never invert the order
- **Browser-verify the actual change on csjones**; `/m` changes via `verify-m`
- Never mix build scripts — `VITE_BASE_PATH` differs and routing breaks silently
- Never `php artisan optimize` or `route:cache`
- Never use the `ssh-fynla` MCP against csjones — that tool is production
- `dev → main` requires the dev tip browser-verified green, and the `main↔dev` diff
  containing nothing that was not on csjones

### 6.1 One part of that skill is superseded

The skill says *"Wait for CSJ's explicit go-ahead… Never extrapolate a previous
session's approval"* and *"Never self-trigger — CSJ decides when to ship."* Session 1
Q2 removed `CODEOWNERS` and branch protection and replaced the approval gate with
the evidence gate (§1–2).

**Superseded: only the human go-ahead.** Everything else in the skill — every
verification step, every ordering constraint, every prohibition — is unchanged and
binding.

**The two documents agree on substance.** The skill's three-question check asks:
has CSJ approved · is it deployed to the relevant environment · is it
browser-verified working there. Questions two and three **are** the evidence pack.
Only question one falls away.

Queued as a skill amendment (`00-precedence.md` §2.6 pattern). Until amended, the
skill's mechanics govern and its go-ahead requirement is inert.

## 7. Gate index

Every gate, and where it is defined. **An index, not a second home** — nothing here
restates a rule.

| Gate | Defined in |
|---|---|
| Merge to `dev` / `main` | §1–2 above (evidence pack) |
| Native iOS changes | §3 |
| Production blast-radius list | §4.1 — **open** |
| Weakening the workforce's own oversight | `charter.md` §2 |
| Compliance hard block — tax, AI prompts, public claims | `charter.md` §4 |
| Any spend | `charter.md` §5 |
| Publishing anything public | `charter.md` §10; articles via the approve-to-production button (`04-voice.md` §5) |
| Trunk amendments | `charter.md` §2; ratified by any founder |
| Persona set changes | `01-mission.md` §3.2 |
| Changing a north star or guardrail definition | `06-commercials.md` |
| Deleting anything | `charter.md` §2 |

**Standing hard blocks** — never gated, simply forbidden — are in §4 above.

## 8. Escalation

| Situation | Path |
|---|---|
| Agent blocked on a decision | Chief of Staff → gate or interview question |
| Chief of Staff cannot judge from the trunk | **Stall rule** — never guess; raise a doctrine question, amend the trunk, resume |
| Two founders disagree | Domain owner decides (`registry/people.md` §3.2). Binds; appealable only at the Friday review |
| Agent silent, hung or thrashing | Probe → intervene → escalate (`registry/rhythm.md` §5) |
| Gate unanswered 48h | Escalate once, then park the item and move on. **Agents never idle waiting on a human.** |
| Same root cause three times in a week | Stops being a bug; goes to CSJ as a design problem |

## 9. Reporting

`registry/rhythm.md` §4 and §4bis are canonical. Not restated.

**The one thing that must not be misread:** a mid-work agent reports nothing. Status
is *observed* by the Chief of Staff from the event log, never requested. Agents
author a report only on completion, handoff, hard block, or context handover.

### 9.1 Reconciling with `.goal`

`.goal` says *"Do NOT write reports/summaries mid-loop. Reports come AFTER green."*

**That ban stands and is not weakened.** It prohibits *stopping work to compose
prose*. The daily brief is a projection generated from the board and event log by
the Chief of Staff — no working agent pauses to produce it.

An agent reading both documents without this paragraph would resolve the tension by
doing less work. That is why it is written down.

## 10. Open

1. **`main` blast-radius list** — §4.1
2. **Out-of-hours P0 exception** — `registry/rhythm.md` §3.1
3. **Independent verification for non-code artefacts** — `07-quality-bar.md` §4
