<template>
  <div>
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-black text-horizon-500">Activity Log</h1>
      <button
        class="flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg bg-raspberry-500 text-white shadow-sm hover:bg-raspberry-600 hover:shadow-md transition-all"
        @click="showActivityForm = true"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Log Activity
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <!-- Client filter -->
      <select
        v-model="filters.client_id"
        class="px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
        @change="loadActivities"
      >
        <option value="">All Clients</option>
        <option
          v-for="client in clients"
          :key="client.client_id"
          :value="client.client_id"
        >
          {{ client.display_name }}
        </option>
      </select>

      <!-- Activity type filter -->
      <select
        v-model="filters.activity_type"
        class="px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
        @change="loadActivities"
      >
        <option value="">All Types</option>
        <option value="email">Email</option>
        <option value="phone">Phone</option>
        <option value="meeting">Meeting</option>
        <option value="letter">Letter</option>
        <option value="suitability_report">Suitability Report</option>
        <option value="review">Review</option>
        <option value="note">Note</option>
      </select>

      <!-- Date from -->
      <div class="flex items-center gap-1.5">
        <span class="text-xs text-neutral-500">From</span>
        <input
          v-model="filters.date_from"
          type="date"
          class="px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
          @change="loadActivities"
        />
      </div>

      <!-- Date to -->
      <div class="flex items-center gap-1.5">
        <span class="text-xs text-neutral-500">To</span>
        <input
          v-model="filters.date_to"
          type="date"
          class="px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
          @change="loadActivities"
        />
      </div>

      <!-- Clear filters -->
      <button
        v-if="hasActiveFilters"
        class="px-3 py-2 text-sm font-semibold text-neutral-500 hover:text-horizon-500 transition-colors"
        @click="clearFilters"
      >
        Clear filters
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Activity List -->
    <div v-else-if="activities.length > 0" class="bg-white border border-light-gray rounded-xl overflow-hidden shadow-card">
      <div
        v-for="(activity, index) in activities"
        :key="activity.id || index"
        class="flex items-start gap-4 p-5 border-b border-light-gray last:border-b-0 hover:bg-savannah-100 transition-colors"
      >
        <!-- Activity icon -->
        <div
          class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
          :class="activityIconClass(activity.activity_type || activity.type)"
        >
          <!-- Email icon -->
          <svg v-if="(activity.activity_type || activity.type) === 'email'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
            <polyline points="22,6 12,13 2,6" />
          </svg>
          <!-- Phone icon -->
          <svg v-else-if="(activity.activity_type || activity.type) === 'phone'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#20B486" stroke-width="2">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.98.37 1.94.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.87.33 1.83.57 2.81.7A2 2 0 0 1 22 16.92z" />
          </svg>
          <!-- Suitability Report icon -->
          <svg v-else-if="(activity.activity_type || activity.type) === 'suitability_report'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E83E6D" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
          </svg>
          <!-- Meeting icon -->
          <svg v-else-if="(activity.activity_type || activity.type) === 'meeting'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E6C9A8" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
          </svg>
          <!-- Letter icon -->
          <svg v-else-if="(activity.activity_type || activity.type) === 'letter'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6C83BC" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
            <polyline points="22,6 12,13 2,6" />
          </svg>
          <!-- Review icon -->
          <svg v-else-if="(activity.activity_type || activity.type) === 'review'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
          </svg>
          <!-- Default / Note icon -->
          <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6C83BC" stroke-width="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
          </svg>
        </div>

        <!-- Activity content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 mb-1">
            <span class="text-sm font-bold text-horizon-500">
              {{ formatActivityType(activity.activity_type || activity.type) }}
            </span>
            <span
              v-if="activity.client_name"
              class="text-xs font-semibold px-2 py-0.5 rounded-full bg-violet-50 text-violet-700"
            >
              {{ activity.client_name }}
            </span>
          </div>
          <div v-if="activity.summary" class="text-sm text-horizon-500 leading-relaxed">
            {{ activity.summary }}
          </div>
          <div v-if="activity.details" class="text-xs text-neutral-500 mt-1 leading-relaxed">
            {{ activity.details }}
          </div>
          <div class="flex items-center gap-4 mt-2">
            <span class="text-xs text-neutral-500">
              {{ formatDateShort(activity.activity_date || activity.date || activity.created_at) }}
              — {{ getRelativeTime(activity.activity_date || activity.date || activity.created_at) }}
            </span>
            <span
              v-if="activity.follow_up_date"
              class="text-xs text-violet-500 font-semibold"
            >
              Follow-up: {{ formatDateShort(activity.follow_up_date) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white border border-light-gray rounded-xl p-12 shadow-card text-center">
      <div class="w-14 h-14 rounded-full bg-light-blue-100 flex items-center justify-center mx-auto mb-4">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6C83BC" stroke-width="2">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
      </div>
      <div class="text-base font-bold text-horizon-500 mb-1">No activities found</div>
      <div class="text-sm text-neutral-500">
        {{ hasActiveFilters ? 'Try adjusting your filters.' : 'Log your first activity to get started.' }}
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="totalPages > 1"
      class="flex items-center justify-between mt-4"
    >
      <div class="text-sm text-neutral-500">
        Page {{ currentPage }} of {{ totalPages }}
      </div>
      <div class="flex gap-1">
        <button
          :disabled="currentPage === 1"
          class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
          @click="goToPage(currentPage - 1)"
        >
          Previous
        </button>
        <button
          :disabled="currentPage === totalPages"
          class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
          @click="goToPage(currentPage + 1)"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Activity Form Modal -->
    <ClientActivityForm
      :visible="showActivityForm"
      :clients="clients"
      @save="handleSaveActivity"
      @close="showActivityForm = false"
    />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import ClientActivityForm from '@/components/Advisor/ClientActivityForm.vue';
import advisorService from '@/services/advisorService';
import { getRelativeTime, formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'AdvisorActivityLog',

  components: {
    ClientActivityForm,
  },

  data() {
    return {
      activities: [],
      loading: false,
      showActivityForm: false,
      currentPage: 1,
      totalPages: 1,
      perPage: 20,
      filters: {
        client_id: '',
        activity_type: '',
        date_from: '',
        date_to: '',
      },
    };
  },

  computed: {
    ...mapGetters('advisor', ['clients']),

    hasActiveFilters() {
      return (
        this.filters.client_id !== '' ||
        this.filters.activity_type !== '' ||
        this.filters.date_from !== '' ||
        this.filters.date_to !== ''
      );
    },
  },

  mounted() {
    // Read initial type filter from route query
    if (this.$route.query.type) {
      const types = this.$route.query.type.split(',');
      if (types.length === 1) {
        this.filters.activity_type = types[0];
      }
    }

    // Ensure clients are loaded for the filter dropdown
    if (this.clients.length === 0) {
      this.$store.dispatch('advisor/fetchClients');
    }

    this.loadActivities();
  },

  methods: {
    async loadActivities() {
      this.loading = true;

      try {
        const params = {
          page: this.currentPage,
          per_page: this.perPage,
        };

        if (this.filters.client_id) {
          params.client_id = this.filters.client_id;
        }
        if (this.filters.activity_type) {
          params.activity_type = this.filters.activity_type;
        }
        if (this.filters.date_from) {
          params.date_from = this.filters.date_from;
        }
        if (this.filters.date_to) {
          params.date_to = this.filters.date_to;
        }

        // If route has comma-separated types (e.g. Communications link), pass them
        if (this.$route.query.type && !this.filters.activity_type) {
          params.type = this.$route.query.type;
        }

        const response = await advisorService.getActivities(params);
        // Response: {success, data: {current_page, data: [...], last_page, ...}}
        const paginated = response.data || response;

        if (Array.isArray(paginated)) {
          this.activities = paginated;
          this.totalPages = 1;
        } else {
          this.activities = paginated.data || [];
          this.totalPages = paginated.last_page || paginated.meta?.last_page || 1;
          this.currentPage = paginated.current_page || paginated.meta?.current_page || 1;
        }
      } catch {
        this.activities = [];
      } finally {
        this.loading = false;
      }
    },

    goToPage(page) {
      if (page < 1 || page > this.totalPages) return;
      this.currentPage = page;
      this.loadActivities();
    },

    clearFilters() {
      this.filters = {
        client_id: '',
        activity_type: '',
        date_from: '',
        date_to: '',
      };
      this.currentPage = 1;
      // Also clear route query if present
      if (this.$route.query.type) {
        this.$router.replace({ query: {} });
      }
      this.loadActivities();
    },

    async handleSaveActivity(activityData) {
      try {
        await this.$store.dispatch('advisor/createActivity', activityData);
        this.showActivityForm = false;
        this.loadActivities();
      } catch {
        // Error handled in store
      }
    },

    formatActivityType(type) {
      if (!type) return 'Activity';
      const labels = {
        email: 'Email',
        phone: 'Phone Call',
        meeting: 'Meeting',
        letter: 'Letter',
        suitability_report: 'Suitability Report',
        review: 'Review',
        note: 'Note',
      };
      return labels[type] || type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ');
    },

    activityIconClass(type) {
      switch (type) {
        case 'email': return 'bg-violet-50';
        case 'phone': return 'bg-spring-50';
        case 'suitability_report': return 'bg-raspberry-50';
        case 'meeting': return 'bg-savannah-100';
        default: return 'bg-light-blue-100';
      }
    },

    formatDateShort(dateStr) {
      if (!dateStr) return '';
      return formatDateLong(dateStr, true);
    },

    getRelativeTime(dateStr) {
      return getRelativeTime(dateStr);
    },
  },
};
</script>
