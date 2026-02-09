<template>
  <div class="holdings-table">
    <!-- Filters and Actions Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-gray-700">Filter by:</label>

        <!-- Account Filter -->
        <select
          id="account-filter"
          v-model="selectedAccountId"
          class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option value="">All Accounts</option>
          <option v-for="account in accounts" :key="account.id" :value="account.id">
            {{ account.provider }}
          </option>
        </select>

        <!-- Asset Type Filter -->
        <select
          id="asset-type-filter"
          v-model="selectedAssetType"
          class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option value="">All Asset Types</option>
          <option value="uk_equity">UK Equity</option>
          <option value="us_equity">US Equity</option>
          <option value="international_equity">International Equity</option>
          <option value="bond">Bond</option>
          <option value="cash">Cash</option>
          <option value="alternative">Alternative</option>
          <option value="property">Property</option>
        </select>
      </div>

      <button
        @click="$emit('add-holding')"
        class="bg-primary-600 text-white px-4 py-2 rounded-button text-sm font-medium hover:bg-primary-700 transition-colors"
      >
        + Add Holding
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Allocation Chart and Legend (when holdings exist) -->
    <div v-else-if="filteredHoldings.length > 0" class="mb-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Chart Card -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          Holdings Allocation
          <span v-if="selectedAccountId || selectedAssetType" class="text-sm font-normal text-gray-600 ml-2">
            (Filtered View)
          </span>
        </h3>
        <div class="flex justify-center">
          <apexchart
            :key="chartKey"
            type="donut"
            :options="chartOptions"
            :series="chartSeries"
            height="350"
            width="100%"
          />
        </div>
      </div>

      <!-- Legend Card -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Holdings Breakdown</h3>
        <div class="max-h-96 overflow-y-auto space-y-2">
          <div v-for="(holding, index) in sortedByValue" :key="holding.id" class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div
                class="w-4 h-4 rounded-full flex-shrink-0"
                :style="{ backgroundColor: chartColours[index % chartColours.length] }"
              ></div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate">{{ holding.security_name }}</p>
                <p class="text-xs text-gray-500">{{ formatAssetType(holding.asset_type) }}</p>
              </div>
            </div>
            <div class="text-right ml-4">
              <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(holding.current_value) }}</p>
              <p class="text-xs text-gray-600">{{ getHoldingPercentage(holding) }}%</p>
            </div>
          </div>
        </div>
        <div class="pt-3 mt-3 border-t-2 border-gray-300">
          <div class="flex justify-between items-center">
            <p class="text-sm font-bold text-gray-900">Total Value:</p>
            <p class="text-lg font-bold text-gray-900">{{ formatCurrency(totalValue) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Holdings Table -->
    <div v-else-if="filteredHoldings.length > 0" class="overflow-x-auto border border-gray-200 rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('security_name')"
            >
              Security
              <span v-if="sortField === 'security_name'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('asset_type')"
            >
              Type
              <span v-if="sortField === 'asset_type'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('allocation_percent')"
            >
              Allocation %
              <span v-if="sortField === 'allocation_percent'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('purchase_price')"
            >
              Purchase Price
              <span v-if="sortField === 'purchase_price'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('current_price')"
            >
              Current Price
              <span v-if="sortField === 'current_price'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('current_value')"
            >
              Current Value
              <span v-if="sortField === 'current_value'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              @click="sortBy('return_percent')"
            >
              Return (%)
              <span v-if="sortField === 'return_percent'" class="ml-1">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              OCF (%)
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr
            v-for="holding in sortedHoldings"
            :key="holding.id"
            class="hover:bg-gray-50 cursor-pointer"
            @click="expandedRow === holding.id ? expandedRow = null : expandedRow = holding.id"
          >
            <td class="px-4 py-3 text-sm">
              <div class="font-medium text-gray-900">{{ holding.security_name }}</div>
              <div class="text-xs text-gray-500">{{ holding.ticker || holding.isin || 'N/A' }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500 text-white">
                {{ formatAssetType(holding.asset_type) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-right text-gray-900">
              {{ (holding.allocation_percent || 0).toFixed(2) }}%
            </td>
            <td class="px-4 py-3 text-sm text-right text-gray-900">
              {{ formatCurrency(holding.purchase_price) }}
            </td>
            <td class="px-4 py-3 text-sm text-right text-gray-900">
              {{ formatCurrency(holding.current_price) }}
            </td>
            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">
              {{ formatCurrency(holding.current_value) }}
            </td>
            <td class="px-4 py-3 text-sm text-right font-medium" :class="getReturnClass(holding.return_percent)">
              {{ formatReturn(holding.return_percent) }}
            </td>
            <td class="px-4 py-3 text-sm text-right text-gray-700">
              {{ holding.ocf_percent ? holding.ocf_percent.toFixed(2) : '0.00' }}%
            </td>
            <td class="px-4 py-3 text-sm text-right">
              <div class="flex justify-end gap-2" @click.stop>
                <button
                  @click="$emit('edit-holding', holding)"
                  class="text-blue-600 hover:text-blue-800"
                  title="Edit"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="$emit('delete-holding', holding)"
                  class="text-red-600 hover:text-red-800"
                  title="Delete"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>

          <!-- Expanded Row Detail -->
          <tr v-if="expandedRow" :key="`${expandedRow}-detail`" class="bg-gray-50">
            <td colspan="9" class="px-4 py-4">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="text-gray-600">Purchase Date:</span>
                  <span class="ml-2 text-gray-900 font-medium">
                    {{ formatDate(getHoldingById(expandedRow).purchase_date) }}
                  </span>
                </div>
                <div>
                  <span class="text-gray-600">ISIN:</span>
                  <span class="ml-2 text-gray-900 font-medium">
                    {{ getHoldingById(expandedRow).isin || 'N/A' }}
                  </span>
                </div>
                <div>
                  <span class="text-gray-600">Cost Basis:</span>
                  <span class="ml-2 text-gray-900 font-medium">
                    {{ formatCurrency((getHoldingById(expandedRow).quantity || 0) * (getHoldingById(expandedRow).purchase_price || 0)) }}
                  </span>
                </div>
                <div>
                  <span class="text-gray-600">Unrealised Gain/Loss:</span>
                  <span class="ml-2 font-medium" :class="getReturnClass(getHoldingById(expandedRow).return_percent)">
                    {{ formatCurrency(getUnrealisedGainLoss(getHoldingById(expandedRow))) }}
                  </span>
                </div>
              </div>
            </td>
          </tr>
        </tbody>

        <!-- Total Row -->
        <tfoot class="bg-gray-100 font-semibold">
          <tr>
            <td colspan="5" class="px-4 py-3 text-sm text-gray-900">Total</td>
            <td class="px-4 py-3 text-sm text-right text-gray-900">{{ formatCurrency(totalValue) }}</td>
            <td class="px-4 py-3 text-sm text-right" :class="getReturnClass(averageReturn)">
              {{ formatReturn(averageReturn) }}
            </td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white border border-gray-200 rounded-lg">
      <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No holdings found</h3>
      <p class="text-gray-500 mb-4">
        {{ (selectedAssetType || selectedAccountId) ? 'No holdings match the selected filters.' : 'Get started by adding your first holding.' }}
      </p>
      <button
        @click="$emit('add-holding')"
        class="bg-primary-600 text-white px-4 py-2 rounded-button text-sm font-medium hover:bg-primary-700 transition-colors"
      >
        + Add Holding
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { CHART_COLORS, TEXT_COLORS } from '@/constants/designSystem';

export default {
  name: 'HoldingsTable',
  mixins: [currencyMixin],

  props: {
    holdings: {
      type: Array,
      required: true,
      default: () => [],
    },
    loading: {
      type: Boolean,
      default: false,
    },
    accounts: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      selectedAssetType: '',
      selectedAccountId: '',
      sortField: 'security_name',
      sortDirection: 'asc',
      expandedRow: null,
      chartColours: CHART_COLORS,
    };
  },

  computed: {
    filteredHoldings() {
      let filtered = this.holdings;

      // Filter by account if selected
      if (this.selectedAccountId) {
        filtered = filtered.filter(h => h.investment_account_id === parseInt(this.selectedAccountId));
      }

      // Filter by asset type if selected
      if (this.selectedAssetType) {
        filtered = filtered.filter(h => h.asset_type === this.selectedAssetType);
      }

      return filtered;
    },

    sortedHoldings() {
      const holdings = [...this.filteredHoldings];
      holdings.sort((a, b) => {
        let aVal = a[this.sortField];
        let bVal = b[this.sortField];

        // Handle null/undefined values
        if (aVal == null) aVal = '';
        if (bVal == null) bVal = '';

        // String comparison
        if (typeof aVal === 'string') {
          return this.sortDirection === 'asc'
            ? aVal.localeCompare(bVal)
            : bVal.localeCompare(aVal);
        }

        // Numeric comparison
        return this.sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
      });
      return holdings;
    },

    totalValue() {
      return this.filteredHoldings.reduce((sum, h) => sum + (h.current_value || 0), 0);
    },

    averageReturn() {
      if (this.filteredHoldings.length === 0) return 0;
      const totalReturn = this.filteredHoldings.reduce((sum, h) => sum + (h.return_percent || 0), 0);
      return totalReturn / this.filteredHoldings.length;
    },

    // All holdings sorted by value for chart
    sortedByValue() {
      return [...this.filteredHoldings]
        .sort((a, b) => (b.current_value || 0) - (a.current_value || 0));
    },

    chartKey() {
      const total = this.chartSeries?.reduce((a, b) => a + b, 0) || 0;
      return `holdings-donut-${this.chartSeries?.length || 0}-${Math.round(total)}`;
    },

    // Chart series data (current values of ALL holdings)
    chartSeries() {
      return this.sortedByValue.map(h => parseFloat(h.current_value) || 0);
    },

    // Chart options
    chartOptions() {
      const self = this;
      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        labels: this.sortedByValue.map(h => h.security_name),
        colors: this.chartColours,
        legend: {
          show: false, // We'll use custom legend in separate card
        },
        dataLabels: {
          enabled: true,
          formatter: function(val) {
            return val.toFixed(1) + '%';
          },
          style: {
            fontSize: '12px',
            fontWeight: 600,
          },
          dropShadow: {
            enabled: false,
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '16px',
                  fontWeight: 600,
                  color: TEXT_COLORS.primary,
                },
                value: {
                  show: true,
                  fontSize: '28px',
                  fontWeight: 700,
                  color: TEXT_COLORS.primary,
                  formatter: function(val) {
                    return new Intl.NumberFormat('en-GB', {
                      style: 'currency',
                      currency: 'GBP',
                      minimumFractionDigits: 0,
                      maximumFractionDigits: 0,
                    }).format(val);
                  },
                },
                total: {
                  show: true,
                  label: 'Total Value',
                  fontSize: '16px',
                  fontWeight: 600,
                  color: TEXT_COLORS.muted,
                  formatter: function(w) {
                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                    return new Intl.NumberFormat('en-GB', {
                      style: 'currency',
                      currency: 'GBP',
                      minimumFractionDigits: 0,
                      maximumFractionDigits: 0,
                    }).format(total);
                  },
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: function(val) {
              return new Intl.NumberFormat('en-GB', {
                style: 'currency',
                currency: 'GBP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              }).format(val);
            },
          },
        },
        responsive: [
          {
            breakpoint: 480,
            options: {
              chart: {
                width: 300,
              },
            },
          },
        ],
      };
    },
  },

  methods: {
    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortField = field;
        this.sortDirection = 'asc';
      }
    },

    formatNumber(value) {
      return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
      }).format(value || 0);
    },

    formatReturn(value) {
      const sign = value >= 0 ? '+' : '';
      return `${sign}${(value || 0).toFixed(2)}%`;
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      return new Date(dateString).toLocaleDateString('en-GB');
    },

    getHoldingPercentage(holding) {
      if (this.totalValue === 0) return 0;
      return ((holding.current_value / this.totalValue) * 100).toFixed(1);
    },

    formatAssetType(type) {
      if (!type) return 'N/A';
      return type.split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },

    getReturnClass(returnPercent) {
      if (returnPercent > 0) return 'text-green-600';
      if (returnPercent < 0) return 'text-red-600';
      return 'text-gray-600';
    },

    getHoldingById(id) {
      return this.filteredHoldings.find(h => h.id === id) || {};
    },

    getUnrealisedGainLoss(holding) {
      const costBasis = (holding.quantity || 0) * (holding.purchase_price || 0);
      const currentValue = holding.current_value || 0;
      return currentValue - costBasis;
    },
  },
};
</script>
