<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">News subscribers</h1>
          <p class="text-sm text-neutral-500 mt-1">{{ meta?.total ?? 0 }} total subscribers</p>
        </div>
        <button
          type="button"
          class="px-5 py-2.5 bg-raspberry-500 text-white rounded-lg text-sm font-semibold hover:bg-raspberry-600 transition-colors"
          @click="downloadCsv"
        >
          Export CSV
        </button>
      </header>

      <div class="flex flex-wrap gap-3 mb-6">
        <button
          v-for="filter in statusFilters"
          :key="filter.value"
          type="button"
          class="px-3 py-1.5 text-sm rounded-full transition-colors"
          :class="status === filter.value ? 'bg-horizon-500 text-white' : 'bg-light-gray text-horizon-500 hover:bg-savannah-100'"
          @click="setStatus(filter.value)"
        >
          {{ filter.label }}
        </button>
        <input
          v-model="search"
          type="search"
          placeholder="Search by email…"
          class="ml-auto px-3 py-1.5 text-sm rounded-lg border border-light-gray bg-white text-horizon-500"
          @input="debouncedFetch"
        />
      </div>

      <div v-if="loading" class="text-center text-neutral-500 py-12">Loading…</div>
      <div v-else-if="error" class="text-center text-raspberry-500 py-12">{{ error }}</div>
      <div v-else-if="!subscribers.length" class="text-center text-neutral-500 py-12">No subscribers match your filters.</div>

      <div v-else class="card overflow-hidden">
        <table class="w-full">
          <thead class="bg-savannah-100 text-xs text-horizon-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Source</th>
              <th class="px-4 py-3 text-left">Signed up</th>
              <th class="px-4 py-3 text-left">Confirmed</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in subscribers"
              :key="s.id"
              class="border-t border-light-gray hover:bg-savannah-100"
            >
              <td class="px-4 py-3 text-horizon-500">{{ s.email }}</td>
              <td class="px-4 py-3">
                <span :class="statusBadgeClass(s.status)">{{ s.status }}</span>
              </td>
              <td class="px-4 py-3 text-neutral-500">{{ s.source }}</td>
              <td class="px-4 py-3 text-neutral-500">{{ formatDate(s.created_at) }}</td>
              <td class="px-4 py-3 text-neutral-500">{{ formatDate(s.confirmed_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-6">
        <button
          type="button"
          class="text-sm font-semibold text-horizon-500 disabled:opacity-40 disabled:cursor-not-allowed hover:text-raspberry-500 transition-colors"
          :disabled="page <= 1"
          @click="changePage(page - 1)"
        >
          Previous
        </button>
        <span class="text-sm text-neutral-500">Page {{ page }} of {{ meta.last_page }}</span>
        <button
          type="button"
          class="text-sm font-semibold text-horizon-500 disabled:opacity-40 disabled:cursor-not-allowed hover:text-raspberry-500 transition-colors"
          :disabled="page >= meta.last_page"
          @click="changePage(page + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';

export default {
  name: 'NewsSubscribersPage',
  components: { AppLayout },

  data() {
    return {
      subscribers: [],
      meta: null,
      page: 1,
      status: 'all',
      search: '',
      loading: false,
      error: null,
      debounceTimer: null,
      statusFilters: [
        { value: 'all', label: 'All' },
        { value: 'confirmed', label: 'Confirmed' },
        { value: 'pending', label: 'Pending' },
        { value: 'unsubscribed', label: 'Unsubscribed' },
      ],
    };
  },

  mounted() {
    document.title = 'News subscribers | Fynla admin';
    this.fetchSubscribers();
  },

  methods: {
    async fetchSubscribers() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await api.get('/admin/news-subscribers', {
          params: { page: this.page, status: this.status, search: this.search || undefined },
        });
        this.subscribers = data.data;
        this.meta = data.meta;
      } catch (err) {
        this.error = 'Could not load subscribers.';
      } finally {
        this.loading = false;
      }
    },
    setStatus(value) {
      this.status = value;
      this.page = 1;
      this.fetchSubscribers();
    },
    debouncedFetch() {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.page = 1;
        this.fetchSubscribers();
      }, 250);
    },
    changePage(newPage) {
      this.page = newPage;
      this.fetchSubscribers();
    },
    async downloadCsv() {
      try {
        const response = await api.get('/admin/news-subscribers/export', { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `news-subscribers-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
      } catch (err) {
        this.error = 'Could not export CSV.';
      }
    },
    formatDate(iso) {
      if (!iso) return '—';
      return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    statusBadgeClass(status) {
      const map = {
        confirmed: 'inline-block px-2 py-0.5 text-[0.65rem] font-bold rounded uppercase bg-spring-100 text-spring-700',
        pending: 'inline-block px-2 py-0.5 text-[0.65rem] font-bold rounded uppercase bg-violet-100 text-violet-700',
        unsubscribed: 'inline-block px-2 py-0.5 text-[0.65rem] font-bold rounded uppercase bg-light-gray text-neutral-500',
      };
      return map[status] || '';
    },
  },
};
</script>
