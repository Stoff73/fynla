<template>
  <div>
    <div v-if="data" class="space-y-6">
      <!-- As Of Date -->
      <div class="card p-4 bg-eggshell-500">
        <p class="text-body-sm text-neutral-500">
          As of: {{ formatDate(data.as_of_date) }}
        </p>
      </div>

      <!-- Assets Section -->
      <div class="card p-6 overflow-x-auto">
        <h3 class="text-h5 font-semibold text-success-700 mb-4">Assets</h3>
        <table class="min-w-full divide-y divide-light-gray">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500">Line Item</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">You</th>
              <th v-if="hasSpouseData" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Spouse</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-light-gray">
            <tr v-for="(item, index) in mergedAssets" :key="index">
              <td class="px-3 py-2 text-body-base text-neutral-500">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                {{ formatCurrency(item.amount) }}
              </td>
              <td v-if="hasSpouseData" class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                {{ formatCurrency(getSpouseAssetAmount(item.line_item)) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-eggshell-500 border-t-2 border-horizon-300">
              <td class="px-3 py-3 text-body-base font-semibold text-horizon-500">Total Assets</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(data.total_assets) }}
              </td>
              <td v-if="hasSpouseData" class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(spouseData.total_assets) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Liabilities Section -->
      <div class="card p-6 overflow-x-auto">
        <h3 class="text-h5 font-semibold text-raspberry-700 mb-4">Liabilities</h3>
        <table class="min-w-full divide-y divide-light-gray">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500">Line Item</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">You</th>
              <th v-if="hasSpouseData" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Spouse</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-light-gray">
            <tr v-for="(item, index) in mergedLiabilities" :key="index">
              <td class="px-3 py-2 text-body-base text-neutral-500">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                {{ formatCurrency(item.amount) }}
              </td>
              <td v-if="hasSpouseData" class="px-3 py-2 text-right text-body-base font-medium text-horizon-500">
                {{ formatCurrency(getSpouseLiabilityAmount(item.line_item)) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-eggshell-500 border-t-2 border-horizon-300">
              <td class="px-3 py-3 text-body-base font-semibold text-horizon-500">Total Liabilities</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                {{ formatCurrency(data.total_liabilities) }}
              </td>
              <td v-if="hasSpouseData" class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                {{ formatCurrency(spouseData.total_liabilities) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Equity (Net Worth) -->
      <div class="card p-6 bg-gradient-to-r from-raspberry-50 to-raspberry-100 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500">Net Worth</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">You</th>
              <th v-if="hasSpouseData" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">Spouse</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="px-3 py-3 text-h5 font-semibold text-horizon-500">Equity (Net Worth)</td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h2 font-display font-bold"
                  :class="data.total_equity >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                >
                  {{ formatCurrency(data.total_equity) }}
                </p>
              </td>
              <td v-if="hasSpouseData" class="px-3 py-3 text-right">
                <p
                  class="text-h2 font-display font-bold"
                  :class="spouseData.total_equity >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                >
                  {{ formatCurrency(spouseData.total_equity) }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card p-8 text-center">
      <p class="text-body-base text-neutral-500">
        No data available. Click "Calculate" to generate your Balance Sheet.
      </p>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'BalanceSheetView',

  props: {
    data: {
      type: Object,
      default: null,
    },
    spouseData: {
      type: Object,
      default: null,
    },
  },

  setup(props) {
    const hasSpouseData = computed(() => props.spouseData !== null && props.spouseData !== undefined);

    const formatDate = (date) => {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    const getUserAssetAmount = (lineItem) => {
      if (!props.data?.assets) return 0;
      const item = props.data.assets.find((a) => a.line_item === lineItem);
      return item?.amount || 0;
    };

    const getSpouseAssetAmount = (lineItem) => {
      if (!props.spouseData?.assets) return 0;
      const item = props.spouseData.assets.find((a) => a.line_item === lineItem);
      return item?.amount || 0;
    };

    const getUserLiabilityAmount = (lineItem) => {
      if (!props.data?.liabilities) return 0;
      const item = props.data.liabilities.find((l) => l.line_item === lineItem);
      return item?.amount || 0;
    };

    const getSpouseLiabilityAmount = (lineItem) => {
      if (!props.spouseData?.liabilities) return 0;
      const item = props.spouseData.liabilities.find((l) => l.line_item === lineItem);
      return item?.amount || 0;
    };

    // Merge assets from both user and spouse to show all line items
    const mergedAssets = computed(() => {
      if (!props.data?.assets) return [];

      // Get all unique line items from both user and spouse
      const allLineItems = new Set();
      props.data.assets.forEach(item => allLineItems.add(item.line_item));
      if (hasSpouseData.value && props.spouseData.assets) {
        props.spouseData.assets.forEach(item => allLineItems.add(item.line_item));
      }

      // Create merged array with all line items
      return Array.from(allLineItems).map(lineItem => ({
        line_item: lineItem,
        category: 'asset',
        amount: getUserAssetAmount(lineItem),
      }));
    });

    // Merge liabilities from both user and spouse to show all line items
    const mergedLiabilities = computed(() => {
      if (!props.data?.liabilities) return [];

      // Get all unique line items from both user and spouse
      const allLineItems = new Set();
      props.data.liabilities.forEach(item => allLineItems.add(item.line_item));
      if (hasSpouseData.value && props.spouseData.liabilities) {
        props.spouseData.liabilities.forEach(item => allLineItems.add(item.line_item));
      }

      // Create merged array with all line items
      return Array.from(allLineItems).map(lineItem => ({
        line_item: lineItem,
        category: 'liability',
        amount: getUserLiabilityAmount(lineItem),
      }));
    });

    return {
      hasSpouseData,
      formatDate,
      formatCurrency,
      getUserAssetAmount,
      getSpouseAssetAmount,
      getUserLiabilityAmount,
      getSpouseLiabilityAmount,
      mergedAssets,
      mergedLiabilities,
    };
  },
};
</script>
