# Registry — Rhythm

**Status:** CSJ's hours ratified 2026-08-13, session 1. Other founders' hours unknown.
**Owner:** CSJ. Amendments gated.

---

## 1. Agents have no hours

The workforce runs continuously while there are open items and unachieved goals.
Reporting times are observation points, not stopping points (workforce design §8.0).
Nothing in this file constrains when agents *work* — only when they may **contact
a human**.

## 2. CSJ

| | |
|---|---|
| **At desk or on phone** | 07:00 – 21:00 |
| **Contactable by agents** | **09:00 – 18:00** |
| Timezone | Europe/London |

**Azlan Raj and Brett Isenberg: unknown.** Until registered, the workforce applies
CSJ's window to them and routes anything time-sensitive to CSJ. Needed in session 2.

## 3. What "contact" means

The window governs **push**, not **pull**. Anything CSJ chooses to open is
unrestricted.

| Channel | Constrained by the window? |
|---|---|
| WhatsApp message or notification | **Yes** — 09:00–18:00 only |
| Slack direct mention or DM | **Yes** |
| Slack channel post, daily brief, control-centre update | **No** — pull, not push. Written whenever ready. |
| Gate, provisioning request or interview question entering the queue | **No** — queueing is not contacting |

Outside the window, contact-class items **queue** and are delivered at 09:00. They
do not escalate overnight, and a 48-hour gate timeout pauses outside the window
rather than running down against it.

### 3.1 Out of hours — RATIFIED 2026-08-14: there is no exception

**CSJ: "No, they need to sort it out, my sleep is sacrosanct."**

**Nothing contacts a founder outside 09:00–18:00. Not production down, not a data
leak, not a security incident, not payments failing.** There is no P0 override and
no escalation path that wakes anyone.

**What the workforce does instead when something serious breaks out of hours:**

1. **Act within its authority.** Automatic rollback on an error-rate breach is
   already required and does not need a human (`08-process.md` §4).
2. **Contain, don't escalate.** Park anything it cannot fix, stop anything that
   would make it worse, and keep working on what it can.
3. **Write the incident up in full**, so the 09:00 brief opens with it rather than
   with routine work.
4. **Never wake anyone to ask.** If the answer genuinely needs a founder, the
   correct behaviour is to wait — the same discipline as the 48-hour gate timeout.

**Why this is workable rather than reckless:** the workforce cannot reach
production without passing the evidence gate, the blast-radius list keeps
migrations, auth, payments, tax and prompts behind a founder, and rollback is
automatic. The blast radius of a night-time failure is bounded by design, not by
someone being awake to catch it.

**Consequence to accept honestly:** a production outage at 23:00 lasts until
09:00 unless rollback catches it. That is CSJ's ruling, made with the trade-off
visible.

## 4. Reporting times

| What | When |
|---|---|
| Checkpoint (agent → Chief of Staff, on completion only) | Event-driven, no clock |
| **Daily brief** generated | 17:30 Europe/London |
| Daily brief delivered | Slack and control centre immediately; WhatsApp only if "Needs you" is non-empty, and only inside the window |
| **Monday plan** | Monday 09:00 |
| **Friday delta** | Friday 16:00 |
| Nightly consistency sweep | 02:00 |
| Quarterly doctrine review | First Monday of the quarter |

**Why 17:30 and not 18:00:** the brief must land inside the contact window so its
"Needs you" section can actually reach you the same day. A brief generated at the
edge of the window is a brief you read tomorrow.

Friday's 17:30 brief still runs after the 16:00 meeting, and notes anything that
changed since it. Work does not stop for a meeting any more than it stops for a
report (§8.0 of the workforce design).

## 4bis. The two weekly meetings

**Ratified 2026-08-13.** The week is bookended: a plan on Monday, a delta on
Friday. Both run as live working sessions with a Meet event as the calendar
anchor — the Chief of Staff can act mid-conversation rather than afterwards.

### Monday 09:00 — the plan

Forward-looking. Agenda auto-built and sent Sunday evening.

1. **Carried forward** — everything Friday marked *not done*, with its reason
2. **This week's commitment** — missions and items, in priority order
3. **Decisions expected** — gates and questions likely to arrive, so they aren't a surprise
4. **Confirmation** — CSJ confirms or reorders

**The output is a commitment record.** `ops/reports/monday-YYYY-MM-DD.md` is the
thing Friday is measured against.

### Friday 16:00 — the delta

Backward-looking, and computed item by item against Monday's plan.

1. **Done** — shipped, with evidence-pack links
2. **Not done** — and why. Never summarised, never merged into one line.
3. **Drift** — work that happened but wasn't in Monday's plan, named explicitly
4. **Trunk amendments** proposed this week
5. **Interview batch** — the week's accumulated deepening questions (up to five)
6. **Gap register** and triage acted-on ratio

**Every Monday item must appear on Friday as done, not done, or descoped. There is
no fourth option and nothing disappears silently between the two.**

**Why "not done" is never summarised:** it is the half that gets skipped, and it
is the half that carries the information. Three items slipping for the same reason
is a pattern; "we didn't get to a few things" is not.

**Drift is not automatically bad.** Bugs and triage-originated work should appear
there. It is named so the ratio stays visible — a week that is mostly drift means
Monday's plan was fiction.

## 4ter. The consistency sweep

`workforce/ops/sweep.sh` runs **weekly, at the Monday planning meeting**, and its
output is read there rather than filed.

**Why a rhythm at all (W-0506):** it was run on discovery, so by the time anyone
looked it reported 99 broken references and nobody believed it. A three-minute check
that is only run when something already feels wrong cannot tell you nothing is wrong.

Two rules that keep it worth reading:

- **Findings and advisories are different numbers.** A size-budget crossing is a
  review (`00-precedence.md` §2.4 says so in terms), not a breach; it is counted
  separately and does not inflate the headline.
- **A rising finding count is the signal, not the absolute number.** Some references
  are permanently unresolvable — a build hash quoted as deploy evidence, for
  instance — and chasing those to zero is how a check gets gamed rather than fixed.

## 5. Liveness thresholds

| Threshold | Value |
|---|---|
| Probe on log silence | 45 minutes |
| Probe on unchanged loop | 3rd iteration |
| Escalate to CSJ after probe | 90 minutes with no resolution |

Changing any value in this section is **gated** (`charter.md` §2) — thresholds are
oversight, and the workforce may not relax its own.
