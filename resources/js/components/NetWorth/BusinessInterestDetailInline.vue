<template>
  <div class="business-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Business Interests
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
      <p class="mt-4 text-neutral-500">Loading business details...</p>
    </div>

    <!-- Business Content -->
    <div v-else-if="business" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', getOwnershipBadgeClass(business.ownership_type)]">
                {{ formatOwnershipType(business.ownership_type) }}
              </span>
              <span :class="['badge', getBusinessTypeBadgeClass(business.business_type)]">
                {{ business.business_type_label || formatBusinessType(business.business_type) }}
              </span>
              <span :class="['badge', getTradingStatusBadgeClass(business.trading_status)]">
                {{ formatTradingStatus(business.trading_status) }}
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ business.business_name }}</h1>
            <p v-if="business.industry_sector" class="text-base sm:text-lg text-neutral-500 mt-1">{{ business.industry_sector }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 sm:space-x-2 w-full sm:w-auto">
            <button
              v-if="business.is_primary_owner !== false"
              v-preview-disabled="'edit'"
              @click="$emit('edit', business)"
              class="w-full sm:w-auto px-4 py-2 bg-purple-600 text-white rounded-button hover:bg-purple-700 transition-colors"
            >
              Edit
            </button>
            <button
              v-if="business.is_primary_owner !== false"
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="w-full sm:w-auto px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Current Valuation</p>
            <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(business.full_value || business.current_valuation) }}</p>
            <p v-if="business.is_shared" class="text-sm text-purple-600 mt-1">
              Your {{ business.ownership_percentage }}% share: {{ formatCurrency(business.user_share) }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Annual Profit</p>
            <p class="text-2xl font-bold" :class="getProfitColorClass(business.annual_profit)">
              {{ formatCurrency(business.annual_profit) }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Employees</p>
            <p class="text-2xl font-bold text-horizon-500">{{ business.employee_count || 0 }}</p>
          </div>
        </div>

        <!-- Business Relief Eligible Notice -->
        <div v-if="business.bpr_eligible" class="mt-4 bg-savannah-100 rounded-lg p-4">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-spring-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm font-medium text-spring-800">Business Relief Eligible - May qualify for 100% Inheritance Tax relief</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-violet-500 text-violet-500'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <!-- Business Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Business Details</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Business Type</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatBusinessType(business.business_type) }}</dd>
                  </div>
                  <div v-if="business.company_number" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Company Number</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ business.company_number }}</dd>
                  </div>
                  <div v-if="business.industry_sector" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Industry</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ business.industry_sector }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Trading Status</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatTradingStatus(business.trading_status) }}</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Financials</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Annual Revenue</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(business.annual_revenue) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Annual Profit</dt>
                    <dd class="text-sm font-medium" :class="getProfitColorClass(business.annual_profit)">{{ formatCurrency(business.annual_profit) }}</dd>
                  </div>
                  <div v-if="business.annual_dividend_income" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Dividend Income</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(business.annual_dividend_income) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Valuation Method</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatValuationMethod(business.valuation_method) }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Tax Registration Details -->
            <div v-if="business.vat_registered || business.utr_number || business.paye_reference">
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Tax Registration</h3>
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-if="business.utr_number" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Unique Taxpayer Reference</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ business.utr_number }}</dd>
                </div>
                <div v-if="business.vat_registered" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">VAT Registration Number</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ business.vat_number || 'Registered' }}</dd>
                </div>
                <div v-if="business.paye_reference" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Employer PAYE Reference</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ business.paye_reference }}</dd>
                </div>
                <div v-if="business.tax_year_end" class="flex justify-between">
                  <dt class="text-sm text-neutral-500">Accounting Year End</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ formatDate(business.tax_year_end) }}</dd>
                </div>
              </dl>
            </div>

            <!-- Description -->
            <div v-if="business.description">
              <h3 class="text-lg font-semibold text-horizon-500 mb-2">Description</h3>
              <p class="text-neutral-500">{{ business.description }}</p>
            </div>
          </div>

          <!-- Tax Deadlines Tab -->
          <div v-else-if="activeTab === 'deadlines'" class="space-y-6">
            <div v-if="loadingDeadlines" class="text-center py-8">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
            </div>

            <div v-else-if="taxDeadlines && taxDeadlines.length > 0">
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Upcoming Tax Deadlines</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                  v-for="(deadline, index) in taxDeadlines"
                  :key="index"
                  class="p-4 rounded-lg border flex flex-col"
                  :class="getDeadlineUrgencyClass(deadline.date)"
                >
                  <div class="flex-1">
                    <p class="font-medium text-horizon-500">{{ deadline.name }}</p>
                    <p class="text-sm text-neutral-500 mt-1">{{ deadline.description }}</p>
                  </div>
                  <div class="mt-3 pt-3 border-t border-light-gray">
                    <p class="font-bold" :class="getDeadlineDateClass(deadline.date)">{{ formatDate(deadline.date) }}</p>
                    <p class="text-sm text-neutral-500">{{ getDaysUntil(deadline.date) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-neutral-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
              </svg>
              <p class="text-lg font-medium">No upcoming deadlines</p>
              <p class="text-sm">Tax deadlines will appear here based on your business type and registration.</p>
            </div>
          </div>

          <!-- Exit Planning Tab -->
          <div v-else-if="activeTab === 'exit'" class="space-y-6">
            <div v-if="loadingExitCalc" class="text-center py-8">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
            </div>

            <div v-else-if="exitCalculation">
              <!-- Exit Scenario Summary -->
              <div class="bg-savannah-100 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-purple-800 mb-4">If You Sold Today</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <p class="text-sm text-neutral-500">Sale Proceeds (Your Share)</p>
                    <p class="text-xl font-bold text-horizon-500">{{ formatCurrency(exitCalculation.user_sale_proceeds) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-neutral-500">Capital Gain</p>
                    <p class="text-xl font-bold" :class="exitCalculation.capital_gain >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                      {{ formatCurrency(exitCalculation.capital_gain) }}
                    </p>
                  </div>
                  <div>
                    <p class="text-sm text-neutral-500">Capital Gains Tax Due</p>
                    <p class="text-xl font-bold text-raspberry-600">{{ formatCurrency(exitCalculation.cgt_due) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-neutral-500">Net Proceeds</p>
                    <p class="text-xl font-bold text-purple-600">{{ formatCurrency(exitCalculation.post_tax_proceeds) }}</p>
                  </div>
                </div>
              </div>

              <!-- Business Asset Disposal Relief Status -->
              <div class="p-4 rounded-lg" :class="exitCalculation.badr_eligible ? 'bg-savannah-100' : 'bg-savannah-100 border border-light-gray'">
                <div class="flex items-center">
                  <svg v-if="exitCalculation.badr_eligible" class="w-6 h-6 text-spring-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else class="w-6 h-6 text-horizon-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  <div>
                    <p class="font-medium" :class="exitCalculation.badr_eligible ? 'text-spring-800' : 'text-neutral-500'">
                      Business Asset Disposal Relief
                    </p>
                    <p class="text-sm" :class="exitCalculation.badr_eligible ? 'text-spring-600' : 'text-neutral-500'">
                      {{ exitCalculation.badr_eligible ? 'Eligible - 10% Capital Gains Tax rate applies' : 'Not currently eligible - standard Capital Gains Tax rates apply' }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Capital Gains Tax Breakdown -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Capital Gains Tax Calculation</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between py-2 border-b border-light-gray">
                    <dt class="text-neutral-500">Sale Proceeds (Your Share)</dt>
                    <dd class="font-medium">{{ formatCurrency(exitCalculation.user_sale_proceeds) }}</dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-light-gray">
                    <dt class="text-neutral-500">Less: Acquisition Cost (Your Share)</dt>
                    <dd class="font-medium text-horizon-500">-{{ formatCurrency(exitCalculation.user_cost_basis) }}</dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-light-gray">
                    <dt class="text-neutral-500">Capital Gain</dt>
                    <dd class="font-medium text-spring-600">{{ formatCurrency(exitCalculation.capital_gain) }}</dd>
                  </div>
                  <div class="flex justify-between py-2 border-b border-light-gray">
                    <dt class="text-neutral-500">Tax Rate Applied</dt>
                    <dd class="font-medium">{{ exitCalculation.cgt_rate }}%</dd>
                  </div>
                  <div class="flex justify-between py-2 bg-savannah-100 -mx-4 px-4 rounded">
                    <dt class="font-semibold text-raspberry-800">Capital Gains Tax Due</dt>
                    <dd class="font-bold text-raspberry-600">{{ formatCurrency(exitCalculation.cgt_due) }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Eligibility Reasons -->
              <div v-if="exitCalculation.badr_reasons && exitCalculation.badr_reasons.length > 0">
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Business Asset Disposal Relief Assessment</h3>
                <ul class="space-y-2">
                  <li v-for="(reason, index) in exitCalculation.badr_reasons" :key="index" class="flex items-start">
                    <svg class="w-5 h-5 text-violet-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-neutral-500">{{ reason }}</span>
                  </li>
                </ul>
              </div>

              <!-- Business Relief Note -->
              <div v-if="exitCalculation.bpr_note" class="p-4 rounded-lg bg-savannah-100">
                <div class="flex items-start">
                  <svg class="w-5 h-5 text-violet-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  <span class="text-sm text-violet-800">{{ exitCalculation.bpr_note }}</span>
                </div>
              </div>

              <!-- Warnings -->
              <div v-if="exitCalculation.warnings && exitCalculation.warnings.length > 0">
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Important Notes</h3>
                <ul class="space-y-2">
                  <li v-for="(warning, index) in exitCalculation.warnings" :key="index" class="flex items-start">
                    <svg class="w-5 h-5 text-violet-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-neutral-500">{{ warning }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <div v-else class="text-center py-8 text-neutral-500">
              <p class="text-lg font-medium">Unable to calculate exit scenario</p>
              <p class="text-sm">Add acquisition cost and date to enable exit planning calculations.</p>
            </div>
          </div>

          <!-- Notes Tab -->
          <div v-else-if="activeTab === 'notes'" class="space-y-4">
            <div v-if="business.notes" class="prose max-w-none">
              <p class="text-neutral-500 whitespace-pre-wrap">{{ business.notes }}</p>
            </div>
            <div v-else class="text-center py-8 text-neutral-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
              <p class="text-lg font-medium">No notes</p>
              <p class="text-sm">Edit this business to add notes.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Business Interest"
      message="Are you sure you want to delete this business interest? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'BusinessInterestDetailInline',

  emits: ['back', 'edit', 'deleted'],

  mixins: [currencyMixin],

  components: {
    ConfirmDialog,
  },

  props: {
    businessId: {
      type: [Number, String],
      required: true,
    },
  },

  data() {
    return {
      activeTab: 'overview',
      tabs: [
        { id: 'overview', label: 'Overview' },
        { id: 'deadlines', label: 'Tax Deadlines' },
        { id: 'exit', label: 'Exit Planning' },
        { id: 'notes', label: 'Notes' },
      ],
      showDeleteConfirm: false,
      loadingDeadlines: false,
      loadingExitCalc: false,
    };
  },

  computed: {
    ...mapState('businessInterests', ['selectedBusiness', 'taxDeadlines', 'exitCalculation', 'loading']),

    business() {
      return this.selectedBusiness;
    },
  },

  watch: {
    businessId: {
      immediate: true,
      handler(newId) {
        if (newId) {
          this.loadBusiness();
        }
      },
    },

    activeTab(newTab) {
      if (newTab === 'deadlines' && !this.taxDeadlines) {
        this.loadTaxDeadlines();
      } else if (newTab === 'exit' && !this.exitCalculation) {
        this.loadExitCalculation();
      }
    },
  },

  methods: {
    ...mapActions('businessInterests', ['fetchBusinessById', 'fetchTaxDeadlines', 'fetchExitCalculation', 'deleteBusiness']),

    async loadBusiness() {
      try {
        await this.fetchBusinessById(this.businessId);
      } catch (error) {
        logger.error('Failed to load business:', error);
      }
    },

    async loadTaxDeadlines() {
      this.loadingDeadlines = true;
      try {
        await this.fetchTaxDeadlines(this.businessId);
      } catch (error) {
        logger.error('Failed to load tax deadlines:', error);
      } finally {
        this.loadingDeadlines = false;
      }
    },

    async loadExitCalculation() {
      this.loadingExitCalc = true;
      try {
        await this.fetchExitCalculation(this.businessId);
      } catch (error) {
        logger.error('Failed to load exit calculation:', error);
      } finally {
        this.loadingExitCalc = false;
      }
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteBusiness(this.businessId);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
        this.$emit('back');
      } catch (error) {
        logger.error('Failed to delete business:', error);
      }
    },

    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    formatBusinessType(type) {
      const types = {
        sole_trader: 'Sole Trader',
        partnership: 'Partnership',
        limited_company: 'Limited Company',
        llp: 'LLP',
        other: 'Other',
      };
      return types[type] || type;
    },

    formatTradingStatus(status) {
      const statuses = {
        trading: 'Trading',
        dormant: 'Dormant',
        pre_trading: 'Pre-Trading',
      };
      return statuses[status] || status;
    },

    formatOwnershipType(type) {
      const types = {
        individual: 'Individual',
        joint: 'Joint',
        trust: 'Trust',
      };
      return types[type] || type;
    },

    formatValuationMethod(method) {
      if (!method) return 'Not specified';
      return method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    getOwnershipBadgeClass(type) {
      return type === 'joint' ? 'bg-purple-500 text-white' : 'bg-savannah-1000 text-white';
    },

    getBusinessTypeBadgeClass(type) {
      const classes = {
        sole_trader: 'bg-violet-500 text-white',
        partnership: 'bg-teal-500 text-white',
        limited_company: 'bg-purple-500 text-white',
        llp: 'bg-indigo-500 text-white',
        other: 'bg-savannah-1000 text-white',
      };
      return classes[type] || 'bg-savannah-1000 text-white';
    },

    getTradingStatusBadgeClass(status) {
      const classes = {
        trading: 'bg-spring-500 text-white',
        dormant: 'bg-savannah-1000 text-white',
        pre_trading: 'bg-violet-500 text-white',
      };
      return classes[status] || 'bg-savannah-1000 text-white';
    },

    getProfitColorClass(profit) {
      if (profit === null || profit === undefined) return 'text-horizon-500';
      return profit >= 0 ? 'text-spring-600' : 'text-raspberry-600';
    },

    getDeadlineUrgencyClass(date) {
      const days = this.getDaysUntilNumber(date);
      if (days < 0) return 'bg-savannah-100';
      if (days <= 30) return 'bg-savannah-100';
      return 'bg-savannah-100 border border-light-gray';
    },

    getDeadlineDateClass(date) {
      const days = this.getDaysUntilNumber(date);
      if (days < 0) return 'text-raspberry-600';
      if (days <= 30) return 'text-violet-600';
      return 'text-horizon-500';
    },

    getDaysUntilNumber(date) {
      if (!date) return 999;
      const target = new Date(date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      target.setHours(0, 0, 0, 0);
      return Math.ceil((target - today) / (1000 * 60 * 60 * 24));
    },

    getDaysUntil(date) {
      const days = this.getDaysUntilNumber(date);
      if (days < 0) return `${Math.abs(days)} days overdue`;
      if (days === 0) return 'Due today';
      if (days === 1) return 'Due tomorrow';
      return `${days} days`;
    },
  },
};
</script>

<style scoped>
.badge {
  @apply px-2.5 py-0.5 rounded-full text-xs font-medium;
}
</style>
