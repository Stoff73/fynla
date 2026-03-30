<template>
  <div class="space-y-6">
    <div :class="property.property_type === 'buy_to_let' ? 'grid grid-cols-1 lg:grid-cols-2 gap-6' : ''">
    <!-- Monthly Costs -->
    <div class="bg-white border border-light-gray rounded-lg p-6">
      <div class="mb-4">
        <div class="flex items-center gap-2">
          <h4 class="text-md font-semibold text-horizon-500">Monthly Costs</h4>
          <div v-if="isSharedOwnership" class="relative group">
            <svg class="w-4 h-4 text-violet-500 cursor-help" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div class="hidden group-hover:block absolute z-10 w-64 p-3 mt-1 text-xs text-white bg-horizon-500 rounded-lg shadow-lg left-0">
              Enter 100% of all property costs. The system will automatically calculate your share ({{ property.ownership_percentage }}%) based on your ownership percentage.
            </div>
          </div>
        </div>
      </div>

      <dl class="space-y-2">
        <div v-if="monthlyMortgagePayments > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Mortgage Payment:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(monthlyMortgagePayments) }}</dd>
        </div>

        <div v-if="property.monthly_council_tax > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Council Tax:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_council_tax) }}</dd>
        </div>

        <div v-if="property.monthly_gas > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Gas:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_gas) }}</dd>
        </div>

        <div v-if="property.monthly_electricity > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Electricity:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_electricity) }}</dd>
        </div>

        <div v-if="property.monthly_water > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Water:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_water) }}</dd>
        </div>

        <div v-if="property.monthly_building_insurance > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Building Insurance:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_building_insurance) }}</dd>
        </div>

        <div v-if="property.monthly_contents_insurance > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Contents Insurance:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_contents_insurance) }}</dd>
        </div>

        <div v-if="property.monthly_service_charge > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Service Charge:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_service_charge) }}</dd>
        </div>

        <div v-if="property.monthly_maintenance_reserve > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Maintenance Reserve:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_maintenance_reserve) }}</dd>
        </div>

        <div v-if="property.other_monthly_costs > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Other Costs:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.other_monthly_costs) }}</dd>
        </div>

        <div v-if="property.property_type === 'buy_to_let'" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Management Agent Fee:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ (parseFloat(property.managing_agent_fee) || 0) > 0 ? formatCurrency(property.managing_agent_fee) : 'Not set' }}</dd>
        </div>

        <div class="flex justify-between py-3 border-t-2 border-horizon-300">
          <dt class="text-base font-semibold text-horizon-500">Total Monthly Costs:</dt>
          <dd class="text-base font-bold text-horizon-500">{{ formatCurrency(totalMonthlyCosts) }}</dd>
        </div>
        <div v-if="isSharedOwnership" class="flex justify-between py-2">
          <dt class="text-base font-semibold text-violet-700">Your Share ({{ property.ownership_percentage }}%):</dt>
          <dd class="text-base font-bold text-violet-600">{{ formatCurrency(userMonthlyCosts) }}</dd>
        </div>
      </dl>
    </div>

    <!-- Buy to Let Financials -->
    <div v-if="property.property_type === 'buy_to_let'" class="bg-white border border-light-gray rounded-lg p-6">
      <h4 class="text-md font-semibold text-horizon-500 mb-4">Rental Income Analysis</h4>

      <!-- Cash Flow Breakdown -->
      <dl class="space-y-2">
        <div class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Your Monthly Rental Income:</dt>
          <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(userMonthlyRentalIncome) }}</dd>
        </div>

        <div class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Less: Running Costs{{ isSharedOwnership ? ' (' + property.ownership_percentage + '%)' : '' }}:</dt>
          <dd class="text-sm font-medium text-raspberry-600">-{{ formatCurrency(userNonMortgageCosts) }}</dd>
        </div>

        <div v-if="userMonthlyMortgagePayments > 0" class="flex justify-between py-2">
          <dt class="text-sm text-neutral-500">Less: Mortgage Payment{{ isSharedOwnership ? ' (' + property.ownership_percentage + '%)' : '' }}:</dt>
          <dd class="text-sm font-medium text-raspberry-600">-{{ formatCurrency(userMonthlyMortgagePayments) }}</dd>
        </div>

        <div class="flex justify-between py-3 border-t-2 border-horizon-300">
          <dt class="text-base font-semibold text-horizon-500">Your Net Monthly Income:</dt>
          <dd class="text-base font-bold" :class="userNetMonthlyIncome >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatCurrency(userNetMonthlyIncome) }}
          </dd>
        </div>
        <div class="flex justify-between py-2 bg-savannah-100 rounded-md p-2 mt-1">
          <dt class="text-sm text-neutral-500">Projected Annual Net Income:</dt>
          <dd class="text-sm font-semibold" :class="userNetAnnualIncome >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
            {{ formatCurrency(userNetAnnualIncome) }}
          </dd>
        </div>
      </dl>

      <!-- Tax Position (separate from cash flow) -->
      <div v-if="property.tax_position" class="mt-6 p-4 bg-violet-50 border border-violet-200 rounded-lg">
        <h5 class="text-sm font-semibold text-violet-900 mb-3">For Your Income Tax Calculation</h5>
        <p class="text-xs text-violet-700 mb-3">These figures are used in your income calculations for UK tax purposes.</p>
        <dl class="space-y-2">
          <div class="flex justify-between py-2 border-b border-violet-100">
            <dt class="text-sm text-violet-800">Taxable Rental Income:</dt>
            <dd class="text-sm font-bold text-violet-900">{{ formatCurrency(property.tax_position.annual_taxable_income) }}/year</dd>
          </div>
          <div class="flex justify-between py-1 pl-4">
            <dt class="text-xs text-violet-600">Rental income minus allowable costs (excl. mortgage)</dt>
            <dd class="text-xs text-violet-600">{{ formatCurrency(property.tax_position.monthly_taxable_income) }}/month</dd>
          </div>

          <div v-if="property.tax_position.monthly_mortgage_interest > 0" class="flex justify-between py-2 border-b border-violet-100">
            <dt class="text-sm text-violet-800">Section 24 Tax Credit (20%):</dt>
            <dd class="text-sm font-bold text-spring-700">-{{ formatCurrency(property.tax_position.section_24_annual_credit) }}/year</dd>
          </div>
          <div v-if="property.tax_position.monthly_mortgage_interest > 0" class="flex justify-between py-1 pl-4">
            <dt class="text-xs text-violet-600">20% of {{ formatCurrency(property.tax_position.monthly_mortgage_interest * 12) }}/year mortgage interest, deducted from tax payable</dt>
            <dd class="text-xs text-violet-600">{{ formatCurrency(property.tax_position.section_24_monthly_credit) }}/month</dd>
          </div>
        </dl>

        <div v-if="property.tax_position.has_interest_portion_missing" class="mt-3 p-3 bg-violet-50 border border-violet-200 rounded-md">
          <p class="text-sm text-violet-800">
            Your mortgage may qualify for a tax credit. Enter the interest portion of your monthly payment in the mortgage details to calculate your Section 24 tax relief (20% of mortgage interest).
          </p>
        </div>
      </div>

      <div v-if="property.tenant_name" class="mt-4 p-4 bg-savannah-100 rounded-md">
        <h5 class="text-sm font-semibold text-horizon-500 mb-2">Tenancy Information</h5>
        <dl class="space-y-1 text-sm">
          <div class="flex justify-between">
            <dt class="text-neutral-500">Tenant:</dt>
            <dd class="font-medium text-horizon-500">{{ property.tenant_name }}</dd>
          </div>
          <div v-if="property.lease_start_date" class="flex justify-between">
            <dt class="text-neutral-500">Lease Start:</dt>
            <dd class="font-medium text-horizon-500">{{ formatDate(property.lease_start_date) }}</dd>
          </div>
          <div v-if="property.lease_end_date" class="flex justify-between">
            <dt class="text-neutral-500">Lease End:</dt>
            <dd class="font-medium text-horizon-500">{{ formatDate(property.lease_end_date) }}</dd>
          </div>
        </dl>
      </div>
    </div>
    </div><!-- end grid wrapper -->

    <!-- SDLT Paid -->
    <div v-if="property.sdlt_paid" class="bg-white border border-light-gray rounded-lg p-6">
      <h4 class="text-md font-semibold text-horizon-500 mb-4">Stamp Duty Land Tax</h4>
      <div class="flex justify-between items-center">
        <span class="text-sm text-neutral-500">Stamp Duty Paid at Purchase:</span>
        <span class="text-lg font-bold text-horizon-500">{{ formatCurrency(property.sdlt_paid) }}</span>
      </div>
    </div>


    <!-- Edit Costs Modal -->
    <div v-if="showEditCostsModal" class="fixed inset-0 bg-horizon-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
      <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="bg-white border-b border-light-gray px-6 py-4 rounded-t-lg">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-semibold text-horizon-500">Edit Monthly Costs</h3>
            <button
              @click="closeEditCostsModal"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
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
                <label for="monthly_council_tax" class="block text-sm font-medium text-horizon-500 mb-1">
                  Council Tax (£/month)
                </label>
                <input
                  id="monthly_council_tax"
                  v-model.number="costsForm.monthly_council_tax"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_gas" class="block text-sm font-medium text-horizon-500 mb-1">
                  Gas (£/month)
                </label>
                <input
                  id="monthly_gas"
                  v-model.number="costsForm.monthly_gas"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_electricity" class="block text-sm font-medium text-horizon-500 mb-1">
                  Electricity (£/month)
                </label>
                <input
                  id="monthly_electricity"
                  v-model.number="costsForm.monthly_electricity"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_water" class="block text-sm font-medium text-horizon-500 mb-1">
                  Water (£/month)
                </label>
                <input
                  id="monthly_water"
                  v-model.number="costsForm.monthly_water"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_building_insurance" class="block text-sm font-medium text-horizon-500 mb-1">
                  Building Insurance (£/month)
                </label>
                <input
                  id="monthly_building_insurance"
                  v-model.number="costsForm.monthly_building_insurance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_contents_insurance" class="block text-sm font-medium text-horizon-500 mb-1">
                  Contents Insurance (£/month)
                </label>
                <input
                  id="monthly_contents_insurance"
                  v-model.number="costsForm.monthly_contents_insurance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_service_charge" class="block text-sm font-medium text-horizon-500 mb-1">
                  Service Charge (£/month)
                </label>
                <input
                  id="monthly_service_charge"
                  v-model.number="costsForm.monthly_service_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="monthly_maintenance_reserve" class="block text-sm font-medium text-horizon-500 mb-1">
                  Maintenance Reserve (£/month)
                </label>
                <input
                  id="monthly_maintenance_reserve"
                  v-model.number="costsForm.monthly_maintenance_reserve"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div>
                <label for="other_monthly_costs" class="block text-sm font-medium text-horizon-500 mb-1">
                  Other Monthly Costs (£/month)
                </label>
                <input
                  id="other_monthly_costs"
                  v-model.number="costsForm.other_monthly_costs"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>

              <div v-if="property.property_type === 'buy_to_let'">
                <label for="managing_agent_fee" class="block text-sm font-medium text-horizon-500 mb-1">
                  Management Agent Fee (£/month)
                </label>
                <input
                  id="managing_agent_fee"
                  v-model.number="costsForm.managing_agent_fee"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-raspberry-500"
                />
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="p-3 bg-savannah-100 rounded-md">
              <p class="text-sm text-raspberry-600">{{ error }}</p>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="bg-savannah-100 border-t border-light-gray px-6 py-4 flex justify-end space-x-2 rounded-b-lg">
            <button
              type="button"
              @click="closeEditCostsModal"
              class="px-4 py-2 bg-white border border-horizon-300 text-neutral-500 rounded-button hover:bg-savannah-100 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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

import logger from '@/utils/logger';
export default {
  name: 'PropertyFinancials',

  emits: ['update-costs'],

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
        managing_agent_fee: null,
      },
    };
  },

  computed: {
    mortgageList() {
      // Use mortgages from prop, or fallback to property.mortgages
      return this.mortgages && this.mortgages.length > 0
        ? this.mortgages
        : (this.property.mortgages || []);
    },

    monthlyMortgagePayments() {
      // Full mortgage payments (for display)
      return this.mortgageList.reduce((total, mortgage) => {
        const payment = parseFloat(mortgage.monthly_payment) || 0;
        return total + payment;
      }, 0);
    },

    userMonthlyMortgagePayments() {
      // User's share of mortgage payments, based on property ownership type
      const total = this.mortgageList.reduce((sum, mortgage) => {
        return sum + (parseFloat(mortgage.monthly_payment) || 0);
      }, 0);
      // Apply ownership split for shared ownership (joint or tenants in common)
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return total * (this.property.ownership_percentage / 100);
      }
      return total;
    },

    isSharedOwnership() {
      return this.property?.ownership_type === 'joint' || this.property?.ownership_type === 'tenants_in_common';
    },

    userPropertyValue() {
      const value = parseFloat(this.property.current_value) || 0;
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return value * (this.property.ownership_percentage / 100);
      }
      return value;
    },

    mortgageBalance() {
      return this.mortgageList.reduce((total, mortgage) => {
        return total + (parseFloat(mortgage.outstanding_balance) || 0);
      }, 0);
    },

    nonMortgageMonthlyCosts() {
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
        (parseFloat(this.property.managing_agent_fee) || 0)
      );
    },

    userNonMortgageCosts() {
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return this.nonMortgageMonthlyCosts * (this.property.ownership_percentage / 100);
      }
      return this.nonMortgageMonthlyCosts;
    },

    totalMonthlyCosts() {
      return this.nonMortgageMonthlyCosts + this.monthlyMortgagePayments;
    },

    userMonthlyCosts() {
      // User's share: property costs use property ownership %, mortgage uses mortgage ownership
      let userNonMortgageCosts = this.nonMortgageMonthlyCosts;
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        userNonMortgageCosts = this.nonMortgageMonthlyCosts * (this.property.ownership_percentage / 100);
      }
      return userNonMortgageCosts + this.userMonthlyMortgagePayments;
    },

    userMonthlyRentalIncome() {
      const fullRentalIncome = parseFloat(this.property.monthly_rental_income) || 0;
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return fullRentalIncome * (this.property.ownership_percentage / 100);
      }
      return fullRentalIncome;
    },

    netMonthlyIncome() {
      const monthlyIncome = parseFloat(this.property.monthly_rental_income) || 0;
      return monthlyIncome - this.totalMonthlyCosts;
    },

    userNetMonthlyIncome() {
      return this.userMonthlyRentalIncome - this.userMonthlyCosts;
    },

    netAnnualIncome() {
      return this.netMonthlyIncome * 12;
    },

    userNetAnnualIncome() {
      return this.userNetMonthlyIncome * 12;
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
      this.costsForm.managing_agent_fee = this.property.managing_agent_fee || null;
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
        logger.error('Failed to save costs:', error);
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
