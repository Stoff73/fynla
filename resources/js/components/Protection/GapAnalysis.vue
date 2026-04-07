<template>
  <div class="gap-analysis">
    <!-- No Policies Alert Banner -->
    <div v-if="hasNoPolicies" class="mb-6 bg-eggshell-500 rounded-lg p-4">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-violet-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-violet-800">No Protection Policies Added</h3>
          <div class="mt-2 text-sm text-violet-700">
            <p class="mb-2">
              You haven't added any protection policies yet. The analysis below shows your protection needs based on your current situation.
            </p>
            <button
              @click="$emit('add-policy')"
              class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Your First Policy
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Spouse Income Not Included Warning -->
    <div v-if="spousePermissionDenied" class="mb-6 bg-eggshell-500 rounded-lg p-4">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-violet-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-violet-800">Spouse Income Not Included</h3>
          <div class="mt-2 text-sm text-violet-700">
            <p>
              Your spouse's income has not been included in this protection analysis because data sharing permissions have not been granted.
              To get a more accurate household protection assessment, please enable data sharing with your spouse in User Profile settings.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Content (always show) -->
    <div>
      <!-- Existing Coverage & Allocation -->
      <div class="mb-8" v-if="existingLifeCoverage > 0">
        <div class="bg-white rounded-lg border border-light-gray p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Your Existing Life Insurance Coverage</h3>
          <p class="text-sm text-neutral-500 mb-6">
            Your life insurance is allocated to cover your debts first, then any excess reduces your income replacement need.
          </p>

          <div class="space-y-4">
            <!-- Total Life Cover -->
            <div class="p-4 bg-eggshell-500 rounded-lg">
              <div class="flex justify-between items-center">
                <span class="font-semibold text-horizon-500">Total Life Insurance</span>
                <span class="text-2xl font-bold text-spring-600">{{ formatCurrency(existingLifeCoverage) }}</span>
              </div>
            </div>

            <!-- Allocation Breakdown -->
            <div class="pl-4 space-y-3">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="text-neutral-500">1. Allocated to cover debts</span>
                  <div class="ml-2 group relative">
                    <svg class="w-4 h-4 text-horizon-400 cursor-help" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    <div class="hidden group-hover:block absolute z-10 w-64 p-2 mt-2 text-xs text-white bg-horizon-600 rounded-lg shadow-lg -left-32">
                      Life insurance covers debts first (priority 1)
                    </div>
                  </div>
                </div>
                <span class="font-medium text-horizon-500">{{ formatCurrency(debtCovered) }}</span>
              </div>

              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="text-neutral-500">2. Excess for {{ spouseName || 'beneficiary' }}'s income</span>
                  <div class="ml-2 group relative">
                    <svg class="w-4 h-4 text-horizon-400 cursor-help" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    <div class="hidden group-hover:block absolute z-10 w-64 p-2 mt-2 text-xs text-white bg-horizon-600 rounded-lg shadow-lg -left-32">
                      After covering debts, remaining life cover provides income for the surviving beneficiary at a 4.7% sustainable draw rate
                    </div>
                  </div>
                </div>
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
            <div v-if="incomeReplacementCoverage > 0" class="mt-4 p-4 bg-eggshell-500 rounded-lg">
              <div class="flex justify-between items-center">
                <div>
                  <span class="font-semibold text-horizon-500">Income Replacement Policies</span>
                  <p class="text-xs text-neutral-500 mt-1">Family Income Benefit, Income Protection, etc.</p>
                </div>
                <span class="text-xl font-bold text-violet-600">{{ formatCurrency(incomeReplacementCoverage) }}/year</span>
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
            <!-- Debt Protection Gap Card (First) -->
            <div class="p-4 rounded-lg" :class="getGapCardClass(debtGapSeverity)">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Debt Protection</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(debtGapSeverity)"
                >
                  {{ debtGapSeverity }}
                </span>
              </div>

              <!-- Current Cover -->
              <div class="mb-3 pb-3 border-b border-light-gray">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Current Life Cover (for debt)</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(debtCovered) }}</span>
                </div>
              </div>

              <!-- Debt Breakdown -->
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

              <!-- Gap -->
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
            <div class="p-4 rounded-lg" :class="getGapCardClass(incomeGapSeverity)">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Income Replacement</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(incomeGapSeverity)"
                >
                  {{ incomeGapSeverity }}
                </span>
              </div>

              <!-- Need -->
              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(fetchedAnnualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">75% of Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(incomeReplacementNeed) }} p.a.</span>
                </div>
              </div>

              <!-- Gap -->
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
            <div class="p-4 rounded-lg" :class="getGapCardClass(criticalIllnessGapSeverity)">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Critical Illness</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(criticalIllnessGapSeverity)"
                >
                  {{ criticalIllnessGapSeverity }}
                </span>
              </div>

              <!-- Current Cover -->
              <div class="mb-3 pb-3 border-b border-light-gray">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Current CI Cover (lump sum)</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(criticalIllnessCover) }}</span>
                </div>
              </div>

              <!-- Need -->
              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(fetchedAnnualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">2x Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(criticalIllnessNeed) }}</span>
                </div>
              </div>

              <!-- Gap -->
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
            <div class="p-4 rounded-lg" :class="getGapCardClass(sicknessGapSeverity)">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Sickness Cover</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(sicknessGapSeverity)"
                >
                  {{ sicknessGapSeverity }}
                </span>
              </div>

              <!-- Current Cover Breakdown -->
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
                  <span class="font-medium text-spring-600">{{ formatCurrency(privateSicknessCover) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center text-sm pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">Total Sickness Cover</span>
                  <span class="font-semibold text-spring-600">{{ formatCurrency(totalSicknessCover) }} p.a.</span>
                </div>
              </div>

              <!-- Need -->
              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(fetchedAnnualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">50% of Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(sicknessNeed) }} p.a.</span>
                </div>
              </div>

              <!-- Gap -->
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
            <div class="p-4 rounded-lg" :class="getGapCardClass(disabilityGapSeverity)">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-horizon-500">Disability Cover</h4>
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getSeverityBadgeClass(disabilityGapSeverity)"
                >
                  {{ disabilityGapSeverity }}
                </span>
              </div>

              <!-- Current Cover -->
              <div class="mb-3 pb-3 border-b border-light-gray">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-neutral-500">Current Disability Cover</span>
                  <span class="font-medium text-spring-600">{{ formatCurrency(disabilityCover) }} p.a.</span>
                </div>
              </div>

              <!-- Need -->
              <div class="space-y-2 text-sm mb-3">
                <div class="flex justify-between items-center">
                  <span class="text-neutral-500">Annual Income</span>
                  <span class="font-medium text-horizon-500">{{ formatCurrency(fetchedAnnualIncome) }} p.a.</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-light-gray">
                  <span class="font-semibold text-horizon-500">50% of Income (Target)</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(disabilityNeed) }} p.a.</span>
                </div>
              </div>

              <!-- Gap -->
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
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Affordability Assessment</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <p class="text-sm text-neutral-500 mb-1">Monthly Income</p>
          <p class="text-xl font-bold text-horizon-500">
            {{ formatCurrency(monthlyIncome) }}
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
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import userProfileService from '@/services/userProfileService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SSP_WEEKLY_RATE } from '@/constants/taxConfig';

export default {
  name: 'GapAnalysis',

  emits: ['add-policy'],

  mixins: [currencyMixin],

  data() {
    return {
      fetchedMortgageDebt: 0,
      fetchedOtherDebt: 0,
      fetchedAnnualIncome: 0,
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
    ...mapState('protection', ['profile', 'analysis', 'policies']),
    ...mapGetters('protection', ['coverageGaps', 'totalPremium']),

    hasNoPolicies() {
      if (!this.policies) return true;

      const allPolicies = [
        ...(this.policies.life || []),
        ...(this.policies.criticalIllness || []),
        ...(this.policies.incomeProtection || []),
        ...(this.policies.disability || []),
        ...(this.policies.sicknessIllness || []),
      ];

      return allPolicies.length === 0;
    },

    // Debt values from fetched user profile
    mortgageDebt() {
      return this.fetchedMortgageDebt;
    },

    otherDebt() {
      return this.fetchedOtherDebt;
    },

    totalDebt() {
      return this.mortgageDebt + this.otherDebt;
    },

    // Life insurance coverage
    totalLifeCoverage() {
      return this.policies.life?.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0) || 0;
    },

    // Debt coverage from life insurance (capped at total debt)
    debtCovered() {
      return Math.min(this.totalLifeCoverage, this.totalDebt);
    },

    // Excess life cover after debt (lump sum for display)
    humanCapitalCovered() {
      return Math.max(0, this.totalLifeCoverage - this.totalDebt);
    },

    // Convert lump sum to sustainable annual income at 4.7% draw rate
    humanCapitalCoveredAnnual() {
      return this.humanCapitalCovered * 0.047;
    },

    // Debt protection gap
    debtProtectionGap() {
      return Math.max(0, this.totalDebt - this.totalLifeCoverage);
    },

    // Income replacement need (75% of annual income)
    incomeReplacementNeed() {
      return this.fetchedAnnualIncome * 0.75;
    },

    // Income replacement gap (compare annual income to sustainable annual income from lump sum)
    incomeReplacementGap() {
      return Math.max(0, this.incomeReplacementNeed - this.humanCapitalCoveredAnnual);
    },

    // Severity calculations
    debtGapSeverity() {
      return this.calculateSeverity(this.debtProtectionGap);
    },

    incomeGapSeverity() {
      return this.calculateSeverity(this.incomeReplacementGap);
    },

    monthlyIncome() {
      // Use net (after-tax) income for affordability assessment
      return this.fetchedNetAnnualIncome / 12;
    },

    premiumPercentage() {
      if (this.monthlyIncome === 0) return 0;
      return ((this.totalPremium / this.monthlyIncome) * 100).toFixed(1);
    },

    premiumPercentageColour() {
      const percentage = parseFloat(this.premiumPercentage);
      if (percentage <= 10) return 'text-spring-600';
      if (percentage <= 15) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    // Spouse permission tracking (for warning banner)
    spousePermissionDenied() {
      return this.analysis?.data?.needs?.spouse_permission_denied ||
             this.analysis?.needs?.spouse_permission_denied || false;
    },

    // Existing coverage values
    existingLifeCoverage() {
      return this.totalLifeCoverage;
    },

    incomeReplacementCoverage() {
      return this.analysis?.data?.gaps?.income_replacement_coverage ||
             this.analysis?.gaps?.income_replacement_coverage || 0;
    },

    // Excess unused life cover (after debt and income replacement needs)
    excessUnused() {
      const afterDebt = this.totalLifeCoverage - this.totalDebt;
      if (afterDebt <= 0) return 0;
      // Convert annual income need to lump sum at 4.7% draw rate
      const lumpSumNeededForIncome = this.incomeReplacementNeed / 0.047;
      return Math.max(0, afterDebt - lumpSumNeededForIncome);
    },

    // Critical Illness Cover
    criticalIllnessCover() {
      return this.policies.criticalIllness?.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0) || 0;
    },

    criticalIllnessNeed() {
      // Target: 2x annual income
      return this.fetchedAnnualIncome * 2;
    },

    criticalIllnessGap() {
      return Math.max(0, this.criticalIllnessNeed - this.criticalIllnessCover);
    },

    criticalIllnessGapSeverity() {
      return this.calculateSeverity(this.criticalIllnessGap);
    },

    // Sickness Cover (including SSP)
    isEmployee() {
      // User is an employee if they have employment income
      return this.fetchedEmploymentIncome > 0;
    },

    sspWeeklyRate() {
      return SSP_WEEKLY_RATE;
    },

    sspAnnualEquivalent() {
      // SSP is paid for up to 28 weeks, but for comparison we show annual equivalent
      // £118.75 × 52 weeks = £6,175 p.a.
      if (!this.isEmployee) return 0;
      return this.sspWeeklyRate * 52;
    },

    privateSicknessCover() {
      // Annual benefit from private sickness/illness policies only
      return this.policies.sicknessIllness?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    totalSicknessCover() {
      // SSP + private policies
      return this.sspAnnualEquivalent + this.privateSicknessCover;
    },

    // Keep sicknessCover as alias for total (used elsewhere)
    sicknessCover() {
      return this.totalSicknessCover;
    },

    sicknessNeed() {
      // Target: 50% of annual income
      return this.fetchedAnnualIncome * 0.5;
    },

    sicknessGap() {
      return Math.max(0, this.sicknessNeed - this.totalSicknessCover);
    },

    sicknessGapSeverity() {
      return this.calculateSeverity(this.sicknessGap);
    },

    // Disability Cover
    disabilityCover() {
      return this.policies.disability?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    disabilityNeed() {
      // Target: 50% of annual income
      return this.fetchedAnnualIncome * 0.5;
    },

    disabilityGap() {
      return Math.max(0, this.disabilityNeed - this.disabilityCover);
    },

    disabilityGapSeverity() {
      return this.calculateSeverity(this.disabilityGap);
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

        // Get annual income from income_occupation
        const income = data.income_occupation || {};
        this.fetchedEmploymentIncome = parseFloat(income.annual_employment_income || 0);
        this.fetchedSelfEmploymentIncome = parseFloat(income.annual_self_employment_income || 0);
        this.fetchedAnnualIncome = this.fetchedEmploymentIncome + this.fetchedSelfEmploymentIncome;

        // Get net (after-tax) income for affordability assessment
        this.fetchedNetAnnualIncome = parseFloat(income.net_income || 0);

        // Get spouse name for beneficiary display
        this.spouseName = data.spouse?.name || null;
      } catch (error) {
        console.warn('Failed to fetch user data for gap analysis:', error);
      }
    },
    calculateSeverity(amount) {
      if (amount === 0) return 'none';
      if (amount < 50000) return 'low';
      if (amount < 150000) return 'medium';
      return 'high';
    },

    getGapCardClass(severity) {
      const classes = {
        none: 'bg-eggshell-500',
        low: 'bg-eggshell-500',
        medium: 'bg-eggshell-500',
        high: 'bg-eggshell-500',
      };
      return classes[severity] || 'bg-eggshell-500';
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
  },
};
</script>

<style scoped>
/* Responsive adjustments */
@media (max-width: 640px) {
  .gap-analysis .grid {
    gap: 1rem;
  }
}
</style>
