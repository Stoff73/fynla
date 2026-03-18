<template>
  <div>
    <!-- Back button & Enter Profile -->
    <div class="flex items-center justify-between mb-6">
      <button
        class="flex items-center gap-2 text-sm font-semibold text-neutral-500 hover:text-horizon-500 transition-colors"
        @click="$router.push('/advisor/clients')"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5" />
          <polyline points="12 19 5 12 12 5" />
        </svg>
        Back to Clients
      </button>
      <button
        v-if="client"
        class="flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg bg-raspberry-500 text-white shadow-sm hover:bg-raspberry-600 hover:shadow-md transition-all"
        @click="enterClientProfile"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
          <polyline points="10 17 15 12 10 7" />
          <line x1="15" y1="12" x2="3" y2="12" />
        </svg>
        Enter Profile
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-white border border-light-gray rounded-xl p-8 shadow-card text-center">
      <div class="text-raspberry-500 font-semibold mb-2">Unable to load client</div>
      <div class="text-sm text-neutral-500">{{ error }}</div>
    </div>

    <!-- Client Detail -->
    <div v-else-if="client">
      <!-- Profile Card -->
      <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card mb-6">
        <div class="flex items-start gap-5">
          <div
            class="w-16 h-16 rounded-full flex items-center justify-center font-bold text-xl text-white flex-shrink-0"
            :style="{ background: getAvatarColour(client) }"
          >
            {{ getInitials(client.display_name) }}
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-black text-horizon-500 mb-1">{{ client.display_name }}</h1>
            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-neutral-500">
              <div v-if="client.assigned_date">
                <span class="font-semibold text-horizon-500">Assigned:</span> {{ formatDateShort(client.assigned_date) }}
              </div>
              <div v-if="client.review_frequency_months">
                <span class="font-semibold text-horizon-500">Review Schedule:</span> {{ formatFrequency(client.review_frequency_months) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Two-column grid -->
      <div class="grid grid-cols-2 gap-6 mb-6">
        <!-- Module Status -->
        <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card">
          <h2 class="text-lg font-bold text-horizon-500 mb-4">Module Status</h2>
          <div class="mb-3">
            <ClientModuleDots :module-status="client.module_status" size="large" />
          </div>
          <div class="flex gap-4 mt-4 pt-4 border-t border-light-gray">
            <div class="flex items-center gap-1.5 text-xs text-neutral-500">
              <div class="w-4 h-4 rounded bg-spring-500"></div>
              Complete
            </div>
            <div class="flex items-center gap-1.5 text-xs text-neutral-500">
              <div class="w-4 h-4 rounded bg-violet-500"></div>
              Partial
            </div>
            <div class="flex items-center gap-1.5 text-xs text-neutral-500">
              <div class="w-4 h-4 rounded bg-light-gray"></div>
              No Data
            </div>
            <div class="flex items-center gap-1.5 text-xs text-neutral-500">
              <div class="w-4 h-4 rounded bg-eggshell-500 border border-light-gray"></div>
              Skipped
            </div>
          </div>
        </div>

        <!-- Review Info -->
        <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card">
          <h2 class="text-lg font-bold text-horizon-500 mb-4">Review Information</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Next Review Due</span>
              <span
                class="text-sm font-semibold"
                :class="isReviewOverdue ? 'text-raspberry-500' : 'text-horizon-500'"
              >
                {{ client.next_review_due ? formatDateShort(client.next_review_due) : 'Not scheduled' }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Last Review</span>
              <span class="text-sm font-semibold text-horizon-500">
                {{ client.last_review_date ? formatDateShort(client.last_review_date) : 'No reviews yet' }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Frequency</span>
              <span class="text-sm font-semibold text-horizon-500">
                {{ formatFrequency(client.review_frequency_months) }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Status</span>
              <span
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="client.status === 'review_due'
                  ? 'bg-raspberry-50 text-raspberry-700'
                  : 'bg-spring-100 text-spring-700'"
              >
                {{ client.status === 'review_due' ? 'Review Due' : 'Active' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity Timeline -->
      <div class="bg-white border border-light-gray rounded-xl shadow-card">
        <div class="flex items-center justify-between p-6 border-b border-light-gray">
          <h2 class="text-lg font-bold text-horizon-500">Activity Timeline</h2>
          <span class="text-xs text-neutral-500">{{ activities.length }} activities</span>
        </div>
        <div v-if="activities.length === 0" class="p-8 text-center text-neutral-500">
          No activity recorded for this client.
        </div>
        <div v-else class="divide-y divide-light-gray">
          <div
            v-for="(activity, index) in activities"
            :key="activity.id || index"
            class="flex items-start gap-4 p-5 hover:bg-savannah-100 transition-colors"
          >
            <div
              class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
              :class="activityIconClass(activity.activity_type || activity.type)"
            >
              <!-- Email icon -->
              <svg v-if="(activity.activity_type || activity.type) === 'email'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
              <!-- Phone icon -->
              <svg v-else-if="(activity.activity_type || activity.type) === 'phone'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#20B486" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.98.37 1.94.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.87.33 1.83.57 2.81.7A2 2 0 0 1 22 16.92z" />
              </svg>
              <!-- Suitability Report icon -->
              <svg v-else-if="(activity.activity_type || activity.type) === 'suitability_report'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E83E6D" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
              <!-- Meeting icon -->
              <svg v-else-if="(activity.activity_type || activity.type) === 'meeting'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E6C9A8" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
              </svg>
              <!-- Review icon -->
              <svg v-else-if="(activity.activity_type || activity.type) === 'review'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
              </svg>
              <!-- Default / Note / Letter icon -->
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6C83BC" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-0.5">
                <span class="text-sm font-semibold text-horizon-500">
                  {{ formatActivityType(activity.activity_type || activity.type) }}
                </span>
                <span class="text-xs text-neutral-500">
                  {{ formatDateShort(activity.activity_date || activity.date || activity.created_at) }}
                </span>
              </div>
              <div v-if="activity.summary" class="text-sm text-horizon-500 leading-relaxed">
                {{ activity.summary }}
              </div>
              <div v-if="activity.details" class="text-xs text-neutral-500 mt-1 leading-relaxed">
                {{ activity.details }}
              </div>
              <div v-if="activity.follow_up_date" class="text-xs text-violet-500 font-semibold mt-1">
                Follow-up: {{ formatDateShort(activity.follow_up_date) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import ClientModuleDots from '@/components/Advisor/ClientModuleDots.vue';
import { getRelativeTime, formatDateLong } from '@/utils/dateFormatter';
import advisorService from '@/services/advisorService';

export default {
  name: 'AdvisorClientDetail',

  components: {
    ClientModuleDots,
  },

  data() {
    return {
      activities: [],
      activitiesLoading: false,
    };
  },

  computed: {
    ...mapGetters('advisor', ['loading', 'error']),

    client() {
      return this.$store.state.advisor.clientDetail;
    },

    clientId() {
      return parseInt(this.$route.params.id, 10);
    },

    isReviewOverdue() {
      if (!this.client || !this.client.next_review_due) return false;
      return new Date(this.client.next_review_due) < new Date();
    },
  },

  mounted() {
    this.loadClientDetail();
  },

  methods: {
    async loadClientDetail() {
      try {
        await this.$store.dispatch('advisor/fetchClientDetail', this.clientId);
      } catch {
        // Error stored in Vuex
      }

      try {
        this.activitiesLoading = true;
        const data = await advisorService.getActivities({ client_id: this.clientId });
        const inner = data.data || data;
        this.activities = Array.isArray(inner) ? inner : (inner.data || []);
      } catch {
        this.activities = [];
      } finally {
        this.activitiesLoading = false;
      }
    },

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

    formatFrequency(frequency) {
      if (!frequency) return 'Annual';
      if (typeof frequency === 'number') {
        const monthLabels = { 1: 'Monthly', 3: 'Quarterly', 6: 'Semi-Annual', 12: 'Annual', 24: 'Biennial' };
        return monthLabels[frequency] || `Every ${frequency} months`;
      }
      const labels = {
        annually: 'Annual',
        semi_annually: 'Semi-Annual',
        quarterly: 'Quarterly',
        monthly: 'Monthly',
      };
      return labels[frequency] || frequency.charAt(0).toUpperCase() + frequency.slice(1).replace(/_/g, ' ');
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

    async enterClientProfile() {
      try {
        await this.$store.dispatch('advisor/enterClient', this.clientId);
        this.$router.push('/dashboard');
      } catch {
        // Error handled in store
      }
    },
  },
};
</script>
