# Fynla — What's New (19 May 2026)

*Release notes for the production deployment to https://fynla.org on 19 May 2026.*

This is the biggest single release Fynla has shipped to date. It introduces a
new membership model, a smarter and more reliable Fyn assistant, more accurate
financial figures behind the scenes, and an important security hardening fix.

**The headline for existing members:** nothing you currently have access to is
being taken away, and your price does not change. See "A note for existing
members" below.

---

## 1. A new membership model (Free, Tier 1, Tier 2, Tier 3)

Fynla now has four membership levels instead of the old plan names. Each level
is a distinct product with its own set of features — they are **not** a rename
of the old Student / Standard / Family / Pro plans.

### What you can do at each level

Every level — including **Free** — gives you the full core experience:

- Your dashboard and net-worth overview
- Goals & life events
- Protection (life cover, income protection, critical illness)
- Property and mortgages
- Liabilities, income and expenditure tracking
- Personal possessions (chattels)
- Child benefit and the Family module (household and spouse linking is
  **never** restricted — it is available to everyone, including Free)
- The Fyn assistant (with a generous weekly usage allowance — see section 3)

**Free** — everything above, with some sensible limits:

- Savings accounts: up to **3**
- Investments: up to **2**
- Pensions: up to **5**
- Estate planning: a **preview** of your potential Inheritance Tax exposure,
  with the option to upgrade for the full toolset
- Document uploads: up to **3**
- Figures shown in pounds sterling

**Tier 1** — everything in Free, plus:

- **Unlimited** savings accounts, investments and pensions (the count limits
  are removed)
- The "Letter to your spouse" estate document
- A larger document upload allowance and a longer history window for your
  saved snapshots
- A higher weekly Fyn allowance

**Tier 2** — everything in Tier 1, plus the full advanced toolset:

- **Full Estate Planning** — the complete Will Builder, Lasting Power of
  Attorney tools, bequests, intestacy calculations and the full Inheritance
  Tax engine (not just the preview)
- Advanced and specialist investment types
- Full retirement decumulation planning (drawdown and income modelling in
  retirement)
- The ability to display values in your choice of currency
- More document storage and a much longer snapshot history
- An even higher weekly Fyn allowance

**Tier 3** — everything in Tier 2, with the most generous allowances:

- The largest document storage and upload allowances
- The longest snapshot history
- The highest weekly Fyn allowance

> **Pricing:** the price of each level is shown on the pricing page in the app.
> Pricing is managed centrally and may be adjusted over time. Existing paying
> members are not affected by any pricing changes until their next renewal
> (see below).

### Fair and non-destructive limits

The new limits never delete or hide anything you already have. If you are on
Free and already have more than three savings accounts (for example, because
you joined before this change), **all of your existing accounts stay exactly
as they are**. You simply can't add a *new* one beyond the limit without
upgrading — and when you try, Fynla shows you a clear upgrade prompt rather
than a confusing error.

### A note for existing members

If you are already a paying subscriber:

- **You keep your current access.** Nothing you can do today is removed.
- **Your price is locked** until your next renewal — this release does not
  change what you pay mid-billing-period.
- Household, spouse and family linking remains free for everyone and is never
  restricted.

In short: this release adds a new model for new members and protects everyone
who is already here.

---

## 2. Estate Planning: Will Builder & Power of Attorney

Estate Planning now has a clear two-stage experience:

- **Free and Tier 1 members** see an Inheritance Tax exposure preview — a
  genuine look at your potential estate liability — with a clear invitation to
  upgrade for the full toolset.
- **Tier 2 and Tier 3 members** get the complete Estate Planning suite,
  including the full **Will Builder** wizard and the **Lasting Power of
  Attorney** tools.

As part of this release we closed a gap where the Will Builder and Power of
Attorney creation screens could still be reached by members who should only be
seeing the preview. These tools are now correctly and consistently part of the
full Estate Planning experience. If you have a preview-level membership and
follow a "Will" or "Power of Attorney" link, you'll now be taken to the Estate
Planning preview with an upgrade option, instead of into a tool you don't yet
have access to.

---

## 3. Fyn — your assistant is smarter and more reliable

We rebuilt the foundations of how Fyn thinks about each conversation. Most of
this is invisible, but you should notice Fyn being more consistent and more
helpful:

- **More consistent answers.** Fyn now works from a single, unified
  understanding of who you are and what's in your plan, rather than switching
  between separate modes. Answers are steadier and better grounded in your
  actual financial picture.
- **Billing and subscription questions are answered properly.** If you ask Fyn
  about your plan, billing, renewals or what your membership includes, it now
  responds correctly instead of deflecting.
- **Better navigation.** When you ask Fyn to take you somewhere ("show me my
  pensions", "open my goals"), it now interprets these requests more reliably
  and stops mistaking ordinary questions for navigation commands.
- **More accurate understanding of your questions.** For example, asking about
  money being "protected" in your savings is now correctly understood as a
  question about deposit protection (FSCS), not life cover.
- **Fixed: duplicate confirmations when adding information via chat.** When you
  told Fyn to add something to your plan (for example, a savings account),
  Fyn would sometimes confirm it more than once, and occasionally the record
  didn't save on the first attempt. Both issues are fixed — Fyn now reliably
  saves what you asked for and confirms it exactly once.
- **Faster responses.** Part of Fyn's working context is now pre-prepared and
  cached, so replies come back a little quicker.

### Fyn usage allowance

Fyn now has a weekly usage allowance that scales with your membership level
(higher levels get a larger allowance). If you reach your weekly allowance,
Fyn doesn't suddenly stop — it gently moves to shorter, lighter responses for
the rest of the week, and returns to normal automatically when the week resets.
There is also a high daily ceiling purely to prevent abuse; ordinary use will
never come close to it.

---

## 4. Behind the scenes — accuracy, reliability and security

These changes don't add new screens, but they make the figures you rely on
more accurate and your data more secure.

### More accurate savings figures everywhere

We completed a large piece of internal work to make your savings data come
from a single, authoritative source. Previously, different parts of the app
(net worth, retirement planning, income projections) could read savings in
slightly different ways, which occasionally produced inconsistent figures —
particularly for **jointly owned** accounts.

Now every part of Fynla reads savings the same way, with correct handling of
joint ownership. The practical result: the savings totals you see in your
dashboard, retirement projections and net-worth breakdown are more accurate
and consistent with one another, especially for couples with shared accounts.

### Security hardening (important)

We identified and fixed a data-isolation issue in the retirement income
projection feature. Under specific conditions, the way account references were
handled could have allowed account balance information to be returned outside
the strict per-user boundary.

- The issue has been **fully closed** with proper per-user ownership checks at
  multiple layers (defence in depth).
- **No action is required from you.**
- There is no indication this was exploited; this was found and fixed
  proactively during an internal security review.

We treat this category of issue with the highest priority and fixed it as part
of this release.

### General reliability and quality work

This release also folds in a substantial amount of accumulated engineering
work: stronger automated test coverage, internal consistency checks, code
quality improvements and admin tooling for managing membership levels and
pricing. None of this changes how you use the app — it makes the app more
robust and easier for the team to maintain and improve safely going forward.

---

## Summary

| Area | What changed | Who it affects |
|---|---|---|
| Membership | New Free / Tier 1 / Tier 2 / Tier 3 model with fair, non-destructive limits | New members; existing members grandfathered (access + price protected) |
| Estate Planning | Will Builder & Power of Attorney are now part of the full (Tier 2+) experience; preview for Free/Tier 1 | All members |
| Fyn assistant | Unified, more consistent and reliable; billing questions answered; navigation fixes; duplicate-confirmation bug fixed; weekly allowance | All members |
| Savings accuracy | Single source of truth for savings; correct joint-ownership handling across the app | All members, especially couples |
| Security | Closed a data-isolation issue in retirement income projections | All members — no action needed |
| Reliability | Accumulated quality, testing and admin tooling improvements | Invisible; improves stability |

---

*Deployed to production (https://fynla.org) on 19 May 2026.*
*Internal reference: PR #337 (SP1 Savings Store + SP2 Freemium + SP3 unified Fyn
prompt + security fix #313 + accrued development) and PR #340 (Will Builder &
Power of Attorney access gating, spec §10.2).*
