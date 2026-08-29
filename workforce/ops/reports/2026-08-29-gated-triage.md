# Gated-item triage — all 96, 2026-08-29

Every one of the 96 `gated` board items, ordered by severity.

- **acceptance** — the item's OWN checklist. `0 of N` means the criteria exist and none is
  ticked; `prose only` means the Acceptance section was never written as a checklist.
- **trace** — whether a test, or an application file, cites the item id.

## The finding

**Not one of the 96 has its acceptance fully evidenced. Zero items are `ALL_TICKED`.**

87 of 96 have a test naming them, which is why they *look* finished. That signal measures
**attention, not completeness** — `W-0463` sits in that bucket with 9 test citations and was
proven this morning to be genuinely unfinished, with four configured reliefs implemented by
nothing. The tests cite the parts that were fixed; the item covers more than those parts.

So the gated bucket is **not** stale paperwork waiting on a signature. It is 96 items of
real outstanding verification, and the two criticals are the place to start:
`W-0154` (0 of 8 criteria ticked) and `W-0463` (now split into W-0524..W-0527).

| item | sev | acceptance | trace | title |
|---|---|---|---|---|
| `W-0154` | critical | 7/16 | test x26 | The same married household is shown two different in |
| `W-0463` | critical | prose only | test x9 | TaxConfigService is the source or it is nothing — 20 |
| `W-0019` | high | prose only | test x34 | Married users are offered a one-sided Simple Will wi |
| `W-0024` | high | **0 of 9** | test x39 | Mirror will copies executors verbatim — the spouse's |
| `W-0030` | high | prose only | test x8 | spouse_pension_percent stored as a decimal by the do |
| `W-0035` | high | **0 of 9** | test x28 | Target Retirement Income has no module-UI entry poin |
| `W-0036` | high | **0 of 9** | test x25 | A Defined Benefit pension is counted as income in pa |
| `W-0039` | high | prose only | test x25 | The holding form has no quantity/units input — every |
| `W-0041` | high | prose only | test x6 | Every chattel delete succeeds and then returns 500 — |
| `W-0047` | high | prose only | test x4 | Google Analytics falls back to the hardcoded product |
| `W-0049` | high | prose only | test x8 | Consent is neither enforced nor recorded server-side |
| `W-0051` | high | 9/20 | test x13 | Onboarding creates a spouse family member with no ac |
| `W-0053` | high | **0 of 7** | test x19 | Completing a mirror will strands the user — "Generat |
| `W-0091` | high | **0 of 9** | test x2 | Business Property Relief is applied as binary 100% w |
| `W-0101` | high | prose only | test x2 | The will document renderer draws the testator's and  |
| `W-0103` | high | prose only | test x2 | Nothing stops a Lasting Power of Attorney donor bein |
| `W-0114` | high | 8/9 | test x3 | Adding a Partner or a Step Child returns HTTP 500 —  |
| `W-0121` | high | prose only | test x7 | A typed holding value is silently overwritten by the |
| `W-0122` | high | prose only | test x3 | Fyn's holding creation carries a second copy of the  |
| `W-0132` | high | prose only | test x23 | The Inheritance Tax rate shown to the user is not th |
| `W-0134` | high | prose only | test x15 | The estate column does not add up — four allowance r |
| `W-0135` | high | prose only | test x11 | Two estate screens give different projected Inherita |
| `W-0136` | high | prose only | test x21 | The residence nil rate band taper is never applied t |
| `W-0137` | high | prose only | test x7 | Projected cash goes to minus £1.8m — a Cash ISA proj |
| `W-0157` | high | **0 of 5** | test x1 | The will signing step states three unsourced facts t |
| `W-0172` | high | prose only | test x20 | A tenants-in-common property saves its mortgage as j |
| `W-0173` | high | prose only | test x9 | Rental income from a jointly-owned buy-to-let reache |
| `W-0174` | high | prose only | test x4 | The Personal Allowance is correctly tapered to £0 bu |
| `W-0175` | high | prose only | test x5 | Rental income is stated two different ways on the sa |
| `W-0186` | high | prose only | test x12 | A joint-life protection policy is invisible to the o |
| `W-0187` | high | prose only | test x1 | Protection charges one person the entire household's |
| `W-0188` | high | prose only | test x7 | The two logins still project household estates £103, |
| `W-0203` | high | prose only | **no trace** | A mortgage recorded in the liabilities table was cou |
| `W-0206` | high | prose only | test x3 | Goals reports a "Current Net Worth" that is wrong on |
| `W-0217` | high | prose only | test x12 | A £85,000 medium-risk portfolio projects higher than |
| `W-0228` | high | prose only | test x8 | A mortgage secured on a tenants-in-common property i |
| `W-0236` | high | prose only | test x1 | The mortgage share cannot be entered — the form offe |
| `W-0237` | high | prose only | code x3, no test | Total Balance Owed sums full balances with no shar |
| `W-0238` | high | prose only | test x13 | The dashboard module cards are a second, wrong answe |
| `W-0244` | high | prose only | test x8 | The retirement module reports "not yet set up" for a |
| `W-0255` | high | prose only | **no trace** | The "80% Probability" band was a straight line betwe |
| `W-0257` | high | prose only | test x4 | An investment account whose holdings exceed 100% all |
| `W-0264` | high | 1/4 | test x1 | The per-product risk override has never worked for a |
| `W-0272` | high | prose only | test x1 | A linked spouse is assessed as childless, and told s |
| `W-0274` | high | prose only | test x2 | Two more answers to "how big is the emergency fund"  |
| `W-0278` | high | prose only | test x2 | LifeCoverReach keeps reading a deleted partner's pol |
| `W-0322` | high | 2/4 | test x4 | Collapsing "Additional information" and pressing Upd |
| `W-0333` | high | prose only | test x3 | The projected estate carries £177,000 belonging to a |
| `W-0342` | high | prose only | code x1, no test | EstateAgent still asks which life policies exist wit |
| `W-0344` | high | prose only | **no trace** | A spouse link claimed from one side only discloses t |
| `W-0395` | high | 7/8 | test x1 | Mirror wills generated before W-0024 still appoint t |
| `W-0396` | high | 5/6 | test x2 | The mirror generator matched one spelling of each pa |
| `W-0401` | high | prose only | test x1 | The coordination plan tells the non-owning spouse to |
| `W-0411` | high | prose only | test x2 | Every overdue goal reports "On track" and the goals  |
| `W-0432` | high | 1/11 | code x1, no test | Rate literals survive in user-facing strings across  |
| `W-0451` | high | **0 of 5** | test x10 | The estate decision trace publishes a saving of £19, |
| `W-0452` | high | **0 of 5** | test x5 | One page shows a charitable percentage that cannot b |
| `W-0464` | high | prose only | test x1 | The Free-tier estate teaser runs a second, independe |
| `W-0025` | medium | **0 of 5** | test x14 | A joint chattel saves with no joint owner and no err |
| `W-0031` | medium | prose only | test x6 | education_level validation accepts three values the  |
| `W-0032` | medium | prose only | test x7 | scheme_status is collected by both pension forms and |
| `W-0034` | medium | prose only | test x2 | /m has no Health & Lifestyle section at all — the da |
| `W-0040` | medium | prose only | test x30 | A deliberate 100/0 joint split is unexpressible, and |
| `W-0044` | medium | prose only | test x4 | The native iOS app has no route to the Will Builder  |
| `W-0100` | medium | prose only | test x9 | The Lasting Power of Attorney document generator and |
| `W-0111` | medium | **0 of 5** | test x6 | Adding a Partner asks for an email address "to creat |
| `W-0112` | medium | **0 of 4** | test x1 | Editing a linked spouse's name never reaches their a |
| `W-0113` | medium | **0 of 4** | test x2 | Two Fyn tools write a spouse and only one can link — |
| `W-0115` | medium | **0 of 5** | test x6 | Two more relationship formatters survive outside the |
| `W-0126` | medium | prose only | test x3 | Seven more holding-valuation copies sat outside the  |
| `W-0140` | medium | prose only | test x13 | /plans/estate states an Annual Expenditure neither u |
| `W-0143` | medium | prose only | code x2, no test | The will builder's signing step tells the user these |
| `W-0161` | medium | prose only | test x3 | Fyn stored every joint liability at 100/0 — half the |
| `W-0189` | medium | prose only | test x6 | The Income Definitions panel shows a chain of labell |
| `W-0207` | medium | prose only | test x2 | A completed 2020 life event is counted as future exp |
| `W-0210` | medium | prose only | test x2 | A goal is counted and labelled as a life event — Sar |
| `W-0335` | medium | prose only | test x1 | /api/savings returns 'analysis' => null as a placeho |
| `W-0383` | medium | prose only | test x4 | Product call — how much of someone else's contract s |
| `W-0394` | medium | 7/8 | test x1 | Every bequest is stored as a gift to a person — both |
| `W-0399` | medium | 8/9 | test x2 | The Charitable Bequest card states £20,000 and £10,0 |
| `W-0431` | medium | 4/5 | test x2 | The Inheritance Tax rate messages asserted 40%, 36%  |
| `W-0433` | medium | 4/7 | test x6 | The charitable percentage and the threshold it is co |
| `W-0470` | medium | prose only | code x3, no test | The controller recomputes the projected net estate o |
| `W-0033` | low | prose only | test x6 | ComprehensiveProtectionPlanService reads two user pr |
| `W-0045` | low | 5/6 | test x3 | All three relevant-property trust surfaces use non-p |
| `W-0336` | low | prose only | code x1, no test | Projected liabilities are taken at 100% for each mem |
| `W-0006` | (unset) | **0 of 6** | test x25 | Health & Lifestyle form silently drops health_status |
| `W-0007` | (unset) | **0 of 5** | test x8 | Investment account modal reports £0 Cash ISA usage — |
| `W-0010` | (unset) | **0 of 6** | test x16 | Dead-end — a user whose only pension is Defined Bene |
| `W-0011` | (unset) | **0 of 7** | test x18 | Free-tier users cannot save monthly expenditure at a |
| `W-0014` | (unset) | **0 of 7** | test x33 | Joint investment accounts save a 100% owner share —  |
| `W-0015` | (unset) | **0 of 8** | test x31 | The same joint account's share is computed three dif |
| `W-0017` | (unset) | **0 of 8** | test x20 | Defined Benefit pension form cannot hold four of the |
| `W-0020` | (unset) | **0 of 6** | test x27 | Charitable bequest total checks bequest_type === 'sp |
| `W-0176` | (unset) | prose only | test x3 | A linked spouse's annual income displays as £0 on /s |
| `W-0177` | (unset) | prose only | test x1 | The readiness panel lists "Income needs updating" un |
