import './bootstrap';

// Unregister any stale service workers from previous PWA-enabled builds.
// A cached SW can intercept asset requests and serve them with wrong MIME types
// (e.g., 'image/png' for JS files), causing blank screens on iOS WKWebView.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(registrations => {
    registrations.forEach(r => r.unregister());
  }).catch(() => {});
  // Also clear all caches left by workbox
  if (typeof caches !== 'undefined') {
    caches.keys().then(names => {
      names.forEach(name => caches.delete(name));
    }).catch(() => {});
  }
}

// Start console capture early to capture any initialization errors
import consoleCapture from './services/consoleCapture';
consoleCapture.startCapture();

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import store from './store';
import VueApexCharts from 'vue3-apexcharts';

// Import custom directives
import { previewDisabled } from './directives/previewDisabled';

// Import session lifecycle service for security
import { initSessionLifecycle } from './services/sessionLifecycleService';

import { isNativePlatform, getToken, getItem } from './services/tokenStorage';
import { initAppLifecycle, attemptBiometricLogin } from './mobile/appLifecycle';
import logger from './utils/logger';

// One-time cleanup: remove legacy auth_token from localStorage (now managed via tokenStorage)
localStorage.removeItem('auth_token');

// Create Vue app instance
const app = createApp(App);

// Use plugins
app.use(router);
app.use(store);
app.use(VueApexCharts);

// Register custom directives
app.directive('preview-disabled', previewDisabled);

// Prevent scroll wheel from changing number input values (silently corrupts data)
document.addEventListener('wheel', (e) => {
    if (e.target?.type === 'number') {
        e.target.blur();
    }
}, { passive: true });

// Expose router globally so Vuex store actions can navigate on native
// without creating circular imports (store -> router -> store)
window.__appRouter = router;

// Register $toast global property — delegates to the toast Vuex store
app.config.globalProperties.$toast = {
  show(message, type = 'success', duration = 3000) {
    store.dispatch('toast/show', { message, type, duration });
  },
  success(message) {
    store.dispatch('toast/success', message);
  },
  error(message) {
    store.dispatch('toast/error', message);
  },
};

// Global Vue error handler — catches rendering and lifecycle errors
app.config.errorHandler = (err, instance, info) => {
  console.error('[Vue Error]', err?.message || err, '| info:', info);
};

logger.debug('App', 'Module init complete, calling initAndMount');

async function initAndMount() {
  logger.debug('App Init', 'Step 1: isNative =', isNativePlatform());

  // On native (Capacitor), try to restore token but don't block app mount.
  // Preferences.get() hangs on some iOS builds, so we use a short timeout.
  if (isNativePlatform()) {
    try {
      logger.debug('App Init', 'Step 2: Calling getToken...');
      const tokenPromise = getToken();
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Token restore timeout (3s)')), 3000)
      );
      const token = await Promise.race([tokenPromise, timeoutPromise]);
      logger.debug('App Init', 'Step 3: Token result:', token ? 'yes (' + token.length + ' chars)' : 'none');
      if (token) {
        store.commit('auth/setToken', token);
      }
    } catch (e) {
      logger.debug('App Init', 'Step 3-ERR: Token failed:', e?.message || 'unknown');
    }
  }

  logger.debug('App Init', 'Step 4: Dispatching preview/initFromStorage');
  await store.dispatch('preview/initFromStorage').catch(() => {});

  logger.debug('App Init', 'Step 5: initSessionLifecycle');
  initSessionLifecycle(store, router);

  logger.debug('App Init', 'Step 6: Mounting app');
  app.mount('#app');
  logger.debug('App Init', 'Step 7: App mounted');

  // Wait for router initial navigation to complete
  try {
    await router.isReady();
    logger.debug('App Init', 'Step 8: Router ready, current route:', router.currentRoute.value.path);
  } catch (e) {
    console.error('[App Init] Step 8-ERR: Router failed:', e?.message || e);
  }

  // On native, initialise app lifecycle (background/foreground handling)
  // and attempt biometric login if no token was restored from storage
  if (isNativePlatform()) {
    logger.debug('App Init', 'Step 9: Initialising native app lifecycle');
    initAppLifecycle(store, router);

    // Auto-login with Face ID if user has previously set it up
    if (!store.getters['auth/isAuthenticated']) {
      const biometricFlag = await getItem('biometric_enabled');
      if (biometricFlag === 'true') {
        try {
          const success = await attemptBiometricLogin(store);
          if (success) {
            router.push('/m/home');
          }
        } catch {
          // Face ID failed or cancelled — fall through to login screen
        }
      }
    }
  }
}

initAndMount().catch(e => {
  console.error('[App Init] FATAL:', e?.message || e);
});
