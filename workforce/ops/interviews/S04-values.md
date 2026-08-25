# Session 4 — Values & Hard Nos

**Onboarding session 4 of 9.** Produces `02-values.md` and `03-hard-nos.md`.
**Date:** 2026-08-13 · **Interviewer:** Chief of Staff · **For:** CSJ

---

## What I read first

`04-product-strategy.md` §5 (the trade-off table, in full, with its reasons) ·
`app/Services/AI/Prompts/CoreIdentity.php` · `ComplianceRules.php` ·
`CLAUDE.md` Rules 2, 9, 12, 14, 15, 16, 19, 20 · `docs/reference/Articles/pension-tracker.md` ·
`01-mission.md` as ratified yesterday

**There is no values document anywhere in either tree.** But there is a great deal
of *behaviour* that only makes sense if certain values are held. Everything below
is derived from that behaviour, with the evidence attached. You are checking
whether I read you correctly, not inventing from scratch.

---

## ANSWERED 2026-08-13 — four corrections

> **V2 was wrong and is rewritten.** I read the score ban as an honesty-versus-
> persuasion trade-off. CSJ: scores were banned because they **"abstracted the
> learning, detail and issues away, as well as oversimplifying"** — and he still
> sees no value in them. The real value is **information preservation**, not
> honesty: the failure mode is summarising until nothing actionable survives, not
> lying. Rewritten as *"the person should understand their own situation"*, and it
> absorbs the old V4 on plain English — plain English is the delivery mechanism for
> understanding, not a separate value.
>
> **V3 is scoped to the app.** It does not constrain horizontal or vertical
> integration or joint ventures. Written in with a concrete test: *does this change
> what the user is shown, or why?* Without that scope the Chief of Staff would have
> blocked a legitimate partnership by citing a value about advertising.
>
> **Rules 14 and 16 are not values.** CSJ: they are agent build rules, "so an agent
> does not lazily quit and report half-finished jobs". Recorded under *Not values*
> in `02-values.md` so nobody promotes them later.
>
> **B1 freemium — answered.** The app was simplified weeks and many PRs ago to
> **free tier forever plus one paid subscription upgrade.** The hard no's
> conclusion was void but its reason survives: ads and AUM fees were always the
> objection, never the free tier. Written to `03-hard-nos.md` §2, with "more than
> one paid tier" added as a hard no since the simplification was deliberate.
>
> **Result: five values, not six.** Written to `core/constitution/02-values.md` and
> `03-hard-nos.md`.
>
> **Still open:** confirm the pricing model reading; HNW >£5m; day-traders.

## Part A — Six proposed values

I've kept the split strict: **a value is about how we treat people.** A choice
about what we build is a hard no and belongs in Part B. Mixing them makes both
useless.

### V1 — Access, not exclusivity

> The wealthiest families' way of planning money should cost £20 a month, not
> £20,000 a year. Who someone is as a client is a matter of their situation, never
> their income.

**Evidence:** the vision line; `01-mission.md` §2 as you ruled yesterday; seven
personas spanning student to widow.

### V2 — We would rather lose a customer than mislead one

> Where clarity and persuasion conflict, clarity wins — even when it costs us the
> sale, the engagement, or the simpler screen.

**Evidence:** Rule 12 bans scores because "they oversimplify and mislead" — you
banned a metric that would have made the product *more* engaging. Hedging language
is mandatory in `ComplianceRules.php`. "We never recommend specific providers or
products" (`docs/reference/Articles/pension-tracker.md:91`).

### V3 — Never adversarial to the customer

> We do not take money from anyone whose interests compete with the person using
> the product. No ads, no assets-under-management fees, no referral kickbacks, no
> selling.

**Evidence:** "never selling you a product" (vision); "Ads destroy trust"; "AUM
fees put us in an adversarial position to clients". **This one has teeth** — it
rules out revenue models, not just tactics, and it is the value most likely to be
tested when money is tight.

### V4 — Plain English is a safety feature, not a style choice

> If someone cannot understand it, they cannot act on it, and a financial decision
> they did not understand is one we helped them get wrong.

**Evidence:** Rule 9 bans every acronym except ISA; `ComplianceRules.php` bans
internal jargon ("waterfall", "opportunity cost", "phased approach") and record IDs
in user-facing text.

### V5 — Nobody is made to feel bad about their money

> We meet people where they are. No scolding, no alarm, no condescension —
> including when the news is bad.

**Evidence:** `CoreIdentity.php` — "Never be condescending or make the user feel
bad about their financial position", "calm, plain-English tone — never patronising,
never alarmist"; "never scolding" in the vision. Rule 12 again: scores rank people.

### V6 — Financial data is private, and that is not negotiable

> No social layer, no sharing, no data sold, no inference we would not say out loud
> to the person it describes.

**Evidence:** "Financial data is private; social layer would bleed trust"; the
episodic-memory retention and GDPR-erasure commands; `SecurityHeaders`.

**Question:** six, or have I missed one? The one I could not evidence but suspect
is something like *"we finish things properly"* — Rule 14's LOOP UNTIL CORRECT and
Rule 16's build-to-spec read like a value about craft, not just a process rule. Is
that a value you hold, or just how you run engineering?

---

## Part B — Hard nos

`04-product-strategy.md` §5 is already an excellent hard-no list, and unlike its
segmentation it is **not** built on anything you overturned yesterday. Each entry
carries its reason.

**Proposal: adopt all ten as `03-hard-nos.md`, verbatim with reasons.**

| We choose | Over | Still true? |
|---|---|---|
| Modelling money | Managing money (robo-advisor) | ✔ |
| UK-only, deep | Multi-country, shallow | ✔ |
| Household as the unit | Individual-account-first | ✔ |
| Subscription | Freemium with ads / AUM fees | **Check — see B1** |
| AI-augmented tool | "Full replacement of an IFA" | ✔ |
| Accountants as channel | Accountants as product | ✔ |
| Tax engine in-house | Licensed tax engine | ✔ |
| No community / social | Forum, "share your plan" | ✔ |
| No real-time trading | Trading execution | ✔ |
| Monthly cadence | Big quarterly releases | ✔ |

### B1 — One that may have moved

The table says **subscription over "freemium with ads / AUM fees"**. But
`docs/archive/CSJTODO-freemium-series.md` exists in the repo root.

The reason given is specifically *ads* and *AUM fees* — both V3 violations. **A
free tier with neither is not obviously ruled out.** Which is it: no freemium at
all, or no ads and no AUM fees with a free tier permitted?

This matters now that income is not an indicator of client — a student is a client,
and £20/month may be the wrong shape for one.

### B2 — Exclusions carried from `01-mission.md` §4

Two still open from session 3:

- **HNW >£5m** — a wealth band, which §2 says cannot be the reason. Is there a
  capability reason, or does this fall? *(Aside: the ads trade-off cites "trust with
  HNW cohort" as a reason — so the strategy doc both excludes them and worries about
  their trust.)*
- **Day-traders and crypto** — "can't serve" or "haven't built"? Only the first is
  an exclusion.

### B3 — Rules that are already hard nos elsewhere

`CLAUDE.md` Rules 8, 12, 15 and 20 are absolute and CSJ-owned. **Proposal:
reference, never restate** — `03-hard-nos.md` points at them. Copying them would
create the second home that Rule 20 exists to prevent.

---

## Completeness check

**Ready to write on your confirmation:** `02-values.md` (six values, with evidence
and reasoning), `03-hard-nos.md` (ten trade-offs plus the surviving exclusions).

**Cannot write without you:** B1 — freemium. It changes what Growth may propose and
what Product may build.

**What I'll assume otherwise:** that V1–V6 are right as read, and that the ten
trade-offs stand. Both are the conservative reading and easy to correct.
