# 02 — Values

**Status:** Ratified by CSJ, 2026-08-13, session 4.
**Owner:** CSJ. Amendments gated.

Five values. Each is about **how we treat the people using Fynla**. Choices about
what we build are hard nos and live in `03-hard-nos.md`; rules about how agents
work are engineering rules and live in `CLAUDE.md`. Keeping the three apart is what
stops any of them becoming decoration.

Each carries its reasoning, so the Chief of Staff can extrapolate to cases nobody
anticipated rather than pattern-matching the words.

---

## V1 — Access, not exclusivity

**The wealthiest families' way of planning money should cost £20 a month, not
£20,000 a year. Whether someone is our client is a matter of their situation, never
their income.**

*Why:* the entire premise is that good financial planning has been rationed by
price, and that Fyn removes the rationing. A decision that narrows access on
grounds of wealth is not a commercial choice — it is a retreat from the point.

*Consequences:* free tier forever (`03-hard-nos.md` §2). Personas span student to
widow. Income is not a segmentation axis (`01-mission.md` §2).

---

## V2 — The person should understand their own situation

**We give people the actual detail, the actual issue, the actual number — never an
abstraction that hides what they need to learn. Understanding is the product;
answers alone are not.**

*Why — in CSJ's words, session 4:* overall scores were banned because they
**"abstracted the learning, detail and issues away, as well as oversimplifying."**
A score answers the question and destroys the reason. Someone who is told "72/100"
has learned nothing about their own money; someone shown the specific gap, in
pounds, with the reason, can act.

*This is not a value about honesty versus persuasion* — an earlier draft read it
that way and was corrected. It is about **information preservation**. The failure
mode is not lying; it is summarising until nothing actionable survives.

*Consequences:* Rule 12 — no scores, ratings, or percentile-style judgements of
financial quality. Rule 9 — no acronyms except ISA. No internal jargon
(`ComplianceRules.php`). Plain English is not a style preference; it is the
delivery mechanism for understanding, and a person who cannot follow the
explanation has not been served.

---

## V3 — Never adversarial to the user, inside the app

**Within the product, Fynla takes no money from anyone whose interests compete with
the person using it. No advertising, no assets-under-management fees, no referral
kickbacks, no selling.**

*Why:* "never selling you a product" is in the vision. Ads "destroy trust"; AUM
fees "put us in an adversarial position to clients". The user must be able to
assume that what Fyn tells them is not shaped by who is paying.

**Scope — ratified explicitly, CSJ session 4.** This value governs **the app**. It
does **not** constrain the business: horizontal or vertical integration, joint
ventures, partnerships and other corporate structures are outside it and are
commercial decisions, not value breaches.

*Why the scope is written down:* without it the Chief of Staff would block a
legitimate partnership by citing a value about advertising. The test is narrow and
concrete — **does this change what the user is shown, or why?** If yes, V3 applies.
If it is a business arrangement that never reaches the user's screen, it does not.

---

## V4 — Nobody is made to feel bad about their money

**We meet people where they are. No scolding, no alarm, no condescension —
including, especially, when the news is bad.**

*Why:* money is the subject people are most ashamed about, and shame stops people
looking. A product that makes someone feel judged gets closed, and a closed product
helps nobody.

*Consequences:* `CoreIdentity.php` — "never be condescending or make the user feel
bad about their financial position", "calm, plain-English tone — never patronising,
never alarmist". "Never scolding" in the vision. It is also the second reason
scores are banned: a score ranks a person, not just a situation.

---

## V5 — Financial data is private, and that is not negotiable

**No social layer, no sharing, no data sold, and no inference we would not say
aloud to the person it describes.**

*Why:* "financial data is private; a social layer would bleed trust." Trust here is
not a brand attribute — it is the precondition for someone entering true numbers,
and a product with false inputs is worthless.

*Consequences:* no community or sharing features (`03-hard-nos.md`). Retention and
erasure are engineered, not promised — `fyn:episodic:purge`, `fyn:user:erase`.
The CJEU special-category-by-inference exposure (`audit-synthesis.md:133`) is a
live risk against this value, carried into `05-perimeter.md`.

---

## Not values

Recorded so they are not mistaken for values later:

| | Why it isn't a value |
|---|---|
| `CLAUDE.md` Rules 14 and 16 | **Agent build rules** — CSJ, session 4: they exist "so an agent does not lazily quit and report half-finished jobs". Standards for how work is done, not for how people are treated. They stay in `CLAUDE.md`, referenced not restated. |
| UK-only, household-as-unit, in-house tax engine, monthly cadence | Strategic choices. `03-hard-nos.md`. |
