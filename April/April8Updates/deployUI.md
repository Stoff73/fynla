# Deploy Guide — UI Fixes (calculator-fixes branch)

**Date:** 8 April 2026
**Branch:** `calculator-fixes` → merged to main via PR #196
**Type:** Frontend only — build + upload `public/build/`
**Status:** DEPLOYED to fynla.org — 8 April 2026

---

## What Changed

### 1. Calculator Bugs Fixed (CalculatorsPage.vue + taxConfig.js)
- **Income Tax NI rate:** Corrected from 12% to 8% (was pre-April 2024 rate)
- **Income Tax PA taper:** Personal Allowance now reduces above £100K income
- **SDLT bands:** Updated to permanent bands (expired 1 Apr 2025 temporary bands removed)
- **SDLT first-time buyer:** Updated to permanent relief (£300K nil-rate, £500K max)
- **Scotland LBTT ADS:** Corrected from 6% to 8% (Dec 2024 change)
- **Wales LTT ADS:** Corrected from 4% to 5% (Dec 2024 change)
- **Mortgage Affordability:** 10% deposit row now shows property price (was showing borrowing amount)
- **Default values:** All calculators pre-populated with sensible defaults (no more £0 on Calculate)
- **No hardcoded tax values:** All rates, thresholds, and bands moved to `taxConfig.js` constants

### 2. Persona Selection Modal (PersonaSelectionModal.vue + persona JSONs)
- Goal/focus badge now stacks below income range badge on all persona cards
- Taylor net worth: removed negative sign (now "£33k - £34k")
- Morgan net worth: removed negative sign (now "£15k - £20k")

### 3. Contact Page (ContactPage.vue)
- Changed `press@fynla.org` to `marketing@fynla.org`
- Updated heading: "Marketing and media"
- Updated description: "Partnerships, press enquiries, and media resources"

---

## Files Changed

```
resources/js/constants/taxConfig.js                          (modified)
resources/js/views/Public/CalculatorsPage.vue                (modified)
resources/js/components/Preview/PersonaSelectionModal.vue     (modified)
resources/js/data/personas/student.json                      (modified)
resources/js/data/personas/young_saver.json                  (modified)
resources/js/views/Public/ContactPage.vue                    (modified)
```

---

## Deploy Steps

### 1. Merge branch to main
```bash
git checkout main
git merge calculator-fixes
git push origin main
```

### 2. Build frontend
```bash
./deploy/fynla-org/build.sh
```

### 3. Upload to production
Upload `public/build/` directory via SiteGround File Manager to:
```
~/www/fynla.org/public_html/public/build/
```

### 4. Clear caches (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## No Backend Changes
- No PHP files changed
- No migrations
- No composer changes
- No route changes
- No database seeding required
