# Frontend Conventions

This file supplements the root `CLAUDE.md` with frontend-specific patterns.

## Entry Points

- `app.js` - Bootstraps Vue app with Router, Vuex, VueApexCharts, `v-preview-disabled` directive
- `App.vue` - Root component, renders `<router-view />`, fetches user data on mount
- `router/index.js` - All routes with lazy loading (`() => import(...)`)
- `store/index.js` - 31 namespaced Vuex modules

## Router

**Route meta flags:**
- `meta: { requiresAuth: true }` - Protected routes (dashboard, modules)
- `meta: { public: true }` - Public pages (landing, calculators)
- `meta: { requiresGuest: true }` - Auth pages (login, register)
- `meta: { previewMode: true }` - Preview persona routes

**Base path:** Configurable via `VITE_ROUTER_BASE` (development: `/`, production: `/` or `/fynla/`)

## Vuex Store Pattern

All 31 modules are `namespaced: true` and follow this structure:

```javascript
// State: data + loading + error
const state = { items: [], loading: false, error: null };

// Mutations: set* (setters), add*/update*/remove* (CRUD)
setItems(state, items) { state.items = items; }
addItem(state, item) { state.items.push(item); }
updateItem(state, item) { /* find by id, splice */ }
removeItem(state, id) { /* find by id, splice */ }

// Actions: async, commit mutations, dispatch cross-module
async fetchItems({ commit }) {
  commit('setLoading', true);
  try {
    const response = await myService.getItems();
    commit('setItems', response.data.items);
  } catch (error) {
    commit('setError', error.message);
    throw error;
  } finally {
    commit('setLoading', false);
  }
}

// Cross-module dispatch
dispatch('netWorth/refreshNetWorth', null, { root: true });
```

**Naming:** Actions use British English spelling: `analyse` not `analyze`, `optimise` not `optimize`.

## Mixins

**currencyMixin** - Always use this, never define local `formatCurrency()`:
```javascript
import { currencyMixin } from '@/mixins/currencyMixin';
// Provides: formatCurrency(), formatCurrencyWithPence(), formatCurrencyCompact(),
//   parseCurrency(), formatPercentage(), formatAccountType(), formatOwnershipType(),
//   formatNumber(), formatLiability()
```

**previewModeMixin** - Use for preview mode checks:
```javascript
import { previewModeMixin } from '@/mixins/previewModeMixin';
// Provides: isPreviewMode (computed), previewGuard(action), getPreviewButtonProps(type),
//   handlePreviewAction(action, type), canOpenModal()
```

## Utilities (`utils/`)

| Utility | Key Exports |
|---------|-------------|
| `currency.js` | `formatCurrency`, `formatCurrencyWithPence`, `formatCurrencyCompact`, `parseCurrency` |
| `dateFormatter.js` | `formatDate` (DD/MM/YYYY), `formatDateForInput` (YYYY-MM-DD), `formatDateLong`, `calculateAge`, `getRelativeTime`, `getTaxYearStart`, `getTaxYearEnd` |
| `ownership.js` | `calculateUserShare`, `isSharedOwnership`, `OWNERSHIP_TYPES`, `getOwnershipLabel` |
| `poller.js` | `poll`, `pollMonteCarloJob` - for long-running async operations |
| `logger.js` | `logger.info/warn/error/debug` - development-only structured logging |

## Constants (`constants/`)

| File | Purpose |
|------|---------|
| `designSystem.js` | `CHART_COLORS`, `ASSET_COLORS`, `PRIMARY_COLORS` (Raspberry), `SECONDARY_COLORS` (Horizon), `SUCCESS_COLORS` (Spring), `WARNING_COLORS` (Violet) — aligned with `fynlaDesignGuide.md` v1.2.0 palette |
| `eventIcons.js` | `LIFE_EVENT_ICONS` - maps event types to icon names |
| `eventIconSvgs.js` | `EVENT_ICON_SVGS` - inline SVG components for life event icons |
| `goalIcons.js` | `GOAL_TYPE_ICONS`, `getGoalIcon()` - maps goal types to emoji icons |
| `taxConfig.js` | Frontend tax references (prefer backend `TaxConfigService` for calculations) |

## API Services Pattern

All services in `services/` are pure API wrappers with no state management:
```javascript
const myService = {
  async getData() { return (await api.get('/endpoint')).data; },
  async create(data) { return (await api.post('/endpoint', data)).data; },
  async update(id, data) { return (await api.put(`/endpoint/${id}`, data)).data; },
  async delete(id) { return (await api.delete(`/endpoint/${id}`)).data; },
};
```

The base `api.js` provides: CSRF injection, auth token from `tokenStorage` (async-ready abstraction over sessionStorage/native storage), automatic retry with exponential backoff (5xx, 429), and preview mode detection.

## Component Conventions

**Views** (`views/`): Page-level route components. Handle module initialisation, data fetching. Use layouts.

**Components** (`components/{Module}/`): Reusable sub-page parts. Organised by module.

**Naming:**
- PascalCase filenames: `TrustFormModal.vue`
- Multi-word required (not single word)
- Suffix patterns: `*Modal` (forms in modals), `*Chart` (charts), `*List` (lists), `*Card` (cards)

**Standard component structure:**
```vue
<script>
import { mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { previewModeMixin } from '@/mixins/previewModeMixin';

export default {
  name: 'MyComponentName',  // Must match filename
  mixins: [currencyMixin, previewModeMixin],
  props: { /* typed, required/default */ },
  data() { return { formData: {}, errors: {}, loading: false }; },
  computed: { ...mapGetters('module', ['items']) },
  methods: { ...mapActions('module', ['fetchItems']) },
};
</script>
```

## Directive: `v-preview-disabled`

Blocks element interaction in preview mode. Adds disabled state, tooltip, and click prevention.
```vue
<button v-preview-disabled>Edit</button>
<button v-preview-disabled="'add'">Add Item</button>
<button v-preview-disabled="'delete'">Delete</button>
```

## Layouts

- **AppLayout** - Authenticated pages: Navbar, TrialCountdownBanner, PreviewBanner, content slot (`max-w-7xl`), Footer, InfoGuidePanel
- **PublicLayout** - Public pages: navigation, login/register buttons, footer

## Mobile App (Capacitor)

Mobile views live in `mobile/` with their own layout (`MobileLayout.vue`) and routes under `/m/`.

**Store modules:** `mobileDashboard`, `mobileNotifications`, `aiChat` (shared with web)

**Data normalisation:** Backend returns raw module fields. The `normaliseModule()` function in `mobileDashboard.js` transforms them into the shape expected by `ModuleSummaryCard` (`metric_type`, `metric_value`, `status`, `subtitle`) and `ModuleSummary` (`hero_metric.formatted`, `hero_metric.value`, `fyn_summary`, `details[]`).

**Platform detection:** `import { platform } from '@/utils/platform'` — `platform.isNative()`, `platform.isIOS()`, `platform.canUseBiometrics()`

**External URLs in mobile:** Never use `window.location.origin` (returns `capacitor://localhost`). Use `import.meta.env.VITE_API_BASE_URL || 'https://fynla.org'`.

**SSE streaming:** `aiChatService.sendMessageStream()` uses raw `fetch()` (not axios) for streaming. On Capacitor, needs `credentials: 'omit'` and fallback for `response.body` being null.

**Biometric (Face ID) login:**
- Credentials stored in iOS Keychain via `@capgo/capacitor-native-biometric` (token as `password`, email as `username`, server `fynla.org`)
- Mobile logout uses `auth/mobileLogout` (clears local state, keeps server token valid) — NEVER use `auth/logout` on mobile or biometric breaks
- `app.js` calls `attemptBiometricLogin()` on startup when no token in Preferences
- `BiometricPrompt.vue` is a bottom-sheet modal shown on dashboard via `?biometricSetup=1` query param
- `SettingsList.vue` has a Face ID toggle at the top of the settings list
- Key files: `appLifecycle.js` (biometric login + app lifecycle), `BiometricPrompt.vue` (setup modal), `SettingsList.vue` (toggle), `MobileLoginScreen.vue` (fallback Face ID button)

**Voice Input (`VoiceInputButton.vue`):**
- Uses `@capacitor-community/speech-recognition` v6.0.1 for native iOS, Web Speech API fallback for browser
- **Continuous listening mode** — mic stays active until user explicitly taps again
- **NEVER call `stop()` then `start()`** — causes fatal Swift crash (nil unwrap at Plugin.swift:81)
- `start()` with `partialResults: true` resolves IMMEDIATELY — use `partialResults` + `listeningState` listeners for results
- `listeningState` `{status: "stopped"}` is the ONLY safe restart point for continuous listening
- `forceStop()` must remove listeners FIRST to prevent ghost restart loops

**CRITICAL — vite.config.js rules for iOS:**
- **NEVER** add `external` to `rollupOptions` for image/asset paths — this makes Rollup leave `/images/*` as JS module imports, causing WKWebView to reject PNGs with `'image/png' is not a valid JavaScript MIME type'` → blank screen.
- **ALWAYS** keep `transformAssetUrls: false` in the `vue()` plugin template config — prevents Vue template compiler from converting `<img src="/images/...">` into JS `import()` calls.
- **ALWAYS** keep `!disablePWA && VitePWA(...)` — PWA must be conditionally disabled for iOS builds via `VITE_DISABLE_PWA=true`.
