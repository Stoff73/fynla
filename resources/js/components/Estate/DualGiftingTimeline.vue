<template>
  <div class="bg-white rounded-lg border border-light-gray p-6">
    <h3 class="text-lg font-semibold text-horizon-500 mb-4">Gifting Timelines (7-Year Rule)</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- User Timeline -->
      <div>
        <h4 class="text-sm font-medium text-neutral-500 mb-3">{{ userTimeline.name }}'s Gifts</h4>

        <div v-if="userTimeline.gift_count > 0">
          <apexchart
            type="rangeBar"
            height="300"
            :options="getUserChartOptions()"
            :series="getUserChartSeries()"
          ></apexchart>

          <!-- Gift Details -->
          <div class="mt-4 space-y-2">
            <div
              v-for="gift in userTimeline.gifts_within_7_years"
              :key="gift.gift_id"
              class="text-xs p-2 bg-eggshell-500 rounded"
            >
              <div class="flex justify-between">
                <span class="font-medium">{{ gift.recipient }}</span>
                <span class="text-neutral-500">{{ formatCurrency(gift.value) }}</span>
              </div>
              <div class="text-neutral-500 mt-1">
                {{ formatDate(gift.date) }} • {{ gift.years_remaining_until_exempt }} years until exempt
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-neutral-500 text-center py-8 bg-light-blue-100 border border-light-gray rounded">
          No gifts recorded within last 7 years
        </div>
      </div>

      <!-- Spouse Timeline -->
      <div>
        <h4 class="text-sm font-medium text-neutral-500 mb-3">
          {{ spouseTimeline.name || 'Spouse' }}'s Gifts
        </h4>

        <div v-if="spouseTimeline.show_empty_timeline">
          <!-- Empty state with data sharing message -->
          <div class="bg-light-blue-100 border border-light-gray rounded-lg p-8 text-center">
            <svg
              class="mx-auto h-12 w-12 text-horizon-400"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
              />
            </svg>
            <p class="mt-2 text-sm text-neutral-500">{{ spouseTimeline.message }}</p>
            <router-link
              to="/settings"
              class="mt-3 inline-block text-sm font-medium text-violet-600 hover:text-violet-700"
            >
              Enable data sharing →
            </router-link>
          </div>
        </div>

        <div v-else-if="spouseTimeline.gift_count > 0">
          <apexchart
            type="rangeBar"
            height="300"
            :options="getSpouseChartOptions()"
            :series="getSpouseChartSeries()"
          ></apexchart>

          <!-- Spouse Gift Details -->
          <div class="mt-4 space-y-2">
            <div
              v-for="gift in spouseTimeline.gifts_within_7_years"
              :key="gift.gift_id"
              class="text-xs p-2 bg-eggshell-500 rounded"
            >
              <div class="flex justify-between">
                <span class="font-medium">{{ gift.recipient }}</span>
                <span class="text-neutral-500">{{ formatCurrency(gift.value) }}</span>
              </div>
              <div class="text-neutral-500 mt-1">
                {{ formatDate(gift.date) }} • {{ gift.years_remaining_until_exempt }} years until exempt
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-neutral-500 text-center py-8 bg-light-blue-100 border border-light-gray rounded">
          No gifts recorded within last 7 years
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="mt-6 pt-6 border-t border-light-gray">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-violet-50 rounded p-3">
          <p class="text-xs text-violet-600 font-medium">Total Gifts ({{ userTimeline.name }})</p>
          <p class="text-lg font-bold text-violet-900">{{ formatCurrency(userTimeline.total_gifts) }}</p>
          <p class="text-xs text-violet-500">{{ userTimeline.gift_count }} gifts within 7 years</p>
        </div>
        <div v-if="!spouseTimeline.show_empty_timeline" class="bg-violet-50 rounded p-3">
          <p class="text-xs text-violet-500 font-medium">Total Gifts ({{ spouseTimeline.name || 'Spouse' }})</p>
          <p class="text-lg font-bold text-violet-500">{{ formatCurrency(spouseTimeline.total_gifts) }}</p>
          <p class="text-xs text-violet-500">{{ spouseTimeline.gift_count }} gifts within 7 years</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, CHART_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'DualGiftingTimeline',
  mixins: [currencyMixin],

  props: {
    userTimeline: {
      type: Object,
      required: true,
    },
    spouseTimeline: {
      type: Object,
      required: true,
    },
    dataSharingEnabled: {
      type: Boolean,
      default: false,
    },
  },

  methods: {
    getUserChartOptions() {
      return {
        chart: { ...CHART_DEFAULTS.chart, type: 'rangeBar', height: 300 },
        plotOptions: {
          bar: {
            horizontal: true,
            barHeight: '50%',
          },
        },
        xaxis: {
          type: 'datetime',
        },
        colours: [PRIMARY_COLORS[500]], // Blue for user
        dataLabels: {
          enabled: true,
          formatter: (val) => {
            if (!val || !Array.isArray(val) || val.length < 2) return '';
            const [start, end] = val;
            const years = Math.round((end - start) / (365 * 24 * 60 * 60 * 1000));
            return years > 0 ? `${years}y` : 'Exempt';
          },
        },
        tooltip: {
          x: {
            format: 'dd MMM yyyy',
          },
        },
      };
    },

    getUserChartSeries() {
      if (!this.userTimeline.gifts_within_7_years || this.userTimeline.gifts_within_7_years.length === 0) {
        return [];
      }

      return [{
        name: 'Gift Period',
        data: this.userTimeline.gifts_within_7_years.map(gift => ({
          x: gift.recipient,
          y: [
            new Date(gift.date).getTime(),
            new Date(gift.becomes_exempt_on).getTime(),
          ],
        })),
      }];
    },

    getSpouseChartOptions() {
      return {
        ...this.getUserChartOptions(),
        colours: [CHART_COLORS[5]], // Purple for spouse
      };
    },

    getSpouseChartSeries() {
      if (!this.spouseTimeline.gifts_within_7_years || this.spouseTimeline.gifts_within_7_years.length === 0) {
        return [];
      }

      return [{
        name: 'Gift Period',
        data: this.spouseTimeline.gifts_within_7_years.map(gift => ({
          x: gift.recipient,
          y: [
            new Date(gift.date).getTime(),
            new Date(gift.becomes_exempt_on).getTime(),
          ],
        })),
      }];
    },

    formatDate(dateString) {
      if (!dateString) return '';
      return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },
  },
};
</script>
