<template>
  <div class="pension-contributions-panel">
    <!-- DC Pension Contributions -->
    <div v-if="pensionType === 'dc'" class="contributions-container">
      <!-- Contribution Summary -->
      <div class="contribution-summary">
        <div class="summary-card total">
          <div class="card-header">
            <h3>Total Annual Contribution</h3>
            <span class="badge badge-blue">{{ annualAllowancePercentage }}% of allowance</span>
          </div>
          <p class="card-value">{{ formatCurrency(totalAnnualContribution) }}</p>
          <p class="card-sublabel">{{ formatCurrency(totalMonthlyContribution) }}/month</p>
        </div>

        <div class="summary-cards-grid">
          <div class="summary-card">
            <h4>Employee Contribution</h4>
            <p class="card-value">{{ formatCurrency(monthlyEmployeeContribution) }}</p>
            <p class="card-sublabel">{{ formatCurrency(annualEmployeeContribution) }}/year</p>
          </div>
          <div class="summary-card">
            <h4>Employer Contribution</h4>
            <p class="card-value">{{ formatCurrency(monthlyEmployerContribution) }}</p>
            <p class="card-sublabel">{{ formatCurrency(annualEmployerContribution) }}/year</p>
          </div>
          <div class="summary-card">
            <h4>Lump Sum Contributions</h4>
            <p class="card-value">{{ formatCurrency(pension.lump_sum_contribution || 0) }}</p>
            <p class="card-sublabel">One-time contributions</p>
          </div>
        </div>
      </div>

      <!-- Annual Allowance Tracker -->
      <div class="allowance-section">
        <h3 class="section-title">Annual Allowance Tracker</h3>
        <div class="allowance-bar-container">
          <div class="allowance-info">
            <span>Used: {{ formatCurrency(totalAnnualContribution) }}</span>
            <span>Remaining: {{ formatCurrency(remainingAllowance) }}</span>
          </div>
          <div class="allowance-bar">
            <div class="allowance-bar-fill" :style="{ width: allowanceBarWidth }"></div>
          </div>
          <div class="allowance-info">
            <span class="text-gray-500">Annual Allowance: {{ formatCurrency(60000) }}</span>
          </div>
        </div>

        <div v-if="totalAnnualContribution > 60000" class="allowance-warning">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          <span>You have exceeded the annual allowance. Tax charges may apply.</span>
        </div>
      </div>

      <!-- Contribution Details -->
      <div class="details-section">
        <h3 class="section-title">Contribution Details</h3>
        <div class="details-grid">
          <div v-if="pension.employee_contribution_percent" class="detail-item">
            <span class="detail-label">Employee Rate</span>
            <span class="detail-value">{{ pension.employee_contribution_percent }}% of salary</span>
          </div>
          <div v-if="pension.employer_contribution_percent" class="detail-item">
            <span class="detail-label">Employer Rate</span>
            <span class="detail-value">{{ pension.employer_contribution_percent }}% of salary</span>
          </div>
          <div v-if="pension.employer_matching_limit" class="detail-item">
            <span class="detail-label">Employer Matching</span>
            <span class="detail-value">
              {{ pension.employer_matching_limit === 100 ? 'Full matching' : 'Up to ' + pension.employer_matching_limit + '% of salary' }}
            </span>
          </div>
          <div v-if="pension.annual_salary" class="detail-item">
            <span class="detail-label">Pensionable Salary</span>
            <span class="detail-value">{{ formatCurrency(pension.annual_salary) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Tax Relief Method</span>
            <span class="detail-value">{{ pension.salary_sacrifice ? 'Net Pay (Salary Sacrifice)' : 'Relief at Source' }}</span>
          </div>
          <div v-if="pension.scheme_type" class="detail-item">
            <span class="detail-label">Scheme Type</span>
            <span class="detail-value">{{ formatSchemeType(pension.scheme_type) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- DB Pension Contributions -->
    <div v-else-if="pensionType === 'db'" class="contributions-container">
      <div class="db-info-card">
        <div class="info-icon">
          <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <div class="info-content">
          <h3>Defined Benefit Pension</h3>
          <p>DB pensions work differently from DC pensions. Rather than building up a pot of money, you earn a guaranteed income based on your salary and years of service.</p>
        </div>
      </div>

      <div class="contribution-summary">
        <div class="summary-cards-grid three-col">
          <div class="summary-card">
            <h4>Annual Income Entitlement</h4>
            <p class="card-value">{{ formatCurrency(pension.annual_income) }}</p>
            <p class="card-sublabel">Guaranteed at retirement</p>
          </div>
          <div class="summary-card">
            <h4>Accrual Rate</h4>
            <p class="card-value">{{ pension.accrual_rate ? '1/' + Math.round(1/pension.accrual_rate) : 'N/A' }}</p>
            <p class="card-sublabel">Pension per year of service</p>
          </div>
          <div class="summary-card">
            <h4>Years of Service</h4>
            <p class="card-value">{{ pension.years_of_service || 'N/A' }}</p>
            <p class="card-sublabel">Contributing to pension</p>
          </div>
        </div>
      </div>

      <div class="details-section">
        <h3 class="section-title">Benefit Calculation</h3>
        <div class="benefit-formula">
          <div class="formula-item">
            <span class="formula-label">Pensionable Salary</span>
            <span class="formula-value">{{ formatCurrency(pension.pensionable_salary || 0) }}</span>
          </div>
          <span class="formula-operator">x</span>
          <div class="formula-item">
            <span class="formula-label">Years of Service</span>
            <span class="formula-value">{{ pension.years_of_service || 'N/A' }}</span>
          </div>
          <span class="formula-operator">x</span>
          <div class="formula-item">
            <span class="formula-label">Accrual Rate</span>
            <span class="formula-value">{{ pension.accrual_rate ? (pension.accrual_rate * 100).toFixed(2) + '%' : 'N/A' }}</span>
          </div>
          <span class="formula-operator">=</span>
          <div class="formula-item result">
            <span class="formula-label">Annual Pension</span>
            <span class="formula-value">{{ formatCurrency(pension.annual_income) }}</span>
          </div>
        </div>
      </div>

      <div class="details-section">
        <h3 class="section-title">Employee Contributions</h3>
        <div class="details-grid">
          <div class="detail-item">
            <span class="detail-label">Contribution Rate</span>
            <span class="detail-value">{{ pension.employee_contribution_rate ? (pension.employee_contribution_rate * 100).toFixed(1) + '%' : 'N/A' }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Monthly Contribution</span>
            <span class="detail-value">{{ formatCurrency(dbMonthlyContribution) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Annual Contribution</span>
            <span class="detail-value">{{ formatCurrency(dbAnnualContribution) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- State Pension Contributions (NI Credits) -->
    <div v-else-if="pensionType === 'state'" class="contributions-container">
      <div class="state-info-card">
        <div class="info-icon">
          <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
          </svg>
        </div>
        <div class="info-content">
          <h3>State Pension & National Insurance</h3>
          <p>Your State Pension is built through National Insurance contributions. You need 35 qualifying years to receive the full State Pension.</p>
        </div>
      </div>

      <div class="contribution-summary">
        <div class="summary-cards-grid three-col">
          <div class="summary-card">
            <h4>NI Years Completed</h4>
            <p class="card-value">{{ pension.ni_years_completed || 0 }}</p>
            <p class="card-sublabel">out of 35 required</p>
          </div>
          <div class="summary-card">
            <h4>Years Still Needed</h4>
            <p class="card-value">{{ yearsNeeded }}</p>
            <p class="card-sublabel">for full pension</p>
          </div>
          <div class="summary-card">
            <h4>NI Record Percentage</h4>
            <p class="card-value">{{ niPercentage }}%</p>
            <p class="card-sublabel">of full record</p>
          </div>
        </div>
      </div>

      <!-- NI Progress Bar -->
      <div class="ni-progress-section">
        <h3 class="section-title">NI Record Progress</h3>
        <div class="ni-progress-container">
          <div class="ni-progress-bar">
            <div class="ni-progress-fill" :style="{ width: niPercentage + '%' }"></div>
            <div class="ni-progress-markers">
              <div class="marker" style="left: 28.57%"><span>10 yrs</span></div>
              <div class="marker" style="left: 57.14%"><span>20 yrs</span></div>
              <div class="marker" style="left: 85.71%"><span>30 yrs</span></div>
            </div>
          </div>
          <div class="ni-progress-labels">
            <span>0 years</span>
            <span>35 years (Full Pension)</span>
          </div>
        </div>
      </div>

      <!-- NI Information -->
      <div class="details-section">
        <h3 class="section-title">National Insurance Information</h3>
        <div class="details-grid">
          <div class="detail-item">
            <span class="detail-label">Minimum Years Required</span>
            <span class="detail-value">10 years</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Full Pension Years</span>
            <span class="detail-value">35 years</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Current Weekly Entitlement</span>
            <span class="detail-value">{{ formatCurrency((pension.state_pension_forecast_annual || 0) / 52) }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Full Weekly Amount (2025/26)</span>
            <span class="detail-value">{{ formatCurrency(230.25) }}</span>
          </div>
        </div>
      </div>

      <!-- Voluntary Contributions Info -->
      <div class="voluntary-info">
        <h4>Voluntary Contributions</h4>
        <p>If you have gaps in your NI record, you may be able to make voluntary contributions to boost your State Pension. This can be a cost-effective way to increase your retirement income.</p>
        <div class="voluntary-calculation">
          <span class="calc-label">Cost per year of NI credit:</span>
          <span class="calc-value">Approximately {{ formatCurrency(824) }}/year</span>
        </div>
        <div class="voluntary-calculation">
          <span class="calc-label">Potential pension increase:</span>
          <span class="calc-value">Approximately {{ formatCurrency(342) }}/year ({{ formatCurrency(6.58) }}/week)</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PensionContributionsPanel',

  props: {
    pension: {
      type: Object,
      required: true,
    },
    pensionType: {
      type: String,
      required: true,
    },
  },

  computed: {
    // DC Pension calculations
    // For occupational pensions: calculate from percentage * salary
    // For SIPPs: use monthly_contribution_amount
    monthlyEmployeeContribution() {
      // If percentage-based (occupational pension)
      if (this.pension.employee_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employee_contribution_percent / 100) / 12;
      }
      // If fixed amount (SIPP)
      return this.pension.monthly_contribution_amount || 0;
    },

    annualEmployeeContribution() {
      // If percentage-based
      if (this.pension.employee_contribution_percent && this.pension.annual_salary) {
        return this.pension.annual_salary * this.pension.employee_contribution_percent / 100;
      }
      return this.monthlyEmployeeContribution * 12;
    },

    monthlyEmployerContribution() {
      // Employer contributions are always percentage-based for occupational pensions
      if (this.pension.employer_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employer_contribution_percent / 100) / 12;
      }
      return 0;
    },

    annualEmployerContribution() {
      if (this.pension.employer_contribution_percent && this.pension.annual_salary) {
        return this.pension.annual_salary * this.pension.employer_contribution_percent / 100;
      }
      return this.monthlyEmployerContribution * 12;
    },

    totalMonthlyContribution() {
      return this.monthlyEmployeeContribution + this.monthlyEmployerContribution;
    },

    totalAnnualContribution() {
      return this.annualEmployeeContribution + this.annualEmployerContribution + (this.pension.lump_sum_contribution || 0);
    },

    annualAllowancePercentage() {
      return Math.min(100, Math.round((this.totalAnnualContribution / 60000) * 100));
    },

    remainingAllowance() {
      return Math.max(0, 60000 - this.totalAnnualContribution);
    },

    allowanceBarWidth() {
      return Math.min(100, (this.totalAnnualContribution / 60000) * 100) + '%';
    },

    // DB Pension calculations
    dbMonthlyContribution() {
      if (!this.pension.employee_contribution_rate || !this.pension.pensionable_salary) return 0;
      return (this.pension.pensionable_salary * this.pension.employee_contribution_rate) / 12;
    },

    dbAnnualContribution() {
      if (!this.pension.employee_contribution_rate || !this.pension.pensionable_salary) return 0;
      return this.pension.pensionable_salary * this.pension.employee_contribution_rate;
    },

    // State Pension calculations
    yearsNeeded() {
      return Math.max(0, 35 - (this.pension.ni_years_completed || 0));
    },

    niPercentage() {
      return Math.min(100, Math.round(((this.pension.ni_years_completed || 0) / 35) * 100));
    },
  },

  methods: {
    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatDate(date) {
      if (!date) return 'Not specified';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    formatSchemeType(type) {
      const types = {
        workplace: 'Workplace Pension',
        sipp: 'Self-Invested Personal Pension (SIPP)',
        personal: 'Personal Pension',
        stakeholder: 'Stakeholder Pension',
      };
      return types[type] || type;
    },
  },
};
</script>

<style scoped>
.pension-contributions-panel {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.contributions-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.contribution-summary {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.summary-card {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 20px;
}

.summary-card.total {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: white;
}

.summary-card.total .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.summary-card.total h3 {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
}

.summary-card h4 {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.card-value {
  font-size: 28px;
  font-weight: 700;
  margin: 0;
}

.summary-card.total .card-value {
  color: white;
}

.card-sublabel {
  font-size: 14px;
  margin-top: 4px;
}

.summary-card.total .card-sublabel {
  color: rgba(255, 255, 255, 0.8);
}

.badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge-blue {
  background: rgba(255, 255, 255, 0.2);
  color: white;
}

.summary-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.summary-cards-grid.three-col {
  grid-template-columns: repeat(3, 1fr);
}

.allowance-section,
.details-section {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 24px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px 0;
}

.allowance-bar-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.allowance-info {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: #374151;
}

.allowance-bar {
  height: 24px;
  background: #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}

.allowance-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
  border-radius: 12px;
  transition: width 0.5s ease;
}

.allowance-warning {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  padding: 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #dc2626;
  font-size: 14px;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 20px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
}

.detail-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

/* DB Pension Styles */
.db-info-card,
.state-info-card {
  display: flex;
  gap: 16px;
  padding: 20px;
  background: #f3e8ff;
  border-radius: 12px;
}

.state-info-card {
  background: #d1fae5;
}

.info-icon {
  flex-shrink: 0;
}

.info-content h3 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.info-content p {
  font-size: 14px;
  color: #4b5563;
  margin: 0;
  line-height: 1.5;
}

.benefit-formula {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 20px;
  background: #f9fafb;
  border-radius: 8px;
}

.formula-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.formula-item.result {
  background: #d1fae5;
  border-color: #10b981;
}

.formula-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.formula-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.formula-operator {
  font-size: 24px;
  font-weight: 300;
  color: #9ca3af;
}

/* State Pension Styles */
.ni-progress-section {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 24px;
}

.ni-progress-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ni-progress-bar {
  position: relative;
  height: 32px;
  background: #e5e7eb;
  border-radius: 16px;
  overflow: visible;
}

.ni-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
  border-radius: 16px;
  transition: width 0.5s ease;
}

.ni-progress-markers {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 100%;
}

.marker {
  position: absolute;
  top: 100%;
  transform: translateX(-50%);
  padding-top: 8px;
}

.marker span {
  font-size: 12px;
  color: #6b7280;
}

.ni-progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: #374151;
  margin-top: 24px;
}

.voluntary-info {
  background: #eff6ff;
  border-radius: 12px;
  padding: 20px;
}

.voluntary-info h4 {
  font-size: 16px;
  font-weight: 600;
  color: #1e40af;
  margin: 0 0 8px 0;
}

.voluntary-info p {
  font-size: 14px;
  color: #1e40af;
  margin: 0 0 16px 0;
  line-height: 1.5;
}

.voluntary-calculation {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-top: 1px solid #bfdbfe;
}

.calc-label {
  font-size: 14px;
  color: #3b82f6;
}

.calc-value {
  font-size: 14px;
  font-weight: 600;
  color: #1e40af;
}

@media (max-width: 768px) {
  .summary-cards-grid,
  .summary-cards-grid.three-col {
    grid-template-columns: 1fr;
  }

  .benefit-formula {
    flex-direction: column;
  }

  .formula-operator {
    transform: rotate(90deg);
  }
}
</style>
