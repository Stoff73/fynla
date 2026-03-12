# Deploy Notes — 12 March 2026

**Branch:** `feature/mobile-app-phase0` (merged from `mobileImprovement`)
**Type:** iOS Capacitor App Only — No server upload needed

## Summary

Full day of mobile app fixes: blank screen resolution, biometric login, module detail screens, pension field fixes, estate restructure, and splash screen update.

## Commits (26 total on branch)

### Critical Fixes
- `e81066d` — Fix iOS WKWebView blank screen (MIME type error from rollup `external` config)
- `426875d` — Fix blank screens, broken nav, data mismatches on iOS Capacitor app
- `eeb8f41` — Fix biometric login: check credentials before Face ID prompt

### Features
- `ef8fb79` — Wire up Face ID biometric login
- `7e594a7` — Persist biometric login across logout
- `5c002bf`–`1640929` — Add all 7 module detail pages with card components
- `37a759e` — Add shared MobileAccordionSection and MobileDataRow components
- `0df52bc` — Add allocation donut and projection line charts
- `acf5043` — Add card components (policy, account, pension, holding, estate, trust, gift)
- `9caa774` — Make account cards expandable with tap-to-reveal details

### Data Fixes
- `713a3ae` — Populate module detail screens with correct data (fetch + analyse dispatches)
- `20871f7` — Correct pension field names, rework estate detail screen (remove broken assets, add protection)

### iOS Native
- `a78cd8a` — Replace Capacitor default splash with Fynla icon

## Deployment Steps

This is a Capacitor iOS app — deployment is via Xcode build, not server upload.

### 1. Build the iOS app
```bash
cd /Users/CSJ/Desktop/fynla
./deploy/mobile/build-ios.sh
```

### 2. Open in Xcode
```bash
npx cap open ios
```

### 3. Build and run
- Clean Build: `Cmd+Shift+K`
- Run: `Cmd+R`

### 4. Clear mobile dashboard cache (if testing with existing user)
```bash
php artisan cache:clear
```

## No Server Changes Required

All changes are in:
- `resources/js/` (Vue components, stores, router, services)
- `ios/` (splash screen assets)
- `vite.config.js` (build config)

No PHP backend files were modified. No migrations. No new dependencies.

## Testing Checklist

- [ ] App launches without blank screen
- [ ] Splash screen shows Fynla icon (not Capacitor X)
- [ ] Login with email + password + verification code works
- [ ] Face ID setup prompt appears after first login
- [ ] Face ID login works after logout
- [ ] Dashboard shows net worth, modules, insight
- [ ] Each module card taps through to detail page
- [ ] Savings: accounts with balances, ISA allowance, emergency fund
- [ ] Retirement: pensions with correct values, projections, annual allowance
- [ ] Investment: accounts, holdings, allocation chart, fees
- [ ] Estate: IHT analysis, gifts, trusts, protection policies
- [ ] Protection: policies, coverage analysis, gaps
- [ ] Account cards expand on tap to show details
- [ ] Fyn chat sends and receives streamed messages
- [ ] Settings → Face ID toggle works
- [ ] More menu navigation works
