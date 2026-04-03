<template>
  <!-- Onboarding: inline form, no modal. Regular: full modal wrapper. -->
  <div :class="context === 'onboarding' ? '' : 'fixed inset-0 z-50 overflow-y-auto'" :aria-labelledby="context === 'onboarding' ? undefined : 'modal-title'" :role="context === 'onboarding' ? undefined : 'dialog'" :aria-modal="context === 'onboarding' ? undefined : 'true'">
    <!-- Background overlay (modal only) -->
    <div
      v-if="context !== 'onboarding'"
      class="fixed inset-0 bg-black/50 transition-opacity"
    ></div>

    <!-- Modal container / inline container -->
    <div :class="context === 'onboarding' ? '' : 'flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0'">
      <span v-if="context !== 'onboarding'" class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Panel -->
      <div
        :class="context === 'onboarding' ? '' : 'relative inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto scrollbar-thin'"
      >
        <!-- Header -->
        <div :class="context === 'onboarding' ? '' : 'bg-white px-6 pt-6'">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-horizon-500">
              {{ isEditing ? 'Edit Account' : 'Add Account' }}
            </h3>
            <button
              v-if="context !== 'onboarding'"
              @click="handleClose"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" :class="context === 'onboarding' ? '' : 'px-6 pb-6'">
          <div class="space-y-4 pr-2">
            <!-- Institution -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'institution' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Institution
              </label>
              <input
                v-model="formData.institution"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., Halifax, Barclays, Marcus"
              />
            </div>

            <!-- Account Type -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'account_type' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Product Type
              </label>
              <select
                v-model="formData.account_type"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="">Select product type...</option>
                <!-- Stage-prioritised types shown first when in onboarding -->
                <optgroup v-if="context === 'onboarding' && stageDefaultTypes.length" label="Most common for you">
                  <option v-if="stageDefaultTypes.includes('savings_account')" value="savings_account">Savings Account</option>
                  <option v-if="stageDefaultTypes.includes('current_account')" value="current_account">Current Account</option>
                  <option v-if="stageDefaultTypes.includes('easy_access')" value="easy_access">Easy Access</option>
                  <option v-if="stageDefaultTypes.includes('instant_access')" value="instant_access">Instant Access</option>
                  <option v-if="stageDefaultTypes.includes('notice')" value="notice">Notice Account</option>
                  <option v-if="stageDefaultTypes.includes('fixed')" value="fixed">Fixed Term</option>
                  <option v-if="stageDefaultTypes.includes('cash_isa')" value="cash_isa">Cash ISA</option>
                  <option v-if="stageDefaultTypes.includes('junior_isa')" value="junior_isa">Junior ISA</option>
                  <option v-if="stageDefaultTypes.includes('lifetime_isa')" value="lifetime_isa">Lifetime ISA</option>
                  <option v-if="stageDefaultTypes.includes('fixed_rate')" value="fixed">Fixed Rate</option>
                  <option v-if="stageDefaultTypes.includes('premium_bonds')" value="premium_bonds">Premium Bonds</option>
                  <option v-if="stageDefaultTypes.includes('nsi')" value="nsi">NS&I Savings</option>
                </optgroup>
                <optgroup label="Bank Accounts">
                  <option value="savings_account">Savings Account</option>
                  <option value="current_account">Current Account</option>
                  <option value="easy_access">Easy Access</option>
                  <option value="instant_access">Instant Access</option>
                  <option value="notice">Notice Account</option>
                  <option value="fixed">Fixed Term</option>
                </optgroup>
                <optgroup label="ISAs">
                  <option value="cash_isa">Cash ISA</option>
                  <option value="junior_isa">Junior ISA</option>
                </optgroup>
                <optgroup label="NS&I Products">
                  <option value="premium_bonds">Premium Bonds</option>
                  <option value="nsi">NS&I Savings</option>
                </optgroup>
              </select>
            </div>

            <!-- Current Balance -->
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_balance' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Current Balance
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  v-model.number="formData.current_balance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full pl-8 pr-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Interest Rate (hidden for NS&I products) -->
            <div v-if="!isNSIProductType" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'interest_rate' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Interest Rate
              </label>
              <div class="relative">
                <input
                  v-model.number="formData.interest_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="20"
                  class="w-full pr-8 pl-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  placeholder="0.00"
                />
                <span class="absolute right-3 top-2.5 text-neutral-500">%</span>
              </div>
              <p class="text-xs text-neutral-500 mt-1">
                Enter as percentage (e.g., 5.0 for 5%). Maximum 20%.
              </p>
            </div>

            <!-- Access Type (hidden for NS&I products) -->
            <div v-if="!isNSIProductType" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'access_type' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Access Type
              </label>
              <select
                v-model="formData.access_type"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              >
                <option value="immediate">Immediate</option>
                <option value="notice">Notice Required</option>
                <option value="fixed">Fixed Term</option>
              </select>
            </div>

            <!-- Notice Period (if access_type is notice, hidden for NS&I) -->
            <div v-if="!isNSIProductType && formData.access_type === 'notice'">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Notice Period (days)
              </label>
              <input
                v-model.number="formData.notice_period_days"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., 30, 60, 90"
              />
            </div>

            <!-- Maturity Date (if access_type is fixed, hidden for NS&I) -->
            <div v-if="!isNSIProductType && formData.access_type === 'fixed'">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Maturity Date
              </label>
              <input
                v-model="formData.maturity_date"
                type="date"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
            </div>

            <!-- Emergency Fund Status (hidden for NS&I products) -->
            <div v-if="!isNSIProductType" :class="[shouldHighlightEmergencyFund ? 'p-4 bg-spring-50 border border-spring-200 rounded-lg' : '', { 'ai-fill-highlight rounded-lg': highlightedField === 'is_emergency_fund' }]">
              <p v-if="shouldHighlightEmergencyFund" class="text-sm font-medium text-spring-700 mb-2">
                Building an emergency fund is a great first step
              </p>
              <div class="flex items-center">
                <input
                  v-model="formData.is_emergency_fund"
                  type="checkbox"
                  id="is_emergency_fund"
                  class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="is_emergency_fund" class="ml-2 block text-sm text-neutral-500">
                  This forms part of my emergency fund
                </label>
              </div>
            </div>

            <!-- ISA Status (hidden when product type is already ISA or NS&I) -->
            <div v-if="!isISAProductType && !isNSIProductType" :class="['flex items-center', { 'ai-fill-highlight rounded-lg p-2': highlightedField === 'is_isa' }]">
              <input
                v-model="formData.is_isa"
                type="checkbox"
                id="is_isa"
                class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
              />
              <label for="is_isa" class="ml-2 block text-sm text-neutral-500">
                This is a tax-free savings account (ISA)
              </label>
            </div>

            <!-- Country Selector (hidden for ISAs and NS&I - UK only) -->
            <div v-if="!formData.is_isa && !isISAProductType && !isNSIProductType">
              <label for="country" class="block text-sm font-medium text-neutral-500 mb-1">
                Account Country
              </label>
              <CountrySelector
                v-model="formData.country"
                placeholder="Select country where account is held"
                id="country"
              />
              <p class="text-sm text-neutral-500 mt-1">Country where the savings account is held</p>
            </div>

            <!-- ISA Details (if is_isa is true or ISA product type selected) -->
            <div v-if="formData.is_isa || isISAProductType" class="space-y-4 p-4 bg-violet-50 border border-violet-200 rounded-lg">
              <!-- ISA Header -->
              <div class="flex items-start gap-2 mb-2">
                <svg class="h-5 w-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-violet-900">ISA Subscription</p>
                  <p class="text-xs text-violet-700 mt-1">
                    All ISA contributions (Cash ISA + Stocks &amp; Shares ISA) count towards your £{{ isJuniorISA ? '9,000' : '20,000' }} annual allowance ({{ currentTaxYear }})
                  </p>
                </div>
              </div>

              <!-- Junior ISA Beneficiary (only for Junior ISA) -->
              <div v-if="isJuniorISA">
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Beneficiary (Child)
                </label>
                <select
                  v-model="formData.beneficiary_id"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  @change="handleBeneficiaryChange"
                >
                  <option value="">Select beneficiary...</option>
                  <option
                    v-for="child in eligibleChildren"
                    :key="child.id"
                    :value="child.id"
                  >
                    {{ child.first_name }} {{ child.last_name }} ({{ formatRelationship(child.relationship) }})
                  </option>
                  <option value="other">Other (enter name)</option>
                </select>
                <p class="text-xs text-neutral-500 mt-1">
                  Junior ISAs are for children under 18. The child owns the account but cannot access it until age 18.
                </p>
                <!-- Age 16-17 guidance -->
                <div v-if="isBeneficiary16Or17" class="mt-2 p-2 bg-violet-50 border border-violet-200 rounded text-xs text-violet-800">
                  <strong>Note:</strong> At 16-17, they can also open their own adult Cash ISA and Stocks & Shares ISA (£20,000 total) which they can access anytime. Combined with the Junior ISA (£9,000), they could save up to £29,000 per year.
                </div>
              </div>

              <!-- Custom Beneficiary Details (if "Other" selected) -->
              <div v-if="isJuniorISA && formData.beneficiary_id === 'other'" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">
                    Beneficiary Name
                  </label>
                  <input
                    v-model="formData.beneficiary_name"
                    type="text"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    placeholder="Enter child's full name"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">
                    Date of Birth
                  </label>
                  <input
                    v-model="formData.beneficiary_dob"
                    type="date"
                    :max="maxBeneficiaryDob"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  />
                  <p class="text-xs text-neutral-500 mt-1">Required to show correct ISA guidance</p>
                </div>
                <!-- Age 16-17 guidance for "Other" beneficiary -->
                <div v-if="isOtherBeneficiary16Or17" class="p-2 bg-violet-50 border border-violet-200 rounded text-xs text-violet-800">
                  <strong>Note:</strong> At 16-17, they can also open their own adult Cash ISA and Stocks & Shares ISA (£20,000 total) which they can access anytime. Combined with the Junior ISA (£9,000), they could save up to £29,000 per year.
                </div>
              </div>

              <!-- ISA Subscription Year -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Tax Year
                </label>
                <select
                  v-model="formData.isa_subscription_year"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
                  <option value="">Select tax year...</option>
                  <option v-for="year in taxYearOptions" :key="year" :value="year">{{ year }}</option>
                </select>
              </div>

              <!-- ISA Subscription Amount -->
              <div>
                <label class="block text-sm font-medium text-violet-900 mb-1">
                  Already Subscribed This Tax Year (£)
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                  <input
                    v-model.number="formData.isa_subscription_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="isJuniorISA ? JUNIOR_ISA_ALLOWANCE : ISA_ALLOWANCE"
                    class="w-full pl-8 pr-3 py-2 border border-violet-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white"
                    placeholder="0.00"
                  />
                </div>
                <p class="text-xs text-violet-700 mt-1">
                  Amount already contributed to this account for {{ currentTaxYear }} tax year, including {{ paymentsMadeThisTaxYear }} regular payments.
                </p>
              </div>

              <!-- Regular Contribution (for non-Junior ISAs) -->
              <div v-if="!isJuniorISA">
                <label class="block text-sm font-medium text-violet-900 mb-1">
                  Regular Contribution Amount (£)
                </label>
                <div class="flex gap-2">
                  <div class="flex-1 relative">
                    <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                    <input
                      v-model.number="formData.regular_contribution_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full pl-8 pr-3 py-2 border border-violet-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white"
                      :class="{ 'border-raspberry-500': isaAllowanceError }"
                      placeholder="0.00"
                    />
                  </div>
                  <div class="w-32">
                    <select
                      v-model="formData.contribution_frequency"
                      class="w-full px-3 py-2 border border-violet-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white"
                    >
                      <option value="monthly">Monthly</option>
                      <option value="quarterly">Quarterly</option>
                      <option value="annually">Annually</option>
                    </select>
                  </div>
                </div>
                <p class="text-xs text-violet-700 mt-1">
                  As of {{ todaysDate }}, you have {{ paymentsRemainingThisTaxYear }} contributions remaining for the {{ currentTaxYear }} tax year.
                </p>
                <!-- Projected ISA subscription advisory -->
                <div v-if="projectedSubscription > 0" class="mt-2 p-2 bg-violet-50 border border-violet-100 rounded text-xs text-violet-800">
                  Based on your regular contributions, your projected ISA subscription this year is <strong>{{ formatCurrency(projectedSubscription) }}</strong>.
                </div>
              </div>

              <!-- Planned Lump Sum (for non-Junior ISAs) -->
              <div v-if="!isJuniorISA">
                <label class="block text-sm font-medium text-violet-900 mb-1">
                  Planned Lump Sum (£)
                </label>
                <div class="flex gap-2">
                  <div class="flex-1 relative">
                    <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                    <input
                      v-model.number="formData.planned_lump_sum_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full pl-8 pr-3 py-2 border border-violet-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white"
                      :class="{ 'border-raspberry-500': isaAllowanceError }"
                      placeholder="0.00"
                    />
                  </div>
                  <div class="w-40">
                    <input
                      v-model="formData.planned_lump_sum_date"
                      type="date"
                      class="w-full px-3 py-2 border border-violet-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white"
                    />
                  </div>
                </div>
                <p class="text-xs text-violet-700 mt-1">
                  One-off contribution planned for this ISA (counts towards allowance)
                </p>
              </div>

              <!-- ISA Allowance Warning -->
              <div v-if="isaAllowanceError" class="p-3 bg-raspberry-50 border border-raspberry-200 rounded-md">
                <p class="text-sm text-raspberry-800">
                  <strong>Warning:</strong> {{ isaAllowanceError }}
                </p>
              </div>

              <!-- ISA Allowance Summary (for non-Junior ISAs) -->
              <div v-if="!isJuniorISA" class="bg-white border border-violet-200 rounded-md p-3">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-medium text-neutral-500">ISA Allowance Usage:</span>
                  <span class="text-lg font-bold" :class="totalRemainingAllowanceClass">
                    {{ formatCurrency(totalRemainingAllowance) }} remaining
                  </span>
                </div>
                <div class="w-full bg-savannah-200 rounded-full h-3 mb-2">
                  <div class="h-full flex rounded-full overflow-hidden">
                    <!-- Cash ISA portion (other accounts) -->
                    <div
                      v-if="otherCashISAUsed > 0"
                      class="bg-violet-500 h-full"
                      :style="{ width: (otherCashISAUsed / ISA_ALLOWANCE * 100) + '%' }"
                      :title="`Other Cash ISAs: ${formatCurrency(otherCashISAUsed)}`"
                    ></div>
                    <!-- S&S ISA portion -->
                    <div
                      v-if="stocksISAUsed > 0"
                      class="bg-purple-500 h-full"
                      :style="{ width: (stocksISAUsed / ISA_ALLOWANCE * 100) + '%' }"
                      :title="`Stocks ISAs: ${formatCurrency(stocksISAUsed)}`"
                    ></div>
                    <!-- This account's subscription -->
                    <div
                      v-if="thisAccountSubscription > 0"
                      class="bg-spring-500 h-full"
                      :style="{ width: (thisAccountSubscription / ISA_ALLOWANCE * 100) + '%' }"
                      :title="`This account: ${formatCurrency(thisAccountSubscription)}`"
                    ></div>
                    <!-- Planned contributions (lighter shade) -->
                    <div
                      v-if="plannedAnnualContribution > 0"
                      class="bg-violet-400 h-full"
                      :style="{ width: Math.min(plannedAnnualContribution / ISA_ALLOWANCE * 100, 100 - totalUsedPercent) + '%' }"
                      :title="`Planned: ${formatCurrency(plannedAnnualContribution)}`"
                    ></div>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-violet-500"></div>
                    <span class="text-neutral-500">Other Cash ISAs: {{ formatCurrency(otherCashISAUsed) }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                    <span class="text-neutral-500">Stocks ISAs: {{ formatCurrency(stocksISAUsed) }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-spring-500"></div>
                    <span class="text-neutral-500">This account: {{ formatCurrency(thisAccountSubscription) }}</span>
                  </div>
                  <div v-if="plannedAnnualContribution > 0" class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-violet-400"></div>
                    <span class="text-neutral-500">Planned: {{ formatCurrency(plannedAnnualContribution) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Joint Ownership Section (hidden for NS&I, ISA, and when stage config hides it) -->
            <div v-if="!isNSIProductType && !isISAProductType && !shouldHideOwnership" class="space-y-4 pt-4 border-t border-light-gray">
              <h4 class="text-sm font-semibold text-horizon-500">Ownership</h4>

              <!-- Ownership Type -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Ownership Type
                </label>
                <select
                  v-model="formData.ownership_type"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
                  <option value="individual">Individual Owner</option>
                  <option value="joint">Joint Owner</option>
                </select>
              </div>

              <!-- Joint Owner (if ownership_type is joint) -->
              <div v-if="formData.ownership_type === 'joint'">
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Joint Owner
                </label>
                <select
                  v-model="formData.joint_owner_id"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                >
                  <option value="">Select joint owner</option>
                  <option v-if="spouse && spouse.id" :value="spouse.id">{{ spouse.name }} (Spouse)</option>
                  <option v-if="!spouse || !spouse.id" value="" disabled>Link your spouse's account to enable joint ownership</option>
                </select>
                <p class="text-sm text-neutral-500 mt-1">
                  Joint accounts will appear in both your and your spouse's accounts.
                </p>
              </div>
            </div>

            <!-- Account Number (optional, hidden for NS&I products) -->
            <div v-if="!isNSIProductType">
              <label class="block text-sm font-medium text-neutral-500 mb-1">
                Account Number (last 4 digits)
              </label>
              <input
                v-model="formData.account_number"
                type="text"
                maxlength="4"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="Optional - for reference only"
              />
            </div>
          </div>

          <!-- Validation Error (shown for all account types) -->
          <div v-if="isaAllowanceError && !formData.is_isa && !isISAProductType" class="mt-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-md">
            <p class="text-sm text-raspberry-800">
              <strong>Warning:</strong> {{ isaAllowanceError }}
            </p>
          </div>

          <!-- Form Actions -->
          <div class="mt-6 flex justify-end gap-3">
            <button
              type="button"
              @click="handleClose"
              :class="context === 'onboarding'
                ? 'px-4 py-2 bg-light-pink-100 hover:bg-light-pink-200 text-horizon-500 rounded-lg transition-colors text-sm font-medium'
                : 'px-4 py-2 border border-horizon-300 rounded-md text-sm font-medium text-neutral-500 hover:bg-savannah-100 transition-colors'"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button text-sm font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Saving...' : (context === 'onboarding' ? 'Save' : (isEditing ? 'Update Account' : 'Add Account')) }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import CountrySelector from '@/components/Shared/CountrySelector.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import { ISA_ANNUAL_ALLOWANCE, JUNIOR_ISA_ALLOWANCE } from '@/constants/taxConfig';

import logger from '@/utils/logger';
export default {
  name: 'SaveAccountModal',

  emits: ['save', 'close'],

  mixins: [currencyMixin],

  components: {
    CountrySelector,
  },

  props: {
    account: {
      type: Object,
      default: null,
    },
    defaultAccountType: {
      type: String,
      default: '',
    },
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
    },
    savedData: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      submitting: false,
      isaAllowanceError: null,
      ISA_ALLOWANCE: ISA_ANNUAL_ALLOWANCE,
      JUNIOR_ISA_ALLOWANCE: JUNIOR_ISA_ALLOWANCE,
      formData: {
        institution: '',
        account_type: '',
        account_number: '',
        current_balance: 0,
        interest_rate: 0,
        access_type: 'immediate',
        notice_period_days: null,
        maturity_date: '',
        is_emergency_fund: false,
        is_isa: false,
        country: 'United Kingdom',
        isa_type: '',
        isa_subscription_year: getCurrentTaxYear(),
        isa_subscription_amount: null,
        regular_contribution_amount: null,
        contribution_frequency: 'monthly',
        planned_lump_sum_amount: null,
        planned_lump_sum_date: null,
        ownership_type: 'individual',
        joint_owner_id: null,
        beneficiary_id: '',
        beneficiary_name: '',
        beneficiary_dob: '',
      },
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    stageFormConfig() {
      return this.$store.getters['lifeStage/formFields']('savings') || {};
    },

    shouldHideOwnership() {
      return this.context === 'onboarding' && this.stageFormConfig.hideOwnership === true;
    },

    shouldHighlightEmergencyFund() {
      return this.context === 'onboarding' && this.stageFormConfig.emergencyFundProminent === true;
    },

    stageDefaultTypes() {
      return this.stageFormConfig.defaultTypes || [];
    },

    isEditing() {
      return !!this.account;
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },

    isISAProductType() {
      return ['cash_isa', 'junior_isa'].includes(this.formData.account_type);
    },

    isJuniorISA() {
      return this.formData.account_type === 'junior_isa';
    },

    isNSIProductType() {
      return ['premium_bonds', 'nsi'].includes(this.formData.account_type);
    },

    eligibleChildren() {
      return this.$store.getters['userProfile/juniorIsaEligibleChildren'] || [];
    },

    selectedBeneficiaryAge() {
      if (!this.formData.beneficiary_id || this.formData.beneficiary_id === 'other') {
        return null;
      }
      const child = this.eligibleChildren.find(c => c.id === parseInt(this.formData.beneficiary_id));
      if (!child || !child.date_of_birth) {
        return null;
      }
      const dob = new Date(child.date_of_birth);
      const today = new Date();
      return Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
    },

    isBeneficiary16Or17() {
      return this.selectedBeneficiaryAge !== null && this.selectedBeneficiaryAge >= 16 && this.selectedBeneficiaryAge <= 17;
    },

    maxBeneficiaryDob() {
      // Child must be under 18, so max DOB is today
      return new Date().toISOString().split('T')[0];
    },

    otherBeneficiaryAge() {
      if (!this.formData.beneficiary_dob) {
        return null;
      }
      const dob = new Date(this.formData.beneficiary_dob);
      const today = new Date();
      return Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
    },

    isOtherBeneficiary16Or17() {
      return this.otherBeneficiaryAge !== null && this.otherBeneficiaryAge >= 16 && this.otherBeneficiaryAge <= 17;
    },

    // ISA Allowance Tracking computed properties
    currentTaxYear() {
      return getCurrentTaxYear();
    },

    taxYearOptions() {
      const current = getCurrentTaxYear();
      const startYear = parseInt(current.split('/')[0]);
      return [
        `${startYear}/${String(startYear + 1).slice(-2)}`,
        `${startYear - 1}/${String(startYear).slice(-2)}`,
        `${startYear - 2}/${String(startYear - 1).slice(-2)}`,
      ];
    },

    todaysDate() {
      const now = new Date();
      return now.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    // Calculate months elapsed since start of tax year (April 6)
    monthsElapsedInTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth(); // 0-indexed

      // Tax year starts April 6
      // If we're Jan-March, tax year started previous April
      // If we're April-Dec, tax year started this April
      let taxYearStart;
      if (month < 3) { // Jan (0), Feb (1), Mar (2)
        taxYearStart = new Date(year - 1, 3, 6); // April 6 of previous year
      } else {
        taxYearStart = new Date(year, 3, 6); // April 6 of this year
      }

      // Calculate months difference
      const monthsDiff = (now.getFullYear() - taxYearStart.getFullYear()) * 12 +
                         (now.getMonth() - taxYearStart.getMonth());

      return Math.max(0, monthsDiff);
    },

    // Calculate payments made and remaining based on frequency
    paymentsMadeThisTaxYear() {
      const frequency = this.formData.contribution_frequency || 'monthly';
      const monthsElapsed = this.monthsElapsedInTaxYear;

      if (frequency === 'monthly') {
        return monthsElapsed;
      } else if (frequency === 'quarterly') {
        return Math.floor(monthsElapsed / 3);
      } else { // annually
        return monthsElapsed >= 12 ? 1 : 0;
      }
    },

    paymentsRemainingThisTaxYear() {
      const frequency = this.formData.contribution_frequency || 'monthly';
      let paymentsPerYear;

      if (frequency === 'monthly') {
        paymentsPerYear = 12;
      } else if (frequency === 'quarterly') {
        paymentsPerYear = 4;
      } else { // annually
        paymentsPerYear = 1;
      }

      return Math.max(0, paymentsPerYear - this.paymentsMadeThisTaxYear);
    },

    // Projected total ISA subscription based on regular contributions for the full tax year
    projectedSubscription() {
      const amount = this.formData.regular_contribution_amount || 0;
      if (amount <= 0) return 0;

      const frequency = this.formData.contribution_frequency || 'monthly';
      let paymentsPerYear;
      if (frequency === 'monthly') paymentsPerYear = 12;
      else if (frequency === 'quarterly') paymentsPerYear = 4;
      else paymentsPerYear = 1;

      let projected = amount * paymentsPerYear;

      // Add planned lump sum if set
      if (this.formData.planned_lump_sum_amount) {
        projected += this.formData.planned_lump_sum_amount;
      }

      return projected;
    },

    // Calculate remaining contributions for the rest of the tax year
    remainingContributionsForYear() {
      const amount = this.formData.regular_contribution_amount || 0;
      return this.paymentsRemainingThisTaxYear * amount;
    },

    // Get total Cash ISA usage from savings store
    totalCashISAUsed() {
      return this.$store.getters['savings/currentYearISASubscription'] || 0;
    },

    // Get S&S ISA usage from investment store
    stocksISAUsed() {
      return this.$store.getters['investment/investmentISASubscription'] || 0;
    },

    // Get other Cash ISA usage (excluding this account if editing)
    otherCashISAUsed() {
      if (!this.isEditing || !this.account) {
        return this.totalCashISAUsed;
      }
      // Subtract this account's subscription from total
      const thisAccountOriginal = parseFloat(this.account.isa_subscription_amount) || 0;
      return Math.max(0, this.totalCashISAUsed - thisAccountOriginal);
    },

    // This account's subscription amount
    thisAccountSubscription() {
      return this.formData.isa_subscription_amount || 0;
    },

    // Calculate planned contribution for remainder of tax year (regular + lump sum)
    // Only counts remaining contributions to avoid double-counting with "Already Subscribed"
    plannedAnnualContribution() {
      // Get remaining contributions for the rest of the tax year
      let planned = this.remainingContributionsForYear;

      // Add planned lump sum
      planned += this.formData.planned_lump_sum_amount || 0;

      return planned;
    },

    // Total ISA usage across all ISAs
    totalISAUsed() {
      return this.otherCashISAUsed + this.stocksISAUsed + this.thisAccountSubscription;
    },

    // Total including planned contributions
    totalWithPlanned() {
      return this.totalISAUsed + this.plannedAnnualContribution;
    },

    // Remaining allowance after all usage
    totalRemainingAllowance() {
      return Math.max(0, this.ISA_ALLOWANCE - this.totalWithPlanned);
    },

    // Percentage used (capped at 100)
    totalUsedPercent() {
      return Math.min(100, (this.totalISAUsed / this.ISA_ALLOWANCE) * 100);
    },

    // Class for remaining allowance display
    totalRemainingAllowanceClass() {
      if (this.totalWithPlanned > this.ISA_ALLOWANCE) return 'text-raspberry-600';
      if (this.totalRemainingAllowance < 2000) return 'text-violet-600';
      return 'text-spring-600';
    },
  },

  watch: {
    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'savings_account' && fill.fields) {
          // Pre-set critical fields before the highlight sequence
          if (fill.fields.institution) {
            this.formData.institution = fill.fields.institution;
          }
          if (fill.fields.account_type) {
            this.formData.account_type = fill.fields.account_type;
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
      if (isFilling === false && this.pendingFill?.entityType === 'savings_account') {
        setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
    },

    'formData.account_type'(newType) {
      // Auto-set ISA fields when ISA product type is selected (ISAs are always individual)
      if (this.isISAProductType) {
        this.formData.is_isa = true;
        this.formData.country = 'United Kingdom';
        this.formData.ownership_type = 'individual';
        this.formData.joint_owner_id = null;
        // Set isa_type based on account_type
        if (newType === 'cash_isa') {
          this.formData.isa_type = 'cash';
        } else if (newType === 'junior_isa') {
          this.formData.isa_type = 'junior';
        }
      }
      // Auto-set fields for NS&I products
      if (this.isNSIProductType) {
        this.formData.country = 'United Kingdom';
        this.formData.is_isa = false;
        this.formData.institution = 'NS&I';
        this.formData.ownership_type = 'individual';
        this.formData.joint_owner_id = null;
      }
      // Auto-set access type based on product type
      if (newType === 'notice') {
        this.formData.access_type = 'notice';
      } else if (newType === 'fixed') {
        this.formData.access_type = 'fixed';
      }
    },
  },

  mounted() {
    // Fetch family members for Junior ISA beneficiary selection
    this.$store.dispatch('userProfile/fetchFamilyMembers');

    if (this.isEditing && this.account) {
      this.loadAccountData();
    } else if (this.defaultAccountType) {
      this.formData.account_type = this.defaultAccountType;
    }
  },

  methods: {
    loadAccountData() {
      this.formData = {
        institution: this.account.institution || '',
        account_type: this.account.account_type || '',
        account_number: this.account.account_number || '',
        current_balance: parseFloat(this.account.current_balance) || 0,
        interest_rate: parseFloat(this.account.interest_rate) || 0, // Rate is already stored as percentage
        access_type: this.account.access_type || 'immediate',
        notice_period_days: this.account.notice_period_days || null,
        maturity_date: this.formatDateForInput(this.account.maturity_date),
        is_emergency_fund: this.account.is_emergency_fund || false,
        is_isa: this.account.is_isa || false,
        country: this.account.country || 'United Kingdom',
        isa_type: this.account.isa_type || '',
        isa_subscription_year: this.account.isa_subscription_year || getCurrentTaxYear(),
        isa_subscription_amount: this.account.isa_subscription_amount ? parseFloat(this.account.isa_subscription_amount) : null,
        regular_contribution_amount: this.account.regular_contribution_amount ? parseFloat(this.account.regular_contribution_amount) : null,
        contribution_frequency: this.account.contribution_frequency || 'monthly',
        planned_lump_sum_amount: this.account.planned_lump_sum_amount ? parseFloat(this.account.planned_lump_sum_amount) : null,
        planned_lump_sum_date: this.formatDateForInput(this.account.planned_lump_sum_date),
        ownership_type: this.account.ownership_type || 'individual',
        joint_owner_id: this.account.joint_owner_id || null,
        beneficiary_id: this.account.beneficiary_id || '',
        beneficiary_name: this.account.beneficiary_name || '',
        beneficiary_dob: this.formatDateForInput(this.account.beneficiary_dob) || '',
      };
    },

    formatDateForInput(date) {
      if (!date) return '';
      if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return date;
      }
      const dateObj = new Date(date);
      if (isNaN(dateObj.getTime())) return '';
      const year = dateObj.getFullYear();
      const month = String(dateObj.getMonth() + 1).padStart(2, '0');
      const day = String(dateObj.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },

    async handleSubmit() {
      this.submitting = true;
      this.isaAllowanceError = null;

      // Validate ISA allowance for non-Junior ISAs
      if ((this.formData.is_isa || this.isISAProductType) && !this.isJuniorISA) {
        if (this.totalWithPlanned > this.ISA_ALLOWANCE) {
          const excess = this.totalWithPlanned - this.ISA_ALLOWANCE;
          this.isaAllowanceError = `Your planned ISA contributions would exceed the £20,000 allowance by ${this.formatCurrency(excess)}. Consider reducing your regular contributions or lump sum.`;
          this.submitting = false;
          return;
        }
      }

      // Validate Premium Bonds maximum £50,000 per person
      if (this.formData.account_type === 'premium_bonds') {
        const balance = parseFloat(this.formData.current_balance) || 0;
        if (balance > 50000) {
          this.isaAllowanceError = `Premium Bonds have a maximum holding of £50,000 per person. You have entered ${this.formatCurrency(balance)}.`;
          this.submitting = false;
          return;
        }
      }

      try {
        const accountData = this.prepareAccountData();
        this.$emit('save', accountData);
      } catch (error) {
        logger.error('Form submission error:', error);
      } finally {
        this.submitting = false;
      }
    },

    prepareAccountData() {
      const isISA = this.formData.is_isa || this.isISAProductType;
      const data = {
        institution: this.formData.institution,
        account_type: this.formData.account_type,
        account_number: this.formData.account_number || null,
        current_balance: this.formData.current_balance,
        interest_rate: this.formData.interest_rate, // Store as percentage (4.55 = 4.55%)
        access_type: this.formData.access_type,
        notice_period_days: this.formData.access_type === 'notice' ? this.formData.notice_period_days : null,
        maturity_date: this.formData.access_type === 'fixed' ? this.formData.maturity_date : null,
        is_emergency_fund: this.formData.is_emergency_fund,
        is_isa: this.formData.is_isa,
        country: isISA ? 'United Kingdom' : this.formData.country,
        isa_type: isISA ? this.formData.isa_type : null,
        isa_subscription_year: isISA ? this.formData.isa_subscription_year : null,
        isa_subscription_amount: isISA ? this.formData.isa_subscription_amount : null,
        // Regular contribution and planned lump sum (for ISAs only, excluding Junior ISAs)
        regular_contribution_amount: isISA && !this.isJuniorISA ? this.formData.regular_contribution_amount : null,
        contribution_frequency: isISA && !this.isJuniorISA ? this.formData.contribution_frequency : null,
        planned_lump_sum_amount: isISA && !this.isJuniorISA ? this.formData.planned_lump_sum_amount : null,
        planned_lump_sum_date: isISA && !this.isJuniorISA ? this.formData.planned_lump_sum_date : null,
        ownership_type: this.formData.ownership_type,
        joint_owner_id: this.formData.ownership_type === 'joint' ? this.formData.joint_owner_id : null,
        // Junior ISA beneficiary fields
        beneficiary_id: this.isJuniorISA && this.formData.beneficiary_id !== 'other' ? this.formData.beneficiary_id : null,
        beneficiary_name: this.isJuniorISA ? this.formData.beneficiary_name : null,
        beneficiary_dob: this.isJuniorISA && this.formData.beneficiary_id === 'other' ? this.formData.beneficiary_dob : null,
      };

      return data;
    },

    handleBeneficiaryChange() {
      // When a child is selected from the list, auto-fill the beneficiary name
      if (this.formData.beneficiary_id && this.formData.beneficiary_id !== 'other') {
        const selectedChild = this.eligibleChildren.find(c => c.id === parseInt(this.formData.beneficiary_id));
        if (selectedChild) {
          this.formData.beneficiary_name = `${selectedChild.first_name} ${selectedChild.last_name}`.trim();
        }
      } else if (this.formData.beneficiary_id === 'other') {
        // Clear the name so user can enter it
        this.formData.beneficiary_name = '';
      }
    },

    formatRelationship(relationship) {
      const labels = {
        child: 'Child',
        step_child: 'Step Child',
        other_dependent: 'Dependant',
      };
      return labels[relationship] || relationship;
    },

    handleClose() {
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
    },
  },
};
</script>

