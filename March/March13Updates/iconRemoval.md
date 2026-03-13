# Emoji Icon Removal — 13 March 2026

## What Changed

Removed all emoji icons from cards across the mobile app for a cleaner, more professional look.

## Components Updated

### Shared Components (render the icons)
- **ModuleSummaryCard** — removed `MODULE_ICONS` map and emoji `<span>`
- **MobileHeroCard** — removed icon prop rendering (prop kept as optional for backwards compat)
- **MobileAccordionSection** — removed `v-if="icon"` emoji span
- **MobileEmptyState** — removed emoji span (prop kept as optional)
- **MobilePolicyCard** — removed `policyIcon` computed and emoji span
- **MobileEstateAssetCard** — removed `assetIcon` computed and emoji span

### Detail Views (pass the icon props)
- ProtectionDetail, SavingsDetail, InvestmentDetail, RetirementDetail, EstateDetail, GoalsDetail, CoordinationDetail — removed all `icon="..."` props from MobileHeroCard, MobileAccordionSection, and MobileEmptyState usage

### More Menu
- Removed emoji icons from module grid buttons and `icon` field from modules data array
