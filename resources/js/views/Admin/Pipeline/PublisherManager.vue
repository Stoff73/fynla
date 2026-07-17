<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <p class="text-xs text-neutral-500 uppercase tracking-wide mb-1">Admin › Pipeline › Publishers</p>
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Publisher whitelist</h1>
        <p class="text-sm text-neutral-500 mt-1">Users on this list can push articles to <strong>fynla.org (live)</strong>. Anyone else can push to local + dev only.</p>
      </header>

      <div class="card mb-6">
        <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Grant publish-to-live</h3>
        <div class="relative">
          <input
            v-model="search"
            @input="onSearch"
            type="text"
            placeholder="Search users by name or email…"
            class="w-full px-3 py-2 text-sm border border-light-gray rounded"
          />
          <div v-if="searchResults.length" class="absolute z-10 bg-white border border-savannah-100 rounded shadow mt-1 w-full max-h-60 overflow-y-auto">
            <button
              v-for="u in searchResults"
              :key="u.id"
              @click="grant(u)"
              class="w-full text-left px-3 py-2 text-sm hover:bg-eggshell-50 border-b border-savannah-100 last:border-b-0"
            >
              <strong class="text-horizon-500">{{ u.name }}</strong>
              <span class="text-neutral-500 ml-2">{{ u.email }}</span>
            </button>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Current publishers ({{ publishers.length }})</h3>
        <div v-if="loading" class="flex justify-center py-8">
          <div class="w-8 h-8 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="publishers.length === 0" class="text-sm text-neutral-500 italic py-4">
          No publishers granted yet. Search above to add one.
        </div>
        <table v-else class="w-full text-sm">
          <thead class="text-xs uppercase text-horizon-400 border-b border-savannah-100">
            <tr>
              <th class="text-left py-2">User</th>
              <th class="text-left py-2">Granted by</th>
              <th class="text-left py-2">Granted</th>
              <th class="text-right py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in publishers" :key="p.id" class="border-b border-savannah-100 last:border-b-0">
              <td class="py-3">
                <strong class="text-horizon-500">{{ p.user?.name }}</strong>
                <span class="text-neutral-500 ml-2">{{ p.user?.email }}</span>
              </td>
              <td class="py-3 text-horizon-400">{{ p.granted_by_user?.name || p.grantedBy?.name || '—' }}</td>
              <td class="py-3 text-horizon-400">{{ formatDate(p.granted_at) }}</td>
              <td class="py-3 text-right">
                <button
                  @click="revoke(p)"
                  class="px-3 py-1 text-xs border border-raspberry-500 text-raspberry-500 rounded hover:bg-raspberry-50"
                >Revoke</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';

export default {
  name: 'PublisherManager',
  components: { AppLayout },
  data() {
    return {
      loading: true,
      publishers: [],
      search: '',
      searchResults: [],
      searchTimer: null,
    };
  },
  async mounted() {
    await this.refresh();
  },
  methods: {
    async refresh() {
      this.loading = true;
      try {
        const res = (await api.get('/admin/pipeline/publishers')).data;
        this.publishers = res.data || [];
      } finally {
        this.loading = false;
      }
    },
    onSearch() {
      clearTimeout(this.searchTimer);
      const term = this.search.trim();
      if (term.length < 2) {
        this.searchResults = [];
        return;
      }
      this.searchTimer = setTimeout(async () => {
        const res = (await api.get('/admin/pipeline/publishers/search-users', { params: { q: term } })).data;
        this.searchResults = res.data || [];
      }, 250);
    },
    async grant(user) {
      if (!confirm(`Grant ${user.name} permission to push articles to LIVE?`)) return;
      try {
        await api.post('/admin/pipeline/publishers', { user_id: user.id });
        this.search = '';
        this.searchResults = [];
        await this.refresh();
      } catch (e) {
        alert('Grant failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    async revoke(publisher) {
      if (!confirm(`Revoke ${publisher.user?.name}'s publish-to-live permission?`)) return;
      try {
        await api.delete(`/admin/pipeline/publishers/${publisher.user_id}`);
        await this.refresh();
      } catch (e) {
        alert('Revoke failed: ' + (e?.response?.data?.message ?? e.message));
      }
    },
    formatDate(iso) {
      if (!iso) return '';
      return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  },
};
</script>
