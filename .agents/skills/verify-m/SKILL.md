---
name: verify-m
description: Verify the /m mobile-web pathway (Rule 19 — "done" = web AND /m). Codifies the two reliable verification paths on csjones, because the desktop→/m token bridge does NOT fire on a cold Playwright navigation. Use whenever verifying any change that touches resources/mobile/, mobile API endpoints, or the /m dashboard; when a plan says "verify on /m"; or when CSJ asks "does this work on mobile/m?".
---

# Verify /m — the paths that actually work

**Trap first:** a cold Playwright `goto('https://csjones.co/fynla/m')` shows the public landing or "could not load dashboard" (greeting "Good …, there" = no user). The desktop→/m bridge (`mScaffoldBridge.js`, localStorage `m_scaffold_token`) only adopts the token in the real funnel/in-app flow — never on a fresh automated nav. Do not burn cycles on it.

`/m` serves the **built** bundle (`public/m-build/`, no HMR) — confirm the bundle on csjones actually contains the change before testing (a stale bundle has cost a full debugging session before). Never use the `ssh-fynla` MCP for csjones — that is PROD; csjones SSH is `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co` (check `ssh-add -l`; if locked, ask CSJ for the passphrase).

## Path 1 — UI verification (log in on /m directly)

1. Navigate to `https://csjones.co/fynla/m/app/login` (the canonical /m login).
2. Fill credentials. Known test user (2026-06): `savetaxb2test@example.com` / `<redacted-password>` (married, non-working spouse, £80k, ISA, mid-onboarding).
3. Fetch the 6-digit MFA code via SSH tinker:
   ```bash
   cd ~/www/csjones.co/fynla-app && php artisan tinker --execute="\$u=\App\Models\User::where('email','savetaxb2test@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id',\$u->id)->latest()->first()->code ?? 'none';"
   ```
4. Enter one digit per box (auto-advance) → lands on `/m/app/dashboard`. Dismiss any level-up dialogs before asserting.
5. Navigate to the screen under test (`/m/app/tax-strategy`, etc.) and interact per the browser-testing law — click, fill, submit, assert content (£ amounts vs the user's actual profile, not just DOM shape).

## Path 2 — backend verification via API (fastest, no UI)

Mint a Sanctum token via tinker and curl the endpoint:
```bash
TOKEN=$(php artisan tinker --execute="echo \App\Models\User::where('email','savetaxb2test@example.com')->first()->createToken('verify')->plainTextToken;")
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" https://csjones.co/fynla/api/v1/mobile/dashboard | python3 -m json.tool
```
Use for aggregator/level/actions assertions (e.g. mark-done → re-fetch → assert `level.actions_completed` changed). UI-rendering claims still need Path 1 — an API 200 is not "verified on /m".

## Leaving /m for desktop routes

Navigation out of `/m` to a desktop-only route (e.g. `/admin`) must target `window.top`, not the iframe — the in-frame guard bounces framed desktop routes back to `/m/app`. See `reference_m_desktop_auth_bridge` memory for the full bridge topology and the iOS sessionStorage-partitioning gotcha before touching any cross-SPA auth code.
