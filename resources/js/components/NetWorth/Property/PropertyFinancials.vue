<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-gray-800">Property Financials</h3>

    <!-- Monthly Costs -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
      <div class="mb-4">
        <h4 class="text-md font-semibold text-gray-700">Monthly Costs</h4>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-if="monthlyMortgagePayments > 0">
          <label class="block text-sm font-medium text-gray-700 mb-1">Mortgage Payment (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 rounded-md text-gray-700 font-medium">
            {{ formatCurrency(monthlyMortgagePayments) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Council Tax (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_council_tax || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Gas (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_gas || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Electricity (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_electricity || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Water (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_water || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Building Insurance (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_building_insurance || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contents Insurance (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_contents_insurance || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Service Charge (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_service_charge || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Maintenance Reserve (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.monthly_maintenance_reserve || 0) }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Other Costs (£/month)</label>
          <div class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-gray-700">
            {{ formatCurrency(property.other_monthly_costs || 0) }}
          </div>
        </div>
      </div>

      <div class="mt-6 p-4 bg-gray-50 border-2 border-gray-300 rounded-lg">
        <div class="flex justify-between items-center">
          <span class="text-lg font-semibold text-gray-900">Total Monthly Costs</span>
          <span class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalMonthlyCosts) }}</span>
        </div>
      </div>
    </div>

    <!-- Buy to Let Financials -->
    <div v-if="property.property_type === 'buy_to_let'" class="bg-white border border-gray-200 rounded-lg p-6">
      <h4 class="text-md font-semibold text-gray-700 mb-4">Rental Income Analysis</h4>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-green-700">Monthly Rental Income</p>
          <p class="text-2xl font-bold text-green-900">{{ formatCurrency(property.monthly_rental_income || 0) }}</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-blue-700">Net Monthly Income</p>
          <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(netMonthlyIncome) }}</p>
          <p class="text-xs text-blue-600 mt-1">After all costs</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-sm text-purple-700">Net Rental Yield</p>
          <p class="text-2xl font-bold text-purple-900">{{ netRentalYield }}%</p>
          <p class="text-xs text-purple-600 mt-1">Annual</p>
        </div>
      </div>

      <dl class="space-y-2">
        <div class="flex justify-between py-2 border-b border-gray-100">
          <dt class="text-sm text-gray-600">Monthly Rental Income:</dt>
          <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(property.monthly_rental_income || 0) }}</dd>
        </div>

        <div class="flex justify-between py-2 border-b border-gray-100">
          <dt class="text-sm text-gray-600">Less: Total Monthly Costs:</dt>
          <dd class="text-sm font-medium text-red-600">-{{ formatCurrency(totalMonthlyCosts) }}</dd>
        </div>

        <div class="flex justify-between py-3 border-t-2 border-gray-300 mt-2">
          <dt class="text-base font-semibold text-gray-700">Net Monthly Income:</dt>
          <dd class="text-base font-bold" :class="netMonthlyIncome >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(netMonthlyIncome) }}
          </dd>
        </div>

        <div class="flex justify-between py-2 bg-gray-50 rounded-md p-2 mt-2">
          <dt class="text-sm text-gray-600">Projected Annual Net Income:</dt>
          <dd class="text-sm font-semibold" :class="netAnnualIncome >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(netAnnualIncome) }}
          </dd>
        </div>
      </dl>

      <div v-if="property.tenant_name" class="mt-4 p-4 bg-gray-50 rounded-md">
        <h5 class="text-sm font-semibold text-gray-700 mb-2">Tenancy Information</h5>
        <dl class="space-y-1 text-sm">
          <div class="flex justify-between">
            <dt class="text-gray-600">Tenant:</dt>
            <dd class="font-medium text-gray-900">{{ property.tenant_name }}</dd>
          </div>
          <div v-if="property.lease_start_date" class="flex justify-between">
            <dt class="text-gray-600">Lease Start:</dt>
            <dd class="font-medium text-gray-900">{{ formatDate(property.lease_start_date) }}</dd>
          </div>
          <div v-if="property.lease_end_date" class="flex justify-between">
            <dt class="text-gray-600">Lease End:</dt>
            <dd class="font-medium text-gray-900">{{ formatDate(property.lease_end_date) }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- SDLT Paid -->
    <div v-if="property.sdlt_paid" class="bg-white border border-gray-200 rounded-lg p-6">
      <h4 class="text-md font-semibold text-gray-700 mb-4">Stamp Duty Land Tax</h4>
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-600">SDLT Paid at Purchase:</span>
        <span class="text-lg font-bold text-gray-900">{{ formatCurrency(property.sdlt_paid) }}</span>
      </div>
    </div>

    <!-- Summary Metrics -->
    <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-lg p-6">
      <h4 class="text-md font-semibold text-gray-800 mb-4">Financial Summary</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <p class="text-sm text-gray-700">Total Investment:</p>
          <p class="text-xl font-bold text-gray-900">{{ formatCurrency(totalInvestment) }}</p>
          <p class="text-xs text-gray-600 mt-1">Purchase price + SDLT + improvements</p>
        </div>

        <div>
          <p class="text-sm text-gray-700">Current Equity:</p>
          <p class="text-xl font-bold text-green-600">{{ formatCurrency(property.equity || 0) }}</p>
          <p class="text-xs text-gray-600 mt-1">Current value - mortgage balance</p>
        </div>

        <div>
          <p class="text-sm text-gray-700">Value Appreciation:</p>
          <p class="text-xl font-bold" :class="valueChange >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(valueChange) }}
          </p>
          <p class="text-xs text-gray-600 mt-1">{{ valueChangePercent }}% since purchase</p>
        </div>

        <div v-if="property.property_type === 'buy_to_let'">
          <p class="text-sm text-gray-700">Annual Cash Flow:</p>
          <p class="text-xl font-bold" :class="netAnnualIncome >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ formatCurrency(netAnnualIncome) }}
          </p>
          <p class="text-xs text-gray-600 mt-1">Rental income - costs</p>
        </div>
      </div>
    </div>

    <!-- Edit Costs Modal -->
    <div v-if="showEditCostsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4 rounded-t-lg">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-semibold text-gray-900">Edit Monthly Costs</h3>
            <button
              @click="closeEditCostsModal"
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal Content -->
        <form @submit.prevent="handleSaveCosts">
          <div class="px-6 py-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="monthly_council_tax" class="block text-sm font-medium text-gray-700 mb-1">
                  Council Tax (£/month)
                </label>
                <input
                  id="monthly_council_tax"
                  v-model.number="costsForm.monthly_council_tax"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_gas" class="block text-sm font-medium text-gray-700 mb-1">
                  Gas (£/month)
                </label>
                <input
                  id="monthly_gas"
                  v-model.number="costsForm.monthly_gas"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_electricity" class="block text-sm font-medium text-gray-700 mb-1">
                  Electricity (£/month)
                </label>
                <input
                  id="monthly_electricity"
                  v-model.number="costsForm.monthly_electricity"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_water" class="block text-sm font-medium text-gray-700 mb-1">
                  Water (£/month)
                </label>
                <input
                  id="monthly_water"
                  v-model.number="costsForm.monthly_water"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_building_insurance" class="block text-sm font-medium text-gray-700 mb-1">
                  Building Insurance (£/month)
                </label>
                <input
                  id="monthly_building_insurance"
                  v-model.number="costsForm.monthly_building_insurance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_contents_insurance" class="block text-sm font-medium text-gray-700 mb-1">
                  Contents Insurance (£/month)
                </label>
                <input
                  id="monthly_contents_insurance"
                  v-model.number="costsForm.monthly_contents_insurance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_service_charge" class="block text-sm font-medium text-gray-700 mb-1">
                  Service Charge (£/month)
                </label>
                <input
                  id="monthly_service_charge"
                  v-model.number="costsForm.monthly_service_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="monthly_maintenance_reserve" class="block text-sm font-medium text-gray-700 mb-1">
                  Maintenance Reserve (£/month)
                </label>
                <input
                  id="monthly_maintenance_reserve"
                  v-model.number="costsForm.monthly_maintenance_reserve"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="other_monthly_costs" class="block text-sm font-medium text-gray-700 mb-1">
                  Other Monthly Costs (£/month)
                </label>
                <input
                  id="other_monthly_costs"
                  v-model.number="costsForm.other_monthly_costs"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="p-3 bg-gray-50 rounded-md">
              <p class="text-sm text-red-600">{{ error }}</p>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-end space-x-2 rounded-b-lg">
            <button
              type="button"
              @click="closeEditCostsModal"
              class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Saving...' : 'Save Costs' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PropertyFinancials',
  mixins: [currencyMixin],

  props: {
    property: {
      type: Object,
      required: true,
    },
    mortgages: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      showEditCostsModal: false,
      submitting: false,
      error: null,
      costsForm: {
        monthly_council_tax: null,
        monthly_gas: null,
        monthly_electricity: null,
        monthly_water: null,
        monthly_building_insurance: null,
        monthly_contents_insurance: null,
        monthly_service_charge: null,
        monthly_maintenance_reserve: null,
        other_monthly_costs: null,
      },
    };
  },

  computed: {
    monthlyMortgagePayments() {
      // Use mortgages from prop, or fallback to property.mortgages
      const mortgageList = this.mortgages && this.mortgages.length > 0
        ? this.mortgages
        : (this.property.mortgages || []);

      return mortgageList.reduce((total, mortgage) => {
        const payment = parseFloat(mortgage.monthly_payment) || 0;
        return total + payment;
      }, 0);
    },

    totalMonthlyCosts() {
      return (
        (parseFloat(this.property.monthly_council_tax) || 0) +
        (parseFloat(this.property.monthly_gas) || 0) +
        (parseFloat(this.property.monthly_electricity) || 0) +
        (parseFloat(this.property.monthly_water) || 0) +
        (parseFloat(this.property.monthly_building_insurance) || 0) +
        (parseFloat(this.property.monthly_contents_insurance) || 0) +
        (parseFloat(this.property.monthly_service_charge) || 0) +
        (parseFloat(this.property.monthly_maintenance_reserve) || 0) +
        (parseFloat(this.property.other_monthly_costs) || 0) +
        this.monthlyMortgagePayments
      );
    },

    netMonthlyIncome() {
      const monthlyIncome = parseFloat(this.property.monthly_rental_income) || 0;
      return monthlyIncome - this.totalMonthlyCosts;
    },

    netAnnualIncome() {
      return this.netMonthlyIncome * 12;
    },

    netRentalYield() {
      const currentValue = parseFloat(this.property.current_value) || 0;
      if (currentValue === 0) return '0.00';
      const yieldValue = (this.netAnnualIncome / currentValue) * 100;
      return yieldValue.toFixed(2);
    },

    totalInvestment() {
      return (
        (this.property.purchase_price || 0) +
        (this.property.sdlt_paid || 0) +
        (this.property.improvement_costs || 0)
      );
    },

    valueChange() {
      return (this.property.current_value || 0) - (this.property.purchase_price || 0);
    },

    valueChangePercent() {
      const purchasePrice = this.property.purchase_price || 0;
      if (purchasePrice === 0) return '0.00';
      const percent = (this.valueChange / purchasePrice) * 100;
      return percent > 0 ? `+${percent.toFixed(2)}` : percent.toFixed(2);
    },
  },

  watch: {
    property: {
      immediate: true,
      handler(newProperty) {
        if (newProperty) {
          this.populateCostsForm();
        }
      },
    },
  },

  methods: {
    populateCostsForm() {
      this.costsForm.monthly_council_tax = this.property.monthly_council_tax || null;
      this.costsForm.monthly_gas = this.property.monthly_gas || null;
      this.costsForm.monthly_electricity = this.property.monthly_electricity || null;
      this.costsForm.monthly_water = this.property.monthly_water || null;
      this.costsForm.monthly_building_insurance = this.property.monthly_building_insurance || null;
      this.costsForm.monthly_contents_insurance = this.property.monthly_contents_insurance || null;
      this.costsForm.monthly_service_charge = this.property.monthly_service_charge || null;
      this.costsForm.monthly_maintenance_reserve = this.property.monthly_maintenance_reserve || null;
      this.costsForm.other_monthly_costs = this.property.other_monthly_costs || null;
    },

    closeEditCostsModal() {
      this.showEditCostsModal = false;
      this.error = null;
      this.populateCostsForm(); // Reset form to current property values
    },

    async handleSaveCosts() {
      this.submitting = true;
      this.error = null;

      try {
        // Emit event to parent to handle the update
        this.$emit('update-costs', this.costsForm);
        this.showEditCostsModal = false;
      } catch (error) {
        console.error('Failed to save costs:', error);
        this.error = error.message || 'Failed to save costs. Please try again.';
      } finally {
        this.submitting = false;
      }
    },

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },
  },
};
</script>
