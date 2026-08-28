# Frontend Conventions

Supplements the root `CLAUDE.md`.

## Router

Meta flags: `requiresAuth` (protected), `public`, `requiresGuest` (auth pages), `previewMode`. All routes lazy-loaded. Base path from `VITE_ROUTER_BASE`.

**Every routed view wraps in `<AppLayout>` or `<PublicLayout>`** (Rule 13). `AppLayout` gives navbar, preview banner, `max-w-7xl` content slot, footer and info panel.

## Vuex

All modules `namespaced: true`. State is `{ items, loading, error }`; mutations are `set*` / `add*` / `update*` / `remove*`; actions are async, commit mutations, and rethrow after committing the error.

```js
async fetchItems({ commit }) {
  commit('setLoading', true);
  try {
    commit('setItems', (await myService.getItems()).data.items);
  } catch (error) {
    commit('setError', error.message);
    throw error;
  } finally {
    commit('setLoading', false);
  }
}
dispatch('netWorth/refreshNetWorth', null, { root: true });   // cross-module
```

**Action names use British spelling:** `analyse`, `optimise`.

## Mixins — use these, never local equivalents

- **`currencyMixin`** (Rule 5): `formatCurrency()`, `formatCurrencyWithPence()`, `formatCurrencyCompact()`, `parseCurrency()`, `formatPercentage()`, `formatAccountType()`, `formatOwnershipType()`, `formatNumber()`, `formatLiability()`.
- **`previewModeMixin`**: `isPreviewMode`, `previewGuard(action)`, `getPreviewButtonProps(type)`, `handlePreviewAction()`, `canOpenModal()`.

## Utilities and constants

| File | Key exports |
|---|---|
| `utils/currency.js` | `formatCurrency`, `formatCurrencyWithPence`, `formatCurrencyCompact`, `parseCurrency` |
| `utils/dateFormatter.js` | `formatDate` (DD/MM/YYYY), `formatDateForInput` (YYYY-MM-DD), `calculateAge`, `getTaxYearStart/End` |
| `utils/ownership.js` | `calculateUserShare`, `isSharedOwnership`, `OWNERSHIP_TYPES`, `getOwnershipLabel` |
| `utils/poller.js` | `poll`, `pollMonteCarloJob` — long-running async operations |
| `utils/logger.js` | `logger.info/warn/error/debug` — development only |
| `constants/designSystem.js` | `CHART_COLORS`, `ASSET_COLORS`, and the palette constants. **All chart colour comes from here** (Rule 11) |
| `constants/taxConfig.js` | Frontend tax references. Prefer the backend `TaxConfigService` for anything calculated (Rule 2) |

## API services

Pure wrappers in `services/`, no state:

```js
async getData() { return (await api.get('/endpoint')).data; }
```

`api.js` provides CSRF injection, auth token from `tokenStorage` (async-ready over sessionStorage/native storage), retry with exponential backoff on 5xx and 429, and preview-mode detection.

## Components

**Views** (`views/`) are route-level: module init and data fetching, wrapped in a layout. **Components** (`components/{Module}/`) are reusable parts organised by module.

PascalCase filenames, multi-word, `name` matching the filename. Suffixes: `*Modal`, `*Chart`, `*List`, `*Card`.

Standard order: `mixins: [currencyMixin, previewModeMixin]`, typed `props`, `data()` returning `{ formData, errors, loading }`, `computed` with `mapGetters`, `methods` with `mapActions`.

**Form modals emit `save`, not `submit`** (Rule 3) — `<form @submit.prevent="handleSubmit">` then `this.$emit('save', formData)`. The parent makes the API call, closes on success and **keeps the modal open on error**.

**`v-preview-disabled`** blocks interaction in preview mode: `<button v-preview-disabled="'delete'">`.

## Mobile is not in this directory

`/m` is **`resources/mobile/`**, an isolated bundle with its own router, store and API client — **nothing here is shared with it, so a fix here does not reach `/m`** (Rule 19). Native iOS is `ios-native/`.

The one exception is `store/modules/auth.js`: mobile logout calls `auth/mobileLogout`, which clears local state only. **Never call `auth/logout` from a mobile client** — it revokes the server token and breaks Face ID.
