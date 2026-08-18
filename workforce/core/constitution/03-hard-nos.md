# 03 — Hard Nos

**Status:** Ratified in part by CSJ, 2026-08-13, session 4. §4 open.
**Owner:** CSJ. Amendments gated.

What Fynla does not build and does not become. Values live in `02-values.md`;
engineering rules live in `CLAUDE.md`. Each entry keeps its reason, because a hard
no without a reason cannot be applied to a case nobody anticipated.

---

## 1. Strategic trade-offs

Adopted from `April/April19Updates/marketing/04-product-strategy.md` §5, verbatim
with reasons. One amended — see §2.

| We choose | Over | Because |
|---|---|---|
| **Modelling money** | Managing money (robo-advisor) | Regulated asset management means FCA Part IV permission, compliance burden, slower cycles. Tooling is defensible; allocation is commoditised. |
| **UK-only, deep** | Multi-country, shallow | Tax depth is the moat. Going wide dilutes the one asset that cannot easily be rebuilt. |
| **Household as the unit** | Individual-account-first | Couples are 60% of UK wealth and every incumbent treats them as two disconnected users. The architecture is already built this way. |
| **AI-augmented tool** | "Full replacement of an IFA" | Positioning as advice invites FCA scrutiny; positioning as a tool keeps accountants and solicitors as partners rather than threats. |
| **Accountants as channel** | Accountants as product | B2B-first would cost consumer velocity and mean fighting Xero head-on. |
| **Tax engine in-house** | Licensing one | `TaxConfigService` plus the Estate module is the thing no competitor has. Licensing it commoditises us. |
| **No community or social layer** | Forum, "share your plan" | Financial data is private; a social layer would bleed trust and engineering focus. **(V5)** |
| **No real-time trading** | Trading execution | Different regulatory regime, different product, different team. |
| **Monthly cadence** | Big quarterly releases | Budget-day changes, Finance Bill, HMRC guidance — speed of tax-rule accuracy is itself a feature. |

## 2. Pricing — amended, session 4

The strategy document's tenth trade-off read *"Subscription over freemium with ads
/ AUM fees"*. **The conclusion is void; the reason survives.**

**Ratified CSJ session 4; verified in code session 7.** The app is **a free tier,
forever, plus one paid upgrade on subscription.** That is the whole model.

**Evidence — this is implemented, not planned:**

| Where | What it shows |
|---|---|
| `app/Services/Tiers/TierResolver.php` | `private const LEGACY_PAID_PLANS = ['student', 'standard', 'family', 'pro']` — the old four are **named as legacy in code** |
| Same, docblock | "NO mechanical plan→tier map… preview / no-sub / legacy-paid all resolve to **`free`** for gating arithmetic" |
| `isGrandfatheredLegacyPaid()` | Existing paid subscribers keep their access; the gate never narrows it |
| `PremiumEntitlementResolver` | Premium is the single upgrade |
| `TierCollapseLock`, `TierCollapsePreflight`, `RevolutTierVariationSync` | The cutover machinery, including a payment lock during identity collapse |

**`SubscriptionPlanSeeder` still seeds four plans and this is correct** — they exist
to honour grandfathered subscribers, not to be offered. **Reading the seeder as the
pricing model is a mistake** (one this workforce made); the entitlement layer is
`TierResolver` and `PremiumEntitlementResolver`.

So Fynla *is* freemium. What was never permitted, and still is not:

| Hard no | Because |
|---|---|
| **Advertising, anywhere in the product** | Ads destroy trust and make the product adversarial to its user (**V3**) |
| **Assets-under-management fees** | Put us in an adversarial position to clients, and require FCA permission |
| **Referral kickbacks or paid placement** | Same reason; "never selling you a product" |
| **More than one paid tier** | The simplification was deliberate. Adding tiers is a trunk amendment, not a product decision. |

The original trade-off named ads and AUM fees as its reasons — never the free tier
itself. A free tier carrying neither was never actually excluded by that reasoning,
and it is now the model.

The strategy doc's four tiers at £3.99–£29.99 are **superseded**. Commercial detail
belongs in `06-commercials.md`, session 7.

## 3. Who Fynla does not serve

**Exclusion is on capability, never on income** (`01-mission.md` §4).

| Excluded | Because |
|---|---|
| **Non-UK residents** | The tax engine is irreducibly UK-specific. International is a different product. |
| **Business-only customers** | The edge is the personal–business bridge, not the business. Xero, FreeAgent and QuickBooks win there. |
| **Under-18s** | Lifetime ISA floor is 18; legal friction too high. |

**Three exclusions, and only three.** All structural — the product genuinely cannot
serve these people well. Two were removed for being wealth bands rather than
capability limits:

| Removed | When | Why |
|---|---|---|
| Sub-£30k earners with no assets | Session 3 | Income threshold. Contradicted by `student` and `young_saver` being clients. |
| **High net worth above £5m** | **Session 4 — CSJ: "HNW included"** | Wealth threshold. The stated reason — "already have private bankers, Coutts, SJP" — is a positioning argument, not a capability one, and `01-mission.md` §2 forbids wealth as a criterion. |

*A prior inconsistency resolves itself here:* §1's advertising trade-off cites
"trust with the HNW cohort" as a reason to refuse ads, while the old list excluded
that cohort as customers. With HNW included, the reason and the audience finally
agree.

**One thing to check, not a doctrine question.** Crypto turned a "not built for"
into a silent completeness problem. HNW clients may carry structures Fynla does not
model — family investment companies, offshore or non-domicile positions, complex
trust arrangements. Some machinery clearly exists (the Estate module, trusts,
`banking_licence_groups.php` for FSCS limits). **Logged as a Cartographer survey
task**, not an open ruling: establish what HNW-specific structures are and are not
modelled, and route anything material to the same disclosure question as crypto.

## 4. Resolved — day-traders and crypto are not excluded

**CSJ, 2026-08-13: "have not built for."**

By this file's own test, that makes it a **roadmap gap, not a hard no.** Removed
from the exclusion list. Someone who trades actively or holds crypto is a client
Fynla has not yet built for — not a client Fynla declines.

### 4.1 The consequence, which is not a roadmap item

Verified 2026-08-13: **crypto is not modelled as an asset class.** It appears only
in Estate will and letter documents, as something a will can mention. It is absent
from net worth, investment and inheritance-tax calculation.

So a client holding meaningful crypto gets **a picture that is silently
incomplete** — in a product whose entire premise is "seeing business, pension,
property, and estate as one living picture."

That is not a gap in coverage. It is a **V2 problem**: the person does not
understand their own situation, and does not know they don't. An incomplete
inheritance-tax figure presented without qualification is worse than no figure.

**Carried to `05-perimeter.md` (session 6)** as a disclosure question: what must
Fynla say when it knows its picture is incomplete? Also logged on the capability
map as a known non-coverage.

**Not carried here.** A hard no it is not, and it will not be smuggled back in as
one.

## 5. Open

**None. Session 4 closed 2026-08-13.** The pricing model in §2 is verified in code,
not pending.

## 5. Referenced, never restated

These are absolute and CSJ-owned. They live in `CLAUDE.md` and are pointed at from
here — copying them would create the second home Rule 20 exists to prevent.

| Rule | Subject |
|---|---|
| **8** | No amber, orange, or non-palette colours |
| **12** | No scores in user-facing UI (**V2**) |
| **15** | Icons functional only; decorative banned |
| **20** | Every Fyn change made once, in one place, for all surfaces |
