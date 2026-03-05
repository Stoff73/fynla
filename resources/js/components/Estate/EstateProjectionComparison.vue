<template>
  <div class="bg-white rounded-lg border border-light-gray p-6">
    <div class="mb-4">
      <h3 class="text-lg font-semibold text-horizon-500">
        Estate Projection: Now vs Death at Age {{ projection.life_expectancy.death_age }}
      </h3>
      <p class="text-sm text-neutral-500 mt-1">
        Based on UK ONS life expectancy tables ({{ projection.life_expectancy.current_age }} year old {{ gender }})
        · Growth rate: {{ formatPercent(projection.growth_rate_used) }} per annum
      </p>
    </div>

    <!-- Comparison Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-light-gray">
        <thead class="bg-eggshell-500">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
              Item
            </th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
              Now
            </th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
              Death at Age {{ projection.life_expectancy.death_age }}
              <span class="block text-xs font-normal text-horizon-400 mt-0.5">
                ({{ projection.at_death.years_from_now }} years, {{ projection.at_death.year }})
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
              Change
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-light-gray">
          <!-- Assets Row -->
          <tr class="hover:bg-eggshell-500">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-horizon-500">
              Total Assets
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-horizon-500">
              {{ formatCurrency(projection.current.assets) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-spring-600 font-medium">
              {{ formatCurrency(projection.at_death.assets) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-spring-600">
              +{{ formatCurrency(projection.at_death.assets - projection.current.assets) }}
            </td>
          </tr>

          <!-- Mortgages Row -->
          <tr class="hover:bg-eggshell-500">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-horizon-500">
              Mortgages
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-raspberry-600">
              {{ formatCurrency(projection.current.mortgages) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium" :class="getMortgageChangeClass()">
              {{ formatCurrency(projection.at_death.mortgages) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="getMortgageChangeClass()">
              {{ formatMortgageChange() }}
            </td>
          </tr>

          <!-- Other Liabilities Row -->
          <tr v-if="projection.current.other_liabilities > 0" class="hover:bg-eggshell-500">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-horizon-500">
              Other Liabilities
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-raspberry-600">
              {{ formatCurrency(projection.current.other_liabilities) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-raspberry-600">
              {{ formatCurrency(projection.at_death.other_liabilities) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-neutral-500">
              -
            </td>
          </tr>

          <!-- Net Estate Row (highlighted) -->
          <tr class="bg-violet-50 font-semibold">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-violet-900">
              Net Estate
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-violet-900">
              {{ formatCurrency(projection.current.net_estate) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-violet-900">
              {{ formatCurrency(projection.at_death.net_estate) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-violet-900">
              +{{ formatCurrency(projection.at_death.net_estate - projection.current.net_estate) }}
            </td>
          </tr>

          <!-- Inheritance Tax Liability Row (highlighted) -->
          <tr class="bg-raspberry-50 font-semibold">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-raspberry-900">
              Inheritance Tax Liability (40%)
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-raspberry-900">
              {{ formatCurrency(projection.current.iht_liability) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-raspberry-900">
              {{ formatCurrency(projection.at_death.iht_liability) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-raspberry-900">
              {{ formatIHTChange() }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Growth Assumptions Note -->
    <div class="mt-4 p-4 bg-violet-50 border border-violet-200 rounded-md">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-violet-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h4 class="text-sm font-medium text-violet-800">Projection Assumptions</h4>
          <div class="mt-2 text-sm text-violet-700">
            <ul class="list-disc list-inside space-y-1">
              <li>Assets grow at {{ formatPercent(projection.growth_rate_used) }} per annum (compound)</li>
              <li>Repayment mortgages amortize over remaining term</li>
              <li>Interest-only mortgages remain constant</li>
              <li>Mortgages with maturity dates before death are paid off (£0)</li>
              <li>Other liabilities remain constant (conservative)</li>
              <li>Life expectancy based on UK ONS actuarial tables (2021-2023 data)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EstateProjectionComparison',
  mixins: [currencyMixin],

  props: {
    projection: {
      type: Object,
      required: true,
    },
    gender: {
      type: String,
      default: 'male',
    },
  },

  methods: {
    formatPercent(value) {
      return `${(value * 100).toFixed(1)}%`;
    },

    getMortgageChangeClass() {
      const change = this.projection.at_death.mortgages - this.projection.current.mortgages;
      if (change < 0) return 'text-spring-600'; // Reduced = good
      if (change === 0) return 'text-neutral-500';
      return 'text-raspberry-600'; // Increased = bad
    },

    formatMortgageChange() {
      const change = this.projection.at_death.mortgages - this.projection.current.mortgages;
      if (change === 0) return '-';
      if (change < 0) return this.formatCurrency(Math.abs(change)) + ' (paid down)';
      return '+' + this.formatCurrency(change);
    },

    formatIHTChange() {
      const change = this.projection.at_death.iht_liability - this.projection.current.iht_liability;
      if (change === 0) return '-';
      if (change < 0) return this.formatCurrency(Math.abs(change)) + ' (reduced)';
      return '+' + this.formatCurrency(change);
    },
  },
};
</script>
