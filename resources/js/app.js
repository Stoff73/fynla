import './bootstrap';

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

// One-time cleanup: remove legacy auth_token from localStorage (now sessionStorage only)
localStorage.removeItem('auth_token');

// Create Vue app instance
const app = createApp(App);

// Use plugins
app.use(router);
app.use(store);
app.use(VueApexCharts);

// Register custom directives
app.directive('preview-disabled', previewDisabled);

// Initialize preview mode from sessionStorage if available
// This allows preview mode to survive page reloads
store.dispatch('preview/initFromStorage').catch(() => {
    // Preview mode restoration failed silently
});

// Initialize session lifecycle management for security
// Handles: browser/tab close logout, inactivity timeout
initSessionLifecycle(store, router);

// Mount app
app.mount('#app');
