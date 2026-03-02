# Deploy 20 February 2026 — Second Update

**Status:** DEPLOYED

---

## Changes

### 1. Net Worth Card — Mobile Bar Chart

- Dashboard Net Worth card now shows a **bar chart on mobile** (< 768px) instead of the donut chart
- Each asset category (Pensions, Property, Investments, Cash & Savings, etc.) and liability category (Mortgages, Loans, Credit Cards, etc.) gets its own bar
- Bar colours match the category colours used in the donut chart (design system compliant)
- X-axis labels rotate -45 degrees when > 4 categories, with `trim: false` and `maxHeight: 80` to ensure full visibility
- Net worth total shown centred below the chart in green (positive) or red (negative)
- Desktop donut chart is completely unchanged
- Responsive switching uses a reactive `isMobile` data property with a `resize` event listener (cleaned up in `beforeUnmount`)

---

## Rebuild Required

Yes - Vue component change requires frontend rebuild.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload

### Frontend build

1. `public/build/` (entire directory after running build script)

No PHP files changed. No migrations. No seeders required.

---

## Post-Upload

```bash
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
