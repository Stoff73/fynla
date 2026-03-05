<template>
  <div class="alerts-panel bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-4">
      <h3 class="text-xl font-semibold text-horizon-500">Alerts & Notifications</h3>
      <button
        v-if="alerts.length > 0"
        @click="showAllAlerts"
        class="text-sm text-violet-600 hover:text-violet-700"
      >
        View All ({{ alerts.length }})
      </button>
    </div>

    <!-- Alerts List -->
    <div v-if="displayedAlerts.length > 0" class="space-y-3">
      <div
        v-for="alert in displayedAlerts"
        :key="alert.id"
        class="flex items-start p-3 rounded-lg border"
        :class="alertBorderClass(alert.severity)"
      >
        <!-- Icon -->
        <div class="flex-shrink-0 mr-3">
          <svg
            v-if="alert.severity === 'critical'"
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-raspberry-600"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
              clip-rule="evenodd"
            />
          </svg>
          <svg
            v-else-if="alert.severity === 'important'"
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-violet-600"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clip-rule="evenodd"
            />
          </svg>
          <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-violet-600"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
              clip-rule="evenodd"
            />
          </svg>
        </div>

        <!-- Content -->
        <div class="flex-grow min-w-0">
          <div class="flex items-start justify-between">
            <div class="flex-grow">
              <p class="text-sm font-semibold" :class="alertTextClass(alert.severity)">
                {{ alert.title }}
              </p>
              <p class="text-sm text-neutral-500 mt-1">{{ alert.message }}</p>
              <div class="flex items-center mt-2">
                <span
                  class="inline-block px-2 py-1 text-xs rounded-full"
                  :class="moduleBadgeClass(alert.module)"
                >
                  {{ alert.module }}
                </span>
                <span v-if="alert.action_link" class="ml-3">
                  <button
                    @click="navigateToAction(alert.action_link)"
                    class="text-xs text-violet-600 hover:text-violet-700 font-medium"
                  >
                    {{ alert.action_text || 'Take Action' }} →
                  </button>
                </span>
              </div>
            </div>

            <!-- Dismiss Button -->
            <button
              @click="dismissAlert(alert.id)"
              class="ml-3 flex-shrink-0 text-horizon-400 hover:text-neutral-500"
              title="Dismiss"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                  clip-rule="evenodd"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-12 w-12 text-horizon-300 mx-auto mb-3"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      <p class="text-sm text-neutral-500">No alerts at this time</p>
      <p class="text-xs text-neutral-500 mt-1">Your financial planning is on track!</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AlertsPanel',
  emits: ['dismiss', 'show-all'],

  props: {
    alerts: {
      type: Array,
      default: () => [],
    },
    maxDisplay: {
      type: Number,
      default: 5,
    },
  },

  computed: {
    displayedAlerts() {
      // Sort by severity (critical > important > info) then by date
      const sorted = [...this.alerts].sort((a, b) => {
        const severityOrder = { critical: 0, important: 1, info: 2 };
        const severityDiff = (severityOrder[a.severity] || 2) - (severityOrder[b.severity] || 2);
        if (severityDiff !== 0) return severityDiff;
        return new Date(b.created_at) - new Date(a.created_at);
      });

      return sorted.slice(0, this.maxDisplay);
    },
  },

  methods: {
    alertBorderClass(severity) {
      const classes = {
        critical: 'border-raspberry-300 bg-raspberry-50',
        important: 'border-violet-300 bg-violet-50',
        info: 'border-violet-300 bg-violet-50',
      };
      return classes[severity] || 'border-horizon-300 bg-savannah-100';
    },

    alertTextClass(severity) {
      const classes = {
        critical: 'text-raspberry-800',
        important: 'text-violet-800',
        info: 'text-violet-800',
      };
      return classes[severity] || 'text-horizon-500';
    },

    moduleBadgeClass(module) {
      const classes = {
        Protection: 'bg-raspberry-100 text-raspberry-700',
        Savings: 'bg-violet-100 text-violet-700',
        Investment: 'bg-spring-100 text-spring-700',
        Retirement: 'bg-purple-100 text-purple-700',
        Estate: 'bg-violet-100 text-violet-700',
      };
      return classes[module] || 'bg-savannah-100 text-neutral-500';
    },

    dismissAlert(alertId) {
      this.$emit('dismiss', alertId);
    },

    navigateToAction(link) {
      this.$router.push(link);
    },

    showAllAlerts() {
      this.$emit('show-all');
    },
  },
};
</script>

<style scoped>
.alerts-panel {
  min-width: 280px;
  max-width: 100%;
}

@media (min-width: 640px) {
  .alerts-panel {
    min-width: 400px;
  }
}

@media (min-width: 1024px) {
  .alerts-panel {
    min-width: 500px;
  }
}
</style>
