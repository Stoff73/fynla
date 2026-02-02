<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-start">
      <div>
        <h2 class="text-2xl font-semibold text-gray-900">{{ pageTitle }}</h2>
        <p class="mt-1 text-sm text-gray-600">
          {{ pageDescription }}
        </p>
      </div>
      <div class="flex gap-3">
        <!-- Save PDF Button - Always visible when not editing -->
        <button
          v-if="!isEditing"
          @click="generatePDF"
          :disabled="generatingPdf"
          class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors disabled:opacity-50 flex items-center gap-2"
        >
          <svg v-if="!generatingPdf" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ generatingPdf ? 'Preparing...' : 'Print / Save PDF' }}
        </button>
        <template v-if="!isReadOnly">
          <button
            v-if="!isEditing"
            @click="isEditing = true"
            class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors"
          >
            Edit
          </button>
          <template v-else>
            <button
              @click="cancelEditing"
              class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg font-medium hover:bg-gray-50 transition-colors"
              :disabled="saving"
            >
              Cancel
            </button>
            <button
              @click="saveLetter"
              :disabled="saving"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition-colors disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </template>
        </template>
      </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex">
        <svg class="h-5 w-5 text-blue-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <p class="ml-3 text-sm text-blue-800">
          <strong>Why this matters:</strong> {{ infoBannerText }}
        </p>
      </div>
    </div>

    <!-- View Toggle -->
    <div v-if="hasSpouse" class="flex space-x-3">
      <button
        @click="switchToMyLetter"
        :class="[
          'px-4 py-2 rounded-lg font-medium transition-colors',
          viewMode === 'my'
            ? 'bg-primary-600 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
        ]"
      >
        My Letter
      </button>
      <button
        @click="loadSpouseLetter"
        :class="[
          'px-4 py-2 rounded-lg font-medium transition-colors',
          viewMode === 'spouse'
            ? 'bg-primary-600 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
        ]"
      >
        {{ spouseName }}'s Letter
      </button>
    </div>

    <!-- Read-only Banner for Spouse View -->
    <div v-if="viewMode === 'spouse'" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-blue-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <p class="text-sm text-blue-800 font-medium">
          Viewing {{ spouseName }}'s letter (read-only)
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Letter Content -->
    <div v-else class="space-y-6">
      <!-- Part 1: What to Do Immediately -->
      <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-900">Part 1: What to Do Immediately</h3>
          <p class="mt-1 text-sm text-gray-500">Critical first steps and key contacts</p>
        </div>
        <div class="p-6 space-y-6">
          <!-- VIEW MODE -->
          <template v-if="!isEditing || isReadOnly">
            <!-- Immediate Actions Checklist -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 mb-3">Immediate Actions Checklist</h4>
              <div v-if="parsedImmediateActions.length > 0" class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div
                    v-for="(action, index) in parsedImmediateActions"
                    :key="index"
                    class="flex items-start"
                  >
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">
                      {{ index + 1 }}
                    </div>
                    <span class="text-sm text-gray-700">{{ action }}</span>
                  </div>
                </div>
              </div>
              <p v-else class="text-sm text-gray-500 italic">No immediate actions specified</p>
            </div>

            <!-- Key Contacts Grid -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 mb-3">Key Contacts</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Executor Card - Highlighted -->
                <div class="bg-primary-50 rounded-lg p-4 border border-primary-200">
                  <div class="flex items-center mb-2">
                    <svg class="w-4 h-4 text-primary-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span class="text-xs font-semibold text-primary-700 uppercase tracking-wide">Executor</span>
                    <span v-if="willData && willData.executor_name" class="ml-auto text-xs bg-primary-100 text-primary-700 px-2 py-0.5 rounded">From Will</span>
                  </div>
                  <p class="text-sm font-medium text-gray-900">{{ displayExecutorName || 'Not specified' }}</p>
                  <p v-if="formData.executor_contact" class="text-sm text-gray-600 mt-1">{{ formData.executor_contact }}</p>
                  <p v-if="willData && willData.executor_notes" class="text-xs text-gray-500 mt-2 italic">{{ willData.executor_notes }}</p>
                </div>

                <!-- Solicitor Card -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                  <div class="flex items-center mb-2">
                    <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Solicitor</span>
                  </div>
                  <p class="text-sm font-medium text-gray-900">{{ formData.attorney_name || 'Not specified' }}</p>
                  <p v-if="formData.attorney_contact" class="text-sm text-gray-600 mt-1">{{ formData.attorney_contact }}</p>
                </div>

                <!-- Financial Adviser Card -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                  <div class="flex items-center mb-2">
                    <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Financial Adviser</span>
                  </div>
                  <p class="text-sm font-medium text-gray-900">{{ formData.financial_advisor_name || 'Not specified' }}</p>
                  <p v-if="formData.financial_advisor_contact" class="text-sm text-gray-600 mt-1">{{ formData.financial_advisor_contact }}</p>
                </div>

                <!-- Accountant Card -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                  <div class="flex items-center mb-2">
                    <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Accountant</span>
                  </div>
                  <p class="text-sm font-medium text-gray-900">{{ formData.accountant_name || 'Not specified' }}</p>
                  <p v-if="formData.accountant_contact" class="text-sm text-gray-600 mt-1">{{ formData.accountant_contact }}</p>
                </div>
              </div>
            </div>

            <!-- Immediate Funds & Employer -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Accessing Immediate Funds</h4>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                  <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.immediate_funds_access || 'Not specified' }}</p>
                </div>
              </div>
              <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Employer HR Contact</h4>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                  <p class="text-sm font-medium text-gray-900">{{ formData.employer_hr_contact || 'Not specified' }}</p>
                  <p v-if="formData.employer_benefits_info" class="text-sm text-gray-600 mt-1">{{ formData.employer_benefits_info }}</p>
                </div>
              </div>
            </div>
          </template>

          <!-- EDIT MODE -->
          <template v-else>
            <!-- Immediate Actions -->
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-2">
                Immediate Actions Checklist
              </label>
              <textarea
                v-model="formData.immediate_actions"
                rows="5"
                class="input-field"
                placeholder="1. Contact executor immediately&#10;2. Notify employer HR&#10;3. Access joint bank accounts for immediate expenses..."
              ></textarea>
            </div>

            <!-- Key Contacts Grid -->
            <div>
              <h4 class="text-body font-semibold text-gray-800 mb-4">Key Contacts</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Executor</div>
                  <input
                    v-model="formData.executor_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.executor_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Solicitor</div>
                  <input
                    v-model="formData.attorney_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.attorney_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Financial Adviser</div>
                  <input
                    v-model="formData.financial_advisor_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.financial_advisor_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Accountant</div>
                  <input
                    v-model="formData.accountant_name"
                    type="text"
                    class="input-field mb-2"
                    placeholder="Name"
                  />
                  <input
                    v-model="formData.accountant_contact"
                    type="text"
                    class="input-field"
                    placeholder="Phone / Email"
                  />
                </div>
              </div>
            </div>

            <!-- Immediate Funds & Employer -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Accessing Immediate Funds</label>
                <textarea
                  v-model="formData.immediate_funds_access"
                  rows="3"
                  class="input-field"
                  placeholder="Which accounts can be accessed immediately..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Employer HR Contact</label>
                <input
                  v-model="formData.employer_hr_contact"
                  type="text"
                  class="input-field mb-2"
                  placeholder="HR phone / email"
                />
                <input
                  v-model="formData.employer_benefits_info"
                  type="text"
                  class="input-field"
                  placeholder="Benefits info (life insurance, pension)"
                />
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Part 2: Financial Overview (Auto-populated) -->
      <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Part 2: Financial Overview</h3>
            <p class="mt-1 text-sm text-gray-500">Your current financial position (automatically updated)</p>
          </div>
          <span class="text-xs font-semibold text-primary-700 bg-primary-100 px-3 py-1 rounded-full">Auto-populated</span>
        </div>
        <div class="p-6 space-y-6">
          <!-- Bank Accounts / Savings -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-sm font-semibold text-gray-900">Bank Accounts & Savings</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalSavings) }}</span>
            </div>
            <div v-if="profileData.savings.length > 0" class="flex flex-wrap gap-3">
              <div
                v-for="account in profileData.savings"
                :key="account.id"
                class="bg-white rounded-lg p-4 border border-gray-200 min-w-[200px] flex-1 max-w-[280px]"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ account.account_name || account.provider }}</div>
                    <div class="text-sm text-gray-500">{{ account.institution || account.provider }}</div>
                  </div>
                  <span v-if="account.is_isa || account.account_type === 'cash_isa'" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">ISA</span>
                </div>
                <div class="mt-2 text-lg font-semibold text-gray-900">{{ formatCurrency(account.current_balance) }}</div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No savings accounts recorded</p>
          </div>

          <!-- Pensions -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-sm font-semibold text-gray-900">Pensions</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalPensions) }}</span>
            </div>
            <div v-if="profileData.pensions.length > 0" class="flex flex-wrap gap-3">
              <div
                v-for="pension in profileData.pensions"
                :key="pension.id"
                class="bg-white rounded-lg p-4 border border-gray-200 min-w-[200px] flex-1 max-w-[280px]"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ pension.scheme_name || pension.provider }}</div>
                    <div class="text-sm text-gray-500">{{ pension.provider }}</div>
                  </div>
                  <span :class="getPensionTypeBadgeClass(pension.pension_type)" class="text-xs px-2 py-0.5 rounded">
                    {{ formatPensionType(pension.pension_type) }}
                  </span>
                </div>
                <div class="mt-2">
                  <div class="text-lg font-semibold text-gray-900">{{ formatCurrency(pension.current_value || pension.current_fund_value) }}</div>
                  <div v-if="pension.employer" class="text-xs text-gray-500 mt-1">{{ pension.employer }}</div>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No pensions recorded</p>
          </div>

          <!-- Investment Accounts -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-sm font-semibold text-gray-900">Investments</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalInvestments) }}</span>
            </div>
            <div v-if="profileData.investments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="account in profileData.investments"
                :key="account.id"
                class="bg-white rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ account.account_name || account.provider }}</div>
                    <div class="text-sm text-gray-500">{{ account.provider }}</div>
                  </div>
                  <span v-if="account.account_type === 'stocks_and_shares_isa'" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">ISA</span>
                  <span v-else-if="account.account_type === 'gia'" class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">GIA</span>
                </div>
                <div class="mt-2 text-lg font-semibold text-gray-900">{{ formatCurrency(account.current_value) }}</div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No investment accounts recorded</p>
          </div>

          <!-- Properties -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-sm font-semibold text-gray-900">Properties</h4>
              <span class="text-lg font-bold text-gray-900">{{ formatCurrency(profileData.totalPropertyValue) }}</span>
            </div>
            <div v-if="profileData.properties.length > 0" class="flex flex-wrap gap-3">
              <div
                v-for="property in profileData.properties"
                :key="property.id"
                class="bg-white rounded-lg p-4 border border-gray-200 min-w-[250px] flex-1 max-w-[320px]"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ property.property_name || property.address_line_1 }}</div>
                    <div class="text-sm text-gray-500">{{ formatPropertyType(property.property_type) }}</div>
                  </div>
                  <span :class="ownershipBadgeClass(property.ownership_type)" class="text-xs px-2 py-0.5 rounded">
                    {{ formatOwnershipType(property.ownership_type) }}
                  </span>
                </div>
                <div class="mt-2 flex justify-between">
                  <div>
                    <div class="text-sm text-gray-500">Value</div>
                    <div class="font-semibold text-gray-900">{{ formatCurrency(property.current_value) }}</div>
                  </div>
                  <div v-if="property.mortgage_balance">
                    <div class="text-sm text-gray-500">Mortgage</div>
                    <div class="font-semibold text-red-600">{{ formatCurrency(property.mortgage_balance) }}</div>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No properties recorded</p>
          </div>

          <!-- Insurance Policies -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-sm font-semibold text-gray-900">Life Insurance & Protection</h4>
              <span class="text-lg font-bold text-green-600">{{ formatCurrency(profileData.totalCoverage) }}</span>
            </div>
            <div v-if="profileData.policies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="policy in profileData.policies"
                :key="`${policy.policy_type}-${policy.id}`"
                class="bg-white rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-gray-900">{{ policy.provider }}</div>
                    <div class="text-sm text-gray-500">{{ formatPolicyType(policy.policy_type) }}</div>
                  </div>
                </div>
                <div class="mt-2">
                  <div class="text-sm text-gray-500">Sum Assured</div>
                  <div class="font-semibold text-green-600">{{ formatCurrency(policy.sum_assured || policy.benefit_amount) }}</div>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No protection policies recorded</p>
          </div>

          <!-- Liabilities -->
          <div>
            <div class="flex justify-between items-center mb-3">
              <h4 class="text-sm font-semibold text-gray-900">Liabilities & Debts</h4>
              <span class="text-lg font-bold text-red-600">{{ formatCurrency(profileData.totalLiabilities) }}</span>
            </div>
            <div v-if="profileData.liabilities.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="liability in profileData.liabilities"
                :key="liability.id"
                class="bg-white rounded-lg p-4 border border-gray-200"
              >
                <div class="font-medium text-gray-900">{{ liability.liability_name }}</div>
                <div class="text-sm text-gray-500">{{ formatLiabilityType(liability.liability_type) }}</div>
                <div class="mt-2 font-semibold text-red-600">{{ formatCurrency(liability.current_balance) }}</div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500 italic">No liabilities recorded</p>
          </div>

          <!-- Bequests (from Will) -->
          <div v-if="willData && willData.bequests && willData.bequests.length > 0">
            <div class="flex justify-between items-center mb-3">
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-semibold text-gray-900">Bequests & Legacies</h4>
                <span class="text-xs bg-primary-100 text-primary-700 px-2 py-0.5 rounded">From Will</span>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="(bequest, index) in willData.bequests"
                :key="'bequest-' + index"
                class="bg-white rounded-lg p-4 border border-gray-200"
              >
                <div class="flex justify-between items-start mb-2">
                  <div class="font-medium text-gray-900">{{ bequest.beneficiary_name }}</div>
                  <span :class="getBequestTypeBadgeClass(bequest.bequest_type)" class="text-xs px-2 py-0.5 rounded">
                    {{ formatBequestType(bequest.bequest_type) }}
                  </span>
                </div>
                <div class="text-sm">
                  <template v-if="bequest.bequest_type === 'percentage'">
                    <span class="font-semibold text-gray-900">{{ bequest.percentage_of_estate }}%</span>
                    <span class="text-gray-500"> of estate</span>
                  </template>
                  <template v-else-if="bequest.bequest_type === 'specific_amount'">
                    <span class="font-semibold text-gray-900">{{ formatCurrency(bequest.specific_amount) }}</span>
                  </template>
                  <template v-else-if="bequest.bequest_type === 'specific_asset'">
                    <span class="text-gray-700">{{ bequest.specific_asset_description }}</span>
                  </template>
                </div>
                <p v-if="bequest.conditions" class="text-xs text-gray-500 mt-2 italic">{{ bequest.conditions }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Part 3: Additional Information (Manual Entry) -->
      <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-900">Part 3: Additional Information</h3>
          <p class="mt-1 text-sm text-gray-500">Important details not captured elsewhere</p>
        </div>
        <div class="p-6 space-y-4">
          <!-- VIEW MODE -->
          <template v-if="!isEditing || isReadOnly">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Password Manager / Online Access</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.password_manager_info || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Estate Documents Location</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.estate_documents_location || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Vehicles</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.vehicles_info || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Valuable Items</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.valuable_items_info || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Cryptocurrency</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.cryptocurrency_info || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Recurring Bills</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.recurring_bills_info || 'Not specified' }}</p>
              </div>
              <!-- Additional Custom Boxes - View Mode -->
              <div
                v-for="(box, index) in formData.additional_boxes"
                :key="'view-box-' + index"
                class="bg-white rounded-lg border border-gray-200 p-4"
              >
                <h4 class="text-sm font-semibold text-gray-900 mb-2">{{ box.title || 'Untitled' }}</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ box.content || 'Not specified' }}</p>
              </div>
            </div>
          </template>

          <!-- EDIT MODE -->
          <template v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Password Manager / Online Access</label>
                <textarea
                  v-model="formData.password_manager_info"
                  rows="3"
                  class="input-field"
                  placeholder="1Password details, emergency access..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Estate Documents Location</label>
                <textarea
                  v-model="formData.estate_documents_location"
                  rows="3"
                  class="input-field"
                  placeholder="Will, trust documents, power of attorney..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Vehicles</label>
                <textarea
                  v-model="formData.vehicles_info"
                  rows="2"
                  class="input-field"
                  placeholder="Car details, V5C location..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Valuable Items</label>
                <textarea
                  v-model="formData.valuable_items_info"
                  rows="2"
                  class="input-field"
                  placeholder="Jewellery, art, antiques..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Cryptocurrency</label>
                <textarea
                  v-model="formData.cryptocurrency_info"
                  rows="2"
                  class="input-field"
                  placeholder="Wallet addresses, recovery seeds..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Recurring Bills</label>
                <textarea
                  v-model="formData.recurring_bills_info"
                  rows="2"
                  class="input-field"
                  placeholder="Council tax, utilities, subscriptions..."
                ></textarea>
              </div>
            </div>

            <!-- Additional Custom Boxes - Edit Mode -->
            <div v-if="formData.additional_boxes.length > 0" class="mt-6 pt-6 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-900 mb-4">Additional Information</h4>
              <div class="space-y-4">
                <div
                  v-for="(box, index) in formData.additional_boxes"
                  :key="'edit-box-' + index"
                  class="bg-white rounded-lg border border-gray-200 p-4"
                >
                  <div class="flex justify-between items-start mb-3">
                    <input
                      v-model="box.title"
                      type="text"
                      class="input-field flex-1 mr-3"
                      placeholder="Title (e.g., Business Contacts, Pet Care Instructions)"
                    />
                    <button
                      @click="removeAdditionalBox(index)"
                      type="button"
                      class="text-red-500 hover:text-red-700 p-1"
                      title="Remove this box"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                  <textarea
                    v-model="box.content"
                    rows="3"
                    class="input-field"
                    placeholder="Enter details..."
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- Add Box Button -->
            <div class="mt-4">
              <button
                v-if="canAddMoreBoxes"
                @click="addAdditionalBox"
                type="button"
                class="flex items-center text-sm text-primary-600 hover:text-primary-700 font-medium"
              >
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Add Additional Information Box
                <span class="ml-2 text-gray-400 font-normal">({{ formData.additional_boxes.length }}/10)</span>
              </button>
              <p v-else class="text-sm text-gray-500">Maximum of 10 additional boxes reached</p>
            </div>
          </template>
        </div>
      </div>

      <!-- Part 4: Funeral and Final Wishes -->
      <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-900">Part 4: Funeral and Final Wishes</h3>
          <p class="mt-1 text-sm text-gray-500">Your preferences for final arrangements</p>
        </div>
        <div class="p-6 space-y-4">
          <!-- VIEW MODE -->
          <template v-if="!isEditing || isReadOnly">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Funeral Preference</h4>
                <span :class="[
                  'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                  formData.funeral_preference === 'burial' ? 'bg-blue-100 text-blue-800' :
                  formData.funeral_preference === 'cremation' ? 'bg-green-100 text-green-800' :
                  'bg-gray-100 text-gray-700'
                ]">
                  {{ formatFuneralPreference(formData.funeral_preference) }}
                </span>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Funeral Service Details</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.funeral_service_details || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Obituary Wishes</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.obituary_wishes || 'Not specified' }}</p>
              </div>
              <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Additional Wishes</h4>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ formData.additional_wishes || 'Not specified' }}</p>
              </div>
            </div>
          </template>

          <!-- EDIT MODE -->
          <template v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Funeral Preference</label>
                <select
                  v-model="formData.funeral_preference"
                  class="input-field"
                >
                  <option value="not_specified">Not Specified</option>
                  <option value="burial">Burial</option>
                  <option value="cremation">Cremation</option>
                </select>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Funeral Service Details</label>
                <textarea
                  v-model="formData.funeral_service_details"
                  rows="2"
                  class="input-field"
                  placeholder="Service preferences, music, location..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Obituary Wishes</label>
                <textarea
                  v-model="formData.obituary_wishes"
                  rows="2"
                  class="input-field"
                  placeholder="Key accomplishments, charities..."
                ></textarea>
              </div>
              <div>
                <label class="block text-body-sm font-medium text-gray-700 mb-2">Additional Wishes</label>
                <textarea
                  v-model="formData.additional_wishes"
                  rows="2"
                  class="input-field"
                  placeholder="Messages to loved ones..."
                ></textarea>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import logoImage from '@/assets/images/logoTransparent.png';

export default {
  name: 'LetterToSpouse',
  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      saving: false,
      generatingPdf: false,
      isEditing: false,
      viewMode: 'my',
      hasSpouse: false,
      spouseName: '',
      isExpressionOfWishes: false,
      originalFormData: null,
      willData: null,
      formData: {
        immediate_actions: '',
        executor_name: '',
        executor_contact: '',
        attorney_name: '',
        attorney_contact: '',
        financial_advisor_name: '',
        financial_advisor_contact: '',
        accountant_name: '',
        accountant_contact: '',
        immediate_funds_access: '',
        employer_hr_contact: '',
        employer_benefits_info: '',
        password_manager_info: '',
        estate_documents_location: '',
        vehicles_info: '',
        valuable_items_info: '',
        cryptocurrency_info: '',
        recurring_bills_info: '',
        funeral_preference: 'not_specified',
        funeral_service_details: '',
        obituary_wishes: '',
        additional_wishes: '',
        additional_boxes: [],
      },
      profileData: {
        savings: [],
        pensions: [],
        investments: [],
        properties: [],
        policies: [],
        liabilities: [],
        totalSavings: 0,
        totalPensions: 0,
        totalInvestments: 0,
        totalPropertyValue: 0,
        totalCoverage: 0,
        totalLiabilities: 0,
      },
      myLetterData: null,
      spouseLetterData: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    isReadOnly() {
      return this.viewMode === 'spouse';
    },

    pageTitle() {
      return this.isExpressionOfWishes ? 'Expression of Wishes' : 'Letter to Spouse';
    },

    pageDescription() {
      return this.isExpressionOfWishes
        ? 'Important information for your loved ones in the event of your death'
        : 'Important information for your spouse in the event of your death';
    },

    infoBannerText() {
      return this.isExpressionOfWishes
        ? 'This document provides crucial information for your loved ones to manage your affairs after your death. Auto-populated sections show your current profile data.'
        : 'This letter provides crucial information for your spouse to manage financial affairs after your death. Auto-populated sections show your current profile data.';
    },

    userName() {
      return this.$store.state.auth?.user?.name || 'User';
    },

    currentDate() {
      return new Date().toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    },

    logoUrl() {
      return logoImage;
    },

    /**
     * Parse immediate actions text into an array of action items
     */
    parsedImmediateActions() {
      if (!this.formData.immediate_actions) return [];

      const text = this.formData.immediate_actions;
      // Split by newlines and filter out empty lines
      const lines = text.split('\n').filter(line => line.trim());

      // Remove leading numbers/bullets and clean up
      return lines.map(line => {
        // Remove patterns like "1.", "1)", "1:", "-", "•", etc. at the start
        return line.replace(/^\s*[\d]+[.):\s-]*|^\s*[-•]\s*/g, '').trim();
      }).filter(line => line.length > 0);
    },

    /**
     * Display executor name - prefer Will data, fall back to letter data
     */
    displayExecutorName() {
      // If Will has executor data, use that as primary source
      if (this.willData && this.willData.executor_name) {
        return this.willData.executor_name;
      }
      // Fall back to letter's stored executor name
      return this.formData.executor_name;
    },

    /**
     * Check if user can add more additional boxes (max 10)
     */
    canAddMoreBoxes() {
      return this.formData.additional_boxes.length < 10;
    },
  },

  async mounted() {
    await Promise.all([
      this.loadMyLetter(),
      this.loadProfileData(),
      this.checkSpouse(),
      this.loadWillData(),
    ]);
  },

  methods: {
    async loadMyLetter() {
      try {
        const response = await api.get('/user/letter-to-spouse');
        this.myLetterData = response.data.data;
        if (this.viewMode === 'my') {
          this.populateForm(this.myLetterData);
        }
      } catch (error) {
        console.error('Error loading letter:', error);
      }
    },

    async loadProfileData() {
      this.loading = true;
      try {
        const [savingsRes, investmentsRes, propertiesRes, protectionRes, estateRes, retirementRes] = await Promise.all([
          api.get('/savings').catch(() => ({ data: { data: [] } })),
          api.get('/investment').catch(() => ({ data: { data: [] } })),
          api.get('/properties').catch(() => ({ data: { data: [] } })),
          api.get('/protection').catch(() => ({ data: { data: {} } })),
          api.get('/estate').catch(() => ({ data: { data: { liabilities: [] } } })),
          api.get('/retirement').catch(() => ({ data: { data: {} } })),
        ]);

        // Extract savings accounts from nested structure
        this.profileData.savings = savingsRes.data.data?.accounts || savingsRes.data?.accounts || [];
        this.profileData.investments = investmentsRes.data.data?.accounts || investmentsRes.data?.accounts || [];
        this.profileData.properties = propertiesRes.data.data || propertiesRes.data || [];

        // Liabilities come from estate endpoint
        const estate = estateRes.data.data || estateRes.data || {};
        this.profileData.liabilities = estate.liabilities || [];

        // Pensions from retirement endpoint
        const retirement = retirementRes.data.data || retirementRes.data || {};
        const dcPensions = retirement.dc_pensions || [];
        const dbPensions = retirement.db_pensions || [];
        this.profileData.pensions = [
          ...dcPensions.map(p => ({ ...p, pension_type: 'dc' })),
          ...dbPensions.map(p => ({ ...p, pension_type: 'db' })),
        ];

        // Combine all protection policies - handle nested policies structure
        const protection = protectionRes.data.data || protectionRes.data || {};
        const policies = protection.policies || protection;
        this.profileData.policies = [
          ...(policies.life_insurance || []).map(p => ({ ...p, policy_type: 'life' })),
          ...(policies.critical_illness || []).map(p => ({ ...p, policy_type: 'critical_illness' })),
          ...(policies.income_protection || []).map(p => ({ ...p, policy_type: 'income_protection' })),
        ];

        // Calculate totals
        this.profileData.totalSavings = this.profileData.savings.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
        this.profileData.totalPensions = this.profileData.pensions.reduce((sum, p) => sum + (parseFloat(p.current_value) || parseFloat(p.current_fund_value) || 0), 0);
        this.profileData.totalInvestments = this.profileData.investments.reduce((sum, a) => sum + (parseFloat(a.current_value) || 0), 0);
        this.profileData.totalPropertyValue = this.profileData.properties.reduce((sum, p) => sum + (parseFloat(p.current_value) || 0), 0);
        this.profileData.totalCoverage = this.profileData.policies.reduce((sum, p) => sum + (parseFloat(p.sum_assured) || parseFloat(p.benefit_amount) || 0), 0);
        this.profileData.totalLiabilities = this.profileData.liabilities.reduce((sum, l) => sum + (parseFloat(l.current_balance) || 0), 0);
      } catch (error) {
        console.error('Error loading profile data:', error);
      } finally {
        this.loading = false;
      }
    },

    async checkSpouse() {
      try {
        const userResponse = await api.get('/auth/user');
        const user = userResponse.data.data?.user || userResponse.data;

        // Single, widowed and divorced users see "Expression of Wishes" instead
        const expressionOfWishesStatuses = ['single', 'widowed', 'divorced'];
        this.isExpressionOfWishes = expressionOfWishesStatuses.includes(user.marital_status);

        // Only show spouse toggle for users with a spouse
        if (!this.isExpressionOfWishes && user.spouse_id && user.spouse) {
          this.spouseName = user.spouse.name;
          this.hasSpouse = true;
        }
      } catch (error) {
        console.error('Error checking spouse:', error);
      }
    },

    async loadWillData() {
      try {
        const response = await api.get('/estate/will');
        this.willData = response.data.data;

        // If letter doesn't have executor info but Will does, pre-populate
        if (this.willData && this.willData.executor_name && !this.formData.executor_name) {
          this.formData.executor_name = this.willData.executor_name;
        }
      } catch (error) {
        // Will might not exist yet, that's fine
        console.log('Will data not available:', error.message);
      }
    },

    switchToMyLetter() {
      this.viewMode = 'my';
      this.populateForm(this.myLetterData);
    },

    async loadSpouseLetter() {
      if (this.viewMode === 'spouse' && this.spouseLetterData) {
        return;
      }

      this.loading = true;
      this.viewMode = 'spouse';

      try {
        const response = await api.get('/user/letter-to-spouse/spouse');
        this.spouseLetterData = response.data.data;
        this.spouseName = response.data.spouse_name;
        this.populateForm(this.spouseLetterData);
      } catch (error) {
        console.error('Error loading spouse letter:', error);
        this.viewMode = 'my';
        this.populateForm(this.myLetterData);
      } finally {
        this.loading = false;
      }
    },

    populateForm(data) {
      if (!data) return;
      Object.keys(this.formData).forEach(key => {
        if (data[key] !== undefined && data[key] !== null) {
          this.formData[key] = data[key];
        }
      });
      this.originalFormData = JSON.parse(JSON.stringify(this.formData));
    },

    async saveLetter() {
      // Allow save in preview mode for testing (backend returns fake success)
      if (this.isReadOnly) return;

      this.saving = true;
      try {
        const response = await api.put('/user/letter-to-spouse', this.formData);
        this.myLetterData = response.data.data;
        this.originalFormData = JSON.parse(JSON.stringify(this.formData));
        this.isEditing = false;
        this.$emit('success', 'Letter saved successfully');
      } catch (error) {
        console.error('Error saving letter:', error);
        this.$emit('error', 'Failed to save letter');
      } finally {
        this.saving = false;
      }
    },

    cancelEditing() {
      if (this.originalFormData) {
        this.formData = JSON.parse(JSON.stringify(this.originalFormData));
      }
      this.isEditing = false;
    },

    generatePDF() {
      this.generatingPdf = true;

      // Open a new window with clean letter content only
      const printWindow = window.open('', '_blank', 'width=800,height=600');

      if (!printWindow) {
        alert('Please allow pop-ups to print the letter');
        this.generatingPdf = false;
        return;
      }

      // Build the clean letter HTML
      const letterHtml = this.buildLetterHtml();

      // Write to the new window
      printWindow.document.write(letterHtml);
      printWindow.document.close();

      // Wait for content to load, then print
      printWindow.onload = () => {
        setTimeout(() => {
          printWindow.print();
          // Close the window after printing (or if cancelled)
          printWindow.onafterprint = () => {
            printWindow.close();
          };
          // Fallback: close after a delay if onafterprint not supported
          setTimeout(() => {
            if (!printWindow.closed) {
              printWindow.close();
            }
          }, 1000);
          this.generatingPdf = false;
        }, 250);
      };
    },

    buildLetterHtml() {
      const title = this.isExpressionOfWishes ? 'Expression of Wishes' : 'Letter to Spouse';
      const userName = this.userName;
      const date = this.currentDate;

      return `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title> </title>
  <style>
    @page {
      size: A4;
      margin: 12mm 0 0 0;
    }

    @page :first {
      margin-top: 0;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 11px;
      line-height: 1.4;
      color: #1f2937;
      background: white;
      padding: 15mm 12mm;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 15px;
      margin-bottom: 20px;
      border-bottom: 3px solid #0ea5e9;
    }

    .header-left h1 {
      font-size: 26px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 4px;
    }

    .header-left .subtitle {
      font-size: 13px;
      color: #64748b;
    }

    .header-left .date {
      font-size: 11px;
      color: #94a3b8;
      margin-top: 2px;
    }

    .logo {
      height: 120px;
      width: auto;
    }

    .section {
      margin-bottom: 18px;
      page-break-inside: auto;
    }

    .section-title {
      font-size: 15px;
      font-weight: 700;
      color: #0f172a;
      padding-bottom: 6px;
      margin-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
      page-break-after: avoid;
    }

    .subsection-title {
      font-size: 12px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 8px;
      page-break-after: avoid;
    }

    .card-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 12px;
    }

    .card-grid-3 {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 8px;
      margin-bottom: 12px;
    }

    .card {
      background: white;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      padding: 10px;
      page-break-inside: avoid;
    }

    .card-highlight {
      background: white;
      border: 2px solid #0ea5e9;
    }

    .card-label {
      font-size: 9px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .card-label-highlight {
      color: #0369a1;
    }

    .card-value {
      font-size: 12px;
      font-weight: 500;
      color: #1f2937;
    }

    .card-detail {
      font-size: 10px;
      color: #64748b;
      margin-top: 3px;
    }

    .card-amount {
      font-size: 13px;
      font-weight: 700;
      color: #1f2937;
      margin-top: 4px;
    }

    .card-amount-green {
      color: #16a34a;
    }

    .card-amount-red {
      color: #dc2626;
    }

    .info-box {
      background: white;
      border: 1px solid #d1d5db;
      border-left: 3px solid #0ea5e9;
      padding: 10px 12px;
      margin-bottom: 12px;
      border-radius: 0 6px 6px 0;
    }

    .action-item {
      display: flex;
      align-items: flex-start;
      margin-bottom: 6px;
    }

    .action-number {
      background: #0ea5e9;
      color: white;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 700;
      margin-right: 8px;
      flex-shrink: 0;
    }

    .action-text {
      font-size: 11px;
      color: #374151;
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 9px;
      font-weight: 600;
    }

    .badge-blue { background: #dbeafe; color: #1e40af; }
    .badge-green { background: #dcfce7; color: #166534; }
    .badge-purple { background: #f3e8ff; color: #7c3aed; }
    .badge-indigo { background: #e0e7ff; color: #4338ca; }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .total-amount {
      font-size: 14px;
      font-weight: 700;
    }

    .pre-wrap {
      white-space: pre-wrap;
      font-size: 10px;
    }

    .footer {
      margin-top: 30px;
      padding-top: 12px;
      border-top: 1px solid #e2e8f0;
      text-align: center;
      font-size: 9px;
      color: #94a3b8;
    }

    .mt-8 { margin-top: 8px; }
    .mb-8 { margin-bottom: 8px; }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-left">
      <h1>${title}</h1>
      <div class="subtitle">Prepared by ${userName}</div>
      <div class="date">Generated: ${date}</div>
    </div>
    <img src="${this.logoUrl}" alt="Fynla" class="logo" />
  </div>

  <!-- Part 1: What to Do Immediately -->
  <div class="section">
    <div class="section-title">Part 1: What to Do Immediately</div>

    ${this.formData.immediate_actions ? `
    <div class="subsection-title">Immediate Actions</div>
    <div class="info-box">
      ${this.parsedImmediateActions.map((action, i) => `
        <div class="action-item">
          <div class="action-number">${i + 1}</div>
          <div class="action-text">${action}</div>
        </div>
      `).join('')}
    </div>
    ` : ''}

    <div class="subsection-title">Key Contacts</div>
    <div class="card-grid">
      <div class="card">
        <div class="card-label">Executor</div>
        <div class="card-value">${this.displayExecutorName || 'Not specified'}</div>
        ${this.formData.executor_contact ? `<div class="card-detail">${this.formData.executor_contact}</div>` : ''}
      </div>
      <div class="card">
        <div class="card-label">Solicitor</div>
        <div class="card-value">${this.formData.attorney_name || 'Not specified'}</div>
        ${this.formData.attorney_contact ? `<div class="card-detail">${this.formData.attorney_contact}</div>` : ''}
      </div>
      <div class="card">
        <div class="card-label">Financial Adviser</div>
        <div class="card-value">${this.formData.financial_advisor_name || 'Not specified'}</div>
        ${this.formData.financial_advisor_contact ? `<div class="card-detail">${this.formData.financial_advisor_contact}</div>` : ''}
      </div>
      <div class="card">
        <div class="card-label">Accountant</div>
        <div class="card-value">${this.formData.accountant_name || 'Not specified'}</div>
        ${this.formData.accountant_contact ? `<div class="card-detail">${this.formData.accountant_contact}</div>` : ''}
      </div>
    </div>

    ${this.formData.immediate_funds_access ? `
    <div class="card mb-8">
      <div class="card-label">Accessing Immediate Funds</div>
      <div class="pre-wrap">${this.formData.immediate_funds_access}</div>
    </div>
    ` : ''}

    ${this.formData.employer_hr_contact ? `
    <div class="card">
      <div class="card-label">Employer HR Contact</div>
      <div class="card-value">${this.formData.employer_hr_contact}</div>
      ${this.formData.employer_benefits_info ? `<div class="card-detail">${this.formData.employer_benefits_info}</div>` : ''}
    </div>
    ` : ''}
  </div>

  <!-- Part 2: Financial Overview -->
  <div class="section">
    <div class="section-title">Part 2: Financial Overview</div>

    ${this.buildFinancialHtml('Bank Accounts & Savings', this.profileData.savings, this.profileData.totalSavings, 'savings')}
    ${this.buildFinancialHtml('Pensions', this.profileData.pensions, this.profileData.totalPensions, 'pensions')}
    ${this.buildFinancialHtml('Investments', this.profileData.investments, this.profileData.totalInvestments, 'investments')}
    ${this.buildFinancialHtml('Properties', this.profileData.properties, this.profileData.totalPropertyValue, 'properties')}
    ${this.buildFinancialHtml('Life Insurance & Protection', this.profileData.policies, this.profileData.totalCoverage, 'policies')}
    ${this.buildFinancialHtml('Liabilities & Debts', this.profileData.liabilities, this.profileData.totalLiabilities, 'liabilities')}
    ${this.buildBequestsHtml()}
  </div>

  <!-- Part 3: Additional Information -->
  <div class="section">
    <div class="section-title">Part 3: Additional Information</div>
    <div class="card-grid">
      ${this.buildInfoCardHtml('Password Manager / Online Access', this.formData.password_manager_info)}
      ${this.buildInfoCardHtml('Estate Documents Location', this.formData.estate_documents_location)}
      ${this.buildInfoCardHtml('Vehicles', this.formData.vehicles_info)}
      ${this.buildInfoCardHtml('Valuable Items', this.formData.valuable_items_info)}
      ${this.buildInfoCardHtml('Cryptocurrency', this.formData.cryptocurrency_info)}
      ${this.buildInfoCardHtml('Recurring Bills', this.formData.recurring_bills_info)}
      ${this.formData.additional_boxes.map(box => this.buildInfoCardHtml(box.title, box.content)).join('')}
    </div>
  </div>

  <!-- Part 4: Funeral and Final Wishes -->
  <div class="section">
    <div class="section-title">Part 4: Funeral and Final Wishes</div>
    <div class="card-grid">
      <div class="card">
        <div class="card-label">Funeral Preference</div>
        <div class="mt-8">
          <span class="badge ${this.formData.funeral_preference === 'cremation' ? 'badge-green' : this.formData.funeral_preference === 'burial' ? 'badge-blue' : ''}">${this.formatFuneralPreference(this.formData.funeral_preference)}</span>
        </div>
      </div>
      ${this.buildInfoCardHtml('Funeral Service Details', this.formData.funeral_service_details)}
      ${this.buildInfoCardHtml('Obituary Wishes', this.formData.obituary_wishes)}
      ${this.buildInfoCardHtml('Additional Wishes', this.formData.additional_wishes)}
    </div>
  </div>

  <div class="footer">
    This document was generated by Fynla Financial Planning Software &bull; www.fynla.org
  </div>
</body>
</html>`;
    },

    buildInfoCardHtml(title, content) {
      if (!content) return '';
      return `
        <div class="card">
          <div class="card-label">${title}</div>
          <div class="pre-wrap">${content}</div>
        </div>
      `;
    },

    buildFinancialHtml(title, items, total, type) {
      if (!items || items.length === 0) return '';

      const isLiability = type === 'liabilities';
      const isPolicy = type === 'policies';
      const amountClass = isLiability ? 'card-amount-red' : isPolicy ? 'card-amount-green' : '';

      const itemsHtml = items.map(item => {
        let name, value, subtext, badge = '';

        switch (type) {
          case 'savings':
            name = item.account_name || item.provider;
            value = item.current_balance;
            subtext = item.institution || item.provider;
            if (item.is_isa || item.account_type === 'cash_isa') badge = '<span class="badge badge-green">ISA</span>';
            break;
          case 'pensions':
            name = item.scheme_name || item.provider;
            value = item.current_value || item.current_fund_value;
            subtext = item.provider;
            badge = item.pension_type === 'db' ? '<span class="badge badge-indigo">DB</span>' : '<span class="badge badge-blue">DC</span>';
            break;
          case 'investments':
            name = item.account_name || item.provider;
            value = item.current_value;
            subtext = item.provider;
            if (item.account_type === 'stocks_and_shares_isa') badge = '<span class="badge badge-green">ISA</span>';
            break;
          case 'properties':
            name = item.property_name || item.address_line_1;
            value = item.current_value;
            subtext = this.formatPropertyType(item.property_type);
            break;
          case 'policies':
            name = item.provider;
            value = item.sum_assured || item.benefit_amount;
            subtext = this.formatPolicyType(item.policy_type);
            break;
          case 'liabilities':
            name = item.liability_name;
            value = item.current_balance;
            subtext = this.formatLiabilityType(item.liability_type);
            break;
        }

        return `
          <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
              <div class="card-value">${name}</div>
              ${badge}
            </div>
            <div class="card-detail">${subtext}</div>
            <div class="card-amount ${amountClass}">${this.formatCurrency(value)}</div>
          </div>
        `;
      }).join('');

      return `
        <div class="mb-8">
          <div class="total-row">
            <div class="subsection-title" style="margin-bottom: 0;">${title}</div>
            <div class="total-amount ${amountClass}">${this.formatCurrency(total)}</div>
          </div>
          <div class="card-grid-3">${itemsHtml}</div>
        </div>
      `;
    },

    buildBequestsHtml() {
      if (!this.willData || !this.willData.bequests || this.willData.bequests.length === 0) return '';

      const itemsHtml = this.willData.bequests.map(bequest => {
        const badgeClass = bequest.bequest_type === 'percentage' ? 'badge-blue'
          : bequest.bequest_type === 'specific_amount' ? 'badge-green' : 'badge-purple';

        const valueText = bequest.bequest_type === 'percentage'
          ? `${bequest.percentage_of_estate}% of estate`
          : bequest.bequest_type === 'specific_amount'
            ? this.formatCurrency(bequest.specific_amount)
            : bequest.specific_asset_description;

        return `
          <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
              <div class="card-value">${bequest.beneficiary_name}</div>
              <span class="badge ${badgeClass}">${this.formatBequestType(bequest.bequest_type)}</span>
            </div>
            <div class="card-amount">${valueText}</div>
            ${bequest.conditions ? `<div class="card-detail" style="font-style: italic;">${bequest.conditions}</div>` : ''}
          </div>
        `;
      }).join('');

      return `
        <div class="mb-8">
          <div class="total-row">
            <div class="subsection-title" style="margin-bottom: 0;">Bequests & Legacies</div>
            <span class="badge badge-blue">From Will</span>
          </div>
          <div class="card-grid-3">${itemsHtml}</div>
        </div>
      `;
    },

    formatFuneralPreference(value) {
      const options = {
        not_specified: 'Not Specified',
        burial: 'Burial',
        cremation: 'Cremation',
      };
      return options[value] || 'Not Specified';
    },

    formatPropertyType(type) {
      const types = { main_residence: 'Main Residence', secondary_residence: 'Secondary', buy_to_let: 'Buy to Let' };
      return types[type] || type;
    },

    formatOwnershipType(type) {
      const types = { individual: 'Sole', joint: 'Joint', tenants_in_common: 'TIC' };
      return types[type] || type;
    },

    ownershipBadgeClass(type) {
      if (type === 'joint') return 'bg-blue-100 text-blue-700';
      if (type === 'tenants_in_common') return 'bg-purple-100 text-purple-700';
      return 'bg-gray-100 text-gray-700';
    },

    formatPolicyType(type) {
      const types = { life: 'Life Insurance', critical_illness: 'Critical Illness', income_protection: 'Income Protection' };
      return types[type] || type;
    },

    formatLiabilityType(type) {
      const types = {
        mortgage: 'Mortgage', personal_loan: 'Personal Loan', credit_card: 'Credit Card',
        student_loan: 'Student Loan', car_loan: 'Car Loan', other: 'Other'
      };
      return types[type] || type;
    },

    formatPensionType(type) {
      const types = {
        dc: 'DC Pension',
        db: 'DB Pension',
        sipp: 'SIPP',
        personal: 'Personal Pension',
        workplace: 'Workplace',
      };
      return types[type] || type || 'Pension';
    },

    getPensionTypeBadgeClass(type) {
      if (type === 'db') return 'bg-indigo-100 text-indigo-700';
      if (type === 'sipp') return 'bg-blue-100 text-blue-700';
      return 'bg-blue-100 text-blue-700';
    },

    formatBequestType(type) {
      const types = {
        percentage: 'Share',
        specific_amount: 'Legacy',
        specific_asset: 'Asset',
      };
      return types[type] || type || 'Bequest';
    },

    getBequestTypeBadgeClass(type) {
      if (type === 'percentage') return 'bg-blue-100 text-blue-700';
      if (type === 'specific_amount') return 'bg-green-100 text-green-700';
      if (type === 'specific_asset') return 'bg-purple-100 text-purple-700';
      return 'bg-gray-100 text-gray-700';
    },

    /**
     * Add a new additional information box
     */
    addAdditionalBox() {
      if (this.canAddMoreBoxes) {
        this.formData.additional_boxes.push({ title: '', content: '' });
      }
    },

    /**
     * Remove an additional information box by index
     */
    removeAdditionalBox(index) {
      this.formData.additional_boxes.splice(index, 1);
    },
  },
};
</script>

<style scoped>
/* No special styles needed - PDF is generated in a separate window */
</style>
