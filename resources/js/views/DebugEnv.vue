<template>
  <div class="p-8">
    <h1 class="text-2xl font-bold mb-4">Environment Debug Info</h1>

    <div class="bg-savannah-100 p-4 rounded mb-4">
      <h2 class="font-bold mb-2">Vite Environment Variables:</h2>
      <pre class="text-sm">{{ envInfo }}</pre>
    </div>

    <div class="bg-savannah-100 p-4 rounded mb-4">
      <h2 class="font-bold mb-2">Axios Config (bootstrap.js):</h2>
      <pre class="text-sm">{{ axiosConfig }}</pre>
    </div>

    <div class="bg-savannah-100 p-4 rounded mb-4">
      <h2 class="font-bold mb-2">API Instance Config (api.js):</h2>
      <pre class="text-sm">{{ apiConfig }}</pre>
    </div>

    <div class="bg-savannah-100 p-4 rounded mb-4">
      <h2 class="font-bold mb-2">Window Location:</h2>
      <pre class="text-sm">{{ locationInfo }}</pre>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'DebugEnv',

  computed: {
    envInfo() {
      return {
        'VITE_API_BASE_URL': import.meta.env.VITE_API_BASE_URL || '(not set - will use fallback)',
        'VITE_APP_NAME': import.meta.env.VITE_APP_NAME,
        'import.meta.env.PROD': import.meta.env.PROD,
        'import.meta.env.DEV': import.meta.env.DEV,
        'import.meta.env.MODE': import.meta.env.MODE,
      };
    },

    axiosConfig() {
      return {
        baseURL: window.axios.defaults.baseURL,
        withCredentials: window.axios.defaults.withCredentials,
      };
    },

    apiConfig() {
      return {
        baseURL: api.defaults.baseURL,
        withCredentials: api.defaults.withCredentials,
      };
    },

    locationInfo() {
      return {
        origin: window.location.origin,
        href: window.location.href,
        pathname: window.location.pathname,
      };
    },
  },
};
</script>
