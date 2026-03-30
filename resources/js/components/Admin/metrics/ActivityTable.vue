<template>
  <div class="bg-white shadow-card rounded-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-eggshell-500">
            <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
              Period
            </th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
              Registrations
            </th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
              Conversions
            </th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
              Cancellations
            </th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
              Trial Expired
            </th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
              Revenue
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-light-gray">
          <tr
            v-for="row in data"
            :key="row.period"
            class="hover:bg-savannah-100 transition-colors"
          >
            <td class="px-4 py-3 text-horizon-500 font-medium">
              {{ row.period_label || row.period }}
            </td>
            <td class="px-4 py-3 text-right text-horizon-500">
              {{ row.registrations ?? 0 }}
            </td>
            <td class="px-4 py-3 text-right text-spring-500">
              {{ row.conversions ?? 0 }}
            </td>
            <td class="px-4 py-3 text-right text-raspberry-500">
              {{ row.cancellations ?? 0 }}
            </td>
            <td class="px-4 py-3 text-right text-neutral-500">
              {{ row.trial_expired ?? 0 }}
            </td>
            <td class="px-4 py-3 text-right font-bold text-horizon-500">
              {{ formatRevenue(row.revenue) }}
            </td>
          </tr>
          <tr v-if="!data || data.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-neutral-500">
              No activity data available
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ActivityTable',

  props: {
    data: {
      type: Array,
      required: true,
    },
  },

  methods: {
    formatRevenue(pence) {
      if (!pence && pence !== 0) return '£0.00';
      return `£${(pence / 100).toFixed(2)}`;
    },
  },
};
</script>
