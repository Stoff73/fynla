<template>
  <div class="space-y-6">
    <!-- Notes Section -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <p class="text-body-sm text-blue-800">
        <strong>Why this matters:</strong> Understanding your expenditure helps us calculate your emergency fund needs, discretionary income, and protection requirements.
      </p>
    </div>

    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
      <p class="text-body-sm text-green-800">
        <strong>Note:</strong> Household expenditure such as Council Tax, utilities, and maintenance are entered in the Properties tab. Car loans/repayments, other loans, credit cards, and hire purchase are entered in the Liabilities section.
      </p>
      <p v-if="isMarried && !useSeparateExpenditure" class="text-body-sm text-green-800 mt-2">
        Expenditure entered below is for the whole household and assumes a 50/50 split with your spouse.
      </p>
    </div>

    <!-- Separate Expenditure Option (Married Users Only) -->
    <div v-if="isMarried" class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="flex items-start">
        <div class="flex items-center h-5">
          <input
            id="separate_expenditure"
            v-model="useSeparateExpenditure"
            type="checkbox"
            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
          >
        </div>
        <div class="ml-3">
          <label for="separate_expenditure" class="text-body font-medium text-gray-900">
            Enter separate expenditure for each spouse
          </label>
          <p class="text-body-sm text-gray-600 mt-1">
            Check this box if you and your spouse want to enter individual expenditure values instead of using a 50/50 household split.
          </p>
        </div>
      </div>
    </div>

    <!-- Entry Mode Toggle -->
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h4 class="text-body font-medium text-gray-900">Entry Method</h4>
          <p class="text-body-sm text-gray-600 mt-1">
            Choose how you'd like to enter your expenditure
          </p>
        </div>
        <div class="flex items-center space-x-2 sm:space-x-3">
          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 rounded-md text-xs sm:text-body-sm font-medium transition-colors flex-1 sm:flex-none',
              useSimpleEntry
                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                : 'bg-primary-600 text-white'
            ]"
            @click="useSimpleEntry = false"
          >
            <span class="hidden sm:inline">Detailed Breakdown</span>
            <span class="sm:hidden">Detailed</span>
          </button>
          <button
            type="button"
            :class="[
              'px-3 sm:px-4 py-2 rounded-md text-xs sm:text-body-sm font-medium transition-colors flex-1 sm:flex-none',
              useSimpleEntry
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
            @click="useSimpleEntry = true"
          >
            <span class="hidden sm:inline">Simple Total</span>
            <span class="sm:hidden">Simple</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Simple Entry Mode -->
    <div v-if="useSimpleEntry" class="card p-6">
      <h3 class="text-h5 font-semibold text-gray-900 mb-4">Total Monthly Expenditure</h3>

      <div class="max-w-md">
        <label for="simple_monthly_expenditure" class="label">
          Monthly Expenditure
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
          <input
            id="simple_monthly_expenditure"
            v-model.number="simpleMonthlyExpenditure"
            type="number"
            min="0"
            step="100"
            class="input-field pl-8"
            placeholder="3000"
            required
          >
        </div>
        <p class="mt-1 text-body-sm text-gray-500">
          Enter your average total monthly household expenses
        </p>
      </div>

      <div class="mt-6 bg-gray-50 rounded-lg p-6">
        <h4 class="text-body font-medium text-gray-900 mb-4">
          Summary
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <p class="text-body-sm text-gray-600">Total Monthly Expenditure</p>
            <p class="text-h3 font-display text-gray-900">
              {{ formatCurrency(simpleMonthlyExpenditure) }}
            </p>
          </div>
          <div>
            <p class="text-body-sm text-gray-600">Total Annual Expenditure</p>
            <p class="text-h3 font-display text-gray-900">
              {{ formatCurrency(simpleMonthlyExpenditure * 12) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Entry Mode -->
    <div v-if="!useSimpleEntry" class="space-y-6">
      <!-- Tabs for Married Users (Always shown in user profile, conditional in onboarding) -->
      <div v-if="showTabs" class="bg-white border border-gray-200 rounded-lg">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              type="button"
              :class="[
                'px-6 py-3 text-body-sm font-medium border-b-2 transition-colors',
                activeTab === 'user'
                  ? 'border-primary-600 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
              @click="activeTab = 'user'"
            >
              Your Expenditure
            </button>
            <button
              type="button"
              :class="[
                'px-6 py-3 text-body-sm font-medium border-b-2 transition-colors',
                activeTab === 'spouse'
                  ? 'border-primary-600 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
              @click="activeTab = 'spouse'"
            >
              Spouse's Expenditure
            </button>
            <button
              type="button"
              :class="[
                'px-6 py-3 text-body-sm font-medium border-b-2 transition-colors',
                activeTab === 'household'
                  ? 'border-primary-600 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
              @click="activeTab = 'household'"
            >
              Household Total
            </button>
          </nav>
        </div>
      </div>

      <!-- User Tab Content -->
      <div v-if="!showTabs || activeTab === 'user'" class="space-y-6">
        <!-- Essential Living Expenses -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Essential Living Expenses (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Food & Groceries -->
            <div>
              <label for="food_groceries" class="label">
                Food & Groceries
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="food_groceries"
                  v-model.number="formData.food_groceries"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="400"
                >
              </div>
            </div>

            <!-- Transport & Fuel -->
            <div>
              <label for="transport_fuel" class="label">
                Transport & Fuel
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="transport_fuel"
                  v-model.number="formData.transport_fuel"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="200"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Petrol, public transport, parking
              </p>
            </div>

            <!-- Healthcare & Medical -->
            <div>
              <label for="healthcare_medical" class="label">
                Healthcare & Medical
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="healthcare_medical"
                  v-model.number="formData.healthcare_medical"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Prescriptions, dental, optician
              </p>
            </div>

            <!-- Insurance -->
            <div>
              <label for="insurance" class="label">
                Insurance (non-property)
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="insurance"
                  v-model.number="formData.insurance"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="150"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Car, private medical, mobile phone etc.
              </p>
            </div>
          </div>
        </div>

        <!-- Communication & Technology -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Communication & Technology (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Mobile Phones -->
            <div>
              <label for="mobile_phones" class="label">
                Mobile Phones
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="mobile_phones"
                  v-model.number="formData.mobile_phones"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
            </div>

            <!-- Internet & TV -->
            <div>
              <label for="internet_tv" class="label">
                Internet & TV
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="internet_tv"
                  v-model.number="formData.internet_tv"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="60"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Broadband, TV licence
              </p>
            </div>

            <!-- Subscriptions -->
            <div>
              <label for="subscriptions" class="label">
                Subscriptions
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="subscriptions"
                  v-model.number="formData.subscriptions"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="30"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Netflix, Spotify, gym memberships
              </p>
            </div>
          </div>
        </div>

        <!-- Personal & Lifestyle -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Personal & Lifestyle (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Clothing & Personal Care -->
            <div>
              <label for="clothing_personal_care" class="label">
                Clothing & Personal Care
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="clothing_personal_care"
                  v-model.number="formData.clothing_personal_care"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="100"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Clothes, toiletries, haircuts
              </p>
            </div>

            <!-- Entertainment & Dining -->
            <div>
              <label for="entertainment_dining" class="label">
                Entertainment & Dining
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="entertainment_dining"
                  v-model.number="formData.entertainment_dining"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="200"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Restaurants, cinema, activities
              </p>
            </div>

            <!-- Holidays & Travel -->
            <div>
              <label for="holidays_travel" class="label">
                Holidays & Travel
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="holidays_travel"
                  v-model.number="formData.holidays_travel"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="200"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Monthly average for annual holidays
              </p>
            </div>

            <!-- Pets -->
            <div>
              <label for="pets" class="label">
                Pets
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="pets"
                  v-model.number="formData.pets"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Food, vet bills, insurance
              </p>
            </div>
          </div>
        </div>

        <!-- Children & Dependents -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Children & Dependents (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Childcare -->
            <div>
              <label for="childcare" class="label">
                Childcare
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="childcare"
                  v-model.number="formData.childcare"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Nursery, childminder, after school
              </p>
            </div>

            <!-- School Fees -->
            <div>
              <label for="school_fees" class="label">
                School Fees
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="school_fees"
                  v-model.number="formData.school_fees"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Private education fees
              </p>
            </div>

            <!-- School Lunches -->
            <div>
              <label for="school_lunches" class="label">
                School Lunches
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="school_lunches"
                  v-model.number="formData.school_lunches"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Monthly school lunch costs
              </p>
            </div>

            <!-- School Extras -->
            <div>
              <label for="school_extras" class="label">
                School Extras
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="school_extras"
                  v-model.number="formData.school_extras"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Uniforms, trips, equipment etc.
              </p>
            </div>

            <!-- University Fees -->
            <div>
              <label for="university_fees" class="label">
                University Fees
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="university_fees"
                  v-model.number="formData.university_fees"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Includes residential, books and any other costs
              </p>
            </div>

            <!-- Children's Activities -->
            <div>
              <label for="children_activities" class="label">
                Children's Activities
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="children_activities"
                  v-model.number="formData.children_activities"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Sports, music lessons, clubs
              </p>
            </div>
          </div>
        </div>

        <!-- Other -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Other Expenses (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Gifts & Charity -->
            <div>
              <label for="gifts_charity" class="label">
                Gifts & Charity
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="gifts_charity"
                  v-model.number="formData.gifts_charity"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Birthday gifts, charitable giving
              </p>
            </div>

            <!-- Other Expenditure -->
            <div>
              <label for="other_expenditure" class="label">
                Other Expenditure
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="other_expenditure"
                  v-model.number="formData.other_expenditure"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Any other monthly expenses
              </p>
            </div>
          </div>
        </div>

        <!-- Financial Commitments (Read-Only) -->
        <div v-if="hasAnyCommitments" class="card p-6 bg-blue-50 border-2 border-blue-200">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h4 class="text-h5 font-semibold text-gray-900">
                Financial Commitments (Automated)
              </h4>
              <p class="text-body-sm text-gray-600 mt-1">
                These monthly commitments are automatically pulled from your entered data across all modules
              </p>
            </div>
            <span v-if="loadingCommitments" class="text-body-sm text-gray-500">Loading...</span>
          </div>

          <!-- Retirement Contributions -->
          <div v-if="hasRetirementCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Retirement Contributions
            </h5>
            <div class="space-y-2">
              <div
                v-for="pension in financialCommitments.commitments.retirement"
                :key="pension.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ pension.name }}</p>
                  <p v-if="pension.is_joint" class="text-body-sm text-amber-600">
                    50% of joint contribution
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(pension.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Property Expenses -->
          <div v-if="hasPropertyCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Property Expenses
            </h5>
            <div class="space-y-2">
              <div
                v-for="property in financialCommitments.commitments.properties"
                :key="property.id"
                class="p-3 bg-white rounded-lg"
              >
                <div class="flex justify-between items-center mb-2">
                  <p class="text-body font-medium text-gray-900">{{ property.name }}</p>
                  <p class="text-body font-medium text-gray-900">
                    {{ formatCurrency(property.monthly_amount) }}/month
                  </p>
                </div>
                <div v-if="property.breakdown" class="grid grid-cols-2 gap-2 text-body-sm text-gray-600">
                  <div v-if="property.breakdown.mortgage" class="flex justify-between">
                    <span>Mortgage<span v-if="property.is_joint && property.mortgage_ownership_percentage !== property.ownership_percentage"> ({{ property.mortgage_ownership_percentage }}%)</span>:</span>
                    <span>{{ formatCurrency(property.breakdown.mortgage) }}</span>
                  </div>
                  <div v-if="property.breakdown.council_tax" class="flex justify-between">
                    <span>Council Tax:</span>
                    <span>{{ formatCurrency(property.breakdown.council_tax) }}</span>
                  </div>
                  <div v-if="property.breakdown.gas" class="flex justify-between">
                    <span>Gas:</span>
                    <span>{{ formatCurrency(property.breakdown.gas) }}</span>
                  </div>
                  <div v-if="property.breakdown.electricity" class="flex justify-between">
                    <span>Electricity:</span>
                    <span>{{ formatCurrency(property.breakdown.electricity) }}</span>
                  </div>
                  <div v-if="property.breakdown.water" class="flex justify-between">
                    <span>Water:</span>
                    <span>{{ formatCurrency(property.breakdown.water) }}</span>
                  </div>
                  <div v-if="property.breakdown.building_insurance" class="flex justify-between">
                    <span>Building Insurance:</span>
                    <span>{{ formatCurrency(property.breakdown.building_insurance) }}</span>
                  </div>
                  <div v-if="property.breakdown.contents_insurance" class="flex justify-between">
                    <span>Contents Insurance:</span>
                    <span>{{ formatCurrency(property.breakdown.contents_insurance) }}</span>
                  </div>
                  <div v-if="property.breakdown.service_charge" class="flex justify-between">
                    <span>Service Charge:</span>
                    <span>{{ formatCurrency(property.breakdown.service_charge) }}</span>
                  </div>
                  <div v-if="property.breakdown.maintenance" class="flex justify-between">
                    <span>Maintenance:</span>
                    <span>{{ formatCurrency(property.breakdown.maintenance) }}</span>
                  </div>
                  <div v-if="property.breakdown.managing_agent" class="flex justify-between">
                    <span>Management Agent:</span>
                    <span>{{ formatCurrency(property.breakdown.managing_agent) }}</span>
                  </div>
                  <div v-if="property.breakdown.other" class="flex justify-between">
                    <span>Other:</span>
                    <span>{{ formatCurrency(property.breakdown.other) }}</span>
                  </div>
                </div>
                <p v-if="property.is_joint" class="text-body-sm text-amber-600 mt-2">
                  Your {{ property.ownership_percentage || 50 }}% share ({{ property.ownership_type === 'tenants_in_common' ? 'Tenants in Common' : 'Joint' }})
                </p>
              </div>
            </div>
          </div>

          <!-- Investment Contributions -->
          <div v-if="hasInvestmentCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Investment Contributions
            </h5>
            <div class="space-y-2">
              <div
                v-for="investment in financialCommitments.commitments.investments"
                :key="investment.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ investment.name }}</p>
                  <p v-if="investment.is_joint" class="text-body-sm text-amber-600">
                    50% of joint contribution
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(investment.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Protection Premiums -->
          <div v-if="hasProtectionCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Protection Premiums
            </h5>
            <div class="space-y-2">
              <div
                v-for="policy in financialCommitments.commitments.protection"
                :key="policy.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <p class="text-body text-gray-900">{{ policy.name }}</p>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(policy.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Liability Payments -->
          <div v-if="hasLiabilityCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Liability Payments
            </h5>
            <div class="space-y-2">
              <div
                v-for="liability in financialCommitments.commitments.liabilities"
                :key="liability.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ liability.name }}</p>
                  <p class="text-body-sm text-gray-600 capitalize">{{ liability.type.replace('_', ' ') }}</p>
                  <p v-if="liability.is_joint" class="text-body-sm text-amber-600">
                    50% of joint liability
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(liability.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Total Commitments -->
          <div class="pt-4 border-t border-blue-300">
            <div class="flex justify-between items-center">
              <p class="text-body font-semibold text-gray-900">Total Monthly Commitments</p>
              <p class="text-h4 font-display text-gray-900">
                {{ formatCurrency(financialCommitments?.totals?.total || 0) }}
              </p>
            </div>
          </div>
        </div>
      </div>
      <!-- End of User Tab Content -->

      <!-- Spouse Tab Content -->
      <div v-if="showTabs && activeTab === 'spouse'" class="space-y-6">
        <!-- Essential Living Expenses -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Essential Living Expenses (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Food & Groceries -->
            <div>
              <label for="spouse_food_groceries" class="label">
                Food & Groceries
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_food_groceries"
                  v-model.number="spouseFormData.food_groceries"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="400"
                >
              </div>
            </div>

            <!-- Transport & Fuel -->
            <div>
              <label for="spouse_transport_fuel" class="label">
                Transport & Fuel
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_transport_fuel"
                  v-model.number="spouseFormData.transport_fuel"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="200"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Petrol, public transport, parking
              </p>
            </div>

            <!-- Healthcare & Medical -->
            <div>
              <label for="spouse_healthcare_medical" class="label">
                Healthcare & Medical
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_healthcare_medical"
                  v-model.number="spouseFormData.healthcare_medical"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Prescriptions, dental, optician
              </p>
            </div>

            <!-- Insurance -->
            <div>
              <label for="spouse_insurance" class="label">
                Insurance (non-property)
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_insurance"
                  v-model.number="spouseFormData.insurance"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="150"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Car, private medical, mobile phone etc.
              </p>
            </div>
          </div>
        </div>

        <!-- Communication & Technology -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Communication & Technology (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Mobile Phones -->
            <div>
              <label for="spouse_mobile_phones" class="label">
                Mobile Phones
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_mobile_phones"
                  v-model.number="spouseFormData.mobile_phones"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
            </div>

            <!-- Internet & TV -->
            <div>
              <label for="spouse_internet_tv" class="label">
                Internet & TV
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_internet_tv"
                  v-model.number="spouseFormData.internet_tv"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="60"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Broadband, TV licence
              </p>
            </div>

            <!-- Subscriptions -->
            <div>
              <label for="spouse_subscriptions" class="label">
                Subscriptions
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_subscriptions"
                  v-model.number="spouseFormData.subscriptions"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="30"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Netflix, Spotify, gym memberships
              </p>
            </div>
          </div>
        </div>

        <!-- Personal & Lifestyle -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Personal & Lifestyle (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Clothing & Personal Care -->
            <div>
              <label for="spouse_clothing_personal_care" class="label">
                Clothing & Personal Care
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_clothing_personal_care"
                  v-model.number="spouseFormData.clothing_personal_care"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="100"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Clothes, toiletries, haircuts
              </p>
            </div>

            <!-- Entertainment & Dining -->
            <div>
              <label for="spouse_entertainment_dining" class="label">
                Entertainment & Dining
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_entertainment_dining"
                  v-model.number="spouseFormData.entertainment_dining"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="200"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Restaurants, cinema, activities
              </p>
            </div>

            <!-- Holidays & Travel -->
            <div>
              <label for="spouse_holidays_travel" class="label">
                Holidays & Travel
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_holidays_travel"
                  v-model.number="spouseFormData.holidays_travel"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="200"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Monthly average for annual holidays
              </p>
            </div>

            <!-- Pets -->
            <div>
              <label for="spouse_pets" class="label">
                Pets
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_pets"
                  v-model.number="spouseFormData.pets"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Food, vet bills, insurance
              </p>
            </div>
          </div>
        </div>

        <!-- Children & Dependents -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Children & Dependents (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Childcare -->
            <div>
              <label for="spouse_childcare" class="label">
                Childcare
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_childcare"
                  v-model.number="spouseFormData.childcare"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Nursery, childminder, after school
              </p>
            </div>

            <!-- School Fees -->
            <div>
              <label for="spouse_school_fees" class="label">
                School Fees
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_school_fees"
                  v-model.number="spouseFormData.school_fees"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Private education fees
              </p>
            </div>

            <!-- School Lunches -->
            <div>
              <label for="spouse_school_lunches" class="label">
                School Lunches
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_school_lunches"
                  v-model.number="spouseFormData.school_lunches"
                  type="number"
                  min="0"
                  step="10"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Monthly school lunch costs
              </p>
            </div>

            <!-- School Extras -->
            <div>
              <label for="spouse_school_extras" class="label">
                School Extras
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_school_extras"
                  v-model.number="spouseFormData.school_extras"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Uniforms, trips, equipment etc.
              </p>
            </div>

            <!-- University Fees -->
            <div>
              <label for="spouse_university_fees" class="label">
                University Fees
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_university_fees"
                  v-model.number="spouseFormData.university_fees"
                  type="number"
                  min="0"
                  step="100"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Includes residential, books and any other costs
              </p>
            </div>

            <!-- Children's Activities -->
            <div>
              <label for="spouse_children_activities" class="label">
                Children's Activities
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_children_activities"
                  v-model.number="spouseFormData.children_activities"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Sports, music lessons, clubs
              </p>
            </div>
          </div>
        </div>

        <!-- Other -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Other Expenses (Monthly)
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Gifts & Charity -->
            <div>
              <label for="spouse_gifts_charity" class="label">
                Gifts & Charity
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_gifts_charity"
                  v-model.number="spouseFormData.gifts_charity"
                  type="number"
                  min="0"
                  step="25"
                  class="input-field pl-8"
                  placeholder="50"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Birthday gifts, charitable giving
              </p>
            </div>

            <!-- Other Expenditure -->
            <div>
              <label for="spouse_other_expenditure" class="label">
                Other Expenditure
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">£</span>
                <input
                  id="spouse_other_expenditure"
                  v-model.number="spouseFormData.other_expenditure"
                  type="number"
                  min="0"
                  step="50"
                  class="input-field pl-8"
                  placeholder="0"
                >
              </div>
              <p class="mt-1 text-body-sm text-gray-500">
                Any other monthly expenses
              </p>
            </div>
          </div>
        </div>

        <!-- Financial Commitments (Read-Only) - Spouse Tab -->
        <div v-if="hasAnySpouseCommitments" class="card p-6 bg-blue-50 border-2 border-blue-200">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h4 class="text-h5 font-semibold text-gray-900">
                Financial Commitments (Automated)
              </h4>
              <p class="text-body-sm text-gray-600 mt-1">
                Spouse's commitments automatically pulled from entered data
              </p>
            </div>
            <span v-if="loadingCommitments" class="text-body-sm text-gray-500">Loading...</span>
          </div>

          <!-- Retirement Contributions -->
          <div v-if="hasSpouseRetirementCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Retirement Contributions
            </h5>
            <div class="space-y-2">
              <div
                v-for="pension in spouseRetirementCommitments"
                :key="pension.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ pension.name }}</p>
                  <p v-if="pension.is_joint" class="text-body-sm text-amber-600">
                    Their share of joint contribution
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(pension.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Property Expenses -->
          <div v-if="hasSpousePropertyCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Property Expenses
            </h5>
            <div class="space-y-2">
              <div
                v-for="property in spousePropertyCommitments"
                :key="property.id"
                class="p-3 bg-white rounded-lg"
              >
                <div class="flex justify-between items-center mb-2">
                  <p class="text-body text-gray-900">{{ property.name }}</p>
                  <p class="text-body font-medium text-gray-900">
                    {{ formatCurrency(property.monthly_amount) }}/month
                  </p>
                </div>
                <div v-if="property.breakdown" class="grid grid-cols-2 gap-2 text-body-sm text-gray-600">
                  <div v-if="property.breakdown.mortgage" class="flex justify-between">
                    <span>Mortgage:</span>
                    <span>{{ formatCurrency(property.breakdown.mortgage) }}</span>
                  </div>
                  <div v-if="property.breakdown.council_tax" class="flex justify-between">
                    <span>Council Tax:</span>
                    <span>{{ formatCurrency(property.breakdown.council_tax) }}</span>
                  </div>
                  <div v-if="property.breakdown.utilities" class="flex justify-between">
                    <span>Utilities:</span>
                    <span>{{ formatCurrency(property.breakdown.utilities) }}</span>
                  </div>
                  <div v-if="property.breakdown.insurance" class="flex justify-between">
                    <span>Insurance:</span>
                    <span>{{ formatCurrency(property.breakdown.insurance) }}</span>
                  </div>
                  <div v-if="property.breakdown.service_charge" class="flex justify-between">
                    <span>Service Charge:</span>
                    <span>{{ formatCurrency(property.breakdown.service_charge) }}</span>
                  </div>
                  <div v-if="property.breakdown.maintenance" class="flex justify-between">
                    <span>Maintenance:</span>
                    <span>{{ formatCurrency(property.breakdown.maintenance) }}</span>
                  </div>
                  <div v-if="property.breakdown.other" class="flex justify-between">
                    <span>Other:</span>
                    <span>{{ formatCurrency(property.breakdown.other) }}</span>
                  </div>
                </div>
                <p v-if="property.is_joint" class="text-body-sm text-amber-600 mt-2">
                  Their {{ property.ownership_percentage || 50 }}% share ({{ property.ownership_type === 'tenants_in_common' ? 'Tenants in Common' : 'Joint' }})
                </p>
              </div>
            </div>
          </div>

          <!-- Investment Contributions -->
          <div v-if="hasSpouseInvestmentCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Investment Contributions
            </h5>
            <div class="space-y-2">
              <div
                v-for="investment in spouseInvestmentCommitments"
                :key="investment.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ investment.name }}</p>
                  <p class="text-body-sm text-gray-600 capitalize">{{ investment.type.replace('_', ' ') }}</p>
                  <p v-if="investment.is_joint" class="text-body-sm text-amber-600">
                    Their share of joint contribution
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(investment.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Protection Premiums -->
          <div v-if="hasSpouseProtectionCommitments" class="mb-6">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Protection Premiums
            </h5>
            <div class="space-y-2">
              <div
                v-for="policy in spouseProtectionCommitments"
                :key="policy.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ policy.name }}</p>
                  <p class="text-body-sm text-gray-600 capitalize">{{ policy.type.replace('_', ' ') }}</p>
                  <p v-if="policy.is_joint" class="text-body-sm text-amber-600">
                    Their share of joint premium
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(policy.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Liability Payments -->
          <div v-if="hasSpouseLiabilityCommitments">
            <h5 class="text-body font-medium text-gray-900 mb-3">
              Liability Payments
            </h5>
            <div class="space-y-2">
              <div
                v-for="liability in spouseLiabilityCommitments"
                :key="liability.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-gray-900">{{ liability.name }}</p>
                  <p class="text-body-sm text-gray-600 capitalize">{{ liability.type.replace('_', ' ') }}</p>
                  <p v-if="liability.is_joint" class="text-body-sm text-amber-600">
                    Their share of joint liability
                  </p>
                </div>
                <p class="text-body font-medium text-gray-900">
                  {{ formatCurrency(liability.monthly_amount) }}/month
                </p>
              </div>
            </div>
          </div>

          <!-- Total Spouse Commitments -->
          <div class="pt-4 border-t border-blue-300">
            <div class="flex justify-between items-center">
              <p class="text-body font-semibold text-gray-900">Total Monthly Commitments</p>
              <p class="text-h4 font-display text-gray-900">
                {{ formatCurrency(spouseCommitmentsTotal) }}
              </p>
            </div>
          </div>
        </div>
      </div>
      <!-- End of Spouse Tab Content -->

      <!-- Household Total Tab Content -->
      <div v-if="showTabs && activeTab === 'household'" class="space-y-6">
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-gray-900 mb-4">
            Household Expenditure Summary
          </h4>
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <p class="text-body-sm text-blue-800">
              <strong>Household Total:</strong> This shows combined expenditure for you and your spouse.
            </p>
          </div>

          <!-- Breakdown by Person -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-body-sm text-gray-600 mb-2">Your Monthly Expenditure</p>
              <p class="text-h4 font-display text-gray-900">
                {{ formatCurrency(totalMonthlyWithCommitments) }}
              </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-body-sm text-gray-600 mb-2">{{ spouseName }}'s Monthly Expenditure</p>
              <p class="text-h4 font-display text-gray-900">
                {{ formatCurrency(spouseTotalMonthlyWithCommitments) }}
              </p>
            </div>
            <div class="bg-primary-50 rounded-lg p-4">
              <p class="text-body-sm text-primary-700 mb-2 font-medium">Household Total (Monthly)</p>
              <p class="text-h4 font-display text-primary-900">
                {{ formatCurrency(householdTotalMonthlyWithCommitments) }}
              </p>
            </div>
          </div>

          <!-- Annual Totals -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-body-sm text-gray-600 mb-2">Your Annual Expenditure</p>
              <p class="text-h4 font-display text-gray-900">
                {{ formatCurrency(totalAnnualWithCommitments) }}
              </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <p class="text-body-sm text-gray-600 mb-2">{{ spouseName }}'s Annual Expenditure</p>
              <p class="text-h4 font-display text-gray-900">
                {{ formatCurrency(spouseTotalAnnualWithCommitments) }}
              </p>
            </div>
            <div class="bg-primary-50 rounded-lg p-4">
              <p class="text-body-sm text-primary-700 mb-2 font-medium">Household Total (Annual)</p>
              <p class="text-h4 font-display text-primary-900">
                {{ formatCurrency(householdTotalAnnualWithCommitments) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Detailed Summary (always shown for detailed mode) -->
      <div v-if="!showTabs || activeTab === 'user'" class="card p-6 bg-gray-50">
        <h4 class="text-h5 font-semibold text-gray-900 mb-4">
          Expenditure Summary
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <p class="text-body-sm text-gray-600">Total Monthly Expenditure</p>
            <p class="text-h3 font-display text-gray-900">
              {{ formatCurrency(totalMonthlyWithCommitments) }}
            </p>
          </div>
          <div>
            <p class="text-body-sm text-gray-600">Total Annual Expenditure</p>
            <p class="text-h3 font-display text-gray-900">
              {{ formatCurrency(totalAnnualWithCommitments) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div v-if="showButtons" class="flex justify-end space-x-3">
      <button
        v-if="showCancel"
        type="button"
        @click="handleCancel"
        class="btn-secondary"
      >
        {{ cancelText }}
      </button>
      <button
        type="button"
        @click="handleSave"
        class="btn-primary"
      >
        {{ saveText }}
      </button>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue';
import { useStore } from 'vuex';
import userProfileService from '../../services/userProfileService';
import { formatCurrency } from '../../utils/currency';

export default {
  name: 'ExpenditureForm',

  props: {
    initialData: {
      type: Object,
      default: () => ({}),
    },
    spouseData: {
      type: Object,
      default: () => ({}),
    },
    spouseName: {
      type: String,
      default: 'Spouse',
    },
    isMarried: {
      type: Boolean,
      default: false,
    },
    alwaysShowTabs: {
      type: Boolean,
      default: false, // In user profile, this is true; in onboarding, false
    },
    showButtons: {
      type: Boolean,
      default: true, // In onboarding, this is false; buttons handled by OnboardingStep
    },
    showCancel: {
      type: Boolean,
      default: false,
    },
    cancelText: {
      type: String,
      default: 'Reset',
    },
    saveText: {
      type: String,
      default: 'Save Changes',
    },
  },

  emits: ['save', 'cancel'],

  setup(props, { emit }) {
    const store = useStore();
    const useSimpleEntry = ref(false);
    const useSeparateExpenditure = ref(false);
    const activeTab = ref('user');
    const simpleMonthlyExpenditure = ref(0);
    const spouseSimpleMonthlyExpenditure = ref(0);
    const financialCommitments = ref(null);
    const spouseFinancialCommitments = ref(null);
    const loadingCommitments = ref(false);

    const formData = ref({
      food_groceries: 0,
      transport_fuel: 0,
      healthcare_medical: 0,
      insurance: 0,
      mobile_phones: 0,
      internet_tv: 0,
      subscriptions: 0,
      clothing_personal_care: 0,
      entertainment_dining: 0,
      holidays_travel: 0,
      pets: 0,
      childcare: 0,
      school_fees: 0,
      school_lunches: 0,
      school_extras: 0,
      university_fees: 0,
      children_activities: 0,
      gifts_charity: 0,
      regular_savings: 0,
      other_expenditure: 0,
    });

    const spouseFormData = ref({
      food_groceries: 0,
      transport_fuel: 0,
      healthcare_medical: 0,
      insurance: 0,
      mobile_phones: 0,
      internet_tv: 0,
      subscriptions: 0,
      clothing_personal_care: 0,
      entertainment_dining: 0,
      holidays_travel: 0,
      pets: 0,
      childcare: 0,
      school_fees: 0,
      school_lunches: 0,
      school_extras: 0,
      university_fees: 0,
      children_activities: 0,
      gifts_charity: 0,
      regular_savings: 0,
      other_expenditure: 0,
    });

    // Determine if tabs should be shown
    const showTabs = computed(() => {
      if (props.alwaysShowTabs) {
        return props.isMarried;
      }
      // In onboarding, only show tabs if separate mode is enabled
      return props.isMarried && useSeparateExpenditure.value;
    });

    const totalMonthlyExpenditure = computed(() => {
      if (useSimpleEntry.value) {
        return simpleMonthlyExpenditure.value || 0;
      }

      return (
        (formData.value.food_groceries || 0) +
        (formData.value.transport_fuel || 0) +
        (formData.value.healthcare_medical || 0) +
        (formData.value.insurance || 0) +
        (formData.value.mobile_phones || 0) +
        (formData.value.internet_tv || 0) +
        (formData.value.subscriptions || 0) +
        (formData.value.clothing_personal_care || 0) +
        (formData.value.entertainment_dining || 0) +
        (formData.value.holidays_travel || 0) +
        (formData.value.pets || 0) +
        (formData.value.childcare || 0) +
        (formData.value.school_fees || 0) +
        (formData.value.school_lunches || 0) +
        (formData.value.school_extras || 0) +
        (formData.value.university_fees || 0) +
        (formData.value.children_activities || 0) +
        (formData.value.gifts_charity || 0) +
        (formData.value.other_expenditure || 0)
      );
    });

    const totalAnnualExpenditure = computed(() => totalMonthlyExpenditure.value * 12);

    const spouseTotalMonthlyExpenditure = computed(() => {
      if (useSimpleEntry.value) {
        return spouseSimpleMonthlyExpenditure.value || 0;
      }

      return (
        (spouseFormData.value.food_groceries || 0) +
        (spouseFormData.value.transport_fuel || 0) +
        (spouseFormData.value.healthcare_medical || 0) +
        (spouseFormData.value.insurance || 0) +
        (spouseFormData.value.mobile_phones || 0) +
        (spouseFormData.value.internet_tv || 0) +
        (spouseFormData.value.subscriptions || 0) +
        (spouseFormData.value.clothing_personal_care || 0) +
        (spouseFormData.value.entertainment_dining || 0) +
        (spouseFormData.value.holidays_travel || 0) +
        (spouseFormData.value.pets || 0) +
        (spouseFormData.value.childcare || 0) +
        (spouseFormData.value.school_fees || 0) +
        (spouseFormData.value.school_lunches || 0) +
        (spouseFormData.value.school_extras || 0) +
        (spouseFormData.value.university_fees || 0) +
        (spouseFormData.value.children_activities || 0) +
        (spouseFormData.value.gifts_charity || 0) +
        (spouseFormData.value.other_expenditure || 0)
      );
    });

    const spouseTotalAnnualExpenditure = computed(() => spouseTotalMonthlyExpenditure.value * 12);

    const householdTotalMonthlyExpenditure = computed(() => {
      if (!props.isMarried || !useSeparateExpenditure.value) {
        return totalMonthlyExpenditure.value;
      }
      return totalMonthlyExpenditure.value + spouseTotalMonthlyExpenditure.value;
    });

    const householdTotalAnnualExpenditure = computed(() => householdTotalMonthlyExpenditure.value * 12);

    // Combined totals including financial commitments
    const totalMonthlyWithCommitments = computed(() => {
      const commitmentsTotal = financialCommitments.value?.totals?.total || 0;
      return totalMonthlyExpenditure.value + commitmentsTotal;
    });

    const totalAnnualWithCommitments = computed(() => totalMonthlyWithCommitments.value * 12);

    // Spouse totals including spouse's own commitments (from spouse API)
    const spouseTotalMonthlyWithCommitments = computed(() => {
      return spouseTotalMonthlyExpenditure.value + spouseCommitmentsTotal.value;
    });

    const spouseTotalAnnualWithCommitments = computed(() => spouseTotalMonthlyWithCommitments.value * 12);

    // Household total with commitments
    // For married users: always sum user + spouse (each has 50% of household expenditure)
    // For single users: just user's total
    const householdTotalMonthlyWithCommitments = computed(() => {
      if (!props.isMarried) {
        // Single user - just their total
        return totalMonthlyWithCommitments.value;
      }
      // Married: sum both spouses (user 50% + spouse 50% = 100% household)
      return totalMonthlyWithCommitments.value + spouseTotalMonthlyWithCommitments.value;
    });

    const householdTotalAnnualWithCommitments = computed(() => householdTotalMonthlyWithCommitments.value * 12);

    // Computed properties for commitments display
    const hasRetirementCommitments = computed(() => {
      return financialCommitments.value?.commitments?.retirement?.length > 0;
    });

    const hasPropertyCommitments = computed(() => {
      return financialCommitments.value?.commitments?.properties?.length > 0;
    });

    const hasInvestmentCommitments = computed(() => {
      return financialCommitments.value?.commitments?.investments?.length > 0;
    });

    const hasProtectionCommitments = computed(() => {
      return financialCommitments.value?.commitments?.protection?.length > 0;
    });

    const hasLiabilityCommitments = computed(() => {
      return financialCommitments.value?.commitments?.liabilities?.length > 0;
    });

    const hasAnyCommitments = computed(() => {
      return hasRetirementCommitments.value ||
             hasPropertyCommitments.value ||
             hasInvestmentCommitments.value ||
             hasProtectionCommitments.value ||
             hasLiabilityCommitments.value;
    });

    // Computed properties for joint commitments only (for spouse tab)
    const jointRetirementCommitments = computed(() => {
      return financialCommitments.value?.commitments?.retirement?.filter(item => item.is_joint) || [];
    });

    const jointPropertyCommitments = computed(() => {
      return financialCommitments.value?.commitments?.properties?.filter(item => item.is_joint) || [];
    });

    const jointProtectionCommitments = computed(() => {
      return financialCommitments.value?.commitments?.protection?.filter(item => item.is_joint) || [];
    });

    const jointLiabilityCommitments = computed(() => {
      return financialCommitments.value?.commitments?.liabilities?.filter(item => item.is_joint) || [];
    });

    const hasJointRetirementCommitments = computed(() => jointRetirementCommitments.value.length > 0);
    const hasJointPropertyCommitments = computed(() => jointPropertyCommitments.value.length > 0);
    const hasJointProtectionCommitments = computed(() => jointProtectionCommitments.value.length > 0);
    const hasJointLiabilityCommitments = computed(() => jointLiabilityCommitments.value.length > 0);

    const hasAnyJointCommitments = computed(() => {
      return hasJointRetirementCommitments.value ||
             hasJointPropertyCommitments.value ||
             hasJointProtectionCommitments.value ||
             hasJointLiabilityCommitments.value;
    });

    const jointCommitmentsTotal = computed(() => {
      const retirement = jointRetirementCommitments.value.reduce((sum, item) => sum + item.monthly_amount, 0);
      const properties = jointPropertyCommitments.value.reduce((sum, item) => sum + item.monthly_amount, 0);
      const protection = jointProtectionCommitments.value.reduce((sum, item) => sum + item.monthly_amount, 0);
      const liabilities = jointLiabilityCommitments.value.reduce((sum, item) => sum + item.monthly_amount, 0);
      return retirement + properties + protection + liabilities;
    });

    // Computed properties for spouse's own commitments (from spouse API)
    const spouseRetirementCommitments = computed(() => {
      return spouseFinancialCommitments.value?.commitments?.retirement || [];
    });

    const spousePropertyCommitments = computed(() => {
      return spouseFinancialCommitments.value?.commitments?.properties || [];
    });

    const spouseInvestmentCommitments = computed(() => {
      return spouseFinancialCommitments.value?.commitments?.investments || [];
    });

    const spouseProtectionCommitments = computed(() => {
      return spouseFinancialCommitments.value?.commitments?.protection || [];
    });

    const spouseLiabilityCommitments = computed(() => {
      return spouseFinancialCommitments.value?.commitments?.liabilities || [];
    });

    const hasSpouseRetirementCommitments = computed(() => spouseRetirementCommitments.value.length > 0);
    const hasSpousePropertyCommitments = computed(() => spousePropertyCommitments.value.length > 0);
    const hasSpouseInvestmentCommitments = computed(() => spouseInvestmentCommitments.value.length > 0);
    const hasSpouseProtectionCommitments = computed(() => spouseProtectionCommitments.value.length > 0);
    const hasSpouseLiabilityCommitments = computed(() => spouseLiabilityCommitments.value.length > 0);

    const hasAnySpouseCommitments = computed(() => {
      return hasSpouseRetirementCommitments.value ||
             hasSpousePropertyCommitments.value ||
             hasSpouseInvestmentCommitments.value ||
             hasSpouseProtectionCommitments.value ||
             hasSpouseLiabilityCommitments.value;
    });

    const spouseCommitmentsTotal = computed(() => {
      return spouseFinancialCommitments.value?.totals?.total || 0;
    });

    // Fetch financial commitments
    const fetchFinancialCommitments = async () => {
      // Fetch for all users (including preview mode)
      // Preview users are real database users and use the same code paths
      loadingCommitments.value = true;
      try {
        // Fetch user commitments
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          financialCommitments.value = response.data;
        }

        // Fetch spouse commitments if married
        if (props.isMarried) {
          try {
            const spouseResponse = await userProfileService.getSpouseFinancialCommitments();
            if (spouseResponse.success) {
              spouseFinancialCommitments.value = spouseResponse.data;
            }
          } catch (spouseError) {
            // Spouse commitments fetch failed - not critical
            console.warn('Failed to fetch spouse financial commitments:', spouseError);
          }
        }
      } catch (error) {
        console.error('Failed to fetch financial commitments:', error);
      } finally {
        loadingCommitments.value = false;
      }
    };

    const loadInitialData = () => {
      if (!props.initialData || Object.keys(props.initialData).length === 0) {
        // Default to detailed breakdown mode for first-time users
        useSimpleEntry.value = false;
        return;
      }

      // Check if user explicitly chose simple entry mode
      if (props.initialData.expenditure_entry_mode === 'simple') {
        useSimpleEntry.value = true;
        simpleMonthlyExpenditure.value = Number(props.initialData.monthly_expenditure) || 0;
      } else {
        // Default to detailed breakdown mode (either explicitly chosen or default)
        useSimpleEntry.value = false;
        formData.value = {
          food_groceries: Number(props.initialData.food_groceries) || 0,
          transport_fuel: Number(props.initialData.transport_fuel) || 0,
          healthcare_medical: Number(props.initialData.healthcare_medical) || 0,
          insurance: Number(props.initialData.insurance) || 0,
          mobile_phones: Number(props.initialData.mobile_phones) || 0,
          internet_tv: Number(props.initialData.internet_tv) || 0,
          subscriptions: Number(props.initialData.subscriptions) || 0,
          clothing_personal_care: Number(props.initialData.clothing_personal_care) || 0,
          entertainment_dining: Number(props.initialData.entertainment_dining) || 0,
          holidays_travel: Number(props.initialData.holidays_travel) || 0,
          pets: Number(props.initialData.pets) || 0,
          childcare: Number(props.initialData.childcare) || 0,
          school_fees: Number(props.initialData.school_fees) || 0,
          school_lunches: Number(props.initialData.school_lunches) || 0,
          school_extras: Number(props.initialData.school_extras) || 0,
          university_fees: Number(props.initialData.university_fees) || 0,
          children_activities: Number(props.initialData.children_activities) || 0,
          gifts_charity: Number(props.initialData.gifts_charity) || 0,
          regular_savings: Number(props.initialData.regular_savings) || 0,
          other_expenditure: Number(props.initialData.other_expenditure) || 0,
        };
      }

      // Check if user has expenditure_sharing_mode set to 'separate'
      if (props.initialData.expenditure_sharing_mode === 'separate') {
        useSeparateExpenditure.value = true;
      }
    };

    const loadSpouseData = () => {
      if (!props.spouseData || Object.keys(props.spouseData).length === 0) {
        return;
      }

      // Check if spouse has detailed breakdown data
      const hasDetailedData =
        (props.spouseData.food_groceries || 0) > 0 ||
        (props.spouseData.transport_fuel || 0) > 0 ||
        (props.spouseData.healthcare_medical || 0) > 0 ||
        (props.spouseData.insurance || 0) > 0 ||
        (props.spouseData.mobile_phones || 0) > 0 ||
        (props.spouseData.internet_tv || 0) > 0 ||
        (props.spouseData.subscriptions || 0) > 0 ||
        (props.spouseData.clothing_personal_care || 0) > 0 ||
        (props.spouseData.entertainment_dining || 0) > 0 ||
        (props.spouseData.holidays_travel || 0) > 0 ||
        (props.spouseData.pets || 0) > 0 ||
        (props.spouseData.childcare || 0) > 0 ||
        (props.spouseData.school_fees || 0) > 0 ||
        (props.spouseData.school_lunches || 0) > 0 ||
        (props.spouseData.school_extras || 0) > 0 ||
        (props.spouseData.university_fees || 0) > 0 ||
        (props.spouseData.children_activities || 0) > 0 ||
        (props.spouseData.gifts_charity || 0) > 0 ||
        (props.spouseData.other_expenditure || 0) > 0;

      if (hasDetailedData) {
        spouseFormData.value = {
          food_groceries: Number(props.spouseData.food_groceries) || 0,
          transport_fuel: Number(props.spouseData.transport_fuel) || 0,
          healthcare_medical: Number(props.spouseData.healthcare_medical) || 0,
          insurance: Number(props.spouseData.insurance) || 0,
          mobile_phones: Number(props.spouseData.mobile_phones) || 0,
          internet_tv: Number(props.spouseData.internet_tv) || 0,
          subscriptions: Number(props.spouseData.subscriptions) || 0,
          clothing_personal_care: Number(props.spouseData.clothing_personal_care) || 0,
          entertainment_dining: Number(props.spouseData.entertainment_dining) || 0,
          holidays_travel: Number(props.spouseData.holidays_travel) || 0,
          pets: Number(props.spouseData.pets) || 0,
          childcare: Number(props.spouseData.childcare) || 0,
          school_fees: Number(props.spouseData.school_fees) || 0,
          school_lunches: Number(props.spouseData.school_lunches) || 0,
          school_extras: Number(props.spouseData.school_extras) || 0,
          university_fees: Number(props.spouseData.university_fees) || 0,
          children_activities: Number(props.spouseData.children_activities) || 0,
          gifts_charity: Number(props.spouseData.gifts_charity) || 0,
          regular_savings: Number(props.spouseData.regular_savings) || 0,
          other_expenditure: Number(props.spouseData.other_expenditure) || 0,
        };
      } else {
        spouseSimpleMonthlyExpenditure.value = Number(props.spouseData.monthly_expenditure) || 0;
      }
    };

    const handleSave = () => {
      let dataToSave = {};

      if (useSimpleEntry.value) {
        // Simple entry: save monthly total + financial commitments, clear detailed fields
        dataToSave = {
          monthly_expenditure: totalMonthlyWithCommitments.value,
          annual_expenditure: totalAnnualWithCommitments.value,
          food_groceries: 0,
          transport_fuel: 0,
          healthcare_medical: 0,
          insurance: 0,
          mobile_phones: 0,
          internet_tv: 0,
          subscriptions: 0,
          clothing_personal_care: 0,
          entertainment_dining: 0,
          holidays_travel: 0,
          pets: 0,
          childcare: 0,
          school_fees: 0,
          school_lunches: 0,
          school_extras: 0,
          university_fees: 0,
          children_activities: 0,
          gifts_charity: 0,
          regular_savings: 0,
          other_expenditure: 0,
        };
      } else {
        // Detailed entry: save all fields and calculate total (including financial commitments)
        dataToSave = {
          ...formData.value,
          monthly_expenditure: totalMonthlyWithCommitments.value,
          annual_expenditure: totalAnnualWithCommitments.value,
        };
      }

      // Add expenditure modes to the data
      dataToSave.expenditure_entry_mode = useSimpleEntry.value ? 'simple' : 'category';
      dataToSave.expenditure_sharing_mode = useSeparateExpenditure.value ? 'separate' : 'joint';

      // If in separate mode and married, prepare spouse data
      if (props.isMarried && useSeparateExpenditure.value) {
        let spouseDataToSave = {};

        if (useSimpleEntry.value) {
          spouseDataToSave = {
            monthly_expenditure: spouseTotalMonthlyWithCommitments.value,
            annual_expenditure: spouseTotalAnnualWithCommitments.value,
            food_groceries: 0,
            transport_fuel: 0,
            healthcare_medical: 0,
            insurance: 0,
            mobile_phones: 0,
            internet_tv: 0,
            subscriptions: 0,
            clothing_personal_care: 0,
            entertainment_dining: 0,
            holidays_travel: 0,
            pets: 0,
            childcare: 0,
            school_fees: 0,
            school_lunches: 0,
            school_extras: 0,
            university_fees: 0,
            children_activities: 0,
            gifts_charity: 0,
            regular_savings: 0,
            other_expenditure: 0,
          };
        } else {
          spouseDataToSave = {
            ...spouseFormData.value,
            monthly_expenditure: spouseTotalMonthlyWithCommitments.value,
            annual_expenditure: spouseTotalAnnualWithCommitments.value,
          };
        }

        spouseDataToSave.expenditure_entry_mode = useSimpleEntry.value ? 'simple' : 'category';
        spouseDataToSave.expenditure_sharing_mode = 'separate';

        // Emit both user and spouse data
        emit('save', { userData: dataToSave, spouseData: spouseDataToSave });
      } else {
        // Emit just user data
        emit('save', dataToSave);
      }
    };

    const handleCancel = () => {
      emit('cancel');
    };

    const getNextTab = () => {
      if (!showTabs.value) return null;

      if (activeTab.value === 'user') return 'spouse';
      if (activeTab.value === 'spouse') return 'household';
      return null; // household is the last tab
    };

    const advanceToNextTab = () => {
      const nextTab = getNextTab();
      if (nextTab) {
        activeTab.value = nextTab;
        return true; // More tabs to view
      }
      return false; // No more tabs, ready to proceed
    };

    // Load initial data when component mounts or prop changes
    watch(() => props.initialData, loadInitialData, { immediate: true, deep: true });
    watch(() => props.spouseData, loadSpouseData, { immediate: true, deep: true });

    // Fetch financial commitments on mount
    onMounted(() => {
      fetchFinancialCommitments();
    });

    return {
      useSimpleEntry,
      useSeparateExpenditure,
      activeTab,
      simpleMonthlyExpenditure,
      spouseSimpleMonthlyExpenditure,
      formData,
      spouseFormData,
      showTabs,
      totalMonthlyExpenditure,
      totalAnnualExpenditure,
      spouseTotalMonthlyExpenditure,
      spouseTotalAnnualExpenditure,
      householdTotalMonthlyExpenditure,
      householdTotalAnnualExpenditure,
      // Combined totals including financial commitments
      totalMonthlyWithCommitments,
      totalAnnualWithCommitments,
      spouseTotalMonthlyWithCommitments,
      spouseTotalAnnualWithCommitments,
      householdTotalMonthlyWithCommitments,
      householdTotalAnnualWithCommitments,
      formatCurrency,
      handleSave,
      handleCancel,
      // Financial commitments
      financialCommitments,
      loadingCommitments,
      hasRetirementCommitments,
      hasPropertyCommitments,
      hasInvestmentCommitments,
      hasProtectionCommitments,
      hasLiabilityCommitments,
      hasAnyCommitments,
      // Joint commitments (for spouse tab)
      jointRetirementCommitments,
      jointPropertyCommitments,
      jointProtectionCommitments,
      jointLiabilityCommitments,
      hasJointRetirementCommitments,
      hasJointPropertyCommitments,
      hasJointProtectionCommitments,
      hasJointLiabilityCommitments,
      hasAnyJointCommitments,
      jointCommitmentsTotal,
      // Spouse commitments (from spouse API)
      spouseFinancialCommitments,
      spouseRetirementCommitments,
      spousePropertyCommitments,
      spouseInvestmentCommitments,
      spouseProtectionCommitments,
      spouseLiabilityCommitments,
      hasSpouseRetirementCommitments,
      hasSpousePropertyCommitments,
      hasSpouseInvestmentCommitments,
      hasSpouseProtectionCommitments,
      hasSpouseLiabilityCommitments,
      hasAnySpouseCommitments,
      spouseCommitmentsTotal,
      // Expose for parent component to manage tab cycling
      getNextTab,
      advanceToNextTab,
    };
  },
};
</script>
