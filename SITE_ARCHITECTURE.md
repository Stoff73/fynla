# Fynla — Site Architecture & Navigation Strategy

> Reference file for Claude Code / CLI. Covers nav structure, content strategy,
> stage names, personas, and all decisions made during the navigation planning session.
> Add a line to CLAUDE.md pointing here: `See SITE_ARCHITECTURE.md for site architecture decisions.`

---

## Brand Colours (Tailwind tokens)

| Token | Hex | Usage |
|---|---|---|
| `spring-500` | `#20B486` | Primary CTA, links, active states |
| `horizon-500` | `#1F2A44` | Nav text, body text, dark backgrounds |
| `horizon-600` | darker navy | Dark section backgrounds |
| `horizon-700` | darkest navy | Hero dark end |
| `light-pink-100` | `#FAD6E0` | Section backgrounds, Sign in button |
| `light-blue-500` | `#6C83BC` | Secondary actions (Ask Fyn) |
| Hero gradient | `#1F2A44` → `#E83E6D` | Hero section background |

Font: `Inter` / `Segoe UI` (system stack)

---

## Navigation Philosophy

**Lead with the person, not the product.**

The primary nav item should put the visitor's life stage at the centre before
the product features. Someone landing on Fynla should immediately self-identify
— "this is for me" — before they understand what the product does.

**Current nav (as-is):**
```
Home | Features ▾ | Scenarios ▾ | Pricing | Learning centre ▾
```

**Recommended nav (to-be):**
```
Home | Your stage ▾ | How it works | Learn ▾ | Insights ▾ | Calculators | Why Fynla ▾ | Pricing
```

### Key changes and rationale

| Change | Rationale |
|---|---|
| "Scenarios" → "Your stage" | "Stage" is ownable, implies journey, short enough for nav |
| "Features" → "How it works" | De-emphasises feature inventory, leads with outcomes |
| "Learning centre" → "Learn ▾ + Insights ▾" | Separates evergreen education from timely commentary |
| Calculators as standalone nav item | High SEO value, high intent, deserves direct access |
| Add "Why Fynla ▾" | Houses comparison pages, trust content, security |
| Keep "Pricing" | Clear, expected, no change needed |

> **Mobile note:** Seven nav items is at the ceiling for desktop comfort.
> On mobile, merge Learn and Insights under a single "Learn ▾" dropdown.

---

## Life Stages — "Your stage" dropdown

Five stages, consistent gerund rhythm throughout.

| # | Stage name | Colour | Sub-description (dropdown) |
|---|---|---|---|
| 1 | Starting Out | `#1D9E75` | First job, first steps |
| 2 | Building Foundations | `#5DCAA5` | Saving, buying, growing |
| 3 | Protecting and Growing | `#378ADD` | Family, home, investments |
| 4 | Planning Your Future | `#7F77DD` | Peak earning, retirement prep |
| 5 | Enjoying Your Wealth | `#EF9F27` | Later life, legacy, estate |

### Colour progression rationale
Green → teal → blue → purple → amber reads as a journey without labelling it.

### Stage card questions (homepage)
Each stage card leads with the visitor's anxiety, not a product description:

- **Starting Out** — "Where do I even begin?"
- **Building Foundations** — "Am I actually making progress?"
- **Protecting and Growing** — "Are we protected — and are we moving forward?"
- **Planning Your Future** — "Can I actually afford to stop working?"
- **Enjoying Your Wealth** — "What happens to everything when I'm gone?"

---

## Demo Personas — mapped to stages

| Stage | Persona(s) | Type |
|---|---|---|
| Starting Out | Janice | Single |
| Building Foundations | John Morgan | Single |
| Protecting and Growing | Emily & James Carter | Couple |
| Protecting and Growing | Alex Chen | Single / business owner |
| Planning Your Future | David & Sarah Mitchell | Couple |
| Enjoying Your Wealth | Patricia & Harold Bennett | Couple |
| Enjoying Your Wealth | Margaret Thompson | Widowed / solo |

> Margaret Thompson is particularly powerful for "Enjoying Your Wealth" — her
> IHT exposure and widowed status make the estate planning story concrete and
> emotionally resonant. Feature her prominently on that landing page.

---

## In-Nav Content Architecture

### Your stage ▾ → /stage/[slug]
Five life stage landing pages. Each page structure:
1. Hero — lead with the visitor's anxiety/ambition, not features
2. "This is your stage if..." — self-identification
3. Platform explainer in context of this stage
4. Features surfaced as answers to this stage's specific anxieties
5. Relevant demo persona CTA — "Try [Name]'s situation"
6. Relevant calculator embed
7. Related Learn articles

**URL pattern:** `/stage/starting-out`, `/stage/building-foundations`, etc.

### How it works → /how-it-works
Single page. Platform overview — the connected financial picture.
Sections: The full picture · Cashflow & projections · IHT & estate · Protection & risk · Works with your adviser.

**Do NOT build a feature inventory page.** Features should be discovered
inside the product, not sold on the website.

### Learn ▾ → /learn/
Evergreen educational reference content. Four content types:

**Type 1 — Concept explainers** (`/learn/what-is-[x]`)
"What is X?" queries — highest search volume. Examples:
- What is salary sacrifice?
- What is an LPA?
- What is drawdown?
- What is a SIPP?
- What is IHT?

**Type 2 — Decision guides** (`/learn/should-i-[x]`)
"Should I...?" — mid-funnel, high intent. Examples:
- Should I overpay my mortgage?
- Should I consolidate my pensions?
- When should I make a will?
- Should I use a LISA or ISA?

**Type 3 — Life stage guides** (`/learn/guide/[stage-slug]`)
Comprehensive long-form, one per stage:
- Starting Out: money basics
- Building Foundations: first home guide
- Protecting and Growing: family finances guide
- Planning Your Future: retirement roadmap
- Enjoying Your Wealth: estate planning guide

**Type 4 — Tax & allowances** (`/learn/tax/[x]`)
Updated annually — drives repeat traffic. Examples:
- ISA allowance 2025/26
- Pension annual allowance
- IHT thresholds & RNRB
- Capital gains tax rates
- Tax year checklist

**Also in Learn:**
- Glossary A–Z (`/learn/glossary`) — plain English, linked from articles AND from product UI
- Video walkthroughs

### Insights ▾ → /insights/
Time-sensitive content. NEVER call this "blog" — it undersells the content.
Five content types:
- Budget & tax year updates ("What the 2025 Autumn Budget means for your pension")
- "What X means for your finances"
- Product updates & new features
- Industry commentary
- Founder / team perspective

> **The key distinction:**
> Learn = reference. `/learn/salary-sacrifice` lives there forever, gets updated
> when rules change, URL never moves.
> Insights = moment. Timely content, high value now, archived later.
> Never mix these two types — readers and search engines need to know what they're getting.

### Calculators → /calculators/
Standalone tools hub. Also embedded in-context on life stage pages.
Each calculator ends with: "Want to see this in the context of your full financial picture? Try Fynla."

| Calculator | Stage context | SEO target |
|---|---|---|
| Mortgage repayment | Building Foundations | ~40k/mo UK searches |
| Pension pot projector | Planning Your Future | High intent |
| Salary sacrifice saving | Planning Your Future | High intent |
| IHT exposure checker | Enjoying Your Wealth | High intent |
| Drawdown runway | Enjoying Your Wealth | High intent |
| Emergency fund target | Starting Out / Building | Top of funnel |

### Why Fynla ▾ → /why-fynla/
Trust, differentiation, objection handling:
- Our approach
- One platform story ("You don't outgrow Fynla")
- Not tied to an adviser
- Security & privacy
- Fynla vs alternatives → (links to /compare/ pages)

---

## Off-Nav Content (search & footer only)

### Comparison pages → /compare/
**Never in main nav** — looks defensive. Linked from "Why Fynla" only.
Found via search for "[competitor] alternative" queries.

Pages to build:
- `/compare/fynla-vs-projectionlab`
- `/compare/fynla-vs-voyant`
- `/compare/fynla-vs-moneyhub`
- `/compare/fynla-vs-spreadsheets`
- `/compare/best-financial-planning-tools-uk`

> Write comparison pages fairly — acknowledge where competitors are strong.
> Honest comparisons convert better than one-sided ones and build trust.
> Example framing: "ProjectionLab is excellent for advanced modelling if you're
> comfortable with complexity — Fynla is built for people who want the same depth
> without the learning curve."

### Footer pages → /about/, /careers/, etc.
- About Fynla
- Careers
- Press & media
- Terms & privacy
- Contact & support

---

## Homepage Section Flow

Seven sections in order. Each does a specific job:

| # | Section | Job | Type |
|---|---|---|---|
| 1 | Hero | Earn the scroll — stakes first, product second | Emotion |
| 2 | Life stage selector | Let them place themselves — 5 cards, anxiety-led | Emotion |
| 3 | Platform explainer | Show the connected picture briefly | Logic |
| 4 | One platform message | Continuity, not pricing — "You don't outgrow Fynla" | Trust |
| 5 | Demo personas | Product as social proof — name the personas | Proof |
| 6 | Works alongside your adviser | Defuse "is this replacing my IFA?" | Trust |
| 7 | Final CTA | Inevitable, not pressured | Logic |

### Hero copy direction
- DO: "Your finances, at every stage of life — in one place you actually understand."
- DON'T: "The most powerful financial planning platform, with cashflow modelling, IHT tools and more."

### CTA language
- Primary: "See your situation" or "Start with your situation →"
- Secondary: "Try the demos"
- AVOID: "Sign up free — no credit card required" (sounds like overcoming resistance)

---

## Stage Landing Page Structure

Each of the five `/stage/[slug]` pages follows this structure:

1. **Hero** — visitor's anxiety as headline, not product feature
2. **"Is this your stage?"** — 3-4 bullet descriptions of who this is for
3. **What Fynla shows you at this stage** — contextual platform preview
4. **Relevant features as answers** — features surfaced in context, not as a list
5. **Demo persona CTA** — "Try [Name]'s situation" — named, not generic
6. **Embedded calculator** — most relevant to this stage
7. **Related Learn articles** — 3 links to relevant /learn/ content
8. **Stage navigation** — subtle "previous / next stage" links

---

## Content & SEO Strategy Notes

- **Calculators = top-of-funnel SEO.** "Mortgage repayment calculator UK" = ~40k searches/mo.
  Each calculator pulls in visitors who don't know Fynla yet. Bridge them with
  contextual CTAs, not sign-up pressure.

- **Learn = trust & retention.** Turns casual visitors into informed users.
  Keeps existing users engaged between sessions. Also linkable from product UI.

- **Compare pages = conversion.** Highest-converting content type but lowest traffic.
  Captures people already in buying mode. Let search do the work — keep off-nav.

- **Glossary = product integration opportunity.** Link glossary terms from inside
  the product UI itself ("What is this? →") — drives return visits and builds trust.

- **Insights = authority building.** Timely budget/tax commentary positions Fynla
  as an expert voice, not just a tool. Aim for at least one piece per major
  tax/financial event (Autumn Budget, Spring Statement, new tax year).

---

*Last updated: March 2026. Based on navigation planning session.*
