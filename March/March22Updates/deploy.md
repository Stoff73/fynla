# Deploy Notes — 22 March 2026

## Branch: `dashboard`

**20 commits** — Major dashboard UI redesign + SubNavBar system + module page standardisation

## Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`

## Changed Files (Frontend Only — No PHP Changes)

### New Files
- `resources/js/components/SubNavBar.vue` — Route-based sub-navigation with tabs + CTAs
- `resources/js/constants/subNavConfig.js` — SubNav route configuration
- `resources/js/store/modules/subNav.js` — Vuex store for CTA event communication

### Modified Files (47 total)
- `tailwind.config.js` — Added light-pink-200/300 shades
- `resources/css/app.css` — Card hover standardisation
- `resources/js/layouts/AppLayout.vue` — SubNavBar integration
- `resources/js/store/index.js` — SubNav store registration
- `resources/js/views/Dashboard.vue` — Major UI redesign (hero, cards, empty states, knowledge nudge)
- `resources/js/views/Login.vue` — Logo height adjustment
- `resources/js/views/Register.vue` — Logo height adjustment
- `resources/js/views/NetWorth/CashOverview.vue` — Light-pink buttons, SubNav wiring
- `resources/js/views/Trusts/TrustsDashboard.vue` — Remove repeat header, SubNav wiring
- `resources/js/views/ValuableInfo.vue` — Remove in-page tabs (now in SubNavBar)
- `resources/js/views/Estate/EstateDashboard.vue` — Hover standardisation
- `resources/js/views/Plans/PlansDashboard.vue` — Hover standardisation
- `resources/js/views/Protection/ProtectionDashboard.vue` — Remove duplicate title
- `resources/js/views/Goals/GoalsDashboard.vue` — Remove duplicate title
- 20+ component files (cards, menus, navigation, modals)

### New Image
- `public/images/Fyn/Fyn-Icon.png` — Upload to `~/www/fynla.org/public_html/public/images/Fyn/`

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No Database Changes

No migrations or seeders required.

## Summary of Changes

1. **Dashboard redesign** — New header with Fyn icon, JourneyProgressHero with progress ring, Suggested for You panel, redesigned module cards
2. **SubNavBar system** — Centralised route-based sub-navigation replacing duplicate page headers across all modules
3. **Card hover standardisation** — All cards use consistent hover (light grey bg or pink border for dashboard)
4. **Light-pink buttons** — Replaced violet action buttons with solid light-pink (#FAD6E0) + dark grey text
5. **Empty state improvements** — Light-pink backgrounds, raspberry CTAs, inline layouts
6. **Active sidebar** — Always horizon-500 blue + white text
7. **Responsive text** — Progress ring percentage and greeting text scale with breakpoints
