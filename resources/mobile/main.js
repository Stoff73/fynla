import { createApp } from 'vue';
import App from './App.vue';
import router from './router.js';
import { store } from './store.js';
import { apiPost } from './api.js';
import { rotateBootToken } from './bootAuth.js';
import consoleCapture from '../js/services/consoleCapture.js';
import './style.css';

// Capture console output + unhandled errors so bug reports carry diagnostics.
consoleCapture.startCapture();

// Boot-time token rotation (short-TTL + refresh rotation, on the existing
// Sanctum bearer channel). On boot we rotate it via the existing
// /api/v1/auth/refresh-token so /m holds its own short-lived (config TTL, ~12h)
// token and the previous one is revoked, shrinking the window a token leaked
// from localStorage stays valid. The rotated mobile bearer deliberately remains
// inside /m; desktop exits use single-use server handoffs. Best-effort and
// time-boxed: mount regardless so a slow/failed refresh never blocks the app.
async function boot() {
  if (store.token) {
    await rotateBootToken({
      token: store.token,
      refresh: (token) => apiPost('/api/v1/auth/refresh-token', {}, token),
      setToken: (token) => store.setToken(token),
      clearToken: () => store.logout(),
    });
  }
  createApp(App).use(router).mount('#m-app');
}

boot();
