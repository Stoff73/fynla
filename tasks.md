# tasks.md — the Fynla board, one task at a time

Generated from `workforce/ops/board/` on 2026-08-31. **Regenerate, never hand-edit the counts.**

The loop is `/Users/CSJ/.claude/skills/board-loop/SKILL.md`. Every live bug goes
through `superpowers:systematic-debugging` before a line of code is changed.

**Outstanding: 110**  ·  closed 2026-08-31: 22

---

## Closed 2026-08-31

- [x] **W-0006** (unset) — Health & Lifestyle form silently drops health_status and smoking_status — never persisted
- [x] **W-0007** (unset) — Investment account modal reports £0 Cash ISA usage — overstates remaining ISA allowance and lets the £20,000 limit be breached
- [x] **W-0010** (unset) — Dead-end — a user whose only pension is Defined Benefit cannot add any further pension
- [x] **W-0011** (unset) — Free-tier users cannot save monthly expenditure at all — Simple View always sends the detailed payload and trips the Premium gate
- [x] **W-0014** (unset) — Joint investment accounts save a 100% owner share — spouse gets nothing, primary owner double-counts
- [x] **W-0015** (unset) — The same joint account's share is computed three different ways — investments page says £95,000, wealth summary says £47,500
- [x] **W-0017** (unset) — Defined Benefit pension form cannot hold four of the fields the model and the persona both have
- [x] **W-0020** (unset) — Charitable bequest total checks bequest_type === 'specific', a value the enum cannot hold — cash legacies never reduce the IHT rate
- [x] **W-0154** (critical) — The same married household is shown two different inheritance tax bills depending on which spouse logs in, and the allowance components do not sum to the totals
- [x] **W-0176** (unset) — A linked spouse's annual income displays as £0 on /settings/family — the row serves a stale column instead of the account behind it
- [x] **W-0177** (unset) — The readiness panel lists "Income needs updating" under COMPLETED while reporting OUTSTANDING (0) — All items complete
- [x] **W-0432** (high) — Rate literals survive in user-facing strings across the estate services — including a class that computes the same threshold from configuration two hundred lines above
- [x] **W-0461** (medium) — The Rule 2 sweeps swept app/ and exactly one Vue file — nine rate literals stand in the estate frontend, five of them live, one of them a wrong statement of the statutory test
- [x] **W-0495** (high) — A household with no recorded expenditure is told it has zero months of runway, however much cash it holds

---


## Critical (1)

- [x] **W-0463** `done` — TaxConfigService is the source or it is nothing — 20 configured rules have zero consumers, and every guard built to catch this is structurally incapable of seeing it

## High (11)

- [x] **W-0037** `done` — Bequest form cannot record priority order, beneficiary type or charity registration — charitable status is inferred from the beneficiary's name
- [x] **W-0050** `done` — You cannot create an account without consenting to Google Analytics and Awin affiliate tracking — a cookie wall, justified by copy that is factually untrue
- [ ] **W-0133** `queued` — Completing a will is a one-way door — "Complete & Finalise" never returns, so a gift edited or a bequest deleted after finalising can never be re-synced and the will document and the Estate module diverge permanently
- [ ] **W-0144** `queued` — The generated will revokes every earlier will and imposes a 28-day survivorship period, and the user is never asked about either
- [ ] **W-0155** `queued` — Cookie consent can be given and seen but never withdrawn — F-0007 made Decline meaningful and thereby created a right with no interface
- [ ] **W-0171** `queued` — The estate calculation cannot be audited by the person whose money it is — £500,000 leaves the estate silently, £10,000 is deducted invisibly, and the rule that reverses it all on 2027-04-06 is never mentioned
- [ ] **W-0462** `queued` — \"Save £74,987\" is attached to an action that leaves the beneficiaries £37,891 worse off — the tax figure is correct and the disclosure is missing
- [x] **W-0508** `done` — Fourteen more sites read ['married'] alone, so a civil partnership is still treated as single across the Estate API, four agents and three services
- [ ] **W-0513** `queued` — The projected estate models only defined contribution pots, where IHTA 1984 s150A brings in lump sum death benefits too
- [ ] **W-0514** `queued` — A pension on the first death can destroy the second death's residence band, and the model cannot show it
- [x] **W-0515** `done` — Fyn still tells the user pensions pass outside the estate, and quotes today's pot as the amount at risk

## Medium (95)

- [ ] **W-0025** `gated` — A joint chattel saves with no joint owner and no error — 50% of the asset belongs to nobody
- [ ] **W-0031** `gated` — education_level validation accepts three values the column enum cannot hold — latent 500 for Fyn and any API client
- [ ] **W-0032** `gated` — scheme_status is collected by both pension forms and silently discarded on every save — no such column exists
- [ ] **W-0034** `gated` — /m has no Health & Lifestyle section at all — the data source is fixed but no mobile screen renders it
- [ ] **W-0040** `gated` — A deliberate 100/0 joint split is unexpressible, and three acceptance criteria contradict each other on whether it should be
- [ ] **W-0044** `gated` — The native iOS app has no route to the Will Builder — WebHandoffClient lacks the estateWill case the PHP enum and /m both have
- [ ] **W-0054** `in-progress` — Two tier caps, two gating philosophies — life events block before entry, detailed expenditure blocks after submit with a silent 403
- [ ] **W-0090** `queued` — Native never says a retirement target was inferred — it shows nothing at all, where web and /m now show the derived figure and label it
- [ ] **W-0100** `gated` — The Lasting Power of Attorney document generator and its compliance service have never been reviewed — the will builder's sibling, unexamined
- [ ] **W-0104** `queued` — An attorney's age is never checked — a child can be appointed attorney on a Lasting Power of Attorney
- [ ] **W-0105** `queued` — No bankruptcy question exists for a property and financial affairs Lasting Power of Attorney
- [ ] **W-0106** `queued` — A professional certificate provider is failed by the two-year rule, despite the field for the professional route already existing
- [ ] **W-0107** `queued` — The replacement-attorney check states a legal consequence that is wrong for the commonest appointment type
- [ ] **W-0108** `queued` — The health and welfare Lasting Power of Attorney document is silent on when attorneys may act — the one restriction that is statutory for that type
- [ ] **W-0109** `queued` — The Lasting Power of Attorney registration fee and timescale are stated in three places with no single home, and the timescale looks stale
- [ ] **W-0110** `queued` — There is no Lasting Power of Attorney surface on /m or iOS, yet Fyn can create one from both — a record the user can never see again
- [ ] **W-0111** `gated` — Adding a Partner asks for an email address "to create or link their account", then silently discards it — no account, no link, no error
- [ ] **W-0112** `gated` — Editing a linked spouse's name never reaches their account — `users.name` is an appended accessor with no column, so the sync is silently discarded
- [ ] **W-0113** `gated` — Two Fyn tools write a spouse and only one can link — `create_family_member` has no email parameter, so it can only ever produce an unlinked household
- [ ] **W-0115** `gated` — Two more relationship formatters survive outside the family surfaces, and one of them can still tell a user their partner is a dependent
- [ ] **W-0126** `gated` — Seven more holding-valuation copies sat outside the one home, and three were in one controller
- [ ] **W-0127** `queued` — An imported holding can store units and a value that contradict each other, and reconciling silently overwrites one of them
- [ ] **W-0131** `queued` — The Inheritance Tax calculation cache is never written — `persist` is never passed true, so `iht_calculations` is empty for every user and every estate view recomputes in full
- [ ] **W-0140** `gated` — /plans/estate states an Annual Expenditure neither user entered — £39,420 against a recorded £29,400, and £7,500 for a user with no expenditure recorded at all — and it drives Disposable Income
- [ ] **W-0142** `queued` — The shared-asset counterparty rule guards chattels only — properties and mortgages can still be orphaned today, through the forms and through Fyn
- [ ] **W-0143** `gated` — The will builder's signing step tells the user these steps make their will legally valid — the same overclaim compliance just removed from the document footer
- [ ] **W-0145** `queued` — Completion is not blocked when a Lasting Power of Attorney names a certificate provider the statute disqualifies — the will builder blocks its equivalent
- [ ] **W-0152** `queued` — Divorce terminates an attorney's appointment and the instrument may opt out of that — an election the wizard never offers and the document never mentions
- [ ] **W-0153** `queued` — A legal rule stated in Fynla's own unattributed voice on a will sits beside an attributed one on a power of attorney, and nothing makes the difference visible
- [ ] **W-0156** `queued` — An anonymous consent row for a visitor who never registers is kept indefinitely — no purge, no expiry, and neither retention path reaches it
- [ ] **W-0161** `gated` — Fyn stored every joint liability at 100/0 — half the debt attributed to nobody
- [ ] **W-0178** `queued` — Decide whether the monthly maintenance reserve and "other" property costs belong in the allowable-letting-expenses list that produces every user's rental profit
- [ ] **W-0189** `gated` — The Income Definitions panel shows a chain of labelled steps whose arithmetic does not work — £147,690 less £11,600 is displayed as £147,690
- [ ] **W-0196** `queued` — Seven retirement-age defaults and four copies of the priority chain — 68 in three services, 67 in four, and two different orderings
- [ ] **W-0197** `queued` — State Pension age is legislated by cohort, and the application holds two static keys — a projection decades out needs a resolver, not a choice between 66 and 67
- [ ] **W-0198** `queued` — Two columns hold one life expectancy — the override now agrees everywhere, the fallbacks still do not
- [ ] **W-0199** `queued` — A projected cash shortfall never draws on investments, so a household runs out of money while holding an untouched portfolio
- [ ] **W-0200** `queued` — A joint-life policy records that it covers two lives but never records whose — the second life assured can only be inferred from users.spouse_id
- [ ] **W-0207** `gated` — A completed 2020 life event is counted as future expected income and displayed as happening "In 0 years
- [ ] **W-0208** `queued` — The letter/will consistency check flags a punctuation difference as an executor mismatch and tells the user to edit a legal document
- [ ] **W-0210** `gated` — A goal is counted and labelled as a life event — Sarah has zero life events and the module reports "1 cash outflow events £400K
- [ ] **W-0243** `queued` — The native iOS retirement card cannot show a guaranteed income, so a defined-benefit-only spouse still reads £0 there
- [ ] **W-0258** `queued` — The card captions an arithmetic "expected return" beside a median projection that is lower by volatility drag, and the two cannot be reconciled by the reader
- [ ] **W-0259** `queued` — The single figure on the projection card is the 20th percentile — the one band where taking more risk makes the number go down
- [ ] **W-0275** `queued` — Eight consumers still ask "who depends on this user" with a user_id-only query
- [ ] **W-0280** `queued` — Census — user_id-only queries over records that can be shared. Every line is a code-read hypothesis until measured; the first one I published was WRONG
- [ ] **W-0311** `queued` — The native Pensions category still calls the figure "Accessible pension capital" and carries no exclusion note, so a Defined Benefit holder sees a bare £0
- [ ] **W-0321** `queued` — Nothing enforces the 100% holdings allocation total on write, so any account can be pushed past 100% and into the state W-0257 could not escape
- [ ] **W-0324** `queued` — holdings.*.dividend_yield has no rule in any nested holdings array, so a yield entered through the account or pension form is silently discarded
- [ ] **W-0330** `queued` — A joint owner is shown Edit and Delete buttons on a shared investment account that can only ever return 404
- [ ] **W-0334** `queued` — The estate projection silently ignores a user's chosen investment growth method, because the code that honours it is unreachable
- [ ] **W-0335** `gated` — /api/savings returns 'analysis' => null as a placeholder, nothing dispatches the analyze action, and the store then reads a key that does not exist
- [ ] **W-0337** `queued` — W-0280 §1 and F-0024 §10 state a double-count mechanism that cannot occur, and a 59-site sweep is queued behind it
- [ ] **W-0338** `queued` — The headline estate's liability reader can drop a co-owner's share of a mortgage the row does not name, inflating the estate and the tax
- [ ] **W-0346** `queued` — A granted spouse permission cannot be withdrawn — the status enum has no revoked value
- [ ] **W-0351** `queued` — A mixed-rate mortgage's fixed and variable rates are stored correctly and can never be displayed — the detail view gates on two fields MortgageResource does not serialise
- [ ] **W-0366** `queued` — Chargeable lifetime transfers made 7–14 years before death wrongly reduce the death estate's own nil rate band, and the comment above the line states the correct rule
- [ ] **W-0367** `queued` — Gift values are taken gross, so none of the lifetime exemptions that reduce a chargeable transfer are applied, overstating tax in every case
- [ ] **W-0369** `queued` — The residence nil rate band may be wrongly excluded from the 10% charitable-rate baseline — flagged for verification, not asserted
- [ ] **W-0370** `queued` — The 7- and 14-year statutory gift windows are hardcoded while TaxConfigService already carries them
- [ ] **W-0371** `queued` — Tax rates and thresholds are hardcoded in the user-facing sentences printed beside figures the arithmetic computed from configuration
- [ ] **W-0373** `queued` — Liability institution names and balances are written to the application log at INFO on every Inheritance Tax calculation
- [ ] **W-0376** `queued` — Four dead sites found in one day, and the dead code carries its own copies of live rules
- [ ] **W-0383** `gated` — Product call — how much of someone else's contract should the other life assured see
- [ ] **W-0385** `queued` — fynla-state.auth.user can name a different user than the token authenticates as, and it is our documented way of checking identity
- [ ] **W-0392** `queued` — The estate a will screen states omits Business Property Relief assets, which do pass under the will
- [ ] **W-0394** `gated` — Every bequest is stored as a gift to a person — both charitable legacies included, because beneficiary_type reaches no request class
- [ ] **W-0398** `queued` — A residuary substitution beneficiary is invisible to every consumer of the bequests table — which is why this household reads as though its children are unprovided for
- [ ] **W-0399** `gated` — The Charitable Bequest card states £20,000 and £10,000 for the same legacy, two sentences apart, on both spouses' accounts
- [ ] **W-0413** `queued` — rent and utilities never persist from the expenditure form — both endpoints accept them and neither validates them
- [ ] **W-0414** `queued` — The goal plan reads a months_remaining key GoalProgressService has never returned, and silently runs on a default of 12
- [ ] **W-0416** `queued` — iOS native carries two copies of the goal status vocabulary and cannot say Overdue, so it reads "Behind" for a goal whose date has gone
- [ ] **W-0424** `queued` — A pension contribution recorded as a percentage never becomes a financial commitment, so it never reaches expenditure and disposable income is overstated by it
- [ ] **W-0426** `queued` — The letter_to_spouse capability gates writes only — every GET under api/user/ short-circuits before the capability check, so the letter has never been read-gated
- [ ] **W-0431** `gated` — The Inheritance Tax rate messages asserted 40%, 36% and 10% as literals while the calculation beside them read the real figures from configuration
- [ ] **W-0433** `gated` — The charitable percentage and the threshold it is compared against are percentages of different things — 0.6% against 10%, where the statutory figure is 0.81%
- [ ] **W-0442** `claimed` — The holdings tables hide what they store — and the investment one has never rendered at all, behind a duplicated v-else-if
- [ ] **W-0443** `queued` — The holding asset-type vocabulary exists as eleven independent copies across four directories, and nothing makes them agree
- [ ] **W-0453** `queued` — A null-defaulting tax getter reaches .toLocaleString() unguarded at two template sites — throws on a cold load, in a fallback block nothing else covers
- [ ] **W-0470** `gated` — The controller recomputes the projected net estate on a liabilities figure the projected taxable estate was never struck on, so the two rows disagree on screen
- [ ] **W-0472** `queued` — The address a user invites their partner on is used once and never stored, so nobody can see who was invited, correct a typo, or re-send
- [ ] **W-0476** `queued` — The account-enumeration oracle moved one endpoint over — two requests still distinguish a registered address from an unregistered one
- [ ] **W-0483** `queued` — A co-owner who borrowed alone cannot be shown as owing alone, and only CSJ can change that
- [ ] **W-0488** `queued` — peak_earners resolves to £1,250 a month and so reports 59.8 months of emergency runway
- [ ] **W-0492** `review` — The E2E consent fixture seeds a key nothing reads, so the banner blocks every landing-page test
- [ ] **W-0494** `review` — Four Architecture tests compare native filesystem paths and fail only on Windows
- [ ] **W-0499** `open` — investments_exotic is advertised as a Premium feature and enforced nowhere, so a free user is not actually prevented from using it
- [ ] **W-0500** `open` — /m and native users can never answer the question the undivided-share discount turns on, so W-0368 is permanently inert on those surfaces
- [ ] **W-0507** `queued` — The free-tier estate teaser prints a second-death Inheritance Tax figure with none of the caveats the full table carries
- [ ] **W-0510** `queued` — A drawn-out pension fund never reports as depleted, so "years funded" is always the full horizon
- [ ] **W-0516** `queued` — The State Pension age is a literal 67 in the retirement engine and a configured 66 everywhere else
- [ ] **W-0518** `queued` — Fyn captures salary sacrifice without asking whether the recorded employment income is before or after the pay given up
- [ ] **W-0522** `gated` — The trust strategy still hardcodes the taper relief band table, the last copy in the estate services
- [x] **W-0525** `done` — Normal Expenditure Out of Income is a label on a strategy and is never computed
- [x] **W-0526** `done` — The 14-year rule is configured and mentioned nowhere in the estate or tax services

## Low (11)

- [ ] **W-0033** `gated` — ComprehensiveProtectionPlanService reads two user properties that never exist — dead branches, and fixing them would change which source drives protection advice
- [ ] **W-0045** `gated` — All three relevant-property trust surfaces use non-palette blue-* and green-* — a live Rule 11 breach
- [ ] **W-0336** `gated` — Projected liabilities are taken at 100% for each member while the headline applies the share, so a third-party-shared debt understates tax
- [ ] **W-0481** `queued` — AssetFactory randomly generates four asset types the column rejects, so any use without an explicit type fails about half the time
- [ ] **W-0496** `open` — The native joint-life rows and the suppressed edit affordance have never been looked at on a screen
- [ ] **W-0497** `open` — The estate strategy and onboarding text meets the user cold with six acronyms — RNRB, NRB, IHT, PET, CLT and GROB
- [ ] **W-0498** `open` — The joint-ownership configuration cluster is live, populated and read by nothing — three accessors with zero callers
- [ ] **W-0503** `queued` — The 'Platform updates' insight tag uses text-light-blue-700, a class Tailwind never emits, so the text takes whatever colour it inherits
- [ ] **W-0504** `queued` — Three of the /m dashboard's donut rings are filled to hardcoded constants, so the arc means nothing
- [ ] **W-0506** `queued` — The consistency sweep reports 91 findings and nothing has been reading it
- [x] **W-0527** `done` — Quick succession relief is configured and implemented by nothing
