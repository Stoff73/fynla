<template>
  <div>
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-black text-horizon-500">All Clients</h1>
      <div class="flex gap-3">
        <div class="relative">
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search clients..."
            class="pl-9 pr-4 py-2 text-sm border border-light-gray rounded-md w-64 text-horizon-500 bg-white focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
          />
        </div>
        <select
          v-model="statusFilter"
          class="px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
        >
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="review_due">Review Due</option>
        </select>
      </div>
    </div>

    <!-- Module Legend -->
    <div class="flex gap-4 mb-4 p-3 px-4 bg-white border border-light-gray rounded-lg items-center shadow-sm">
      <span class="text-xs font-bold text-horizon-500 mr-1">Module Status:</span>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <div class="w-[18px] h-[18px] rounded bg-spring-500 text-white flex items-center justify-center text-[9px] font-bold">P</div>
        Complete
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <div class="w-[18px] h-[18px] rounded bg-violet-500 text-white flex items-center justify-center text-[9px] font-bold">P</div>
        Partial
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <div class="w-[18px] h-[18px] rounded bg-light-gray text-neutral-500 flex items-center justify-center text-[9px] font-bold">P</div>
        No Data
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <div class="w-[18px] h-[18px] rounded bg-eggshell-500 text-horizon-300 flex items-center justify-center text-[9px] font-bold border border-light-gray line-through">P</div>
        Skipped
      </div>
      <span class="text-xs text-neutral-500 ml-3">
        P = Protection &nbsp; S = Savings &nbsp; I = Investment &nbsp; R = Retirement &nbsp; E = Estate
      </span>
    </div>

    <!-- Client Table -->
    <div class="bg-white border border-light-gray rounded-xl overflow-hidden shadow-card">
      <table class="w-full border-collapse">
        <thead>
          <tr>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Client</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Modules</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Last Review</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Last Communication</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Last Report</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Status</th>
            <th class="text-right p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="client in paginatedClients"
            :key="client.client_id"
            class="hover:bg-savannah-100 transition-colors"
          >
            <!-- Client name cell -->
            <td class="p-4 text-sm border-b border-light-gray">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-[13px] text-white flex-shrink-0"
                  :style="{ background: getAvatarColour(client) }"
                >
                  {{ getInitials(client.display_name) }}
                </div>
                <div>
                  <div class="font-bold text-horizon-500">{{ client.display_name }}</div>
                  <div class="text-xs text-neutral-500 mt-px">{{ client.client_type || '' }}</div>
                </div>
              </div>
            </td>

            <!-- Modules -->
            <td class="p-4 text-sm border-b border-light-gray">
              <ClientModuleDots :module-status="client.module_status" />
            </td>

            <!-- Last Review -->
            <td class="p-4 text-sm border-b border-light-gray">
              <div v-if="client.last_review_date">
                <div :class="isOverdue(client) ? 'text-raspberry-500 font-bold' : 'font-semibold text-horizon-500'">
                  {{ formatDateShort(client.last_review_date) }}
                </div>
                <div
                  class="text-xs mt-0.5"
                  :class="isOverdue(client) ? 'text-raspberry-500' : 'text-neutral-500'"
                >
                  {{ getTimeSince(client.last_review_date, isOverdue(client)) }}
                </div>
              </div>
              <span v-else class="text-neutral-500">--</span>
            </td>

            <!-- Last Communication -->
            <td class="p-4 text-sm border-b border-light-gray">
              <div v-if="client.last_communication">
                <div class="font-semibold text-horizon-500">
                  {{ formatCommLabel(client.last_communication) }}
                </div>
                <div class="text-xs text-neutral-500 mt-0.5">
                  {{ getRelativeTime(client.last_communication.date) }}
                </div>
              </div>
              <span v-else class="text-neutral-500">--</span>
            </td>

            <!-- Last Report -->
            <td class="p-4 text-sm border-b border-light-gray">
              <div v-if="client.last_report">
                <div class="font-semibold text-horizon-500">
                  {{ formatDateShort(client.last_report.date) }}
                </div>
                <div class="text-xs text-neutral-500 mt-0.5">
                  {{ client.last_report.title || '' }}
                </div>
              </div>
              <span v-else class="text-neutral-500">--</span>
            </td>

            <!-- Status -->
            <td class="p-4 text-sm border-b border-light-gray">
              <span
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="client.status === 'review_due'
                  ? 'bg-raspberry-50 text-raspberry-700'
                  : 'bg-spring-100 text-spring-700'"
              >
                {{ client.status === 'review_due' ? 'Review Due' : 'Active' }}
              </span>
            </td>

            <!-- Actions -->
            <td class="p-4 text-sm border-b border-light-gray text-right">
              <div class="flex gap-1.5 justify-end">
                <button
                  class="px-3 py-1.5 text-[13px] font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 hover:shadow-md transition-all"
                  @click="viewClient(client)"
                >
                  View
                </button>
                <button
                  class="px-3 py-1.5 text-[13px] font-semibold rounded-lg bg-raspberry-500 text-white shadow-sm hover:bg-raspberry-600 hover:shadow-md transition-all"
                  @click="enterClientProfile(client)"
                >
                  Enter Profile
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredClients.length === 0">
            <td colspan="7" class="p-8 text-center text-neutral-500">
              {{ loading ? 'Loading clients...' : 'No clients found.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      v-if="totalPages > 1"
      class="flex items-center justify-between mt-4"
    >
      <div class="text-sm text-neutral-500">
        Showing {{ paginationStart }}–{{ paginationEnd }} of {{ filteredClients.length }} clients
      </div>
      <div class="flex gap-1">
        <button
          :disabled="currentPage === 1"
          class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
          @click="currentPage--"
        >
          Previous
        </button>
        <button
          v-for="page in visiblePages"
          :key="page"
          class="w-9 h-9 text-sm font-semibold rounded-lg border shadow-sm transition-all"
          :class="page === currentPage
            ? 'bg-raspberry-500 text-white border-raspberry-500'
            : 'border-light-gray bg-white text-horizon-500 hover:bg-savannah-100'"
          @click="currentPage = page"
        >
          {{ page }}
        </button>
        <button
          :disabled="currentPage === totalPages"
          class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
          @click="currentPage++"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import ClientModuleDots from '@/components/Advisor/ClientModuleDots.vue';
import { getRelativeTime, formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'AdvisorClientList',

  components: {
    ClientModuleDots,
  },

  data() {
    return {
      searchQuery: '',
      statusFilter: 'all',
      currentPage: 1,
      perPage: 15,
    };
  },

  computed: {
    ...mapGetters('advisor', ['clients', 'loading']),

    filteredClients() {
      let result = this.clients;

      if (this.statusFilter !== 'all') {
        result = result.filter((c) => c.status === this.statusFilter);
      }

      if (this.searchQuery.trim()) {
        const query = this.searchQuery.toLowerCase().trim();
        result = result.filter(
          (c) => c.display_name && c.display_name.toLowerCase().includes(query)
        );
      }

      return result;
    },

    totalPages() {
      return Math.max(1, Math.ceil(this.filteredClients.length / this.perPage));
    },

    paginatedClients() {
      const start = (this.currentPage - 1) * this.perPage;
      return this.filteredClients.slice(start, start + this.perPage);
    },

    paginationStart() {
      if (this.filteredClients.length === 0) return 0;
      return (this.currentPage - 1) * this.perPage + 1;
    },

    paginationEnd() {
      return Math.min(this.currentPage * this.perPage, this.filteredClients.length);
    },

    visiblePages() {
      const pages = [];
      const total = this.totalPages;
      const current = this.currentPage;

      let start = Math.max(1, current - 2);
      let end = Math.min(total, current + 2);

      if (end - start < 4) {
        if (start === 1) {
          end = Math.min(total, start + 4);
        } else {
          start = Math.max(1, end - 4);
        }
      }

      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      return pages;
    },
  },

  watch: {
    searchQuery() {
      this.currentPage = 1;
    },
    statusFilter() {
      this.currentPage = 1;
    },
  },

  mounted() {
    this.$store.dispatch('advisor/fetchClients');
  },

  methods: {
    getInitials(name) {
      if (!name) return '';
      const parts = name.split(' ').filter(Boolean);
      if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
      }
      return (parts[0] || '').substring(0, 2).toUpperCase();
    },

    getAvatarColour(client) {
      if (client.notes) {
        try {
          const notes = typeof client.notes === 'string' ? JSON.parse(client.notes) : client.notes;
          if (notes.avatar_colour) return notes.avatar_colour;
        } catch {
          // ignore parse error
        }
      }
      const colours = ['#5854E6', '#E83E6D', '#20B486', '#E6C9A8', '#6C83BC', '#1F2A44'];
      return colours[(client.client_id || 0) % colours.length];
    },

    formatDateShort(dateStr) {
      if (!dateStr) return '';
      return formatDateLong(dateStr, true);
    },

    getRelativeTime(dateStr) {
      return getRelativeTime(dateStr);
    },

    getTimeSince(dateStr, overdue) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      const now = new Date();
      const diffMs = now.getTime() - date.getTime();
      const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

      if (diffDays < 0) return '';
      if (overdue) {
        return `${diffDays} days overdue`;
      }
      return `${diffDays} days ago`;
    },

    isOverdue(client) {
      return client.status === 'review_due' && client.is_review_overdue;
    },

    formatCommLabel(comm) {
      if (!comm) return '';
      const typeLabel = comm.type ? comm.type.charAt(0).toUpperCase() + comm.type.slice(1) : '';
      const dateShort = comm.date ? this.formatDateShort(comm.date) : '';
      return typeLabel && dateShort ? `${typeLabel} \u2014 ${dateShort}` : typeLabel || dateShort;
    },

    viewClient(client) {
      this.$router.push(`/advisor/clients/${client.client_id}`);
    },

    async enterClientProfile(client) {
      try {
        await this.$store.dispatch('advisor/enterClient', client.client_id);
        this.$router.push('/dashboard');
      } catch {
        // Error handled in store
      }
    },
  },
};
</script>
