<template>
  <div class="bg-violet-50 rounded-lg border border-violet-200 p-4">
    <p class="text-xs font-semibold text-violet-500 uppercase tracking-wide mb-2">Alerts</p>
    <ul class="space-y-2">
      <li v-for="(alert, index) in displayAlerts" :key="index" class="flex items-start gap-2">
        <svg class="w-4 h-4 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-horizon-500">{{ alert.message }}</p>
          <p v-if="alert.time" class="text-xs text-neutral-500 mt-0.5">{{ alert.time }}</p>
        </div>
      </li>
    </ul>
    <button
      v-if="alerts.length > 3"
      class="text-xs text-violet-500 font-semibold mt-2"
      @click="showAll = !showAll"
    >
      {{ showAll ? 'Show less' : `View all ${alerts.length} alerts` }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'MobileAlertsList',
  props: {
    alerts: { type: Array, required: true },
  },
  data() {
    return { showAll: false };
  },
  computed: {
    displayAlerts() {
      return this.showAll ? this.alerts : this.alerts.slice(0, 3);
    },
  },
};
</script>
