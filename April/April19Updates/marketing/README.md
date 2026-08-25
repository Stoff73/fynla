# Fynla Marketing & Strategy Reports — 19 April 2026

Session output from a Product Management skill run: market segmentation → beachhead selection → go-to-market plan → product strategy canvas.

All four documents are derived from direct codebase analysis (preview personas, module service depth, feature inventory) plus standard PM frameworks.

## Reading order

1. **[01-market-segments.md](01-market-segments.md)** — Two niche target markets Fynla already serves based on product depth
2. **[02-beachhead-analysis.md](02-beachhead-analysis.md)** — Scoring the two niches to pick the launch beachhead
3. **[03-gtm-plan.md](03-gtm-plan.md)** — Full go-to-market plan for the chosen beachhead (UK Ltd Co Directors)
4. **[04-product-strategy.md](04-product-strategy.md)** — 9-section Product Strategy Canvas

## Headline conclusions

- **Beachhead segment**: UK single-director Limited Companies, £120k–£300k revenue, age 35–55 (IT contractors, consultants, agency founders). ~150k SAM.
- **Acquisition wedge**: Oct 2024 Budget capped Business Property Relief at £1m — founders with businesses >£1m face silent £400k+ IHT exposure. Hot window, closes as the market adapts.
- **Expansion target**: IHT-anxious mass-affluent couples (£800k–£2m, age 50–70). ~300k SAM. Year 2.
- **North Star metric**: Paid Active Households.
- **Core strategic trade-off**: Modelling money (tool) over managing money (robo-advisor). Keeps us out of FCA Part IV authorisation.
- **Moat today**: UK-tax-engine breadth + household-first architecture + speed of tax-rule updates. Not network effects, not patents.
- **Biggest risk**: FCA reclassifies Fyn AI outputs as regulated advice.

## Next skills worth running

- `pm-product-strategy:business-model` — Lean Canvas stress-test of the economics
- `pm-product-strategy:market-scan` — SWOT + PESTLE + Porter's Five Forces for external assumptions
- `pm-go-to-market:growth-strategy` — Design the specific growth loops
- `pm-go-to-market:competitive-battlecard` — Sales/positioning assets for the accountant-partner channel

## Source evidence (for future audits)

- Preview personas: `database/seeders/PreviewUserSeeder.php`
- Module service depth: `app/Services/{Estate,Investment,Retirement,Savings,Protection,Goals,Coordination}/`
- Distinctive features verified: NHS pension handling, BPR eligibility, joint_owner_id architecture, TaxConfigService, LISA/Plan2/Plan5, non-UK-dom tracking, key-person insurance linked to business valuation
- Landing page + persona selector: `resources/js/views/LandingPage.vue` (approx.)
- Budget context: HMRC IHT receipts £7.5bn (2023/24), Oct 2024 Budget BPR £1m cap, frozen NRB through 2029/30

---

*Generated via the `pm-market-research`, `pm-go-to-market`, and `pm-product-strategy` skills. Challenge the premises before acting on them.*
