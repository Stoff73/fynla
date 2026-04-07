<template>
  <div class="current-situation">
    <!-- No Protection Notice -->
    <div v-if="hasNoPolicies" class="bg-eggshell-500 rounded-lg p-6 mb-8">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-lg font-medium text-violet-800 mb-2">No Protection Coverage</h3>
          <p class="text-sm text-violet-700 mb-4">
            You currently have no protection policies recorded. Without adequate life insurance and protection coverage, your family may face financial difficulties if something unexpected happens.
          </p>
          <div class="bg-white rounded-lg p-4 border border-violet-300 mb-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-2">Why Protection is Important:</h4>
            <ul class="text-sm text-neutral-500 space-y-1 list-disc list-inside">
              <li>Replaces lost income if you're unable to work</li>
              <li>Covers outstanding debts and mortgages</li>
              <li>Provides financial security for dependents</li>
              <li>Protects your family's lifestyle and future plans</li>
            </ul>
          </div>
          <div>
            <button
              v-preview-disabled="'add'"
              @click="$emit('add-policy')"
              class="px-5 py-2.5 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors font-medium text-sm"
            >
              Add Protection
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Header with Add Button and Filters -->
    <div v-else class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h3 class="text-lg font-semibold text-horizon-500">{{ totalPolicyCount === 1 ? 'Policy' : 'Policies' }}</h3>
        </div>

        <div class="flex gap-3">
          <button
            v-preview-disabled="'add'"
            @click="$emit('add-policy')"
            class="px-4 py-2 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 transition-colors flex items-center gap-2"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4"
              />
            </svg>
            Add New Policy
          </button>
          <button
            v-preview-disabled="'upload'"
            @click="showUploadModal = true"
            class="inline-flex items-center px-4 py-2 border-2 border-violet-600 text-violet-600 bg-white rounded-lg hover:bg-violet-50 transition-colors font-medium"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Document
          </button>
        </div>
      </div>

    </div>

    <!-- Policy Cards Grid -->
    <div v-if="filteredPolicies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <PolicyCard
        v-for="policy in filteredPolicies"
        :key="`${policy.policy_type}-${policy.id}`"
        :policy="policy"
        @edit="handleEditPolicy"
      />
    </div>

    <!-- Gap Analysis Content -->
    <template v-if="!hasNoPolicies">
      <!-- Existing Coverage & Allocation -->
      <div class="mb-8" v-if="existingLifeCoverage > 0">
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Your Existing Life Insurance Coverage</h3>
          <p class="text-sm text-neutral-500 mb-6">
            Your life insurance is allocated to cover your debts first, then any excess reduces your income replacement need.
          </p>

          <div class="space-y-3">
            <!-- Total Life Cover -->
            <div class="flex justify-between items-center pb-3 border-b border-light-gray">
              <span class="font-semibold text-horizon-500">Total Life Insurance</span>
              <span class="text-xl font-bold text-spring-600">{{ formatCurrency(existingLifeCoverage) }}</span>
            </div>

            <!-- Allocation Breakdown -->
            <div class="space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-neutral-500">1. Allocated to cover debts</span>
                <span class="font-medium text-horizon-500">{{ formatCurrency(debtCoveredAmount) }}</span>
              </div>

              <div class="flex justify-between items-center">
                <span class="text-neutral-500">2. Excess for {{ spouseName || 'beneficiary' }}'s income</span>
                <div class="text-right">
                  <span class="font-medium text-horizon-500">{{ formatCurrency(humanCapitalCovered) }}</span>
                  <span class="text-xs text-neutral-500 block">{{ formatCurrency(humanCapitalCoveredAnnual) }} p.a.</span>
                </div>
              </div>

              <div v-if="excessUnused > 0" class="flex justify-between items-center text-violet-700">
                <span class="font-medium">3. Excess unused</span>
                <span class="font-medium">{{ formatCurrency(excessUnused) }}</span>
              </div>
            </div>

            <!-- Income Replacement Policies -->
            <div v-if="incomeReplacementCoverageAnnual > 0" class="mt-4 p-4 bg-eggshell-500 rounded-lg">
              <div class="flex justify-between items-center">
                <div>
                  <span class="font-semibold text-horizon-500">Income Replacement Policies</span>
                  <p class="text-xs text-neutral-500 mt-1">Family Income Benefit, Income Protection, etc.</p>
                </div>
                <span class="text-xl font-bold text-violet-600">{{ formatCurrency(incomeReplacementCoverageAnnual) }}/year</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Protection Shortfall -->
      <div class="mb-8">
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-2">Protection Shortfall</h3>
          <p class="text-sm text-neutral-500 mb-4">
            After accounting for your existing cover, these are the protection gaps that remain.
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Debt Protection Gap Card -->
            <div class="p-4 rounded-lg bg-eggshell-500">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Debt Protection</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(debtGapSeverity)"
                >
                  {{ debtGapSeverity }}
                </span>
              </div>

              <div class="mb-3 pb-3 border-b border-light-gray">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Current Life Cover (for debt)</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(debtCoveredAmount) }}</span>
                </div>
              </div>

              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Mortgage Debt</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(mortgageDebt) }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Other Liabilities</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(otherDebt) }}</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">Total Debt</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(totalDebt) }}</span>
                </div>
              </div>

              <div class="pt-3 border-t border-horizon-300">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-horizon-500">Shortfall</span>
                  <span class="text-lg font-bold" :class="debtProtectionGap > 0 ? 'text-raspberry-600' : 'text-spring-600'">
                    {{ formatCurrency(debtProtectionGap) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Income Replacement Gap Card -->
            <div class="p-4 rounded-lg bg-eggshell-500">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Income Replacement</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(incomeGapSeverity)"
                >
                  {{ incomeGapSeverity }}
                </span>
              </div>

              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(annualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">75% of Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(incomeReplacementNeed) }} p.a.</span>
                </div>
              </div>

              <div class="pt-3 border-t border-horizon-300">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-horizon-500">Shortfall</span>
                  <div class="text-right">
                    <span class="text-lg font-bold block" :class="incomeReplacementGap > 0 ? 'text-raspberry-600' : 'text-spring-600'">
                      {{ formatCurrency(incomeReplacementGap) }} p.a.
                    </span>
                    <span v-if="incomeReplacementGap > 0" class="text-xs text-neutral-500">{{ formatCurrency(incomeReplacementGap / 12) }}/month</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Critical Illness Card -->
            <div class="p-4 rounded-lg bg-eggshell-500">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Critical Illness</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(criticalIllnessGapSeverity)"
                >
                  {{ criticalIllnessGapSeverity }}
                </span>
              </div>

              <div class="mb-3 pb-3 border-b border-light-gray">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Current CI Cover (lump sum)</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(criticalIllnessCover) }}</span>
                </div>
              </div>

              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(annualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">2x Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(criticalIllnessNeed) }}</span>
                </div>
              </div>

              <div class="pt-3 border-t border-horizon-300">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-horizon-500">Shortfall</span>
                  <span class="text-lg font-bold" :class="criticalIllnessGap > 0 ? 'text-raspberry-600' : 'text-spring-600'">
                    {{ formatCurrency(criticalIllnessGap) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Sickness Cover Card -->
            <div class="p-4 rounded-lg bg-eggshell-500">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Sickness Cover</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(sicknessGapSeverity)"
                >
                  {{ sicknessGapSeverity }}
                </span>
              </div>

              <div class="mb-3 pb-3 border-b border-light-gray space-y-2">
                <div v-if="isEmployee" class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">
                    Statutory Sick Pay (SSP)
                    <span class="text-xs text-horizon-400 block">£{{ sspWeeklyRate }}/week for up to 28 weeks</span>
                  </span>
                  <span class="font-medium text-violet-600">{{ formatCurrency(sspAnnualEquivalent) }} p.a.</span>
                </div>
                <div v-if="!isEmployee" class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">
                    Statutory Sick Pay (SSP)
                    <span class="text-xs text-violet-500 block">Self-employed not eligible</span>
                  </span>
                  <span class="font-medium text-horizon-400">£0</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Private Sickness Policies</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(sicknessCover) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center text-sm pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">Total Sickness Cover</span>
                  <span class="font-semibold text-spring-600">{{ formatCurrency(totalSicknessCover) }} p.a.</span>
                </div>
              </div>

              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(annualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">50% of Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(sicknessNeed) }} p.a.</span>
                </div>
              </div>

              <div class="pt-3 border-t border-horizon-300">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-horizon-500">Shortfall</span>
                  <div class="text-right">
                    <span class="text-lg font-bold block" :class="sicknessGap > 0 ? 'text-raspberry-600' : 'text-spring-600'">
                      {{ formatCurrency(sicknessGap) }} p.a.
                    </span>
                    <span v-if="sicknessGap > 0" class="text-xs text-neutral-500">{{ formatCurrency(sicknessGap / 12) }}/month</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Disability Cover Card -->
            <div class="p-4 rounded-lg bg-eggshell-500">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Disability Cover</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(disabilityGapSeverity)"
                >
                  {{ disabilityGapSeverity }}
                </span>
              </div>

              <div class="mb-3 pb-3 border-b border-light-gray">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Current Disability Cover</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(disabilityCover) }} p.a.</span>
                </div>
              </div>

              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(annualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">50% of Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(disabilityNeed) }} p.a.</span>
                </div>
              </div>

              <div class="pt-3 border-t border-horizon-300">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-horizon-500">Shortfall</span>
                  <div class="text-right">
                    <span class="text-lg font-bold block" :class="disabilityGap > 0 ? 'text-raspberry-600' : 'text-spring-600'">
                      {{ formatCurrency(disabilityGap) }} p.a.
                    </span>
                    <span v-if="disabilityGap > 0" class="text-xs text-neutral-500">{{ formatCurrency(disabilityGap / 12) }}/month</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Affordability Assessment -->
      <div class="bg-white rounded-lg border border-light-gray p-6 mb-8">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Affordability Assessment</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <p class="text-sm text-neutral-500 mb-1">Monthly Income</p>
            <p class="text-xl font-bold text-horizon-500">
              {{ formatCurrency(monthlyNetIncome) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-neutral-500 mb-1">Current Premium Spend</p>
            <p class="text-xl font-bold text-horizon-500">
              {{ formatCurrency(totalPremium) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-neutral-500 mb-1">% of Income</p>
            <p
              class="text-xl font-bold"
              :class="premiumPercentageColour"
            >
              {{ premiumPercentage }}%
            </p>
            <p class="text-xs text-neutral-500 mt-1">
              Recommended: 5-10% of gross income
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- Coverage Summary -->
    <div v-if="!hasNoPolicies" class="bg-white rounded-lg border border-light-gray p-4 sm:p-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">Coverage Summary</h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-6">
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1" :class="debtCoverageColour">
            {{ debtCoveragePercent }}%
          </div>
          <div class="text-xs sm:text-sm text-neutral-500">Debt Coverage</div>
          <div class="text-xs text-horizon-400 hidden sm:block">{{ formatCurrency(debtCoverage) }} / {{ formatCurrency(totalDebt) }}</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1" :class="incomeProtectedColour">
            {{ incomeProtectedPercent }}%
          </div>
          <div class="text-xs sm:text-sm text-neutral-500">Income Protected</div>
          <div class="text-xs text-horizon-400 hidden sm:block">{{ formatCurrency(incomeProtected) }} / {{ formatCurrency(annualIncome) }} p.a.</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-pink-600 mb-1">
            {{ formatCurrency(criticalIllnessCover) }}
          </div>
          <div class="text-xs sm:text-sm text-neutral-500">Critical Illness</div>
          <div class="text-xs text-horizon-400 hidden sm:block">lump sum</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-purple-600 mb-1">
            {{ formatCurrency(sicknessCover) }}
          </div>
          <div class="text-xs sm:text-sm text-neutral-500">Sickness Cover</div>
          <div class="text-xs text-horizon-400 hidden sm:block">per year</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-violet-600 mb-1">
            {{ formatCurrency(disabilityCover) }}
          </div>
          <div class="text-xs sm:text-sm text-neutral-500">Disability Cover</div>
          <div class="text-xs text-horizon-400 hidden sm:block">per year</div>
        </div>
      </div>
    </div>

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="insurance_policy"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal(); $emit('add-policy');"
    />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import PolicyCard from './PolicyCard.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import userProfileService from '@/services/userProfileService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SSP_WEEKLY_RATE } from '@/constants/taxConfig';

export default {
  name: 'CurrentSituation',

  emits: ['add-policy', 'edit-policy', 'refresh-data'],

  mixins: [currencyMixin],

  components: {
    PolicyCard,
    DocumentUploadModal,
  },

  data() {
    return {
      showUploadModal: false,
      fetchedTotalDebt: 0,
      fetchedMortgageDebt: 0,
      fetchedOtherDebt: 0,
      fetchedNetAnnualIncome: 0,
      fetchedEmploymentIncome: 0,
      fetchedSelfEmploymentIncome: 0,
      spouseName: null,
    };
  },

  async mounted() {
    await this.fetchUserData();
  },

  computed: {
    ...mapState('protection', ['policies', 'profile', 'analysis']),
    ...mapGetters('protection', [
      'allPolicies',
      'totalPremium',
    ]),
    ...mapGetters('auth', ['currentUser']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    // Get user from auth store for fallback income data
    authUser() {
      return this.currentUser || {};
    },

    hasNoPolicies() {
      // Check if all policy types have zero policies
      const totalPolicies =
        (this.policies.life?.length || 0) +
        (this.policies.criticalIllness?.length || 0) +
        (this.policies.incomeProtection?.length || 0) +
        (this.policies.disability?.length || 0) +
        (this.policies.sicknessIllness?.length || 0);
      return totalPolicies === 0;
    },

    totalDebt() {
      // Use fetched liabilities from user profile (same as User Profile page shows)
      return this.fetchedTotalDebt || 0;
    },

    annualIncome() {
      // Gross annual income from coverage gap analysis, or fallback to auth user
      return this.analysis?.data?.needs?.gross_income ||
             this.analysis?.needs?.gross_income ||
             parseFloat(this.profile?.annual_income || 0) ||
             parseFloat(this.authUser?.annual_employment_income || 0) +
             parseFloat(this.authUser?.annual_self_employment_income || 0) ||
             0;
    },

    debtCoverage() {
      // Life insurance coverage for debt protection
      return this.policies.life?.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0) || 0;
    },

    debtCoveragePercent() {
      if (this.totalDebt === 0) return 0;
      return Math.round((this.debtCoverage / this.totalDebt) * 100);
    },

    debtCoverageColour() {
      if (this.totalDebt === 0) return 'text-spring-600';
      if (this.debtCoveragePercent >= 100) return 'text-spring-600';
      if (this.debtCoveragePercent >= 75) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    incomeProtected() {
      // Annual benefit from income protection policies
      return this.policies.incomeProtection?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    incomeProtectedPercent() {
      if (this.annualIncome === 0) return 0;
      return Math.round((this.incomeProtected / this.annualIncome) * 100);
    },

    incomeProtectedColour() {
      if (this.annualIncome === 0) return 'text-neutral-500';
      // Target is typically 50-70% of income
      if (this.incomeProtectedPercent >= 50) return 'text-spring-600';
      if (this.incomeProtectedPercent >= 25) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    criticalIllnessCover() {
      // Lump sum from critical illness policies
      return this.policies.criticalIllness?.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0) || 0;
    },

    sicknessCover() {
      // Annual benefit from sickness/illness policies
      return this.policies.sicknessIllness?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    disabilityCover() {
      // Annual benefit from disability policies
      return this.policies.disability?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    totalPolicyCount() {
      return this.allPolicies?.length || 0;
    },

    filteredPolicies() {
      const policies = [...(this.allPolicies || [])];
      // Sort by coverage (high to low)
      policies.sort((a, b) => {
        const aValue = a.sum_assured || a.benefit_amount || 0;
        const bValue = b.sum_assured || b.benefit_amount || 0;
        return bValue - aValue;
      });
      return policies;
    },

    // Gap Analysis computed properties
    mortgageDebt() {
      return this.fetchedMortgageDebt;
    },

    otherDebt() {
      return this.fetchedOtherDebt;
    },

    existingLifeCoverage() {
      return this.debtCoverage;
    },

    debtCoveredAmount() {
      return Math.min(this.debtCoverage, this.totalDebt);
    },

    humanCapitalCovered() {
      return Math.max(0, this.debtCoverage - this.totalDebt);
    },

    humanCapitalCoveredAnnual() {
      return this.humanCapitalCovered * 0.047;
    },

    excessUnused() {
      const afterDebt = this.debtCoverage - this.totalDebt;
      if (afterDebt <= 0) return 0;
      const lumpSumNeededForIncome = this.incomeReplacementNeed / 0.047;
      return Math.max(0, afterDebt - lumpSumNeededForIncome);
    },

    incomeReplacementCoverageAnnual() {
      return this.incomeProtected;
    },

    debtProtectionGap() {
      return Math.max(0, this.totalDebt - this.debtCoverage);
    },

    incomeReplacementNeed() {
      return this.annualIncome * 0.75;
    },

    incomeReplacementGap() {
      return Math.max(0, this.incomeReplacementNeed - this.humanCapitalCoveredAnnual);
    },

    criticalIllnessNeed() {
      return this.annualIncome * 2;
    },

    criticalIllnessGap() {
      return Math.max(0, this.criticalIllnessNeed - this.criticalIllnessCover);
    },

    isEmployee() {
      return this.fetchedEmploymentIncome > 0;
    },

    sspWeeklyRate() {
      return SSP_WEEKLY_RATE;
    },

    sspAnnualEquivalent() {
      if (!this.isEmployee) return 0;
      return this.sspWeeklyRate * 52;
    },

    totalSicknessCover() {
      return this.sspAnnualEquivalent + this.sicknessCover;
    },

    sicknessNeed() {
      return this.annualIncome * 0.5;
    },

    sicknessGap() {
      return Math.max(0, this.sicknessNeed - this.totalSicknessCover);
    },

    disabilityNeed() {
      return this.annualIncome * 0.5;
    },

    disabilityGap() {
      return Math.max(0, this.disabilityNeed - this.disabilityCover);
    },

    // Severity calculations
    debtGapSeverity() {
      return this.calculateSeverity(this.debtProtectionGap);
    },

    incomeGapSeverity() {
      return this.calculateSeverity(this.incomeReplacementGap);
    },

    criticalIllnessGapSeverity() {
      return this.calculateSeverity(this.criticalIllnessGap);
    },

    sicknessGapSeverity() {
      return this.calculateSeverity(this.sicknessGap);
    },

    disabilityGapSeverity() {
      return this.calculateSeverity(this.disabilityGap);
    },

    // Affordability
    monthlyNetIncome() {
      return this.fetchedNetAnnualIncome / 12;
    },

    premiumPercentage() {
      if (this.monthlyNetIncome === 0) return 0;
      return ((this.totalPremium / this.monthlyNetIncome) * 100).toFixed(1);
    },

    premiumPercentageColour() {
      const percentage = parseFloat(this.premiumPercentage);
      if (percentage <= 10) return 'text-spring-600';
      if (percentage <= 15) return 'text-violet-600';
      return 'text-raspberry-600';
    },
  },

  methods: {
    async fetchUserData() {
      try {
        const response = await userProfileService.getProfile();
        const data = response.data || response;

        // Get liabilities breakdown
        const liabilities = data.liabilities_summary || {};
        this.fetchedMortgageDebt = liabilities.mortgages?.total || 0;
        this.fetchedOtherDebt = liabilities.other?.total || 0;
        this.fetchedTotalDebt = liabilities.total || 0;

        // Get annual income from income_occupation
        const income = data.income_occupation || {};
        this.fetchedEmploymentIncome = parseFloat(income.annual_employment_income || 0);
        this.fetchedSelfEmploymentIncome = parseFloat(income.annual_self_employment_income || 0);

        // Get net (after-tax) income for affordability assessment
        this.fetchedNetAnnualIncome = parseFloat(income.net_income || 0);

        // Get spouse name for beneficiary display
        this.spouseName = data.spouse?.name || null;
      } catch (error) {
        console.warn('Failed to fetch user data:', error);
      }
    },

    calculateSeverity(amount) {
      if (amount === 0) return 'none';
      if (amount < 50000) return 'low';
      if (amount < 150000) return 'medium';
      return 'high';
    },

    getSeverityBadgeClass(severity) {
      const classes = {
        none: 'bg-spring-500 text-white',
        low: 'bg-violet-500 text-white',
        medium: 'bg-violet-500 text-white',
        high: 'bg-raspberry-500 text-white',
      };
      return classes[severity] || 'bg-eggshell-500 text-white';
    },

    handleEditPolicy(policy) {
      this.$emit('edit-policy', policy);
    },

    closeUploadModal() {
      this.showUploadModal = false;
    },

    handleDocumentSaved(savedData) {
      this.showUploadModal = false;
      // Emit event to parent to refresh data
      this.$emit('refresh-data');
    },
  },
};
</script>

<style scoped>
/* Responsive adjustments */
@media (max-width: 640px) {
  .current-situation .grid {
    gap: 1rem;
  }
}
</style>
