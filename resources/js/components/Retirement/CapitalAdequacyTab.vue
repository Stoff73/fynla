<template>
  <div class="capital-adequacy-tab">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back mb-4"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="w-12 h-12 border-4 border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Loading capital adequacy data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="error-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p>{{ error }}</p>
      <button class="retry-button" @click="loadData">Try Again</button>
    </div>

    <!-- No Data State -->
    <div v-else-if="!hasPensions" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
      </svg>
      <p>No Defined Contribution pensions found</p>
      <p class="empty-subtitle">Add Defined Contribution pensions to track your capital adequacy and annual allowance</p>
    </div>

    <!-- Main Content -->
    <template v-else>
      <!-- Header Card with summary metrics inside (matches pension detail pattern) -->
      <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div>
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">Capital Planner</h1>
          <p class="text-base sm:text-lg text-neutral-500 mt-1">Track your pension contributions and capital progress towards retirement at age {{ retirementAge }}</p>
        </div>

        <!-- Summary Metrics Grid (inside card) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-light-gray">
          <!-- Required Capital -->
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Required Capital</p>
            <p class="text-lg font-bold text-horizon-500 mt-1">{{ formatCurrency(requiredCapitalAtRetirement) }}</p>
            <p class="text-xs text-neutral-400">Based on {{ formatCurrency(targetIncome) }}/year target</p>
          </div>

          <!-- Projected Capital -->
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Projected Capital</p>
            <p class="text-lg font-bold mt-1" :class="projectedCapitalAtRetirement >= requiredCapitalAtRetirement ? 'text-spring-600' : 'text-raspberry-500'">{{ formatCurrency(projectedCapitalAtRetirement) }}</p>
            <p class="text-xs text-neutral-400">80% confidence (Monte Carlo)</p>
          </div>

          <!-- Annual Allowance Used -->
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Allowance Used</p>
            <p class="text-lg font-bold text-horizon-500 mt-1">{{ formatCurrency(allowanceUsedThisYear) }}</p>
            <p class="text-xs text-neutral-400">of {{ formatCurrency(standardAllowance) }} ({{ currentTaxYear }})</p>
          </div>

          <!-- Carry Forward -->
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Carry Forward</p>
            <p class="text-lg font-bold text-horizon-500 mt-1">{{ formatCurrency(carryForwardAvailable) }}</p>
            <div class="mt-1 space-y-0.5">
              <div v-for="year in carryForwardByYear" :key="year.taxYear" class="flex justify-between text-xs">
                <span class="text-neutral-400">{{ year.taxYear }}</span>
                <span class="text-neutral-500">{{ formatCurrency(year.amount) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Annual Allowance Progress Section -->
      <div class="allowance-section">
        <div class="section-header">
          <h4 class="section-heading">Annual Allowance Progress</h4>
          <span class="tax-year-badge">Tax Year {{ currentTaxYear }}</span>
        </div>

        <div class="allowance-progress">
          <div class="progress-labels">
            <span class="progress-used">Used: {{ formatCurrency(allowanceUsedThisYear) }}</span>
            <span class="progress-remaining">Remaining: {{ formatCurrency(remainingAllowance) }} <span class="allowance-hint">(of {{ formatCurrency(standardAllowance) }})</span></span>
          </div>
          <div class="progress-bar-container">
            <div
              class="progress-bar"
              :class="allowanceProgressClass"
              :style="{ width: allowanceProgressPercent + '%' }"
            ></div>
          </div>
          <div class="progress-breakdown">
            <div class="breakdown-item">
              <span class="breakdown-label">Remaining Allowance</span>
              <span class="breakdown-value">{{ formatCurrency(remainingAllowance) }}</span>
            </div>
            <div v-if="carryForwardAvailable > 0" class="breakdown-item">
              <span class="breakdown-label">+ Carry Forward</span>
              <span class="breakdown-value">{{ formatCurrency(carryForwardAvailable) }}</span>
            </div>
            <div class="breakdown-item total">
              <span class="breakdown-label">Total Available</span>
              <span class="breakdown-value">{{ formatCurrency(totalRemainingAllowance) }}</span>
            </div>
          </div>

          <!-- Monthly Equivalent -->
          <div class="monthly-equivalent">
            <div class="breakdown-item">
              <span class="breakdown-label">Monthly Equivalent</span>
              <span class="breakdown-value">{{ formatCurrency(totalRemainingAllowance / 12) }}/month</span>
            </div>
            <div v-if="sliderConstraint === 'affordability'" class="affordability-note">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
              </svg>
              <span>Affordability limits contributions to {{ formatCurrency(monthlyDisposable) }}/month</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Contribution Slider Section -->
      <div class="slider-section">
        <div class="section-header">
          <h4 class="section-heading">What-If: Increase Contributions</h4>
          <p class="section-description">See how additional contributions could impact your retirement capital</p>
        </div>

        <div class="slider-content">
          <div class="slider-row">
            <div class="slider-labels">
              <span class="current-contribution">
                Current: {{ formatCurrency(currentMonthlyContributions) }}/month
              </span>
              <span class="additional-contribution">
                Additional: {{ formatCurrency(additionalMonthly) }}/month
              </span>
            </div>

            <div class="slider-container">
              <input
                type="range"
                v-model.number="additionalMonthly"
                :min="0"
                :max="maxAdditionalMonthly"
                step="25"
                class="contribution-slider"
              />
              <div class="slider-ticks">
                <span>£0</span>
                <span>{{ formatCurrency(maxAdditionalMonthly / 2) }}</span>
                <span>{{ formatCurrency(maxAdditionalMonthly) }}</span>
              </div>
              <p class="slider-limit-note">
                <template v-if="sliderConstraint === 'affordability'">
                  Limited by affordability ({{ formatCurrency(monthlyDisposable) }}/month disposable income)
                </template>
                <template v-else>
                  Limited by remaining annual allowance ({{ formatCurrency(totalRemainingAllowance) }}/year)
                </template>
              </p>
            </div>

            <!-- Constraint Warnings -->
            <div v-if="additionalMonthly > 0" class="constraint-warnings">
              <div v-if="wouldExceedAllowance" class="warning-banner">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <span>This would exceed your annual allowance</span>
              </div>
              <div v-if="wouldExceedAffordability" class="warning-banner affordability">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
                <span>Based on your budget, max affordable is {{ formatCurrency(monthlyDisposable) }}/month</span>
              </div>
            </div>
          </div>

          <!-- Impact Panel -->
          <div class="impact-panel">
            <h5 class="impact-title">Projected Impact</h5>
            <div class="impact-grid">
              <div class="impact-item">
                <span class="impact-label">New Annual Contribution</span>
                <span class="impact-value">{{ formatCurrency(newAnnualContribution) }}</span>
              </div>
              <div class="impact-item">
                <span class="impact-label">Additional Capital at Retirement</span>
                <span class="impact-value highlight">+{{ formatCurrency(additionalCapitalAtRetirement) }}</span>
              </div>
              <div class="impact-item">
                <span class="impact-label">New Projected Capital</span>
                <span :class="['impact-value', newProjectedCapitalClass]">{{ formatCurrency(newProjectedCapital) }}</span>
              </div>
              <div class="impact-item">
                <span class="impact-label">Capital Gap/Surplus</span>
                <span :class="['impact-value', newCapitalGapClass]">
                  {{ newCapitalGap >= 0 ? '+' : '' }}{{ formatCurrency(newCapitalGap) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <p class="slider-note">
          This is a what-if visualisation only. To change your actual contributions, update individual pension records.
        </p>
      </div>

      <!-- Capital Progress Section -->
      <div class="progress-section">
        <div class="section-header">
          <h4 class="section-heading">Capital Progress</h4>
        </div>

        <div class="capital-progress">
          <div class="progress-label-row">
            <span class="progress-type">Progress to Required Capital</span>
            <span :class="['progress-percentage', capitalProgressClass]">{{ capitalProgressPercent }}%</span>
          </div>
          <div class="progress-bar-container large">
            <div
              class="progress-bar"
              :class="capitalProgressClass"
              :style="{ width: Math.min(capitalProgressPercent, 100) + '%' }"
            ></div>
            <div
              v-if="capitalProgressPercent > 100"
              class="progress-bar surplus"
              :style="{ width: Math.min(capitalProgressPercent - 100, 100) + '%', left: '100%' }"
            ></div>
          </div>
          <div class="progress-values">
            <span>Projected: {{ formatCurrency(projectedCapitalAtRetirement) }}</span>
            <span class="target-label">Required: {{ formatCurrency(requiredCapitalAtRetirement) }}</span>
          </div>

          <div :class="['status-banner', capitalStatusClass]">
            <svg v-if="capitalProgressPercent >= 100" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div class="status-text">
              <h5>{{ capitalStatusTitle }}</h5>
              <p>{{ capitalStatusMessage }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

import logger from '@/utils/logger';
export default {
  name: 'CapitalAdequacyTab',

  mixins: [currencyMixin],

  emits: ['back'],

  data() {
    return {
      additionalMonthly: 0,
      dataLoaded: false,
    };
  },

  computed: {
    ...mapState('retirement', [
      'dcPensions',
      'profile',
      'projections',
      'projectionsLoading',
      'requiredCapital',
      'requiredCapitalLoading',
      'strategies',
      'strategiesLoading',
      'annualAllowance',
      'error',
    ]),

    loading() {
      return this.projectionsLoading || this.requiredCapitalLoading || this.strategiesLoading;
    },

    hasPensions() {
      return this.dcPensions && this.dcPensions.length > 0;
    },

    retirementAge() {
      return this.profile?.target_retirement_age || 68;
    },

    yearsToRetirement() {
      return this.requiredCapital?.retirement_info?.years_to_retirement ||
             this.projections?.pension_pot_projection?.years_to_retirement || 0;
    },

    // Target Income from centralised source
    targetIncome() {
      return this.requiredCapital?.required_income || this.profile?.target_retirement_income || 35000;
    },

    // Capital values
    requiredCapitalAtRetirement() {
      return this.requiredCapital?.required_capital_at_retirement || 0;
    },

    projectedCapitalAtRetirement() {
      return this.projections?.pension_pot_projection?.percentile_20_at_retirement || 0;
    },

    projectedCapitalClass() {
      if (this.projectedCapitalAtRetirement >= this.requiredCapitalAtRetirement) {
        return 'green';
      }
      return 'red';
    },

    // Annual Allowance calculations
    standardAllowance() {
      return ANNUAL_ALLOWANCE;
    },

    currentTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth(); // 0-indexed
      const day = now.getDate();
      // Tax year starts April 6
      if (month > 3 || (month === 3 && day >= 6)) {
        return `${year}/${(year + 1).toString().slice(-2)}`;
      }
      return `${year - 1}/${year.toString().slice(-2)}`;
    },

    allowanceUsedThisYear() {
      // Use API data if available, otherwise calculate from DC pensions
      if (this.annualAllowance?.total_contributions !== undefined) {
        return this.annualAllowance.total_contributions;
      }
      // Fallback: calculate from current contributions
      return this.currentAnnualContributions;
    },

    currentAnnualContributions() {
      // Sum of all DC pension contributions (employee + employer) annualised
      // Includes both percentage-based (occupational) and flat monthly contributions
      return this.dcPensions.reduce((sum, p) => {
        // Percentage-based contributions (occupational pensions)
        const salary = parseFloat(p.annual_salary || 0);
        const employeePercent = parseFloat(p.employee_contribution_percent || 0);
        const employerPercent = parseFloat(p.employer_contribution_percent || 0);
        const percentBasedAnnual = salary * (employeePercent + employerPercent) / 100;

        // Flat monthly contributions (personal pensions, SIPPs)
        const monthlyFlat = parseFloat(p.monthly_contribution_amount || 0);
        const flatAnnual = monthlyFlat * 12;

        return sum + percentBasedAnnual + flatAnnual;
      }, 0);
    },

    currentMonthlyContributions() {
      return this.currentAnnualContributions / 12;
    },

    excessMonthlyAvailable() {
      // How much more could be contributed per month within allowance
      const remainingAnnualAllowance = this.totalAllowanceAvailable - this.allowanceUsedThisYear;
      return Math.max(0, remainingAnnualAllowance / 12);
    },

    carryForwardAvailable() {
      // Always sum from year breakdown to ensure consistency
      return this.carryForwardByYear.reduce((sum, y) => sum + y.amount, 0);
    },

    carryForwardByYear() {
      // Use API data if available with year breakdown
      if (this.annualAllowance?.carry_forward_by_year) {
        return this.annualAllowance.carry_forward_by_year;
      }

      // Calculate the previous 3 tax years
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const day = now.getDate();
      const currentTaxYearStart = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;

      // Assume same contributions as current year for previous years
      // (since we don't have historical contribution data)
      const assumedContributions = this.currentAnnualContributions;
      const unusedPerYear = Math.max(0, this.standardAllowance - assumedContributions);

      // Generate last 3 tax years
      const years = [];
      for (let i = 1; i <= 3; i++) {
        const taxYearStart = currentTaxYearStart - i;
        const taxYear = `${taxYearStart}/${(taxYearStart + 1).toString().slice(-2)}`;

        years.push({
          taxYear,
          amount: unusedPerYear,
        });
      }

      return years;
    },

    totalAllowanceAvailable() {
      return this.standardAllowance + this.carryForwardAvailable;
    },

    remainingAllowance() {
      return Math.max(0, this.standardAllowance - this.allowanceUsedThisYear);
    },

    totalRemainingAllowance() {
      // Remaining from this year's allowance + carry forward from previous years
      return this.remainingAllowance + this.carryForwardAvailable;
    },

    allowanceProgressPercent() {
      if (this.standardAllowance === 0) return 0;
      return Math.min(100, (this.allowanceUsedThisYear / this.standardAllowance) * 100);
    },

    allowanceProgressClass() {
      const pct = this.allowanceProgressPercent;
      if (pct >= 100) return 'red';
      if (pct >= 80) return 'blue';
      return 'green';
    },

    // Affordability from strategies
    monthlyDisposable() {
      return this.strategies?.affordability?.monthly_disposable || 500;
    },

    // Slider constraints - use MINIMUM of affordability or allowance as the hard limit
    maxAdditionalMonthly() {
      const allowanceLimit = this.totalRemainingAllowance / 12;
      const affordabilityLimit = this.monthlyDisposable;
      // Slider max is the lower of the two constraints
      return Math.max(0, Math.min(affordabilityLimit, allowanceLimit));
    },

    // For display - show which constraint is the limiting factor
    sliderConstraint() {
      const allowanceLimit = this.totalRemainingAllowance / 12;
      const affordabilityLimit = this.monthlyDisposable;
      if (affordabilityLimit <= allowanceLimit) {
        return 'affordability';
      }
      return 'allowance';
    },

    wouldExceedAllowance() {
      const newAnnual = this.currentAnnualContributions + (this.additionalMonthly * 12);
      return newAnnual > this.totalAllowanceAvailable;
    },

    wouldExceedAffordability() {
      return this.additionalMonthly > this.monthlyDisposable;
    },

    // Contribution impact calculations
    newAnnualContribution() {
      return this.currentAnnualContributions + (this.additionalMonthly * 12);
    },

    additionalCapitalAtRetirement() {
      if (this.additionalMonthly === 0 || this.yearsToRetirement <= 0) return 0;

      // Future value of additional contributions using compound growth
      // Same formula as backend RequiredCapitalCalculator
      const additionalAnnual = this.additionalMonthly * 12;
      const grossReturn = this.projections?.pension_pot_projection?.expected_return || 5;
      const fees = this.requiredCapital?.assumptions?.fees_total || 1;
      const netRate = (grossReturn - fees) / 100;
      const m = 4; // Quarterly compounding
      const n = this.yearsToRetirement;
      const periodicRate = netRate / m;
      const periods = m * n;
      const fvFactor = Math.pow(1 + periodicRate, periods);
      const fvAnnuity = (additionalAnnual / m) * ((fvFactor - 1) / periodicRate);

      return fvAnnuity;
    },

    newProjectedCapital() {
      return this.projectedCapitalAtRetirement + this.additionalCapitalAtRetirement;
    },

    newProjectedCapitalClass() {
      if (this.newProjectedCapital >= this.requiredCapitalAtRetirement) {
        return 'green';
      }
      return 'red';
    },

    newCapitalGap() {
      return this.newProjectedCapital - this.requiredCapitalAtRetirement;
    },

    newCapitalGapClass() {
      return this.newCapitalGap >= 0 ? 'green' : 'red';
    },

    // Capital progress
    capitalProgressPercent() {
      if (this.requiredCapitalAtRetirement === 0) return 0;
      return Math.round((this.projectedCapitalAtRetirement / this.requiredCapitalAtRetirement) * 100);
    },

    capitalProgressClass() {
      const pct = this.capitalProgressPercent;
      if (pct >= 100) return 'green';
      if (pct >= 80) return 'blue';
      return 'red';
    },

    capitalStatusClass() {
      const pct = this.capitalProgressPercent;
      if (pct >= 100) return 'surplus';
      if (pct >= 80) return 'on-track';
      return 'shortfall';
    },

    capitalStatusTitle() {
      const pct = this.capitalProgressPercent;
      if (pct >= 100) return 'On Track';
      if (pct >= 80) return 'Nearly There';
      return 'Capital Shortfall';
    },

    capitalStatusMessage() {
      const pct = this.capitalProgressPercent;
      const gap = this.requiredCapitalAtRetirement - this.projectedCapitalAtRetirement;
      if (pct >= 100) {
        const surplus = this.projectedCapitalAtRetirement - this.requiredCapitalAtRetirement;
        return `You're projected to have ${this.formatCurrency(surplus)} more than required at retirement.`;
      }
      if (pct >= 80) {
        return `You're ${this.formatCurrency(gap)} short of your target. Consider increasing contributions.`;
      }
      return `You need an additional ${this.formatCurrency(gap)} to meet your retirement target.`;
    },
  },

  mounted() {
    this.loadData();
  },

  methods: {
    ...mapActions('retirement', [
      'fetchProjections',
      'fetchRequiredCapital',
      'fetchStrategies',
      'fetchAnnualAllowance',
    ]),

    async loadData() {
      try {
        // Get current tax year for annual allowance
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const day = now.getDate();
        const taxYear = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;

        // Fetch all data in parallel
        await Promise.all([
          this.fetchProjections(),
          this.fetchRequiredCapital(),
          this.fetchStrategies(),
          this.fetchAnnualAllowance(taxYear),
        ]);
        this.dataLoaded = true;
      } catch (error) {
        logger.error('Failed to load capital adequacy data:', error);
      }
    },
  },
};
</script>

<style scoped>
.capital-adequacy-tab {
  animation: fadeInSlideUp 0.3s ease-out;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}

.loading-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

/* Error State */
.error-state {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 12px;
  @apply border border-raspberry-200;
}

.error-icon {
  width: 48px;
  height: 48px;
  @apply text-raspberry-500;
  margin: 0 auto 16px;
}

.error-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0 0 16px 0;
}

.retry-button {
  @apply bg-raspberry-500;
  color: white;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.retry-button:hover {
  @apply bg-raspberry-500;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 40px;
  border-radius: 12px;
  @apply bg-light-blue-100 border border-light-gray;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-state p {
  @apply text-neutral-500;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  @apply text-horizon-400;
  font-size: 14px;
  font-weight: 400;
}

/* Header Section */
.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
  flex: 1;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 4px 0;
}

.section-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-light-gray;
}

.summary-label {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
  font-weight: 500;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.summary-subtitle {
  font-size: 13px;
  @apply text-neutral-500;
  margin: 8px 0 0 0;
}

/* Summary Card Colors */
.summary-card.teal {
  @apply border-teal-200;
  @apply bg-teal-50;
}

.summary-card.teal .summary-value {
  @apply text-teal-700;
}

.summary-card.green {
  @apply border-spring-200;
  @apply bg-spring-50;
}

.summary-card.green .summary-value {
  @apply text-spring-700;
}

.summary-card.red {
  @apply border-raspberry-200;
  @apply bg-raspberry-50;
}

.summary-card.red .summary-value {
  @apply text-raspberry-700;
}

.summary-card.blue {
  @apply border-violet-200;
  @apply bg-violet-50;
}

.summary-card.blue .summary-value {
  @apply text-violet-700;
}

.summary-card.indigo {
  @apply border-violet-200;
  @apply bg-violet-50;
}

.summary-card.indigo .summary-value {
  @apply text-violet-700;
}

/* Carry Forward Card with Year Breakdown */
.carry-forward-card {
  display: flex;
  flex-direction: column;
}

.carry-forward-breakdown {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cf-year-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.cf-year-label {
  @apply text-violet-600;
  font-weight: 500;
}

.cf-year-value {
  @apply text-violet-700;
  font-weight: 600;
}

.cf-year-row.cf-total {
  margin-top: 6px;
  padding-top: 8px;
  @apply border-t border-violet-300;
}

.cf-year-row.cf-total .cf-year-label,
.cf-year-row.cf-total .cf-year-value {
  font-weight: 700;
  font-size: 13px;
}

/* Section Styling */
.allowance-section,
.slider-section,
.progress-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  @apply border border-light-gray;
  margin-bottom: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-heading {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0;
}

.section-description {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 4px 0 0 0;
}

.tax-year-badge {
  display: inline-block;
  padding: 4px 12px;
  @apply bg-violet-50 text-violet-700;
  border-radius: 16px;
  font-size: 13px;
  font-weight: 600;
}

/* Allowance Progress */
.allowance-progress {
  padding: 0;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.progress-used {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
}

.progress-remaining {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
}

.allowance-hint {
  font-weight: 400;
  @apply text-horizon-400;
}

.progress-bar-container {
  height: 12px;
  @apply bg-savannah-200;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 16px;
  position: relative;
}

.progress-bar-container.large {
  height: 16px;
  border-radius: 8px;
}

.progress-bar {
  height: 100%;
  border-radius: 6px;
  transition: width 0.3s ease;
}

.progress-bar.green {
  @apply bg-spring-500;
}

.progress-bar.blue {
  @apply bg-violet-500;
}

.progress-bar.red {
  @apply bg-raspberry-500;
}

.progress-bar.surplus {
  @apply bg-spring-400;
  position: absolute;
  top: 0;
}

.progress-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 16px;
  @apply border-t border-light-gray;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.breakdown-label {
  @apply text-neutral-500;
}

.breakdown-value {
  @apply text-horizon-500;
  font-weight: 600;
}

.breakdown-item.total {
  padding-top: 8px;
  @apply border-t border-light-gray;
}

.breakdown-item.total .breakdown-label,
.breakdown-item.total .breakdown-value {
  font-weight: 700;
}

/* Monthly Equivalent Section */
.monthly-equivalent {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t border-light-gray;
}

.monthly-equivalent .breakdown-item {
  margin-bottom: 12px;
}

.affordability-note {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  @apply bg-violet-50 border border-violet-200;
  border-radius: 8px;
  font-size: 13px;
  @apply text-violet-700;
}

.affordability-note svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  @apply text-violet-500;
}

/* Slider Section */
.slider-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.slider-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
}

.current-contribution {
  font-size: 14px;
  @apply text-neutral-500;
}

.additional-contribution {
  font-size: 14px;
  font-weight: 600;
  @apply text-raspberry-500;
}

.slider-container {
  padding: 0 8px;
}

.contribution-slider {
  width: 100%;
  height: 8px;
  -webkit-appearance: none;
  appearance: none;
  background: linear-gradient(to right, theme('colors.raspberry.500') 0%, theme('colors.horizon.200') 0%);
  border-radius: 4px;
  outline: none;
  cursor: pointer;
}

.contribution-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 24px;
  height: 24px;
  background: white;
  @apply border-2 border-raspberry-500;
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.15s;
}

.contribution-slider::-webkit-slider-thumb:hover {
  transform: scale(1.1);
}

.contribution-slider::-moz-range-thumb {
  width: 24px;
  height: 24px;
  background: white;
  border: 2px solid theme('colors.raspberry.500');
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.slider-ticks {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  @apply text-horizon-400;
  margin-top: 8px;
}

.slider-limit-note {
  font-size: 12px;
  @apply text-neutral-500;
  margin: 12px 0 0 0;
  font-style: italic;
}

/* Constraint Warnings */
.constraint-warnings {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.warning-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  @apply bg-violet-50 border border-violet-200;
  border-radius: 8px;
  font-size: 13px;
  @apply text-violet-700;
}

.warning-banner.affordability {
  @apply bg-violet-50 border-violet-200 text-violet-700;
}

.warning-banner svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* Impact Panel */
.impact-panel {
  @apply bg-savannah-100;
  border-radius: 12px;
  padding: 20px;
}

.impact-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.impact-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.impact-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.impact-label {
  font-size: 13px;
  @apply text-neutral-500;
}

.impact-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

.impact-value.highlight {
  @apply text-raspberry-500;
}

.impact-value.green {
  @apply text-spring-600;
}

.impact-value.red {
  @apply text-raspberry-600;
}

.slider-note {
  font-size: 13px;
  @apply text-horizon-400;
  margin: 20px 0 0 0;
  font-style: italic;
}

/* Capital Progress */
.capital-progress {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.progress-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.progress-type {
  font-size: 14px;
  @apply text-neutral-500;
  font-weight: 500;
}

.progress-percentage {
  font-size: 16px;
  font-weight: 700;
}

.progress-percentage.green {
  @apply text-spring-600;
}

.progress-percentage.blue {
  @apply text-violet-600;
}

.progress-percentage.red {
  @apply text-raspberry-600;
}

.progress-values {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  @apply text-neutral-500;
}

.target-label {
  @apply text-horizon-400;
}

/* Status Banner */
.status-banner {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  border-radius: 12px;
  margin-top: 8px;
}

.status-banner.surplus {
  @apply bg-spring-50 border border-spring-200;
}

.status-banner.surplus svg {
  @apply text-spring-600;
}

.status-banner.on-track {
  @apply bg-violet-50 border border-violet-200;
}

.status-banner.on-track svg {
  @apply text-violet-600;
}

.status-banner.shortfall {
  @apply bg-raspberry-50 border border-raspberry-200;
}

.status-banner.shortfall svg {
  @apply text-raspberry-600;
}

.status-banner svg {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
}

.status-text h5 {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 4px 0;
}

.status-banner.surplus .status-text h5 {
  @apply text-spring-800;
}

.status-banner.on-track .status-text h5 {
  @apply text-violet-800;
}

.status-banner.shortfall .status-text h5 {
  @apply text-raspberry-800;
}

.status-text p {
  font-size: 14px;
  margin: 0;
}

.status-banner.surplus .status-text p {
  @apply text-spring-700;
}

.status-banner.on-track .status-text p {
  @apply text-violet-700;
}

.status-banner.shortfall .status-text p {
  @apply text-raspberry-700;
}

/* Responsive */
@media (max-width: 1200px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .slider-content {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .impact-grid {
    grid-template-columns: 1fr;
  }
}
</style>
