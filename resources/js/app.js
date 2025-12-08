import './bootstrap';
// Import Slippery Mode CSS directly

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import store from './store';
import VueApexCharts from 'vue3-apexcharts';
import { useDesignMode } from './composables/useDesignMode';

// Initialize design mode on app startup
const { designMode } = useDesignMode();

// Create Vue app instance
const app = createApp(App);

// Use plugins
app.use(router);
app.use(store);
app.use(VueApexCharts);

// Mount app
app.mount('#app');
