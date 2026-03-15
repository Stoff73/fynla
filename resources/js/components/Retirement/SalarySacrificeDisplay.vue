<template>
  <div class="salary-sacrifice-display">
    <h3 class="display-title">Salary Sacrifice Analysis</h3>

    <!-- Warning badges -->
    <div v-if="warnings.length > 0" class="warnings-section">
      <div
        v-for="(warning, index) in warnings"
        :key="index"
        class="warning-badge"
      >
        <svg
          class="w-4 h-4 text-violet-500 flex-shrink-0"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
        <span class="text-sm text-violet-800">{{ warning }}</span>
      </div>
    </div>

    <!-- Side-by-side comparison cards -->
    <div class="comparison-grid">
      <!-- Without Salary Sacrifice -->
      <div class="comparison-card without-card">
        <div class="card-label">
          <div class="label-dot without-dot"></div>
          <span>Without Salary Sacrifice</span>
        </div>

        <div class="metric-rows">
          <div class="metric-row">
            <span class="metric-label">Gross Salary</span>
            <span class="metric-value">{{ formatCurrency(grossSalary) }}</span>
          </div>
          <div class="metric-row">
            <span class="metric-label">Income Tax</span>
            <span class="metric-value deduction">-{{ formatCurrency(withoutTax) }}</span>
          </div>
          <div class="metric-row">
            <span class="metric-label">National Insurance (Employee)</span>
            <span class="metric-value deduction">-{{ formatCurrency(withoutEmployeeNI) }}</span>
          </div>
          <div class="metric-row">
            <span class="metric-label">Pension Contribution</span>
            <span class="metric-value deduction">-{{ formatCurrency(currentContribution) }}</span>
          </div>
          <div class="metric-row total-row">
            <span class="metric-label">Take-Home Pay</span>
            <span class="metric-value total">{{ formatCurrency(withoutTakeHome) }}</span>
          </div>
          <div class="metric-row employer-row">
            <span class="metric-label">Employer National Insurance</span>
            <span class="metric-value">{{ formatCurrency(withoutEmployerNI) }}</span>
          </div>
        </div>
      </div>

      <!-- Arrow -->
      <div class="comparison-arrow">
        <svg class="w-6 h-6 text-spring-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
        </svg>
      </div>

      <!-- With Salary Sacrifice -->
      <div class="comparison-card with-card">
        <div class="card-label">
          <div class="label-dot with-dot"></div>
          <span>With Salary Sacrifice</span>
        </div>

        <div class="metric-rows">
          <div class="metric-row">
            <span class="metric-label">Gross Salary</span>
            <span class="metric-value">{{ formatCurrency(grossSalary) }}</span>
          </div>
          <div class="metric-row">
            <span class="metric-label">Salary Sacrifice</span>
            <span class="metric-value sacrifice">-{{ formatCurrency(recommendedSacrifice) }}</span>
          </div>
          <div class="metric-row">
            <span class="metric-label">Income Tax</span>
            <span class="metric-value deduction">-{{ formatCurrency(withTax) }}</span>
          </div>
          <div class="metric-row">
            <span class="metric-label">National Insurance (Employee)</span>
            <span class="metric-value deduction">-{{ formatCurrency(withEmployeeNI) }}</span>
          </div>
          <div class="metric-row total-row">
            <span class="metric-label">Take-Home Pay</span>
            <span class="metric-value total">{{ formatCurrency(withTakeHome) }}</span>
          </div>
          <div class="metric-row employer-row">
            <span class="metric-label">Employer National Insurance</span>
            <span class="metric-value">{{ formatCurrency(withEmployerNI) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Net cost comparison -->
    <div class="net-cost-section">
      <h4 class="section-title">Net Impact Summary</h4>
      <div class="savings-grid">
        <div class="saving-item">
          <span class="saving-label">Reduction in Take-Home Pay</span>
          <span class="saving-value cost">{{ formatCurrency(takeHomeReduction) }}</span>
        </div>
        <div class="saving-item">
          <span class="saving-label">Employee National Insurance Saving</span>
          <span class="saving-value saving">{{ formatCurrency(employeeNISaving) }}</span>
        </div>
        <div class="saving-item">
          <span class="saving-label">Employer National Insurance Saving</span>
          <span class="saving-value saving">{{ formatCurrency(employerNISaving) }}</span>
        </div>
        <div class="saving-item">
          <span class="saving-label">Total Additional Pension Contribution</span>
          <span class="saving-value pension-total">{{ formatCurrency(totalPensionBenefit) }}</span>
        </div>
        <div class="saving-item net-row">
          <span class="saving-label">Net Cost to You</span>
          <span class="saving-value net">{{ formatCurrency(netCost) }}</span>
          <span v-if="netCost < recommendedSacrifice" class="saving-note">
            Every {{ formatCurrency(1) }} of sacrifice costs you only {{ formatCurrencyWithPence(netCostPerPound) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'SalarySacrificeDisplay',

  mixins: [currencyMixin],

  props: {
    analysis: {
      type: Object,
      required: true,
    },
  },

  computed: {
    // Current values
    grossSalary() {
      return this.analysis?.gross_salary || 0;
    },

    currentContribution() {
      return this.analysis?.current_contribution || 0;
    },

    recommendedSacrifice() {
      return this.analysis?.recommended_sacrifice || 0;
    },

    // Without salary sacrifice
    withoutTax() {
      return this.analysis?.without?.income_tax || 0;
    },

    withoutEmployeeNI() {
      return this.analysis?.without?.employee_ni || 0;
    },

    withoutEmployerNI() {
      return this.analysis?.without?.employer_ni || 0;
    },

    withoutTakeHome() {
      return this.analysis?.without?.take_home || 0;
    },

    // With salary sacrifice
    withTax() {
      return this.analysis?.with?.income_tax || 0;
    },

    withEmployeeNI() {
      return this.analysis?.with?.employee_ni || 0;
    },

    withEmployerNI() {
      return this.analysis?.with?.employer_ni || 0;
    },

    withTakeHome() {
      return this.analysis?.with?.take_home || 0;
    },

    // Savings calculations
    employeeNISaving() {
      return Math.max(0, this.withoutEmployeeNI - this.withEmployeeNI);
    },

    employerNISaving() {
      return Math.max(0, this.withoutEmployerNI - this.withEmployerNI);
    },

    takeHomeReduction() {
      return Math.max(0, this.withoutTakeHome - this.withTakeHome);
    },

    totalPensionBenefit() {
      return this.analysis?.total_pension_benefit || (this.recommendedSacrifice + this.employerNISaving);
    },

    netCost() {
      return this.analysis?.net_cost || this.takeHomeReduction;
    },

    netCostPerPound() {
      if (this.recommendedSacrifice === 0) return 0;
      return this.netCost / this.recommendedSacrifice;
    },

    // Warning conditions
    warnings() {
      const warnings = [];
      const sacrifice = this.recommendedSacrifice;
      const salary = this.grossSalary;

      if (salary > 0 && sacrifice > 0) {
        const sacrificePercent = (sacrifice / salary) * 100;

        if (sacrificePercent > 20) {
          warnings.push(
            `The recommended sacrifice of ${this.formatCurrency(sacrifice)} represents ${sacrificePercent.toFixed(1)}% of your gross salary, which is above 20%. Consider the impact on your day-to-day finances.`
          );
        }

        const personalAllowance = this.analysis?.personal_allowance || 12570;
        const reducedSalary = salary - sacrifice;
        if (reducedSalary < personalAllowance) {
          warnings.push(
            `This sacrifice would reduce your salary below the Personal Allowance (${this.formatCurrency(personalAllowance)}), meaning you would lose some tax-free income.`
          );
        }

        const autoEnrolmentTrigger = this.analysis?.auto_enrolment_trigger || 10000;
        if (reducedSalary < autoEnrolmentTrigger) {
          warnings.push(
            `This sacrifice would reduce your salary below the auto-enrolment trigger (${this.formatCurrency(autoEnrolmentTrigger)}), which could affect your employer's contribution obligations.`
          );
        }
      }

      return warnings;
    },
  },
};
</script>

<style scoped>
.salary-sacrifice-display {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 24px;
}

.display-title {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 20px 0;
}

/* Warnings */
.warnings-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.warning-badge {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  @apply bg-violet-50;
  @apply border border-violet-200;
  border-radius: 8px;
}

/* Comparison grid */
.comparison-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 16px;
  align-items: start;
  margin-bottom: 24px;
}

.comparison-card {
  border-radius: 10px;
  padding: 20px;
  @apply border border-light-gray;
}

.without-card {
  @apply bg-savannah-100;
}

.with-card {
  @apply bg-spring-50;
  @apply border-spring-200;
}

.card-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  @apply text-horizon-500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 16px;
}

.label-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.without-dot {
  @apply bg-horizon-400;
}

.with-dot {
  @apply bg-spring-500;
}

/* Metric rows */
.metric-rows {
  display: flex;
  flex-direction: column;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  @apply border-b border-light-gray;
}

.metric-row:last-child {
  border-bottom: none;
}

.metric-label {
  font-size: 13px;
  @apply text-neutral-500;
}

.metric-value {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
  white-space: nowrap;
}

.metric-value.deduction {
  @apply text-raspberry-600;
}

.metric-value.sacrifice {
  @apply text-violet-600;
  font-weight: 700;
}

.metric-value.total {
  font-size: 16px;
  font-weight: 700;
}

.total-row {
  @apply border-t-2 border-horizon-200;
  margin-top: 4px;
  padding-top: 12px;
}

.employer-row {
  @apply bg-savannah-100;
  border-radius: 6px;
  padding: 10px 8px;
  margin-top: 8px;
  border-bottom: none;
}

.with-card .employer-row {
  @apply bg-spring-100;
}

/* Arrow */
.comparison-arrow {
  display: flex;
  align-items: center;
  padding-top: 120px;
}

/* Net cost section */
.net-cost-section {
  padding-top: 24px;
  @apply border-t border-light-gray;
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.savings-grid {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.saving-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  @apply bg-savannah-100;
  border-radius: 8px;
  flex-wrap: wrap;
}

.saving-label {
  font-size: 14px;
  @apply text-neutral-500;
}

.saving-value {
  font-size: 15px;
  font-weight: 700;
  @apply text-horizon-500;
}

.saving-value.cost {
  @apply text-raspberry-600;
}

.saving-value.saving {
  @apply text-spring-600;
}

.saving-value.pension-total {
  @apply text-spring-600;
  font-size: 16px;
}

.saving-value.net {
  @apply text-horizon-500;
  font-size: 16px;
}

.net-row {
  @apply bg-horizon-500;
  @apply text-white;
  margin-top: 4px;
}

.net-row .saving-label {
  @apply text-white;
  font-weight: 600;
}

.net-row .saving-value {
  @apply text-white;
}

.saving-note {
  width: 100%;
  font-size: 12px;
  @apply text-savannah-200;
  margin-top: 4px;
}

@media (max-width: 768px) {
  .salary-sacrifice-display {
    padding: 18px;
  }

  .comparison-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .comparison-arrow {
    padding-top: 0;
    justify-content: center;
    transform: rotate(90deg);
  }

  .metric-label {
    font-size: 12px;
  }

  .metric-value {
    font-size: 13px;
  }

  .saving-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}
</style>
