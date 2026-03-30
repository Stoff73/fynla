<template>
  <div class="property-detail-inline">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back mb-4"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Properties
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      <p class="mt-4 text-neutral-500">Loading property details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-savannah-100 rounded-lg p-6 text-center">
      <p class="text-raspberry-600">{{ error }}</p>
      <button
        @click="loadProperty"
        class="mt-4 px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
      >
        Retry
      </button>
    </div>

    <!-- Property Content -->
    <div v-else-if="property" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ propertyAddress }}</h1>
            <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ propertyTypeLabel }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 sm:space-x-2 w-full sm:w-auto">
            <button
              v-preview-disabled="'edit'"
              @click="openEditModal"
              class="w-full sm:w-auto px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
            >
              Edit
            </button>
            <button
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="w-full sm:w-auto px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Full Property Value</p>
            <p class="text-2xl font-bold text-violet-600">{{ formatCurrency(calculateFullPropertyValue()) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Your Share ({{ property.ownership_percentage }}%)</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(calculateUserPropertyShare()) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">{{ isSharedOwnership ? `Your Mortgage Share (${property.ownership_percentage}%)` : 'Mortgage Balance' }}</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(mortgageBalance) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4" v-if="property.property_type === 'buy_to_let'">
            <p class="text-sm text-neutral-500">Net Rental Yield</p>
            <p class="text-2xl font-bold text-violet-600">{{ property.net_rental_yield || 0 }}%</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors"
              :class="
                activeTab === tab.id
                  ? 'border-violet-600 text-violet-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Property Details</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Address:</dt>
                    <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ propertyAddress }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Postcode:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ property.postcode }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Property Type:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ propertyTypeLabel }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Purchase Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(property.purchase_date) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Purchase Price:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.purchase_price) }}</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Ownership</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Ownership Type:</dt>
                    <dd class="text-sm font-medium text-horizon-500 capitalize">{{ formatOwnershipType(property.ownership_type) }}</dd>
                  </div>
                  <!-- Owner names for shared ownership -->
                  <template v-if="isSharedOwnership">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">{{ currentUserName }}:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ property.ownership_percentage }}%</dd>
                    </div>
                    <div v-if="jointOwnerDisplayName" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">{{ jointOwnerDisplayName }}:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ jointOwnerPercentage }}%</dd>
                    </div>
                  </template>
                  <!-- Standard percentage display for individual ownership -->
                  <div v-else class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Owner:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ currentUserName }} (100%)</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Valuation</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Full Property Value:</dt>
                    <dd class="text-sm font-medium text-violet-600 font-semibold">{{ formatCurrency(calculateFullPropertyValue()) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Your Share ({{ property.ownership_percentage }}%):</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(calculateUserPropertyShare()) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Valuation Date:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(property.valuation_date) || 'Not set' }}</dd>
                  </div>
                  <div v-if="property.purchase_price" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Value Change:</dt>
                    <dd class="text-sm font-medium" :class="valueChange >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                      {{ formatCurrency(valueChange) }} ({{ valueChangePercent }}%)
                    </dd>
                  </div>
                </dl>
              </div>

              <div v-if="property.property_type === 'buy_to_let'">
                <h3 class="text-lg font-semibold text-horizon-500 mb-3">Rental Income</h3>
                <dl class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Full Monthly Rental Income:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.monthly_rental_income) }}</dd>
                  </div>
                  <div v-if="isSharedOwnership" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Your Share ({{ property.ownership_percentage }}%):</dt>
                    <dd class="text-sm font-medium text-violet-600">{{ formatCurrency(calculateUserRentalIncome()) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Full Annual Rental Income:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency((property.monthly_rental_income || 0) * 12) }}</dd>
                  </div>
                  <div v-if="isSharedOwnership" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                    <dt class="text-sm text-neutral-500">Your Annual Share:</dt>
                    <dd class="text-sm font-medium text-violet-600">{{ formatCurrency(calculateUserRentalIncome() * 12) }}</dd>
                  </div>
                  <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0" v-if="property.tenant_name">
                    <dt class="text-sm text-neutral-500">Tenant:</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ property.tenant_name }}</dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <!-- Mortgage Tab -->
          <div v-show="activeTab === 'mortgage'" class="space-y-6">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-horizon-500">Mortgages</h3>
              <button
                v-preview-disabled="'add'"
                @click="showEditModal = true"
                class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
              >
                Add Mortgage
              </button>
            </div>

            <div v-if="mortgages.length === 0" class="text-center py-8 text-neutral-500">
              <p>No mortgages found for this property.</p>
            </div>

            <div v-else class="space-y-6">
              <div
                v-for="mortgage in mortgages"
                :key="mortgage.id"
                class="bg-white border border-light-gray rounded-lg p-6"
              >
                <!-- Mortgage Header -->
                <div class="flex justify-between items-start mb-6">
                  <div>
                    <h4 class="text-xl font-semibold text-horizon-500">{{ mortgage.lender_name }}</h4>
                    <p class="text-sm text-neutral-500 mt-1">{{ formatMortgageType(mortgage.mortgage_type) }}</p>
                  </div>
                  <button
                    v-preview-disabled="'delete'"
                    @click="deleteMortgageConfirm(mortgage.id)"
                    class="px-3 py-1 text-sm bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700"
                  >
                    Delete
                  </button>
                </div>

                <!-- Mortgage Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <!-- Mortgage Details Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-horizon-500 mb-3">Mortgage Details</h5>
                    <dl class="space-y-2">
                      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Lender:</dt>
                        <dd class="text-sm font-medium text-horizon-500 sm:text-right">{{ mortgage.lender_name }}</dd>
                      </div>
                      <div v-if="mortgage.mortgage_account_number" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Account Number:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ mortgage.mortgage_account_number }}</dd>
                      </div>
                      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Mortgage Type:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ formatMortgageType(mortgage.mortgage_type) }}</dd>
                      </div>
                      <div v-if="mortgage.mortgage_type === 'mixed' && mortgage.repayment_percentage" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500 pl-4">└ Repayment:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ mortgage.repayment_percentage }}%</dd>
                      </div>
                      <div v-if="mortgage.mortgage_type === 'mixed' && mortgage.interest_only_percentage" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500 pl-4">└ Interest Only:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ mortgage.interest_only_percentage }}%</dd>
                      </div>
                      <div v-if="mortgage.country" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Property Country:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ mortgage.country }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Loan Information Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-horizon-500 mb-3">Loan Information</h5>
                    <dl class="space-y-2">
                      <div v-if="mortgage.original_loan_amount" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Original Loan Amount:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(mortgage.original_loan_amount) }}</dd>
                      </div>
                      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Outstanding Balance:</dt>
                        <dd class="text-sm font-medium text-violet-600 font-semibold">{{ formatCurrency(mortgage.outstanding_balance) }}</dd>
                      </div>
                      <div v-if="isSharedOwnership && property.ownership_percentage && property.ownership_percentage < 100" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Your Share ({{ property.ownership_percentage }}%):</dt>
                        <dd class="text-sm font-medium text-violet-600">{{ formatCurrency(calculateUserMortgageShare(mortgage)) }}</dd>
                      </div>
                      <div v-if="mortgage.original_loan_amount" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Amount Paid Off:</dt>
                        <dd class="text-sm font-medium text-spring-600">{{ formatCurrency(mortgage.original_loan_amount - mortgage.outstanding_balance) }}</dd>
                      </div>
                      <div v-if="property.current_value && mortgage.outstanding_balance" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Loan-to-Value (LTV):</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ calculateLTV(mortgage) }}%</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Interest Rate Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-horizon-500 mb-3">Interest Rate</h5>
                    <dl class="space-y-2">
                      <div v-if="mortgage.rate_type !== 'mixed' && mortgage.interest_rate != null" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Interest Rate:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ parseFloat(mortgage.interest_rate).toFixed(2) }}%</dd>
                      </div>
                      <div v-if="mortgage.rate_type" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Rate Type:</dt>
                        <dd class="text-sm font-medium text-horizon-500 capitalize">{{ mortgage.rate_type }}</dd>
                      </div>
                      <div v-if="mortgage.rate_type === 'mixed' && mortgage.fixed_rate_percentage" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500 pl-4">└ Fixed ({{ parseFloat(mortgage.fixed_rate_percentage).toFixed(2) }}%):</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ parseFloat(mortgage.fixed_interest_rate).toFixed(2) }}%</dd>
                      </div>
                      <div v-if="mortgage.rate_type === 'mixed' && mortgage.variable_rate_percentage" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500 pl-4">└ Variable ({{ parseFloat(mortgage.variable_rate_percentage).toFixed(2) }}%):</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ parseFloat(mortgage.variable_interest_rate).toFixed(2) }}%</dd>
                      </div>
                      <div v-if="mortgage.rate_type === 'fixed' && mortgage.rate_fix_end_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Rate Fix Ends:</dt>
                        <dd class="text-sm font-medium text-violet-600">{{ formatDate(mortgage.rate_fix_end_date) }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Payment Information Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-horizon-500 mb-3">Payment Information</h5>
                    <dl class="space-y-2">
                      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Full Monthly Payment:</dt>
                        <dd class="text-sm font-medium text-violet-600 font-semibold">{{ formatCurrency(calculateFullMonthlyPayment(mortgage)) }}</dd>
                      </div>
                      <div v-if="isSharedOwnership && property.ownership_percentage && property.ownership_percentage < 100" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Your Share ({{ property.ownership_percentage }}%):</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(calculateFullMonthlyPayment(mortgage) * (property.ownership_percentage / 100)) }}</dd>
                      </div>
                      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Full Annual Payment:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(calculateFullMonthlyPayment(mortgage) * 12) }}</dd>
                      </div>
                      <div v-if="mortgage.start_date && mortgage.maturity_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Remaining Term:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ calculateRemainingTerm(mortgage.maturity_date) }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Dates Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-horizon-500 mb-3">Important Dates</h5>
                    <dl class="space-y-2">
                      <div v-if="mortgage.start_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Start Date:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ formatDate(mortgage.start_date) }}</dd>
                      </div>
                      <div v-if="mortgage.maturity_date" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">End Date:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ formatDate(mortgage.maturity_date) }}</dd>
                      </div>
                      <div v-else class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">End Date:</dt>
                        <dd class="text-sm text-neutral-500 italic">No end date specified, retirement age of {{ retirementAge }} being used</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Ownership Section -->
                  <div>
                    <h5 class="text-sm font-semibold text-horizon-500 mb-3">Ownership</h5>
                    <dl class="space-y-2">
                      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Ownership Type:</dt>
                        <dd class="text-sm font-medium text-horizon-500 capitalize">{{ isSharedOwnership ? formatOwnershipType(property.ownership_type) : 'Individual' }}</dd>
                      </div>
                      <div v-if="!isSharedOwnership" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Owner:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ currentUserName }}</dd>
                      </div>
                      <div v-if="isSharedOwnership && (mortgage.joint_owner_name || jointOwnerDisplayName)" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                        <dt class="text-sm text-neutral-500">Joint Owner:</dt>
                        <dd class="text-sm font-medium text-horizon-500">{{ mortgage.joint_owner_name || jointOwnerDisplayName }}</dd>
                      </div>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Financials Tab -->
          <div v-show="activeTab === 'financials'">
            <PropertyFinancials
              :property="property"
              :mortgages="mortgages"
              @update-costs="handleCostsUpdate"
            />
          </div>

          <!-- Management Agent Tab -->
          <div v-show="activeTab === 'management_agent'" class="space-y-6">
            <div v-if="!hasManagingAgentData" class="text-center py-8 text-neutral-500">
              <p>No management agent details recorded for this property.</p>
              <p class="text-sm mt-2">Click "Edit" to add management agent details.</p>
            </div>

            <div v-else class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="text-md font-semibold text-horizon-500 mb-3">Agent Details</h4>
                  <dl class="space-y-2">
                    <div v-if="property.managing_agent_name" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Name:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ property.managing_agent_name }}</dd>
                    </div>
                    <div v-if="property.managing_agent_company" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Company:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ property.managing_agent_company }}</dd>
                    </div>
                    <div v-if="property.managing_agent_email" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Email:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ property.managing_agent_email }}</dd>
                    </div>
                    <div v-if="property.managing_agent_phone" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Phone:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ property.managing_agent_phone }}</dd>
                    </div>
                  </dl>
                </div>

                <div v-if="(parseFloat(property.managing_agent_fee) || 0) > 0">
                  <h4 class="text-md font-semibold text-horizon-500 mb-3">Fee</h4>
                  <dl class="space-y-2">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Monthly Fee:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(property.managing_agent_fee) }}</dd>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Annual Fee:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(parseFloat(property.managing_agent_fee) * 12) }}</dd>
                    </div>
                    <div v-if="isSharedOwnership" class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                      <dt class="text-sm text-neutral-500">Your Share ({{ property.ownership_percentage }}%):</dt>
                      <dd class="text-sm font-medium text-violet-600">{{ formatCurrency(parseFloat(property.managing_agent_fee) * (property.ownership_percentage / 100)) }}/month</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <PropertyForm
      v-if="showEditModal"
      :property="property"
      @save="handlePropertyUpdate"
      @close="showEditModal = false"
    />

    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Property"
      message="Are you sure you want to delete this property? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

    <ConfirmDialog
      :show="showDeleteMortgageConfirm"
      title="Delete Mortgage"
      message="Are you sure you want to delete this mortgage?"
      @confirm="handleMortgageDelete"
      @cancel="showDeleteMortgageConfirm = false"
    />
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import PropertyForm from './PropertyForm.vue';
import PropertyFinancials from './PropertyFinancials.vue';
import ConfirmDialog from '../../Common/ConfirmDialog.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'PropertyDetailInline',

  mixins: [currencyMixin],

  components: {
    PropertyForm,
    PropertyFinancials,
    ConfirmDialog,
  },

  props: {
    propertyId: {
      type: Number,
      required: true,
    },
  },

  emits: ['back', 'deleted'],

  data() {
    return {
      activeTab: 'overview',
      showEditModal: false,
      showDeleteConfirm: false,
      showDeleteMortgageConfirm: false,
      mortgageToDelete: null,
    };
  },

  computed: {
    ...mapState('netWorth', ['selectedProperty', 'mortgages', 'loading', 'error']),
    ...mapState('retirement', { retirementProfile: 'profile' }),

    property() {
      return this.selectedProperty;
    },

    retirementAge() {
      return this.retirementProfile?.target_retirement_age || 67;
    },

    tabs() {
      const baseTabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'mortgage', label: 'Mortgage' },
      ];
      if (this.property?.property_type === 'buy_to_let' && this.hasManagingAgentData) {
        baseTabs.push({ id: 'management_agent', label: 'Management Agent' });
      }
      baseTabs.push({ id: 'financials', label: 'Financials' });
      return baseTabs;
    },

    hasManagingAgentData() {
      if (!this.property) return false;
      return !!(
        this.property.managing_agent_name ||
        this.property.managing_agent_company ||
        this.property.managing_agent_email ||
        this.property.managing_agent_phone ||
        (parseFloat(this.property.managing_agent_fee) || 0) > 0
      );
    },

    isSharedOwnership() {
      return this.property?.ownership_type === 'joint' || this.property?.ownership_type === 'tenants_in_common';
    },

    propertyAddress() {
      if (!this.property) return '';
      const parts = [
        this.property.address_line_1,
        this.property.address_line_2,
        this.property.city,
      ].filter(Boolean);
      return parts.join(', ');
    },

    propertyTypeLabel() {
      const types = {
        main_residence: 'Main Residence',
        secondary_residence: 'Secondary Residence',
        buy_to_let: 'Buy to Let',
      };
      return types[this.property?.property_type] || '';
    },

    mortgageBalance() {
      let total = 0;
      if (this.mortgages && this.mortgages.length > 0) {
        total = this.mortgages.reduce((sum, m) => sum + (m.outstanding_balance || 0), 0);
      } else {
        total = this.property?.outstanding_mortgage || 0;
      }
      // Apply ownership split for shared ownership (joint or tenants in common)
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return total * (this.property.ownership_percentage / 100);
      }
      return total;
    },

    valueChange() {
      if (!this.property) return 0;
      return this.property.current_value - this.property.purchase_price;
    },

    valueChangePercent() {
      if (!this.property || this.property.purchase_price === 0) return '0.00';
      const percent = (this.valueChange / this.property.purchase_price) * 100;
      return percent.toFixed(2);
    },

    currentUserName() {
      const user = this.$store.getters['auth/currentUser'];
      if (!user) return 'You';
      return `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'You';
    },

    jointOwnerDisplayName() {
      if (!this.property) return null;
      // Return the joint owner name if set (either from linked user or free text)
      return this.property.joint_owner_name || null;
    },

    jointOwnerPercentage() {
      if (!this.property?.ownership_percentage) return 50;
      return 100 - this.property.ownership_percentage;
    },
  },

  watch: {
    hasManagingAgentData(hasData) {
      if (!hasData && this.activeTab === 'management_agent') {
        this.activeTab = 'overview';
      }
    },
  },

  mounted() {
    this.loadProperty();
  },

  methods: {
    ...mapActions('netWorth', [
      'fetchProperty',
      'fetchPropertyMortgages',
      'updateProperty',
      'deleteProperty',
      'createMortgage',
      'updateMortgage',
      'deleteMortgage',
    ]),

    async loadProperty() {
      try {
        await this.fetchProperty(this.propertyId);
        await this.fetchPropertyMortgages(this.propertyId);
      } catch (error) {
        logger.error('Failed to load property:', error);
      }
    },

    openEditModal() {
      this.showEditModal = true;
    },

    async handlePropertyUpdate(data) {
      const isPreview = this.$store.getters['preview/isPreviewMode'];

      // In preview mode, update local state directly for immediate UI feedback
      if (isPreview) {
        const updatedProperty = {
          ...this.selectedProperty,
          ...data.property,
        };
        this.$store.commit('netWorth/SET_SELECTED_PROPERTY', updatedProperty);
        this.showEditModal = false;
        return;
      }

      // Normal mode - persist to API
      try {
        await this.updateProperty({ id: this.propertyId, data: data.property });

        if (data.mortgage && data.mortgage.outstanding_balance) {
          try {
            if (this.mortgages && this.mortgages.length > 0) {
              await this.updateMortgage({
                id: this.mortgages[0].id,
                data: data.mortgage,
                propertyId: this.propertyId,
              });
            } else {
              await this.createMortgage({
                propertyId: this.propertyId,
                data: data.mortgage,
              });
            }
          } catch (mortgageError) {
            logger.error('Failed to save mortgage:', mortgageError);
          }
        }

        this.showEditModal = false;
        await this.loadProperty();
      } catch (error) {
        logger.error('Failed to update property:', error);
      }
    },

    async handleCostsUpdate(costsData) {
      try {
        await this.updateProperty({ id: this.propertyId, data: costsData });
        await this.loadProperty();
      } catch (error) {
        logger.error('Failed to update costs:', error);
        throw error;
      }
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteProperty(this.propertyId);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        logger.error('Failed to delete property:', error);
      }
    },

    deleteMortgageConfirm(mortgageId) {
      this.mortgageToDelete = mortgageId;
      this.showDeleteMortgageConfirm = true;
    },

    async handleMortgageDelete() {
      try {
        await this.deleteMortgage({
          id: this.mortgageToDelete,
          propertyId: this.propertyId,
        });
        this.showDeleteMortgageConfirm = false;
        this.mortgageToDelete = null;
        await this.loadProperty();
      } catch (error) {
        logger.error('Failed to delete mortgage:', error);
      }
    },

    formatMortgageType(type) {
      const types = {
        repayment: 'Repayment',
        interest_only: 'Interest Only',
        part_and_part: 'Part and Part',
        mixed: 'Mixed',
      };
      return types[type] || type;
    },

    formatOwnershipType(type) {
      const types = {
        individual: 'Individual',
        joint: 'Joint',
        tenants_in_common: 'Tenants in Common',
        trust: 'Trust',
      };
      return types[type] || type;
    },

    calculateFullOutstandingBalance(mortgage) {
      // Single-record pattern: DB stores FULL balance
      return mortgage.mortgage_full_balance ?? mortgage.outstanding_balance ?? 0;
    },

    calculateFullMonthlyPayment(mortgage) {
      // Single-record pattern: DB stores FULL payment
      return mortgage.monthly_payment ?? 0;
    },

    calculateFullPropertyValue() {
      // Single-record pattern: DB stores FULL value
      return this.property?.full_value ?? this.property?.current_value ?? 0;
    },

    calculateUserPropertyShare() {
      // Single-record pattern: Calculate user's share from full value
      if (this.property?.user_share !== undefined) {
        return this.property.user_share;
      }
      const fullValue = this.calculateFullPropertyValue();
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return fullValue * (this.property.ownership_percentage / 100);
      }
      return fullValue;
    },

    calculateUserMortgageShare(mortgage) {
      const fullBalance = mortgage.outstanding_balance || 0;
      // For shared ownership properties, calculate user's share based on property ownership %
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return fullBalance * (this.property.ownership_percentage / 100);
      }
      // Individual mortgage = 100% belongs to this user
      return fullBalance;
    },

    calculateUserRentalIncome() {
      // Single-record pattern: Calculate user's share of rental income
      const fullRentalIncome = this.property?.monthly_rental_income || 0;
      if (this.isSharedOwnership && this.property?.ownership_percentage) {
        return fullRentalIncome * (this.property.ownership_percentage / 100);
      }
      return fullRentalIncome;
    },

    calculateLTV(mortgage) {
      const fullBalance = this.calculateFullOutstandingBalance(mortgage);
      const fullValue = this.calculateFullPropertyValue();

      if (!fullValue || fullValue === 0) return '0.00';

      const ltv = (fullBalance / fullValue) * 100;
      return ltv.toFixed(2);
    },

    calculateRemainingTerm(maturityDate) {
      if (!maturityDate) return 'N/A';
      const today = new Date();
      const maturity = new Date(maturityDate);
      const diffTime = maturity - today;
      const diffMonths = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 30.44));

      if (diffMonths <= 0) return 'Matured';

      const years = Math.floor(diffMonths / 12);
      const months = diffMonths % 12;

      if (years === 0) return `${months} month${months !== 1 ? 's' : ''}`;
      if (months === 0) return `${years} year${years !== 1 ? 's' : ''}`;
      return `${years} year${years !== 1 ? 's' : ''}, ${months} month${months !== 1 ? 's' : ''}`;
    },

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },
  },
};
</script>

<style scoped>
.property-detail-inline {
  animation: fadeIn 0.3s ease-out;
}

</style>
