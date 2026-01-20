<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-8">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p class="mt-4 text-gray-600">Loading property details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-6 text-center">
      <p class="text-red-600">{{ error }}</p>
      <button
        @click="loadProperty"
        class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
      >
        Retry
      </button>
    </div>

    <!-- Property Content -->
    <div v-else-if="property" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{{ propertyAddress }}</h1>
            <p class="text-base sm:text-lg text-gray-600 mt-1">{{ propertyTypeLabel }}</p>
          </div>
          <div class="flex space-x-2 w-full sm:w-auto">
            <button
              @click="showEditModal = true"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Edit
            </button>
            <button
              @click="confirmDelete"
              class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Full Property Value</p>
            <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(calculateFullPropertyValue()) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Your Share ({{ property.ownership_percentage }}%)</p>
            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(calculateUserPropertyShare()) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Equity</p>
            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(property.equity || 0) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm text-gray-600">Mortgage Balance</p>
            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(mortgageBalance) }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-4" v-if="property.property_type === 'buy_to_let'">
            <p class="text-sm text-gray-600">Net Rental Yield</p>
            <p class="text-2xl font-bold text-blue-600">{{ property.net_rental_yield || 0 }}%</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors"
              :class="
                activeTab === tab.id
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Property Details</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Address:</dt>
                    <dd class="text-sm font-medium text-gray-900 text-right">{{ propertyAddress }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Postcode:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ property.postcode }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Property Type:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ propertyTypeLabel }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Purchase Date:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatDate(property.purchase_date) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Purchase Price:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(property.purchase_price) }}</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Ownership</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Ownership Type:</dt>
                    <dd class="text-sm font-medium text-gray-900 capitalize">{{ property.ownership_type }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Ownership Percentage:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ property.ownership_percentage }}%</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Valuation</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Full Property Value:</dt>
                    <dd class="text-sm font-medium text-blue-600 font-semibold">{{ formatCurrency(calculateFullPropertyValue()) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Your Share ({{ property.ownership_percentage }}%):</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(calculateUserPropertyShare()) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Valuation Date:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatDate(property.valuation_date) || 'Not set' }}</dd>
                  </div>
                  <div v-if="property.purchase_price" class="flex justify-between">
                    <dt class="text-sm text-gray-600">Value Change:</dt>
                    <dd class="text-sm font-medium" :class="valueChange >= 0 ? 'text-green-600' : 'text-red-600'">
                      {{ formatCurrency(valueChange) }} ({{ valueChangePercent }}%)
                    </dd>
                  </div>
                </dl>
              </div>

              <div v-if="property.property_type === 'buy_to_let'">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Rental Income</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Full Monthly Rental Income:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(property.monthly_rental_income) }}</dd>
                  </div>
                  <div v-if="isSharedOwnership" class="flex justify-between">
                    <dt class="text-sm text-gray-600">Your Share ({{ property.ownership_percentage }}%):</dt>
                    <dd class="text-sm font-medium text-blue-600">{{ formatCurrency(calculateUserRentalIncome()) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-gray-600">Full Annual Rental Income:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ formatCurrency((property.monthly_rental_income || 0) * 12) }}</dd>
                  </div>
                  <div v-if="isSharedOwnership" class="flex justify-between">
                    <dt class="text-sm text-gray-600">Your Annual Share:</dt>
                    <dd class="text-sm font-medium text-blue-600">{{ formatCurrency(calculateUserRentalIncome() * 12) }}</dd>
                  </div>
                  <div class="flex justify-between" v-if="property.tenant_name">
                    <dt class="text-sm text-gray-600">Tenant:</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ property.tenant_name }}</dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <!-- Mortgage Tab -->
          <div v-show="activeTab === 'mortgage'" class="space-y-6">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-800">Mortgages</h3>
              <button
                @click="showEditModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                Add Mortgage
              </button>
            </div>

            <div v-if="mortgages.length === 0" class="text-center py-8 text-gray-500">
              <p>No mortgages found for this property.</p>
            </div>

            <div v-else class="space-y-6">
              <div
                v-for="mortgage in mortgages"
                :key="mortgage.id"
                class="bg-white border border-gray-200 rounded-lg p-6"
              >
                <!-- Mortgage Header -->
                <div class="flex justify-between items-start mb-6">
                  <div>
                    <h4 class="text-xl font-semibold text-gray-900">{{ mortgage.lender_name }}</h4>
                    <p class="text-sm text-gray-600 mt-1">{{ formatMortgageType(mortgage.mortgage_type) }}</p>
                  </div>
                  <button
                    @click="deleteMortgageConfirm(mortgage.id)"
                    class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                  >
                    Delete
                  </button>
                </div>

                <!-- Mortgage Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <!-- Mortgage Details Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-3">Mortgage Details</h5>
                    <dl class="space-y-2">
                      <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Lender:</dt>
                        <dd class="text-sm font-medium text-gray-900 text-right">{{ mortgage.lender_name }}</dd>
                      </div>
                      <div v-if="mortgage.mortgage_account_number" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Account Number:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ mortgage.mortgage_account_number }}</dd>
                      </div>
                      <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Mortgage Type:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ formatMortgageType(mortgage.mortgage_type) }}</dd>
                      </div>
                      <div v-if="mortgage.mortgage_type === 'mixed' && mortgage.repayment_percentage" class="flex justify-between">
                        <dt class="text-sm text-gray-600 pl-4">└ Repayment:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ mortgage.repayment_percentage }}%</dd>
                      </div>
                      <div v-if="mortgage.mortgage_type === 'mixed' && mortgage.interest_only_percentage" class="flex justify-between">
                        <dt class="text-sm text-gray-600 pl-4">└ Interest Only:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ mortgage.interest_only_percentage }}%</dd>
                      </div>
                      <div v-if="mortgage.country" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Property Country:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ mortgage.country }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Loan Information Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-3">Loan Information</h5>
                    <dl class="space-y-2">
                      <div v-if="mortgage.original_loan_amount" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Original Loan Amount:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(mortgage.original_loan_amount) }}</dd>
                      </div>
                      <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Outstanding Balance:</dt>
                        <dd class="text-sm font-medium text-blue-600 font-semibold">{{ formatCurrency(mortgage.outstanding_balance) }}</dd>
                      </div>
                      <div v-if="mortgage.ownership_type === 'joint' && property.ownership_percentage && property.ownership_percentage < 100" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Your Share ({{ property.ownership_percentage }}%):</dt>
                        <dd class="text-sm font-medium text-blue-600">{{ formatCurrency(calculateUserMortgageShare(mortgage)) }}</dd>
                      </div>
                      <div v-if="mortgage.original_loan_amount" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Amount Paid Off:</dt>
                        <dd class="text-sm font-medium text-green-600">{{ formatCurrency(mortgage.original_loan_amount - mortgage.outstanding_balance) }}</dd>
                      </div>
                      <div v-if="property.current_value && mortgage.outstanding_balance" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Loan-to-Value (LTV):</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ calculateLTV(mortgage) }}%</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Interest Rate Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-3">Interest Rate</h5>
                    <dl class="space-y-2">
                      <div v-if="mortgage.rate_type !== 'mixed' && mortgage.interest_rate != null" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Interest Rate:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ parseFloat(mortgage.interest_rate).toFixed(2) }}%</dd>
                      </div>
                      <div v-if="mortgage.rate_type" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Rate Type:</dt>
                        <dd class="text-sm font-medium text-gray-900 capitalize">{{ mortgage.rate_type }}</dd>
                      </div>
                      <div v-if="mortgage.rate_type === 'mixed' && mortgage.fixed_rate_percentage" class="flex justify-between">
                        <dt class="text-sm text-gray-600 pl-4">└ Fixed ({{ parseFloat(mortgage.fixed_rate_percentage).toFixed(2) }}%):</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ parseFloat(mortgage.fixed_interest_rate).toFixed(2) }}%</dd>
                      </div>
                      <div v-if="mortgage.rate_type === 'mixed' && mortgage.variable_rate_percentage" class="flex justify-between">
                        <dt class="text-sm text-gray-600 pl-4">└ Variable ({{ parseFloat(mortgage.variable_rate_percentage).toFixed(2) }}%):</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ parseFloat(mortgage.variable_interest_rate).toFixed(2) }}%</dd>
                      </div>
                      <div v-if="mortgage.rate_type === 'fixed' && mortgage.rate_fix_end_date" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Rate Fix Ends:</dt>
                        <dd class="text-sm font-medium text-orange-600">{{ formatDate(mortgage.rate_fix_end_date) }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Payment Information Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-3">Payment Information</h5>
                    <dl class="space-y-2">
                      <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Full Monthly Payment:</dt>
                        <dd class="text-sm font-medium text-blue-600 font-semibold">{{ formatCurrency(calculateFullMonthlyPayment(mortgage)) }}</dd>
                      </div>
                      <div v-if="mortgage.ownership_type === 'joint'" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Your Share ({{ property.ownership_percentage }}%):</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(calculateFullMonthlyPayment(mortgage) * (property.ownership_percentage / 100)) }}</dd>
                      </div>
                      <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Full Annual Payment:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(calculateFullMonthlyPayment(mortgage) * 12) }}</dd>
                      </div>
                      <div v-if="mortgage.start_date && mortgage.maturity_date" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Remaining Term:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ calculateRemainingTerm(mortgage.maturity_date) }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Dates Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-3">Important Dates</h5>
                    <dl class="space-y-2">
                      <div v-if="mortgage.start_date" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Start Date:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ formatDate(mortgage.start_date) }}</dd>
                      </div>
                      <div v-if="mortgage.maturity_date" class="flex justify-between">
                        <dt class="text-sm text-gray-600">End Date:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ formatDate(mortgage.maturity_date) }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Ownership Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-3">Ownership</h5>
                    <dl class="space-y-2">
                      <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Ownership Type:</dt>
                        <dd class="text-sm font-medium text-gray-900 capitalize">{{ mortgage.ownership_type === 'individual' ? 'Individual' : 'Joint' }}</dd>
                      </div>
                      <div v-if="mortgage.ownership_type === 'individual'" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Owner:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ currentUserName }}</dd>
                      </div>
                      <div v-if="mortgage.ownership_type === 'joint' && mortgage.joint_owner_name" class="flex justify-between">
                        <dt class="text-sm text-gray-600">Joint Owner:</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ mortgage.joint_owner_name }}</dd>
                      </div>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Financials Tab -->
          <div v-show="activeTab === 'financials'">
            <PropertyFinancials
              :property="property"
              :mortgages="mortgages"
              @update-costs="handleCostsUpdate"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <PropertyForm
      v-if="showEditModal"
      :property="property"
      @save="handlePropertyUpdate"
      @close="showEditModal = false"
    />

    <ConfirmationModal
      v-if="showDeleteConfirm"
      title="Delete Property"
      message="Are you sure you want to delete this property? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

    <ConfirmationModal
      v-if="showDeleteMortgageConfirm"
      title="Delete Mortgage"
      message="Are you sure you want to delete this mortgage?"
      @confirm="handleMortgageDelete"
      @cancel="showDeleteMortgageConfirm = false"
    />
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import PropertyForm from './PropertyForm.vue';
import PropertyFinancials from './PropertyFinancials.vue';
import ConfirmationModal from '../../Common/ConfirmationModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PropertyDetail',
  mixins: [currencyMixin],

  components: {
    AppLayout,
    PropertyForm,
    PropertyFinancials,
    ConfirmationModal,
  },

  data() {
    return {
      propertyId: parseInt(this.$route.params.id),
      activeTab: 'overview',
      tabs: [
        { id: 'overview', label: 'Overview' },
        { id: 'mortgage', label: 'Mortgage' },
        { id: 'financials', label: 'Financials' },
      ],
      showEditModal: false,
      showDeleteConfirm: false,
      showDeleteMortgageConfirm: false,
      mortgageToDelete: null,
    };
  },

  computed: {
    ...mapState('netWorth', ['selectedProperty', 'mortgages', 'loading', 'error']),

    property() {
      return this.selectedProperty;
    },

    propertyAddress() {
      if (!this.property) return '';
      const parts = [
        this.property.address_line_1,
        this.property.address_line_2,
        this.property.city,
      ].filter(Boolean);
      return parts.join(', ');
    },

    propertyTypeLabel() {
      const types = {
        main_residence: 'Main Residence',
        secondary_residence: 'Secondary Residence',
        buy_to_let: 'Buy to Let',
      };
      return types[this.property?.property_type] || '';
    },

    mortgageBalance() {
      // If detailed mortgage records exist, sum them
      if (this.mortgages && this.mortgages.length > 0) {
        return this.mortgages.reduce((sum, m) => sum + (m.outstanding_balance || 0), 0);
      }
      // Otherwise, fall back to simple outstanding_mortgage field from property
      return this.property?.outstanding_mortgage || 0;
    },

    valueChange() {
      if (!this.property) return 0;
      return this.property.current_value - this.property.purchase_price;
    },

    valueChangePercent() {
      if (!this.property || this.property.purchase_price === 0) return '0.00';
      const percent = (this.valueChange / this.property.purchase_price) * 100;
      return percent.toFixed(2);
    },

    currentUserName() {
      const user = this.$store.getters['auth/currentUser'];
      if (!user) return 'You';
      return `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'You';
    },
  },

  mounted() {
    this.loadProperty();
  },

  methods: {
    ...mapActions('netWorth', [
      'fetchProperty',
      'fetchPropertyMortgages',
      'updateProperty',
      'deleteProperty',
      'createMortgage',
      'updateMortgage',
      'deleteMortgage',
    ]),

    async loadProperty() {
      try {
        await this.fetchProperty(this.propertyId);
        await this.fetchPropertyMortgages(this.propertyId);
      } catch (error) {
        console.error('Failed to load property:', error);
      }
    },

    async handlePropertyUpdate(data) {
      try {
        // Update property
        await this.updateProperty({ id: this.propertyId, data: data.property });

        // If mortgage data is included, create/update mortgage
        if (data.mortgage && data.mortgage.outstanding_balance) {
          try {
            // Check if this property already has a mortgage (we'll update first one if exists)
            if (this.mortgages && this.mortgages.length > 0) {
              await this.updateMortgage({
                id: this.mortgages[0].id,
                data: data.mortgage,
                propertyId: this.propertyId,
              });
            } else {
              // Create new mortgage
              await this.createMortgage({
                propertyId: this.propertyId,
                data: data.mortgage,
              });
            }
          } catch (mortgageError) {
            console.error('Failed to save mortgage:', mortgageError);
            // Don't throw - property was saved successfully
          }
        }

        this.showEditModal = false;
        // Refresh property data
        await this.loadProperty();
      } catch (error) {
        console.error('Failed to update property:', error);
      }
    },

    async handleCostsUpdate(costsData) {
      try {
        await this.updateProperty({ id: this.propertyId, data: costsData });
        // Refresh property data
        await this.loadProperty();
      } catch (error) {
        console.error('Failed to update costs:', error);
        throw error;
      }
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteProperty(this.propertyId);
        this.showDeleteConfirm = false;
        // Navigate back to net worth property list
        this.$router.push({ name: 'NetWorthProperty' });
      } catch (error) {
        console.error('Failed to delete property:', error);
      }
    },


    deleteMortgageConfirm(mortgageId) {
      this.mortgageToDelete = mortgageId;
      this.showDeleteMortgageConfirm = true;
    },

    async handleMortgageDelete() {
      try {
        await this.deleteMortgage({
          id: this.mortgageToDelete,
          propertyId: this.propertyId,
        });
        this.showDeleteMortgageConfirm = false;
        this.mortgageToDelete = null;
        // Refresh data
        await this.loadProperty();
      } catch (error) {
        console.error('Failed to delete mortgage:', error);
      }
    },

    formatMortgageType(type) {
      const types = {
        repayment: 'Repayment',
        interest_only: 'Interest Only',
        part_and_part: 'Part and Part',
        mixed: 'Mixed',
      };
      return types[type] || type;
    },

    calculateFullOutstandingBalance(mortgage) {
      // Single-record pattern: DB stores FULL balance
      // Use mortgage_full_balance from API if available, otherwise outstanding_balance is already full
      return mortgage.mortgage_full_balance ?? mortgage.outstanding_balance ?? 0;
    },

    calculateFullMonthlyPayment(mortgage) {
      // Single-record pattern: DB stores FULL payment
      // For now, monthly_payment in DB is full value
      return mortgage.monthly_payment ?? 0;
    },

    calculateFullPropertyValue() {
      // Single-record pattern: DB stores FULL value
      // Use full_value from API if available, otherwise current_value is already full
      return this.property?.full_value ?? this.property?.current_value ?? 0;
    },

    calculateUserPropertyShare() {
      // Single-record pattern: Calculate user's share from full value
      if (this.property?.user_share !== undefined) {
        return this.property.user_share;
      }
      // Fallback: calculate from full value
      const fullValue = this.calculateFullPropertyValue();
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return fullValue * (this.property.ownership_percentage / 100);
      }
      return fullValue;
    },

    calculateUserMortgageShare(mortgage) {
      const fullBalance = mortgage.outstanding_balance || 0;
      // For joint mortgages, calculate user's share based on property ownership %
      if (mortgage.ownership_type === 'joint' && this.property?.ownership_percentage) {
        return fullBalance * (this.property.ownership_percentage / 100);
      }
      // Individual mortgage = 100% belongs to this user
      return fullBalance;
    },

    isSharedOwnership() {
      return this.property?.ownership_type === 'joint' || this.property?.ownership_type === 'tenants_in_common';
    },

    calculateUserRentalIncome() {
      // Single-record pattern: Calculate user's share of rental income
      const fullRentalIncome = this.property?.monthly_rental_income || 0;
      if (this.isSharedOwnership() && this.property?.ownership_percentage) {
        return fullRentalIncome * (this.property.ownership_percentage / 100);
      }
      return fullRentalIncome;
    },

    calculateLTV(mortgage) {
      // Always use full amounts for correct LTV calculation
      const fullBalance = this.calculateFullOutstandingBalance(mortgage);
      const fullValue = this.calculateFullPropertyValue();

      if (!fullValue || fullValue === 0) return '0.00';

      const ltv = (fullBalance / fullValue) * 100;
      return ltv.toFixed(2);
    },

    calculateRemainingTerm(maturityDate) {
      if (!maturityDate) return 'N/A';
      const today = new Date();
      const maturity = new Date(maturityDate);
      const diffTime = maturity - today;
      const diffMonths = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 30.44)); // Average days per month

      if (diffMonths <= 0) return 'Matured';

      const years = Math.floor(diffMonths / 12);
      const months = diffMonths % 12;

      if (years === 0) return `${months} month${months !== 1 ? 's' : ''}`;
      if (months === 0) return `${years} year${years !== 1 ? 's' : ''}`;
      return `${years} year${years !== 1 ? 's' : ''}, ${months} month${months !== 1 ? 's' : ''}`;
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
