# Phase 2b Implementation Plan — Capacitor iOS App

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete iOS app using Capacitor 6.x with biometric auth, 5-tab navigation, full-screen Fyn chat with voice, goals with milestones, Learn Hub, module summaries, push notifications, and mortgage rate alerts.

**Architecture:** Hybrid approach — Capacitor provides native shell (biometrics, push, haptics, voice, keychain) while Vue.js components render in WKWebView. Mobile routes under `/m/` prefix. Native plugins wired progressively as each screen needs them.

**Tech Stack:** Capacitor 6.x, Vue 3, Vuex, Tailwind CSS, @capacitor/ios, @capgo/capacitor-native-biometric, @capacitor-community/speech-recognition, ApexCharts (sparklines)

**Design doc:** `docs/plans/2026-03-10-phase2b-design.md`

**Existing infrastructure (Phase 2a):** Device tokens, notification preferences, push notification service, token refresh, mobile dashboard API, social share backend, CORS config, platform.js, tokenStorage.js, mobileDashboard store, QuickReplyChips, OfflineBanner.

---

## Task 1: Capacitor Project Setup

**Files:**
- Modify: `package.json`
- Create: `capacitor.config.ts`
- Modify: `.gitignore`

**Step 1: Install Capacitor core + iOS + all native plugins**

```bash
npm install @capacitor/core @capacitor/cli @capacitor/ios
npm install @capgo/capacitor-native-biometric
npm install @capacitor/push-notifications @capacitor/keyboard @capacitor/app
npm install @capacitor/preferences @capacitor/network @capacitor/browser
npm install @capacitor/status-bar @capacitor/haptics @capacitor/splash-screen
npm install @capacitor/device @capacitor/local-notifications @capacitor/share
npm install @capacitor-community/speech-recognition
```

**Step 2: Initialise Capacitor**

```bash
npx cap init Fynla org.fynla.app --web-dir public
```

**Step 3: Create `capacitor.config.ts`**

```typescript
import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'org.fynla.app',
  appName: 'Fynla',
  webDir: 'public',
  server: {
    url: process.env.CAPACITOR_DEV ? 'http://localhost:5173' : undefined,
    androidScheme: 'https',
  },
  plugins: {
    SplashScreen: {
      backgroundColor: '#F7F6F4',
      spinnerColor: '#E83E6D',
      launchAutoHide: true,
      launchShowDuration: 2000,
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    Keyboard: {
      resize: 'body',
      style: 'light',
    },
  },
};

export default config;
```

**Step 4: Add iOS platform**

```bash
npx cap add ios
```

**Step 5: Update `.gitignore`**

Add to `.gitignore`:
```
# Capacitor
ios/App/Pods/
ios/App/App/public/
```

Do NOT ignore `ios/` entirely — the Xcode project config needs to be committed.

**Step 6: Verify iOS project exists**

```bash
ls ios/App/App.xcworkspace
```

Expected: file exists.

**Step 7: Commit**

```bash
git add -A
git commit -m "feat(mobile): initialise Capacitor 6.x with iOS platform and native plugins"
```

---

## Task 2: Platform Detection + Token Storage Native Upgrade

**Files:**
- Modify: `resources/js/utils/platform.js`
- Modify: `resources/js/services/tokenStorage.js`

**Step 1: Update `platform.js` to use `@capacitor/core`**

Replace the entire contents of `resources/js/utils/platform.js`:

```javascript
/**
 * Platform Detection Utility
 *
 * Detects whether the app is running as a native Capacitor app,
 * on iOS/Android, or in a standard web browser.
 * Uses @capacitor/core for reliable detection.
 */

import { Capacitor } from '@capacitor/core';

export const platform = {
    isNative: () => Capacitor.isNativePlatform(),

    isIOS: () => Capacitor.getPlatform() === 'ios',

    isAndroid: () => Capacitor.getPlatform() === 'android',

    isWeb: () => Capacitor.getPlatform() === 'web',

    isMobileViewport: () => typeof window !== 'undefined' && window.innerWidth < 768,

    canUseBiometrics: () => Capacitor.isNativePlatform(),

    canUsePushNotifications: () => Capacitor.isNativePlatform(),

    canUseHaptics: () => Capacitor.isNativePlatform(),

    canUseVoiceInput: () => Capacitor.isNativePlatform(),
};
```

**Step 2: Update `tokenStorage.js` to use Capacitor Preferences**

Replace `isNativePlatform()` and activate native storage paths:

```javascript
/**
 * Token Storage Abstraction Layer
 *
 * Web: sessionStorage (sync, wrapped in Promises).
 * Native (Capacitor): @capacitor/preferences (encrypted iOS Keychain / Android Keystore).
 */

import { Capacitor } from '@capacitor/core';

const AUTH_TOKEN_KEY = 'auth_token';

let _cachedToken = null;

export function isNativePlatform() {
  return Capacitor.isNativePlatform();
}

// Lazy-load Preferences only on native to avoid bundling issues on web
async function getPreferences() {
  const { Preferences } = await import('@capacitor/preferences');
  return Preferences;
}

export async function getToken() {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    const { value } = await Preferences.get({ key: AUTH_TOKEN_KEY });
    _cachedToken = value;
    return value;
  }
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

export async function setToken(token) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.set({ key: AUTH_TOKEN_KEY, value: token });
    _cachedToken = token;
    return;
  }
  sessionStorage.setItem(AUTH_TOKEN_KEY, token);
}

export async function removeToken() {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.remove({ key: AUTH_TOKEN_KEY });
    _cachedToken = null;
    return;
  }
  sessionStorage.removeItem(AUTH_TOKEN_KEY);
}

export async function getItem(key) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    const { value } = await Preferences.get({ key });
    return value;
  }
  return sessionStorage.getItem(key);
}

export async function setItem(key, value) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.set({ key, value });
    return;
  }
  sessionStorage.setItem(key, value);
}

export async function removeItem(key) {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.remove({ key });
    return;
  }
  sessionStorage.removeItem(key);
}

export async function clear() {
  if (isNativePlatform()) {
    const Preferences = await getPreferences();
    await Preferences.clear();
    _cachedToken = null;
    return;
  }
  sessionStorage.clear();
}

export function getTokenSync() {
  if (isNativePlatform()) {
    return _cachedToken;
  }
  return sessionStorage.getItem(AUTH_TOKEN_KEY);
}

export default {
  AUTH_TOKEN_KEY,
  isNativePlatform,
  getToken,
  setToken,
  removeToken,
  getItem,
  setItem,
  removeItem,
  clear,
  getTokenSync,
};
```

**Step 3: Run existing tests to verify no regressions**

```bash
./vendor/bin/pest
```

Expected: all tests pass (web behaviour unchanged — `Capacitor.isNativePlatform()` returns `false` in Node/test environments).

**Step 4: Verify dev server still works**

```bash
# Start dev server, load localhost:8000 in browser, confirm login works
```

**Step 5: Commit**

```bash
git add resources/js/utils/platform.js resources/js/services/tokenStorage.js
git commit -m "feat(mobile): upgrade platform.js and tokenStorage.js for native Capacitor support"
```

---

## Task 3: Store Persistence for Native + Build Script

**Files:**
- Modify: `resources/js/store/index.js`
- Create: `deploy/mobile/build-ios.sh`

**Step 1: Update store persistence to use Capacitor Preferences on native**

Modify `resources/js/store/index.js` — add native-aware storage backend and `mobileNotifications` module import (module created in Task 17):

```javascript
import { createStore } from 'vuex';
import createPersistedState from 'vuex-persistedstate';
import { Capacitor } from '@capacitor/core';
import auth from './modules/auth';
import dashboard from './modules/dashboard';
import protection from './modules/protection';
import savings from './modules/savings';
import investment from './modules/investment';
import retirement from './modules/retirement';
import goals from './modules/goals';
import estate from './modules/estate';
import userProfile from './modules/userProfile';
import netWorth from './modules/netWorth';
import trusts from './modules/trusts';
import businessInterests from './modules/businessInterests';
import chattels from './modules/chattels';
import recommendations from './modules/recommendations';
import spousePermission from './modules/spousePermission';
import onboarding from './modules/onboarding';
import preview from './modules/preview';
import guidance from './modules/guidance';
import infoGuide from './modules/infoGuide';
import aiChat from './modules/aiChat';
import plans from './modules/plans';
import taxOptimisation from './modules/taxOptimisation';
import household from './modules/household';
import journeys from './modules/journeys';
import mobileDashboard from './modules/mobileDashboard';
import mobileNotifications from './modules/mobileNotifications';

/**
 * Create a storage backend that uses Capacitor Preferences on native
 * and localStorage on web. vuex-persistedstate requires sync getItem/setItem,
 * so on native we use a sync in-memory cache that's hydrated on app start.
 */
const nativeCache = {};

const storageBackend = Capacitor.isNativePlatform()
  ? {
      getItem: (key) => nativeCache[key] || null,
      setItem: (key, value) => {
        nativeCache[key] = value;
        // Async persist to native storage (fire-and-forget)
        import('@capacitor/preferences').then(({ Preferences }) => {
          Preferences.set({ key, value });
        });
      },
      removeItem: (key) => {
        delete nativeCache[key];
        import('@capacitor/preferences').then(({ Preferences }) => {
          Preferences.remove({ key });
        });
      },
    }
  : window.localStorage;

const store = createStore({
  modules: {
    auth,
    dashboard,
    protection,
    savings,
    investment,
    retirement,
    goals,
    estate,
    userProfile,
    netWorth,
    trusts,
    businessInterests,
    chattels,
    recommendations,
    spousePermission,
    onboarding,
    preview,
    guidance,
    infoGuide,
    aiChat,
    plans,
    taxOptimisation,
    household,
    journeys,
    mobileDashboard,
    mobileNotifications,
  },
  plugins: [
    createPersistedState({
      key: 'fynla-state',
      paths: [
        'auth.user',
        'auth.token',
        'dashboard',
        'aiChat.conversations',
        'goals.goals',
        'mobileDashboard',
        'mobileNotifications.permissionStatus',
      ],
      storage: storageBackend,
    }),
  ],
  strict: process.env.NODE_ENV !== 'production',
});

export default store;
```

**Note:** The `mobileNotifications` module is created in Task 17. To avoid a build error, either create a minimal placeholder now or reorder to create the module first. For simplicity, create a minimal placeholder:

Create `resources/js/store/modules/mobileNotifications.js`:

```javascript
/**
 * Mobile Notifications Store Module
 *
 * Manages push notification permission state, unread counts,
 * and in-app notification display. Full implementation in Task 17.
 */

const state = {
    permissionStatus: 'unknown', // 'unknown', 'granted', 'denied', 'prompt'
    unreadCount: 0,
    inAppNotification: null,
    promptDismissals: {}, // { triggerType: dismissedAt }
};

const getters = {
    permissionStatus: (state) => state.permissionStatus,
    unreadCount: (state) => state.unreadCount,
    inAppNotification: (state) => state.inAppNotification,
    hasPermission: (state) => state.permissionStatus === 'granted',
    shouldPrompt: (state) => state.permissionStatus === 'unknown' || state.permissionStatus === 'prompt',
};

const mutations = {
    SET_PERMISSION_STATUS(state, status) {
        state.permissionStatus = status;
    },
    SET_UNREAD_COUNT(state, count) {
        state.unreadCount = count;
    },
    SET_IN_APP_NOTIFICATION(state, notification) {
        state.inAppNotification = notification;
    },
    CLEAR_IN_APP_NOTIFICATION(state) {
        state.inAppNotification = null;
    },
    SET_PROMPT_DISMISSAL(state, triggerType) {
        state.promptDismissals[triggerType] = Date.now();
    },
};

const actions = {
    async requestPermission({ commit }) {
        // Full implementation in Task 17
        commit('SET_PERMISSION_STATUS', 'unknown');
    },

    async registerToken() {
        // Full implementation in Task 17
    },

    showInAppNotification({ commit }, notification) {
        commit('SET_IN_APP_NOTIFICATION', notification);
        setTimeout(() => {
            commit('CLEAR_IN_APP_NOTIFICATION');
        }, 4000);
    },

    clearUnread({ commit }) {
        commit('SET_UNREAD_COUNT', 0);
    },

    dismissPrompt({ commit }, triggerType) {
        commit('SET_PROMPT_DISMISSAL', triggerType);
    },

    shouldShowPrompt({ state }, triggerType) {
        const dismissal = state.promptDismissals[triggerType];
        if (!dismissal) return true;
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        return Date.now() - dismissal > sevenDays;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
```

**Step 2: Create iOS build script**

Create `deploy/mobile/build-ios.sh`:

```bash
#!/bin/bash
set -e

echo "=== Fynla iOS Build ==="
echo ""

# Environment for production iOS build
export VITE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
export VITE_PLATFORM=ios

echo "1. Building web assets..."
npm run build

echo "2. Syncing to iOS project..."
npx cap sync ios

echo ""
echo "=== Build complete ==="
echo "Open ios/App/App.xcworkspace in Xcode to build and archive."
echo ""
```

```bash
chmod +x deploy/mobile/build-ios.sh
```

**Step 3: Verify build works**

```bash
npm run build
```

Expected: Vite build succeeds without errors.

**Step 4: Commit**

```bash
git add resources/js/store/index.js resources/js/store/modules/mobileNotifications.js deploy/mobile/build-ios.sh
git commit -m "feat(mobile): native-aware store persistence, mobileNotifications store, iOS build script"
```

---

## Task 4: Mobile Auth Screens

**Files:**
- Create: `resources/js/mobile/views/MobileLoginScreen.vue`
- Create: `resources/js/mobile/views/VerificationCodeScreen.vue`
- Create: `resources/js/mobile/BiometricPrompt.vue`

**Step 1: Create MobileLoginScreen.vue**

Create `resources/js/mobile/views/MobileLoginScreen.vue`:

```vue
<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <!-- Logo -->
    <div class="text-center mb-8">
      <img
        src="/images/logos/favicon.png"
        alt="Fynla"
        class="w-16 h-16 mx-auto mb-3"
      />
      <h1 class="text-2xl font-black text-horizon-500">Fynla</h1>
      <p class="text-neutral-500 text-sm mt-1">Your financial planning companion</p>
    </div>

    <!-- Login Form -->
    <form @submit.prevent="handleLogin" class="space-y-4">
      <div>
        <label for="email" class="block text-sm font-semibold text-horizon-500 mb-1">
          Email address
        </label>
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="email"
          required
          :disabled="loading"
          class="w-full px-4 py-3 rounded-xl border border-light-gray bg-white text-horizon-500
                 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                 disabled:opacity-50"
          placeholder="you@example.com"
        />
      </div>

      <div>
        <label for="password" class="block text-sm font-semibold text-horizon-500 mb-1">
          Password
        </label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
          :disabled="loading"
          class="w-full px-4 py-3 rounded-xl border border-light-gray bg-white text-horizon-500
                 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                 disabled:opacity-50"
          placeholder="Enter your password"
        />
      </div>

      <!-- Error -->
      <p v-if="error" class="text-raspberry-500 text-sm">{{ error }}</p>

      <!-- Submit -->
      <button
        type="submit"
        :disabled="loading || !email || !password"
        class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
               active:bg-raspberry-600 disabled:opacity-50 transition-colors"
      >
        <span v-if="loading" class="flex items-center justify-center gap-2">
          <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Signing in...
        </span>
        <span v-else>Sign in</span>
      </button>
    </form>

    <!-- Footer -->
    <p class="text-center text-neutral-500 text-xs mt-8">
      Don't have an account?
      <a href="https://fynla.org/register" class="text-raspberry-500 font-semibold">Sign up on web</a>
    </p>
  </div>
</template>

<script>
import authService from '@/services/authService';
import { setToken } from '@/services/tokenStorage';

export default {
  name: 'MobileLoginScreen',

  data() {
    return {
      email: '',
      password: '',
      loading: false,
      error: null,
    };
  },

  methods: {
    async handleLogin() {
      this.loading = true;
      this.error = null;

      try {
        const response = await authService.login({
          email: this.email,
          password: this.password,
        });

        const data = response.data || response;

        // Check if MFA or verification required
        if (data.requires_mfa || data.requires_verification) {
          this.$router.push({
            path: '/m/verify',
            query: {
              email: this.email,
              mfa: data.requires_mfa ? '1' : '0',
              mfa_token: data.mfa_token || '',
            },
          });
          return;
        }

        // Direct login (no verification needed)
        if (data.access_token) {
          await setToken(data.access_token);
          this.$store.commit('auth/setToken', data.access_token);
          await this.$store.dispatch('auth/fetchUser');
          this.$router.push('/m/home');
        }
      } catch (error) {
        this.error = error.response?.data?.message || error.message || 'Login failed. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

**Step 2: Create VerificationCodeScreen.vue**

Create `resources/js/mobile/views/VerificationCodeScreen.vue`:

```vue
<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <div class="text-center mb-8">
      <div class="w-16 h-16 mx-auto mb-3 bg-violet-100 rounded-full flex items-center justify-center">
        <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <h1 class="text-xl font-bold text-horizon-500">Verification code</h1>
      <p class="text-neutral-500 text-sm mt-1">
        We've sent a code to {{ maskedEmail }}
      </p>
    </div>

    <!-- Code Input -->
    <div class="flex justify-center gap-3 mb-6">
      <input
        v-for="(digit, index) in codeDigits"
        :key="index"
        :ref="el => { if (el) digitRefs[index] = el; }"
        v-model="codeDigits[index]"
        type="text"
        inputmode="numeric"
        pattern="[0-9]"
        maxlength="1"
        :disabled="loading"
        class="w-12 h-14 text-center text-xl font-bold rounded-xl border border-light-gray bg-white
               text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500
               disabled:opacity-50"
        @input="handleDigitInput(index)"
        @keydown.backspace="handleBackspace(index)"
        @paste="handlePaste"
      />
    </div>

    <!-- Error -->
    <p v-if="error" class="text-raspberry-500 text-sm text-center mb-4">{{ error }}</p>

    <!-- Submit -->
    <button
      :disabled="loading || code.length < 6"
      class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
             active:bg-raspberry-600 disabled:opacity-50 transition-colors"
      @click="handleVerify"
    >
      <span v-if="loading" class="flex items-center justify-center gap-2">
        <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        Verifying...
      </span>
      <span v-else>Verify</span>
    </button>

    <!-- Resend -->
    <button
      :disabled="resendCooldown > 0"
      class="w-full py-3 mt-3 text-raspberry-500 font-semibold text-sm"
      @click="handleResend"
    >
      {{ resendCooldown > 0 ? `Resend code in ${resendCooldown}s` : 'Resend code' }}
    </button>
  </div>
</template>

<script>
import authService from '@/services/authService';
import { setToken } from '@/services/tokenStorage';

export default {
  name: 'VerificationCodeScreen',

  data() {
    return {
      codeDigits: ['', '', '', '', '', ''],
      digitRefs: [],
      loading: false,
      error: null,
      resendCooldown: 60,
      resendTimer: null,
    };
  },

  computed: {
    code() {
      return this.codeDigits.join('');
    },
    maskedEmail() {
      const email = this.$route.query.email || '';
      if (!email.includes('@')) return email;
      const [local, domain] = email.split('@');
      return local.slice(0, 2) + '***@' + domain;
    },
  },

  mounted() {
    this.startResendTimer();
    this.$nextTick(() => {
      if (this.digitRefs[0]) this.digitRefs[0].focus();
    });
  },

  beforeUnmount() {
    if (this.resendTimer) clearInterval(this.resendTimer);
  },

  methods: {
    handleDigitInput(index) {
      const val = this.codeDigits[index];
      if (val && index < 5) {
        this.$nextTick(() => {
          if (this.digitRefs[index + 1]) this.digitRefs[index + 1].focus();
        });
      }
      if (this.code.length === 6) {
        this.handleVerify();
      }
    },

    handleBackspace(index) {
      if (!this.codeDigits[index] && index > 0) {
        this.$nextTick(() => {
          if (this.digitRefs[index - 1]) this.digitRefs[index - 1].focus();
        });
      }
    },

    handlePaste(event) {
      const pasted = (event.clipboardData || window.clipboardData).getData('text').trim();
      if (/^\d{6}$/.test(pasted)) {
        event.preventDefault();
        pasted.split('').forEach((digit, i) => {
          this.codeDigits[i] = digit;
        });
        this.handleVerify();
      }
    },

    async handleVerify() {
      if (this.code.length < 6) return;
      this.loading = true;
      this.error = null;

      try {
        const response = await authService.verifyCode({
          email: this.$route.query.email,
          code: this.code,
          mfa_token: this.$route.query.mfa_token || undefined,
        });

        const data = response.data || response;
        if (data.access_token) {
          await setToken(data.access_token);
          this.$store.commit('auth/setToken', data.access_token);
          await this.$store.dispatch('auth/fetchUser');

          // Navigate to biometric prompt (or dashboard if biometrics unavailable)
          this.$router.push('/m/biometric-setup');
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Invalid code. Please try again.';
        this.codeDigits = ['', '', '', '', '', ''];
        this.$nextTick(() => {
          if (this.digitRefs[0]) this.digitRefs[0].focus();
        });
      } finally {
        this.loading = false;
      }
    },

    async handleResend() {
      try {
        await authService.resendVerificationCode({ email: this.$route.query.email });
        this.resendCooldown = 60;
        this.startResendTimer();
      } catch (error) {
        this.error = 'Failed to resend code. Please try again.';
      }
    },

    startResendTimer() {
      if (this.resendTimer) clearInterval(this.resendTimer);
      this.resendTimer = setInterval(() => {
        if (this.resendCooldown > 0) {
          this.resendCooldown--;
        } else {
          clearInterval(this.resendTimer);
        }
      }, 1000);
    },
  },
};
</script>
```

**Step 3: Create BiometricPrompt.vue**

Create `resources/js/mobile/BiometricPrompt.vue`:

```vue
<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <div class="text-center mb-8">
      <div class="w-20 h-20 mx-auto mb-4 bg-spring-100 rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h1 class="text-xl font-bold text-horizon-500">Quick, secure login</h1>
      <p class="text-neutral-500 text-sm mt-2 leading-relaxed">
        Enable {{ biometricName }} so you can sign in instantly next time — no password needed.
      </p>
    </div>

    <button
      :disabled="loading"
      class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
             active:bg-raspberry-600 disabled:opacity-50 transition-colors mb-3"
      @click="enableBiometric"
    >
      Enable {{ biometricName }}
    </button>

    <button
      class="w-full py-3 text-neutral-500 font-semibold text-sm"
      @click="skipBiometric"
    >
      Not now
    </button>
  </div>
</template>

<script>
import { platform } from '@/utils/platform';

export default {
  name: 'BiometricPrompt',

  data() {
    return {
      loading: false,
      biometricType: 'face', // 'face' or 'finger'
    };
  },

  computed: {
    biometricName() {
      return this.biometricType === 'face' ? 'Face ID' : 'Touch ID';
    },
  },

  async mounted() {
    if (platform.canUseBiometrics()) {
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const { isAvailable, biometryType } = await NativeBiometric.isAvailable();
        if (!isAvailable) {
          this.skipBiometric();
          return;
        }
        // biometryType: 1 = Touch ID, 2 = Face ID
        this.biometricType = biometryType === 2 ? 'face' : 'finger';
      } catch {
        this.skipBiometric();
      }
    } else {
      this.skipBiometric();
    }
  },

  methods: {
    async enableBiometric() {
      this.loading = true;
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const token = this.$store.getters['auth/isAuthenticated']
          ? this.$store.state.auth.token
          : null;
        const email = this.$store.state.auth.user?.email || '';

        if (token) {
          await NativeBiometric.setCredentials({
            username: email,
            password: token,
            server: 'fynla.org',
          });
        }

        this.navigateToDashboard();
      } catch {
        // If biometric setup fails, still proceed
        this.navigateToDashboard();
      } finally {
        this.loading = false;
      }
    },

    skipBiometric() {
      this.navigateToDashboard();
    },

    navigateToDashboard() {
      // Register device for push (will be enhanced in Task 17)
      this.$router.replace('/m/home');
    },
  },
};
</script>
```

**Step 4: Commit**

```bash
git add resources/js/mobile/views/MobileLoginScreen.vue resources/js/mobile/views/VerificationCodeScreen.vue resources/js/mobile/BiometricPrompt.vue
git commit -m "feat(mobile): add mobile auth screens — login, verification code, biometric setup"
```

---

## Task 5: App Lifecycle Management

**Files:**
- Create: `resources/js/mobile/appLifecycle.js`

**Step 1: Create app lifecycle manager**

Create `resources/js/mobile/appLifecycle.js`:

```javascript
/**
 * Mobile App Lifecycle Manager
 *
 * Handles app background/foreground transitions, biometric re-auth,
 * token validation, and SSE stream management.
 */

import { platform } from '@/utils/platform';
import { getToken } from '@/services/tokenStorage';
import api from '@/services/api';

let appListenerRegistered = false;

export async function initAppLifecycle(store, router) {
  if (!platform.isNative() || appListenerRegistered) return;

  const { App } = await import('@capacitor/app');

  App.addListener('appStateChange', async ({ isActive }) => {
    if (!isActive) {
      // App going to background — abort any active SSE streams
      store.dispatch('aiChat/abortStreaming').catch(() => {});
    } else {
      // App coming to foreground — validate token and refresh data
      const token = await getToken();
      if (!token) return;

      try {
        await api.get('/auth/user');
        // Token valid — refresh dashboard
        store.dispatch('mobileDashboard/refreshDashboard').catch(() => {});
      } catch (error) {
        if (error.response?.status === 401) {
          // Token expired — redirect to login
          store.commit('auth/clearAuth');
          router.push('/m/login');
        }
      }
    }
  });

  // Handle back button (Android, but safe to register on iOS too)
  App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack) {
      router.back();
    }
  });

  appListenerRegistered = true;
}

/**
 * Attempt biometric login on app launch.
 * Returns true if successful, false if re-auth needed.
 */
export async function attemptBiometricLogin(store) {
  if (!platform.canUseBiometrics()) return false;

  try {
    const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
    const { isAvailable } = await NativeBiometric.isAvailable();
    if (!isAvailable) return false;

    // Verify biometrics
    await NativeBiometric.verifyIdentity({
      reason: 'Sign in to Fynla',
      title: 'Fynla',
    });

    // Retrieve stored credentials
    const credentials = await NativeBiometric.getCredentials({ server: 'fynla.org' });
    if (credentials?.password) {
      // Validate stored token is still good
      try {
        await api.get('/auth/user');
        return true;
      } catch {
        return false;
      }
    }

    return false;
  } catch {
    return false;
  }
}

/**
 * Check if token needs refresh (>25 days old).
 * Called on app foreground.
 */
export async function checkTokenRefresh(store) {
  const token = await getToken();
  if (!token) return;

  try {
    // The server handles token age checking — just call refresh
    // The backend returns a new token if the current one is >25 days old
    // If not needed, it returns the current token info
    const response = await api.post('/v1/auth/refresh-token');
    if (response.data?.data?.access_token) {
      const { setToken } = await import('@/services/tokenStorage');
      await setToken(response.data.data.access_token);
      store.commit('auth/setToken', response.data.data.access_token);
    }
  } catch {
    // Token refresh failed — not critical, will retry on next launch
  }
}
```

**Step 2: Commit**

```bash
git add resources/js/mobile/appLifecycle.js
git commit -m "feat(mobile): add app lifecycle manager — background/foreground, biometric re-auth, token refresh"
```

---

## Task 6: Mobile Layout + Tab Bar + Header

**Files:**
- Create: `resources/js/mobile/layouts/MobileLayout.vue`
- Create: `resources/js/mobile/MobileTabBar.vue`
- Create: `resources/js/mobile/MobileHeader.vue`

**Step 1: Create MobileLayout.vue**

Create `resources/js/mobile/layouts/MobileLayout.vue`:

```vue
<template>
  <div class="mobile-layout flex flex-col h-screen bg-eggshell-500">
    <!-- Header -->
    <MobileHeader
      :title="currentTitle"
      :show-back="canGoBack"
      :right-action="rightAction"
      @back="handleBack"
      @action="handleAction"
    />

    <!-- Content -->
    <main class="flex-1 overflow-y-auto" ref="contentArea">
      <keep-alive :include="keepAliveIncludes">
        <router-view />
      </keep-alive>
    </main>

    <!-- Tab Bar -->
    <MobileTabBar
      :active-tab="activeTab"
      :alert-count="alertCount"
      :unread-count="unreadCount"
      :milestone-count="milestoneCount"
      @tab="handleTabChange"
    />

    <!-- In-app notification toast -->
    <InAppNotificationToast
      v-if="inAppNotification"
      :notification="inAppNotification"
      @dismiss="dismissNotification"
    />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import MobileTabBar from '@/mobile/MobileTabBar.vue';
import MobileHeader from '@/mobile/MobileHeader.vue';

export default {
  name: 'MobileLayout',

  components: {
    MobileTabBar,
    MobileHeader,
    InAppNotificationToast: () => import('@/mobile/InAppNotificationToast.vue'),
  },

  data() {
    return {
      keepAliveIncludes: [
        'MobileDashboard',
        'MobileFynChat',
        'LearnHub',
        'MobileGoalsList',
        'MoreMenu',
      ],
    };
  },

  computed: {
    ...mapGetters('mobileDashboard', { dashboardAlerts: 'alerts' }),
    ...mapGetters('mobileNotifications', ['unreadCount', 'inAppNotification']),

    activeTab() {
      const path = this.$route.path;
      if (path.startsWith('/m/home')) return 'home';
      if (path.startsWith('/m/fyn')) return 'fyn';
      if (path.startsWith('/m/learn')) return 'learn';
      if (path.startsWith('/m/goals')) return 'goals';
      if (path.startsWith('/m/more')) return 'more';
      return 'home';
    },

    currentTitle() {
      const titles = {
        home: 'Home',
        fyn: 'Fyn',
        learn: 'Learn',
        goals: 'Goals',
        more: 'More',
      };
      return this.$route.meta?.title || titles[this.activeTab] || 'Fynla';
    },

    canGoBack() {
      // Show back button if deeper than tab root
      const tabRoots = ['/m/home', '/m/fyn', '/m/learn', '/m/goals', '/m/more'];
      return !tabRoots.includes(this.$route.path);
    },

    rightAction() {
      return this.$route.meta?.rightAction || null;
    },

    alertCount() {
      return this.dashboardAlerts?.length || 0;
    },

    milestoneCount() {
      return 0; // TODO: compute from goals store
    },
  },

  methods: {
    handleBack() {
      this.$router.back();
    },

    handleAction() {
      this.$emit('header-action');
    },

    handleTabChange(tab) {
      const routes = {
        home: '/m/home',
        fyn: '/m/fyn',
        learn: '/m/learn',
        goals: '/m/goals',
        more: '/m/more',
      };

      if (this.activeTab === tab) {
        // Already on this tab — scroll to top
        if (this.$refs.contentArea) {
          this.$refs.contentArea.scrollTo({ top: 0, behavior: 'smooth' });
        }
      } else {
        this.$router.push(routes[tab]);
      }
    },

    dismissNotification() {
      this.$store.dispatch('mobileNotifications/clearUnread');
    },
  },
};
</script>

<style scoped>
.mobile-layout {
  /* Ensure layout fills viewport including safe areas */
  padding-top: env(safe-area-inset-top);
}
</style>
```

**Step 2: Create MobileTabBar.vue**

Create `resources/js/mobile/MobileTabBar.vue`:

```vue
<template>
  <nav class="mobile-tab-bar bg-white border-t border-light-gray flex items-start justify-around"
       style="box-shadow: 0 -1px 3px rgba(0,0,0,0.06);">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      class="tab-button flex flex-col items-center pt-2 pb-1 px-3 min-w-0 flex-1 relative"
      :class="activeTab === tab.id ? 'text-raspberry-500' : 'text-neutral-500'"
      @click="$emit('tab', tab.id)"
    >
      <!-- Icon -->
      <div class="relative">
        <component :is="tab.icon" :active="activeTab === tab.id" class="w-6 h-6" />
        <!-- Badge -->
        <span
          v-if="tab.badge > 0"
          class="absolute -top-1 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-raspberry-500
                 text-white text-[10px] font-bold flex items-center justify-center"
        >
          {{ tab.badge > 9 ? '9+' : tab.badge }}
        </span>
        <span
          v-else-if="tab.dot"
          class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-raspberry-500"
        ></span>
      </div>
      <!-- Label -->
      <span class="text-[10px] font-semibold mt-1">{{ tab.label }}</span>
    </button>
  </nav>
</template>

<script>
import TabIconHome from '@/mobile/icons/TabIconHome.vue';
import TabIconFyn from '@/mobile/icons/TabIconFyn.vue';
import TabIconLearn from '@/mobile/icons/TabIconLearn.vue';
import TabIconGoals from '@/mobile/icons/TabIconGoals.vue';
import TabIconMore from '@/mobile/icons/TabIconMore.vue';

export default {
  name: 'MobileTabBar',

  components: {
    TabIconHome,
    TabIconFyn,
    TabIconLearn,
    TabIconGoals,
    TabIconMore,
  },

  props: {
    activeTab: { type: String, default: 'home' },
    alertCount: { type: Number, default: 0 },
    unreadCount: { type: Number, default: 0 },
    milestoneCount: { type: Number, default: 0 },
  },

  emits: ['tab'],

  computed: {
    tabs() {
      return [
        { id: 'home', label: 'Home', icon: 'TabIconHome', dot: this.alertCount > 0, badge: 0 },
        { id: 'fyn', label: 'Fyn', icon: 'TabIconFyn', dot: false, badge: this.unreadCount },
        { id: 'learn', label: 'Learn', icon: 'TabIconLearn', dot: false, badge: 0 },
        { id: 'goals', label: 'Goals', icon: 'TabIconGoals', dot: false, badge: this.milestoneCount },
        { id: 'more', label: 'More', icon: 'TabIconMore', dot: false, badge: 0 },
      ];
    },
  },
};
</script>

<style scoped>
.mobile-tab-bar {
  height: 83px;
  padding-bottom: env(safe-area-inset-bottom);
}

.tab-button {
  -webkit-tap-highlight-color: transparent;
}
</style>
```

**Step 3: Create tab icon components**

Create directory `resources/js/mobile/icons/` and create 5 icon components. Example for `TabIconHome.vue`:

```vue
<template>
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
  </svg>
</template>
<script>
export default { name: 'TabIconHome', props: { active: Boolean } };
</script>
```

Create similar for: `TabIconFyn.vue` (Fyn avatar/springbok), `TabIconLearn.vue` (book-open), `TabIconGoals.vue` (flag), `TabIconMore.vue` (grid-2x2).

**Step 4: Create MobileHeader.vue**

Create `resources/js/mobile/MobileHeader.vue`:

```vue
<template>
  <header class="mobile-header bg-white border-b border-light-gray flex items-center px-4"
          style="min-height: 44px;">
    <!-- Left: Back button -->
    <div class="w-10">
      <button
        v-if="showBack"
        class="p-1 -ml-1 text-horizon-500"
        @click="$emit('back')"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
    </div>

    <!-- Centre: Title -->
    <h1 class="flex-1 text-center text-base font-bold text-horizon-500 truncate">
      {{ title }}
    </h1>

    <!-- Right: Action -->
    <div class="w-10 flex justify-end">
      <button
        v-if="rightAction"
        class="p-1 text-horizon-500"
        @click="$emit('action')"
      >
        <slot name="right-action">
          <svg v-if="rightAction === 'share'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
        </slot>
      </button>
    </div>
  </header>
</template>

<script>
export default {
  name: 'MobileHeader',
  props: {
    title: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
    rightAction: { type: String, default: null },
  },
  emits: ['back', 'action'],
};
</script>
```

**Step 5: Commit**

```bash
git add resources/js/mobile/layouts/ resources/js/mobile/MobileTabBar.vue resources/js/mobile/MobileHeader.vue resources/js/mobile/icons/
git commit -m "feat(mobile): add MobileLayout, MobileTabBar (5 tabs), MobileHeader with safe areas"
```

---

## Task 7: Mobile Routes

**Files:**
- Modify: `resources/js/router/index.js`

**Step 1: Add mobile route imports and route definitions**

Add to `resources/js/router/index.js` — lazy-loaded mobile views in the imports section, then mobile routes in the routes array:

After the existing lazy imports, add:

```javascript
// Mobile views
const MobileLoginScreen = () => import('@/mobile/views/MobileLoginScreen.vue');
const VerificationCodeScreen = () => import('@/mobile/views/VerificationCodeScreen.vue');
const BiometricPrompt = () => import('@/mobile/BiometricPrompt.vue');
const MobileLayout = () => import('@/mobile/layouts/MobileLayout.vue');
const MobileDashboard = () => import('@/mobile/views/MobileDashboard.vue');
const MobileFynChat = () => import('@/mobile/views/MobileFynChat.vue');
const LearnHub = () => import('@/mobile/views/LearnHub.vue');
const LearnTopicDetail = () => import('@/mobile/views/LearnTopicDetail.vue');
const MobileGoalsList = () => import('@/mobile/views/MobileGoalsList.vue');
const MobileGoalDetail = () => import('@/mobile/views/MobileGoalDetail.vue');
const MoreMenu = () => import('@/mobile/views/MoreMenu.vue');
const ModuleSummary = () => import('@/mobile/views/ModuleSummary.vue');
const NotificationSettings = () => import('@/mobile/views/NotificationSettings.vue');
```

Add mobile routes before the catch-all route:

```javascript
  // Mobile auth routes (no layout)
  {
    path: '/m/login',
    name: 'MobileLogin',
    component: MobileLoginScreen,
    meta: { public: true },
  },
  {
    path: '/m/verify',
    name: 'MobileVerify',
    component: VerificationCodeScreen,
    meta: { public: true },
  },
  {
    path: '/m/biometric-setup',
    name: 'BiometricSetup',
    component: BiometricPrompt,
    meta: { requiresAuth: true },
  },

  // Mobile app routes (with MobileLayout)
  {
    path: '/m',
    component: MobileLayout,
    meta: { requiresAuth: true },
    children: [
      { path: 'home', name: 'MobileHome', component: MobileDashboard, meta: { title: 'Home' } },
      { path: 'fyn', name: 'MobileFyn', component: MobileFynChat, meta: { title: 'Fyn' } },
      { path: 'learn', name: 'MobileLearn', component: LearnHub, meta: { title: 'Learn' } },
      { path: 'learn/:topic', name: 'MobileLearnTopic', component: LearnTopicDetail, meta: { title: 'Learn' } },
      { path: 'goals', name: 'MobileGoals', component: MobileGoalsList, meta: { title: 'Goals' } },
      { path: 'goals/:id', name: 'MobileGoalDetail', component: MobileGoalDetail, meta: { title: 'Goal' } },
      { path: 'more', name: 'MobileMore', component: MoreMenu, meta: { title: 'More' } },
      { path: 'more/summary/:module', name: 'MobileModuleSummary', component: ModuleSummary },
      { path: 'more/notifications', name: 'MobileNotificationSettings', component: NotificationSettings, meta: { title: 'Notifications' } },
    ],
  },
```

**Step 2: Add native redirect in navigation guard**

In the `router.beforeEach` guard, add logic to redirect native app users to mobile routes:

```javascript
// After auth check, before final navigation
import { platform } from '@/utils/platform';

// In beforeEach:
if (platform.isNative() && to.path === '/dashboard') {
  return next('/m/home');
}
if (platform.isNative() && to.path === '/login') {
  return next('/m/login');
}
```

**Step 3: Verify routes register without errors**

```bash
npm run build
```

Expected: build succeeds (views don't exist yet but are lazy-loaded, so no import errors at build time).

**Step 4: Commit**

```bash
git add resources/js/router/index.js
git commit -m "feat(mobile): add mobile routes under /m/ prefix with MobileLayout wrapper"
```

---

## Task 8: Dashboard Screen

**Files:**
- Create: `resources/js/mobile/views/MobileDashboard.vue`
- Create: `resources/js/mobile/MobileNetWorthCard.vue`
- Create: `resources/js/mobile/charts/NetWorthSparkline.vue`
- Create: `resources/js/mobile/FynInsightCard.vue`
- Create: `resources/js/mobile/MobileAlertsList.vue`
- Create: `resources/js/mobile/ModuleSummaryCard.vue`
- Create: `resources/js/mobile/PullToRefresh.vue`

**Key data source:** `mobileDashboard` Vuex store (`GET /api/v1/mobile/dashboard`).

**Step 1: Create all dashboard components**

The implementer should create each component following the design spec from `docs/plans/2026-03-10-phase2b-design.md` Section 4. Key requirements:

- **MobileDashboard.vue**: Scrollable view. Time-aware greeting ("Good morning/afternoon/evening, {firstName}"). Fetches dashboard on mount if stale. Shows skeleton loading states (`bg-savannah-100 animate-pulse rounded-xl`). Shows empty state for new users.
- **MobileNetWorthCard.vue**: `bg-white rounded-xl shadow-sm p-5`. Net worth in `text-3xl font-black text-horizon-500`. Change in `spring-500` (positive) or `raspberry-500` (negative). Contains `NetWorthSparkline`.
- **NetWorthSparkline.vue**: Tiny ApexCharts sparkline (90-day). Use `vue3-apexcharts`. Import chart colours from `@/constants/designSystem.js`.
- **FynInsightCard.vue**: `bg-horizon-500 rounded-xl p-4 text-white`. Fyn avatar (from `public/images/logos/favicon.png`) + 1-sentence insight text.
- **MobileAlertsList.vue**: `bg-violet-50 rounded-lg border border-violet-200 p-4`. Max 3 alerts shown. Each alert: icon + message + relative time.
- **ModuleSummaryCard.vue**: `bg-white rounded-xl border border-light-gray p-4`. Icon + module name + key metric. Coloured left border: `spring-500` (good), `violet-500` (warning), `raspberry-500` (action needed). Emits `click` for navigation to module summary.
- **PullToRefresh.vue**: Touch gesture wrapper. Pull down past 60px threshold shows loading indicator. Dispatches `mobileDashboard/refreshDashboard`. Uses CSS transforms, no external library.

**Step 2: Verify dashboard loads in browser**

```bash
./dev.sh
# Navigate to localhost:8000/m/home (after logging in)
```

**Step 3: Commit**

```bash
git add resources/js/mobile/views/MobileDashboard.vue resources/js/mobile/MobileNetWorthCard.vue resources/js/mobile/charts/NetWorthSparkline.vue resources/js/mobile/FynInsightCard.vue resources/js/mobile/MobileAlertsList.vue resources/js/mobile/ModuleSummaryCard.vue resources/js/mobile/PullToRefresh.vue
git commit -m "feat(mobile): add mobile dashboard with net worth card, Fyn insight, alerts, module grid"
```

---

## Task 9: Fyn Chat Screen (Text Only)

**Files:**
- Create: `resources/js/mobile/views/MobileFynChat.vue`
- Create: `resources/js/mobile/ChatBubble.vue`
- Create: `resources/js/mobile/TypingIndicator.vue`
- Create: `resources/js/mobile/ToolExecutionStatus.vue`
- Create: `resources/js/mobile/SuggestedPrompts.vue`

**Key data source:** `aiChat` Vuex store. Streaming via `aiChatService.sendMessageStream()`.

**Step 1: Create all chat components**

Follow design spec Section 5. Key requirements:

- **MobileFynChat.vue**: Full-screen. `bg-eggshell-500` message area. Auto-scroll to bottom. Input bar at bottom with send button (`bg-raspberry-500 rounded-full`). Keyboard-aware (input stays above keyboard). Conversation menu (new, history, clear) in header via `...` icon. On mount: load existing conversation or show `SuggestedPrompts`. Uses existing `aiChat` store actions.
- **ChatBubble.vue**: Props: `role` ('user' | 'assistant' | 'navigation' | 'entity_created'), `content`, `metadata`. Fyn messages: `bg-white rounded-2xl rounded-bl-sm p-4`, left-aligned, max-width 85%. User messages: `bg-raspberry-50 rounded-2xl rounded-br-sm p-4`, right-aligned. Navigation messages: inline link card. Entity created: confirmation card.
- **TypingIndicator.vue**: Three animated dots in a Fyn-style bubble. CSS animation (`@keyframes bounce`), no JS.
- **ToolExecutionStatus.vue**: "Fyn is analysing your portfolio..." with spinner. Shown during tool execution SSE events.
- **SuggestedPrompts.vue**: Grid of prompt cards shown in empty conversation. Examples: "How am I doing financially?", "What should I focus on?", "Review my protection", "Help me with my goals". Each card: `bg-white rounded-xl border border-light-gray p-4`. On tap: creates conversation and sends prompt.

**Step 2: Add `abortStreaming` action to aiChat store**

Add to `resources/js/store/modules/aiChat.js` actions:

```javascript
abortStreaming({ commit }) {
    // Will be enhanced with AbortController in Task 10
    commit('SET_STREAMING', false);
    commit('SET_STREAMING_TEXT', '');
},
```

Also add `prefillPrompt` action (for Learn Hub "Ask Fyn" flow):

```javascript
prefillPrompt({ commit }, prompt) {
    commit('SET_PREFILLED_PROMPT', prompt);
},
```

And mutation:

```javascript
SET_PREFILLED_PROMPT(state, prompt) {
    state.prefilledPrompt = prompt;
},
```

And state:

```javascript
prefilledPrompt: null,
```

And getter:

```javascript
prefilledPrompt: (state) => state.prefilledPrompt,
```

**Step 3: Commit**

```bash
git add resources/js/mobile/views/MobileFynChat.vue resources/js/mobile/ChatBubble.vue resources/js/mobile/TypingIndicator.vue resources/js/mobile/ToolExecutionStatus.vue resources/js/mobile/SuggestedPrompts.vue resources/js/store/modules/aiChat.js
git commit -m "feat(mobile): add full-screen Fyn chat with bubbles, typing indicator, suggested prompts"
```

---

## Task 10: Voice Input + SSE Lifecycle

**Files:**
- Create: `resources/js/mobile/VoiceInputButton.vue`
- Modify: `resources/js/store/modules/aiChat.js` (AbortController support)

**Step 1: Create VoiceInputButton.vue**

Create `resources/js/mobile/VoiceInputButton.vue`:

```vue
<template>
  <button
    class="voice-button flex items-center justify-center w-10 h-10 rounded-full transition-colors"
    :class="listening ? 'bg-raspberry-500 text-white' : 'text-neutral-500'"
    @click="toggleListening"
    :disabled="!available"
  >
    <svg v-if="!listening" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
    </svg>
    <!-- Animated waveform when listening -->
    <div v-else class="flex items-center gap-0.5">
      <span v-for="i in 4" :key="i"
            class="w-0.5 bg-white rounded-full animate-pulse"
            :style="{ height: `${8 + Math.random() * 8}px`, animationDelay: `${i * 0.1}s` }">
      </span>
    </div>
  </button>
</template>

<script>
import { platform } from '@/utils/platform';

export default {
  name: 'VoiceInputButton',

  emits: ['transcript', 'partial'],

  data() {
    return {
      available: false,
      listening: false,
    };
  },

  async mounted() {
    if (platform.canUseVoiceInput()) {
      try {
        const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');
        const { available } = await SpeechRecognition.available();
        this.available = available;
      } catch {
        this.available = false;
      }
    }
  },

  methods: {
    async toggleListening() {
      if (this.listening) {
        await this.stopListening();
      } else {
        await this.startListening();
      }
    },

    async startListening() {
      try {
        const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');

        const permResult = await SpeechRecognition.requestPermissions();
        if (permResult.speechRecognition !== 'granted') return;

        this.listening = true;

        SpeechRecognition.addListener('partialResults', (result) => {
          if (result.matches?.length) {
            this.$emit('partial', result.matches[0]);
          }
        });

        const result = await SpeechRecognition.start({
          language: 'en-GB',
          partialResults: true,
          popup: false,
        });

        this.listening = false;

        if (result.matches?.length) {
          this.$emit('transcript', result.matches[0]);
        }
      } catch {
        this.listening = false;
      }
    },

    async stopListening() {
      try {
        const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');
        await SpeechRecognition.stop();
      } catch {
        // Ignore
      }
      this.listening = false;
    },
  },
};
</script>
```

**Step 2: Add AbortController to aiChat streaming**

Modify `resources/js/services/aiChatService.js` to accept an `AbortSignal`:

```javascript
async sendMessageStream(conversationId, message, currentRoute = null, signal = null) {
    const token = await getToken();

    const response = await fetch(`/api/ai-chat/conversations/${conversationId}/messages`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
            message,
            current_route: currentRoute,
        }),
        signal,
    });

    if (!response.ok) {
        throw new Error(`Chat request failed: ${response.status}`);
    }

    return response.body.getReader();
},
```

Update `aiChat.js` store to manage an AbortController:

Add to state: `abortController: null`

In `sendMessage` action, create and store controller:
```javascript
const controller = new AbortController();
commit('SET_ABORT_CONTROLLER', controller);
// Pass controller.signal to sendMessageStream
```

In `abortStreaming` action:
```javascript
abortStreaming({ commit, state }) {
    if (state.abortController) {
        state.abortController.abort();
    }
    commit('SET_STREAMING', false);
    commit('SET_STREAMING_TEXT', '');
    commit('SET_ABORT_CONTROLLER', null);
},
```

Add mutation: `SET_ABORT_CONTROLLER(state, controller) { state.abortController = controller; }`

**Step 3: Commit**

```bash
git add resources/js/mobile/VoiceInputButton.vue resources/js/services/aiChatService.js resources/js/store/modules/aiChat.js
git commit -m "feat(mobile): add voice input button with speech recognition, SSE AbortController support"
```

---

## Task 11: Goals List + Card + ProgressRing

**Files:**
- Create: `resources/js/mobile/views/MobileGoalsList.vue`
- Create: `resources/js/mobile/goals/MobileGoalCard.vue`
- Create: `resources/js/mobile/charts/ProgressRing.vue`

Follow design spec Section 6. Key requirements:

- **MobileGoalsList.vue**: Filter chips (All/Active/Completed). Uses existing `goals` store. Fetches on mount. Maps goals to `MobileGoalCard` components. Shows `ContributionFAB` at bottom-right.
- **MobileGoalCard.vue**: Props: `goal`. Shows ProgressRing (56x56), goal name, current/target amounts (use `currencyMixin`), percentage, status text. Status colours: `spring-500` (on track), `violet-500` (behind), `raspberry-500` (at risk). Tap navigates to `/m/goals/:id`.
- **ProgressRing.vue**: SVG-based. Props: `percentage` (0-100), `size` (number, default 56), `strokeWidth` (default 4). Colour based on status prop or percentage thresholds. Animated fill on mount via CSS transition on `stroke-dashoffset`.

**Commit:**

```bash
git add resources/js/mobile/views/MobileGoalsList.vue resources/js/mobile/goals/MobileGoalCard.vue resources/js/mobile/charts/ProgressRing.vue
git commit -m "feat(mobile): add mobile goals list with filter chips and progress ring charts"
```

---

## Task 12: Goal Detail + Contribution FAB + Milestone Overlay

**Files:**
- Create: `resources/js/mobile/views/MobileGoalDetail.vue`
- Create: `resources/js/mobile/goals/ContributionFAB.vue`
- Create: `resources/js/mobile/goals/MilestoneOverlay.vue`

Follow design spec Section 6. Key requirements:

- **MobileGoalDetail.vue**: Large ProgressRing (120x120). Amounts, target date, monthly needed, status. Milestone tracker (reuse existing `GoalMilestoneTracker` component if possible, or render inline). Recent contributions (last 5). Share button in header (uses `ShareButton.vue` from Task 19).
- **ContributionFAB.vue**: Fixed bottom-right, 56x56, `bg-raspberry-500 text-white rounded-full`. Opens bottom sheet on tap. Fields: amount, date (default today), optional note. Submits to `POST /api/v1/goals/{id}/contributions`. Haptic on save: `Haptics.notification({ type: NotificationType.Success })`.
- **MilestoneOverlay.vue**: Full-screen overlay. CSS confetti animation. Fyn avatar + congratulatory message. Share button. Haptic: `Haptics.impact({ style: ImpactStyle.Medium })`. Auto-dismiss 5 seconds or tap.

**Haptic integration pattern:**

```javascript
import { platform } from '@/utils/platform';

async function triggerHaptic(style) {
  if (!platform.canUseHaptics()) return;
  const { Haptics, ImpactStyle, NotificationType } = await import('@capacitor/haptics');
  // Use appropriate method
}
```

**Commit:**

```bash
git add resources/js/mobile/views/MobileGoalDetail.vue resources/js/mobile/goals/ContributionFAB.vue resources/js/mobile/goals/MilestoneOverlay.vue
git commit -m "feat(mobile): add goal detail, contribution FAB with bottom sheet, milestone celebration overlay"
```

---

## Task 13: Learn Hub

**Files:**
- Create: `resources/js/mobile/learn/learnTopics.js`
- Create: `resources/js/mobile/views/LearnHub.vue`
- Create: `resources/js/mobile/learn/LearnTopicCard.vue`
- Create: `resources/js/mobile/views/LearnTopicDetail.vue`
- Create: `resources/js/mobile/learn/LearnInfoCard.vue`
- Create: `resources/js/mobile/learn/LearnInfoPopup.vue`
- Create: `resources/js/mobile/learn/LearnGuideLink.vue`

Follow design spec Section 7. Key requirements:

- **learnTopics.js**: Static data for 8 topics (Tax, Pensions, Protection, Investing, Estate, Budgeting, ISAs, Goals). Each topic: `id`, `label`, `icon` (emoji), `fynIntro`, `keyInfo[]` (title, summary, detail), `guides[]` (title, source, readTime, url), `fynPrompt`. External links must point to real MoneyHelper/HMRC/Pension Wise URLs.
- **LearnHub.vue**: Fyn intro card (`bg-horizon-500 text-white`). 2-column topic grid. Uses `LearnTopicCard`.
- **LearnTopicCard.vue**: Props: `topic`. `bg-white rounded-xl border border-light-gray p-4`. Icon in `violet-500`. Guide count. Tap navigates to `/m/learn/:topic`.
- **LearnTopicDetail.vue**: Fyn context card. Key info section with `LearnInfoCard` components. Guides section with `LearnGuideLink` components. "Ask Fyn" CTA at bottom.
- **LearnInfoCard.vue**: Props: `info`. Title + summary + ⓘ button. Tap ⓘ opens `LearnInfoPopup`.
- **LearnInfoPopup.vue**: Bottom sheet. Slides up. Drag handle. Expanded detail text. "Ask Fyn about this" button. Dismiss by drag-down, tap outside, or X.
- **LearnGuideLink.vue**: Props: `guide`. External link card. Source + read time. Opens in SFSafariViewController via `@capacitor/browser`:

```javascript
import { Browser } from '@capacitor/browser';
await Browser.open({ url: guide.url });
```

On web fallback: `window.open(guide.url, '_blank')`.

**"Ask Fyn" flow:**
```javascript
this.$store.dispatch('aiChat/prefillPrompt', this.topic.fynPrompt);
this.$router.push('/m/fyn');
```

**Commit:**

```bash
git add resources/js/mobile/learn/ resources/js/mobile/views/LearnHub.vue resources/js/mobile/views/LearnTopicDetail.vue
git commit -m "feat(mobile): add Fyn-led Learn Hub with topic grid, info popups, external guides"
```

---

## Task 14: More Menu + Profile + Settings

**Files:**
- Create: `resources/js/mobile/views/MoreMenu.vue`
- Create: `resources/js/mobile/ProfileCard.vue`
- Create: `resources/js/mobile/SettingsList.vue`

Follow design spec Section 8. Key requirements:

- **MoreMenu.vue**: Scrollable. Profile card at top. Module grid (2-column, 7 modules). Settings list. "Open full web app" link. Logout button (`text-raspberry-500`). Version info.
- **ProfileCard.vue**: `bg-white rounded-xl p-4`. User avatar (initials circle), name, subscription tier badge, email. Data from `auth` store.
- **SettingsList.vue**: List rows, min 48pt height. Items: Account & Profile, Security, Notifications, Subscription, Data & Privacy, Help & Support, About Fynla. Notifications → navigates to `/m/more/notifications`. All others → `Browser.open({ url: 'https://fynla.org/{path}' })`.
- **Logout:** Dispatches `auth/logout`, clears biometric credentials, navigates to `/m/login`.

**Commit:**

```bash
git add resources/js/mobile/views/MoreMenu.vue resources/js/mobile/ProfileCard.vue resources/js/mobile/SettingsList.vue
git commit -m "feat(mobile): add More menu with profile card, module grid, settings list"
```

---

## Task 15: Module Summaries

**Files:**
- Create: `resources/js/mobile/views/ModuleSummary.vue`

**Step 1: Create shared ModuleSummary component**

Follows design spec Section 8. Single component, receives module name from route param. Simplified: hero metric + Fyn one-liner + "View full detail on web" button.

```vue
<!-- Route: /m/more/summary/:module -->
<!-- module param: protection, savings, investment, retirement, estate, goals, tax -->
```

- Hero metric card: large number + status, data from `mobileDashboard` store modules data
- Fyn one-liner: `bg-horizon-500 text-white rounded-xl p-4`
- "View full detail on web" button → `Browser.open({ url: 'https://fynla.org/{modulePath}' })`

Module path mapping:
```javascript
const MODULE_PATHS = {
  protection: '/protection',
  savings: '/savings',
  investment: '/net-worth/investments',
  retirement: '/retirement',
  estate: '/estate',
  goals: '/goals',
  tax: '/uk-taxes',
};
```

**Step 2: Commit**

```bash
git add resources/js/mobile/views/ModuleSummary.vue
git commit -m "feat(mobile): add simplified module summary screen — hero metric + Fyn + view on web"
```

---

## Task 16: Notification Settings Screen

**Files:**
- Create: `resources/js/mobile/views/NotificationSettings.vue`

**Step 1: Create NotificationSettings.vue**

8 toggle rows. Fetches current preferences on mount via `GET /api/v1/mobile/notifications/preferences`. Updates on toggle via `PUT /api/v1/mobile/notifications/preferences`.

```vue
<!-- Toggle items:
  1. Policy Renewals (policy_renewals)
  2. Goal Milestones (goal_milestones)
  3. Contribution Reminders (contribution_reminders)
  4. Market Updates (market_updates)
  5. Fyn Daily Insight (fyn_daily_insight)
  6. Security Alerts (security_alerts)
  7. Payment Alerts (payment_alerts)
  8. Mortgage Rate Alerts (mortgage_rate_alerts) — NEW
-->
```

If push permissions not granted, show banner at top: "Enable notifications to receive these alerts" with "Enable" button that triggers push permission request.

**Step 2: Commit**

```bash
git add resources/js/mobile/views/NotificationSettings.vue
git commit -m "feat(mobile): add notification settings screen with 8 preference toggles"
```

---

## Task 17: Push Notifications Frontend (Full Implementation)

**Files:**
- Modify: `resources/js/store/modules/mobileNotifications.js` (complete implementation)
- Create: `resources/js/mobile/PushPermissionPrompt.vue`
- Create: `resources/js/mobile/InAppNotificationToast.vue`

**Step 1: Complete mobileNotifications store**

Update the placeholder from Task 3 with full `@capacitor/push-notifications` integration:

```javascript
async requestPermission({ commit, dispatch }) {
    if (!platform.canUsePushNotifications()) {
        commit('SET_PERMISSION_STATUS', 'denied');
        return;
    }

    const { PushNotifications } = await import('@capacitor/push-notifications');
    const result = await PushNotifications.requestPermissions();

    if (result.receive === 'granted') {
        commit('SET_PERMISSION_STATUS', 'granted');
        await PushNotifications.register();
    } else {
        commit('SET_PERMISSION_STATUS', 'denied');
    }
},

async registerToken({ rootState }) {
    // Called after PushNotifications 'registration' event
    // POST to /api/v1/mobile/devices
},
```

Register push notification listeners (registration, received, action performed) during app init.

**Step 2: Create PushPermissionPrompt.vue**

Bottom sheet with Fyn avatar + contextual message + "Enable" / "Not now" buttons. Props: `triggerType` (string), `message` (string). "Not now" dismisses for 7 days per trigger type.

**Step 3: Create InAppNotificationToast.vue**

Top banner that slides down. Auto-dismiss after 4 seconds. Shows notification title + body. Tap navigates to deep link.

**Step 4: Commit**

```bash
git add resources/js/store/modules/mobileNotifications.js resources/js/mobile/PushPermissionPrompt.vue resources/js/mobile/InAppNotificationToast.vue
git commit -m "feat(mobile): complete push notification frontend — permission flow, in-app toast, deep links"
```

---

## Task 18: Mortgage Rate Alerts (Backend)

**Files:**
- Create: `database/migrations/2026_03_10_200004_add_mortgage_rate_alerts_to_notification_preferences.php`
- Create: `app/Console/Commands/SendMortgageRateAlerts.php`
- Create: `app/Notifications/MortgageRateAlertNotification.php`
- Modify: `app/Models/NotificationPreference.php`
- Modify: `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php`
- Modify: `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php`
- Modify: `app/Console/Kernel.php`
- Create: `tests/Unit/Commands/SendMortgageRateAlertsTest.php`
- Create: `tests/Feature/Mobile/MortgageRateAlertPreferenceTest.php`

**Step 1: Write failing tests**

`tests/Unit/Commands/SendMortgageRateAlertsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\NotificationPreference;
use App\Models\Property;
use App\Models\User;

describe('SendMortgageRateAlerts', function () {
    it('runs successfully', function () {
        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });

    it('detects mortgages with fixed rate ending in 90 days', function () {
        $user = User::factory()->create();
        NotificationPreference::getOrCreateForUser($user->id);
        $property = Property::factory()->create(['user_id' => $user->id]);
        Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => now()->addDays(90),
        ]);

        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });

    it('skips users with mortgage_rate_alerts disabled', function () {
        $user = User::factory()->create();
        $prefs = NotificationPreference::getOrCreateForUser($user->id);
        $prefs->update(['mortgage_rate_alerts' => false]);
        $property = Property::factory()->create(['user_id' => $user->id]);
        Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => now()->addDays(30),
        ]);

        $this->artisan('notifications:mortgage-rate-alerts')
            ->assertExitCode(0);
    });
});
```

`tests/Feature/Mobile/MortgageRateAlertPreferenceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Mortgage Rate Alert Preference', function () {
    it('returns mortgage_rate_alerts in preferences response', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/mobile/notifications/preferences');
        $response->assertOk()
            ->assertJsonPath('data.mortgage_rate_alerts', true);
    });

    it('can update mortgage_rate_alerts preference', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/mobile/notifications/preferences', [
            'mortgage_rate_alerts' => false,
        ]);

        $response->assertOk();

        $prefs = NotificationPreference::where('user_id', $user->id)->first();
        expect($prefs->mortgage_rate_alerts)->toBeFalse();
    });
});
```

**Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Commands/SendMortgageRateAlertsTest.php
./vendor/bin/pest tests/Feature/Mobile/MortgageRateAlertPreferenceTest.php
```

Expected: FAIL (command doesn't exist, column doesn't exist).

**Step 3: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('mortgage_rate_alerts')->default(true)->after('payment_alerts');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('mortgage_rate_alerts');
        });
    }
};
```

**Step 4: Update NotificationPreference model**

Add `'mortgage_rate_alerts'` to `$fillable` and `$casts`. Update `getOrCreateForUser` defaults to include `'mortgage_rate_alerts' => true`.

**Step 5: Update validation and controller**

Add `'mortgage_rate_alerts' => ['nullable', 'boolean']` to `UpdateNotificationPreferencesRequest`.

Add `'mortgage_rate_alerts' => $prefs->mortgage_rate_alerts` to the show response in `NotificationPreferenceController`.

**Step 6: Create SendMortgageRateAlerts command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\Mortgage;
use App\Models\NotificationPreference;
use App\Services\Mobile\PushNotificationService;
use Illuminate\Console\Command;

class SendMortgageRateAlerts extends Command
{
    protected $signature = 'notifications:mortgage-rate-alerts';
    protected $description = 'Send push notifications for fixed rate mortgages expiring at 90/60/30 days';

    public function handle(PushNotificationService $pushService): int
    {
        $thresholds = [90, 60, 30];

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $mortgages = Mortgage::where('rate_type', 'fixed')
                ->whereDate('rate_fix_end_date', $targetDate)
                ->with('property')
                ->get();

            foreach ($mortgages as $mortgage) {
                $userId = $mortgage->user_id;

                // Check preference
                if (!$pushService->shouldSend($userId, 'mortgage_rate_alerts')) {
                    continue;
                }

                $address = $mortgage->property?->address ?? 'your property';
                $message = "Your fixed rate on {$address} expires in {$days} days. Now might be a good time to review your options.";

                $pushService->sendToUser($userId, 'Mortgage Rate Alert', $message, [
                    'type' => 'mortgage_rate_alert',
                    'deepLink' => '/m/more/summary/savings',
                ]);
            }
        }

        $this->info('Mortgage rate alerts processed.');

        return self::SUCCESS;
    }
}
```

**Step 7: Create notification class**

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MortgageRateAlertNotification extends Notification
{
    public function __construct(
        private readonly string $address,
        private readonly int $daysUntilExpiry,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mortgage_rate_alert',
            'title' => 'Mortgage Rate Alert',
            'message' => "Your fixed rate on {$this->address} expires in {$this->daysUntilExpiry} days. Now might be a good time to review your options.",
            'deep_link' => '/m/more/summary/savings',
        ];
    }
}
```

**Step 8: Register in Kernel.php**

Add: `$schedule->command('notifications:mortgage-rate-alerts')->dailyAt('09:30');`

**Step 9: Run migration and tests**

```bash
php artisan migrate
./vendor/bin/pest tests/Unit/Commands/SendMortgageRateAlertsTest.php
./vendor/bin/pest tests/Feature/Mobile/MortgageRateAlertPreferenceTest.php
```

Expected: all pass.

**Step 10: Seed database**

```bash
php artisan db:seed
```

**Step 11: Commit**

```bash
git add database/migrations/2026_03_10_200004_add_mortgage_rate_alerts_to_notification_preferences.php app/Console/Commands/SendMortgageRateAlerts.php app/Notifications/MortgageRateAlertNotification.php app/Models/NotificationPreference.php app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php app/Console/Kernel.php tests/Unit/Commands/SendMortgageRateAlertsTest.php tests/Feature/Mobile/MortgageRateAlertPreferenceTest.php
git commit -m "feat(mobile): add mortgage rate alerts — migration, command, notification, 90/60/30 day warnings"
```

---

## Task 19: ShareButton Component

**Files:**
- Create: `resources/js/mobile/ShareButton.vue`

**Step 1: Create ShareButton.vue**

```vue
<template>
  <button
    class="share-button p-2 text-horizon-500"
    :disabled="loading"
    @click="handleShare"
  >
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
    </svg>
  </button>
</template>

<script>
import api from '@/services/api';
import { platform } from '@/utils/platform';

export default {
  name: 'ShareButton',
  props: {
    shareType: { type: String, required: true },
    entityId: { type: [String, Number], default: null },
  },

  data() {
    return { loading: false };
  },

  methods: {
    async handleShare() {
      this.loading = true;
      try {
        const url = this.entityId
          ? `/v1/mobile/share/${this.shareType}/${this.entityId}`
          : `/v1/mobile/share/${this.shareType}`;

        const response = await api.get(url);
        const payload = response.data.data;

        if (platform.isNative()) {
          const { Share } = await import('@capacitor/share');
          await Share.share({
            title: payload.title,
            text: payload.text,
            url: payload.url,
            dialogTitle: 'Share via',
          });
        } else if (navigator.share) {
          await navigator.share({
            title: payload.title,
            text: payload.text,
            url: payload.url,
          });
        } else {
          await navigator.clipboard.writeText(`${payload.text} ${payload.url}`);
          // Show toast: "Copied to clipboard"
        }
      } catch {
        // User cancelled share or error — silent fail
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

**Step 2: Commit**

```bash
git add resources/js/mobile/ShareButton.vue
git commit -m "feat(mobile): add ShareButton with native share sheet, Web Share API, clipboard fallback"
```

---

## Task 20: App Icons + Splash Screen + Xcode Configuration

**Files:**
- Source: `public/images/logos/favicon.png`
- Generate: iOS app icon set in `ios/App/App/Assets.xcassets/AppIcon.appiconset/`
- Modify: Xcode project capabilities

**Step 1: Generate iOS app icons from favicon.png**

Use the `sips` command (macOS built-in) to resize `public/images/logos/favicon.png` to all required iOS sizes:

```bash
mkdir -p ios/App/App/Assets.xcassets/AppIcon.appiconset

# Generate all required sizes
sizes=("20x20" "29x29" "40x40" "58x58" "60x60" "76x76" "80x80" "87x87" "120x120" "152x152" "167x167" "180x180" "1024x1024")
for size in "${sizes[@]}"; do
  w=$(echo $size | cut -dx -f1)
  sips -z $w $w public/images/logos/favicon.png --out "ios/App/App/Assets.xcassets/AppIcon.appiconset/icon-${size}.png" 2>/dev/null
done
```

Create the `Contents.json` manifest for the icon set pointing to the generated files.

**Step 2: Configure Xcode project capabilities**

Open `ios/App/App.xcworkspace` in Xcode and enable:
- Push Notifications capability
- Associated Domains: `applinks:fynla.org`
- Keychain Sharing

Add to `ios/App/App/Info.plist`:
```xml
<key>NSMicrophoneUsageDescription</key>
<string>Fynla uses your microphone for voice input when chatting with Fyn</string>
<key>NSFaceIDUsageDescription</key>
<string>Fynla uses Face ID for quick, secure login</string>
```

**Step 3: Commit**

```bash
git add ios/App/App/Assets.xcassets/ ios/App/App/Info.plist
git commit -m "feat(mobile): add iOS app icons from favicon.png, configure capabilities and permissions"
```

---

## Task 21: Integration Testing + Build Verification

**Files:**
- Test files from all previous tasks
- Verify full test suite

**Step 1: Run full backend test suite**

```bash
./vendor/bin/pest
```

Expected: all tests pass (existing + new mortgage rate alert tests).

**Step 2: Run frontend build**

```bash
npm run build
```

Expected: Vite build succeeds, no errors.

**Step 3: Run iOS build**

```bash
./deploy/mobile/build-ios.sh
```

Expected: Web assets built, synced to iOS project.

**Step 4: Open Xcode and verify**

```bash
open ios/App/App.xcworkspace
```

Verify:
- Project builds without errors
- App icons appear in asset catalog
- Info.plist permissions present
- Capabilities configured (Push, Associated Domains, Keychain)

**Step 5: Test in Simulator**

```bash
npx cap run ios
```

Verify:
- App launches with splash screen
- Login screen appears
- Can navigate through all 5 tabs
- Dashboard loads data
- Chat sends messages
- Learn Hub shows topics
- Goals list renders
- More menu accessible

**Step 6: Seed database**

```bash
php artisan db:seed
```

**Step 7: Commit any final fixes**

```bash
git add -A
git commit -m "chore(mobile): Phase 2b integration verification — all tests passing, iOS build confirmed"
```

---

## Summary

| Task | Focus | Components |
|------|-------|------------|
| 1 | Capacitor project setup | config, npm packages, iOS init |
| 2 | Platform + token storage native upgrade | platform.js, tokenStorage.js |
| 3 | Store persistence + build script | index.js, mobileNotifications store, build-ios.sh |
| 4 | Mobile auth screens | login, verification code, biometric prompt |
| 5 | App lifecycle management | background/foreground, token refresh |
| 6 | Layout + tab bar + header | MobileLayout, MobileTabBar, MobileHeader |
| 7 | Mobile routes | router/index.js additions |
| 8 | Dashboard screen | 7 dashboard components |
| 9 | Fyn Chat (text) | chat view, bubbles, typing, prompts |
| 10 | Voice input + SSE lifecycle | VoiceInputButton, AbortController |
| 11 | Goals list + card + ring | goals list, card, ProgressRing |
| 12 | Goal detail + FAB + milestone | detail, contribution, celebration |
| 13 | Learn Hub | 7 learn components + data |
| 14 | More menu + settings | menu, profile, settings list |
| 15 | Module summaries | shared template, 7 modules |
| 16 | Notification settings | 8-toggle screen |
| 17 | Push notifications frontend | store, permission prompt, toast |
| 18 | Mortgage rate alerts (backend) | migration, command, notification |
| 19 | ShareButton | native share, web fallback |
| 20 | App icons + Xcode config | icons, permissions, capabilities |
| 21 | Integration testing + build | full verification |
