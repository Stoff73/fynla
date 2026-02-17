<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-gray-900">Tax Configuration Admin</h2>
        <p class="text-sm text-gray-600 mt-1">
          Manage UK tax rates and allowances for different tax years
        </p>
      </div>
      <button
        @click="showCreateModal = true"
        class="px-4 py-2 bg-primary-600 text-white rounded-button hover:bg-primary-700 transition-colors"
      >
        Create New Tax Year
      </button>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="rounded-md bg-red-50 border border-red-200 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-red-800">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div
      v-if="successMessage"
      class="rounded-md bg-green-50 border border-green-200 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-green-800">{{ successMessage }}</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Content -->
    <div v-else-if="!error && currentConfig" class="space-y-6">
      <!-- Current Active Configuration Card -->
      <div class="card bg-blue-50 border-blue-200">
        <div class="flex items-start justify-between">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h3 class="text-sm font-medium text-blue-900">Active Tax Configuration</h3>
              <div class="mt-2 text-sm text-blue-800">
                <p><strong>Tax Year:</strong> {{ currentConfig.tax_year }}</p>
                <p><strong>Effective:</strong> {{ formatDate(currentConfig.effective_from) }} - {{ formatDate(currentConfig.effective_to) }}</p>
              </div>
            </div>
          </div>
          <button
            v-if="!isEditing"
            @click="startEditing"
            class="px-3 py-1 bg-primary-600 text-white text-sm rounded hover:bg-primary-700 transition-colors"
          >
            Edit Configuration
          </button>
          <div v-else class="flex gap-2">
            <button
              @click="saveChanges"
              :disabled="saving || !isFormValid"
              :class="[
                'px-3 py-1 text-white text-sm rounded transition-colors',
                (saving || !isFormValid) ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'
              ]"
              :title="!isFormValid ? 'Please fix validation errors before saving' : ''"
            >
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
            <button
              @click="cancelEditing"
              :disabled="saving"
              class="px-3 py-1 bg-gray-500 text-white text-sm rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200">
        <nav class="flex space-x-8 overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === tab.id
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="space-y-6">
        <!-- Income Tax & NI Tab -->
        <div v-if="activeTab === 'income-ni'">
          <!-- Income Tax Section -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Income Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <!-- Personal Allowance -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Personal Allowance (£)
                </label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.income_tax.personal_allowance"
                  type="number"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
                <p v-else class="text-gray-900 font-medium">
                  £{{ formatNumber(currentConfig.income_tax.personal_allowance) }}
                </p>
              </div>

              <!-- Income Tax Bands -->
              <div>
                <h4 class="text-sm font-medium text-gray-900 mb-3">Tax Bands</h4>
                <div class="space-y-3">
                  <div
                    v-for="(band, index) in (isEditing ? editableConfig.income_tax.bands : currentConfig.income_tax.bands)"
                    :key="index"
                    class="grid grid-cols-1 md:grid-cols-4 gap-3 p-3 bg-gray-50 rounded-lg"
                  >
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Band Name</label>
                      <input
                        v-if="isEditing"
                        v-model="editableConfig.income_tax.bands[index].name"
                        type="text"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                      />
                      <p v-else class="text-sm font-medium">{{ band.name }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Lower Limit (£)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.income_tax.bands[index].lower_limit"
                        type="number"
                        step="1"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                      />
                      <p v-else class="text-sm">{{ formatCurrency(band.lower_limit) }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Upper Limit (£)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.income_tax.bands[index].upper_limit"
                        type="number"
                        step="1"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                      />
                      <p v-else class="text-sm">{{ formatCurrency(band.upper_limit) }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Rate (%)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.income_tax.bands[index].rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                      />
                      <p v-else class="text-sm font-semibold text-primary-600">{{ band.rate }}%</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- National Insurance Section -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">National Insurance</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Class 1 Employee -->
                <div class="space-y-3">
                  <h4 class="font-medium text-gray-900">Class 1 (Employee)</h4>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Primary Threshold (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.primary_threshold"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_1.employee.primary_threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Upper Earnings Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.upper_earnings_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_1.employee.upper_earnings_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Main Rate (as decimal, e.g., 0.12 for 12%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.main_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.national_insurance.class_1.employee.main_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Additional Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.additional_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.national_insurance.class_1.employee.additional_rate * 100).toFixed(2) }}%</p>
                  </div>

                  <div class="pt-3 border-t">
                    <h5 class="font-medium text-gray-900 mb-2">Employer Contributions</h5>
                    <div class="space-y-2">
                      <div>
                        <label class="block text-sm text-gray-700 mb-1">Secondary Threshold (£)</label>
                        <input
                          v-if="isEditing"
                          v-model.number="editableConfig.national_insurance.class_1.employer.secondary_threshold"
                          type="number"
                          step="1"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        />
                        <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_1.employer.secondary_threshold) }}</p>
                      </div>
                      <div>
                        <label class="block text-sm text-gray-700 mb-1">Rate (as decimal)</label>
                        <input
                          v-if="isEditing"
                          v-model.number="editableConfig.national_insurance.class_1.employer.rate"
                          type="number"
                          step="0.0001"
                          min="0"
                          max="1"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        />
                        <p v-else class="font-semibold text-gray-900">{{ (currentConfig.national_insurance.class_1.employer.rate * 100).toFixed(2) }}%</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Class 4 Self-Employed -->
                <div class="space-y-3">
                  <h4 class="font-medium text-gray-900">Class 4 (Self-Employed)</h4>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Lower Profits Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.lower_profits_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_4.lower_profits_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Upper Profits Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.upper_profits_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_4.upper_profits_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Main Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.main_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.national_insurance.class_4.main_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Additional Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.additional_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.national_insurance.class_4.additional_rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Savings & Investments Tab -->
        <div v-if="activeTab === 'savings-investments'">
          <!-- ISA Allowances -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">ISA Allowances</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Annual Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.isa.annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.isa.annual_allowance) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Junior ISA Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.isa.junior_isa.annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.isa.junior_isa.annual_allowance) }}</p>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 mb-3">Lifetime ISA</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Annual Allowance (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.isa.lifetime_isa.annual_allowance"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.isa.lifetime_isa.annual_allowance) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Government Bonus Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.isa.lifetime_isa.government_bonus_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.isa.lifetime_isa.government_bonus_rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Capital Gains Tax -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Capital Gains Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Annual Exempt Amount (£)</label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.capital_gains_tax.annual_exempt_amount"
                  type="number"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
                <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax.annual_exempt_amount) }}</p>
              </div>

              <!-- Individual Rates -->
              <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Individual Taxpayers</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Basic Rate Taxpayer (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.basic_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.capital_gains_tax.basic_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Higher Rate Taxpayer (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.higher_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.capital_gains_tax.higher_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Residential Property Basic Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.residential_property_basic_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.capital_gains_tax.residential_property_basic_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Residential Property Higher Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.residential_property_higher_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.capital_gains_tax.residential_property_higher_rate }}%</p>
                  </div>
                </div>
              </div>

              <!-- Trust Rates -->
              <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Trusts</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust Capital Gains Tax Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.trust_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.capital_gains_tax.trust_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust Annual Exempt Amount (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.trust_annual_exempt_amount"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax.trust_annual_exempt_amount) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Vulnerable Beneficiary Exempt Amount (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.trust_vulnerable_beneficiary_exempt_amount"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax.trust_vulnerable_beneficiary_exempt_amount) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Dividend Tax -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Dividend Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dividend Allowance (£) - Individuals Only</label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.dividend_tax.allowance"
                  type="number"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
                <p v-else class="font-medium">£{{ formatNumber(currentConfig.dividend_tax.allowance) }}</p>
              </div>

              <!-- Individual Rates -->
              <div class="mb-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Individual Taxpayers</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Basic Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.basic_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.basic_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Higher Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.higher_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.higher_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Additional Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.additional_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.additional_rate }}%</p>
                  </div>
                </div>
              </div>

              <!-- Trust Rates -->
              <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Trusts</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust Dividend Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_dividend_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.trust_dividend_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust Other Income Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_other_income_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.trust_other_income_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust De Minimis Allowance (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_de_minimis_allowance"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.dividend_tax.trust_de_minimis_allowance) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust Management Expenses - Dividend (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_management_expenses_dividend_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.trust_management_expenses_dividend_rate }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Trust Management Expenses - Other (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_management_expenses_other_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ currentConfig.dividend_tax.trust_management_expenses_other_rate }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pensions Tab -->
        <div v-if="activeTab === 'pensions'">
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Pension Allowances</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Annual Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.pension.annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.annual_allowance) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Money Purchase Annual Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.pension.money_purchase_annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.money_purchase_annual_allowance) }}</p>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 mb-3">Tapered Annual Allowance</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Threshold Income (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.pension.tapered_annual_allowance.threshold_income"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.tapered_annual_allowance.threshold_income) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Adjusted Income (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.pension.tapered_annual_allowance.adjusted_income"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.tapered_annual_allowance.adjusted_income) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Minimum Allowance (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.pension.tapered_annual_allowance.minimum_allowance"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.tapered_annual_allowance.minimum_allowance) }}</p>
                  </div>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 mb-3">Lifetime Allowance</h4>
                <div class="flex items-center gap-4">
                  <div v-if="isEditing" class="flex items-center">
                    <input
                      v-model="editableConfig.pension.lifetime_allowance_abolished"
                      type="checkbox"
                      class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                    />
                    <label class="ml-2 text-sm text-gray-700">Lifetime Allowance Abolished</label>
                  </div>
                  <p v-else class="text-sm">
                    <span class="font-medium">Status:</span>
                    <span :class="currentConfig.pension.lifetime_allowance_abolished ? 'text-green-600' : 'text-gray-600'">
                      {{ currentConfig.pension.lifetime_allowance_abolished ? 'Abolished' : 'Active' }}
                    </span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Inheritance Tax Tab -->
        <div v-if="activeTab === 'inheritance-tax'">
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Inheritance Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Nil Rate Band (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.nil_rate_band"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax.nil_rate_band) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Residence Nil Rate Band (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.residence_nil_rate_band"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax.residence_nil_rate_band) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Residence Nil Rate Band Taper Threshold (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.rnrb_taper_threshold"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax.rnrb_taper_threshold) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Standard Rate (as decimal)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.standard_rate"
                    type="number"
                    step="0.0001"
                    min="0"
                    max="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-semibold text-primary-600">{{ (currentConfig.inheritance_tax.standard_rate * 100).toFixed(2) }}%</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reduced Rate (Charity) (as decimal)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.reduced_rate_charity"
                    type="number"
                    step="0.0001"
                    min="0"
                    max="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-semibold text-primary-600">{{ (currentConfig.inheritance_tax.reduced_rate_charity * 100).toFixed(2) }}%</p>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 mb-3">Gifting Exemptions</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Annual Exemption (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.gifting_exemptions.annual_exemption"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions.annual_exemption) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Small Gifts Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.gifting_exemptions.small_gifts_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions.small_gifts_limit) }}</p>
                  </div>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 mb-3">Potentially Exempt Transfers</h4>
                <div class="mb-4">
                  <label class="block text-sm text-gray-700 mb-1">Years to Full Exemption</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.years_to_exemption"
                    type="number"
                    step="1"
                    min="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">{{ currentConfig.inheritance_tax?.potentially_exempt_transfers?.years_to_exemption }} years</p>
                </div>

                <h5 class="text-sm font-medium text-gray-900 mb-2">Taper Relief Schedule</h5>
                <p class="text-xs text-gray-600 mb-3">Tax rate for gifts made within 7 years of death</p>
                <div class="space-y-2">
                  <div
                    v-for="(relief, index) in (isEditing ? editableConfig.inheritance_tax?.potentially_exempt_transfers?.taper_relief : currentConfig.inheritance_tax?.potentially_exempt_transfers?.taper_relief)"
                    :key="index"
                    class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg"
                  >
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Years Before Death</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.taper_relief[index].years"
                        type="number"
                        step="1"
                        min="1"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                      />
                      <p v-else class="text-sm font-medium">{{ relief.years }} years</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Inheritance Tax Rate (as decimal)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.taper_relief[index].rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="1"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                      />
                      <p v-else class="text-sm font-semibold text-primary-600">{{ (relief.rate * 100).toFixed(0) }}%</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Trust IHT Charges -->
              <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 mb-3">Trust Inheritance Tax Charges</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Entry Charge (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_entry_charge"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.inheritance_tax.trust_entry_charge * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Periodic Charge Max (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_periodic_charge_max"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.inheritance_tax.trust_periodic_charge_max * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Exit Charge Max (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_exit_charge_max"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-semibold text-primary-600">{{ (currentConfig.inheritance_tax.trust_exit_charge_max * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">No Exit Charge Period (months)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_no_exit_charge_period"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">{{ currentConfig.inheritance_tax.trust_no_exit_charge_period }} months</p>
                  </div>
                  <div>
                    <label class="block text-sm text-gray-700 mb-1">Will Trust No Exit Charge Period (months)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_will_no_exit_charge_period"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="font-medium">{{ currentConfig.inheritance_tax.trust_will_no_exit_charge_period }} months</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Property/SDLT Tab -->
        <div v-if="activeTab === 'property'">
          <!-- Standard Residential SDLT -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Standard Residential Stamp Duty Land Tax</h3>
            </div>
            <div class="px-6 py-4">
              <div v-for="(band, index) in (isEditing ? editableConfig.stamp_duty?.residential?.standard?.bands : currentConfig.stamp_duty?.residential?.standard?.bands)" :key="index" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg mb-3">
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Threshold (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.standard.bands[index].threshold"
                    type="number"
                    step="1"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="text-sm font-medium">{{ formatCurrency(band.threshold) }}</p>
                </div>
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Rate (%)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.standard.bands[index].rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="1"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="text-sm font-semibold text-primary-600">{{ (band.rate * 100).toFixed(2) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Properties SDLT -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Additional Properties Stamp Duty Land Tax</h3>
              <p class="text-sm text-gray-600 mt-1">Second homes and buy-to-let properties</p>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Dwelling Surcharge (as decimal)</label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.stamp_duty.residential.additional_properties.surcharge"
                  type="number"
                  step="0.01"
                  min="0"
                  max="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
                <p v-else class="font-semibold text-primary-600">{{ (currentConfig.stamp_duty?.residential?.additional_properties?.surcharge * 100).toFixed(2) }}%</p>
              </div>

              <div>
                <h4 class="text-sm font-medium text-gray-900 mb-3">Stamp Duty Land Tax Bands</h4>
                <div v-for="(band, index) in (isEditing ? editableConfig.stamp_duty?.residential?.additional_properties?.bands : currentConfig.stamp_duty?.residential?.additional_properties?.bands)" :key="index" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg mb-3">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Threshold (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.additional_properties.bands[index].threshold"
                      type="number"
                      step="1"
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="text-sm font-medium">{{ formatCurrency(band.threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.additional_properties.bands[index].rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="text-sm font-semibold text-primary-600">{{ (band.rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- First-Time Buyers Relief -->
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">First-Time Buyers Relief</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Max Property Value (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.max_property_value"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.stamp_duty?.residential?.first_time_buyers?.max_property_value) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Nil Rate Threshold (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.nil_rate_threshold"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.stamp_duty?.residential?.first_time_buyers?.nil_rate_threshold) }}</p>
                </div>
              </div>

              <div>
                <h4 class="text-sm font-medium text-gray-900 mb-3">First-Time Buyer Stamp Duty Land Tax Bands</h4>
                <div v-for="(band, index) in (isEditing ? editableConfig.stamp_duty?.residential?.first_time_buyers?.bands : currentConfig.stamp_duty?.residential?.first_time_buyers?.bands)" :key="index" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg mb-3">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Threshold (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.bands[index].threshold"
                      type="number"
                      step="1"
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="text-sm font-medium">{{ formatCurrency(band.threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.bands[index].rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500"
                    />
                    <p v-else class="text-sm font-semibold text-primary-600">{{ (band.rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Version Management Tab -->
        <div v-if="activeTab === 'versions'">
          <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">All Tax Configurations</h3>
            </div>
            <div class="px-6 py-4">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Year</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Period</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="config in allConfigs" :key="config.id" :class="config.is_active ? 'bg-blue-50' : ''">
                      <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ config.tax_year }}
                        <span v-if="config.is_active" class="ml-2 px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-200 rounded">Active</span>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                        {{ formatDate(config.effective_from) }} - {{ formatDate(config.effective_to) }}
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm">
                        <span :class="[
                          'px-2 py-1 text-xs font-semibold rounded',
                          config.is_active ? 'text-green-800 bg-green-200' : 'text-gray-600 bg-gray-200'
                        ]">
                          {{ config.is_active ? 'Active' : 'Inactive' }}
                        </span>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          v-if="!config.is_active"
                          @click="activateConfig(config.id)"
                          class="text-green-600 hover:text-green-900 mr-3"
                        >
                          Activate
                        </button>
                        <button
                          @click="duplicateConfig(config)"
                          class="text-primary-600 hover:text-primary-900 mr-3"
                        >
                          Duplicate
                        </button>
                        <button
                          v-if="!config.is_active"
                          @click="deleteConfig(config.id)"
                          class="text-red-600 hover:text-red-900"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Duplicate Modal -->
    <div
      v-if="showCreateModal || showDuplicateModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="closeModals"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          {{ showDuplicateModal ? 'Duplicate Tax Configuration' : 'Create New Tax Configuration' }}
        </h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Year</label>
            <input
              v-model="newConfigForm.tax_year"
              type="text"
              placeholder="2026/27"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">Format: YYYY/YY (e.g., 2026/27)</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Effective From</label>
            <input
              v-model="newConfigForm.effective_from"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Effective To</label>
            <input
              v-model="newConfigForm.effective_to"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="closeModals"
            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="showDuplicateModal ? submitDuplicate() : submitCreate()"
            :disabled="creating || !isNewConfigFormValid"
            :class="[
              'px-4 py-2 text-white rounded-lg transition-colors',
              (creating || !isNewConfigFormValid) ? 'bg-gray-400 cursor-not-allowed' : 'bg-primary-600 hover:bg-primary-700'
            ]"
            :title="!isNewConfigFormValid ? 'Please fill in all required fields with valid data' : ''"
          >
            {{ creating ? 'Creating...' : (showDuplicateModal ? 'Duplicate' : 'Create') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import taxSettingsService from '../../services/taxSettingsService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxSettings',
  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      error: null,
      successMessage: null,
      currentConfig: null,
      allConfigs: [],
      isEditing: false,
      editableConfig: null,
      saving: false,
      creating: false,
      activeTab: 'income-ni',
      showCreateModal: false,
      showDuplicateModal: false,
      configToDuplicate: null,
      newConfigForm: {
        tax_year: '',
        effective_from: '',
        effective_to: '',
      },
      validationErrors: [],
      tabs: [
        { id: 'income-ni', label: 'Income Tax & National Insurance' },
        { id: 'savings-investments', label: 'Savings & Investments' },
        { id: 'pensions', label: 'Pensions' },
        { id: 'inheritance-tax', label: 'Inheritance Tax' },
        { id: 'property', label: 'Property/Stamp Duty Land Tax' },
        { id: 'versions', label: 'Version Management' },
      ],
    };
  },

  computed: {
    isFormValid() {
      if (!this.isEditing || !this.editableConfig) return true;

      const errors = this.validateConfig(this.editableConfig);
      return errors.length === 0;
    },

    isNewConfigFormValid() {
      if (!this.newConfigForm.tax_year || !this.newConfigForm.effective_from || !this.newConfigForm.effective_to) {
        return false;
      }

      // Validate tax year format (YYYY/YY)
      const taxYearRegex = /^\d{4}\/\d{2}$/;
      if (!taxYearRegex.test(this.newConfigForm.tax_year)) {
        return false;
      }

      // Validate dates
      const fromDate = new Date(this.newConfigForm.effective_from);
      const toDate = new Date(this.newConfigForm.effective_to);

      return fromDate < toDate;
    },
  },

  mounted() {
    this.loadData();
  },

  methods: {
    async loadData() {
      this.loading = true;
      this.error = null;

      try {
        const [configResponse, allConfigsResponse] = await Promise.all([
          taxSettingsService.getCurrent(),
          taxSettingsService.getAll(),
        ]);

        if (configResponse.data.success) {
          this.currentConfig = configResponse.data.data;
        } else {
          this.error = configResponse.data.message || 'Failed to load tax configuration';
          return;
        }

        if (allConfigsResponse.data.success) {
          this.allConfigs = allConfigsResponse.data.data;
        }
      } catch (error) {
        console.error('Failed to load tax settings:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to load tax settings';
      } finally {
        this.loading = false;
      }
    },

    startEditing() {
      this.editableConfig = JSON.parse(JSON.stringify(this.currentConfig));
      this.isEditing = true;
      this.error = null;
      this.successMessage = null;
    },

    cancelEditing() {
      this.editableConfig = null;
      this.isEditing = false;
      this.error = null;
    },

    validateConfig(config) {
      const errors = [];

      // Validate Income Tax
      if (!config.income_tax?.personal_allowance || config.income_tax.personal_allowance < 0) {
        errors.push('Personal allowance must be a positive number');
      }

      if (!config.income_tax?.bands || config.income_tax.bands.length < 3) {
        errors.push('Income tax must have at least 3 bands');
      }

      // Validate tax rates (should be percentages, 0-100)
      config.income_tax?.bands?.forEach((band, index) => {
        if (band.rate < 0 || band.rate > 100) {
          errors.push(`Income tax band ${index + 1} rate must be between 0 and 100`);
        }
      });

      // Validate National Insurance rates (stored as decimals 0-1)
      const niRates = [
        config.national_insurance?.class_1?.employee?.main_rate,
        config.national_insurance?.class_1?.employee?.additional_rate,
        config.national_insurance?.class_1?.employer?.rate,
        config.national_insurance?.class_4?.main_rate,
        config.national_insurance?.class_4?.additional_rate,
      ];

      niRates.forEach((rate, index) => {
        if (rate !== undefined && (rate < 0 || rate > 1)) {
          errors.push(`National Insurance rate ${index + 1} must be between 0 and 1 (decimal format)`);
        }
      });

      // Validate IHT rates (decimals 0-1)
      if (config.inheritance_tax?.standard_rate && (config.inheritance_tax.standard_rate < 0 || config.inheritance_tax.standard_rate > 1)) {
        errors.push('Inheritance Tax standard rate must be between 0 and 1');
      }

      if (config.inheritance_tax?.reduced_rate_charity && (config.inheritance_tax.reduced_rate_charity < 0 || config.inheritance_tax.reduced_rate_charity > 1)) {
        errors.push('Inheritance Tax reduced rate must be between 0 and 1');
      }

      // Validate positive amounts
      const positiveFields = [
        { value: config.isa?.annual_allowance, name: 'ISA annual allowance' },
        { value: config.pension?.annual_allowance, name: 'Pension annual allowance' },
        { value: config.inheritance_tax?.nil_rate_band, name: 'Nil rate band' },
        { value: config.inheritance_tax?.residence_nil_rate_band, name: 'Residence nil rate band' },
        { value: config.capital_gains_tax?.annual_exempt_amount, name: 'Capital Gains Tax annual exempt amount' },
      ];

      positiveFields.forEach(field => {
        if (field.value !== undefined && field.value < 0) {
          errors.push(`${field.name} must be a positive number`);
        }
      });

      // Validate SDLT bands in ascending order
      if (config.stamp_duty?.residential?.standard?.bands) {
        const bands = config.stamp_duty.residential.standard.bands;
        for (let i = 1; i < bands.length; i++) {
          if (bands[i].threshold <= bands[i - 1].threshold) {
            errors.push('Stamp Duty Land Tax standard bands must be in ascending order');
            break;
          }
        }
      }

      if (config.stamp_duty?.residential?.additional_properties?.bands) {
        const bands = config.stamp_duty.residential.additional_properties.bands;
        for (let i = 1; i < bands.length; i++) {
          if (bands[i].threshold <= bands[i - 1].threshold) {
            errors.push('Stamp Duty Land Tax additional property bands must be in ascending order');
            break;
          }
        }
      }

      // Validate SDLT rates (decimals 0-1)
      const sdltRates = [];
      if (config.stamp_duty?.residential?.standard?.bands) {
        config.stamp_duty.residential.standard.bands.forEach((band, i) => {
          if (band.rate < 0 || band.rate > 1) {
            errors.push(`Stamp Duty Land Tax standard band ${i + 1} rate must be between 0 and 1`);
          }
        });
      }

      if (config.stamp_duty?.residential?.additional_properties?.surcharge) {
        const surcharge = config.stamp_duty.residential.additional_properties.surcharge;
        if (surcharge < 0 || surcharge > 1) {
          errors.push('Stamp Duty Land Tax additional dwelling surcharge must be between 0 and 1');
        }
      }

      // Validate PET taper relief schedule
      if (config.inheritance_tax?.potentially_exempt_transfers) {
        const pet = config.inheritance_tax.potentially_exempt_transfers;
        const yearsToExemption = pet.years_to_exemption || 7;

        if (pet.taper_relief && Array.isArray(pet.taper_relief)) {
          const reliefSchedule = pet.taper_relief;

          // Check that years are in ascending order
          for (let i = 1; i < reliefSchedule.length; i++) {
            if (reliefSchedule[i].years <= reliefSchedule[i - 1].years) {
              errors.push('Potentially Exempt Transfer taper relief years must be in ascending order');
              break;
            }
          }

          // Check that the last year matches years_to_exemption
          if (reliefSchedule.length > 0) {
            const lastYear = reliefSchedule[reliefSchedule.length - 1].years;
            if (lastYear !== yearsToExemption) {
              errors.push(`Potentially Exempt Transfer taper relief schedule must end at ${yearsToExemption} years (years to exemption)`);
            }
          }

          // Validate rates are between 0-1
          reliefSchedule.forEach((relief, i) => {
            if (relief.rate < 0 || relief.rate > 1) {
              errors.push(`Potentially Exempt Transfer taper relief year ${relief.years} rate must be between 0 and 1`);
            }
          });
        }
      }

      return errors;
    },

    async saveChanges() {
      // Validate before saving
      this.validationErrors = this.validateConfig(this.editableConfig);

      if (this.validationErrors.length > 0) {
        this.error = 'Please fix validation errors:\n' + this.validationErrors.join('\n');
        return;
      }

      this.saving = true;
      this.error = null;
      this.successMessage = null;

      try {
        const response = await taxSettingsService.update(this.currentConfig.id, {
          config_data: {
            income_tax: this.editableConfig.income_tax,
            national_insurance: this.editableConfig.national_insurance,
            isa: this.editableConfig.isa,
            capital_gains_tax: this.editableConfig.capital_gains_tax,
            dividend_tax: this.editableConfig.dividend_tax,
            pension: this.editableConfig.pension,
            inheritance_tax: this.editableConfig.inheritance_tax,
            gifting_exemptions: this.editableConfig.gifting_exemptions,
            stamp_duty: this.editableConfig.stamp_duty,
          }
        });

        if (response.data.success) {
          this.successMessage = 'Tax configuration updated successfully';
          this.currentConfig = this.editableConfig;
          this.isEditing = false;
          this.editableConfig = null;
          this.validationErrors = [];
          await this.loadData();
        } else {
          this.error = response.data.message || 'Failed to update configuration';
        }
      } catch (error) {
        console.error('Failed to save changes:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to save changes';
      } finally {
        this.saving = false;
      }
    },

    async activateConfig(configId) {
      if (!confirm('Are you sure you want to activate this tax configuration? This will deactivate the current active configuration.')) {
        return;
      }

      try {
        const response = await taxSettingsService.setActive(configId);

        if (response.data.success) {
          this.successMessage = 'Tax configuration activated successfully';
          await this.loadData();
        } else {
          this.error = response.data.message || 'Failed to activate configuration';
        }
      } catch (error) {
        console.error('Failed to activate configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to activate configuration';
      }
    },

    duplicateConfig(config) {
      this.configToDuplicate = config;
      this.showDuplicateModal = true;

      // Pre-fill form with next tax year
      const currentYear = parseInt(config.tax_year.split('/')[0]);
      this.newConfigForm.tax_year = `${currentYear + 1}/${String(currentYear + 2).slice(-2)}`;

      // Pre-fill dates with next year
      const fromDate = new Date(config.effective_from);
      const toDate = new Date(config.effective_to);
      fromDate.setFullYear(fromDate.getFullYear() + 1);
      toDate.setFullYear(toDate.getFullYear() + 1);

      this.newConfigForm.effective_from = fromDate.toISOString().split('T')[0];
      this.newConfigForm.effective_to = toDate.toISOString().split('T')[0];
    },

    async submitDuplicate() {
      this.creating = true;
      this.error = null;

      try {
        const response = await taxSettingsService.duplicate(this.configToDuplicate.id, {
          new_tax_year: this.newConfigForm.tax_year,
          effective_from: this.newConfigForm.effective_from,
          effective_to: this.newConfigForm.effective_to,
        });

        if (response.data.success) {
          this.successMessage = `Tax year ${this.newConfigForm.tax_year} created successfully`;
          this.closeModals();
          await this.loadData();
        } else {
          this.error = response.data.message || 'Failed to duplicate configuration';
        }
      } catch (error) {
        console.error('Failed to duplicate configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to duplicate configuration';
      } finally {
        this.creating = false;
      }
    },

    async submitCreate() {
      this.creating = true;
      this.error = null;

      try {
        // For now, create will use current config as template
        // In future, this could be enhanced with a full form
        this.error = 'Create from scratch not yet implemented. Please use Duplicate instead.';
      } catch (error) {
        console.error('Failed to create configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to create configuration';
      } finally {
        this.creating = false;
      }
    },

    async deleteConfig(configId) {
      if (!confirm('Are you sure you want to delete this tax configuration? This action cannot be undone.')) {
        return;
      }

      try {
        const response = await taxSettingsService.delete(configId);

        if (response.data.success) {
          this.successMessage = 'Tax configuration deleted successfully';
          await this.loadData();
        } else {
          this.error = response.data.message || 'Failed to delete configuration';
        }
      } catch (error) {
        console.error('Failed to delete configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to delete configuration';
      }
    },

    closeModals() {
      this.showCreateModal = false;
      this.showDuplicateModal = false;
      this.configToDuplicate = null;
      this.newConfigForm = {
        tax_year: '',
        effective_from: '',
        effective_to: '',
      };
    },

    formatNumber(value) {
      if (!value && value !== 0) return 'N/A';
      return value.toLocaleString('en-GB');
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    },
  },
};
</script>
