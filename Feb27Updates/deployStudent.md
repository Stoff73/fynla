# Deployment Guide: Student Preview Persona (Janice Taylor)

## Rebuild Required: YES (frontend)

New JSON persona file and Vue store/component changes require a frontend rebuild.

```bash
./deploy/fynla-org/build.sh
```

## Summary

Added a 7th preview persona — Janice Taylor, a 21-year-old university Economics student. Showcases Fynla for younger users with student loans, minimal savings, and early-stage financial planning.

### Persona Details
- **Name:** Janice Taylor, 21, single, female
- **Situation:** Second-year Economics student, part-time work
- **Income:** £9,000/year (maintenance loan + part-time job)
- **Savings:** Cash ISA (£1,200, Monzo)
- **Investments:** Lifetime ISA (£400, Moneybox)
- **Liabilities:** Student Loan Plan 5 (£35,000, SLC)
- **Goals:** Start LISA for first home, build emergency fund, graduate with a financial plan
- **Life Events:** Graduation (2027), first graduate job (2027), start pension contributions (2027)
- **Net Worth:** ~-£33k

## New Files to Upload

```
resources/js/data/personas/student.json
```

## Modified Files to Upload

```
app/Http/Controllers/Api/PreviewController.php
database/seeders/PreviewUserSeeder.php
resources/js/components/Preview/PersonaSelector.vue
resources/js/store/modules/preview.js
```

## Post-Upload: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
php artisan db:seed --class=PreviewUserSeeder --force
```

## Upload Paths (SiteGround)

| Local File | Remote Path |
|-----------|-------------|
| `public/build/` | `~/www/fynla.org/public_html/public/build/` |
| `app/Http/Controllers/Api/PreviewController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/PreviewController.php` |
| `database/seeders/PreviewUserSeeder.php` | `~/www/fynla.org/public_html/database/seeders/PreviewUserSeeder.php` |
| `resources/js/data/personas/student.json` | `~/www/fynla.org/public_html/resources/js/data/personas/student.json` |
