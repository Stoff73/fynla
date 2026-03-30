<template>
  <div class="employee-share-scheme-detail space-y-6">
    <!-- Key Metrics Header -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Card 1: Exercise/Grant Value -->
      <div class="metric-card bg-violet-50 border border-violet-200">
        <p class="metric-label">{{ isRSU ? 'Grant Value' : 'Exercise Value' }}</p>
        <p class="metric-value text-violet-600">{{ formatCurrency(isRSU ? grantValue : exerciseValue) }}</p>
        <p class="metric-sub">{{ formatNumber(account.units_granted) }} units</p>
      </div>

      <!-- Card 2: Vested Value -->
      <div class="metric-card bg-spring-50 border border-spring-200">
        <p class="metric-label">Vested Value</p>
        <p class="metric-value text-spring-600">{{ formatCurrency(vestedValue) }}</p>
        <p class="metric-sub">{{ formatNumber(account.units_vested || 0) }} vested</p>
      </div>

      <!-- Card 3: Vesting Progress -->
      <div class="metric-card bg-violet-50 border border-violet-200">
        <p class="metric-label">Vesting Progress</p>
        <p class="metric-value text-violet-600">{{ vestingProgressPercent.toFixed(0) }}%</p>
        <div class="w-full bg-violet-200 rounded-full h-2 mt-2">
          <div class="bg-violet-500 h-2 rounded-full" :style="{ width: `${vestingProgressPercent}%` }"></div>
        </div>
      </div>

      <!-- Card 4: Exercise Window / Full Vest -->
      <div class="metric-card" :class="exerciseWindowCardClass">
        <template v-if="isOptionsScheme">
          <p class="metric-label">Exercise Window</p>
          <template v-if="isInExerciseWindow">
            <p class="metric-value text-spring-600">Open</p>
            <p class="metric-sub">{{ daysRemainingInWindow }} days left</p>
          </template>
          <template v-else-if="daysToExerciseWindow">
            <p class="metric-value text-violet-600">{{ daysToExerciseWindow }}</p>
            <p class="metric-sub">days until open</p>
          </template>
          <template v-else>
            <p class="metric-value text-neutral-500">--</p>
            <p class="metric-sub">Not set</p>
          </template>
        </template>
        <template v-else>
          <p class="metric-label">Full Vest</p>
          <template v-if="daysToFullVest">
            <p class="metric-value text-violet-600">{{ daysToFullVest }}</p>
            <p class="metric-sub">days remaining</p>
          </template>
          <template v-else>
            <p class="metric-value text-spring-600">Complete</p>
            <p class="metric-sub">Fully vested</p>
          </template>
        </template>
      </div>
    </div>

    <!-- Employer Details -->
    <div class="details-section">
      <h3 class="section-title">Employer Details</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Employer</span>
          <span class="detail-value">{{ account.employer_name || 'Not specified' }}</span>
        </div>
        <div v-if="account.employer_ticker" class="detail-item">
          <span class="detail-label">Ticker</span>
          <span class="detail-value">{{ account.employer_ticker }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Listed Company</span>
          <span class="detail-value">{{ account.employer_is_listed ? 'Yes' : 'No' }}</span>
        </div>
        <div v-if="account.employer_registration" class="detail-item">
          <span class="detail-label">Company Number</span>
          <span class="detail-value">{{ account.employer_registration }}</span>
        </div>
        <div v-if="account.parent_company_name" class="detail-item">
          <span class="detail-label">Parent Company</span>
          <span class="detail-value">{{ account.parent_company_name }}</span>
        </div>
        <div v-if="account.ers_scheme_reference" class="detail-item">
          <span class="detail-label">Employment Related Securities Reference</span>
          <span class="detail-value">{{ account.ers_scheme_reference }}</span>
        </div>
      </div>
    </div>

    <!-- Grant Details -->
    <div class="details-section">
      <h3 class="section-title">Grant Details</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Grant Date</span>
          <span class="detail-value">{{ formatDate(account.grant_date) }}</span>
        </div>
        <div v-if="account.grant_reference" class="detail-item">
          <span class="detail-label">Reference</span>
          <span class="detail-value">{{ account.grant_reference }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Units Granted</span>
          <span class="detail-value text-violet-600 font-bold">{{ formatNumber(account.units_granted) }}</span>
        </div>
        <div v-if="isOptionsScheme" class="detail-item">
          <span class="detail-label">Exercise Price</span>
          <span class="detail-value">{{ formatCurrencyPence(account.exercise_price) }}</span>
        </div>
        <div v-if="account.market_value_at_grant" class="detail-item">
          <span class="detail-label">Market Value at Grant</span>
          <span class="detail-value">{{ formatCurrencyPence(account.market_value_at_grant) }}</span>
        </div>
        <div v-if="account.share_class_scheme" class="detail-item">
          <span class="detail-label">Share Class</span>
          <span class="detail-value">{{ account.share_class_scheme }}</span>
        </div>
      </div>
    </div>

    <!-- Vesting Schedule -->
    <div class="details-section">
      <h3 class="section-title">Vesting Schedule</h3>

      <!-- Vesting Progress Bar -->
      <div class="vesting-progress mb-6 p-4 bg-eggshell-500 rounded-lg">
        <div class="flex justify-between text-sm mb-2">
          <span class="text-neutral-500">Vesting Progress</span>
          <span class="font-semibold">{{ vestingProgressPercent.toFixed(1) }}%</span>
        </div>
        <div class="w-full bg-savannah-200 rounded-full h-3">
          <div
            class="bg-spring-500 h-3 rounded-full transition-all duration-500"
            :style="{ width: `${vestingProgressPercent}%` }"
          ></div>
        </div>
        <div class="flex justify-between text-xs text-neutral-500 mt-2">
          <span>{{ formatNumber(account.units_vested || 0) }} vested</span>
          <span>{{ formatNumber(account.units_unvested || 0) }} unvested</span>
        </div>
      </div>

      <div class="details-grid">
        <div v-if="account.vesting_type" class="detail-item">
          <span class="detail-label">Vesting Type</span>
          <span class="detail-value">{{ formatVestingType(account.vesting_type) }}</span>
        </div>
        <div v-if="account.cliff_date" class="detail-item">
          <span class="detail-label">Cliff Date</span>
          <span class="detail-value">{{ formatDate(account.cliff_date) }} ({{ account.cliff_percentage || 0 }}%)</span>
        </div>
        <div v-if="account.vesting_period_months" class="detail-item">
          <span class="detail-label">Vesting Period</span>
          <span class="detail-value">{{ account.vesting_period_months }} months</span>
        </div>
        <div v-if="account.full_vest_date" class="detail-item">
          <span class="detail-label">Full Vest Date</span>
          <span class="detail-value">{{ formatDate(account.full_vest_date) }}</span>
        </div>
        <div v-if="account.has_performance_conditions" class="detail-item detail-item-wide">
          <span class="detail-label">Performance Conditions</span>
          <span class="detail-value">{{ account.performance_conditions_description || 'Yes - see grant letter' }}</span>
        </div>
      </div>
    </div>

    <!-- Current Status -->
    <div class="details-section">
      <h3 class="section-title">Current Status</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Scheme Status</span>
          <span class="detail-value" :class="schemeStatusClass">{{ schemeStatusLabel }}</span>
        </div>
        <div v-if="account.current_share_price" class="detail-item">
          <span class="detail-label">Current Share Price</span>
          <span class="detail-value">{{ formatCurrencyPence(account.current_share_price) }}</span>
        </div>
        <div v-if="account.share_price_date" class="detail-item">
          <span class="detail-label">Price Date</span>
          <span class="detail-value text-neutral-500">{{ formatDate(account.share_price_date) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Units Vested</span>
          <span class="detail-value text-spring-600">{{ formatNumber(account.units_vested || 0) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Units Unvested</span>
          <span class="detail-value text-violet-600">{{ formatNumber(account.units_unvested || 0) }}</span>
        </div>
        <div v-if="account.units_exercised" class="detail-item">
          <span class="detail-label">Units Exercised</span>
          <span class="detail-value text-violet-600">{{ formatNumber(account.units_exercised) }}</span>
        </div>
        <div v-if="account.units_forfeited" class="detail-item">
          <span class="detail-label">Units Forfeited</span>
          <span class="detail-value text-raspberry-600">{{ formatNumber(account.units_forfeited) }}</span>
        </div>
      </div>
    </div>

    <!-- Exercise & Expiry (Options only) -->
    <div v-if="isOptionsScheme" class="details-section">
      <h3 class="section-title">Exercise & Expiry</h3>

      <!-- Exercise Window Alert -->
      <div v-if="isInExerciseWindow" class="alert alert-success mb-4">
        <p class="font-medium">Exercise Window is OPEN</p>
        <p class="text-sm">{{ daysRemainingInWindow }} days remaining until {{ formatDate(account.exercise_window_end) }}</p>
      </div>
      <div v-else-if="daysToExerciseWindow" class="alert alert-info mb-4">
        <p class="font-medium">Exercise window opens in {{ daysToExerciseWindow }} days</p>
        <p class="text-sm">{{ formatDate(account.exercise_window_start) }}</p>
      </div>

      <div class="details-grid">
        <div v-if="account.exercise_window_start" class="detail-item">
          <span class="detail-label">Window Opens</span>
          <span class="detail-value">{{ formatDate(account.exercise_window_start) }}</span>
        </div>
        <div v-if="account.exercise_window_end" class="detail-item">
          <span class="detail-label">Window Closes</span>
          <span class="detail-value">{{ formatDate(account.exercise_window_end) }}</span>
        </div>
        <div v-if="account.total_exercise_proceeds" class="detail-item">
          <span class="detail-label">Total Exercise Proceeds</span>
          <span class="detail-value text-spring-600">{{ formatCurrency(account.total_exercise_proceeds) }}</span>
        </div>
        <div v-if="account.total_exercise_cost" class="detail-item">
          <span class="detail-label">Total Exercise Cost</span>
          <span class="detail-value">{{ formatCurrency(account.total_exercise_cost) }}</span>
        </div>
      </div>
    </div>

    <!-- Tax Treatment -->
    <div class="details-section">
      <h3 class="section-title">Tax Treatment</h3>

      <!-- Tax-advantaged scheme badge -->
      <div v-if="isTaxAdvantaged" class="alert alert-success mb-4">
        <p class="font-medium">Tax-Advantaged Scheme</p>
        <p class="text-sm">
          <template v-if="isSAYE">SAYE: No Income Tax or National Insurance on exercise if held to maturity</template>
          <template v-else-if="isCSOPScheme">CSOP: No Income Tax on exercise between 3-10 years from grant</template>
          <template v-else>EMI: Capital Gains Tax rates apply on disposal (with potential Business Asset Disposal Relief)</template>
        </p>
      </div>

      <!-- CSOP 3-year window info -->
      <div v-if="isCSOPScheme && csopThreeYearDate" class="mb-4">
        <div v-if="isInCsopTaxWindow" class="alert alert-success">
          <p class="font-medium">In CSOP Tax-Advantaged Window</p>
          <p class="text-sm">Exercise now for Capital Gains Tax treatment only (no Income Tax)</p>
        </div>
        <div v-else-if="daysToCsopTaxWindow" class="alert alert-warning">
          <p class="font-medium">{{ daysToCsopTaxWindow }} days until tax-advantaged window</p>
          <p class="text-sm">Opens {{ formatDate(csopThreeYearDate) }}</p>
        </div>
      </div>

      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Tax Treatment</span>
          <span class="detail-value">{{ account.tax_treatment === 'tax_advantaged' ? 'Tax Advantaged' : 'Unapproved' }}</span>
        </div>
        <div v-if="isCSOPScheme && csopThreeYearDate" class="detail-item">
          <span class="detail-label">CSOP 3-Year Date</span>
          <span class="detail-value" :class="isInCsopTaxWindow ? 'text-spring-600' : 'text-violet-600'">
            {{ formatDate(csopThreeYearDate) }}
          </span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Readily Convertible Asset</span>
          <span class="detail-value">{{ account.is_readily_convertible_asset ? 'Yes' : 'No' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">PAYE via Payroll</span>
          <span class="detail-value">{{ account.paye_via_payroll !== false ? 'Yes' : 'No' }}</span>
        </div>
      </div>
    </div>

    <!-- SAYE Savings (SAYE only) -->
    <div v-if="isSAYE" class="details-section bg-spring-50 border-spring-200">
      <h3 class="section-title text-spring-800">SAYE Savings Contract</h3>
      <div class="details-grid">
        <div class="detail-item">
          <span class="detail-label">Monthly Savings</span>
          <span class="detail-value">{{ formatCurrency(account.saye_monthly_savings) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Current Balance</span>
          <span class="detail-value text-spring-600 font-bold">{{ formatCurrency(account.saye_current_savings_balance) }}</span>
        </div>
        <div v-if="account.saye_maturity_date" class="detail-item">
          <span class="detail-label">Maturity Date</span>
          <span class="detail-value">{{ formatDate(account.saye_maturity_date) }}</span>
        </div>
        <div v-if="account.scheme_duration_months" class="detail-item">
          <span class="detail-label">Contract Duration</span>
          <span class="detail-value">{{ account.scheme_duration_months }} months</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Projected Savings</span>
          <span class="detail-value text-spring-600">{{ formatCurrency(sayeProjectedSavings) }}</span>
        </div>
      </div>
    </div>

    <!-- Leaver Terms -->
    <div v-if="account.leaver_category || account.post_termination_exercise_days" class="details-section">
      <h3 class="section-title">Leaver Terms</h3>
      <div class="details-grid">
        <div v-if="account.leaver_category" class="detail-item">
          <span class="detail-label">Leaver Category</span>
          <span class="detail-value">{{ formatLeaverCategory(account.leaver_category) }}</span>
        </div>
        <div v-if="account.post_termination_exercise_days" class="detail-item">
          <span class="detail-label">Post-Termination Exercise</span>
          <span class="detail-value">{{ account.post_termination_exercise_days }} days</span>
        </div>
        <div v-if="account.termination_date" class="detail-item">
          <span class="detail-label">Termination Date</span>
          <span class="detail-value text-raspberry-600">{{ formatDate(account.termination_date) }}</span>
        </div>
        <div v-if="account.leaver_notes" class="detail-item detail-item-wide">
          <span class="detail-label">Notes</span>
          <span class="detail-value">{{ account.leaver_notes }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EmployeeShareSchemeDetail',
  mixins: [currencyMixin],

  props: {
    account: {
      type: Object,
      required: true,
    },
  },

  computed: {
    // Type checks
    isOptionsScheme() {
      return ['saye', 'csop', 'emi', 'unapproved_options'].includes(this.account.account_type);
    },
    isRSU() {
      return this.account.account_type === 'rsu';
    },
    isSAYE() {
      return this.account.account_type === 'saye';
    },
    isCSOPScheme() {
      return this.account.account_type === 'csop';
    },
    isTaxAdvantaged() {
      return ['saye', 'csop', 'emi'].includes(this.account.account_type);
    },

    // Values
    exerciseValue() {
      const units = this.account.units_granted || 0;
      const price = parseFloat(this.account.exercise_price) || 0;
      return units * price;
    },
    grantValue() {
      const units = this.account.units_granted || 0;
      const price = parseFloat(this.account.market_value_at_grant) || 0;
      return units * price;
    },
    vestedValue() {
      const price = parseFloat(this.account.current_share_price) ||
                    parseFloat(this.account.market_value_at_grant) ||
                    parseFloat(this.account.exercise_price) || 0;
      return price * (this.account.units_vested || 0);
    },

    // Vesting
    vestingProgressPercent() {
      const granted = this.account.units_granted || 0;
      const vested = this.account.units_vested || 0;
      if (granted === 0) return 0;
      return Math.min(100, (vested / granted) * 100);
    },

    // Exercise Window
    daysToExerciseWindow() {
      if (!this.account.exercise_window_start) return null;
      const start = new Date(this.account.exercise_window_start);
      const now = new Date();
      const diff = Math.ceil((start - now) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : null;
    },
    daysRemainingInWindow() {
      if (!this.account.exercise_window_end) return null;
      const end = new Date(this.account.exercise_window_end);
      const now = new Date();
      const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : null;
    },
    isInExerciseWindow() {
      const now = new Date();
      const start = this.account.exercise_window_start ? new Date(this.account.exercise_window_start) : null;
      const end = this.account.exercise_window_end ? new Date(this.account.exercise_window_end) : null;
      if (!start || !end) return false;
      return now >= start && now <= end;
    },
    exerciseWindowCardClass() {
      if (this.isInExerciseWindow) return 'bg-spring-50 border border-spring-200';
      if (this.daysToExerciseWindow) return 'bg-violet-50 border border-violet-200';
      return 'bg-eggshell-500 border border-light-gray';
    },

    // Full vest
    daysToFullVest() {
      if (!this.account.full_vest_date) return null;
      const fullVest = new Date(this.account.full_vest_date);
      const now = new Date();
      const diff = Math.ceil((fullVest - now) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : null;
    },

    // CSOP
    csopThreeYearDate() {
      if (!this.isCSOPScheme || !this.account.grant_date) return null;
      const grantDate = new Date(this.account.grant_date);
      grantDate.setFullYear(grantDate.getFullYear() + 3);
      return grantDate;
    },
    isInCsopTaxWindow() {
      if (!this.csopThreeYearDate) return false;
      const now = new Date();
      const tenYear = new Date(this.account.grant_date);
      tenYear.setFullYear(tenYear.getFullYear() + 10);
      return now >= this.csopThreeYearDate && now <= tenYear;
    },
    daysToCsopTaxWindow() {
      if (!this.csopThreeYearDate) return null;
      const diff = Math.ceil((this.csopThreeYearDate - new Date()) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : null;
    },

    // SAYE
    sayeProjectedSavings() {
      if (!this.isSAYE) return null;
      const monthly = parseFloat(this.account.saye_monthly_savings) || 0;
      const durationMonths = this.account.scheme_duration_months || 36;
      return monthly * durationMonths;
    },

    // Status
    schemeStatusLabel() {
      const statuses = {
        'active': 'Active',
        'vesting': 'Vesting',
        'exercisable': 'Exercisable',
        'exercised': 'Fully Exercised',
        'expired': 'Expired',
        'forfeited': 'Forfeited',
        'cancelled': 'Cancelled',
      };
      return statuses[this.account.scheme_status] || 'Active';
    },
    schemeStatusClass() {
      const classes = {
        'active': 'text-spring-600',
        'vesting': 'text-violet-600',
        'exercisable': 'text-spring-600',
        'exercised': 'text-violet-600',
        'expired': 'text-neutral-500',
        'forfeited': 'text-raspberry-600',
        'cancelled': 'text-neutral-500',
      };
      return classes[this.account.scheme_status] || 'text-spring-600';
    },
  },

  methods: {
    formatDate(dateString) {
      if (!dateString) return '--';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    formatCurrencyPence(value) {
      if (!value && value !== 0) return '--';
      const num = parseFloat(value);
      if (num < 1) {
        return `${(num * 100).toFixed(1)}p`;
      }
      return this.formatCurrency(num);
    },
    formatVestingType(type) {
      const types = {
        'cliff': 'Cliff Vesting',
        'monthly': 'Monthly Vesting',
        'quarterly': 'Quarterly Vesting',
        'annual': 'Annual Vesting',
        'performance': 'Performance-Based',
        'immediate': 'Immediate',
      };
      return types[type] || type || '--';
    },
    formatLeaverCategory(category) {
      const categories = {
        'good_leaver': 'Good Leaver',
        'bad_leaver': 'Bad Leaver',
        'death': 'Death',
        'redundancy': 'Redundancy',
        'retirement': 'Retirement',
        'not_applicable': 'Not Applicable',
      };
      return categories[category] || category || '--';
    },
  },
};
</script>

<style scoped>
.metric-card {
  @apply rounded-lg p-4;
}

.metric-label {
  @apply text-sm text-neutral-500 mb-1;
}

.metric-value {
  @apply text-2xl font-bold;
}

.metric-sub {
  @apply text-xs text-neutral-500 mt-1;
}

.details-section {
  @apply bg-white rounded-lg border border-light-gray p-6;
}

.section-title {
  @apply text-lg font-semibold text-horizon-500 mb-5 pb-3 border-b border-light-gray;
}

.details-grid {
  @apply grid gap-5;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.detail-item {
  @apply flex flex-col gap-1;
}

.detail-item-wide {
  @apply col-span-full;
}

.detail-label {
  @apply text-sm font-medium text-neutral-500;
}

.detail-value {
  @apply text-base font-semibold text-horizon-500;
}

.alert {
  @apply rounded-lg p-4;
}

.alert-success {
  @apply bg-spring-50 border border-spring-200 text-spring-800;
}

.alert-info {
  @apply bg-violet-50 border border-violet-200 text-violet-800;
}

.alert-warning {
  @apply bg-violet-50 border border-violet-200 text-violet-800;
}

@media (max-width: 640px) {
  .details-grid {
    grid-template-columns: 1fr;
  }
}
</style>
