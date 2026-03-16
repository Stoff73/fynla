# Fynla v0.9.2 Patch Notes

**Released:** 16 March 2026
**Previous version:** v0.9.0

---

## Headline

Two major estate planning features in one release. Fynla now helps you **build a legally-structured UK will** with a guided 10-step wizard, and plan for incapacity with full **Lasting Power of Attorney** support — all directly within your estate plan.

---

## What's New

### Lasting Power of Attorney

A new **Power of Attorney** tab in Estate Planning gives you a dedicated space to manage one of the most important — and often overlooked — parts of your estate plan.

**Why it matters:**
Without a Lasting Power of Attorney, if you lose mental capacity, your family must apply to the Court of Protection to manage your affairs. This costs over £1,000 per year, takes months to arrange, and a court may appoint someone you wouldn't have chosen. Fynla now makes it straightforward to get this in order.

**Two types supported:**

- **Property & Financial Affairs** — covers your bank accounts, investments, property, bills, and tax affairs. You choose whether your attorneys can act while you still have capacity or only when you've lost it.

- **Health & Welfare** — covers medical treatment, care decisions, daily routine, and life-sustaining treatment. Can only be used when you lack capacity.

---

### Guided Creation Wizard

A step-by-step wizard walks you through creating each Lasting Power of Attorney:

1. **Donor details** — pre-filled from your Fynla profile
2. **Appoint attorneys** — add one or more people to act on your behalf
3. **Replacement attorneys** — optional backups if primary attorneys can no longer serve
4. **Decision type** — choose how multiple attorneys make decisions (jointly, jointly and severally, or a combination)
5. **When attorneys can act** — Property & Financial only: while you have capacity or only when lost
6. **Preferences and instructions** — advisory wishes and binding instructions, plus life-sustaining treatment decisions for Health & Welfare
7. **Certificate provider** — the person who confirms you understand and aren't under pressure
8. **People to notify** — up to 5 people who can raise objections during registration
9. **Review** — full summary with compliance checks before completing

You can save as a draft at any point and return to finish later.

---

### Compliance Checking

Every Lasting Power of Attorney is automatically checked against UK legal requirements under the Mental Capacity Act 2005:

- Donor must be 18 or older
- At least one primary attorney must be appointed
- Decision type required when appointing multiple attorneys
- Certificate provider must have known you for at least 2 years
- Maximum 5 people to notify
- When attorneys can act must be specified (Property & Financial)
- Life-sustaining treatment decision required (Health & Welfare)
- Registration status with the Office of the Public Guardian

Each check shows a clear pass, fail, or warning with an explanation of what needs attention.

---

### Print and Save

View your completed Lasting Power of Attorney in a format that mirrors the official Office of the Public Guardian structure. Print directly from the browser or save as a PDF for your records.

**Important:** The printed document is a record of your details — to make your Lasting Power of Attorney legally valid, you must complete the official forms, sign with wet ink, and register with the Office of the Public Guardian (currently £82 per registration).

---

### Upload Existing

Already have a Lasting Power of Attorney? Upload your existing document (PDF or image) and Fynla will track it alongside any new ones you create. Mark it as registered and add your Office of the Public Guardian reference number to keep everything in one place.

---

### Registration Tracking

Once you've registered your Lasting Power of Attorney with the Office of the Public Guardian, mark it as registered in Fynla with your reference number. Your estate planning readiness checks will update automatically to reflect the registration.

---

### Will Builder

A new **Will Builder** in Estate Planning lets you create a legally-structured will for England and Wales without leaving Fynla. A 10-step guided wizard walks you through everything needed for a valid UK will.

**Why it matters:**
Around 60% of UK adults don't have a will. Without one, your estate is distributed under intestacy rules — which may not reflect your wishes, can delay access to funds for your family, and may result in a larger Inheritance Tax bill. Fynla now makes it straightforward to get a will in place.

**Guided wizard steps:**

1. **Introduction** — legal disclaimer, domicile confirmation (England and Wales only), will type selection
2. **Personal details** — pre-filled from your Fynla profile (name, address, date of birth, occupation)
3. **Executors** — appoint a primary and backup executor with contact details and guidance on the role
4. **Guardians** — only shown if you have children under 18; appoint who should care for them
5. **Specific gifts** — leave cash amounts or named items to particular people (optional)
6. **Residuary estate** — distribute the remainder of your estate by percentage, with substitutional beneficiaries if someone predeceases you
7. **Funeral wishes** — burial, cremation, or no preference, plus free-text wishes (optional)
8. **Digital assets** — appoint a digital executor and record how to access your online accounts (optional)
9. **Review** — full will document preview in formal legal language, validation warnings, and print/save as PDF
10. **Signing guide** — step-by-step instructions for making your will legally valid with two witnesses

**Mirror wills for couples:**
If you have a spouse, you can create a **mirror will** — two matching wills where each of you leaves everything to the other first, then to your chosen beneficiaries. One wizard run creates both wills with the beneficiaries automatically swapped.

**Pre-population:**
The wizard automatically fills in your name, address, date of birth, occupation, spouse details, children, and existing executor from your Fynla profile. You only need to add new information.

**Document format:**
Your generated will uses formal legal language ("I HEREBY REVOKE", "I GIVE DEVISE AND BEQUEATH", "IN WITNESS WHEREOF") with a hybrid design — Fynla branding in the header with traditional legal formatting in the body (serif font, numbered clauses, uppercase headings). Print directly from the browser or save as PDF.

**Important:** The will builder generates a structured will document — it does not provide legal advice. Your will is only legally valid once properly signed and witnessed in accordance with the Wills Act 1837. For complex estates, we recommend having your will reviewed by a qualified solicitor.

---

## Improvements

### Sidebar Navigation

A new **Power of Attorney** item has been added to the sidebar under the Family section, giving you one-click access to your Lasting Powers of Attorney from anywhere in the application. The sidebar highlights correctly whether you're viewing the Power of Attorney tab or creating a new Lasting Power of Attorney in the wizard.

### Estate Planning Readiness

The estate planning data readiness assessment now checks for Lasting Powers of Attorney as a recommended item. If you haven't created one, you'll see a clear prompt explaining why it matters and linking to the new Power of Attorney tab.

This check has been upgraded from informational to a warning — recognising that lacking a Lasting Power of Attorney is a significant gap in any estate plan.

### Version Page

The version information page now reflects v0.9.2 with the Lasting Power of Attorney feature details and release statistics.

---

## Preview Personas

Three preview personas now include Lasting Power of Attorney data for demonstration:

| Persona | Lasting Power of Attorney Details |
|---------|-----------------------------------|
| David & Sarah Mitchell (Peak Earners) | Both types registered with the Office of the Public Guardian, multiple attorneys acting jointly and severally |
| Margaret Thompson (Widow) | Property & Financial registered, Health & Welfare in draft |
| Patricia & Harold Bennett (Retired Couple) | Both types registered for both spouses, with replacement attorneys |

---

## Technical Summary

| Metric | Lasting Power of Attorney | Will Builder | Total |
|--------|--------------------------|-------------|-------|
| New database tables | 3 | 1 (+1 altered) | 4 |
| New API endpoints | 9 | 9 | 18 |
| New backend files | 14 | 6 | 20 |
| New frontend components | 16 | 13 | 29 |
| Modified files | 12 | 6 | 18 |
| New tests | 38 (93 assertions) | 36 (91 assertions) | 74 (184 assertions) |

---

## Fyn Assistant Optimisation

A comprehensive overhaul of the Fynla AI assistant (Fyn) based on a full audit against Anthropic's 2026 best practices. 21 improvements implemented across streaming, prompt quality, safety, and cost efficiency.

### Real-Time Streaming

Fyn's responses now stream word-by-word in real time. Previously, the entire response was generated server-side before anything appeared — meaning you'd wait 10–30 seconds seeing nothing. Now the first words appear within a second, and you can watch the response form naturally.

A new **Stop generating** button lets you cancel a response mid-stream if you've already seen what you need.

### Smarter Prompt Architecture

The system prompt that guides Fyn has been completely restructured:

- **XML-tagged sections** for clearer instruction following — identity, rules, compliance, context, examples, and personality are now cleanly separated
- **5 few-shot examples** demonstrating the ideal tone, format, and depth for protection, savings, tax, goal creation, and financial overview conversations
- **Richer financial context** — Fyn now sees your total savings, investments, pension value, protection cover, property ownership, estimated tax band, and retirement income gap in every conversation
- **Warm personality guidelines** — Fyn celebrates your progress before discussing gaps, uses plain language, and treats financial decisions with empathy
- **Response format rules** — concise, bold key figures, always ends with a follow-up question, no filler preambles

### Regulatory Compliance

A dedicated 6-rule compliance framework ensures Fyn:

1. Uses hedging language for all guidance ("you may want to consider", not "you should")
2. Never recommends specific financial products or providers
3. Signposts regulated advice for complex decisions (pension transfers, estate structures)
4. Includes risk warnings when discussing investments
5. Caveats all tax guidance with current tax year and individual circumstances
6. Never gives market timing advice

### Safety & Data Integrity

- **Input validation** on every record Fyn creates — currency amounts, dates, percentages, and enums are all validated before saving to the database
- **Duplicate detection** for savings accounts, investment accounts, pensions, and protection policies — Fyn warns you if a similar record already exists
- **Out-of-scope handling** — asking Fyn about the weather or football redirects politely to financial planning
- **Structured error messages** — specific, actionable messages for rate limits, busy service, long conversations, and configuration issues (replacing generic "something went wrong")
- **Daily token budgets** per subscription plan (Student: 50k, Standard: 200k, Pro: 500k tokens/day)

### Tool Definitions

All 17 AI tools now use **strict schema validation** — guaranteeing that every tool call from the model has the correct types and required fields. Date fields use ISO format validation. Three previously underspecified tools (what-if scenarios, recommendations, financial plan) now have proper schemas.

### Cost Efficiency

- **Prompt caching** reduces input token costs by up to 70% across multi-turn conversations — the system prompt and tool definitions are cached for 5 minutes between messages
- **Financial summary caching** avoids re-running the full 7-module analysis on every message (2-minute cache per user)
- **Model tiering** for Pro users — complex queries (financial plans, what-if scenarios, estate planning, pension projections) automatically use a more capable model, while simple queries stay on the fast, cost-effective default

### Max Token Increase

Response length limits increased from 2,048/4,096 to **4,096/8,192 tokens** (Standard/Pro), allowing Fyn to give properly detailed analysis without truncating mid-thought.

---

## Technical Summary (Updated)

| Metric | Lasting Power of Attorney | Will Builder | Fyn Assistant | Total |
|--------|--------------------------|-------------|---------------|-------|
| New database tables | 3 | 1 (+1 altered) | 0 | 4 |
| New API endpoints | 9 | 9 | 0 | 18 |
| New backend files | 14 | 6 | 0 | 20 |
| New frontend components | 16 | 13 | 0 | 29 |
| Modified files | 12 | 6 | 7 | 25 |
| New tests | 38 (93 assertions) | 36 (91 assertions) | 0 | 74 (184 assertions) |

---

## Estate Planning Navigation Fix

### Power of Attorney — Standalone Page

The Power of Attorney feature has been moved from a tab on the Estate Planning dashboard to its own **standalone page** at `/estate/power-of-attorney`. This matches the pattern used for other estate sub-features like the Will Builder and Trust management.

**What changed:**
- **Sidebar "Power of Attorney"** now links directly to `/estate/power-of-attorney` instead of `/estate?tab=power-of-attorney`
- The Power of Attorney tab has been removed from the Estate Dashboard's internal tab system
- A new **Power of Attorney card** has been added to the IHT Planning navigation grid alongside Will, Gifting, Life Policy, and Charitable Bequest — clicking it navigates to the standalone page
- The card shows LPA status (registered count, draft count, or "None")

### Conditional Will Builder Banner

The "Build Your Will" banner on the Estate Dashboard now **only shows when the user has no will document**. Previously it showed unconditionally for all users — including those who had already completed the Will Builder. The dashboard now checks for an existing will document on load and hides the banner accordingly.

### IHT Planning Navigation Cards

A new **Power of Attorney card** has been added to the IHT Planning page's top navigation grid. The card displays:
- Registration status (number of registered LPAs, drafts, or "None")
- Guidance text ("Appoint someone to act on your behalf" when no LPAs exist)
- Click navigates to `/estate/power-of-attorney`

### Updated Preview Persona Data

LPA seeding has been refined:

| Persona | LPA Data |
|---------|----------|
| David & Sarah Mitchell (Peak Earners) | 4 registered LPAs — David has Property & Financial (LP-2024-0847291) and Health & Welfare (LP-2024-0847292), Sarah has Property & Financial (LP-2024-0953104) and Health & Welfare (LP-2024-0953105). Each with 2 attorneys (spouse + sibling) acting jointly and severally, 1 notification person |
| Margaret Thompson (Widow) | Property & Financial registered (LP-2023-0612845) with son as primary attorney and daughter as replacement. Health & Welfare in draft with same attorney arrangement |

### Files Changed

| File | Change |
|------|--------|
| `resources/js/views/Estate/EstateDashboard.vue` | Removed LPA tab, conditional will banner |
| `resources/js/views/Estate/PowerOfAttorneyView.vue` | **NEW** — standalone LPA page with back link |
| `resources/js/router/index.js` | Added `/estate/power-of-attorney` + preview route |
| `resources/js/components/SideMenu.vue` | Updated LPA link to standalone path, simplified active state |
| `resources/js/components/Estate/IHTPlanning.vue` | Added Power of Attorney card, LPA state/computed, fetch on mount |
| `database/seeders/PreviewUserSeeder.php` | Added `createLpas()` for Mitchell + Thompson, LPA cleanup in `deleteUserData()` |

---

## Coming Soon

- Fyn Assistant conversation analytics dashboard

---

*Fynla is a financial planning tool, not a legal service. We recommend seeking professional legal advice when creating your will or Lasting Power of Attorney. A will must be properly signed and witnessed to be legally valid. Registration with the Office of the Public Guardian is required to make a Lasting Power of Attorney legally valid.*
