<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-8">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      <p class="mt-4 text-neutral-500">Loading policy details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-6 text-center">
      <p class="text-raspberry-600">{{ error }}</p>
      <button
        @click="loadPolicy"
        class="mt-4 px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
      >
        Retry
      </button>
    </div>

    <!-- Policy Content -->
    <div v-else-if="policy" class="space-y-6">
      <!-- Back Button -->
      <button @click="$router.push('/protection')" class="detail-inline-back mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to Policies
      </button>

      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span
                class="px-3 py-1 text-xs font-semibold rounded-full"
                :class="policyTypeBadgeClass"
              >
                {{ policyTypeLabel }}
              </span>
              <!-- Life Policy Type Tag (only for life insurance) -->
              <span
                v-if="isLifePolicy && lifePolicyTypeLabel"
                class="px-2 py-1 text-xs font-medium bg-spring-100 text-spring-700 rounded"
              >
                {{ lifePolicyTypeLabel }}
              </span>
              <span
                v-if="isActive"
                class="px-2 py-1 text-xs font-medium bg-spring-100 text-spring-800 rounded"
              >
                Active
              </span>
              <span
                v-else
                class="px-2 py-1 text-xs font-medium bg-savannah-100 text-horizon-500 rounded"
              >
                Inactive
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ policy.provider || 'Unknown Provider' }}</h1>
            <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ policyTypeLabel }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 sm:space-x-2 w-full sm:w-auto">
            <button
              @click="showEditModal = true"
              class="w-full sm:w-auto px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
            >
              Edit
            </button>
            <button
              @click="confirmDelete"
              class="w-full sm:w-auto px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mt-6">
          <div class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-sm text-neutral-500">{{ coverageLabel }}</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(coverageAmount) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Premium</p>
            <p class="text-2xl font-bold text-violet-600">{{ formatCurrency(policy.premium_amount) }}</p>
            <p class="text-xs text-neutral-500 mt-1">per {{ policy.premium_frequency || 'month' }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Annual Cost</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(annualCost) }}</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors"
              :class="
                activeTab === tab.id
                  ? 'border-violet-600 text-violet-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
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
              <!-- Policy Details -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Policy Details</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Provider:</dt>
                    <dd class="text-sm font-medium text-horizon-500 text-right">{{ policy.provider || 'N/A' }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Policy Number:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ policy.policy_number || 'N/A' }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Policy Type:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ policyTypeLabel }}</dd>
                  </div>
                  <div v-if="isLifePolicy && lifePolicyTypeLabel" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Life Policy Type:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ lifePolicyTypeLabel }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Coverage Details -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Coverage</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">{{ coverageLabel }}:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(coverageAmount) }}</dd>
                  </div>
                  <div v-if="policy.policy_type === 'life' && policy.policy_subtype === 'decreasing_term' && policy.decreasing_rate" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Decreasing Rate:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ policy.decreasing_rate * 100 }}% per year</dd>
                  </div>
                  <div v-if="!isLifeOrCriticalIllness && policy.benefit_frequency" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Benefit Frequency:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatBenefitFrequency(policy.benefit_frequency) }}</dd>
                  </div>
                  <div v-if="!isLifeOrCriticalIllness && policy.waiting_period_weeks" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Waiting Period:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ policy.waiting_period_weeks }} weeks</dd>
                  </div>
                  <div v-if="!isLifeOrCriticalIllness && policy.benefit_period_months" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Benefit Period:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ policy.benefit_period_months }} months</dd>
                  </div>
                </dl>
              </div>

              <!-- Premium Details -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Premium</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Premium Amount:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(policy.premium_amount) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Frequency:</dt>
                    <dd class="text-sm font-medium text-horizon-500 capitalize">{{ policy.premium_frequency || 'N/A' }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Annual Cost:</dt>
                    <dd class="text-sm font-medium text-horizon-500 font-semibold">{{ formatCurrency(annualCost) }}</dd>
                  </div>
                </dl>
              </div>

              <!-- Policy Dates -->
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Important Dates</h3>
                <dl class="space-y-2">
                  <div v-if="policyStartDate" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Start Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(policyStartDate) }}</dd>
                  </div>
                  <div v-if="policyTermYears" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Term:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ policyTermYears }} years</dd>
                  </div>
                  <div v-if="policyEndDate" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">End Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(policyEndDate) }}</dd>
                  </div>
                  <div v-if="remainingYears !== null" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Remaining Term:</dt>
                    <dd class="text-sm font-medium text-horizon-500" :class="remainingYears < 5 ? 'text-violet-600' : ''">
                      {{ remainingYears }} years
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <!-- Beneficiaries Tab -->
          <div v-show="activeTab === 'beneficiaries'" class="space-y-6">
            <div>
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Beneficiary Information</h3>
              <div v-if="policy.beneficiaries" class="bg-eggshell-500 rounded-lg p-6">
                <p class="text-sm text-horizon-500 whitespace-pre-line">{{ policy.beneficiaries }}</p>
              </div>
              <div v-else class="text-center py-8 text-neutral-500">
                <p>No beneficiary information recorded</p>
              </div>
            </div>

            <div v-if="policy.in_trust" class="bg-violet-50 border border-violet-200 rounded-lg p-4">
              <div class="flex items-start">
                <svg class="h-5 w-5 text-violet-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h4 class="text-sm font-medium text-violet-800">Policy Held in Trust</h4>
                  <p class="mt-1 text-sm text-violet-700">
                    This policy is held in trust, which means the payout will not form part of your estate for inheritance tax purposes.
                  </p>
                </div>
              </div>
            </div>

            <div v-if="!policy.in_trust" class="bg-violet-50 border border-violet-200 rounded-lg p-4">
              <div class="flex items-start">
                <svg class="h-5 w-5 text-violet-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h4 class="text-sm font-medium text-violet-800">Consider a Trust</h4>
                  <p class="mt-1 text-sm text-violet-700">
                    This policy is not currently held in trust. Consider placing it in trust to potentially reduce inheritance tax liability and ensure faster payout to beneficiaries.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Conditions Tab (for Critical Illness and Income Protection) -->
          <div v-show="activeTab === 'conditions'" class="space-y-6">
            <div>
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Covered Conditions</h3>
              <div v-if="coveredConditions.length > 0" class="bg-eggshell-500 rounded-lg p-6">
                <ul class="space-y-2">
                  <li
                    v-for="(condition, index) in coveredConditions"
                    :key="index"
                    class="flex items-start"
                  >
                    <svg class="h-5 w-5 text-spring-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-horizon-500">{{ condition }}</span>
                  </li>
                </ul>
              </div>
              <div v-else class="text-center py-8 text-neutral-500">
                <p>No specific conditions recorded</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <PolicyFormModal
      v-if="showEditModal"
      :policy="policy"
      :is-editing="true"
      @save="handlePolicyUpdate"
      @close="showEditModal = false"
    />

    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Policy"
      message="Are you sure you want to delete this policy? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import PolicyFormModal from './PolicyFormModal.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'PolicyDetail',
  mixins: [currencyMixin],

  components: {
    AppLayout,
    PolicyFormModal,
    ConfirmDialog,
  },

  data() {
    return {
      policyType: this.$route.params.policyType,
      policyId: parseInt(this.$route.params.id),
      activeTab: 'overview',
      showEditModal: false,
      showDeleteConfirm: false,
      loading: false,
      error: null,
      policy: null,
    };
  },

  computed: {
    ...mapGetters('protection', ['policies']),

    tabs() {
      const baseTabs = [
        { id: 'overview', label: 'Overview' },
      ];

      // Only show beneficiaries tab for life insurance policies
      if (this.policy?.policy_type === 'life') {
        baseTabs.push({ id: 'beneficiaries', label: 'Beneficiaries' });
      }

      // Add conditions tab for critical illness and income protection
      if (this.policy && (this.policy.policy_type === 'criticalIllness' || this.policy.policy_type === 'incomeProtection')) {
        baseTabs.push({ id: 'conditions', label: 'Covered Conditions' });
      }

      return baseTabs;
    },

    policyTypeLabel() {
      const labels = {
        life: 'Life Insurance',
        criticalIllness: 'Critical Illness',
        incomeProtection: 'Income Protection',
        disability: 'Disability',
        sicknessIllness: 'Sickness/Illness',
      };
      return labels[this.policy?.policy_type] || 'Policy';
    },

    policyTypeBadgeClass() {
      const classes = {
        life: 'bg-violet-100 text-violet-800',
        criticalIllness: 'bg-purple-100 text-purple-800',
        incomeProtection: 'bg-spring-100 text-spring-800',
        disability: 'bg-indigo-100 text-indigo-800',
        sicknessIllness: 'bg-raspberry-100 text-raspberry-800',
      };
      return classes[this.policy?.policy_type] || 'bg-savannah-100 text-horizon-500';
    },

    coverageLabel() {
      const type = this.policy?.policy_type;
      if (type === 'life' || type === 'criticalIllness') {
        return 'Sum Assured';
      }
      return 'Benefit Amount';
    },

    coverageAmount() {
      return this.policy?.sum_assured || this.policy?.benefit_amount || 0;
    },

    isLifeOrCriticalIllness() {
      return this.policy?.policy_type === 'life' || this.policy?.policy_type === 'criticalIllness';
    },

    isLifePolicy() {
      return this.policy?.policy_type === 'life';
    },

    lifePolicyTypeLabel() {
      if (!this.isLifePolicy || !this.policy?.policy_subtype) return null;

      const labels = {
        decreasing_term: 'Decreasing Term',
        level_term: 'Level Term',
        whole_of_life: 'Whole of Life',
        term: 'Term',
        family_income_benefit: 'Family Income Benefit',
      };
      return labels[this.policy.policy_subtype] || this.policy.policy_subtype;
    },

    policyStartDate() {
      return this.policy?.policy_start_date || this.policy?.start_date;
    },

    policyTermYears() {
      return this.policy?.policy_term_years || this.policy?.term_years;
    },

    policyEndDate() {
      if (!this.policyStartDate || !this.policyTermYears) return null;
      const startDate = new Date(this.policyStartDate);
      const endDate = new Date(startDate);
      endDate.setFullYear(endDate.getFullYear() + this.policyTermYears);
      return endDate;
    },

    remainingYears() {
      if (!this.policyEndDate) return null;
      const now = new Date();
      const end = new Date(this.policyEndDate);
      const diffTime = end - now;
      const diffYears = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 365.25));
      return Math.max(0, diffYears);
    },

    isActive() {
      if (!this.policyStartDate) return false;

      const startDate = new Date(this.policyStartDate);
      const now = new Date();

      if (startDate > now) return false; // Not started yet

      if (this.policyTermYears) {
        const endDate = new Date(startDate);
        endDate.setFullYear(endDate.getFullYear() + this.policyTermYears);
        return endDate > now;
      }

      if (this.policy?.benefit_period_months) {
        const endDate = new Date(startDate);
        endDate.setMonth(endDate.getMonth() + this.policy.benefit_period_months);
        return endDate > now;
      }

      return true; // No end date specified, assume active
    },

    annualCost() {
      if (!this.policy?.premium_amount) return 0;
      const frequency = this.policy.premium_frequency || 'monthly';
      const amount = parseFloat(this.policy.premium_amount);

      switch (frequency) {
        case 'monthly':
          return amount * 12;
        case 'quarterly':
          return amount * 4;
        case 'annually':
          return amount;
        default:
          return amount * 12;
      }
    },

    coveredConditions() {
      if (!this.policy?.covered_conditions) return [];

      // Check if it's already an array
      if (Array.isArray(this.policy.covered_conditions)) {
        return this.policy.covered_conditions;
      }

      // Try to parse JSON string
      try {
        return JSON.parse(this.policy.covered_conditions);
      } catch {
        return [];
      }
    },
  },

  mounted() {
    this.loadPolicy();
  },

  methods: {
    async loadPolicy() {
      this.loading = true;
      this.error = null;

      try {
        // Fetch all policies from the store
        await this.$store.dispatch('protection/fetchProtectionData');

        // Get the specific policy from the store
        const policies = this.policies;
        const policyArray = policies[this.policyType] || [];
        let foundPolicy = policyArray.find(p => p.id === this.policyId);

        if (!foundPolicy) {
          this.error = 'Policy not found';
          return;
        }

        // For life insurance, preserve the original policy_type as policy_subtype
        if (this.policyType === 'life') {
          this.policy = {
            ...foundPolicy,
            policy_subtype: foundPolicy.policy_type, // Preserve life policy type (e.g., decreasing_term, level_term)
            policy_type: this.policyType, // Set general type to 'life'
          };
        } else {
          this.policy = {
            ...foundPolicy,
            policy_type: this.policyType,
          };
        }
      } catch (error) {
        logger.error('Failed to load policy:', error);
        this.error = 'Failed to load policy details. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async handlePolicyUpdate(updatedPolicy) {
      try {
        // Update policy via store (uses correct parameter names)
        await this.$store.dispatch('protection/updatePolicy', {
          policyType: this.policyType,
          id: this.policyId,
          policyData: updatedPolicy,
        });

        this.showEditModal = false;
        // Reload policy data
        await this.loadPolicy();
      } catch (error) {
        logger.error('Failed to update policy:', error);
      }
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        // Delete policy via API
        const endpoint = this.getApiEndpoint(this.policyType);
        await this.$store.dispatch('protection/deletePolicy', {
          endpoint,
          policyId: this.policyId,
        });

        this.showDeleteConfirm = false;
        // Navigate back to protection page
        this.$router.push('/protection');
      } catch (error) {
        logger.error('Failed to delete policy:', error);
      }
    },

    getApiEndpoint(policyType) {
      const endpoints = {
        life: '/api/protection/life-insurance',
        criticalIllness: '/api/protection/critical-illness',
        incomeProtection: '/api/protection/income-protection',
        disability: '/api/protection/disability',
        sicknessIllness: '/api/protection/sickness-illness',
      };
      return endpoints[policyType] || '/api/protection/life-insurance';
    },

    formatCoverageType(type) {
      if (!type) return 'N/A';
      return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    formatBenefitFrequency(frequency) {
      const map = {
        monthly: 'Monthly',
        weekly: 'Weekly',
        lump_sum: 'Lump Sum',
      };
      return map[frequency] || frequency;
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

<style scoped>
</style>
