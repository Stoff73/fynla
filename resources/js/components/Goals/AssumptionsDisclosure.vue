<template>
  <div class="bg-savannah-100 border border-light-gray rounded-lg p-4">
    <details>
      <summary class="text-sm font-medium text-neutral-500 cursor-pointer hover:text-horizon-500">
        View Projection Assumptions
      </summary>
      <div class="mt-3 space-y-3">
        <!-- Assumptions Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
          <div>
            <span class="text-neutral-500">Inflation Rate:</span>
            <span class="ml-2 font-medium text-horizon-500">{{ assumptions.inflation_rate }}%</span>
          </div>
          <div>
            <span class="text-neutral-500">Investment Growth:</span>
            <span class="ml-2 font-medium text-horizon-500">{{ assumptions.investment_growth }}%</span>
          </div>
          <div>
            <span class="text-neutral-500">Property Growth:</span>
            <span class="ml-2 font-medium text-horizon-500">{{ assumptions.property_growth }}%</span>
          </div>
        </div>

        <!-- Disclaimers -->
        <ul class="space-y-1.5 text-xs text-neutral-500">
          <li class="flex items-start">
            <span class="mr-2">•</span>
            Projections are estimates based on current data and assumptions. Actual results may vary significantly.
          </li>
          <li class="flex items-start">
            <span class="mr-2">•</span>
            Investment returns are assumed to be consistent; actual returns will fluctuate year to year.
          </li>
          <li class="flex items-start">
            <span class="mr-2">•</span>
            Goals are shown as expenditure at their target date, assuming funds are withdrawn when achieved.
          </li>
          <li class="flex items-start">
            <span class="mr-2">•</span>
            Life events with "possible" or "speculative" certainty may not occur as planned.
          </li>
          <li class="flex items-start">
            <span class="mr-2">•</span>
            This projection does not constitute financial advice. Consult a qualified adviser for personalised guidance.
          </li>
        </ul>

        <!-- Link to settings -->
        <p class="text-xs text-neutral-500">
          You can customise these assumptions in
          <router-link
            to="/settings/assumptions"
            class="text-raspberry-600 hover:text-raspberry-700 font-medium"
          >
            Settings → Assumptions
          </router-link>
        </p>
      </div>
    </details>
  </div>
</template>

<script>
export default {
  name: 'AssumptionsDisclosure',

  props: {
    assumptions: {
      type: Object,
      default: () => ({
        inflation_rate: 2.5,
        investment_growth: 4.7,
        property_growth: 3.0,
      }),
    },
  },
};
</script>
