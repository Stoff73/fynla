# Fynla — Tech Stack and High-Level Architecture

**Stamp:** commit `a7c13608a`, branch `fix/rsu-value-labels-free-tier-capture`, 2026-09-22.
**Method:** every claim below was read from a file in this run. Where I could not read the backing evidence it says "I COULD NOT VERIFY".

---

## 1. In plain English

Fynla is one Laravel application with one MySQL database, served to three front ends: the desktop web app, the `/m` mobile web app, and a native iPhone app. All three talk to the same HTTP API. Behind the API, each of the seven planning modules has a "module agent" that runs the calculations and produces recommendations, and Fyn, the AI companion, sits on top of those same engines. It is hosted on shared SiteGround hosting, built locally, and deployed by hand.

---

## 2. Runtime stack

| Layer | Technology | Evidence |
|---|---|---|
| Language | PHP `^8.2` (local machine runs 8.5.2) | `composer.json` require; `php -v` |
| Backend framework | Laravel `^10.10` | `composer.json` |
| Auth | Laravel Sanctum `^3.3` (bearer tokens; 61 `auth:sanctum` groups in `routes/api.php`) plus TOTP MFA via `pragmarx/google2fa-laravel` | `composer.json`; `routes/api.php:186,272,279` |
| Database | MySQL 8 (`DB_CONNECTION` default `mysql`); 342 migrations; 125 model files | `config/database.php:19`; `ls database/migrations`; `ls app/Models` |
| Cache / session / queue | Defaults `file` / `file` / `sync` in the env template; `queue:restart` is part of the csjones deploy runbook, so a worker is expected on servers. **I COULD NOT VERIFY** the live `.env` values. | `.env.example:24-28`; `deploy/DEPLOY.md` step 4 |
| Mail | `MAIL_MAILER` default `log`; template says `smtp` | `config/mail.php:16`; `.env.example:37` |
| File storage | `local` default, S3 disk configured | `config/filesystems.php:16,47` |
| Frontend framework | Vue 3 `^3.5`, Vue Router 4, Vuex 4 (+ `vuex-persistedstate`) | `package.json` |
| Build | Vite 5 with `laravel-vite-plugin`, `vite-plugin-pwa` (web only); separate `vite.mobile.config.js` for `/m` with no Laravel plugin and no PWA | `vite.config.js`; `vite.mobile.config.js` |
| Styling | Tailwind CSS 3 with the Fynla palette tokens (`raspberry`, `horizon`, `spring`, `violet`, `savannah`, `eggshell`) | `tailwind.config.js:15-48` |
| Charts | ApexCharts 5 via `vue3-apexcharts` | `package.json` |
| Rich text | TipTap 3 (editor extensions for tables, images, links) | `package.json` |
| Native iOS | SwiftUI, Xcode project `ios-native/Fynla.xcodeproj`, two schemes (`Fynla-Staging`, `Fynla-Production`), both pointing at fynla.org | `ios-native/CLAUDE.md` |
| PDF / documents | `barryvdh/laravel-dompdf`, `phpoffice/phpspreadsheet`, `phpoffice/phpword`, `smalot/pdfparser`, `intervention/image`, `mews/purifier` | `composer.json` |
| AI SDKs | `anthropic-ai/sdk`, `openai-php/client` (composer); plus a hand-written `XaiClient` | `composer.json`; `app/Services/AI/XaiClient.php` |

---

## 3. Third-party services

| Service | Used for | Evidence |
|---|---|---|
| xAI (`grok-4.3` chat, vision and "advanced" models) | Fyn chat and document extraction when `AI_PROVIDER=xai`; runtime override stored in cache from the admin panel | `config/services.php:40-53`; `app/Services/Documents/AIExtractionService.php:127-189` |
| Anthropic (`claude-haiku-4-5`, `claude-sonnet-4-6`) | The other chat provider; also the marketing pipeline's `AnthropicOpusClient` | `config/services.php:34-38`; `app/Services/Pipeline/AnthropicOpusClient.php` |
| Revolut Merchant | Subscription payments (sandbox on dev, live on prod); tier price sync | `config/services.php:60-65`; `app/Services/Payment/Revolut*.php`; `app/Services/Tiers/RevolutTierVariationSync.php` |
| Apple App Store Server API | Native in-app purchase verification through a Python bridge (`services/apple_store_bridge/cli.py`) | `config/apple_store.php:18-39` |
| APNs / FCM | Push notifications to native and mobile | `config/services.php:67-81`; `app/Services/Mobile/ApnsClient.php` |
| getAddress.io | Postcode lookup | `config/services.php:56` |
| Companies House | Filing due dates for a user's limited company | `config/services.php:100-104` |
| GitHub Issues API | In-app bug reports become issues, feeding the `claude.yml` auto-fix workflow | `config/services.php:83-89`; `.github/workflows/claude.yml` |
| Pexels | Stock cover images for CMS articles | `config/services.php:92-95` |
| Plausible | Privacy-first web analytics | `config/analytics.php:30` |
| Awin | Affiliate attribution (off by default, on in the prod template) | `workforce/core/registry/capabilities.md` §7.3 |
| HeyGen, local Whisper, FFmpeg | Marketing video pipeline: script -> avatar video -> transcription -> clips | `app/Services/HeyGenService.php:48`; `app/Services/Pipeline/LocalWhisperTranscriber.php`; `app/Services/FFmpegService.php` |
| Google Drive (service account) | Word documents in Drive become `InsightArticle` records | `app/Services/Pipeline/Google/`; `workforce/core/registry/storage.md` §3 |
| AWS S3 | Application file storage | `config/filesystems.php:47`; `storage.md` §3 |

---

## 4. High-level architecture

### 4.1 Request flow (all three clients)

```
Browser / iPhone
   │  HTTPS, Sanctum bearer token
   ▼
routes/api.php (1,577 lines) · routes/api_v1.php (native /api/v1/native/*) · routes/web.php (server-rendered public pages + SPA catch-all)
   ▼
Form Request (validation)  ->  Controller (app/Http/Controllers/Api/*, ~80 top-level + Admin, Estate, Investment, Retirement, Tax, V1 subfolders)
   ▼
Module Agent (app/Agents/*)  ->  Services (app/Services/*, 558 files across 50 domain folders)  ->  Eloquent Models  ->  MySQL
   ▼
API Resource / projection  ->  JSON back to the client
```

Evidence: `CLAUDE.md` flow line; `ls app/Http/Controllers/Api`; `ls app/Services`; `wc -l routes/*.php`.

### 4.2 The three clients

| Client | Source | Bundle | Notes |
|---|---|---|---|
| Desktop web SPA | `resources/js/` (36 Vuex modules, views per module) | `public/build/` via `vite.config.js` | PWA plugin enabled; router base and API base set by the per-environment build script |
| `/m` mobile web | `resources/mobile/` (own `api.js`, `router.js`, `store.js`, `tokens.js`) | `public/m-build/` via `vite.mobile.config.js` | Deliberately isolated: no Laravel Vite plugin, no PWA, `publicDir: false`; only shared file is `store/modules/auth.js` |
| Native iOS | `ios-native/` SwiftUI | Xcode archive via `deploy/mobile-native/` | Layers: `Core/API`, `Core/Authentication`, `Core/Keychain`, `Core/Biometrics`, `Core/StoreKit`, `Core/DesignSystem`, `Features/{Area}`; decodes with `TolerantDecoding`; native-only session routes under `/api/v1/native/auth/session/*` |

Evidence: `vite.config.js`; `vite.mobile.config.js`; `ios-native/CLAUDE.md` "Layering"; `routes/api_v1.php:48-118`.

### 4.3 Public pages are not the SPA

The homepage, `/how-it-works`, `/features`, `/pricing` and the other marketing pages are server-rendered PHP files under `public/pages/`, included by closures in `routes/web.php` and cached for five minutes. Authenticated users are redirected to the SPA by the `redirect.authed` middleware. This is why compiling the route cache is banned on this app: the compiled matcher lets the SPA catch-all shadow `/`.

Evidence: `routes/web.php:96-135`; `deploy/DEPLOY.md` "NEVER php artisan optimize".

### 4.4 The AI layer (summary; full detail in `agentsMap.md`)

- One endpoint for every surface: `POST /api/ai-chat/conversations/{id}/messages`, handled by `AiChatController`.
- One unified system prompt (`FynSystemPrompt` + `FynContextAssembler`), two write states enforced by tool-gating (Onboarding Fyn writes, Advice Fyn is read-only).
- A CoALA decision loop under `app/Services/AI/Loop/` with a cycle cap of 8 per turn, and three durable memory stores as markdown under `fyn-memory/` (procedural, episodic, semantic) plus per-user semantic memory under `storage/app/memory/`.
- Provider switchable between Anthropic and xAI; per-user weekly token budgets live in `tier_configurations`.

Evidence: `.claude/skills/fyn-architecture/SKILL.md`; `config/fyn.php:17-68`; `fyn-memory/README.md`; `workforce/core/constitution/06-commercials.md` §3.

### 4.5 Commercial and entitlement layer

Two tiers only, Free (£0) and Premium (£6.99 monthly / £59.99 annual), seeded into `tier_configurations` by `TierConfigurationSeeder`. `TierResolver` and `PremiumEntitlementResolver` decide a user's tier; `DbTierGate` and `TeaserGate` enforce it. Free tier has count caps (for example 2 savings, 2 investments, 2 pensions, 1 property) and the Estate module in "teaser" state. Legacy paid plans are grandfathered.

Evidence: `database/seeders/TierConfigurationSeeder.php:31-74`; `app/Services/Tiers/`; `workforce/core/constitution/06-commercials.md` §1-2.

### 4.6 Background work

The Laravel scheduler (`app/Console/Kernel.php`) runs 41 entries: subscription expiry and renewal reminders, account deletion and retention purges, module alert emails (protection, savings, estate, mortgage rates, business filings), the lifecycle email engine at 08:30, daily insight push at 08:00, payment reconciliation every ten minutes, AI audit retention and chain verification weekly, stale-conversation summarisation every thirty minutes, episodic memory reconcile nightly and cold-archive weekly, and the marketing pipeline (Drive watch, post scheduling, weekly social report). Queue jobs include `ConversationSummariserJob`, `RunMonteCarloSimulation`, `RecalculateRiskProfileJob`, `ProcessAppleNotification`, `FireAwinConversionJob` and `PublishScheduledInsightsJob`.

Evidence: `app/Console/Kernel.php:21-111`; `ls app/Jobs`.

### 4.7 Data protection and audit

- Encryption of existing data is a one-off artisan command (`data:encrypt`, `app/Console/Commands/EncryptExistingData.php:27`). **I COULD NOT VERIFY** which model attributes are cast encrypted: a grep for the `encrypted` cast across `app/Models` returned zero files in this run.
- GDPR: `GDPRController`, scheduled and grace deletions, retention warnings and purges, `FynUserErase` for AI memory (`app/Console/Commands/`, `Kernel.php:22-27`).
- AI audit chain: `AuditChainService`, `AiAuditRetentionJob`, `ai:audit:verify-chain` weekly (`Kernel.php:59-60`).
- Preview personas are isolated by `is_preview_user` and `PreviewWriteInterceptor` (`CLAUDE.md` Rule 1 and 7).

---

## 5. Environments and deployment

| | Production | Dev / staging |
|---|---|---|
| URL | fynla.org | csjones.co/fynla |
| Branch | `main` | `dev` |
| Hosting | SiteGround shared hosting (no `DirectoryMatch`, memory too low for npm) | same |
| Deploy | manual upload; `ssh-fynla` MCP is production-only | `git pull origin dev` + upload `public/build/` and `public/m-build/` |
| Build | `./deploy/fynla-org/build.sh` (`VITE_BASE_PATH=/build/`) | `./deploy/csjones-fynla/build.sh` (`/fynla/build/`) |

Evidence: `deploy/DEPLOY.md:1-80`; `deploy/README.md:25,101`; `workforce/core/registry/systems.md` §2.

Flow: feature branch -> PR to `dev` -> PR `dev` -> `main`. Nothing reaches `main` without going through `dev`.

---

## 6. Quality tooling and CI

| Tool | Where |
|---|---|
| Pest 2 (486 Unit, 556 Feature, 4 Integration, 27 Browser files; 24 BS-NN scenarios) | `tests/`; `.github/workflows/quality.yml` runs per suite |
| Vitest 3 + `@vue/test-utils` | `tests/frontend`, `resources/**/__tests__` |
| Playwright 1.56 (smoke + full E2E; nightly on chrome and webkit) | `tests/E2E/*.spec.js`; `.github/workflows/nightly.yml` |
| ESLint 9, Pint (PSR-12), policy lint, mobile-impact check | `package.json` scripts; `scripts/quality/` |
| Claude Code hooks: dangerous-command guard, env guard, prod guard, tax-hardcode check, design lint, `/m` parity check, oversight and workforce guards, pre-compact handover | `.claude/hooks/`; `.claude/settings.json` |
| CI workflows: Quality Gate, Nightly Quality (E2E + audits), iOS Native (macOS runner, `xcodebuild`), Logic Guard (dashboard/onboarding), Main Branch Guard, Claude Auto Bug Fix | `.github/workflows/` |
| Fyn eval harness (HTTP-driven, SSE consumer, recall floor 95 per module) | `app/Services/Eval/`; `config/fyn_eval.php:22-33` |
| Local MCP servers: `mysql`, `playwright`, `ssh-fynla` | `.mcp.json` |

---

## 7. Where the knowledge lives

- `CLAUDE.md` at the root and nested in `app/Http/`, `app/Services/`, `database/`, `tests/`, `resources/js/`, `ios-native/`.
- `.claude/skills/` (workflow skills such as `fyn-architecture`, `verify-m`, `release`, `app-map`) and `.claude/agents/` (the workforce, see `agentsMap.md`).
- `workforce/core/` (constitution, charter, registries), `workforce/ops/` (board of 347 items, gates, missions, event log).
- `fynlaBrain/` Obsidian vault on the desktop (mirror, not source).
- `docs/app-map/` section maps and `docs/diagrams/` Excalidraw.
