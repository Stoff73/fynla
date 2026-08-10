# Projection parity acceptance evidence — 10 August 2026

## Scope

PR5 reconciles retirement projections and separates recorded Net Worth history from forward projections across native iOS and the `/m` mobile route. The acceptance loop used the same household fixture on both surfaces and covered assumption save, reload and reset.

## Automated verification

- Backend projection, forecast, history and E2E support suites: 61 tests passed, 281 assertions. PHP 8.5 reported existing reflection deprecations only.
- Full mobile-web unit suite: 82 files passed, 877 tests passed.
- Focused mobile-web retirement and Net Worth suites after the acceptance fixes: 3 files passed, 20 tests passed.
- Mobile production bundle: built successfully with Vite. The existing large-chunk advisory remains non-blocking.
- Full native iOS run on the visible iPhone 16 Pro simulator: 383 Swift unit tests passed; 53 UI and screenshot tests passed with 2 credential-gated tests skipped and no failures.
- Exact native PR5 journey on the visible iPhone 16 Pro simulator: 1 test passed with no failures. Result bundle: `/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-epbutbbmpsmzeedyffebqqpdwwtk/Logs/Test/Test-Fynla-Staging-2026.08.10_22-38-01-+0100.xcresult`.
- Focused native retirement and Net Worth suites after the acceptance fixes: 8 tests in 2 suites passed.
- Optimized native production build: arm64 and x86_64 simulator slices built successfully with Swift warnings treated as errors.
- Installed-Google-Chrome `/m` journey against the isolated local E2E database: 1 test passed in 29.7 seconds. It entered through `/m/app/retirement`, reconciled the retirement products and age bands, navigated through the real drawer to Net Worth, saved 6.25%, reloaded, confirmed persistence, reset to 3%, and found no runtime errors.

## User-journey acceptance

The native journey exercised the application as a user:

1. Open Retirement from the drawer.
2. Confirm the three canonical retirement income bands and the disclosed 4.7% withdrawal assumption.
3. Open Net Worth and distinguish recorded balance history from projected Net Worth.
4. Edit the property assumption to 6.25% and dismiss the numeric keyboard.
5. Save, navigate away, return, and confirm the saved value persisted.
6. Reset to the 3% Fynla default.

The Google Chrome journey followed the equivalent `/m` flow with the same household data.

## Defects found and fixed during the loop

- Corrected the E2E investment ISA and occupational pension fixture values to match canonical model enums.
- Rebuilt only the validated `*_e2e` database before migration so stale local schemas cannot contaminate acceptance runs.
- Preserved the rotated `/m` scaffold token across page reloads instead of restoring a revoked token from the initial harness state.
- Removed SwiftUI container identifiers that propagated onto descendants and hid the field, Save and Reset identifiers from assistive technology and UI tests.
- Added a visible Done control to the native forecast keyboard so users can reach Save on an iPhone-sized screen.
- Disambiguated retirement age-band assertions by their exact accessible labels.

## Production route note

The exact production entry route `https://csjones.co/fynla/m` was opened in the user's installed Google Chrome. The current Chrome session was signed out and redirected to login, so this is not recorded as authenticated production-feature evidence. PR5 was not deployed as part of this work; production claims are intentionally limited to the deployed route's authentication boundary.
