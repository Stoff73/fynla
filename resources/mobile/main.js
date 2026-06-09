import { createApp } from 'vue';
import App from './App.vue';
import router from './router.js';
import { store } from './store.js';
import { apiPost } from './api.js';
import consoleCapture from '../js/services/consoleCapture.js';
import './style.css';

// Capture console output + unhandled errors so bug reports carry diagnostics.
consoleCapture.startCapture();

// Boot-time token rotation (short-TTL + refresh rotation, on the existing
// Sanctum bearer channel). The bridge hands /m a copy of the user's bearer; on
// boot we rotate it via the existing /api/v1/auth/refresh-token so /m holds its
// own short-lived (config TTL, ~12h) token and the previous one is revoked,
// shrinking the window a token leaked from localStorage stays valid. Best-effort
// and time-boxed: mount regardless so a slow/failed refresh never blocks the app
// (the existing token is still valid for up to its 4h life).
async function boot() {
  if (store.token) {
    try {
      const refreshed = await Promise.race([
        apiPost('/api/v1/auth/refresh-token', {}, store.token),
        new Promise((resolve) => setTimeout(() => resolve(null), 2000)),
      ]);
      if (refreshed && refreshed.ok && refreshed.data?.data?.token) {
        store.setToken(refreshed.data.data.token);
      }
    } catch (e) {
      /* keep the existing token — it is still valid */
    }
  }
  createApp(App).use(router).mount('#m-app');
}

boot();
