import './bootstrap';

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import store from './store';
import VueApexCharts from 'vue3-apexcharts';

// Create Vue app instance
const app = createApp(App);

// Use plugins
app.use(router);
app.use(store);
app.use(VueApexCharts);

// Initialize preview mode from sessionStorage if available
// This allows preview mode to survive page reloads
store.dispatch('preview/initFromStorage').then((restored) => {
    if (restored) {
        console.log('[App] Preview mode restored from sessionStorage');
    }
}).catch((error) => {
    console.warn('[App] Failed to restore preview mode:', error);
});

// Mount app
app.mount('#app');
