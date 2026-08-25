# SaveTax Campaign Onboarding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the `/savetax` campaign landing page + the `?from=<campaign>` wire-through that carries a campaign identifier from URL → registration → MFA → dashboard → onboarding chat, so Fyn opens with a campaign-specific welcome and the onboarding flow can later branch on `users.onboarding_fyn_path='campaign'`.

**Architecture:** Reuse the existing `?from=` mechanism (S0.15 / INV-2.2.5). Add a parallel `campaign_map` config (analogous to `journey_map`) consumed by `AiChatController::startOnboarding`. Close the live-browser-flow gap that made `?from=` unreachable from production paths today (`aiChatService.startOnboardingStream` posts `body: '{}'` and never forwards `from`). Same fix unblocks all journey CTAs (BS-05 / PSP-LS) as a side benefit. Sections 4 (post-expenses state branch), 5 (spouse-work tool), and 6 (terminal page / strategy outcome) are explicitly DEFERRED — they need CSJ's planned conversation map and a separate follow-up plan.

**Tech Stack:** Laravel 10, PHP 8.3, Vue 3 (Composition + Options API mix), Vuex, Pest (PHP), Playwright MCP (browser smoke), Tailwind 3 with Segoe UI primary font (per `fynlaDesignGuide.md`).

**Branch:** `feature/fyn-persona-split` (current). All commits land on this branch.

---

## Already shipped this session (verify before starting)

The agent that ran the brainstorming session created **`resources/js/views/Public/SaveTaxCampaignPage.vue`** and may have started further work before being interrupted. Task 1 verifies the file matches this plan and is uncommitted; if missing or different, Task 1 includes the full file content to recreate.

Also note: lowercase-spouse-name extractor fix (`OnboardingFactExtractor::extractSpouse`) was shipped in a separate adjacent commit during the same session. Independent of this plan, no rebase needed.

## Out of scope (explicitly deferred)

- **Section 4** — post-expenses state-machine branch (the "Hello {name}, in order to generate your tax savings strategies, there are some additional details I need to gather. Does {spouse_name} work?" conversation). CSJ is mapping this out; future plan.
- **Section 5** — `capture_spouse_work_details` tool + the deterministic "no, doesn't work" write path.
- **Section 6** — terminal page / strategy outcome wiring (whether `/actions` is the right destination, or a campaign-specific dashboard is built).

This plan only ships: landing page, `?from=` wire-through, campaign_map config, controller dispatch, state-machine welcome message at `STATE_BASE_PERSONAL`. After this lands, the user clicking through `/savetax → register → MFA → dashboard` reaches base_personal with a campaign-specific opening message, then continues the existing flow. They do NOT yet reach a campaign-specific terminal — they currently fall through to `STATE_ASSET_CAPTURE` like every other onboarding path. That's intentional until sections 4-6 land.

## File structure

| Action | Path | Responsibility |
|---|---|---|
| CREATE | `resources/js/views/Public/SaveTaxCampaignPage.vue` | The new landing page. Hero + Allowances 2026/27 table + 4 example cards + docked StaticFynChat. Inline content via `data()` arrays. CTA + example buttons all link to `/register?from=savetax`. |
| MODIFY | `resources/js/router/index.js:227-231` | Point `/savetax` route at `SaveTaxCampaignPage` instead of generic `CampaignPage`. Other campaign routes (`/biggerpension`, `/paymortgage`, `/managedebt`, `/wealth`) stay on `CampaignPage`. |
| MODIFY | `resources/js/views/Register.vue:340-351` | Generalise the `if (fromParam === 'fyn')` branch so any non-empty `fromParam` routes to Dashboard with `openFyn=journey` + propagates `from` in the query. |
| MODIFY | `resources/js/views/Dashboard.vue:2154-2183` | Read `this.$route.query.from` alongside `openFyn`. Pass to `aiChat/startOnboardingConversation` action. Strip from URL after consumption. |
| MODIFY | `resources/js/store/modules/aiChat.js:854` | `startOnboardingConversation` accepts `{ from }` payload. Forwards to `aiChatService.startOnboardingStream`. |
| MODIFY | `resources/js/services/aiChatService.js:111` | `startOnboardingStream` accepts `{ signal, from }`. When `from` provided, send `JSON.stringify({ from })` instead of `'{}'`. |
| MODIFY | `config/onboarding.php` | Add `campaign_map` array with `'savetax' => 'savetax'`. |
| MODIFY | `app/Http/Controllers/Api/AiChatController.php:340-360` | Check `campaign_map` BEFORE `journey_map`. On match: set `onboarding_fyn_path='campaign'`, `onboarding_fyn_selection=<campaign-id>`, `onboarding_fyn_step=STATE_BASE_PERSONAL`. |
| MODIFY | `app/Services/Onboarding/OnboardingStateMachine.php:buildPersonalPrompt` | Add a fourth branch: when `onboarding_fyn_path === 'campaign'` and the user has neither DOB nor marital status set, prepend a campaign-specific welcome to the existing grouped DOB+marital question. Welcome text is sourced from a per-selection map keyed on `onboarding_fyn_selection`. |
| CREATE | `tests/Feature/Onboarding/EntrySourceCampaignMapTest.php` | Mirror of `EntrySourceJourneyMapTest`. Verifies `?from=savetax` sets path/selection/step + skips path_choice. |
| MODIFY | `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php` | Add `describe('OnboardingStateMachine::buildPersonalPrompt — campaign welcome', ...)` block. 3 cases: campaign welcome fires for fresh campaign user; existing branches still fire for path='journey'/'focus'; resume case (DOB or marital already set) skips the welcome. |

No new agent classes. No new migrations. No new Vuex modules.

---

## Pre-flight (run once before starting)

- [ ] **Verify branch + working tree**

```bash
git -C /Users/CSJ/Desktop/fynla branch --show-current
git -C /Users/CSJ/Desktop/fynla status
```

Expected: branch is `feature/fyn-persona-split`. Working tree may have an uncommitted `SaveTaxCampaignPage.vue` from this session — that's expected; Task 1 handles it.

- [ ] **Verify dev server is up**

```bash
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8000 --max-time 3
```

Expected: `HTTP 200`. If not, run `./dev.sh` and wait for Vite + Laravel to come up.

- [ ] **Run baseline Pest sweep on Onboarding + Fyn suites**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/ tests/Feature/Onboarding/ tests/Feature/Fyn/
```

Expected: 386 passed, 1 skipped, 0 failed (or higher if the lowercase-fix branch added cases). Record the count — use as regression floor.

---

## Task 1: Verify or create the SaveTax landing page

**Files:**
- Create (or verify): `resources/js/views/Public/SaveTaxCampaignPage.vue`

- [ ] **Step 1: Check if the file already exists from the paused session**

```bash
ls -la /Users/CSJ/Desktop/fynla/resources/js/views/Public/SaveTaxCampaignPage.vue
```

Expected: file exists, ~165 lines, modified today.

- [ ] **Step 2: Verify content matches expected structure**

```bash
grep -c "incomeAllowances\|investmentAllowances\|examples\|StaticFynChat\|campaignRegistrationLink" /Users/CSJ/Desktop/fynla/resources/js/views/Public/SaveTaxCampaignPage.vue
```

Expected: `5` (one match per term). If less, the file is incomplete or different — recreate it with Step 3 content.

- [ ] **Step 3: If missing or wrong, create the file with exactly this content:**

```vue
<template>
  <PublicLayout>
    <div class="campaign-body">
      <!-- Hero -->
      <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
        <div class="campaign-inner relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-left w-full">
          <h1 class="text-4xl md:text-6xl font-black text-white mb-4 leading-tight">
            Save more on <span class="text-raspberry-300">tax</span>
          </h1>
          <p class="text-lg text-white/70 max-w-2xl">
            Understand your tax position, maximise your allowances, and keep more of what you earn with Fynla's complete financial planning platform.
          </p>
        </div>
      </div>

      <!-- Allowances 2026/27 -->
      <div class="bg-eggshell-500 py-14 lg:py-16">
        <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="mb-10 lg:mb-12">
            <span class="inline-block text-xs font-mono uppercase tracking-widest text-raspberry-500 mb-2">Tax year 2026/27</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-horizon-500 leading-tight">Your allowances</h2>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
            <div>
              <div class="mb-5 pb-3 border-b-2 border-horizon-500">
                <h3 class="text-xl font-bold text-horizon-500">Income</h3>
              </div>
              <ul class="space-y-4">
                <li v-for="item in incomeAllowances" :key="item.label" class="flex items-baseline justify-between gap-3 pb-3 border-b border-light-gray">
                  <div>
                    <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ item.note }}</p>
                  </div>
                  <span class="text-lg font-bold text-raspberry-500 whitespace-nowrap font-mono">{{ item.amount }}</span>
                </li>
              </ul>
            </div>
            <div>
              <div class="mb-5 pb-3 border-b-2 border-raspberry-500">
                <h3 class="text-xl font-bold text-horizon-500">Investment &amp; Cash</h3>
              </div>
              <ul class="space-y-4">
                <li v-for="item in investmentAllowances" :key="item.label" class="flex items-baseline justify-between gap-3 pb-3 border-b border-light-gray">
                  <div>
                    <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ item.note }}</p>
                  </div>
                  <span class="text-lg font-bold text-raspberry-500 whitespace-nowrap font-mono">{{ item.amount }}</span>
                </li>
              </ul>
            </div>
          </div>

          <p class="text-base sm:text-lg text-neutral-500 leading-relaxed mt-12 max-w-3xl">
            Knowing how to get the most out of, and use your allowances can be tricky.
            <span class="font-semibold text-horizon-500">Fyn can help.</span>
            Here are a few common situations where the right allowance — or the right account — can save you thousands.
          </p>
        </div>
      </div>

      <!-- Examples — "Could this be you?" -->
      <div class="bg-light-pink-100 py-14 lg:py-16">
        <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="mb-10 lg:mb-12">
            <span class="inline-block text-xs font-mono uppercase tracking-widest text-raspberry-500 mb-2">Real-life examples</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-horizon-500 leading-tight">Could this be you?</h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <article v-for="example in examples" :key="example.title" class="bg-white rounded-2xl border border-light-gray p-6 lg:p-7 flex flex-col">
              <h3 class="text-xl font-bold text-horizon-500 mb-3">{{ example.title }}</h3>
              <p class="text-sm text-neutral-500 leading-relaxed flex-1" v-html="example.body"></p>
              <router-link :to="campaignRegistrationLink" class="mt-5 inline-block px-5 py-2.5 bg-raspberry-500 hover:bg-raspberry-600 text-white text-sm font-semibold rounded-lg transition-colors text-left">
                Ask Fyn how →
              </router-link>
            </article>
          </div>
          <div class="text-center mt-12">
            <p class="text-sm text-neutral-500 mb-4">Got a different question? Just ask.</p>
            <router-link :to="campaignRegistrationLink" class="inline-block px-8 py-3 bg-spring-500 hover:bg-spring-600 text-white text-base font-semibold rounded-lg transition-colors">
              Start your free 7-day trial
            </router-link>
          </div>
        </div>
      </div>
    </div>
    <StaticFynChat />
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import StaticFynChat from '@/components/Public/StaticFynChat.vue';

const META_TITLE = 'Save Tax — Maximise Your Allowances | Fynla';
const META_DESC = 'Maximise ISA allowances, pension tax relief, and Marriage Allowance. See your full tax position with Fynla.';

export default {
  name: 'SaveTaxCampaignPage',
  components: { PublicLayout, StaticFynChat },
  data() {
    return {
      campaignRegistrationLink: { path: '/register', query: { from: 'savetax' } },
      incomeAllowances: [
        { label: 'Personal Allowance', note: 'Tax-free income each year', amount: '£12,570' },
        { label: 'Savings Allowance', note: 'Basic-rate taxpayers', amount: '£1,000' },
        { label: 'Starting Rate for Savings', note: 'If non-savings income < £17,570', amount: '£5,000' },
        { label: 'Marriage Allowance', note: 'Transferable to spouse', amount: '£1,260' },
      ],
      investmentAllowances: [
        { label: 'ISA Allowance', note: 'Tax-free savings & investing', amount: '£20,000' },
        { label: 'CGT Allowance', note: 'Capital gains exempt amount', amount: '£3,000' },
        { label: 'Dividend Allowance', note: 'Tax-free dividend income', amount: '£500' },
        { label: 'Pension Annual Allowance', note: 'Tax-relievable contributions', amount: '£60,000' },
      ],
      examples: [
        { title: 'Non-working spouse', body: 'If no income is earned, a non-earning spouse can still receive up to <span class="font-bold text-horizon-500 font-mono">£18,750</span> per year of income tax-free by combining the Personal Allowance, Starting Rate for Savings and Personal Savings Allowance.' },
        { title: 'High income tax trap', body: 'If you earn above <span class="font-bold text-horizon-500 font-mono">£100,000</span> per year, you may have some of your income taxed at an effective rate of <span class="font-bold text-horizon-500 font-mono">60%</span> due to the tapered withdrawal of your Personal Allowance.' },
        { title: 'General Investment Accounts', body: 'Just using these, you can take up to <span class="font-bold text-horizon-500 font-mono">£3,500</span> per year of tax-free gains and <span class="font-bold text-horizon-500 font-mono">£500</span> per year of tax-free dividend income — on top of your ISA.' },
        { title: "National Insurance payments (NIC's)", body: 'When you pay into your pension, both you and your employer pay <span class="font-bold text-horizon-500">NICs</span> on those contributions. But if your employer pays directly into your pension, neither side pays NICs at all. A <span class="font-bold text-horizon-500">salary sacrifice</span> scheme makes tax-efficient use of this difference.' },
      ],
    };
  },
  mounted() {
    document.title = META_TITLE;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', META_DESC);
  },
};
</script>

<style scoped>
.campaign-body { margin-right: 0; }
@media (min-width: 1024px) {
  .campaign-body { margin-right: 356px; }
  .campaign-body :deep(.campaign-inner) {
    max-width: none;
    margin-left: max(1rem, calc((100vw - 80rem) / 2));
    margin-right: 0;
    padding-left: 1rem;
    padding-right: 2rem;
  }
}
</style>
```

- [ ] **Step 4: Verify the file does not import the Inter Google Font**

```bash
grep -n "fonts.googleapis.com\|family=Inter" /Users/CSJ/Desktop/fynla/resources/js/views/Public/SaveTaxCampaignPage.vue || echo "OK — no Google Font import"
```

Expected: `OK — no Google Font import`. The page relies on `tailwind.config.js`'s `fontFamily.sans: ['Segoe UI', 'Inter', ...]` which already enforces Segoe UI primary per `fynlaDesignGuide.md`.

- [ ] **Step 5: Do NOT commit yet** — Task 2 routes the page in. Both changes commit together.

---

## Task 2: Route `/savetax` to the new component

**Files:**
- Modify: `resources/js/router/index.js:227-231`

- [ ] **Step 1: Read the current `/savetax` route block**

```bash
sed -n '215,235p' /Users/CSJ/Desktop/fynla/resources/js/router/index.js
```

Expected output includes:
```
  {
    path: '/savetax',
    name: 'CampaignSaveTax',
    component: CampaignPage,
    meta: { public: true },
  },
```

- [ ] **Step 2: Find the existing CampaignPage import line**

```bash
grep -n "CampaignPage" /Users/CSJ/Desktop/fynla/resources/js/router/index.js | head -3
```

Expected: one import at top, four route entries (`CampaignSaveTax`, `CampaignBiggerPension`, `CampaignPayMortgage`, `CampaignManageDebt`).

- [ ] **Step 3: Add a new import for `SaveTaxCampaignPage`** alongside the existing `CampaignPage` import. Use the existing lazy-loaded pattern if `CampaignPage` is lazy-loaded; mirror it if not. Example direct import:

```javascript
import SaveTaxCampaignPage from '@/views/Public/SaveTaxCampaignPage.vue';
```

(Place it next to the existing `CampaignPage` import.)

- [ ] **Step 4: Replace the `/savetax` route's component**

Change:
```javascript
{
  path: '/savetax',
  name: 'CampaignSaveTax',
  component: CampaignPage,
  meta: { public: true },
},
```

To:
```javascript
{
  path: '/savetax',
  name: 'CampaignSaveTax',
  component: SaveTaxCampaignPage,
  meta: { public: true },
},
```

Leave the other four campaign routes (`CampaignBiggerPension`, `CampaignPayMortgage`, `CampaignManageDebt`, plus any `/wealth` entry) on `CampaignPage` — out of scope.

- [ ] **Step 5: Smoke-test in browser via Playwright MCP**

```javascript
// Navigate
await mcp__playwright__browser_navigate({ url: 'http://localhost:8000/savetax' });
// Wait for SPA to render
await mcp__playwright__browser_wait_for({ time: 2 });
// Snapshot
await mcp__playwright__browser_snapshot();
```

Expected snapshot includes: `Save more on tax` heading, `Tax year 2026/27`, `Your allowances`, `Could this be you?`, four example cards with `Ask Fyn how →` buttons.

- [ ] **Step 6: Commit Tasks 1 + 2 together**

```bash
git add resources/js/views/Public/SaveTaxCampaignPage.vue resources/js/router/index.js
git commit -m "$(cat <<'EOF'
feat(campaigns): /savetax landing page

Forks /savetax from the generic CampaignPage to a dedicated
SaveTaxCampaignPage with hero, 2026/27 allowances table, and 4
example cards. CTA + example buttons all link to /register?from=savetax
so the next plan task can wire that through to the onboarding chat.

Other campaign routes (/biggerpension, /paymortgage, /managedebt)
stay on CampaignPage — out of scope for this change.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Backend campaign_map config

**Files:**
- Modify: `config/onboarding.php`
- Test: (no Pest test for this step — Task 5 covers config + controller together via integration test).

- [ ] **Step 1: Open `config/onboarding.php` and append a `campaign_map` array**

After the existing `journey_map` block (around line 53-58), add:

```php
    /*
    |--------------------------------------------------------------------------
    | Entry-source campaign map
    |--------------------------------------------------------------------------
    |
    | Mirrors `journey_map` for marketing-driven entry points. When
    | POST /api/ai-chat/onboarding/start arrives with a `from` value
    | matching a key here, the controller pre-selects the campaign
    | (path='campaign', selection=<value>) and skips path_choice +
    | journey_selection / focus_selection. Checked BEFORE journey_map
    | so a campaign id never gets misclassified as a journey id.
    |
    | Adding a new campaign requires only a new key/value pair here.
    |
    */
    'campaign_map' => [
        'savetax' => 'savetax',
    ],
```

- [ ] **Step 2: Clear config cache**

```bash
php artisan config:clear
```

- [ ] **Step 3: Verify the config loads**

```bash
php artisan tinker --execute="echo json_encode(config('onboarding.campaign_map'));"
```

Expected: `{"savetax":"savetax"}`.

- [ ] **Step 4: Do NOT commit yet** — Task 4 ships the controller change. They commit together.

---

## Task 4: Controller checks campaign_map BEFORE journey_map

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php` — the `startOnboarding` block at lines 340-360 that currently reads `from` and looks it up in `journey_map`.

- [ ] **Step 1: Read the current dispatch block**

```bash
sed -n '340,365p' /Users/CSJ/Desktop/fynla/app/Http/Controllers/Api/AiChatController.php
```

Expected: the journey_map lookup block.

- [ ] **Step 2: Replace the block with the campaign-aware version**

Find:
```php
        $from = $request->input('from');
        $journeyMap = (array) config('onboarding.journey_map', []);
        $matchedJourney = is_string($from) && isset($journeyMap[$from]) ? $journeyMap[$from] : null;

        if ($matchedJourney !== null) {
            $user->onboarding_fyn_path = 'journey';
            $user->onboarding_fyn_selection = $matchedJourney;
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_BASE_PERSONAL;
            $startStateId = OnboardingStateMachine::STATE_BASE_PERSONAL;
        } else {
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_PATH_CHOICE;
            $startStateId = OnboardingStateMachine::STATE_PATH_CHOICE;
        }
```

Replace with:
```php
        $from = $request->input('from');
        $campaignMap = (array) config('onboarding.campaign_map', []);
        $journeyMap = (array) config('onboarding.journey_map', []);
        $matchedCampaign = is_string($from) && isset($campaignMap[$from]) ? $campaignMap[$from] : null;
        $matchedJourney = is_string($from) && $matchedCampaign === null && isset($journeyMap[$from]) ? $journeyMap[$from] : null;

        if ($matchedCampaign !== null) {
            $user->onboarding_fyn_path = 'campaign';
            $user->onboarding_fyn_selection = $matchedCampaign;
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_BASE_PERSONAL;
            $startStateId = OnboardingStateMachine::STATE_BASE_PERSONAL;
        } elseif ($matchedJourney !== null) {
            $user->onboarding_fyn_path = 'journey';
            $user->onboarding_fyn_selection = $matchedJourney;
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_BASE_PERSONAL;
            $startStateId = OnboardingStateMachine::STATE_BASE_PERSONAL;
        } else {
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_PATH_CHOICE;
            $startStateId = OnboardingStateMachine::STATE_PATH_CHOICE;
        }
```

The campaign check happens BEFORE journey check. A `from` value matching both maps is impossible if config keys don't collide; the explicit ordering documents the intent.

- [ ] **Step 3: Clear cache**

```bash
php artisan config:clear && php artisan cache:clear
```

- [ ] **Step 4: Do NOT commit yet** — Task 5 ships the test that proves this works.

---

## Task 5: Pest integration test for campaign_map

**Files:**
- Create: `tests/Feature/Onboarding/EntrySourceCampaignMapTest.php`

- [ ] **Step 1: Read the existing journey-map test as a template**

```bash
sed -n '1,90p' /Users/CSJ/Desktop/fynla/tests/Feature/Onboarding/EntrySourceJourneyMapTest.php
```

Skim the structure: `RefreshDatabase` trait, `TaxConfigurationSeeder`, `grantAiChatConsentForJourneyMapTest` helper, parameterised `it(...)->with(...)` tests.

- [ ] **Step 2: Write the failing test** at `tests/Feature/Onboarding/EntrySourceCampaignMapTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * INV-2.2.5 (campaign extension) — entry-source → campaign mapping is
 * config-driven. AiChatController::startOnboarding looks the request
 * `from` value up in config('onboarding.campaign_map') BEFORE
 * config('onboarding.journey_map'). A match pre-selects the campaign
 * (path='campaign', selection=<id>) and lands the user at
 * STATE_BASE_PERSONAL. Adding a new campaign requires only a config
 * change — never a controller change.
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

function grantAiChatConsentForCampaignMapTest(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

it('exposes the savetax campaign-map entry by default', function () {
    $map = config('onboarding.campaign_map');

    expect($map)->toBeArray();
    expect($map)->toHaveKey('savetax');
    expect($map['savetax'])->toBe('savetax');
});

it('skips path_choice and lands the user at base_personal for a campaign `from` value', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'savetax']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('savetax');
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});

it('falls through to STATE_PATH_CHOICE for an unknown `from` value', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'unknown-thing'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
    expect($user->onboarding_fyn_path)->toBeNull();
});

it('checks campaign_map BEFORE journey_map so a campaign key never gets misread as a journey', function () {
    config()->set('onboarding.campaign_map', ['shared-key' => 'campaign-id']);
    config()->set('onboarding.journey_map', ['shared-key' => 'journey-id']);

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'shared-key'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign');
    expect($user->onboarding_fyn_selection)->toBe('campaign-id');
});

it('does not interfere with existing journey_map behaviour', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsentForCampaignMapTest($user);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/ai-chat/onboarding/start', ['from' => 'protection'])
        ->assertOk();

    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('journey');
    expect($user->onboarding_fyn_selection)->toBe('protection');
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL);
});
```

- [ ] **Step 3: Run the test to verify it passes** (the controller in Task 4 already implements the behaviour)

```bash
./vendor/bin/pest tests/Feature/Onboarding/EntrySourceCampaignMapTest.php
```

Expected: `Tests:    5 passed`. If any fail, fix the controller (Task 4) — the test is the contract.

- [ ] **Step 4: Run the existing journey-map test to confirm no regression**

```bash
./vendor/bin/pest tests/Feature/Onboarding/EntrySourceJourneyMapTest.php
```

Expected: same pass count as before (8 tests per file inspection).

- [ ] **Step 5: Commit Tasks 3 + 4 + 5 together**

```bash
git add config/onboarding.php app/Http/Controllers/Api/AiChatController.php tests/Feature/Onboarding/EntrySourceCampaignMapTest.php
git commit -m "$(cat <<'EOF'
feat(onboarding): campaign_map config + controller dispatch

Adds config('onboarding.campaign_map') checked BEFORE journey_map in
AiChatController::startOnboarding. A matching `from` value sets
users.onboarding_fyn_path='campaign', selection=<campaign-id>, step=
STATE_BASE_PERSONAL — skipping path_choice and journey/focus selection.

First entry: 'savetax' => 'savetax'. Future campaigns plug in with a
single-line config change.

5 Pest cases covering happy path, fallthrough, ordering, journey
non-regression.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: State machine — campaign welcome at base_personal

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` — `buildPersonalPrompt` (search for the function name; it follows the FR-M10 docblock).

- [ ] **Step 1: Read the existing buildPersonalPrompt to understand the current branches**

```bash
grep -n "public static function buildPersonalPrompt" /Users/CSJ/Desktop/fynla/app/Services/Onboarding/OnboardingStateMachine.php
```

Then read 30 lines starting at that line. Expected: 3 branches today (all-empty, DOB-known, marital-known) per FR-M10.

- [ ] **Step 2: Write the failing test FIRST** in `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php`. Append this new describe block at the end of the file (just before the closing `});` of the last existing describe, or as a top-level describe — top-level is fine):

```php

/**
 * Campaign welcome — when the user arrives via the campaign_map
 * (onboarding_fyn_path='campaign'), buildPersonalPrompt prepends a
 * one-sentence campaign-specific opening to the existing grouped
 * DOB+marital question. All in one bubble (option A from the spec).
 *
 * Welcome only fires for fresh users (neither DOB nor marital_status
 * set) — the existing "I have you as born..." / "Got that you're
 * married..." retry branches still take precedence on resume.
 */
describe('OnboardingStateMachine::buildPersonalPrompt — campaign welcome', function () {
    it('prepends the savetax welcome for a fresh campaign user', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'date_of_birth' => null,
            'marital_status' => null,
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        expect($text)->toContain('tax-saving strategy')
            ->and($text)->toContain('Verify')
            ->and($text)->toContain('date of birth')
            ->and($text)->toContain('married');
    });

    it('does NOT prepend the welcome when the path is journey', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'date_of_birth' => null,
            'marital_status' => null,
            'onboarding_fyn_path' => 'journey',
            'onboarding_fyn_selection' => 'protection',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        expect($text)->not->toContain('tax-saving strategy')
            ->and($text)->toContain('grab a few basics');
    });

    it('does NOT prepend the welcome when the path is focus', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'date_of_birth' => null,
            'marital_status' => null,
            'onboarding_fyn_path' => 'focus',
            'onboarding_fyn_selection' => 'investment',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        expect($text)->not->toContain('tax-saving strategy')
            ->and($text)->toContain('grab a few basics');
    });

    it('skips the welcome on resume when DOB is already set', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'date_of_birth' => '1985-01-12',
            'marital_status' => null,
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'savetax',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        // Welcome suppressed; resume branch ("I have you as born...") fires.
        expect($text)->not->toContain('tax-saving strategy')
            ->and($text)->toContain('12 January 1985');
    });

    it('falls back to a generic campaign welcome for an unknown campaign id', function () {
        $user = User::factory()->create([
            'first_name' => 'Verify',
            'date_of_birth' => null,
            'marital_status' => null,
            'onboarding_fyn_path' => 'campaign',
            'onboarding_fyn_selection' => 'future-campaign-id',
        ]);
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_BASE_PERSONAL);
        $text = OnboardingStateMachine::resolvePromptText($state, $user);

        // Generic campaign welcome — no campaign-specific phrasing — but
        // still distinguishable from the path_choice greeting and the
        // base "grab a few basics" opening.
        expect($text)->toContain('Verify')
            ->and($text)->toContain('date of birth');
    });
});
```

- [ ] **Step 3: Run the test to verify it FAILS**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php --filter="campaign welcome"
```

Expected: 5 failures (because the welcome branch isn't implemented yet).

- [ ] **Step 4: Implement the welcome branch**

In `app/Services/Onboarding/OnboardingStateMachine.php`, find `buildPersonalPrompt`. At the very TOP of the function — before any of the existing branches — add:

```php
        $isCampaign = ($user->onboarding_fyn_path ?? '') === 'campaign';
        $hasNoBasics = empty($user->date_of_birth) && empty($user->marital_status);

        if ($isCampaign && $hasNoBasics) {
            $welcome = self::campaignWelcomeFor((string) ($user->onboarding_fyn_selection ?? ''));
            $firstName = trim((string) ($user->first_name ?? '')) !== ''
                ? trim((string) $user->first_name)
                : 'there';

            return "Hi {$firstName}, {$welcome} Let's start with the basics: what's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?";
        }
```

Then add the helper method at the bottom of the class (just before the final closing `}`):

```php
    /**
     * One-sentence campaign-specific opening prepended to the
     * grouped base_personal question. Keyed on
     * users.onboarding_fyn_selection. Falls back to a generic line
     * for unknown campaigns — better than crashing.
     */
    private static function campaignWelcomeFor(string $campaignId): string
    {
        return match ($campaignId) {
            'savetax' => "welcome to Fynla — I'll help you build your tax-saving strategy.",
            default => "welcome to Fynla — let's get started.",
        };
    }
```

- [ ] **Step 5: Run the test to verify it now PASSES**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php --filter="campaign welcome"
```

Expected: 5 passed.

- [ ] **Step 6: Run the full state-machine test file to confirm no regressions**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php
```

Expected: all previous tests still pass + 5 new passes.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Onboarding/OnboardingStateMachine.php tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php
git commit -m "$(cat <<'EOF'
feat(onboarding): campaign welcome at base_personal

buildPersonalPrompt prepends a one-sentence campaign-specific opening
when users.onboarding_fyn_path='campaign' and the user has neither
DOB nor marital_status set. Welcome text keyed on selection via
campaignWelcomeFor() — first entry is 'savetax'; unknown campaigns
fall back to a generic Fynla welcome.

Resume branches (DOB-known / marital-known) take precedence so the
welcome only fires on the very first base_personal turn.

5 Pest cases — fresh campaign, journey/focus non-regression, resume
suppression, unknown-campaign fallback.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Frontend — Register.vue forwards `from` to Dashboard

**Files:**
- Modify: `resources/js/views/Register.vue:340-351`

- [ ] **Step 1: Read the current routing block**

```bash
sed -n '338,353p' /Users/CSJ/Desktop/fynla/resources/js/views/Register.vue
```

- [ ] **Step 2: Generalise the `fromParam` branch**

Find:
```javascript
      // Route based on registration source
      const fromParam = route.query.from;
      const stageParam = route.query.stage;

      if (fromParam === 'fyn') {
        // Came from "Get started with Fyn" — go to dashboard with Fyn chat open
        router.push({ name: 'Dashboard', query: { openFyn: 'journey', newUser: '1' } });
      } else if (stageParam) {
        router.push({ name: 'Onboarding', query: { stage: stageParam, newUser: '1' } });
      } else {
        router.push({ name: 'Onboarding', query: { newUser: '1' } });
      }
```

Replace with:
```javascript
      // Route based on registration source. Any `from=<id>` (e.g. fyn,
      // savetax, biggerpension) takes the user to the dashboard with the
      // Fyn chat auto-opened, propagating the entry source so the
      // onboarding director can route to the matching campaign or
      // life-stage journey via the campaign_map / journey_map config.
      const fromParam = route.query.from;
      const stageParam = route.query.stage;

      if (fromParam) {
        router.push({
          name: 'Dashboard',
          query: { openFyn: 'journey', newUser: '1', from: fromParam },
        });
      } else if (stageParam) {
        router.push({ name: 'Onboarding', query: { stage: stageParam, newUser: '1' } });
      } else {
        router.push({ name: 'Onboarding', query: { newUser: '1' } });
      }
```

- [ ] **Step 3: Do NOT commit yet** — Tasks 8 + 9 + 10 finish the frontend wire-through. They commit together.

---

## Task 8: Dashboard reads `from` and forwards to onboarding action

**Files:**
- Modify: `resources/js/views/Dashboard.vue:2154-2183`

- [ ] **Step 1: Read the current openFyn handler**

```bash
sed -n '2154,2185p' /Users/CSJ/Desktop/fynla/resources/js/views/Dashboard.vue
```

- [ ] **Step 2: Replace the `dispatch('aiChat/startOnboardingConversation')` call**

Find:
```javascript
      this.$store.dispatch('aiChat/startOnboardingConversation')
        .then(expandDockedChat)
        .catch((err) => {
          // Fall back to just opening an empty chat if the director fails.
          console.warn('[Dashboard] startOnboardingConversation failed, falling back', err);
          expandDockedChat();
        });
```

Replace with:
```javascript
      const fromParam = this.$route.query.from;
      this.$store.dispatch('aiChat/startOnboardingConversation', { from: fromParam })
        .then(expandDockedChat)
        .catch((err) => {
          // Fall back to just opening an empty chat if the director fails.
          console.warn('[Dashboard] startOnboardingConversation failed, falling back', err);
          expandDockedChat();
        });
```

Also update the URL-clean line just above it. Find:
```javascript
      // Clean the query param first so a page reload doesn't re-trigger.
      this.$router.replace({ query: {} });
```

Leave this as-is — it strips `from` along with `openFyn` after consumption (read happens on the line above, push happens before strip).

Actually wait — `this.$router.replace({ query: {} })` happens BEFORE `this.$store.dispatch(...)` in the existing code, so by the time we read `this.$route.query.from`, it's already been replaced to empty. Move the read above the replace:

Find this exact block:
```javascript
      // Clean the query param first so a page reload doesn't re-trigger.
      this.$router.replace({ query: {} });

      // Populate the store BEFORE opening the panel so onOpen() sees the
```

Replace with:
```javascript
      // Capture the from query param BEFORE stripping it so the
      // campaign / journey identifier reaches the onboarding director.
      const fromParam = this.$route.query.from;

      // Clean the query param first so a page reload doesn't re-trigger.
      this.$router.replace({ query: {} });

      // Populate the store BEFORE opening the panel so onOpen() sees the
```

Then in the dispatch call below, use the already-captured `fromParam`:
```javascript
      this.$store.dispatch('aiChat/startOnboardingConversation', { from: fromParam })
```

(Remove the inline `const fromParam = this.$route.query.from;` you would have added — it's now declared above.)

- [ ] **Step 3: Do NOT commit yet** — Tasks 9 + 10 finish.

---

## Task 9: aiChat store action accepts `from` and forwards to service

**Files:**
- Modify: `resources/js/store/modules/aiChat.js:854`

- [ ] **Step 1: Read the current action signature**

```bash
sed -n '854,902p' /Users/CSJ/Desktop/fynla/resources/js/store/modules/aiChat.js
```

- [ ] **Step 2: Update the action signature** to accept a payload object

Find:
```javascript
    async startOnboardingConversation({ commit, dispatch, state, rootState }) {
```

Replace with:
```javascript
    async startOnboardingConversation({ commit, dispatch, state, rootState }, payload = {}) {
        const fromParam = (payload && typeof payload.from === 'string') ? payload.from : null;
```

- [ ] **Step 3: Forward `fromParam` to the service** (further down in the same function, around the `aiChatService.startOnboardingStream` call)

Find:
```javascript
            reader = await aiChatService.startOnboardingStream({ signal: abortController.signal });
```

Replace with:
```javascript
            reader = await aiChatService.startOnboardingStream({
                signal: abortController.signal,
                from: fromParam,
            });
```

- [ ] **Step 4: Do NOT commit yet.**

---

## Task 10: Service sends `from` in the request body

**Files:**
- Modify: `resources/js/services/aiChatService.js:111-125`

- [ ] **Step 1: Read the current service implementation**

```bash
sed -n '103,135p' /Users/CSJ/Desktop/fynla/resources/js/services/aiChatService.js
```

- [ ] **Step 2: Update the function signature + body**

Find:
```javascript
    async startOnboardingStream({ signal } = {}) {
        const token = await getToken();
        const isCapacitor = typeof window !== 'undefined' && window.location.protocol === 'capacitor:';

        const response = await fetch(`${apiBaseURL}/api/ai-chat/onboarding/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'Authorization': `Bearer ${token}`,
            },
            body: '{}',
            credentials: isCapacitor ? 'omit' : 'same-origin',
            signal,
        });
```

Replace with:
```javascript
    async startOnboardingStream({ signal, from } = {}) {
        const token = await getToken();
        const isCapacitor = typeof window !== 'undefined' && window.location.protocol === 'capacitor:';

        // Forward the `from` entry-source identifier (e.g. 'savetax',
        // 'protection') to the backend so the onboarding director can
        // pre-select the matching campaign or life-stage journey via
        // config('onboarding.campaign_map') / journey_map.
        const body = (typeof from === 'string' && from.length > 0)
            ? JSON.stringify({ from })
            : '{}';

        const response = await fetch(`${apiBaseURL}/api/ai-chat/onboarding/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'Authorization': `Bearer ${token}`,
            },
            body,
            credentials: isCapacitor ? 'omit' : 'same-origin',
            signal,
        });
```

- [ ] **Step 3: Commit Tasks 7 + 8 + 9 + 10 together**

```bash
git add resources/js/views/Register.vue resources/js/views/Dashboard.vue resources/js/store/modules/aiChat.js resources/js/services/aiChatService.js
git commit -m "$(cat <<'EOF'
feat(onboarding): wire ?from= URL param through registration to onboarding/start

Closes the gap that made config('onboarding.journey_map') /
campaign_map unreachable from a real browser flow:

- Register.vue: any `from` query param routes to Dashboard with
  openFyn=journey + propagates from in the redirect query
- Dashboard.vue: reads from query, captures BEFORE the URL strip,
  forwards to aiChat/startOnboardingConversation
- aiChat store action: accepts {from} payload, forwards to service
- aiChatService.startOnboardingStream: sends {from} in request body
  instead of '{}'

This unblocks /savetax → /register?from=savetax → MFA → dashboard
auto-onboarding with campaign welcome. Same fix unblocks all
?from= journey CTAs (BS-05 / PSP-LS) as a side benefit.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: End-to-end browser smoke test via Playwright MCP

This task drives the full live flow to prove the wire works. No automated Pest browser scenario stub is added in this plan (a BS-26 scenario stub is a Sprint 0 follow-up, deferred until sections 4-6 land and the conversation has a defined terminal).

- [ ] **Step 1: Make sure dev server is up**

```bash
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8000 --max-time 3
```

Expected: `HTTP 200`.

- [ ] **Step 2: Navigate to /savetax via Playwright MCP**

```javascript
mcp__playwright__browser_navigate({ url: 'http://localhost:8000/savetax' });
mcp__playwright__browser_snapshot();
```

Expected: hero "Save more on tax", allowances table, examples cards, all links to `/register?from=savetax`.

- [ ] **Step 3: Click the main "Start your free 7-day trial" CTA**

Find the CTA via the snapshot ref, then `mcp__playwright__browser_click({ target: '<ref>' })`.

Expected: URL becomes `http://localhost:8000/register?from=savetax`.

- [ ] **Step 4: Fill the registration form and submit**

```javascript
mcp__playwright__browser_type({ target: '<first-name-ref>', text: 'Verify' });
mcp__playwright__browser_type({ target: '<last-name-ref>', text: 'CampaignWire' });
mcp__playwright__browser_type({ target: '<email-ref>', text: 'verify-campaign-2026-04-28@example.com' });
mcp__playwright__browser_type({ target: '<password-ref>', text: 'Password1!' });
mcp__playwright__browser_type({ target: '<confirm-ref>', text: 'Password1!' });
mcp__playwright__browser_click({ target: '<create-account-button-ref>' });
```

Expected: MFA modal opens.

- [ ] **Step 5: Fetch the MFA code from the DB**

```bash
php artisan tinker --execute="
\$p = \App\Models\PendingRegistration::where('email','verify-campaign-2026-04-28@example.com')->latest()->first();
echo \$p ? \$p->verification_code : 'NONE';
"
```

Expected: 6-digit code.

- [ ] **Step 6: Enter MFA digits**

```javascript
mcp__playwright__browser_click({ target: '<first-mfa-input-ref>' });
mcp__playwright__browser_press_key({ key: '<digit1>' });
// repeat for all 6 digits
```

Expected: MFA verified, redirect to `/dashboard?openFyn=journey&newUser=1&from=savetax`.

- [ ] **Step 7: Wait for SSE stream + snapshot the chat panel**

```javascript
mcp__playwright__browser_wait_for({ time: 8 });
mcp__playwright__browser_snapshot();
```

Expected: Fyn message contains both `tax-saving strategy` (welcome) AND `date of birth` + `married` (grouped question) — all in one assistant bubble.

- [ ] **Step 8: Verify DB state**

```bash
php artisan tinker --execute="
\$u = \App\Models\User::where('email','verify-campaign-2026-04-28@example.com')->first();
echo 'path=' . \$u->onboarding_fyn_path . ' selection=' . \$u->onboarding_fyn_selection . ' step=' . \$u->onboarding_fyn_step . PHP_EOL;
"
```

Expected: `path=campaign selection=savetax step=base_personal`.

- [ ] **Step 9: If any of Steps 7 or 8 fail**, return to the failing layer (controller / state machine / service) and re-run that task's Pest tests. Loop until GREEN per CLAUDE.md Rule #15.

- [ ] **Step 10: No commit needed for this task** — it verifies prior commits.

---

## Task 12: Final regression sweep + spec doc

- [ ] **Step 1: Run the full Onboarding + Fyn Pest sweep**

```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/ tests/Feature/Onboarding/ tests/Feature/Fyn/
```

Expected: count meets or exceeds the pre-flight baseline. Zero failures.

- [ ] **Step 2: Run the architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```

Expected: all green.

- [ ] **Step 3: Write spec doc** at `April/April28Updates/savetax-campaign-onboarding-spec.md`

Document what shipped (sections 1, 2, plus the wire-through). Document what's deferred (sections 4, 5, 6) with a clear handover note. Cross-reference this plan file.

- [ ] **Step 4: Commit the spec doc**

```bash
git add April/April28Updates/savetax-campaign-onboarding-spec.md April/April28Updates/savetax-campaign-onboarding-plan.md
git commit -m "$(cat <<'EOF'
docs(campaigns): savetax campaign onboarding plan + spec

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Note: the plan file (this file) is at `April/April28Updates/savetax-campaign-onboarding-plan.md`. The April/ directory is gitignored on this branch — so the commit happens only if the user wants it. If gitignored, skip Step 4.

- [ ] **Step 5: Report ship-readiness** to the user with: total test count delta, files changed count, commits added count, and explicit pointer to the deferred sections 4/5/6 awaiting their planned conversation map.

---

## Self-review checklist (run before handoff)

- [x] Every spec section that's IN scope has a task implementing it (sections 1, 2, 7, 8, plus the wire-through addendum).
- [x] No "TBD" / "TODO" / "fill in later" placeholders.
- [x] Every code step shows the actual code.
- [x] Every test step has the actual test code, not a description.
- [x] Type names consistent across tasks (`onboarding_fyn_path`, `onboarding_fyn_selection`, `STATE_BASE_PERSONAL`, `campaign_map`, `journey_map` — all referenced consistently).
- [x] Commit boundaries make sense — landing page + route together, backend three-task block together, frontend four-task block together, spec doc separately.
- [x] Deferred sections explicitly out-of-scope with a pointer to the future plan.

---

## Out of scope (handover for future plan)

These need CSJ's planned conversation map before they can be specced:

- **Section 4** — post-expenses state-machine branch. Today, after `STATE_PROFILE_REVIEW_EXPENDITURE`, the campaign user falls through to `STATE_ASSET_CAPTURE` (same as journey/focus). The campaign-specific divergence ("Hello {name}, in order to generate your tax savings strategies, there are some additional details I need to gather. Does {spouse_name} work?") is NOT shipped here.
- **Section 5** — `capture_spouse_work_details` tool + the "no, doesn't work" deterministic write path that updates the spouse user's `employment_status`/`employer`/`occupation`/`annual_employment_income`.
- **Section 6** — terminal page / strategy outcome wiring. CSJ noted: *"the actions tab is a good spot, but I need to think this through properly, as we would need to create a dashboard on the fly with the user's information"*. Open question: extend `/actions`, or build a campaign-specific dashboard?
- **BS-26 / BS-27 Playwright scenario stubs** — author once sections 4-6 land and the flow has a defined terminal.

These belong in a follow-up plan once CSJ delivers the conversation map.
