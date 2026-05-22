<template>
  <div>
    <div class="m-card">
      <h1 class="m-h1">Signed in</h1>
      <p class="m-sub">{{ greeting }}</p>
      <button class="m-btn" style="background:#1F2A44" @click="logout">Sign out</button>
    </div>
    <div class="m-card">
      <h1 class="m-h1">Dashboard (placeholder)</h1>
      <p class="m-sub">Live data from the existing backend — presentation is disposable.</p>
      <p v-if="loading" class="m-sub">Loading…</p>
      <p v-else-if="error" class="m-err">{{ error }}</p>
      <pre v-else style="white-space:pre-wrap;font-size:12px;color:#374151">{{ summary }}</pre>
    </div>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';

export default {
  name: 'MobileDashboard',
  data: () => ({ loading: true, error: '', summary: '' }),
  computed: {
    greeting() {
      const u = store.user;
      const name = u?.first_name || u?.name || u?.email || 'there';
      return `Welcome, ${name}.`;
    },
  },
  async created() {
    try {
      const { ok, data } = await apiGet('/api/v1/mobile/dashboard', store.token);
      if (ok) this.summary = JSON.stringify(data?.data ?? data, null, 2);
      else this.error = data?.message || 'Failed to load dashboard.';
    } catch (e) {
      this.error = 'Network error. Please try again.';
    } finally {
      this.loading = false;
    }
  },
  methods: {
    logout() {
      store.logout();
      this.$router.push({ name: 'login' });
    },
  },
};
</script>
