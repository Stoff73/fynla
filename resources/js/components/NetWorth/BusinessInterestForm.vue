<template>
  <div class="fixed inset-0 bg-horizon-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" @click.self="">
    <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden" @click.stop>
      <div class="overflow-y-auto max-h-[90vh]">
        <!-- Header -->
        <div class="sticky top-0 bg-white border-b border-light-gray px-6 py-4 rounded-t-lg z-10">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-semibold text-horizon-500">
              {{ isEditMode ? 'Edit Business Interest' : 'Add Business Interest' }}
            </h3>
            <button
              @click="handleClose"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Progress Indicator -->
          <div class="mt-4">
            <div class="flex items-center justify-between">
              <div
                v-for="(step, index) in steps"
                :key="index"
                class="flex-1 flex flex-col items-center relative"
              >
                <div
                  class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all cursor-pointer hover:opacity-80"
                  :class="
                    currentStep === index + 1
                      ? 'bg-purple-600 border-purple-600 text-white'
                      : (isEditMode || currentStep > index + 1)
                      ? 'bg-spring-600 border-spring-600 text-white'
                      : 'bg-white border-horizon-300 text-horizon-400'
                  "
                  @click="goToStep(index + 1)"
                  :title="'Go to ' + step"
                >
                  {{ index + 1 }}
                </div>
                <span class="text-xs mt-1 text-center px-1" :class="currentStep === index + 1 ? 'text-purple-600 font-semibold' : 'text-neutral-500'">
                  {{ step }}
                </span>
                <div
                  v-if="index < steps.length - 1"
                  class="absolute h-0.5 top-5 left-1/2 -z-10"
                  :style="{ width: 'calc(100% - 2.5rem)' }"
                  :class="(isEditMode || currentStep > index + 1) ? 'bg-spring-600' : 'bg-horizon-300'"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Content -->
        <form @submit.prevent="handleSubmit" novalidate>
          <div class="px-6 py-4">
            <!-- Error Message -->
            <div v-if="error" class="mb-4 p-4 bg-savannah-100 rounded-lg">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <p class="text-sm text-raspberry-700">{{ error }}</p>
              </div>
            </div>

            <!-- Step 1: Basic Information -->
            <div v-show="currentStep === 1" class="space-y-4">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Basic Information</h4>

              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'business_name' }">
                <label for="business_name" class="block text-sm font-medium text-horizon-500 mb-1">Business Name</label>
                <input
                  id="business_name"
                  v-model="form.business_name"
                  type="text"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  placeholder="Enter business name"
                />
              </div>

              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'business_type' }">
                <label for="business_type" class="block text-sm font-medium text-horizon-500 mb-1">Business Type</label>
                <select
                  id="business_type"
                  v-model="form.business_type"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                  <option value="">Select business type</option>
                  <option value="sole_trader">Sole Trader</option>
                  <option value="partnership">Partnership</option>
                  <option value="limited_company">Limited Company</option>
                  <option value="llp">LLP (Limited Liability Partnership)</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="company_number" class="block text-sm font-medium text-horizon-500 mb-1">
                    Company Number
                  </label>
                  <input
                    id="company_number"
                    v-model="form.company_number"
                    type="text"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="e.g., 12345678"
                  />
                  <p class="text-xs text-neutral-500 mt-1">Companies House registration number</p>
                </div>

                <div>
                  <label for="industry_sector" class="block text-sm font-medium text-horizon-500 mb-1">Industry Sector</label>
                  <input
                    id="industry_sector"
                    v-model="form.industry_sector"
                    type="text"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="e.g., Technology, Retail, Construction"
                  />
                </div>
              </div>

              <div>
                <label for="trading_status" class="block text-sm font-medium text-horizon-500 mb-1">Trading Status</label>
                <select
                  id="trading_status"
                  v-model="form.trading_status"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                  <option value="trading">Trading</option>
                  <option value="dormant">Dormant</option>
                  <option value="pre_trading">Pre-Trading</option>
                </select>
              </div>

              <div>
                <label for="description" class="block text-sm font-medium text-horizon-500 mb-1">Description</label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  placeholder="Brief description of your business activities"
                ></textarea>
              </div>
            </div>

            <!-- Step 2: Ownership -->
            <div v-show="currentStep === 2" class="space-y-4">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Ownership</h4>

              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-2">Ownership Type</label>
                <div class="space-y-2">
                  <label class="flex items-start">
                    <input
                      type="radio"
                      v-model="form.ownership_type"
                      value="individual"
                      class="mr-2 mt-0.5"
                    />
                    <div>
                      <span class="font-medium">Individual Owner</span>
                      <p class="text-xs text-neutral-500">You own 100% of this business interest</p>
                    </div>
                  </label>
                  <label class="flex items-start">
                    <input
                      type="radio"
                      v-model="form.ownership_type"
                      value="joint"
                      class="mr-2 mt-0.5"
                    />
                    <div>
                      <span class="font-medium">Joint Ownership</span>
                      <p class="text-xs text-neutral-500">Shared ownership with spouse/partner</p>
                    </div>
                  </label>
                  <label class="flex items-start">
                    <input
                      type="radio"
                      v-model="form.ownership_type"
                      value="trust"
                      class="mr-2 mt-0.5"
                    />
                    <div>
                      <span class="font-medium">Trust</span>
                      <p class="text-xs text-neutral-500">Business held in trust</p>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Joint Ownership Details -->
              <div v-if="form.ownership_type === 'joint'" class="space-y-4 p-4 bg-savannah-100 rounded-md">
                <p class="text-sm text-purple-800 font-medium">Joint Ownership Details</p>

                <div>
                  <label for="ownership_percentage" class="block text-sm font-medium text-horizon-500 mb-1">
                    Your Ownership Share (%)
                  </label>
                  <input
                    id="ownership_percentage"
                    v-model.number="form.ownership_percentage"
                    type="number"
                    min="1"
                    max="99"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                </div>

                <!-- Ownership Split Display -->
                <div class="bg-white p-3 rounded border border-purple-300">
                  <div class="flex justify-between items-center">
                    <div>
                      <p class="text-sm font-medium text-horizon-500">Your Share</p>
                      <p class="text-2xl font-bold text-purple-600">{{ form.ownership_percentage || 0 }}%</p>
                    </div>
                    <div class="text-horizon-400">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                      </svg>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-horizon-500">Joint Owner's Share</p>
                      <p class="text-2xl font-bold text-purple-600">{{ 100 - (form.ownership_percentage || 0) }}%</p>
                    </div>
                  </div>
                </div>

                <div>
                  <label for="joint_owner_selection" class="block text-sm font-medium text-horizon-500 mb-1">
                    Joint Owner
                  </label>
                  <select
                    id="joint_owner_selection"
                    v-model="jointOwnerSelection"
                    @change="handleJointOwnerSelection"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  >
                    <option value="">Select joint owner</option>
                    <option v-if="spouse" :value="'linked_' + spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                    <option value="other">Other (Not Linked)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Step 3: Valuation -->
            <div v-show="currentStep === 3" class="space-y-4">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Valuation</h4>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_valuation' }">
                  <label for="current_valuation" class="block text-sm font-medium text-horizon-500 mb-1">Current Valuation</label>
                  <input
                    id="current_valuation"
                    v-model.number="form.current_valuation"
                    type="number"
                    step="any"
                    min="0"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Enter estimated value"
                  />
                  <p class="text-xs text-neutral-500 mt-1">
                    {{ form.ownership_type === 'joint' ? 'Full business value (your share will be calculated)' : 'Your share of the business value' }}
                  </p>
                </div>

                <div>
                  <label for="valuation_date" class="block text-sm font-medium text-horizon-500 mb-1">Valuation Date</label>
                  <input
                    id="valuation_date"
                    v-model="form.valuation_date"
                    type="date"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                </div>
              </div>

              <div>
                <label for="valuation_method" class="block text-sm font-medium text-horizon-500 mb-1">Valuation Method</label>
                <select
                  id="valuation_method"
                  v-model="form.valuation_method"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                  <option value="">Select method</option>
                  <option value="self_assessed">Self Assessed</option>
                  <option value="professional_valuation">Professional Valuation</option>
                  <option value="earnings_multiple">Earnings Multiple</option>
                  <option value="asset_based">Asset Based</option>
                  <option value="discounted_cash_flow">Discounted Cash Flow</option>
                </select>
              </div>

              <div class="p-4 bg-savannah-100 rounded-md">
                <p class="text-sm text-violet-800">
                  <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  Business valuation can be complex. Consider getting a professional valuation for significant business interests.
                </p>
              </div>
            </div>

            <!-- Step 4: Financials -->
            <div v-show="currentStep === 4" class="space-y-4">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Financials</h4>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="annual_revenue" class="block text-sm font-medium text-horizon-500 mb-1">Annual Revenue</label>
                  <input
                    id="annual_revenue"
                    v-model.number="form.annual_revenue"
                    type="number"
                    step="any"
                    min="0"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Annual turnover"
                  />
                </div>

                <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'annual_profit' }">
                  <label for="annual_profit" class="block text-sm font-medium text-horizon-500 mb-1">Annual Profit</label>
                  <input
                    id="annual_profit"
                    v-model.number="form.annual_profit"
                    type="number"
                    step="any"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Net profit (can be negative)"
                  />
                </div>

                <div>
                  <label for="annual_dividend_income" class="block text-sm font-medium text-horizon-500 mb-1">
                    Annual Dividend Income
                    <span class="text-xs text-neutral-500">(from this business)</span>
                  </label>
                  <input
                    id="annual_dividend_income"
                    v-model.number="form.annual_dividend_income"
                    type="number"
                    step="any"
                    min="0"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Dividends you receive"
                  />
                  <p class="text-xs text-neutral-500 mt-1">Only applicable for Limited Companies</p>
                </div>

                <div>
                  <label for="employee_count" class="block text-sm font-medium text-horizon-500 mb-1">Number of Employees</label>
                  <input
                    id="employee_count"
                    v-model.number="form.employee_count"
                    type="number"
                    min="0"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Including yourself"
                  />
                </div>
              </div>
            </div>

            <!-- Step 5: Tax & Compliance -->
            <div v-show="currentStep === 5" class="space-y-4">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Tax & Compliance</h4>

              <!-- VAT Section -->
              <div class="p-4 bg-savannah-100 border border-light-gray rounded-md space-y-4">
                <label class="flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="form.vat_registered"
                    class="mr-3 h-4 w-4 text-purple-600 focus:ring-purple-500 border-horizon-300 rounded"
                  />
                  <span class="text-sm font-medium text-horizon-500">VAT Registered</span>
                </label>

                <div v-if="form.vat_registered">
                  <label for="vat_number" class="block text-sm font-medium text-horizon-500 mb-1">VAT Number</label>
                  <input
                    id="vat_number"
                    v-model="form.vat_number"
                    type="text"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="GB 123 4567 89"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="utr_number" class="block text-sm font-medium text-horizon-500 mb-1">
                    UTR Number
                    <span class="relative inline-block group">
                      <svg class="inline w-4 h-4 text-horizon-400 cursor-help ml-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                      </svg>
                      <span class="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-horizon-500 rounded-md whitespace-nowrap z-10">
                        Unique Taxpayer Reference - 10 digit number from HMRC
                      </span>
                    </span>
                  </label>
                  <input
                    id="utr_number"
                    v-model="form.utr_number"
                    type="text"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="10 digit UTR"
                  />
                </div>

                <div v-if="form.business_type === 'limited_company' || form.business_type === 'llp'">
                  <label for="tax_year_end" class="block text-sm font-medium text-horizon-500 mb-1">Accounting Year End</label>
                  <input
                    id="tax_year_end"
                    v-model="form.tax_year_end"
                    type="date"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                  <p class="text-xs text-neutral-500 mt-1">Company's accounting reference date</p>
                </div>
              </div>

              <div v-if="form.employee_count > 0">
                <label for="paye_reference" class="block text-sm font-medium text-horizon-500 mb-1">PAYE Reference</label>
                <input
                  id="paye_reference"
                  v-model="form.paye_reference"
                  type="text"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  placeholder="123/A12345"
                />
              </div>
            </div>

            <!-- Step 6: Exit Planning -->
            <div v-show="currentStep === 6" class="space-y-4">
              <h4 class="text-lg font-semibold text-horizon-500 mb-4">Exit Planning</h4>

              <div class="p-4 bg-savannah-100 rounded-md">
                <p class="text-sm text-purple-800">
                  <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  This information helps calculate potential Capital Gains Tax if you sell your business, including Business Asset Disposal Relief (BADR) eligibility.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="acquisition_date" class="block text-sm font-medium text-horizon-500 mb-1">
                    When did you acquire this business?
                  </label>
                  <input
                    id="acquisition_date"
                    v-model="form.acquisition_date"
                    type="date"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                  <p class="text-xs text-neutral-500 mt-1">Important for 2-year BADR eligibility</p>
                </div>

                <div>
                  <label for="acquisition_cost" class="block text-sm font-medium text-horizon-500 mb-1">
                    Original Investment / Cost Basis
                  </label>
                  <input
                    id="acquisition_cost"
                    v-model.number="form.acquisition_cost"
                    type="number"
                    step="any"
                    min="0"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Initial investment amount"
                  />
                  <p class="text-xs text-neutral-500 mt-1">Used to calculate capital gain on sale</p>
                </div>
              </div>

              <!-- BPR Eligibility -->
              <div class="p-4 bg-savannah-100 rounded-md space-y-4">
                <label class="flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="form.bpr_eligible"
                    class="mr-3 h-4 w-4 text-spring-600 focus:ring-violet-500 border-horizon-300 rounded"
                  />
                  <div>
                    <span class="text-sm font-medium text-horizon-500">Business Property Relief (BPR) Eligible</span>
                    <p class="text-xs text-neutral-500">For Inheritance Tax purposes - typically 100% relief for qualifying trading businesses owned 2+ years</p>
                  </div>
                </label>

                <div v-if="form.bpr_eligible" class="text-sm text-spring-800">
                  <p class="font-medium">Business Property Relief can reduce the Inheritance Tax value of this business interest to zero if:</p>
                  <ul class="list-disc ml-5 mt-1 space-y-1">
                    <li>The business is a trading business (not mainly investment)</li>
                    <li>You've owned it for at least 2 years</li>
                    <li>It's not listed on a recognised stock exchange</li>
                  </ul>
                </div>
              </div>

              <!-- Notes -->
              <div>
                <label for="notes" class="block text-sm font-medium text-horizon-500 mb-1">Notes</label>
                <textarea
                  id="notes"
                  v-model="form.notes"
                  rows="3"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                  placeholder="Any additional notes about exit planning, succession, etc."
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-savannah-100 border-t border-light-gray px-6 py-4 flex justify-between rounded-b-lg">
            <button
              type="button"
              @click="previousStep"
              v-show="currentStep > 1"
              class="px-4 py-2 bg-savannah-200 text-neutral-500 rounded-button hover:bg-horizon-300 transition-colors"
            >
              Previous
            </button>

            <div class="flex space-x-2 ml-auto">
              <button
                type="button"
                @click="handleClose"
                class="px-4 py-2 bg-white border border-horizon-300 text-neutral-500 rounded-button hover:bg-savannah-100 transition-colors"
              >
                Cancel
              </button>

              <button
                v-if="currentStep < totalSteps && !isEditMode"
                type="button"
                @click="nextStep"
                class="px-4 py-2 bg-purple-600 text-white rounded-button hover:bg-purple-700 transition-colors"
              >
                Next
              </button>

              <button
                v-if="currentStep < totalSteps && isEditMode"
                type="button"
                @click="nextStep"
                class="px-4 py-2 bg-savannah-200 text-neutral-500 rounded-button hover:bg-horizon-300 transition-colors"
              >
                Next Step
              </button>

              <button
                v-if="currentStep >= totalSteps || isEditMode"
                type="submit"
                :disabled="submitting"
                class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ submitting ? 'Saving...' : 'Save Business' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'BusinessInterestForm',

  emits: ['save', 'close'],

  props: {
    business: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      currentStep: 1,
      steps: ['Basic Info', 'Ownership', 'Valuation', 'Financials', 'Tax', 'Exit Planning'],
      jointOwnerSelection: '',
      form: {
        business_name: '',
        business_type: '',
        company_number: '',
        industry_sector: '',
        country: 'United Kingdom',
        trading_status: 'trading',
        description: '',
        ownership_type: 'individual',
        ownership_percentage: 100,
        joint_owner_id: null,
        current_valuation: null,
        valuation_date: new Date().toISOString().split('T')[0],
        valuation_method: '',
        annual_revenue: null,
        annual_profit: null,
        annual_dividend_income: null,
        employee_count: 0,
        vat_registered: false,
        vat_number: '',
        utr_number: '',
        tax_year_end: '',
        paye_reference: '',
        acquisition_date: '',
        acquisition_cost: null,
        bpr_eligible: false,
        notes: '',
      },
      submitting: false,
      error: null,
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    isEditMode() {
      return this.business !== null;
    },

    totalSteps() {
      return this.steps.length;
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },
  },

  watch: {
    business: {
      immediate: true,
      handler(newBusiness) {
        if (newBusiness) {
          this.populateForm();
        }
      },
    },

    'form.ownership_type'(newVal) {
      if (newVal === 'individual') {
        this.form.ownership_percentage = 100;
        this.form.joint_owner_id = null;
        this.jointOwnerSelection = '';
      } else if (newVal === 'joint' && this.form.ownership_percentage === 100) {
        this.form.ownership_percentage = 50;
      } else if (newVal === 'trust') {
        this.form.ownership_percentage = 0;
      }
    },

    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'business_interest' && fill.fields) {
          // Pre-set key fields before field sequence so Vue reactivity works
          if (fill.fields.business_name) {
            this.form.business_name = fill.fields.business_name;
          }
          if (fill.fields.business_type) {
            this.form.business_type = fill.fields.business_type;
          }
          if (fill.fields.current_valuation) {
            this.form.current_valuation = fill.fields.current_valuation;
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
          this.form[fieldKey] = value;
        }
      }
    },

    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'business_interest') {
        setTimeout(() => {
          this.$nextTick(() => {
            this.handleSubmit();
            if (this.error) {
              this.$store.commit('aiChat/ADD_MESSAGE', {
                id: 'fill_error_' + Date.now(),
                role: 'assistant',
                content: `I wasn't able to save the business — ${this.error}. Please check the form and try again.`,
                created_at: new Date().toISOString(),
              }, { root: true });
              this.$store.dispatch('aiFormFill/cancelFill');
            }
          });
        }, 500);
      }
    },
  },

  methods: {
    handleClose() {
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
    },

    populateForm() {
      this.form.business_name = this.business.business_name || '';
      this.form.business_type = this.business.business_type || '';
      this.form.company_number = this.business.company_number || '';
      this.form.industry_sector = this.business.industry_sector || '';
      this.form.country = this.business.country || 'United Kingdom';
      this.form.trading_status = this.business.trading_status || 'trading';
      this.form.description = this.business.description || '';
      this.form.ownership_type = this.business.ownership_type || 'individual';
      this.form.ownership_percentage = this.business.ownership_percentage || 100;
      this.form.joint_owner_id = this.business.joint_owner_id || null;
      this.form.current_valuation = this.business.current_valuation || null;
      this.form.valuation_date = this.formatDateForInput(this.business.valuation_date);
      this.form.valuation_method = this.business.valuation_method || '';
      this.form.annual_revenue = this.business.annual_revenue || null;
      this.form.annual_profit = this.business.annual_profit || null;
      this.form.annual_dividend_income = this.business.annual_dividend_income || null;
      this.form.employee_count = this.business.employee_count || 0;
      this.form.vat_registered = this.business.vat_registered || false;
      this.form.vat_number = this.business.vat_number || '';
      this.form.utr_number = this.business.utr_number || '';
      this.form.tax_year_end = this.formatDateForInput(this.business.tax_year_end);
      this.form.paye_reference = this.business.paye_reference || '';
      this.form.acquisition_date = this.formatDateForInput(this.business.acquisition_date);
      this.form.acquisition_cost = this.business.acquisition_cost || null;
      this.form.bpr_eligible = this.business.bpr_eligible || false;
      this.form.notes = this.business.notes || '';

      // Set joint owner selection
      if (this.form.joint_owner_id) {
        this.jointOwnerSelection = 'linked_' + this.form.joint_owner_id;
      }
    },

    formatDateForInput(date) {
      if (!date) return '';
      try {
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    },

    nextStep() {
      this.error = null;
      if (this.currentStep < this.totalSteps) {
        this.currentStep++;
      }
    },

    previousStep() {
      if (this.currentStep > 1) {
        this.currentStep--;
      }
    },

    goToStep(stepNumber) {
      this.error = null;
      if (stepNumber >= 1 && stepNumber <= this.totalSteps) {
        this.currentStep = stepNumber;
      }
    },

    handleJointOwnerSelection() {
      if (this.jointOwnerSelection.startsWith('linked_')) {
        this.form.joint_owner_id = parseInt(this.jointOwnerSelection.replace('linked_', ''));
      } else if (this.jointOwnerSelection === 'other') {
        this.form.joint_owner_id = null;
      }
    },

    validateForm() {
      if (!this.form.business_name) {
        this.error = 'Please enter a business name.';
        this.currentStep = 1;
        return false;
      }

      if (!this.form.business_type) {
        this.error = 'Please select a business type.';
        this.currentStep = 1;
        return false;
      }

      if (!this.form.current_valuation || this.form.current_valuation <= 0) {
        this.error = 'Please enter a current valuation.';
        this.currentStep = 3;
        return false;
      }

      if (!this.form.valuation_date) {
        this.error = 'Please enter a valuation date.';
        this.currentStep = 3;
        return false;
      }

      this.error = null;
      return true;
    },

    async handleSubmit() {
      if (!this.validateForm()) {
        return;
      }

      this.submitting = true;
      this.error = null;

      // Clean up empty strings to null
      const cleanedForm = { ...this.form };
      Object.keys(cleanedForm).forEach(key => {
        if (cleanedForm[key] === '') {
          cleanedForm[key] = null;
        }
      });

      // Emit save event (NOT submit - per CLAUDE.md)
      this.$emit('save', cleanedForm);
    },
  },
};
</script>
