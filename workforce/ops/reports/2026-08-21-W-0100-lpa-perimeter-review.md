# W-0100 — the perimeter half: generating and assessing a Lasting Power of Attorney

**Agent:** compliance-lead · **Date:** 2026-08-21 · **Item:** W-0100 (acceptance 5 only)
**Scope:** the perimeter question. **Not the generator audit** — acceptance 1–4 are
build-lead's and I have not done them.

> **Not an approval and not a legal opinion.** Perimeter §7.3: I may apply a written rule and
> may never determine what the law requires. Where I reach that edge I stop and say so.

---

## 0. What I did and did not read

I read what the tool **says to the user** — that is my surface — and the statutory provisions
that bear on it. I did **not** audit the checks against the requirements, did not enumerate
what `checkCompliance()` misses, and did not read the renderer. Those are acceptance 1–3 and
they are build-lead's.

**This matters to how you read §Q2.** My finding there is structural: it holds whatever
build-lead concludes about the checks. I have deliberately not made it contingent on a code
audit, because then it would be build-lead's finding and not mine.

---

## Q1 — Does generating this instrument sit inside what an unauthorised firm may do?

### Does the W-0019 wills analysis carry across? **Established, not assumed: yes — and from the same sub-paragraph.**

**Legal Services Act 2007, Schedule 2, paragraph 5(3)** defines "instrument" for *reserved
instrument activities* and then excludes a list. Two entries on that list:

> **5(3)(a)** *"a will or other testamentary instrument"*
> **5(3)(c)** *"a letter or power of attorney"*

Wills and powers of attorney are excluded **by the same provision**, one line apart. So the
reserved-activity limb of the W-0019 analysis does carry across, and it carries across for the
same reason rather than by analogy. Nothing in Schedule 2 paras 3–8 reserves the preparation
of a power of attorney; para 6 reserves *probate activities*, which are "preparing any probate
papers", not this.

**I do not conclude that generating an LPA is therefore permissible.** "Not a reserved
activity" answers one question out of the set, and it is the question a lawyer answers
fastest. It is the *rest* that differs from wills.

### Where the LPA is **not** like a will — two differences that cut the other way

**1. There is a statutorily prescribed form. A will has none.**
**Mental Capacity Act 2005, Schedule 1, para 1:** the instrument must be *"in the prescribed
form"* and comply with para 2.

A will must satisfy execution formalities but has no prescribed form — which is why W-0019's
hazards were about *content* (who is named in which role). An LPA that departs from the
prescribed form is not a defective LPA; **it is not an LPA.** The failure is binary and it
surfaces at registration, months later, when the donor may no longer have capacity to make
another. Whether Fynla's renderer produces the prescribed form is acceptance 1–2 and I have
not looked — **I am flagging why the answer matters more here than it did for the will.**

**2. The Act contains, on its face, the exact defect shape W-0024 turned out to be.**
**MCA 2005, Sch 1, para 2(6):** *"The certificate may not be given by a person appointed as
donee."*

W-0024 was a party appointed in a role they could not hold — a testator named as her own
executor — with no statute behind it, found only because someone read the generator. Here the
equivalent prohibition is **written into the schedule**. The certificate provider must also
certify, under **para 2(1)(e)**, that the donor understands the instrument's purpose and
scope, that *"no fraud or undue pressure is being used"*, and that nothing else prevents
creation of a valid LPA.

I make no claim about whether Fynla's generator can produce that state. **I am recording that
the statute names it**, so acceptance 1 has a citation to test against rather than an
instinct.

### The law here is mid-reform — flag for the source register
`legislation.gov.uk` shows Sch 1 carrying **pending amendments from the Powers of Attorney
Act 2023 (2023 c. 42), not yet in force as at 20 August 2026.** The registration scheme is
being modernised. **Anyone answering acceptance 1–3 against the current text should check
commencement before relying on it** — the same trap that made W-0050's PECR citation stale.

---

## Q2 — The `checkCompliance()` verdict, which is the sharper question

### What the user is told — verified
`LpaComplianceService.php:49`:

```php
$overallStatus = $failed > 0 ? 'incomplete' : ($warnings > 0 ? 'review_needed' : 'compliant');
```

`LpaComplianceChecklist.vue:97` renders that as the label **"Compliant"**, and `:88` renders
it in `bg-spring-100 text-spring-800` — **the success colour** (CLAUDE.md Rule 8: spring =
success). Landed `1a3d17e99`, **2026-03-16**, and it is **on `origin/main`** — live on
production for five months.

So: **a user is shown a green badge reading "Compliant" on their Lasting Power of Attorney.**

### Finding 1 — the word is an overclaim, and the trunk already says so about me

Perimeter **§7.3** sets out what the compliance function may and may not output:

| May | May never |
|---|---|
| Report *"no issues found **within my competence**"* | Report *"this is fine"* |
| Flag content matching a known risk pattern | **Approve anything as legally compliant** |

and: *"**Its output is never an approval.** Two outcomes only: no issues found within
competence, or flagged."* And §7.3's closing rationale: *"The failure mode this exists to
prevent is a confident-looking compliance sign-off that nobody questions. An agent that says
'compliant' has done more damage than one that says nothing, because it stops a human from
looking."*

**That paragraph describes `LpaComplianceService` exactly.** The trunk already contains the
principle, correctly reasoned, and it binds **the agent**. It has never been read as binding
**the product**. A green "Compliant" badge is a confident-looking compliance sign-off that
nobody questions, and it stops the user from looking — which is the whole harm §7.3 names.

**This is the finding.** It requires no code audit and no legal determination: the trunk's own
standard, applied to the product, is not met.

### Finding 2 — the object assessed is not the instrument

The checks run against **stored form data**: donor age, at least one attorney, decision type,
certificate provider named, certificate provider known two years or more, notification-person
count, replacement attorneys, registration status, and a property-only check on when attorneys
may act.

But an LPA's validity turns on events **the application never observes**:

- whether the donor in fact had capacity when they signed;
- whether the certificate provider genuinely formed and gave the para 2(1)(e) certificate, or
  merely appears in a form field;
- the manner and order of execution;
- whether the Public Guardian has in fact registered it (**Sch 1 paras 4–5** — the instrument
  is registered by the Public Guardian at the end of the prescribed period).

**So the verdict is structurally incapable of being what its name says.** Not because the
checks are poor — I have not assessed them — but because *compliance* is a property of an
executed and registered instrument, and what the app holds is a form. **A perfect checker
would still not be entitled to the word.** This is why my finding does not depend on
build-lead's answer to acceptance 3.

### Finding 3 — generating and assessing are different acts, and the second is the one that looks like advice

Correct in the framing team-lead put to me: generating a document and telling someone it is
sound are different. Nothing in LSA 2007 Sch 2 reserves the giving of legal advice — the six
reserved activities are paras 3–8 and advice is not among them. **But I stop there.** Whether
an automated compliance verdict on a statutory instrument requires anything, or creates
exposure, is a legal determination and it is §6-class. What I can say within competence is
Findings 1 and 2, and both stand on their own.

---

## Q3 — What the user-facing framing must say

At the level of *must disclose X*. **Not drafted copy** — that goes to design-lead once
build-lead has established what the tool actually does, and drafting it now would fix wording
to behaviour nobody has verified.

1. **Drop the word "compliant", and every synonym asserting a property of the instrument** —
   approved, valid, sufficient, correct, in order. The claim must describe **the act
   performed**, not the object: *"we checked these nine things; none failed"* is sayable,
   *"your Lasting Power of Attorney is compliant"* is not. This is §7.3's distinction applied
   to the product, and it is the whole of the fix in one line.
2. **It must name what it did not check, at the point the result is shown.** Trunk **§4**
   already requires this — *"Where Fynla knows its picture is incomplete, it says so at the
   point the affected figure is shown — not in a footer, not in a blanket disclaimer"* — and
   §4's reasoning reaches it exactly: *"An incomplete figure presented without qualification
   is worse than no figure."* §4's only live instance to date is unmodelled crypto in an
   inheritance-tax figure. **A verdict computed from a subset of requirements is the same
   shape, and this is the first time §4 has been applied outside a currency figure.**
3. **It must state that validity depends on execution and registration, which Fynla does not
   observe** (Sch 1 paras 2, 4–5). Without this the user reads the checklist as covering the
   whole question.
4. **It must not use the success colour.** Rule 8 maps spring to success. A checklist outcome
   is not a success state, and green is the strongest affirmation the design system can make —
   here attached to the weakest warrant. Which token replaces it is design-lead's; that green
   asserts something is the perimeter point.
5. **It must signpost a qualified solicitor.** The phrasing precedent exists —
   `WillBuilderIntroStep.vue:14` and `WillPlanning.vue:27` both say *"qualified solicitor"* —
   and W-0019 established `WillTypePolicy` as the one home for that family of wording. **Rule
   20: if a referral line lands here it composes from one home; a second copy is a
   violation.**
6. **No FCA-authorisation wording.** Same ruling as W-0019 §3a item 4, and it holds a fortiori:
   an LPA is a creature of the Mental Capacity Act. Pointing at the financial-services regime
   points at the wrong one, and it is the reflex the regime map exists to catch.

---

## Q4 — Is the trunk gap the same one, or distinct?

**Both — and the distinct half is the more useful finding.**

**Same:** the legal-services regime is unmapped (regime-map row 4, drafted today). Generating
an LPA is the same unmapped-regime problem as generating a will, and the map row covers it
without amendment. It also **grew again**: the row was written for wills and trusts; LPAs make
three instruments, and the MCA is a further statute the trunk does not cite.

**Distinct, and new:** the regime map does **not** reach the *assessment*. But something else
nearly does. **§7.3's competence boundary is written as a rule for the compliance agent, and
it is the exact rule the product needs.** The gap is not in the trunk's content — the
principle is present, correct, and well-reasoned. **The gap is in its scope: it binds one of
the two actors it should.**

### Recommended position
Extend §7.3, or add a short §7.5, applying the same boundary to the product:

> **No Fynla surface may tell a user that anything they hold is compliant, approved, valid or
> sufficient.** It may report what was checked, what was not, and what it found. The rule that
> governs the compliance function governs the software for the same reason: a confident-looking
> verdict stops a person from looking, and the person is the last check.

**Adopting** costs a paragraph and narrows what the product claims. **Not adopting** leaves
the trunk in the position of forbidding an agent from saying "compliant" while the application
says it in green, which is not a position that survives being pointed at.

---

## 5. Live exposure vs documentation gap

### Live

1. **The green "Compliant" badge is on production and has been since 2026-03-16**
   (`1a3d17e99`; `origin/main` carries both `LpaComplianceService.php:49` and
   `LpaComplianceChecklist.vue:88,97`). Five months. **This is a live overclaim on a legal
   instrument, and it is the item's sharpest point — sharper than the generator audit, because
   it does not depend on the generator being wrong.** A correct generator plus this badge still
   tells a user something nobody is entitled to tell them.
2. **How many real users hold one is unknown to me.** Same shape as W-0019's count, so —
   pre-stating both branches, since that is the model:
   - **Zero real LPAs** → no user has been given the verdict; the badge is a defect to fix
     before anyone is, and the audit proceeds at ordinary priority.
   - **Any real LPAs** → those users have been shown a green "Compliant" on a statutory
     instrument. My recommendation would then be that the wording is corrected before the
     generator audit concludes, because the two are independent: the badge is wrong even if
     the generator is right, and it is much cheaper to fix.
   **I have not queried production** (`ssh-fynla` is prod; this is local-only).
3. **Whether an LPA generated here would be rejected at registration is unknown and is
   acceptance 1–2.** Recorded as live-if-true rather than assumed either way. The reason it
   belongs on the live list at all is the prescribed-form point in Q1: the failure mode is
   binary and surfaces late.

### Documentation gaps

4. **§7.3 binds the agent and not the product** (Q4). The one I would fix first.
5. Legal-services regime unmapped — already raised; the map row absorbs LPAs without change.
6. **The Powers of Attorney Act 2023 amendments are pending and not in force as at
   20 August 2026.** A "the law is moving" entry for the source register, and a live trap for
   whoever does acceptance 1–3.

---

## Done

- Answered whether the wills reserved-activity analysis carries across: **it does, from the
  same sub-paragraph** — LSA 2007 Sch 2 para 5(3)(a) wills, 5(3)(c) powers of attorney.
  Established rather than assumed, as asked.
- Set out the two ways an LPA is **not** like a will and where that cuts against us:
  a statutorily prescribed form (Sch 1 para 1), and a statutory role-disqualification of
  precisely the W-0024 shape (Sch 1 para 2(6)).
- Ruled on the verdict on grounds that **do not depend on the code audit**: the word overclaims
  whatever it checks, and the object assessed is a form rather than an executed instrument.
- Established that **§7.3 already contains the right rule and applies it to the wrong half of
  the system** — the trunk's own standard, applied to the product, is not met.
- Gave the framing at must-disclose level, six requirements, no drafted copy.
- Confirmed the badge is **on production since 2026-03-16**.

## Not done, and why

- **Acceptance 1–4 untouched.** Not mine; I did not read the renderer or audit the checks, and
  §Q2 is deliberately built so it does not need them.
- **No drafted copy.** Premature until build-lead establishes what the tool does, and it is
  design-lead's craft. §Q3 is the brief for it.
- **No production query** on how many real users hold an LPA. Both branches pre-stated.
- **No determination** that generating or assessing an LPA is permissible or impermissible.
  Not reserved ≠ permitted, and I have not let the first stand in for the second.
- **Did not check commencement of the Powers of Attorney Act 2023 amendments** — flagged as a
  trap rather than resolved.
- Trunk unamended; no code, no PR, no prod.

## Assumptions

- `origin/main` is production, per `CLAUDE.md`'s deployment table. I verified the commit is on
  the branch; I did not observe the server.
- That the nine checks I saw named are all of them. I read method names and the status
  expression, not the bodies — **if there are more checks, Finding 2 is unaffected**, because
  it is about what the app can observe, not how much it checks.
- That "Compliant" reaches the user as rendered. `LpaComplianceChecklist.vue` computes the
  label and the class; I did not confirm the component is mounted on a reachable route —
  **that is acceptance 4 and I have not duplicated it.**

## Needs

- **Gate (CSJ):** extend §7.3 to the product (Q4). My first recommendation of the four I have
  now made, because it is the cheapest and it closes a contradiction rather than a gap.
- **Answer (CSJ or build-lead):** how many real users hold a Lasting Power of Attorney on
  production.
- **Decision (team-lead):** whether the badge wording is corrected ahead of the generator
  audit. They are independent, and the badge is wrong even if the generator is right.
- **Review (design-lead), later:** the replacement framing, once acceptance 1–3 land.

## Noticed — outside my remit, routed

- **build-lead, for acceptance 1:** MCA 2005 Sch 1 para 2(6) — *"The certificate may not be
  given by a person appointed as donee"* — is the statutory version of the W-0024 defect and is
  worth testing first, because it is the one the statute names.
- **build-lead, for acceptance 2:** the property/health-and-welfare split team-lead added is
  the right instinct — Check 9 in the service is already flagged *"(Property only)"*, which
  confirms the model distinguishes them and that at least one check is type-conditional.
  **Not a finding; a pointer to where to start.**
- **archivist:** the regime map drafted today needs no amendment for LPAs, but its
  legal-services row now covers three instruments and a second statute. Worth noting when the
  map is reviewed, as evidence the row is load-bearing rather than theoretical.

---

### Dated source register — W-0100 additions

| Source | Provision | Date |
|---|---|---|
| Legal Services Act 2007 | Sch 2 para 5(1) reserved instrument activities · **5(3)(a) excludes wills** · **5(3)(c) excludes "a letter or power of attorney"** | Latest available (Revised); earliest listed 07/03/2008 |
| Mental Capacity Act 2005 | Sch 1 para 1 prescribed form · para 2(1)(e) certificate provider's three certifications · **para 2(6) certificate may not be given by a donee** · paras 4–5 registration by the Public Guardian | **Pending amendments from 2023 c. 42 (Powers of Attorney Act 2023), not in force as at 20 Aug 2026 — check commencement before relying** |
