import './bootstrap';

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import store from './store';
import VueApexCharts from 'vue3-apexcharts';

// Import custom directives
import { previewDisabled } from './directives/previewDisabled';

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

// Mount app
app.mount('#app');
