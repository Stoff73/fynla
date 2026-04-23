<template>
  <!-- Onboarding: inline form, no modal. Regular: full modal wrapper. -->
  <div :class="context === 'onboarding' ? '' : 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 animate-fade-in'">
    <div :class="context === 'onboarding' ? '' : 'bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto scrollbar-thin'">
      <!-- Header -->
      <div :class="context === 'onboarding' ? 'mb-4' : 'sticky top-0 bg-white border-b border-light-gray px-6 py-4 flex items-center justify-between'">
        <h3 class="text-xl font-semibold text-horizon-500">
          {{ isEdit ? 'Edit ' + editTypeLabel : 'Add Pension' }}
        </h3>
        <button
          v-if="context !== 'onboarding'"
          @click="$emit('close')"
          class="text-horizon-400 hover:text-neutral-500 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" :class="context === 'onboarding' ? '' : 'p-6'">
        <div class="space-y-6">
          <!-- Pension Type -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'pension_type' }">
            <label for="pension_type" class="block text-sm font-medium text-neutral-500 mb-2">
              Pension Type
            </label>
            <select
              id="pension_type"
              v-model="formData.pension_type"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              @change="handlePensionTypeChange"
            >
              <option value="">Select pension type...</option>
              <option value="occupational">Occupational (Workplace)</option>
              <option value="sipp">Self-Invested Personal Pension</option>
              <option value="personal">Personal Pension</option>
              <option value="stakeholder">Stakeholder Pension</option>
              <!-- Onboarding pre-scopes this form to DC only; the DB and State options
                   are for the unified "Add Pension" entry point on the retirement dashboard. -->
              <option v-if="!isOnboarding" value="final_salary">Final Salary (Defined Benefit)</option>
              <option v-if="!isOnboarding" value="state_pension">State Pension</option>
            </select>
            <p class="text-xs text-neutral-500 mt-1">
              {{ pensionTypeHelpText }}
            </p>
          </div>

          <!-- Scheme Name (DC + DB only; State is a single UK scheme) -->
          <div v-if="isDCType || isFinalSalary" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'scheme_name' }">
            <label for="scheme_name" class="block text-sm font-medium text-neutral-500 mb-2">
              {{ isFinalSalary ? 'Employer / Scheme Name' : 'Scheme Name' }}
            </label>
            <input
              id="scheme_name"
              v-model="formData.scheme_name"
              type="text"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              :placeholder="isFinalSalary ? 'e.g., NHS Pension Scheme' : 'e.g., Aviva Master Trust'"
            />
          </div>

          <!-- Provider and Policy Number (DC only) -->
          <div v-if="isDCType" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'provider' }">
              <label for="provider" class="block text-sm font-medium text-neutral-500 mb-2">
                Provider
              </label>
              <input
                id="provider"
                v-model="formData.provider"
                type="text"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., Aviva"
              />
            </div>
            <div>
              <label for="policy_number" class="block text-sm font-medium text-neutral-500 mb-2">
                Policy Number
              </label>
              <input
                id="policy_number"
                v-model="formData.policy_number"
                type="text"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., DC123456"
              />
            </div>
          </div>

          <!-- Current Fund Value (DC only) -->
          <div v-if="isDCType" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_fund_value' }">
            <label for="current_fund_value" class="block text-sm font-medium text-neutral-500 mb-2">
              Current Fund Value (£)
            </label>
            <input
              id="current_fund_value"
              v-model.number="formData.current_fund_value"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
          </div>

          <!-- Workplace Pension: Annual Salary -->
          <div v-if="isWorkplacePension">
            <label for="annual_salary" class="block text-sm font-medium text-neutral-500 mb-2">
              Annual Salary (£)
            </label>
            <input
              id="annual_salary"
              v-model.number="formData.annual_salary"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 50000.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Required to calculate percentage contributions</p>
          </div>

          <!-- Workplace Pension: Percentage Contributions -->
          <div v-if="isWorkplacePension" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'employee_contribution_percent' }">
              <label for="employee_contribution_percent" class="block text-sm font-medium text-neutral-500 mb-2">
                Employee Contribution (%)
              </label>
              <input
                id="employee_contribution_percent"
                v-model.number="formData.employee_contribution_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                :class="{ 'border-raspberry-500': validationErrors.employee_contribution_percent }"
                placeholder="e.g., 5.00"
                @blur="validateEmployeeContribution"
              />
              <p v-if="validationErrors.employee_contribution_percent" class="text-xs text-raspberry-500 mt-1">
                {{ validationErrors.employee_contribution_percent }}
              </p>
              <p v-else-if="calculatedEmployeeContribution" class="text-xs text-neutral-500 mt-1">
                = {{ formatCurrency(calculatedEmployeeContribution) }}/month
              </p>
              <p v-else class="text-xs text-neutral-500 mt-1">Enter as percentage of salary (0-100)</p>
            </div>
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'employer_contribution_percent' }">
              <label for="employer_contribution_percent" class="block text-sm font-medium text-neutral-500 mb-2">
                Employer Contribution (%)
              </label>
              <input
                id="employer_contribution_percent"
                v-model.number="formData.employer_contribution_percent"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                :class="{ 'border-raspberry-500': validationErrors.employer_contribution_percent }"
                placeholder="e.g., 3.00"
                @blur="validateEmployerContribution"
              />
              <p v-if="validationErrors.employer_contribution_percent" class="text-xs text-raspberry-500 mt-1">
                {{ validationErrors.employer_contribution_percent }}
              </p>
              <p v-else-if="calculatedEmployerContribution" class="text-xs text-neutral-500 mt-1">
                = {{ formatCurrency(calculatedEmployerContribution) }}/month
              </p>
              <p v-else class="text-xs text-neutral-500 mt-1">Enter as percentage of salary (0-100)</p>
            </div>
          </div>

          <!-- Personal/SIPP: Fixed Monthly Contribution -->
          <div v-if="isPersonalPension">
            <label for="monthly_contribution_amount" class="block text-sm font-medium text-neutral-500 mb-2">
              Monthly Contribution (£)
            </label>
            <input
              id="monthly_contribution_amount"
              v-model.number="formData.monthly_contribution_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 500.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Fixed monthly contribution amount</p>
          </div>

          <!-- Personal/SIPP: Lump Sum Contribution (Carry Forward) — collapsed by default, see "Additional information" -->
          <div v-if="isPersonalPension && showAdditionalInfo">
            <label for="lump_sum_contribution" class="block text-sm font-medium text-neutral-500 mb-2">
              Lump Sum Contribution (£) <span class="text-neutral-500 text-xs">(Optional)</span>
            </label>
            <input
              id="lump_sum_contribution"
              v-model.number="formData.lump_sum_contribution"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 10000.00"
            />
            <p class="text-xs text-neutral-500 mt-1">
              One-off lump sum payment to take advantage of carry forward allowances
            </p>
          </div>

          <!-- Expected Return (DC only) — collapsed by default, see "Additional information" -->
          <div v-if="isDCType && showAdditionalInfo">
            <label for="expected_return_percent" class="block text-sm font-medium text-neutral-500 mb-2">
              Expected Return (% p.a.)
            </label>
            <input
              id="expected_return_percent"
              v-model.number="formData.expected_return_percent"
              type="number"
              step="0.01"
              min="0"
              max="20"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 5.00"
            />
            <p class="text-xs text-neutral-500 mt-1">Typical: 4-6% for balanced funds</p>
          </div>

          <!-- Platform Fee (DC only) — collapsed by default, see "Additional information" -->
          <div v-if="isDCType && showAdditionalInfo">
            <label class="block text-sm font-medium text-neutral-500 mb-2">
              Platform Fee
            </label>
            <div class="flex gap-2">
              <div class="flex-1">
                <input
                  v-model.number="platformFeeValue"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  :placeholder="formData.platform_fee_type === 'percentage' ? 'e.g., 0.45' : 'e.g., 50.00'"
                />
              </div>
              <div class="w-20">
                <select
                  v-model="formData.platform_fee_type"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
                  <option value="percentage">%</option>
                  <option value="fixed">£</option>
                </select>
              </div>
              <div class="w-32">
                <select
                  v-model="formData.platform_fee_frequency"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                  <option value="annually">Annually</option>
                </select>
              </div>
            </div>
            <p class="text-xs text-neutral-500 mt-1">{{ feeHelpText }}</p>
          </div>

          <!-- Advisor Fee (DC only) — collapsed by default, see "Additional information" -->
          <div v-if="isDCType && showAdditionalInfo">
            <label for="advisor_fee_percent" class="block text-sm font-medium text-neutral-500 mb-2">
              Advisor Fee (% p.a.)
            </label>
            <input
              id="advisor_fee_percent"
              v-model.number="formData.advisor_fee_percent"
              type="number"
              step="0.01"
              min="0"
              max="10"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="e.g., 0.50"
            />
            <p class="text-xs text-neutral-500 mt-1">Annual advisor fee as a percentage of fund value</p>
          </div>

          <!-- Retirement Age (SIPP and personal pensions only) -->
          <div v-if="isPersonalPension">
            <label for="retirement_age" class="block text-sm font-medium text-neutral-500 mb-2">
              Retirement Age
              <span class="relative inline-block ml-1 group cursor-help">
                <svg class="w-4 h-4 inline text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-2 bg-horizon-500 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                  {{ profileRetirementAge ? `Based on your profile retirement age of ${profileRetirementAge}` : 'Default UK State Pension age. Update in your personal information.' }}
                </span>
              </span>
            </label>
            <input
              id="retirement_age"
              v-model.number="formData.retirement_age"
              type="number"
              min="55"
              max="75"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              :placeholder="profileRetirementAge ? `Default: ${profileRetirementAge}` : 'e.g., 67'"
            />
            <p class="text-xs text-neutral-500 mt-1">
              When you plan to access this pension (minimum 55).
            </p>
            <p v-if="formData.retirement_age && formData.retirement_age < 55" class="text-xs text-raspberry-500 mt-1">
              Minimum pension access age is 55
            </p>
          </div>

          <!-- Risk Level Section (DC only; hidden during onboarding) -->
          <template v-if="isDCType && !isOnboarding">
            <div v-if="hasRiskProfile" class="pt-4 border-t border-light-gray">
              <RiskLevelSelector
                v-model="formData.risk_preference"
                :allowed-levels="allowedRiskLevels"
                :profile-level="mainRiskLevel"
                :compact="true"
                :show-allocation="false"
                :show-returns="false"
                :collapsible="true"
                label="Risk Level for This Pension"
              />
              <p class="mt-2 text-xs text-neutral-500">
                Your main risk profile is <strong>{{ mainRiskLevelDisplay }}</strong>.
                You can choose a different risk level for this pension if needed.
              </p>
            </div>
            <div v-else class="pt-4 border-t border-light-gray">
              <div class="bg-savannah-100 rounded-md p-3">
                <div class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-sm text-violet-800">
                      <router-link to="/risk-profile" class="font-medium underline hover:text-violet-900">
                        Set your risk profile
                      </router-link>
                      to get personalised risk guidance for your pension investments.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Salary Sacrifice (Workplace Pensions Only) -->
          <div v-if="isWorkplacePension" class="flex items-center">
            <input
              id="salary_sacrifice"
              v-model="formData.salary_sacrifice"
              type="checkbox"
              class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
            />
            <label for="salary_sacrifice" class="ml-2 block text-sm text-neutral-500">
              Using salary sacrifice arrangement
            </label>
          </div>

          <!-- Beneficiary Section (DC only) — collapsed by default, see "Additional information" -->
          <div v-if="isDCType && showAdditionalInfo" class="space-y-4 p-4 bg-violet-50 border border-violet-200 rounded-lg">
            <p class="text-sm text-violet-800 font-medium">Beneficiary Details</p>

            <!-- Beneficiary Selection -->
            <div>
              <label for="beneficiary_selection" class="block text-sm font-medium text-neutral-500 mb-1">
                Beneficiary
              </label>
              <select
                id="beneficiary_selection"
                v-model="beneficiarySelection"
                @change="handleBeneficiarySelection"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select beneficiary...</option>
                <option v-if="spouseOption" :value="'linked_' + spouseOption.id">
                  {{ spouseOption.name }} (Spouse - Linked Account)
                </option>
                <option value="other">Other (enter name)</option>
              </select>
              <p class="text-xs text-neutral-500 mt-1">
                Who should receive this pension if you pass away?
              </p>
            </div>

            <!-- Custom Beneficiary Name (when "Other" selected) -->
            <div v-if="beneficiarySelection === 'other'">
              <label for="beneficiary_name" class="block text-sm font-medium text-neutral-500 mb-1">
                Beneficiary Name
              </label>
              <input
                id="beneficiary_name"
                v-model="formData.beneficiary_name"
                type="text"
                placeholder="Enter beneficiary's full name"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
              <p class="text-xs text-neutral-500 mt-1">
                This person doesn't have an account in the system.
              </p>
            </div>
          </div>

          <!-- Final Salary (Defined Benefit) fields. These map to the db_pensions
               payload in handleSubmit — scheme_name above doubles as employer_name. -->
          <template v-if="isFinalSalary">
            <div class="bg-savannah-100 rounded-lg p-4 flex items-start">
              <svg class="w-6 h-6 text-violet-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
              <div>
                <p class="text-sm font-bold text-violet-900">Important Notice About Defined Benefit Pensions</p>
                <p class="text-sm text-violet-800 mt-2">
                  Defined Benefit pension information is captured for <strong>income projection only</strong>.
                  This system does <strong>not provide Defined Benefit to Defined Contribution transfer advice</strong>.
                  Defined Benefit pension transfers are complex and may not be suitable.
                  You should seek specialist financial advice before considering any transfer.
                </p>
              </div>
            </div>

            <div>
              <label for="db_scheme_status" class="block text-sm font-medium text-neutral-500 mb-2">
                Scheme Status
              </label>
              <select
                id="db_scheme_status"
                v-model="formData.db_scheme_status"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select status</option>
                <option value="Active">Active</option>
                <option value="Deferred">Deferred</option>
                <option value="In Payment">In Payment</option>
              </select>
            </div>

            <div>
              <label for="db_annual_income" class="block text-sm font-medium text-neutral-500 mb-2">
                Annual Income at Retirement (£)
              </label>
              <input
                id="db_annual_income"
                v-model.number="formData.db_annual_income"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 15000.00"
              />
              <p class="text-xs text-neutral-500 mt-1">Projected annual pension at your normal retirement age</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="db_service_years" class="block text-sm font-medium text-neutral-500 mb-2">
                  Service Years
                </label>
                <input
                  id="db_service_years"
                  v-model.number="formData.db_service_years"
                  type="number"
                  step="0.1"
                  min="0"
                  class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="e.g., 20.5"
                />
              </div>
              <div>
                <label for="db_final_salary" class="block text-sm font-medium text-neutral-500 mb-2">
                  Pensionable Salary (£)
                </label>
                <input
                  id="db_final_salary"
                  v-model.number="formData.db_final_salary"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="e.g., 50000.00"
                />
              </div>
            </div>

            <div>
              <label for="db_accrual_rate" class="block text-sm font-medium text-neutral-500 mb-2">
                Accrual Rate (1/X)
              </label>
              <input
                id="db_accrual_rate"
                v-model.number="formData.db_accrual_rate"
                type="number"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 60 (for 1/60th)"
              />
              <p class="text-xs text-neutral-500 mt-1">Common: 60 (public sector), 80 (older schemes)</p>
            </div>

            <div>
              <label for="db_revaluation_rate" class="block text-sm font-medium text-neutral-500 mb-2">
                Revaluation Rate (% p.a.)
              </label>
              <input
                id="db_revaluation_rate"
                v-model.number="formData.db_revaluation_rate"
                type="number"
                step="0.01"
                min="0"
                max="10"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 2.50"
              />
              <p class="text-xs text-neutral-500 mt-1">Typical: CPI, CPI+1.5%, or fixed %</p>
            </div>

            <div>
              <label for="db_pcls_available" class="block text-sm font-medium text-neutral-500 mb-2">
                Pension Commencement Lump Sum (PCLS) Available (£)
              </label>
              <input
                id="db_pcls_available"
                v-model.number="formData.db_pcls_available"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 50000.00"
              />
              <p class="text-xs text-neutral-500 mt-1">Tax-free lump sum available at retirement (if applicable)</p>
            </div>
          </template>

          <!-- State Pension fields. These map to the state_pensions payload in handleSubmit. -->
          <template v-if="isStatePension">
            <div class="bg-savannah-100 rounded-lg p-4 flex items-start">
              <svg class="w-5 h-5 text-violet-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <p class="text-sm font-medium text-violet-900">Get Your State Pension Forecast</p>
                <p class="text-sm text-violet-800 mt-1">
                  Check your State Pension forecast at
                  <a href="https://www.gov.uk/check-state-pension" target="_blank" class="underline font-medium">gov.uk/check-state-pension</a>
                </p>
              </div>
            </div>

            <div>
              <label for="state_forecast_weekly" class="block text-sm font-medium text-neutral-500 mb-2">
                Forecast Weekly Amount (£)
              </label>
              <input
                id="state_forecast_weekly"
                v-model.number="formData.state_forecast_weekly_amount"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 203.85"
              />
              <p class="text-xs text-neutral-500 mt-1">
                Full new State Pension ({{ currentTaxYear }}): £221.20/week (£11,502/year)
              </p>
            </div>

            <div>
              <label for="state_qualifying_years" class="block text-sm font-medium text-neutral-500 mb-2">
                Qualifying Years
              </label>
              <input
                id="state_qualifying_years"
                v-model.number="formData.state_qualifying_years"
                type="number"
                min="0"
                max="50"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 25"
              />
              <p class="text-xs text-neutral-500 mt-1">You need 35 qualifying years for the full new State Pension</p>
            </div>

            <div>
              <label for="state_forecast_date" class="block text-sm font-medium text-neutral-500 mb-2">
                Forecast Date
              </label>
              <input
                id="state_forecast_date"
                v-model="formData.state_forecast_date"
                type="date"
                class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
              <p class="text-xs text-neutral-500 mt-1">When did you check your forecast?</p>
            </div>

            <div class="flex items-start">
              <input
                id="state_has_ni_gaps"
                v-model="formData.state_has_ni_gaps"
                type="checkbox"
                class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded mt-1"
              />
              <label for="state_has_ni_gaps" class="ml-2 block text-sm text-neutral-500">
                I have National Insurance gaps that can be filled
              </label>
            </div>

            <div v-if="formData.state_has_ni_gaps" class="pl-6 space-y-4">
              <div>
                <label for="state_gaps_years" class="block text-sm font-medium text-neutral-500 mb-2">
                  Number of Gap Years
                </label>
                <input
                  id="state_gaps_years"
                  v-model.number="formData.state_gaps_years"
                  type="number"
                  min="0"
                  max="20"
                  class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="e.g., 3"
                />
              </div>
              <div>
                <label for="state_estimated_gap_cost" class="block text-sm font-medium text-neutral-500 mb-2">
                  Estimated Cost to Fill Gaps (£)
                </label>
                <input
                  id="state_estimated_gap_cost"
                  v-model.number="formData.state_estimated_gap_cost"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="e.g., 2500.00"
                />
                <p class="text-xs text-neutral-500 mt-1">Typical cost: ~£800-900 per year ({{ currentTaxYear }})</p>
              </div>
            </div>
          </template>

          <!-- Additional information toggle (DC only; DB/State forms have no hidden fields) -->
          <div v-if="isDCType" class="pt-2">
            <button
              type="button"
              @click="showAdditionalInfo = !showAdditionalInfo"
              class="inline-flex items-center gap-1 text-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
            >
              {{ showAdditionalInfo ? 'Hide' : 'Show' }} additional information
              <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': showAdditionalInfo }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <p v-if="!showAdditionalInfo" class="mt-1 text-xs text-neutral-500">
              Fees, expected return, lump sum contribution, beneficiary and holdings
            </p>
          </div>

          <!-- Notes (all types) -->
          <div>
            <label for="notes" class="block text-sm font-medium text-neutral-500 mb-2">
              Notes
            </label>
            <textarea
              id="notes"
              v-model="formData.notes"
              rows="3"
              class="w-full px-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              placeholder="Any additional notes about this pension..."
            ></textarea>
          </div>

          <!-- Inline Holdings Editor (DC only) — collapsed by default, see "Additional information" -->
          <InlineHoldingsEditor
            v-if="isDCType && showAdditionalInfo && parseFloat(formData.current_fund_value) > 0"
            :account-value="parseFloat(formData.current_fund_value) || 0"
            :holdings="formData.holdings"
            :account-id="pension?.id || null"
            @update:holdings="formData.holdings = $event"
          />
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-light-gray">
          <p v-if="validationError" class="text-sm text-raspberry-500 mt-2 mr-auto">{{ validationError }}</p>
          <button
            type="button"
            @click="$emit('close')"
            :class="context === 'onboarding'
              ? 'px-4 py-2 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 rounded-lg transition-colors duration-200 text-sm font-medium'
              : 'px-4 py-2 text-neutral-500 bg-white border border-horizon-300 rounded-lg hover:bg-savannah-100 transition-colors duration-200'"
          >
            Cancel
          </button>
          <button
            type="submit"
            :class="context === 'onboarding'
              ? 'px-6 py-2 bg-raspberry-500 text-white rounded-lg hover:bg-raspberry-600 transition-colors duration-200 text-sm font-medium'
              : 'px-6 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors duration-200'"
          >
            {{ context === 'onboarding' ? 'Save' : (isEdit ? 'Update' : 'Add') + ' Pension' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import RiskLevelSelector from '@/components/Shared/RiskLevelSelector.vue';
import InlineHoldingsEditor from '@/components/Investment/InlineHoldingsEditor.vue';
import riskService from '@/services/riskService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

export default {
  name: 'DCPensionForm',

  emits: ['save', 'close'],

  mixins: [currencyMixin],

  components: {
    RiskLevelSelector,
    InlineHoldingsEditor,
  },

  props: {
    pension: {
      type: Object,
      default: null,
    },
    isOnboarding: {
      type: Boolean,
      default: false,
    },
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
    },
  },

  data() {
    return {
      formData: {
        pension_type: '',
        scheme_type: '', // Keep for backward compatibility
        scheme_name: '',
        provider: '',
        policy_number: '',
        current_fund_value: null,
        annual_salary: null,
        employee_contribution_percent: null,
        employer_contribution_percent: null,
        monthly_contribution_amount: null,
        lump_sum_contribution: null,
        expected_return_percent: null,
        platform_fee_type: 'percentage',
        platform_fee_amount: null,
        platform_fee_frequency: 'annually',
        advisor_fee_percent: null,
        retirement_age: null,
        salary_sacrifice: false,
        notes: '',
        risk_preference: null,
        beneficiary_id: null,
        beneficiary_name: '',
        holdings: [],
        // Defined Benefit (Final Salary) fields — only populated when pension_type === 'final_salary'.
        // Mapped to the db_pensions backend payload in handleSubmit.
        db_scheme_status: '',
        db_annual_income: null,
        db_service_years: null,
        db_final_salary: null,
        db_accrual_rate: null,
        db_revaluation_rate: null,
        db_pcls_available: null,
        // State Pension fields — only populated when pension_type === 'state_pension'.
        // Mapped to the state_pensions backend payload in handleSubmit.
        state_forecast_weekly_amount: null,
        state_qualifying_years: null,
        state_forecast_date: null,
        state_has_ni_gaps: false,
        state_gaps_years: null,
        state_estimated_gap_cost: null,
      },
      validationError: null,
      validationErrors: {
        employee_contribution_percent: '',
        employer_contribution_percent: '',
      },
      // Risk profile state
      mainRiskLevel: null,
      allowedRiskLevels: ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
      // Beneficiary state
      beneficiarySelection: '',
      // Collapsed "Additional information" section (fees, expected return, lump sum,
      // beneficiary, holdings). Auto-expands in edit mode when any hidden field has a value.
      showAdditionalInfo: false,
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),
    ...mapGetters('auth', ['currentUser']),

    isEdit() {
      return !!this.pension;
    },

    hasRiskProfile() {
      return !!this.mainRiskLevel;
    },

    mainRiskLevelDisplay() {
      return riskService.getDisplayName(this.mainRiskLevel);
    },

    isWorkplacePension() {
      return this.formData.pension_type === 'occupational';
    },

    isPersonalPension() {
      return this.formData.pension_type === 'sipp' || this.formData.pension_type === 'personal' || this.formData.pension_type === 'stakeholder';
    },

    // True for any defined-contribution sub-type. Controls visibility of the DC-only
    // sections (fund value, fees, holdings, risk level, etc.).
    isDCType() {
      return this.isWorkplacePension || this.isPersonalPension;
    },

    isFinalSalary() {
      return this.formData.pension_type === 'final_salary';
    },

    isStatePension() {
      return this.formData.pension_type === 'state_pension';
    },

    editTypeLabel() {
      if (this.isFinalSalary) return 'Final Salary Pension';
      if (this.isStatePension) return 'State Pension';
      return 'Money Purchase Pension';
    },

    pensionTypeHelpText() {
      if (this.isFinalSalary) return 'Defined Benefit scheme — captured for income projection only.';
      if (this.isStatePension) return 'UK State Pension — based on your National Insurance record.';
      return 'Workplace pensions use % of salary contributions. Personal pensions use fixed monthly amounts.';
    },

    currentTaxYear() {
      return getCurrentTaxYear();
    },

    profileRetirementAge() {
      return this.$store.state.userProfile?.incomeOccupation?.target_retirement_age
        || this.$store.getters['auth/user']?.target_retirement_age
        || null;
    },

    platformFeeValue: {
      get() {
        return this.formData.platform_fee_type === 'percentage'
          ? this.formData.platform_fee_percent
          : this.formData.platform_fee_amount;
      },
      set(value) {
        if (this.formData.platform_fee_type === 'percentage') {
          this.formData.platform_fee_percent = value;
          this.formData.platform_fee_amount = null;
        } else {
          this.formData.platform_fee_amount = value;
          this.formData.platform_fee_percent = null;
        }
      },
    },

    feeHelpText() {
      const frequencyText = { monthly: 'per month', quarterly: 'per quarter', annually: 'per year' };
      const freq = frequencyText[this.formData.platform_fee_frequency] || 'per year';
      if (this.formData.platform_fee_type === 'percentage') {
        return `Platform fee as a percentage of assets ${freq}`;
      }
      return `Fixed platform fee charged ${freq}`;
    },

    calculatedEmployeeContribution() {
      if (!this.isWorkplacePension || !this.formData.annual_salary || !this.formData.employee_contribution_percent) {
        return null;
      }
      return Math.round((this.formData.annual_salary * this.formData.employee_contribution_percent / 100) / 12);
    },

    calculatedEmployerContribution() {
      if (!this.isWorkplacePension || !this.formData.annual_salary || !this.formData.employer_contribution_percent) {
        return null;
      }
      return Math.round((this.formData.annual_salary * this.formData.employer_contribution_percent / 100) / 12);
    },

    spouseOption() {
      const spouse = this.$store.getters['userProfile/spouse'];
      return spouse ? { id: spouse.id, name: spouse.name } : null;
    },
  },

  watch: {
    pension: {
      immediate: true,
      handler(newPension) {
        if (newPension) {
          // Editing existing pension - populate form with pension data while
          // preserving db_* / state_* defaults so v-model bindings stay reactive
          // if the user later switches the dropdown to a non-DC type.
          this.formData = {
            ...this.formData,
            ...newPension,
            policy_number: newPension.member_number || '',
            risk_preference: newPension.risk_preference || null,
            beneficiary_id: newPension.beneficiary_id || null,
            beneficiary_name: newPension.beneficiary_name || '',
            holdings: [],
          };
          // Load existing holdings for edit mode (filter out auto-created cash)
          if (newPension.holdings?.length) {
            this.formData.holdings = newPension.holdings
              .filter(h => h.asset_type !== 'cash')
              .map(h => ({
                security_name: h.security_name,
                asset_type: h.asset_type,
                allocation_percent: h.allocation_percent,
                ocf_percent: h.ocf_percent,
                cost_basis: h.cost_basis,
              }));
          }
          // Auto-expand "Additional information" if any of the collapsed-by-default
          // fields already have a value, so editors see their own data on open.
          this.showAdditionalInfo = this.hasAdditionalInfoData();
          this.$nextTick(() => {
            this.initializeBeneficiarySelection();
          });
        }
      },
    },
    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'dc_pension' && fill.fields) {
          // Set pension_type immediately — it controls which conditional fields render
          // Also set scheme_type which is what validation checks (handlePensionTypeChange syncs these normally)
          if (fill.fields.pension_type) {
            this.formData.pension_type = fill.fields.pension_type;
            this.handlePensionTypeChange();
          }
          // Pre-set scheme_name — required for validation, may come from provider fallback
          if (fill.fields.scheme_name) {
            this.formData.scheme_name = fill.fields.scheme_name;
          } else if (fill.fields.provider) {
            this.formData.scheme_name = fill.fields.provider;
          }
          const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
          this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
        }
      },
      immediate: true,
    },
    highlightedField(fieldKey) {
      if (fieldKey && this.pendingFill?.fields) {
        const value = this.pendingFill.fields[fieldKey];
        if (value !== undefined && value !== null) {
          this.formData[fieldKey] = value;
        }
      }
    },
    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'dc_pension') {
        setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
    },
  },

  async mounted() {
    // Load risk profile when component mounts (auto-calculated if none exists)
    await this.loadRiskProfile();

    // Always re-fetch user profile to get the latest retirement age
    // (user may have just entered it in the Income step during onboarding)
    await this.$store.dispatch('userProfile/fetchProfile').catch(() => {});

    // Default retirement age from user profile for all new pensions
    if (!this.isEdit && !this.formData.retirement_age && this.profileRetirementAge) {
      this.formData.retirement_age = this.profileRetirementAge;
    }
  },

  methods: {
    async loadRiskProfile() {
      try {
        const [profileResponse, allowedResponse] = await Promise.all([
          riskService.getProfile(),
          riskService.getAllowedLevels(),
        ]);

        if (profileResponse.data?.risk_level) {
          this.mainRiskLevel = profileResponse.data.risk_level;
        }

        if (allowedResponse.data?.allowed_levels) {
          this.allowedRiskLevels = allowedResponse.data.allowed_levels;
        }
      } catch (error) {
        // Silently fail - risk profile is optional
      }
    },

    handlePensionTypeChange() {
      // Clear fields that don't apply to the selected pension type
      if (this.isWorkplacePension) {
        this.formData.monthly_contribution_amount = null;
      } else {
        this.formData.annual_salary = null;
        this.formData.employee_contribution_percent = null;
        this.formData.employer_contribution_percent = null;
      }

      // When switching away from DC, clear DC-only numeric fields so they can't
      // leak into a DB/State payload by accident.
      if (!this.isDCType) {
        this.formData.current_fund_value = null;
        this.formData.monthly_contribution_amount = null;
        this.formData.lump_sum_contribution = null;
        this.formData.holdings = [];
      }

      // Default retirement age from user profile (for DC personal pensions that
      // render the retirement-age input)
      if (!this.formData.retirement_age && this.profileRetirementAge) {
        this.formData.retirement_age = this.profileRetirementAge;
      }

      // Set scheme_type for backward compatibility with existing DC validation
      if (this.formData.pension_type === 'occupational') {
        this.formData.scheme_type = 'workplace';
      } else if (this.formData.pension_type === 'stakeholder') {
        // Stakeholder pensions map to 'personal' scheme_type
        // (stakeholder is a regulated type of personal pension in UK)
        this.formData.scheme_type = 'personal';
      } else if (this.isPersonalPension || this.formData.pension_type === 'sipp' || this.formData.pension_type === 'personal') {
        this.formData.scheme_type = this.formData.pension_type;
      } else {
        // Final Salary / State Pension don't use DC's scheme_type — clear it so
        // the DC validator below doesn't trip when the user is on a non-DC branch
        this.formData.scheme_type = '';
      }
    },

    // Returns true if any of the "Additional information" fields have a
    // user-provided value, so the section should auto-expand on open.
    hasAdditionalInfoData() {
      const f = this.formData;
      return !!(
        (f.lump_sum_contribution !== null && f.lump_sum_contribution !== '' && f.lump_sum_contribution !== 0)
        || (f.expected_return_percent !== null && f.expected_return_percent !== '')
        || (f.platform_fee_percent !== null && f.platform_fee_percent !== '')
        || (f.platform_fee_amount !== null && f.platform_fee_amount !== '')
        || (f.advisor_fee_percent !== null && f.advisor_fee_percent !== '')
        || f.beneficiary_id
        || (f.beneficiary_name && f.beneficiary_name.trim() !== '')
        || (f.holdings && f.holdings.length > 0)
      );
    },

    handleBeneficiarySelection() {
      if (this.beneficiarySelection.startsWith('linked_')) {
        // Linked spouse selected
        const id = parseInt(this.beneficiarySelection.replace('linked_', ''));
        this.formData.beneficiary_id = id;
        this.formData.beneficiary_name = this.spouseOption?.name || '';
      } else if (this.beneficiarySelection === 'other') {
        // Custom beneficiary
        this.formData.beneficiary_id = null;
        this.formData.beneficiary_name = '';
      } else {
        // No beneficiary selected
        this.formData.beneficiary_id = null;
        this.formData.beneficiary_name = '';
      }
    },

    initializeBeneficiarySelection() {
      // Set beneficiarySelection based on existing data
      if (this.formData.beneficiary_id && this.spouseOption && this.formData.beneficiary_id === this.spouseOption.id) {
        this.beneficiarySelection = `linked_${this.formData.beneficiary_id}`;
      } else if (this.formData.beneficiary_name) {
        this.beneficiarySelection = 'other';
      } else {
        this.beneficiarySelection = '';
      }
    },

    validateEmployeeContribution() {
      this.validationErrors.employee_contribution_percent = '';
      const value = this.formData.employee_contribution_percent;

      if (value !== null && value !== '') {
        if (value < 0) {
          this.validationErrors.employee_contribution_percent = 'Cannot be negative';
        } else if (value > 100) {
          this.validationErrors.employee_contribution_percent = 'Cannot exceed 100%';
        }
      }
    },

    validateEmployerContribution() {
      this.validationErrors.employer_contribution_percent = '';
      const value = this.formData.employer_contribution_percent;

      if (value !== null && value !== '') {
        if (value < 0) {
          this.validationErrors.employer_contribution_percent = 'Cannot be negative';
        } else if (value > 100) {
          this.validationErrors.employer_contribution_percent = 'Cannot exceed 100%';
        }
      }
    },

    handleSubmit() {
      this.validationError = null;

      if (!this.formData.pension_type) {
        this.validationError = 'Please select a pension type';
        return;
      }

      if (this.isFinalSalary) {
        this.submitFinalSalary();
        return;
      }

      if (this.isStatePension) {
        this.submitStatePension();
        return;
      }

      // --- DC (Money Purchase) submission ---
      if (this.isWorkplacePension) {
        this.validateEmployeeContribution();
        this.validateEmployerContribution();

        if (this.validationErrors.employee_contribution_percent || this.validationErrors.employer_contribution_percent) {
          return;
        }
      }

      if (!this.formData.scheme_type) {
        this.validationError = 'Please select a pension type';
        return;
      }

      if (!this.formData.scheme_name) {
        this.validationError = 'Please enter a scheme name';
        return;
      }

      if (!this.formData.current_fund_value || this.formData.current_fund_value < 0) {
        this.validationError = 'Please enter a valid current fund value';
        return;
      }

      if (this.isPersonalPension && this.formData.retirement_age && this.formData.retirement_age < 55) {
        this.validationError = 'Minimum pension access age is 55';
        return;
      }

      // Build a DC-only payload — strip the db_* and state_* fields so they
      // can't leak into dc_pensions records.
      const payload = this.buildDCPayload();
      this.$emit('save', { ...payload, _pensionType: 'dc' });
    },

    // Strips DB/State scratch fields and renames policy_number -> member_number
    // (the column the backend expects for dc_pensions).
    buildDCPayload() {
      const {
        db_scheme_status, db_annual_income, db_service_years, db_final_salary,
        db_accrual_rate, db_revaluation_rate, db_pcls_available,
        state_forecast_weekly_amount, state_qualifying_years, state_forecast_date,
        state_has_ni_gaps, state_gaps_years, state_estimated_gap_cost,
        policy_number,
        ...dcFields
      } = this.formData;

      const payload = { ...dcFields };
      if (policy_number !== undefined && policy_number !== null && policy_number !== '') {
        payload.member_number = policy_number;
      }

      // If the "Additional information" section is collapsed, clear its values
      // so the backend never stores fees/returns/lump sum/beneficiary/holdings the
      // user can't see on the form.
      if (!this.showAdditionalInfo) {
        payload.lump_sum_contribution = null;
        payload.expected_return_percent = null;
        payload.platform_fee_percent = null;
        payload.platform_fee_amount = null;
        payload.advisor_fee_percent = null;
        payload.beneficiary_id = null;
        payload.beneficiary_name = '';
        payload.holdings = [];
      }

      return payload;
    },

    submitFinalSalary() {
      if (!this.formData.scheme_name) {
        this.validationError = 'Please enter an employer / scheme name';
        return;
      }
      if (!this.formData.db_scheme_status) {
        this.validationError = 'Please select a scheme status';
        return;
      }
      if (this.formData.db_annual_income === null || this.formData.db_annual_income === '' || this.formData.db_annual_income < 0) {
        this.validationError = 'Please enter a valid annual income at retirement';
        return;
      }
      if (this.formData.db_service_years === null || this.formData.db_service_years === '' || this.formData.db_service_years < 0) {
        this.validationError = 'Please enter valid service years';
        return;
      }

      // Shape mirrors DBPensionForm.handleSubmit so db_pensions records are
      // identical whether captured via this unified form or the legacy edit form.
      const apiData = {
        scheme_name: this.formData.scheme_name,
        scheme_type: 'final_salary',
        accrued_annual_pension: this.formData.db_annual_income,
        pensionable_service_years: this.formData.db_service_years,
        pensionable_salary: this.formData.db_final_salary,
        revaluation_method: this.formData.db_revaluation_rate ? `${this.formData.db_revaluation_rate}%` : null,
        lump_sum_entitlement: this.formData.db_pcls_available,
        notes: this.formData.notes,
      };

      this.$emit('save', { ...apiData, _pensionType: 'db' });
    },

    submitStatePension() {
      if (!this.formData.state_forecast_weekly_amount || this.formData.state_forecast_weekly_amount < 0) {
        this.validationError = 'Please enter a valid forecast weekly amount';
        return;
      }
      if (!this.formData.state_qualifying_years || this.formData.state_qualifying_years < 0) {
        this.validationError = 'Please enter valid qualifying years';
        return;
      }

      // Shape mirrors StatePensionForm.handleSubmit — annual figure derived from
      // the weekly forecast, ni_gaps encoded as a placeholder array the backend
      // already understands.
      const apiData = {
        ni_years_completed: this.formData.state_qualifying_years,
        ni_years_required: 35,
        state_pension_forecast_annual: this.formData.state_forecast_weekly_amount * 52,
        ni_gaps: this.formData.state_has_ni_gaps && this.formData.state_gaps_years
          ? Array(this.formData.state_gaps_years).fill({ year: 'Unknown', cost: 0 })
          : null,
        gap_fill_cost: this.formData.state_has_ni_gaps ? this.formData.state_estimated_gap_cost : null,
      };

      this.$emit('save', { ...apiData, _pensionType: 'state' });
    },
  },
};
</script>

