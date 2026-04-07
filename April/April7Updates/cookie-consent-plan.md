# Cookie Consent Banner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gate Google Analytics behind cookie consent with a bottom-centre overlay banner, warning on decline, and registration block without cookies.

**Architecture:** New `cookieConsent.js` utility manages localStorage state and dynamic GA script injection. `CookieBanner.vue` component mounted in `App.vue` handles UI. GA script removed from blade template. Register page checks consent before showing form.

**Tech Stack:** Vue 3, localStorage, Tailwind CSS (Fynla design system tokens)

---

### Task 1: Create cookieConsent.js utility

**Files:**
- Create: `resources/js/utils/cookieConsent.js`

- [ ] **Step 1: Create the utility**

```javascript
// resources/js/utils/cookieConsent.js

const STORAGE_KEY = 'cookie_consent';
const GA_ID = import.meta.env.VITE_GA_ID || 'G-3Y8DL3QB09';

let gaLoaded = false;

/**
 * Get current consent status.
 * @returns {'accepted'|'declined'|null}
 */
export function getConsentStatus() {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

/**
 * Whether the user has accepted cookies.
 */
export function hasConsent() {
  return getConsentStatus() === 'accepted';
}

/**
 * Accept cookies — store preference and load Google Analytics.
 */
export function acceptCookies() {
  try {
    localStorage.setItem(STORAGE_KEY, 'accepted');
  } catch {
    // localStorage unavailable — proceed anyway
  }
  loadGoogleAnalytics();
}

/**
 * Decline cookies — store preference, do not load GA.
 */
export function declineCookies() {
  try {
    localStorage.setItem(STORAGE_KEY, 'declined');
  } catch {
    // localStorage unavailable
  }
}

/**
 * Reset consent — removes the stored preference.
 * Banner will show again on next page load.
 */
export function resetConsent() {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    // localStorage unavailable
  }
  gaLoaded = false;
}

/**
 * Dynamically inject Google Analytics gtag script.
 * Safe to call multiple times — only loads once.
 */
function loadGoogleAnalytics() {
  if (gaLoaded || !GA_ID) return;

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_ID}`;
  document.head.appendChild(script);

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', GA_ID);

  gaLoaded = true;
}

/**
 * Initialise — if user previously accepted, load GA on page load.
 * Called from app.js or App.vue on mount.
 */
export function initCookieConsent() {
  if (hasConsent()) {
    loadGoogleAnalytics();
  }
}
```

- [ ] **Step 2: Add VITE_GA_ID to env files**

Add to `.env` and `.env.example`:
```
VITE_GA_ID=G-3Y8DL3QB09
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/utils/cookieConsent.js .env.example
git commit -m "feat: add cookieConsent utility for GA gating"
```

---

### Task 2: Create CookieBanner.vue component

**Files:**
- Create: `resources/js/components/Shared/CookieBanner.vue`

- [ ] **Step 1: Create the component**

```vue
<template>
  <div v-if="visible" class="fixed inset-0 z-[100] flex items-end justify-center pb-8 px-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/30" />

    <!-- Banner card -->
    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 space-y-4">
      <!-- Initial state -->
      <template v-if="!showWarning">
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <div>
            <h3 class="text-body-sm font-bold text-horizon-500">Cookie Preferences</h3>
            <p class="text-body-sm text-neutral-500 mt-1">
              We use cookies to help analyse how you use our site. You can accept or decline.
              <router-link to="/privacy" class="text-raspberry-500 hover:underline">Privacy Policy</router-link>
            </p>
          </div>
        </div>
        <div class="flex gap-3">
          <button
            class="flex-1 px-4 py-2.5 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
            @click="handleAccept"
          >
            Accept Cookies
          </button>
          <button
            class="flex-1 px-4 py-2.5 rounded-lg border border-light-gray text-neutral-500 text-body-sm font-medium hover:bg-eggshell-500 transition-colors"
            @click="showWarning = true"
          >
            Decline Cookies
          </button>
        </div>
      </template>

      <!-- Warning state (after clicking Decline) -->
      <template v-else>
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <h3 class="text-body-sm font-bold text-horizon-500">Limited Functionality</h3>
            <p class="text-body-sm text-neutral-500 mt-1">
              Without cookies, some features including registration will be unavailable. Google Analytics has been disabled.
            </p>
          </div>
        </div>
        <div class="flex gap-3">
          <button
            class="flex-1 px-4 py-2.5 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
            @click="handleAccept"
          >
            Accept Cookies
          </button>
          <button
            class="flex-1 px-4 py-2.5 rounded-lg border border-light-gray text-neutral-500 text-body-sm font-medium hover:bg-eggshell-500 transition-colors"
            @click="handleDecline"
          >
            Continue Without Cookies
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { getConsentStatus, acceptCookies, declineCookies } from '@/utils/cookieConsent';

export default {
  name: 'CookieBanner',

  setup() {
    const visible = ref(false);
    const showWarning = ref(false);

    onMounted(() => {
      // Only show if no choice has been made yet
      visible.value = getConsentStatus() === null;
    });

    const handleAccept = () => {
      acceptCookies();
      visible.value = false;
    };

    const handleDecline = () => {
      declineCookies();
      visible.value = false;
    };

    return { visible, showWarning, handleAccept, handleDecline };
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Shared/CookieBanner.vue
git commit -m "feat: add CookieBanner component with accept/decline/warning states"
```

---

### Task 3: Mount banner in App.vue and initialise consent

**Files:**
- Modify: `resources/js/App.vue`

- [ ] **Step 1: Import and mount CookieBanner, call initCookieConsent**

Add the import and component registration:
```javascript
import CookieBanner from '@/components/Shared/CookieBanner.vue';
import { initCookieConsent } from '@/utils/cookieConsent';
```

Add `CookieBanner` to components. Add to template:
```html
<template>
  <router-view />
  <CookieBanner />
</template>
```

Add `initCookieConsent()` call in `onMounted`:
```javascript
onMounted(async () => {
  // Initialise cookie consent — loads GA if previously accepted
  initCookieConsent();

  // ... existing auth code ...
});
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/App.vue
git commit -m "feat: mount CookieBanner in App.vue, init consent on load"
```

---

### Task 4: Remove inline GA script from blade template

**Files:**
- Modify: `resources/views/app.blade.php`

- [ ] **Step 1: Remove the hardcoded gtag script block (lines 4-11)**

Remove:
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3Y8DL3QB09"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-3Y8DL3QB09');
</script>
```

Replace with a comment:
```html
<!-- Google Analytics loaded dynamically via cookieConsent.js (requires user consent) -->
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/app.blade.php
git commit -m "fix: remove hardcoded GA script, now gated behind cookie consent"
```

---

### Task 5: Add consent gate to Register.vue

**Files:**
- Modify: `resources/js/views/Register.vue`

- [ ] **Step 1: Add consent check and gate UI**

Import the utility at the top of the script:
```javascript
import { hasConsent, acceptCookies } from '@/utils/cookieConsent';
```

Add reactive state in setup/data:
```javascript
const cookiesAccepted = ref(hasConsent());

const handleAcceptCookiesForRegistration = () => {
  acceptCookies();
  cookiesAccepted.value = true;
};
```

Add a consent gate card above the registration form in the template (inside the `auth-card` div, after the beta warning, before the form):

```html
<!-- Cookie Consent Required for Registration -->
<div v-if="!cookiesAccepted" class="mt-4 bg-violet-50 border-2 border-violet-300 rounded-lg p-5">
  <div class="flex items-start gap-3">
    <svg class="w-5 h-5 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
    <div>
      <p class="text-body-sm font-semibold text-horizon-500">Cookies Required</p>
      <p class="text-body-sm text-neutral-500 mt-1">
        Cookies are required to create an account. They allow us to keep you securely signed in.
      </p>
      <button
        class="mt-3 px-5 py-2 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
        @click="handleAcceptCookiesForRegistration"
      >
        Accept Cookies & Continue
      </button>
    </div>
  </div>
</div>
```

Wrap the existing registration form with `v-if="cookiesAccepted"` so it's hidden until consent is given.

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/Register.vue
git commit -m "feat: gate registration behind cookie consent"
```

---

### Task 6: Build, test, and final commit

**Files:**
- Build: `./deploy/fynla-org/build.sh`

- [ ] **Step 1: Build for production**

```bash
./deploy/fynla-org/build.sh
```

Verify no build errors.

- [ ] **Step 2: Local browser test**

1. Clear localStorage (`localStorage.removeItem('cookie_consent')`)
2. Visit `http://localhost:8000` — banner should appear at bottom centre
3. Click "Decline Cookies" — warning state should show
4. Click "Continue Without Cookies" — banner dismisses, no GA in network tab
5. Visit `/register` — consent gate card should show, form hidden
6. Click "Accept Cookies & Continue" — form should appear
7. Reload page — banner should NOT reappear (localStorage persisted)
8. Check network tab — gtag script should now be loaded

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: cookie consent banner with GA gating and registration block"
git push
```
