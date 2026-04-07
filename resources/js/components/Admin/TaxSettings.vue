<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-horizon-500">Tax Configuration Admin</h2>
        <p class="text-sm text-neutral-500 mt-1">
          Manage UK tax rates and allowances for different tax years
        </p>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <!-- Active Tax Year Quick-Switch Dropdown -->
        <div v-if="allConfigs.length > 0" class="flex items-center gap-2">
          <label for="active-tax-year-select" class="text-sm font-medium text-horizon-500 whitespace-nowrap">
            Active Year:
          </label>
          <select
            id="active-tax-year-select"
            :value="activeConfigId"
            @change="handleActiveYearChange($event)"
            :disabled="activating"
            class="px-3 py-2 border border-horizon-300 rounded-button bg-white text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <option
              v-for="config in sortedConfigs"
              :key="config.id"
              :value="config.id"
            >
              {{ config.tax_year }}{{ config.is_active ? ' (active)' : '' }}
            </option>
          </select>
          <svg v-if="activating" class="w-4 h-4 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
        <button
          @click="duplicateCurrentConfig"
          class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors whitespace-nowrap"
        >
          Create New Tax Year
        </button>
      </div>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="rounded-md bg-raspberry-50 border border-raspberry-200 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-raspberry-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-raspberry-800">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div
      v-if="successMessage"
      class="rounded-md bg-spring-50 border border-spring-200 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-spring-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-spring-800">{{ successMessage }}</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
    </div>

    <!-- Content -->
    <div v-else-if="!error && currentConfig" class="space-y-6">
      <!-- Current Active Configuration Card -->
      <div class="card bg-violet-50 border-violet-200">
        <div class="flex items-start justify-between">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="h-6 w-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h3 class="text-sm font-medium text-violet-900">Active Tax Configuration</h3>
              <div class="mt-2 text-sm text-violet-800">
                <p><strong>Tax Year:</strong> {{ currentConfig.tax_year }}</p>
                <p><strong>Effective:</strong> {{ formatDate(currentConfig.effective_from) }} - {{ formatDate(currentConfig.effective_to) }}</p>
              </div>
            </div>
          </div>
          <button
            v-if="!isEditing"
            @click="startEditing"
            class="px-3 py-1 bg-raspberry-600 text-white text-sm rounded hover:bg-raspberry-700 transition-colors"
          >
            Edit Configuration
          </button>
          <div v-else class="flex gap-2">
            <button
              @click="saveChanges"
              :disabled="saving || !isFormValid"
              :class="[
                'px-3 py-1 text-white text-sm rounded transition-colors',
                (saving || !isFormValid) ? 'bg-horizon-400 cursor-not-allowed' : 'bg-spring-600 hover:bg-spring-700'
              ]"
              :title="!isFormValid ? 'Please fix validation errors before saving' : ''"
            >
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
            <button
              @click="cancelEditing"
              :disabled="saving"
              class="px-3 py-1 bg-horizon-500 text-white text-sm rounded hover:bg-horizon-400 transition-colors disabled:opacity-50"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-light-gray">
        <nav class="flex space-x-8 overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === tab.id
                ? 'border-raspberry-600 text-raspberry-600'
                : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
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
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Income Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <!-- Personal Allowance -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Personal Allowance (£)
                </label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.income_tax.personal_allowance"
                  type="number"
                  step="1"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                />
                <p v-else class="text-horizon-500 font-medium">
                  £{{ formatNumber(currentConfig.income_tax.personal_allowance) }}
                </p>
              </div>

              <!-- Income Tax Bands -->
              <div>
                <h4 class="text-sm font-medium text-horizon-500 mb-3">Tax Bands</h4>
                <div class="space-y-3">
                  <div
                    v-for="(band, index) in (isEditing ? editableConfig.income_tax.bands : currentConfig.income_tax.bands)"
                    :key="band.name || `band-${band.lower_limit}-${index}`"
                    class="grid grid-cols-1 md:grid-cols-4 gap-3 p-3 bg-savannah-100 rounded-lg"
                  >
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Band Name</label>
                      <input
                        v-if="isEditing"
                        v-model="editableConfig.income_tax.bands[index].name"
                        type="text"
                        class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                      />
                      <p v-else class="text-sm font-medium">{{ band.name }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Lower Limit (£)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.income_tax.bands[index].lower_limit"
                        type="number"
                        step="1"
                        class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                      />
                      <p v-else class="text-sm">{{ formatCurrency(band.lower_limit) }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Upper Limit (£)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.income_tax.bands[index].upper_limit"
                        type="number"
                        step="1"
                        class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                      />
                      <p v-else class="text-sm">{{ band.upper_limit ? formatCurrency(band.upper_limit) : 'No limit' }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Rate (%)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.income_tax.bands[index].rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="1"
                        class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                      />
                      <p v-else class="text-sm font-semibold text-raspberry-600">{{ (band.rate * 100).toFixed(0) }}%</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Personal Allowance Taper, Savings Allowances, Blind Person's Allowance -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Allowances &amp; Reliefs</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Personal Allowance Taper Threshold (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.income_tax.personal_allowance_taper_threshold" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.income_tax?.personal_allowance_taper_threshold) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Personal Allowance Taper Rate</label>
                  <input v-if="isEditing" v-model.number="editableConfig.income_tax.personal_allowance_taper_rate" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.income_tax?.personal_allowance_taper_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Blind Person's Allowance (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.income_tax.blind_persons_allowance" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.income_tax?.blind_persons_allowance) }}</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Personal Savings Allowance</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Basic Rate Taxpayer (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.income_tax.personal_savings_allowance.basic" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.income_tax?.personal_savings_allowance?.basic) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Higher Rate Taxpayer (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.income_tax.personal_savings_allowance.higher" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.income_tax?.personal_savings_allowance?.higher) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Additional Rate Taxpayer (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.income_tax.personal_savings_allowance.additional" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.income_tax?.personal_savings_allowance?.additional) }}</p>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Starting Rate for Savings</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Band (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.income_tax.starting_rate_for_savings.band" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.income_tax?.starting_rate_for_savings?.band) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Rate</label>
                    <input v-if="isEditing" v-model.number="editableConfig.income_tax.starting_rate_for_savings.rate" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.income_tax?.starting_rate_for_savings?.rate ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Scottish Income Tax -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Scottish Income Tax</h3>
            </div>
            <div class="px-6 py-4">
              <div class="flex items-center gap-3 mb-3">
                <span class="text-sm text-neutral-500">Status:</span>
                <span :class="currentConfig.income_tax?.scotland?.enabled ? 'text-spring-600' : 'text-neutral-500'" class="font-medium">
                  {{ currentConfig.income_tax?.scotland?.enabled ? 'Enabled' : 'Not yet implemented' }}
                </span>
              </div>
              <p v-if="!currentConfig.income_tax?.scotland?.enabled" class="text-sm text-neutral-500">Scottish rate bands are not yet configured. When enabled, Scottish taxpayers will use separate income tax bands (Starter, Basic, Intermediate, Higher, Advanced, Top).</p>
              <div v-else>
                <div v-for="(band, index) in currentConfig.income_tax.scotland.bands" :key="index" class="grid grid-cols-4 gap-3 p-2 bg-savannah-100 rounded mb-2">
                  <span class="text-sm">{{ band.name }}</span>
                  <span class="text-sm">£{{ formatNumber(band.lower_limit) }}</span>
                  <span class="text-sm">{{ band.upper_limit ? '£' + formatNumber(band.upper_limit) : 'No limit' }}</span>
                  <span class="text-sm font-semibold text-raspberry-600">{{ (band.rate * 100).toFixed(0) }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- National Insurance Section -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">National Insurance</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Class 1 Employee -->
                <div class="space-y-3">
                  <h4 class="font-medium text-horizon-500">Class 1 (Employee)</h4>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Primary Threshold (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.primary_threshold"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_1.employee.primary_threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Upper Earnings Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.upper_earnings_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_1.employee.upper_earnings_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Main Rate (as decimal, e.g., 0.12 for 12%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.main_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.national_insurance.class_1.employee.main_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Additional Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_1.employee.additional_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.national_insurance.class_1.employee.additional_rate * 100).toFixed(2) }}%</p>
                  </div>

                  <div class="pt-3 border-t">
                    <h5 class="font-medium text-horizon-500 mb-2">Employer Contributions</h5>
                    <div class="space-y-2">
                      <div>
                        <label class="block text-sm text-neutral-500 mb-1">Secondary Threshold (£)</label>
                        <input
                          v-if="isEditing"
                          v-model.number="editableConfig.national_insurance.class_1.employer.secondary_threshold"
                          type="number"
                          step="1"
                          class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                        />
                        <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_1.employer.secondary_threshold) }}</p>
                      </div>
                      <div>
                        <label class="block text-sm text-neutral-500 mb-1">Rate (as decimal)</label>
                        <input
                          v-if="isEditing"
                          v-model.number="editableConfig.national_insurance.class_1.employer.rate"
                          type="number"
                          step="0.0001"
                          min="0"
                          max="1"
                          class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                        />
                        <p v-else class="font-semibold text-horizon-500">{{ (currentConfig.national_insurance.class_1.employer.rate * 100).toFixed(2) }}%</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Class 4 Self-Employed -->
                <div class="space-y-3">
                  <h4 class="font-medium text-horizon-500">Class 4 (Self-Employed)</h4>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Lower Profits Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.lower_profits_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_4.lower_profits_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Upper Profits Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.upper_profits_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.national_insurance.class_4.upper_profits_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Main Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.main_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.national_insurance.class_4.main_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Additional Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.national_insurance.class_4.additional_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.national_insurance.class_4.additional_rate * 100).toFixed(2) }}%</p>
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
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">ISA Allowances</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Annual Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.isa.annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.isa.annual_allowance) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Junior ISA Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.isa.junior_isa.annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.isa.junior_isa.annual_allowance) }}</p>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Lifetime ISA</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Annual Allowance (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.isa.lifetime_isa.annual_allowance"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.isa.lifetime_isa.annual_allowance) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Government Bonus Rate (as decimal)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.isa.lifetime_isa.government_bonus_rate"
                      type="number"
                      step="0.0001"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.isa.lifetime_isa.government_bonus_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Max Age to Open</label>
                    <p class="font-medium">{{ currentConfig.isa?.lifetime_isa?.max_age_to_open }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Early Withdrawal Penalty</label>
                    <p class="font-semibold text-raspberry-600">{{ ((currentConfig.isa?.lifetime_isa?.withdrawal_penalty ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Junior ISA Max Age</label>
                    <p class="font-medium">{{ currentConfig.isa?.junior_isa?.max_age }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Capital Gains Tax -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Capital Gains Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Annual Exempt Amount (£)</label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.capital_gains_tax.annual_exempt_amount"
                  type="number"
                  step="1"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                />
                <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax.annual_exempt_amount) }}</p>
              </div>

              <!-- Individual Rates -->
              <div class="mb-4">
                <h4 class="text-sm font-semibold text-horizon-500 mb-3">Individual Taxpayers</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Basic Rate Taxpayer (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.basic_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.capital_gains_tax.basic_rate * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Higher Rate Taxpayer (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.higher_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.capital_gains_tax.higher_rate * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Residential Property Basic Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.residential_property_basic_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.capital_gains_tax.residential_property_basic_rate * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Residential Property Higher Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.residential_property_higher_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.capital_gains_tax.residential_property_higher_rate * 100).toFixed(0) }}%</p>
                  </div>
                </div>
              </div>

              <!-- Trust Rates -->
              <div class="mt-6 pt-6 border-t border-light-gray">
                <h4 class="text-sm font-semibold text-horizon-500 mb-3">Trusts</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust Capital Gains Tax Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.trust_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.capital_gains_tax.trust_rate * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust Annual Exempt Amount (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.trust_annual_exempt_amount"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax.trust_annual_exempt_amount) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Vulnerable Beneficiary Exempt Amount (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.capital_gains_tax.trust_vulnerable_beneficiary_exempt_amount"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax.trust_vulnerable_beneficiary_exempt_amount) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- CGT Extras: Business Asset Disposal Relief & Chattels -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Business Asset Disposal Relief &amp; Chattels</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Business Asset Disposal Relief Rate</label>
                  <input v-if="isEditing" v-model.number="editableConfig.capital_gains_tax.business_asset_disposal_relief_rate" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.capital_gains_tax?.business_asset_disposal_relief_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Lifetime Limit (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.capital_gains_tax.business_asset_disposal_relief_lifetime_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax?.business_asset_disposal_relief_lifetime_limit) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Ownership (years)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.capital_gains_tax.business_asset_disposal_relief_min_ownership_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.capital_gains_tax?.business_asset_disposal_relief_min_ownership_years }} years</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Chattel Exemptions</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Chattel Exemption Threshold (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.capital_gains_tax.chattel_exemption_threshold" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax?.chattel_exemption_threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Marginal Relief Multiplier</label>
                    <input v-if="isEditing" v-model.number="editableConfig.capital_gains_tax.chattel_marginal_relief_multiplier" type="number" step="0.1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">{{ currentConfig.capital_gains_tax?.chattel_marginal_relief_multiplier }}x</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Marginal Relief Limit (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.capital_gains_tax.chattel_marginal_relief_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.capital_gains_tax?.chattel_marginal_relief_limit) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Dividend Tax -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Dividend Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Dividend Allowance (£) - Individuals Only</label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.dividend_tax.allowance"
                  type="number"
                  step="1"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                />
                <p v-else class="font-medium">£{{ formatNumber(currentConfig.dividend_tax.allowance) }}</p>
              </div>

              <!-- Individual Rates -->
              <div class="mb-4">
                <h4 class="text-sm font-semibold text-horizon-500 mb-3">Individual Taxpayers</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Basic Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.basic_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.basic_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Higher Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.higher_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.higher_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Additional Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.additional_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.additional_rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>

              <!-- Trust Rates -->
              <div class="mt-6 pt-6 border-t border-light-gray">
                <h4 class="text-sm font-semibold text-horizon-500 mb-3">Trusts</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust Dividend Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_dividend_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.trust_dividend_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust Other Income Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_other_income_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.trust_other_income_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust De Minimis Allowance (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_de_minimis_allowance"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.dividend_tax.trust_de_minimis_allowance) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust Management Expenses - Dividend (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_management_expenses_dividend_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.trust_management_expenses_dividend_rate * 100).toFixed(2) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Trust Management Expenses - Other (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.dividend_tax.trust_management_expenses_other_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.dividend_tax.trust_management_expenses_other_rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- VCT / EIS / SEIS -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Venture Capital Schemes</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <!-- VCT -->
              <div>
                <h4 class="font-medium text-horizon-500 mb-3">Venture Capital Trust</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Income Tax Relief</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.vct.income_tax_relief" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.venture_capital?.vct?.income_tax_relief ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Annual Limit (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.vct.annual_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.investment?.venture_capital?.vct?.annual_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Minimum Holding (years)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.vct.min_holding_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">{{ currentConfig.investment?.venture_capital?.vct?.min_holding_years }} years</p>
                  </div>
                </div>
                <div class="mt-2 flex gap-4">
                  <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700">Dividends: Tax-free</span>
                  <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700">Capital Gains Tax: Exempt</span>
                  <span class="text-xs px-2 py-1 rounded-full bg-raspberry-100 text-raspberry-700">Inheritance Tax: Not exempt</span>
                </div>
              </div>

              <!-- EIS -->
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Enterprise Investment Scheme</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Income Tax Relief</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.eis.income_tax_relief" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.venture_capital?.eis?.income_tax_relief ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Annual Limit (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.eis.annual_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.investment?.venture_capital?.eis?.annual_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Capital Gains Tax Exempt After (years)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.eis.cgt_exempt_after_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">{{ currentConfig.investment?.venture_capital?.eis?.cgt_exempt_after_years }} years</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Knowledge Intensive Limit (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.eis.knowledge_intensive_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.investment?.venture_capital?.eis?.knowledge_intensive_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Carry Back Years</label>
                    <p class="font-medium">{{ currentConfig.investment?.venture_capital?.eis?.carry_back_years }} year</p>
                  </div>
                </div>
                <div class="mt-2 flex gap-4 flex-wrap">
                  <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700">Capital Gains Tax: Exempt after 3 years</span>
                  <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700">Inheritance Tax: Exempt (Business Property Relief after 2 years)</span>
                  <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700">Loss Relief: Available</span>
                </div>
              </div>

              <!-- SEIS -->
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Seed Enterprise Investment Scheme</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Income Tax Relief</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.seis.income_tax_relief" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.venture_capital?.seis?.income_tax_relief ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Annual Limit (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.seis.annual_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.investment?.venture_capital?.seis?.annual_limit) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Capital Gains Tax Exempt After (years)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.venture_capital.seis.cgt_exempt_after_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">{{ currentConfig.investment?.venture_capital?.seis?.cgt_exempt_after_years }} years</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Investment Bond Tax Treatment -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Investment Bonds — Tax Treatment</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <!-- Onshore Bond -->
              <div>
                <h4 class="font-medium text-horizon-500 mb-3">Onshore Investment Bond</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Minimum Investment (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.waterfall.onshore_bond_minimum" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.investment?.waterfall?.onshore_bond_minimum) }}</p>
                  </div>
                </div>
                <div class="bg-savannah-100 rounded-lg p-4 space-y-2">
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-violet-100 text-violet-700 whitespace-nowrap">Deferred</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Income Tax on Gains</p>
                      <p class="text-xs text-neutral-500">Gains taxed as income with a 20% basic rate credit (already paid by the life fund). Basic rate taxpayers have no further tax to pay.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-violet-100 text-violet-700 whitespace-nowrap">Deferred</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">5% Tax-Deferred Withdrawals</p>
                      <p class="text-xs text-neutral-500">Withdraw up to 5% of original investment annually without immediate tax. Allowance rolls over if unused.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700 whitespace-nowrap">Relief</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Top-Slicing Relief</p>
                      <p class="text-xs text-neutral-500">Large gains spread over years held to determine effective tax rate, preventing unfair higher rate charges.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-raspberry-100 text-raspberry-700 whitespace-nowrap">Taxable</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Inheritance Tax</p>
                      <p class="text-xs text-neutral-500">Forms part of estate for Inheritance Tax. Often used with trusts for Inheritance Tax planning.</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Offshore Bond -->
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Offshore Investment Bond</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Minimum Investment (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.investment.waterfall.offshore_bond_minimum" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.investment?.waterfall?.offshore_bond_minimum) }}</p>
                  </div>
                </div>
                <div class="bg-savannah-100 rounded-lg p-4 space-y-2">
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-violet-100 text-violet-700 whitespace-nowrap">Deferred</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Income Tax on Gains</p>
                      <p class="text-xs text-neutral-500">Gains taxed at full marginal rate with no tax credit. Gross roll-up means no tax during accumulation.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-violet-100 text-violet-700 whitespace-nowrap">Deferred</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">5% Tax-Deferred Withdrawals</p>
                      <p class="text-xs text-neutral-500">Same 5% annual withdrawal allowance as onshore bonds. Cumulative unused allowance available.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-spring-100 text-spring-700 whitespace-nowrap">Relief</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Time Apportionment Relief</p>
                      <p class="text-xs text-neutral-500">Gains reduced proportionally for periods of non-UK residence. Useful for those who have lived abroad.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="text-xs px-2 py-1 rounded-full bg-raspberry-100 text-raspberry-700 whitespace-nowrap">Taxable</span>
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Inheritance Tax</p>
                      <p class="text-xs text-neutral-500">Forms part of estate for Inheritance Tax. Often used with trusts to exclude from estate.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Investment Waterfall Thresholds -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Investment Thresholds &amp; Fees</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Low Cost Ongoing Charge Figure</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.fee_benchmarks?.low_cost_ocf ?? 0) * 100).toFixed(2) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">High Cost Ongoing Charge Figure</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.fee_benchmarks?.high_cost_ocf ?? 0) * 100).toFixed(2) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Typical Platform Fee</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.fee_benchmarks?.platform_fee_typical ?? 0) * 100).toFixed(2) }}%</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Portfolio Thresholds</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Rebalancing Drift</label>
                    <p class="font-medium">{{ currentConfig.investment?.portfolio_thresholds?.rebalancing_drift_percent }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Concentration Warning</label>
                    <p class="font-medium">{{ currentConfig.investment?.portfolio_thresholds?.concentration_warning_percent }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Minimum Diversification Holdings</label>
                    <p class="font-medium">{{ currentConfig.investment?.portfolio_thresholds?.minimum_diversification_holdings }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pensions Tab -->
        <div v-if="activeTab === 'pensions'">
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Pension Allowances</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Annual Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.pension.annual_allowance"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.annual_allowance) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Money Purchase Annual Allowance (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.pension.mpaa"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.money_purchase_annual_allowance) }}</p>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Tapered Annual Allowance</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Threshold Income (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.pension.tapered_annual_allowance.threshold_income"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.tapered_annual_allowance.threshold_income) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Adjusted Income (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.pension.tapered_annual_allowance.adjusted_income"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.tapered_annual_allowance.adjusted_income) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Minimum Allowance (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.pension.tapered_annual_allowance.minimum_allowance"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension.tapered_annual_allowance.minimum_allowance) }}</p>
                  </div>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Lifetime Allowance</h4>
                <div class="flex items-center gap-4">
                  <div v-if="isEditing" class="flex items-center">
                    <input
                      v-model="editableConfig.pension.lifetime_allowance_abolished"
                      type="checkbox"
                      class="h-4 w-4 text-raspberry-600 focus:ring-violet-500 border-horizon-300 rounded"
                    />
                    <label class="ml-2 text-sm text-neutral-500">Lifetime Allowance Abolished</label>
                  </div>
                  <p v-else class="text-sm">
                    <span class="font-medium">Status:</span>
                    <span :class="currentConfig.pension.lifetime_allowance_abolished ? 'text-spring-600' : 'text-neutral-500'">
                      {{ currentConfig.pension.lifetime_allowance_abolished ? 'Abolished' : 'Active' }}
                    </span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Pension Tax Relief -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Pension Tax Relief</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Basic Rate Relief</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.pension?.tax_relief?.basic_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Higher Rate Relief</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.pension?.tax_relief?.higher_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Additional Rate Relief</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.pension?.tax_relief?.additional_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- State Pension -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">State Pension</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Full New State Pension (£/year)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.state_pension.full_new_state_pension" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension?.state_pension?.full_new_state_pension) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Current State Pension Age</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.state_pension.current_spa" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.pension?.state_pension?.current_spa }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Future State Pension Age</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.state_pension.future_spa" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.pension?.state_pension?.future_spa }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Qualifying Years</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.state_pension.qualifying_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.pension?.state_pension?.qualifying_years }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Qualifying Years</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.state_pension.minimum_qualifying_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.pension?.state_pension?.minimum_qualifying_years }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Carry Forward Years</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.carry_forward_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.pension?.carry_forward_years }} years</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Auto-Enrolment -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Auto-Enrolment</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Earnings Trigger (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.auto_enrolment.earnings_trigger" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension?.auto_enrolment?.earnings_trigger) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Lower Qualifying Earnings (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.auto_enrolment.lower_qualifying_earnings" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension?.auto_enrolment?.lower_qualifying_earnings) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Upper Qualifying Earnings (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.auto_enrolment.upper_qualifying_earnings" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.pension?.auto_enrolment?.upper_qualifying_earnings) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Employee Contribution</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.auto_enrolment.minimum_employee_contribution" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.pension?.auto_enrolment?.minimum_employee_contribution ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Employer Contribution</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.auto_enrolment.minimum_employer_contribution" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.pension?.auto_enrolment?.minimum_employer_contribution ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Total Contribution</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.auto_enrolment.minimum_total_contribution" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.pension?.auto_enrolment?.minimum_total_contribution ?? 0) * 100).toFixed(0) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Salary Sacrifice -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Salary Sacrifice &amp; Minimum Wage</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">National Living Wage (£/hr)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.salary_sacrifice.nlw_hourly" type="number" step="0.01" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ currentConfig.pension?.salary_sacrifice?.nlw_hourly }}/hr</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">NMW 21+ (£/hr)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.salary_sacrifice.nmw_hourly['21_plus']" type="number" step="0.01" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ currentConfig.pension?.salary_sacrifice?.nmw_hourly?.['21_plus'] }}/hr</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">NMW 18-20 (£/hr)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.pension.salary_sacrifice.nmw_hourly['18_to_20']" type="number" step="0.01" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ currentConfig.pension?.salary_sacrifice?.nmw_hourly?.['18_to_20'] }}/hr</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Inheritance Tax Tab -->
        <div v-if="activeTab === 'inheritance-tax'">
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Inheritance Tax</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Nil Rate Band (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.nil_rate_band"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax.nil_rate_band) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Residence Nil Rate Band (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.residence_nil_rate_band"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax.residence_nil_rate_band) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Residence Nil Rate Band Taper Threshold (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.rnrb_taper_threshold"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax.rnrb_taper_threshold) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Standard Rate (as decimal)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.standard_rate"
                    type="number"
                    step="0.0001"
                    min="0"
                    max="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.inheritance_tax.standard_rate * 100).toFixed(2) }}%</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Reduced Rate (Charity) (as decimal)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.reduced_rate_charity"
                    type="number"
                    step="0.0001"
                    min="0"
                    max="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.inheritance_tax.reduced_rate_charity * 100).toFixed(2) }}%</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Residence Nil Rate Band Taper Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.rnrb_taper_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Charity Threshold</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.charity_threshold_percent ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Spouse Exemption</label>
                  <p class="font-medium text-spring-600">{{ currentConfig.inheritance_tax?.spouse_exemption ? 'Unlimited' : 'Limited' }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Transferable Nil Rate Band</label>
                  <p class="font-medium text-spring-600">{{ currentConfig.inheritance_tax?.transferable_nil_rate_band ? 'Yes' : 'No' }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Transferable Residence Nil Rate Band</label>
                  <p class="font-medium text-spring-600">{{ currentConfig.inheritance_tax?.transferable_rnrb ? 'Yes' : 'No' }}</p>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Gifting Exemptions</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Annual Exemption (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.gifting_exemptions.annual_exemption"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions.annual_exemption) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Small Gifts Limit (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.gifting_exemptions.small_gifts_limit"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions.small_gifts_limit) }}</p>
                  </div>
                </div>
              </div>

              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Potentially Exempt Transfers</h4>
                <div class="mb-4">
                  <label class="block text-sm text-neutral-500 mb-1">Years to Full Exemption</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.years_to_exemption"
                    type="number"
                    step="1"
                    min="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">{{ currentConfig.inheritance_tax?.potentially_exempt_transfers?.years_to_exemption }} years</p>
                </div>

                <h5 class="text-sm font-medium text-horizon-500 mb-2">Taper Relief Schedule</h5>
                <p class="text-xs text-neutral-500 mb-3">Tax rate for gifts made within 7 years of death</p>
                <div class="space-y-2">
                  <div
                    v-for="(relief, index) in (isEditing ? editableConfig.inheritance_tax?.potentially_exempt_transfers?.taper_relief : currentConfig.inheritance_tax?.potentially_exempt_transfers?.taper_relief)"
                    :key="`taper-${relief.min_years}-${index}`"
                    class="grid grid-cols-1 md:grid-cols-3 gap-3 p-3 bg-savannah-100 rounded-lg"
                  >
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Years Before Death</label>
                      <div v-if="isEditing" class="flex gap-2 items-center">
                        <input
                          v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.taper_relief[index].min_years"
                          type="number"
                          step="1"
                          min="0"
                          class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                        />
                        <span class="text-xs text-neutral-500">to</span>
                        <input
                          v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.taper_relief[index].max_years"
                          type="number"
                          step="1"
                          min="0"
                          class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                        />
                      </div>
                      <p v-else class="text-sm font-medium">{{ relief.min_years }}-{{ relief.max_years ?? '7+' }} years</p>
                    </div>
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Tax Rate (as decimal)</label>
                      <input
                        v-if="isEditing"
                        v-model.number="editableConfig.inheritance_tax.potentially_exempt_transfers.taper_relief[index].tax_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="1"
                        class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                      />
                      <p v-else class="text-sm font-semibold text-raspberry-600">{{ (relief.tax_rate * 100).toFixed(0) }}%</p>
                    </div>
                    <div>
                      <label class="block text-xs text-neutral-500 mb-1">Description</label>
                      <p class="text-sm text-neutral-500">{{ relief.description }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Trust IHT Charges -->
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Trust Inheritance Tax Charges</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Entry Charge Rate</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_charges.entry.rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.trust_charges?.entry?.rate ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Periodic Charge Max Rate</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_charges.periodic.max_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.trust_charges?.periodic?.max_rate ?? 0) * 100).toFixed(1) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Exit Charge Max Rate</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_charges.exit.max_rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.trust_charges?.exit?.max_rate ?? 0) * 100).toFixed(1) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">No Exit Charge Period (months)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_charges.exit.no_charge_periods.first_quarter"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">{{ currentConfig.inheritance_tax?.trust_charges?.exit?.no_charge_periods?.first_quarter }} months</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Will Trust No Exit Charge Period (months)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.inheritance_tax.trust_charges.exit.no_charge_periods.will_trust_months"
                      type="number"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="font-medium">{{ currentConfig.inheritance_tax?.trust_charges?.exit?.no_charge_periods?.will_trust_months }} months</p>
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
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Standard Residential Stamp Duty Land Tax</h3>
            </div>
            <div class="px-6 py-4">
              <div v-for="(band, index) in (isEditing ? editableConfig.stamp_duty?.residential?.standard?.bands : currentConfig.stamp_duty?.residential?.standard?.bands)" :key="`sdlt-std-${band.threshold}-${index}`" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-savannah-100 rounded-lg mb-3">
                <div>
                  <label class="block text-xs text-neutral-500 mb-1">Threshold (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.standard.bands[index].threshold"
                    type="number"
                    step="1"
                    class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="text-sm font-medium">{{ formatCurrency(band.threshold) }}</p>
                </div>
                <div>
                  <label class="block text-xs text-neutral-500 mb-1">Rate (%)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.standard.bands[index].rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="1"
                    class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="text-sm font-semibold text-raspberry-600">{{ (band.rate * 100).toFixed(2) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Properties SDLT -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Additional Properties Stamp Duty Land Tax</h3>
              <p class="text-sm text-neutral-500 mt-1">Second homes and buy-to-let properties</p>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Additional Dwelling Surcharge (as decimal)</label>
                <input
                  v-if="isEditing"
                  v-model.number="editableConfig.stamp_duty.residential.additional_properties.surcharge"
                  type="number"
                  step="0.01"
                  min="0"
                  max="1"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                />
                <p v-else class="font-semibold text-raspberry-600">{{ (currentConfig.stamp_duty?.residential?.additional_properties?.surcharge * 100).toFixed(2) }}%</p>
              </div>

              <div>
                <h4 class="text-sm font-medium text-horizon-500 mb-3">Stamp Duty Land Tax Bands</h4>
                <div v-for="(band, index) in (isEditing ? editableConfig.stamp_duty?.residential?.additional_properties?.bands : currentConfig.stamp_duty?.residential?.additional_properties?.bands)" :key="`sdlt-add-${band.threshold}-${index}`" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-savannah-100 rounded-lg mb-3">
                  <div>
                    <label class="block text-xs text-neutral-500 mb-1">Threshold (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.additional_properties.bands[index].threshold"
                      type="number"
                      step="1"
                      class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="text-sm font-medium">{{ formatCurrency(band.threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-xs text-neutral-500 mb-1">Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.additional_properties.bands[index].rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="text-sm font-semibold text-raspberry-600">{{ (band.rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Non-Resident Surcharge -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Non-Resident Surcharge</h3>
            </div>
            <div class="px-6 py-4">
              <div>
                <label class="block text-sm text-neutral-500 mb-1">Non-Resident Surcharge Rate</label>
                <input v-if="isEditing" v-model.number="editableConfig.stamp_duty.residential.non_resident_surcharge" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.stamp_duty?.residential?.non_resident_surcharge ?? 0) * 100).toFixed(0) }}%</p>
              </div>
            </div>
          </div>

          <!-- First-Time Buyers Relief -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">First-Time Buyers Relief</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Max Property Value (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.max_property_value"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.stamp_duty?.residential?.first_time_buyers?.max_property_value) }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Nil Rate Threshold (£)</label>
                  <input
                    v-if="isEditing"
                    v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.nil_rate_threshold"
                    type="number"
                    step="1"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
                  />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.stamp_duty?.residential?.first_time_buyers?.nil_rate_threshold) }}</p>
                </div>
              </div>

              <div>
                <h4 class="text-sm font-medium text-horizon-500 mb-3">First-Time Buyer Stamp Duty Land Tax Bands</h4>
                <div v-for="(band, index) in (isEditing ? editableConfig.stamp_duty?.residential?.first_time_buyers?.bands : currentConfig.stamp_duty?.residential?.first_time_buyers?.bands)" :key="`sdlt-ftb-${band.threshold}-${index}`" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-savannah-100 rounded-lg mb-3">
                  <div>
                    <label class="block text-xs text-neutral-500 mb-1">Threshold (£)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.bands[index].threshold"
                      type="number"
                      step="1"
                      class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="text-sm font-medium">{{ formatCurrency(band.threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-xs text-neutral-500 mb-1">Rate (%)</label>
                    <input
                      v-if="isEditing"
                      v-model.number="editableConfig.stamp_duty.residential.first_time_buyers.bands[index].rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="1"
                      class="w-full px-2 py-1 text-sm border border-horizon-300 rounded focus:ring-2 focus:ring-violet-500"
                    />
                    <p v-else class="text-sm font-semibold text-raspberry-600">{{ (band.rate * 100).toFixed(2) }}%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Gifting Exemptions Tab -->
        <div v-if="activeTab === 'gifting'">
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Gifting Exemptions</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Annual Exemption (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.gifting_exemptions.annual_exemption" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions?.annual_exemption) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Small Gifts Limit (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.gifting_exemptions.small_gifts_limit" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions?.small_gifts_limit) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Carry Forward Years</label>
                  <input v-if="isEditing" v-model.number="editableConfig.gifting_exemptions.carry_forward_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.gifting_exemptions?.carry_forward_years }} year(s)</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Wedding Gifts</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Parent to Child (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.gifting_exemptions.wedding_gifts.parent_to_child" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions?.wedding_gifts?.parent_to_child) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Grandparent to Grandchild (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.gifting_exemptions.wedding_gifts.grandparent_to_grandchild" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions?.wedding_gifts?.grandparent_to_grandchild) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Other Person (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.gifting_exemptions.wedding_gifts.other_person" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.gifting_exemptions?.wedding_gifts?.other_person) }}</p>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Normal Expenditure from Income</h4>
                <p class="text-sm text-neutral-500">{{ currentConfig.gifting_exemptions?.normal_expenditure_from_income?.notes }}</p>
              </div>
            </div>
          </div>

          <!-- IHT Business Relief -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Business Property Relief</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Ownership (years)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.inheritance_tax.business_relief.min_ownership_years" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.inheritance_tax?.business_relief?.min_ownership_years }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Allowance Cap (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.inheritance_tax.business_relief.allowance_cap" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax?.business_relief?.allowance_cap) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Cap Effective Date</label>
                  <p class="font-medium">{{ currentConfig.inheritance_tax?.business_relief?.allowance_cap_effective_date }}</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Relief Rates by Asset Type</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div v-for="(rate, assetType) in (currentConfig.inheritance_tax?.business_relief?.rates || {})" :key="assetType" class="flex justify-between items-center p-2 bg-savannah-100 rounded">
                    <span class="text-sm text-neutral-500">{{ assetType.replace(/_/g, ' ') }}</span>
                    <span class="font-semibold text-raspberry-600">{{ (rate * 100).toFixed(0) }}%</span>
                  </div>
                </div>
              </div>
              <p class="text-xs text-neutral-500 italic">{{ currentConfig.inheritance_tax?.business_relief?.notes }}</p>
            </div>
          </div>

          <!-- IHT Agricultural Relief -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Agricultural Property Relief</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Ownership (years)</label>
                  <p class="font-medium">{{ currentConfig.inheritance_tax?.agricultural_relief?.min_ownership_years }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Minimum Occupation (years)</label>
                  <p class="font-medium">{{ currentConfig.inheritance_tax?.agricultural_relief?.min_occupation_years }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Allowance Cap (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.inheritance_tax?.agricultural_relief?.allowance_cap) }}</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Relief Rates</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div v-for="(rate, tenancyType) in (currentConfig.inheritance_tax?.agricultural_relief?.rates || {})" :key="tenancyType" class="flex justify-between items-center p-2 bg-savannah-100 rounded">
                    <span class="text-sm text-neutral-500">{{ tenancyType.replace(/_/g, ' ') }}</span>
                    <span class="font-semibold text-raspberry-600">{{ (rate * 100).toFixed(0) }}%</span>
                  </div>
                </div>
              </div>
              <p class="text-xs text-neutral-500 italic">{{ currentConfig.inheritance_tax?.agricultural_relief?.notes }}</p>
            </div>
          </div>

          <!-- Quick Succession Relief -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Quick Succession Relief</h3>
            </div>
            <div class="px-6 py-4">
              <p class="text-sm text-neutral-500 mb-3">Maximum period: {{ currentConfig.inheritance_tax?.quick_succession_relief?.max_years }} years</p>
              <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div v-for="band in (currentConfig.inheritance_tax?.quick_succession_relief?.relief_rates || [])" :key="band.max_years" class="text-center p-3 bg-savannah-100 rounded-lg">
                  <p class="text-xs text-neutral-500 mb-1">Within {{ band.max_years }} year(s)</p>
                  <p class="font-semibold text-raspberry-600">{{ (band.relief * 100).toFixed(0) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Chargeable Lifetime Transfers -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Chargeable Lifetime Transfers</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Lifetime Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.chargeable_lifetime_transfers?.lifetime_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Grossed-Up Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.chargeable_lifetime_transfers?.lifetime_rate_grossed_up ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Death Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.chargeable_lifetime_transfers?.death_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Lookback Period</label>
                  <p class="font-medium">{{ currentConfig.inheritance_tax?.chargeable_lifetime_transfers?.lookback_period }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Additional Death Charge</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.inheritance_tax?.chargeable_lifetime_transfers?.additional_death_charge ?? 0) * 100).toFixed(0) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Pension IHT Inclusion -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Pension Inheritance Tax Inclusion</h3>
            </div>
            <div class="px-6 py-4">
              <p class="text-sm text-neutral-500 mb-2">{{ currentConfig.inheritance_tax?.pension_iht_inclusion?.description }}</p>
              <p class="text-sm font-medium">Effective Date: {{ currentConfig.inheritance_tax?.pension_iht_inclusion?.effective_date }}</p>
              <p class="text-xs text-neutral-500 italic mt-2">{{ currentConfig.inheritance_tax?.pension_iht_inclusion?.notes }}</p>
            </div>
          </div>
        </div>

        <!-- Benefits Tab -->
        <div v-if="activeTab === 'benefits'">
          <!-- Child Benefit -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Child Benefit</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Eldest Child (£/week)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.benefits.child_benefit.eldest_child_weekly" type="number" step="0.01" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ currentConfig.benefits?.child_benefit?.eldest_child_weekly }}/week</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Additional Child (£/week)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.benefits.child_benefit.additional_child_weekly" type="number" step="0.01" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ currentConfig.benefits?.child_benefit?.additional_child_weekly }}/week</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Guardian Allowance (£/week)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.benefits.child_benefit.guardian_allowance_weekly" type="number" step="0.01" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ currentConfig.benefits?.child_benefit?.guardian_allowance_weekly }}/week</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">High Income Child Benefit Charge</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Charge Threshold (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.benefits.child_benefit.high_income_charge_threshold" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.benefits?.child_benefit?.high_income_charge_threshold) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Full Clawback Threshold (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.benefits.child_benefit.high_income_full_clawback" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.benefits?.child_benefit?.high_income_full_clawback) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Clawback Increment (£)</label>
                    <input v-if="isEditing" v-model.number="editableConfig.benefits.child_benefit.clawback_increment" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                    <p v-else class="font-medium">£{{ formatNumber(currentConfig.benefits?.child_benefit?.clawback_increment) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Savings Config -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Savings Protection</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Financial Services Compensation Scheme Deposit Protection (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.savings.fscs_deposit_protection" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.savings?.fscs_deposit_protection) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Financial Services Compensation Scheme Joint Protection (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.savings.fscs_joint_protection" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.savings?.fscs_joint_protection) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Premium Bonds Max Holding (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.savings.premium_bonds_max_holding" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.savings?.premium_bonds_max_holding) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Premium Bonds Prize Fund Rate</label>
                  <input v-if="isEditing" v-model.number="editableConfig.savings.premium_bonds_prize_fund_rate" type="number" step="0.001" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.savings?.premium_bonds_prize_fund_rate ?? 0) * 100).toFixed(1) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Parental Settlement Threshold (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.savings.parental_settlement_threshold" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.savings?.parental_settlement_threshold) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Early Years Funding -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Early Years Funding</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div v-for="(scheme, schemeKey) in {
                'universal_15hrs': 'Universal 15 Hours (All 3-4 Year Olds)',
                'disadvantaged_2yr': 'Disadvantaged 2 Year Olds',
                'working_parents_under_2': 'Working Parents (Under 2)',
                'working_parents_2yr': 'Working Parents (2 Year Olds)',
                'working_parents_30hrs': 'Working Parents 30 Hours (3-4 Year Olds)'
              }" :key="schemeKey" class="p-3 bg-savannah-100 rounded-lg">
                <h4 class="text-sm font-medium text-horizon-500 mb-2">{{ scheme }}</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                  <div>
                    <span class="text-neutral-500">Hours/Week:</span>
                    <span class="font-medium ml-1">{{ currentConfig.benefits?.early_years_funding?.[schemeKey]?.hours_per_week }}</span>
                  </div>
                  <div>
                    <span class="text-neutral-500">Weeks/Year:</span>
                    <span class="font-medium ml-1">{{ currentConfig.benefits?.early_years_funding?.[schemeKey]?.weeks_per_year }}</span>
                  </div>
                  <div>
                    <span class="text-neutral-500">Total Hours/Year:</span>
                    <span class="font-medium ml-1">{{ currentConfig.benefits?.early_years_funding?.[schemeKey]?.total_hours_per_year }}</span>
                  </div>
                  <div v-if="currentConfig.benefits?.early_years_funding?.[schemeKey]?.max_income_threshold">
                    <span class="text-neutral-500">Max Income:</span>
                    <span class="font-medium ml-1">£{{ formatNumber(currentConfig.benefits?.early_years_funding?.[schemeKey]?.max_income_threshold) }}</span>
                  </div>
                  <div v-if="currentConfig.benefits?.early_years_funding?.[schemeKey]?.min_weekly_earnings">
                    <span class="text-neutral-500">Min Weekly Earnings:</span>
                    <span class="font-medium ml-1">£{{ currentConfig.benefits?.early_years_funding?.[schemeKey]?.min_weekly_earnings }}</span>
                  </div>
                  <div v-if="currentConfig.benefits?.early_years_funding?.[schemeKey]?.eligible_age_from !== undefined">
                    <span class="text-neutral-500">Age From:</span>
                    <span class="font-medium ml-1">{{ currentConfig.benefits?.early_years_funding?.[schemeKey]?.eligible_age_from_months ? currentConfig.benefits.early_years_funding[schemeKey].eligible_age_from_months + ' months' : currentConfig.benefits.early_years_funding[schemeKey].eligible_age_from }}</span>
                  </div>
                  <div v-if="currentConfig.benefits?.early_years_funding?.[schemeKey]?.eligible_age_to">
                    <span class="text-neutral-500">Age To:</span>
                    <span class="font-medium ml-1">{{ currentConfig.benefits?.early_years_funding?.[schemeKey]?.eligible_age_to }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tax-Free Childcare -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Tax-Free Childcare</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Government Top-Up Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.benefits?.tax_free_childcare?.government_top_up_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Max Government Contribution (£/quarter)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.benefits?.tax_free_childcare?.quarterly_limit) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Max Disabled Child (£/quarter)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.benefits?.tax_free_childcare?.quarterly_limit_disabled) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Child Age Limit</label>
                  <p class="font-medium">{{ currentConfig.benefits?.tax_free_childcare?.child_age_limit }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Disabled Child Age Limit</label>
                  <p class="font-medium">{{ currentConfig.benefits?.tax_free_childcare?.disabled_child_age_limit }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Max Income Threshold (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.benefits?.tax_free_childcare?.max_income_threshold) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Statutory Sick Pay -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Statutory Sick Pay</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Weekly Rate (£)</label>
                  <p class="font-medium">£{{ currentConfig.benefits?.ssp?.weekly_rate }}/week</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Max Weeks</label>
                  <p class="font-medium">{{ currentConfig.benefits?.ssp?.max_weeks }} weeks</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Lower Earnings Limit (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.benefits?.ssp?.lower_earnings_limit) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- ESA, PIP, Universal Credit, Bereavement Support -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Other Benefits</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div>
                <h4 class="font-medium text-horizon-500 mb-3">Employment &amp; Support Allowance</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Assessment Rate (25+) (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.esa?.assessment_rate_25_plus }}/week</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Assessment Rate (Under 25) (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.esa?.assessment_rate_under_25 }}/week</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Support Group Supplement (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.esa?.support_group_supplement }}/week</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Work Related Activity Group Supplement (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.esa?.wrag_supplement }}/week</p>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Personal Independence Payment</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Daily Living Standard (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.pip?.daily_living_standard }}/week</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Daily Living Enhanced (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.pip?.daily_living_enhanced }}/week</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Mobility Standard (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.pip?.mobility_standard }}/week</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Mobility Enhanced (£/week)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.pip?.mobility_enhanced }}/week</p>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Universal Credit</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Single (25+) (£/month)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.universal_credit?.standard_allowance_single_25_plus }}/month</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Couple (25+) (£/month)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.universal_credit?.standard_allowance_couple_one_25_plus }}/month</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Taper Rate</label>
                    <p class="font-semibold text-raspberry-600">{{ ((currentConfig.benefits?.universal_credit?.taper_rate ?? 0) * 100).toFixed(0) }}%</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Child Element (First) (£/month)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.universal_credit?.child_element_first }}/month</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Limited Capability for Work &amp; Work-Related Activity Element (£/month)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.universal_credit?.lcwra_element }}/month</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Carer Element (£/month)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.universal_credit?.carer_element }}/month</p>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Bereavement Support Payment</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Higher Rate Lump Sum (£)</label>
                    <p class="font-medium">£{{ formatNumber(currentConfig.benefits?.bereavement_support?.higher_rate_lump_sum) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Higher Rate Monthly (£)</label>
                    <p class="font-medium">£{{ currentConfig.benefits?.bereavement_support?.higher_rate_monthly }}/month</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Lower Rate Lump Sum (£)</label>
                    <p class="font-medium">£{{ formatNumber(currentConfig.benefits?.bereavement_support?.lower_rate_lump_sum) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm text-neutral-500 mb-1">Payment Months</label>
                    <p class="font-medium">{{ currentConfig.benefits?.bereavement_support?.payment_months }} months</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Assumptions Tab -->
        <div v-if="activeTab === 'assumptions'">
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Economic Assumptions</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Inflation Rate</label>
                  <input v-if="isEditing" v-model.number="editableConfig.assumptions.inflation" type="number" step="0.001" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.assumptions?.inflation ?? 0) * 100).toFixed(1) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Salary Growth Rate</label>
                  <input v-if="isEditing" v-model.number="editableConfig.assumptions.salary_growth" type="number" step="0.001" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.assumptions?.salary_growth ?? 0) * 100).toFixed(1) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Property Growth Rate</label>
                  <input v-if="isEditing" v-model.number="editableConfig.assumptions.property_growth" type="number" step="0.001" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.assumptions?.property_growth ?? 0) * 100).toFixed(1) }}%</p>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Investment Growth by Asset Class</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div v-for="(rate, assetClass) in (currentConfig.assumptions?.investment_growth || {})" :key="assetClass" class="flex justify-between items-center p-3 bg-savannah-100 rounded-lg">
                    <span class="text-sm text-neutral-500 capitalize">{{ assetClass.replace(/_/g, ' ') }}</span>
                    <span class="font-semibold text-raspberry-600">{{ (rate * 100).toFixed(1) }}%</span>
                  </div>
                </div>
              </div>
              <div class="border-t pt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Growth by Risk Profile</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div v-for="(rate, profile) in (currentConfig.assumptions?.growth_by_risk || {})" :key="profile" class="flex justify-between items-center p-3 bg-savannah-100 rounded-lg">
                    <span class="text-sm text-neutral-500 capitalize">{{ profile.replace(/_/g, ' ') }}</span>
                    <span class="font-semibold text-raspberry-600">{{ (rate * 100).toFixed(1) }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Module Config Tab -->
        <div v-if="activeTab === 'module-config'">
          <!-- Protection Config -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Protection Module</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Life Cover Multiplier</label>
                  <input v-if="isEditing" v-model.number="editableConfig.protection.income_multipliers.life_cover" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.protection?.income_multipliers?.life_cover }}x income</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Critical Illness Multiplier</label>
                  <input v-if="isEditing" v-model.number="editableConfig.protection.income_multipliers.critical_illness" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">{{ currentConfig.protection?.income_multipliers?.critical_illness }}x income</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Income Protection Max Benefit</label>
                  <input v-if="isEditing" v-model.number="editableConfig.protection.income_multipliers.income_protection_max_benefit" type="number" step="0.01" min="0" max="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-semibold text-raspberry-600">{{ ((currentConfig.protection?.income_multipliers?.income_protection_max_benefit ?? 0) * 100).toFixed(0) }}% of income</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Final Expenses Estimate (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.protection.final_expenses" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.protection?.final_expenses) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Education Cost per Year (£)</label>
                  <input v-if="isEditing" v-model.number="editableConfig.protection.education_cost_per_year" type="number" step="1" class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500" />
                  <p v-else class="font-medium">£{{ formatNumber(currentConfig.protection?.education_cost_per_year) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Insurance Premium Tax (Standard)</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.protection?.ipt?.standard_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Retirement Config -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Retirement Module</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Target Income Percent</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.retirement?.target_income_percent ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Projection End Age</label>
                  <p class="font-medium">{{ currentConfig.retirement?.projection_end_age }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Monte Carlo Iterations</label>
                  <p class="font-medium">{{ formatNumber(currentConfig.retirement?.monte_carlo_iterations) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Compounding Periods (per year)</label>
                  <p class="font-medium">{{ currentConfig.retirement?.compounding_periods }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Accumulation to Decumulation (years)</label>
                  <p class="font-medium">{{ currentConfig.retirement?.accumulation_to_decumulation_years }} years</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Employer Match Threshold</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.retirement?.employer_match_threshold ?? 0) * 100).toFixed(0) }}%</p>
                </div>
              </div>
              <div class="border-t pt-4 mt-4">
                <h4 class="font-medium text-horizon-500 mb-3">Withdrawal Rates</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div v-for="(rate, strategy) in (currentConfig.retirement?.withdrawal_rates || {})" :key="strategy" class="flex justify-between items-center p-3 bg-savannah-100 rounded-lg">
                    <span class="text-sm text-neutral-500 capitalize">{{ strategy.replace(/_/g, ' ') }}</span>
                    <span class="font-semibold text-raspberry-600">{{ (rate * 100).toFixed(1) }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Asset Class Yields -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Asset Class Yields</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div v-for="(yields, assetClass) in (currentConfig.investment?.asset_class_yields || {})" :key="assetClass" class="p-3 bg-savannah-100 rounded-lg text-center">
                  <p class="text-xs text-neutral-500 capitalize mb-2">{{ assetClass.replace(/_/g, ' ') }}</p>
                  <p class="text-sm">Growth: <span class="font-semibold text-raspberry-600">{{ ((yields.growth ?? 0) * 100).toFixed(1) }}%</span></p>
                  <p class="text-sm">Income: <span class="font-semibold text-raspberry-600">{{ ((yields.income_yield ?? 0) * 100).toFixed(1) }}%</span></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Investment Safety & Transfers -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Investment Safety &amp; Transfers</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Emergency Fund Months</label>
                  <p class="font-medium">{{ currentConfig.investment?.safety?.emergency_fund_months }} months</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Emergency Fund Minimum (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.investment?.safety?.emergency_fund_minimum) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Critical Debt Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.investment?.safety?.critical_debt_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Bed &amp; ISA Minimum Gain (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.investment?.transfers?.bed_and_isa_min_gain) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Cash Excess Buffer (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.investment?.transfers?.cash_excess_buffer) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Consolidation Minimum Accounts</label>
                  <p class="font-medium">{{ currentConfig.investment?.transfers?.consolidation_min_accounts }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Retirement Annuity Estimates -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Retirement Annuity Rate Estimates</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div v-for="(rates, age) in (currentConfig.retirement?.annuity_rate_estimates || {})" :key="age" class="p-3 bg-savannah-100 rounded-lg text-center">
                  <p class="text-sm font-medium text-horizon-500 mb-2">Age {{ age }}</p>
                  <p class="text-xs">Single: <span class="font-semibold text-raspberry-600">{{ ((rates.single ?? 0) * 100).toFixed(1) }}%</span></p>
                  <p class="text-xs">Joint: <span class="font-semibold text-raspberry-600">{{ ((rates.joint ?? 0) * 100).toFixed(1) }}%</span></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Domicile Rules -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Domicile Rules</h3>
            </div>
            <div class="px-6 py-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="font-medium text-horizon-500 mb-3">UK Domiciled</h4>
                  <div class="space-y-2">
                    <p class="text-sm"><span class="text-neutral-500">Spouse Exemption:</span> <span class="font-medium text-spring-600">{{ currentConfig.domicile?.uk_domiciled?.spouse_exemption_unlimited ? 'Unlimited' : 'Limited' }}</span></p>
                    <p class="text-sm"><span class="text-neutral-500">Inheritance Tax Scope:</span> <span class="font-medium">{{ currentConfig.domicile?.uk_domiciled?.worldwide_assets_subject_to_iht ? 'Worldwide assets' : 'UK assets only' }}</span></p>
                  </div>
                </div>
                <div>
                  <h4 class="font-medium text-horizon-500 mb-3">Non-UK Domiciled</h4>
                  <div class="space-y-2">
                    <p class="text-sm"><span class="text-neutral-500">Deemed Domicile:</span> <span class="font-medium">After {{ currentConfig.domicile?.non_uk_domiciled?.deemed_domicile_years }} years UK residence</span></p>
                    <p class="text-sm"><span class="text-neutral-500">Spouse Exemption Limit:</span> <span class="font-medium">£{{ formatNumber(currentConfig.domicile?.non_uk_domiciled?.spouse_exemption_limit) }}</span></p>
                    <p class="text-sm"><span class="text-neutral-500">Inheritance Tax Scope:</span> <span class="font-medium">{{ currentConfig.domicile?.non_uk_domiciled?.uk_assets_only_subject_to_iht ? 'UK assets only' : 'Worldwide' }}</span></p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Trust Types Reference -->
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">Trust Types &amp; Tax Treatment</h3>
            </div>
            <div class="px-6 py-4 space-y-3">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Trust Tax-Free Allowance (£)</label>
                  <p class="font-medium">£{{ formatNumber(currentConfig.trusts?.income_tax?.tax_free_allowance) }}</p>
                </div>
                <div>
                  <label class="block text-sm text-neutral-500 mb-1">Discretionary Trust Income Tax Rate</label>
                  <p class="font-semibold text-raspberry-600">{{ ((currentConfig.trusts?.income_tax?.discretionary?.standard_rate ?? 0) * 100).toFixed(0) }}%</p>
                </div>
              </div>
              <div v-for="(trust, trustType) in (currentConfig.trusts?.types || {})" :key="trustType" class="p-4 bg-savannah-100 rounded-lg">
                <div class="flex justify-between items-start mb-2">
                  <h4 class="font-medium text-horizon-500">{{ trust.name }}</h4>
                  <span v-if="trust.is_relevant_property_trust" class="text-xs px-2 py-1 rounded-full bg-violet-100 text-violet-700">Relevant Property</span>
                </div>
                <p class="text-xs text-neutral-500 mb-2">{{ trust.description }}</p>
                <div class="flex gap-3 flex-wrap">
                  <span class="text-xs px-2 py-1 rounded-full bg-eggshell-500 text-horizon-500">Income Tax: {{ trust.income_tax_treatment }}</span>
                  <span class="text-xs px-2 py-1 rounded-full bg-eggshell-500 text-horizon-500">Capital Gains Tax: {{ trust.cgt_treatment }}</span>
                  <span class="text-xs px-2 py-1 rounded-full bg-eggshell-500 text-horizon-500">Inheritance Tax: {{ trust.iht_treatment }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Version Management Tab -->
        <div v-if="activeTab === 'versions'">
          <div class="card">
            <div class="px-6 py-4 border-b border-light-gray">
              <h3 class="text-lg font-semibold text-horizon-500">All Tax Configurations</h3>
            </div>
            <div class="px-6 py-4">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-light-gray">
                  <thead class="bg-savannah-100">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tax Year</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Effective Period</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                      <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-light-gray">
                    <tr v-for="config in allConfigs" :key="config.id" :class="config.is_active ? 'bg-violet-50' : ''">
                      <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-horizon-500">
                        {{ config.tax_year }}
                        <span v-if="config.is_active" class="ml-2 px-2 py-1 text-xs font-semibold text-violet-800 bg-violet-200 rounded">Active</span>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-500">
                        {{ formatDate(config.effective_from) }} - {{ formatDate(config.effective_to) }}
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm">
                        <span :class="[
                          'px-2 py-1 text-xs font-semibold rounded',
                          config.is_active ? 'text-spring-800 bg-spring-200' : 'text-neutral-500 bg-savannah-100'
                        ]">
                          {{ config.is_active ? 'Active' : 'Inactive' }}
                        </span>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          v-if="!config.is_active"
                          @click="activateConfig(config.id)"
                          class="text-spring-600 hover:text-spring-900 mr-3"
                        >
                          Activate
                        </button>
                        <button
                          @click="duplicateConfig(config)"
                          class="text-raspberry-600 hover:text-raspberry-900 mr-3"
                        >
                          Duplicate
                        </button>
                        <button
                          v-if="!config.is_active"
                          @click="deleteConfig(config.id)"
                          class="text-raspberry-600 hover:text-raspberry-900"
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

    <!-- Duplicate Modal -->
    <div
      v-if="showDuplicateModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="closeModals"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">
          Create New Tax Year
        </h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-1">Tax Year</label>
            <input
              v-model="newConfigForm.tax_year"
              type="text"
              placeholder="2026/27"
              class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
            />
            <p class="text-xs text-neutral-500 mt-1">Format: YYYY/YY (e.g., 2026/27)</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-1">Effective From</label>
            <input
              v-model="newConfigForm.effective_from"
              type="date"
              class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-1">Effective To</label>
            <input
              v-model="newConfigForm.effective_to"
              type="date"
              class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="closeModals"
            class="px-4 py-2 text-neutral-500 bg-savannah-100 rounded-lg hover:bg-savannah-100 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitDuplicate()"
            :disabled="creating || !isNewConfigFormValid"
            :class="[
              'px-4 py-2 text-white rounded-lg transition-colors',
              (creating || !isNewConfigFormValid) ? 'bg-horizon-400 cursor-not-allowed' : 'bg-raspberry-600 hover:bg-raspberry-700'
            ]"
            :title="!isNewConfigFormValid ? 'Please fill in all required fields with valid data' : ''"
          >
            {{ creating ? 'Creating...' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import taxSettingsService from '../../services/taxSettingsService';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
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
      activating: false,
      activeTab: 'income-ni',
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
        { id: 'gifting', label: 'Gifting Exemptions' },
        { id: 'property', label: 'Property/Stamp Duty Land Tax' },
        { id: 'benefits', label: 'Benefits' },
        { id: 'assumptions', label: 'Assumptions' },
        { id: 'module-config', label: 'Module Config' },
        { id: 'versions', label: 'Version Management' },
      ],
    };
  },

  computed: {
    /**
     * ID of the currently active tax configuration, if any.
     * Used to bind the quick-switch dropdown to the correct option.
     */
    activeConfigId() {
      const active = this.allConfigs.find((config) => config.is_active);
      return active ? active.id : null;
    },

    /**
     * All tax configurations sorted with the most recent year first.
     * Sorting by effective_from descending keeps the dropdown in a
     * predictable order regardless of database row order.
     */
    sortedConfigs() {
      return [...this.allConfigs].sort((a, b) => {
        return new Date(b.effective_from) - new Date(a.effective_from);
      });
    },

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
        logger.error('Failed to load tax settings:', error);
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

      // Validate tax rates (stored as decimals 0-1)
      config.income_tax?.bands?.forEach((band, index) => {
        if (band.rate < 0 || band.rate > 1) {
          errors.push(`Income tax band ${index + 1} rate must be between 0 and 1 (decimal format, e.g. 0.2 for 20%)`);
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
        logger.error('Failed to save changes:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to save changes';
      } finally {
        this.saving = false;
      }
    },

    /**
     * Handle the quick-switch dropdown in the header.
     * Confirms with the user, then activates the selected tax year.
     * If the user cancels, the dropdown is reverted to the current active year.
     */
    async handleActiveYearChange(event) {
      const newConfigId = parseInt(event.target.value, 10);
      if (!newConfigId || newConfigId === this.activeConfigId) {
        return;
      }

      const selectedConfig = this.allConfigs.find((c) => c.id === newConfigId);
      const confirmed = confirm(
        `Switch the active tax year to ${selectedConfig?.tax_year}?\n\n` +
        'All tax calculations across the application will use the new year from the next request onwards.'
      );

      if (!confirmed) {
        // Revert the dropdown to the current active year.
        event.target.value = this.activeConfigId;
        return;
      }

      this.activating = true;
      this.error = null;
      this.successMessage = null;

      try {
        const response = await taxSettingsService.setActive(newConfigId);
        if (response.data.success) {
          this.successMessage = `Active tax year switched to ${selectedConfig?.tax_year}`;
          await this.loadData();
          // Refresh the global tax year cache so every bound component
          // (dashboard, allowance cards, etc.) re-renders with the new year.
          this.$store.dispatch('taxConfig/fetchActive').catch(() => {});
        } else {
          this.error = response.data.message || 'Failed to switch active tax year';
          event.target.value = this.activeConfigId;
        }
      } catch (error) {
        logger.error('Failed to switch active tax year:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to switch active tax year';
        event.target.value = this.activeConfigId;
      } finally {
        this.activating = false;
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
        logger.error('Failed to activate configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to activate configuration';
      }
    },

    duplicateCurrentConfig() {
      if (this.currentConfig) {
        this.duplicateConfig(this.currentConfig);
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
        logger.error('Failed to duplicate configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to duplicate configuration';
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
        logger.error('Failed to delete configuration:', error);
        this.error = error.response?.data?.message || error.message || 'Failed to delete configuration';
      }
    },

    closeModals() {
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
