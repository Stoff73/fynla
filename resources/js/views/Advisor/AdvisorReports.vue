<template>
  <div>
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-black text-horizon-500">Suitability Reports</h1>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Reports Table -->
    <div v-else-if="reports.length > 0" class="bg-white border border-light-gray rounded-xl overflow-hidden shadow-card">
      <table class="w-full border-collapse">
        <thead>
          <tr>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Client</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Report Type</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Sent Date</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Acknowledged Date</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Summary</th>
            <th class="text-left p-3 px-4 text-sm font-semibold text-neutral-500 border-b border-light-gray">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(report, index) in reports"
            :key="report.id || index"
            class="hover:bg-savannah-100 transition-colors"
          >
            <td class="p-4 text-sm border-b border-light-gray">
              <span class="font-semibold text-horizon-500">{{ report.client_name || '--' }}</span>
            </td>
            <td class="p-4 text-sm border-b border-light-gray text-horizon-500">
              {{ formatReportType(report.report_type) }}
            </td>
            <td class="p-4 text-sm border-b border-light-gray text-horizon-500">
              {{ formatDateShort(report.report_sent_date || report.activity_date || report.date) }}
            </td>
            <td class="p-4 text-sm border-b border-light-gray text-horizon-500">
              {{ report.report_acknowledged_date ? formatDateShort(report.report_acknowledged_date) : '--' }}
            </td>
            <td class="p-4 text-sm border-b border-light-gray text-neutral-500 max-w-xs truncate">
              {{ report.summary || '--' }}
            </td>
            <td class="p-4 text-sm border-b border-light-gray">
              <span
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                :class="report.report_acknowledged_date
                  ? 'bg-spring-100 text-spring-700'
                  : 'bg-violet-50 text-violet-700'"
              >
                {{ report.report_acknowledged_date ? 'Acknowledged' : 'Pending' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-white border border-light-gray rounded-xl p-12 shadow-card text-center">
      <div class="w-14 h-14 rounded-full bg-light-blue-100 flex items-center justify-center mx-auto mb-4">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6C83BC" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
        </svg>
      </div>
      <div class="text-base font-bold text-horizon-500 mb-1">No suitability reports</div>
      <div class="text-sm text-neutral-500">Suitability reports will appear here once logged via the Activity Log.</div>
    </div>
  </div>
</template>

<script>
import advisorService from '@/services/advisorService';
import { formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'AdvisorReports',

  data() {
    return {
      reports: [],
      loading: false,
    };
  },

  mounted() {
    this.loadReports();
  },

  methods: {
    async loadReports() {
      this.loading = true;

      try {
        const response = await advisorService.getActivities({ activity_type: 'suitability_report' });
        // Response: {success, data: {current_page, data: [...], ...}}
        const paginated = response.data || response;

        if (Array.isArray(paginated)) {
          this.reports = paginated;
        } else {
          this.reports = paginated.data || [];
        }
      } catch {
        this.reports = [];
      } finally {
        this.loading = false;
      }
    },

    formatDateShort(dateStr) {
      if (!dateStr) return '--';
      return formatDateLong(dateStr, true);
    },

    formatReportType(type) {
      if (!type) return '--';
      return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
  },
};
</script>
