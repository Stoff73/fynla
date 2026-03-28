# Calculator Page Redesign — Grouped Cards + Gated Planning Tools

## Summary

Redesign the `/calculators` page from tab-button selector to a grouped card list. Add 7 gated "planning tool" cards that require sign-in. Free calculators remain fully open. All items grouped by life stage.

## Hero Section

- Gradient: `bg-gradient-to-r from-horizon-500 to-raspberry-500` (matches home page)
- Headline: "Financial Calculators & Planning Tools"
- Subtitle: "Free tools to help you understand your finances. Planning tools require a free Fynla account."
- No buttons in hero — the cards below are the action

## Card List Layout

Below the hero on `bg-eggshell-500`. Cards grouped under stage headers.

### Stage Header

Coloured dot + stage name, matching the stage colours from `SITE_ARCHITECTURE.md`:

```
● Starting Out          (#1D9E75)
● Building Foundations   (#5DCAA5)
● Protecting and Growing (#378ADD)
● Planning Your Future   (#7F77DD)
● Enjoying Your Wealth   (#EF9F27)
```

### Free Calculator Card

- White background, rounded-lg, border border-light-gray
- Left: emoji/icon (existing)
- Centre: calculator name (font-semibold) + one-line description (text-neutral-500)
- Right: subtle arrow or "Open" indicator
- Clickable — sets `activeCalculator` and scrolls to the calculator panel below
- Hover: slight shadow, border-raspberry-400

### Gated Planning Tool Card

- Same card dimensions and layout as free cards
- Left: emoji/icon
- Centre: tool name + one-line description
- Right: lock icon + "Sign in to access" in muted text
- Small badge: "Free account" in `light-blue-500` background
- Entire card is a `router-link to="/register"`
- Hover: same subtle shadow treatment
- Visually distinct but not drastically different — same family, slightly muted

### Paid Plan Card (2 items)

- Same card layout
- Right: lock icon + "Paid plan" badge in `violet-500` background
- CTA goes to `/pricing`
- Slightly more muted/greyed treatment than free-account cards

## Grouping

| Stage | Free Calculators | Gated (Free Account) | Gated (Paid) |
|-------|-----------------|---------------------|--------------|
| Starting Out | Student Loan, Savings Goal, Emergency Fund | — | — |
| Building Foundations | Mortgage Repayment, Mortgage Affordability, Stamp Duty, Personal Loan, Compound Interest | — | — |
| Protecting and Growing | — | Life Insurance Needs, Income Protection | — |
| Planning Your Future | Income Tax, Pension Growth, Pension Tax Relief | Salary Sacrifice | Retirement Budget Planner |
| Enjoying Your Wealth | Pension Withdrawal Tax | Inheritance Tax Exposure Checker, Pension Drawdown / Runway | Annuity vs Drawdown Comparison |

## Gated Card Content

### Free Account Cards (5)

| Tool | Description | Icon |
|------|-------------|------|
| Life Insurance Needs | How much cover does my family actually need? | Shield |
| Income Protection | What would I need if I couldn't work? | Umbrella |
| Salary Sacrifice | Take-home pay vs pension boost — what's the real trade-off? | Scales |
| Inheritance Tax Exposure Checker | Estimated inheritance tax liability with full breakdown | Building |
| Pension Drawdown / Runway | How long will my pension pot last? | Hourglass |

### Paid Plan Cards (2)

| Tool | Description | Icon |
|------|-------------|------|
| Retirement Budget Planner | Detailed income vs spending plan for retirement | Chart |
| Annuity vs Drawdown Comparison | Which gives you more over your lifetime? | Comparison |

## Calculator Display

When a free calculator card is clicked:
1. Card gets active state (raspberry border)
2. Calculator panel appears below the card list in a `max-w-7xl` container
3. Same inputs + results layout as current implementation
4. Smooth scroll to the calculator panel

## Active Calculator Panel

Keep existing calculator panels unchanged. They render below the card list in the same `max-w-7xl` wrapper. The card list acts as the selector; the panel below is the workspace.

## Reusable Component

Create `CalculatorCard.vue` used in three contexts:
- `/calculators` page (primary)
- Feature pages (e.g. `/features/iht-planning` can include the IHT Checker card)
- Stage pages (e.g. `/stage/protecting-and-growing` can include Life Insurance card)

Props:
- `name` — display name
- `description` — one-line description
- `icon` — emoji or icon identifier
- `type` — `'free'` | `'gated-free'` | `'gated-paid'`
- `calculatorId` — slug for free calculators (sets activeCalculator)
- `to` — link destination for gated cards (`/register` or `/pricing`)

## Files Changed

1. **New:** `resources/js/components/Public/CalculatorCard.vue` — reusable card component
2. **Modified:** `resources/js/views/Public/CalculatorsPage.vue` — replace tab buttons with grouped card list, keep all calculator panels
3. **Optional later:** stage pages and feature pages can import CalculatorCard

## What This Does NOT Include

- No new calculator functionality — the 7 gated tools are preview cards only, linking to `/register` or `/pricing`
- No sessionStorage input saving — cards link directly, no calculation happens
- No blur/overlay — cards are simple teasers, not partially functional tools
- No changes to existing calculator logic or results panels
