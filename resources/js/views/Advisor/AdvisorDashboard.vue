<template>
  <div>
    <!-- Stats Row -->
    <div class="grid grid-cols-4 gap-6 mb-6">
      <!-- Active Clients -->
      <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
            </svg>
          </div>
        </div>
        <div class="text-3xl font-black text-horizon-500 leading-none tracking-tight">
          {{ stats.clients || 0 }}
        </div>
        <div class="text-sm text-neutral-500 mt-1">Active Clients</div>
      </div>

      <!-- Reviews Due -->
      <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-raspberry-50 flex items-center justify-center">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E83E6D" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
            </svg>
          </div>
          <span
            v-if="overdueCount > 0"
            class="text-xs font-semibold px-2 py-0.5 rounded-full bg-raspberry-50 text-raspberry-700"
          >
            {{ overdueCount }} overdue
          </span>
        </div>
        <div class="text-3xl font-black leading-none tracking-tight text-raspberry-500">
          {{ stats.reviewsDue || 0 }}
        </div>
        <div class="text-sm text-neutral-500 mt-1">Reviews Due</div>
      </div>

      <!-- Communications -->
      <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-spring-50 flex items-center justify-center">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#20B486" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg>
          </div>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-spring-100 text-spring-700">
            This week
          </span>
        </div>
        <div class="text-3xl font-black text-horizon-500 leading-none tracking-tight">
          {{ stats.commsThisWeek || 0 }}
        </div>
        <div class="text-sm text-neutral-500 mt-1">Communications</div>
      </div>

      <!-- Reports Sent -->
      <div class="bg-white border border-light-gray rounded-xl p-6 shadow-card">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-light-blue-100 flex items-center justify-center">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6C83BC" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
            </svg>
          </div>
        </div>
        <div class="text-3xl font-black text-horizon-500 leading-none tracking-tight">
          {{ stats.reportsThisMonth || 0 }}
        </div>
        <div class="text-sm text-neutral-500 mt-1">Reports Sent (This Month)</div>
      </div>
    </div>

    <!-- Client Overview Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-horizon-500">Client Overview</h2>
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
            class="pl-9 pr-4 py-2 text-sm border border-light-gray rounded-md w-60 text-horizon-500 bg-white focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
          />
        </div>
        <button class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 hover:shadow-md transition-all">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 6h18M3 12h12M3 18h6" />
          </svg>
          Filter
        </button>
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
    <div class="bg-white border border-light-gray rounded-xl overflow-hidden mb-8 shadow-card">
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
            v-for="client in filteredClients"
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
                  {{ getRelativeTime(client.last_communication.activity_date) }}
                </div>
              </div>
              <span v-else class="text-neutral-500">--</span>
            </td>

            <!-- Last Report -->
            <td class="p-4 text-sm border-b border-light-gray">
              <div v-if="client.last_report">
                <div class="font-semibold text-horizon-500">
                  {{ formatDateShort(client.last_report.report_sent_date || client.last_report.activity_date) }}
                </div>
                <div class="text-xs text-neutral-500 mt-0.5">
                  {{ formatReportType(client.last_report.report_type) }}
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

    <!-- Bottom Grid -->
    <div class="grid grid-cols-2 gap-6">
      <!-- Reviews Due -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold text-horizon-500">Reviews Due</h2>
          <button
            class="flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 hover:shadow-md transition-all"
            @click="$router.push('/advisor/reviews')"
          >
            View All
          </button>
        </div>
        <div v-if="reviewsDue.length === 0" class="bg-white border border-light-gray rounded-xl p-6 shadow-card text-center text-neutral-500">
          No reviews due.
        </div>
        <div
          v-for="review in reviewsDue"
          :key="review.client_id"
          class="bg-white border border-light-gray rounded-xl p-5 shadow-card mb-4"
        >
          <div class="flex items-center justify-between mb-3">
            <div class="font-bold text-base text-horizon-500">{{ review.display_name }}</div>
            <div
              class="text-xs font-bold"
              :class="review.is_overdue ? 'text-raspberry-500' : 'text-neutral-500'"
            >
              {{ review.is_overdue ? `${review.days_overdue} days overdue` : `Due in ${review.days_until_due} days` }}
            </div>
          </div>
          <div class="text-sm text-neutral-500 leading-relaxed">
            <strong class="text-horizon-500 font-semibold">{{ formatReviewFrequency(review.review_frequency_months) }}</strong>
            — Last reviewed {{ formatDateShort(review.last_review_date) }}
            <br />
            <span v-if="review.focus">{{ review.focus }}<br /></span>
            <span v-if="review.action" class="text-raspberry-500 font-semibold">Action: {{ review.action }}</span>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold text-horizon-500">Recent Activity</h2>
          <button
            class="flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 hover:shadow-md transition-all"
            @click="$router.push('/advisor/activities')"
          >
            Log Activity
          </button>
        </div>
        <div class="bg-white border border-light-gray rounded-xl overflow-hidden shadow-card">
          <div v-if="recentActivities.length === 0" class="p-6 text-center text-neutral-500">
            No recent activity.
          </div>
          <div
            v-for="(activity, index) in recentActivities"
            :key="activity.id || index"
            class="flex items-start gap-3 py-3.5 px-4 border-b border-light-gray last:border-b-0 hover:bg-savannah-100 transition-colors"
          >
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
              :class="activityIconClass(activity.activity_type)"
            >
              <!-- Email icon -->
              <svg v-if="activity.activity_type === 'email'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5854E6" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
              <!-- Phone icon -->
              <svg v-else-if="activity.activity_type === 'phone'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#20B486" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.98.37 1.94.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.87.33 1.83.57 2.81.7A2 2 0 0 1 22 16.92z" />
              </svg>
              <!-- Report icon -->
              <svg v-else-if="activity.activity_type === 'suitability_report'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E83E6D" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
              <!-- Meeting icon -->
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E6C9A8" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
              </svg>
            </div>
            <div>
              <div class="text-sm text-horizon-500 leading-normal">
                <strong class="font-semibold">{{ activityLabel(activity) }}</strong>
                {{ activityDescription(activity) }}
              </div>
              <div class="text-xs text-neutral-500 mt-0.5">
                {{ formatDateShort(activity.activity_date || activity.created_at) }}
                — {{ getRelativeTime(activity.activity_date || activity.created_at) }}
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
  name: 'AdvisorDashboard',

  components: {
    ClientModuleDots,
  },

  data() {
    return {
      searchQuery: '',
      recentActivities: [],
    };
  },

  computed: {
    ...mapGetters('advisor', ['clients', 'overdueReviews', 'loading']),

    stats() {
      return this.$store.state.advisor.dashboardStats || {};
    },

    reviewsDue() {
      return this.$store.state.advisor.reviewsDue || [];
    },

    overdueCount() {
      return this.overdueReviews.length;
    },

    filteredClients() {
      if (!this.searchQuery.trim()) {
        return this.clients;
      }
      const query = this.searchQuery.toLowerCase().trim();
      return this.clients.filter(
        (c) => c.display_name && c.display_name.toLowerCase().includes(query)
      );
    },
  },

  mounted() {
    this.loadData();
  },

  methods: {
    async loadData() {
      try {
        await Promise.all([
          this.$store.dispatch('advisor/fetchDashboard'),
          this.$store.dispatch('advisor/fetchClients'),
          this.$store.dispatch('advisor/fetchReviewsDue'),
        ]);
      } catch {
        // Errors are stored in Vuex state
      }

      try {
        const activitiesResponse = await advisorService.getActivities({ per_page: 5 });
        // API returns paginated: {success, data: {current_page, data: [...], ...}}
        const responseData = activitiesResponse.data || activitiesResponse;
        this.recentActivities = Array.isArray(responseData)
          ? responseData
          : (responseData.data || []);
      } catch {
        this.recentActivities = [];
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
      // Try to parse from notes.avatar_colour if available
      if (client.notes) {
        try {
          const notes = typeof client.notes === 'string' ? JSON.parse(client.notes) : client.notes;
          if (notes.avatar_colour) return notes.avatar_colour;
        } catch {
          // ignore parse error
        }
      }
      // Fallback: deterministic colour based on client_id
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
      const typeLabel = comm.activity_type ? comm.activity_type.charAt(0).toUpperCase() + comm.activity_type.slice(1) : '';
      const dateShort = comm.activity_date ? this.formatDateShort(comm.activity_date) : '';
      return typeLabel && dateShort ? `${typeLabel} \u2014 ${dateShort}` : typeLabel || dateShort;
    },

    activityIconClass(type) {
      switch (type) {
        case 'email': return 'bg-violet-50';
        case 'phone': return 'bg-spring-50';
        case 'suitability_report': return 'bg-raspberry-50';
        case 'meeting': return 'bg-savannah-100';
        default: return 'bg-violet-50';
      }
    },

    activityLabel(activity) {
      const type = activity.activity_type || activity.type;
      switch (type) {
        case 'email': return 'Email sent';
        case 'phone': return 'Phone call';
        case 'suitability_report': return 'Suitability report sent';
        case 'meeting': return 'Meeting';
        case 'letter': return 'Letter sent';
        case 'review': return 'Review';
        case 'note': return 'Note';
        default: return type || 'Activity';
      }
    },

    formatReviewFrequency(months) {
      if (!months) return 'Annual Review';
      const labels = { 1: 'Monthly Review', 3: 'Quarterly Review', 6: 'Semi-Annual Review', 12: 'Annual Review', 24: 'Biennial Review' };
      return labels[months] || `Review (Every ${months} months)`;
    },

    activityDescription(activity) {
      const clientName = activity.client_name || '';
      const summary = activity.summary || '';
      if (summary) return `\u2014 ${summary}`;
      if (clientName) return `\u2014 ${clientName}`;
      return '';
    },

    formatReportType(type) {
      if (!type) return '';
      return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
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
