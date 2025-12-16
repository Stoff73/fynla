<template>
  <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" @click.self="">
    <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden" @click.stop>
      <div class="overflow-y-auto max-h-[90vh]">
      <!-- Header -->
      <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 rounded-t-lg z-10">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-semibold text-gray-900">
            {{ isEditMode ? 'Edit Property' : 'Add Property' }}
          </h3>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600 transition-colors"
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
              v-for="(step, index) in activeSteps"
              :key="index"
              class="flex-1 flex flex-col items-center relative"
            >
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all cursor-pointer hover:opacity-80"
                :class="
                  currentStep === index + 1
                    ? 'bg-blue-600 border-blue-600 text-white'
                    : (isEditMode || currentStep > index + 1)
                    ? 'bg-green-600 border-green-600 text-white'
                    : 'bg-white border-gray-300 text-gray-400'
                "
                @click="goToStep(index + 1)"
                :title="'Go to ' + step"
              >
                {{ index + 1 }}
              </div>
              <span class="text-xs mt-1 text-center px-1" :class="currentStep === index + 1 ? 'text-blue-600 font-semibold' : 'text-gray-500'">
                {{ step }}
              </span>
              <div
                v-if="index < activeSteps.length - 1"
                class="absolute h-0.5 top-5 left-1/2 -z-10"
                :style="{ width: 'calc(100% - 2.5rem)' }"
                :class="(isEditMode || currentStep > index + 1) ? 'bg-green-600' : 'bg-gray-300'"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Form Content -->
      <form @submit.prevent="handleSubmit" novalidate>
        <div class="px-6 py-4">
          <!-- Error Message -->
          <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
              <svg class="w-5 h-5 text-red-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
              <p class="text-sm text-red-700">{{ error }}</p>
            </div>
          </div>

          <!-- Step 1: Basic Information -->
          <div v-show="currentStep === stepMapping[1]" class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h4>

            <div>
              <label for="property_type" class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
              <select
                id="property_type"
                name="property_type"
                v-model="form.property_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select property type</option>
                <option value="main_residence">Main Residence</option>
                <option value="secondary_residence">Secondary Residence</option>
                <option value="buy_to_let">Buy to Let</option>
              </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="address_line_1" class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                <input
                  id="address_line_1"
                  name="address_line_1"
                  v-model="form.address_line_1"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="address_line_2" class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                <input
                  id="address_line_2"
                  name="address_line_2"
                  v-model="form.address_line_2"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input
                  id="city"
                  name="city"
                  v-model="form.city"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="county" class="block text-sm font-medium text-gray-700 mb-1">County</label>
                <input
                  id="county"
                  name="county"
                  v-model="form.county"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="postcode" class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                <input
                  id="postcode"
                  name="postcode"
                  v-model="form.postcode"
                  type="text"
                  pattern="^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
                  placeholder="SW1A 1AA"
                />
              </div>
            </div>

            <!-- Country Selector -->
            <div>
              <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Property Country</label>
              <CountrySelector
                v-model="form.country"
                placeholder="Select country where property is located"
                id="country"
              />
              <p class="text-sm text-gray-500 mt-1">Country where the property is located</p>

              <!-- Non-UK Property Message -->
              <div v-if="form.country !== 'United Kingdom'" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <p class="text-sm text-blue-800">
                  <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  Please enter values in GBP. Local currency and currency conversion is coming soon.
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                <input
                  id="purchase_date"
                  name="purchase_date"
                  v-model="form.purchase_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-1">Purchase Price (£)</label>
                <input
                  id="purchase_price"
                  name="purchase_price"
                  v-model.number="form.purchase_price"
                  type="number"
                  step="any"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="current_value" class="block text-sm font-medium text-gray-700 mb-1">
                  {{ isJointPropertyEdit ? 'Full Property Value (£)' : 'Current Value (£)' }}
                </label>
                <input
                  id="current_value"
                  name="current_value"
                  v-model.number="form.current_value"
                  type="number"
                  step="any"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p v-if="isJointPropertyEdit" class="text-xs text-blue-600 mt-1">
                  Enter the full property value. Your {{ form.ownership_percentage }}% share will be calculated automatically.
                </p>
              </div>

              <div>
                <label for="valuation_date" class="block text-sm font-medium text-gray-700 mb-1">Valuation Date</label>
                <input
                  id="valuation_date"
                  name="valuation_date"
                  v-model="form.valuation_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <!-- Mortgage Checkbox -->
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
              <label class="flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  v-model="hasMortgage"
                  class="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <span class="text-sm font-medium text-gray-700">This property has a mortgage</span>
              </label>
              <p class="text-xs text-gray-500 mt-1 ml-7">Check this if you want to add mortgage details</p>
            </div>
          </div>

          <!-- Step 2: Ownership -->
          <div v-show="currentStep === stepMapping[2]" class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Ownership</h4>

            <!-- Tenure Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tenure Type</label>
              <div class="space-y-2">
                <label class="flex items-center">
                  <input
                    type="radio"
                    v-model="form.tenure_type"
                    value="freehold"
                    class="mr-2"
                  />
                  <span>Freehold</span>
                </label>
                <label class="flex items-center">
                  <input
                    type="radio"
                    v-model="form.tenure_type"
                    value="leasehold"
                    class="mr-2"
                  />
                  <span>Leasehold</span>
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Note: UK government is phasing out leasehold for new builds. Ground rent eliminated for new leases from 2022.
              </p>
            </div>

            <!-- Leasehold Details (conditional) -->
            <div v-if="form.tenure_type === 'leasehold'" class="p-4 bg-amber-50 border border-amber-200 rounded-md space-y-4">
              <p class="text-sm text-amber-800 font-medium">Leasehold Property Details</p>

              <div>
                <label for="lease_remaining_years" class="block text-sm font-medium text-gray-700 mb-1">
                  Remaining Lease Term (Years)
                </label>
                <input
                  id="lease_remaining_years"
                  v-model.number="form.lease_remaining_years"
                  type="number"
                  min="1"
                  max="999"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p v-if="form.lease_remaining_years && form.lease_remaining_years < 80" class="text-xs text-amber-600 mt-1">
                  ⚠️ Properties with less than 80 years remaining may be difficult to mortgage
                </p>
                <p v-if="form.lease_remaining_years && form.lease_remaining_years < 60" class="text-xs text-red-600 mt-1">
                  ⚠️ Properties with less than 60 years remaining may significantly lose value
                </p>
              </div>

              <div>
                <label for="lease_expiry_date" class="block text-sm font-medium text-gray-700 mb-1">
                  Lease Expiry Date
                </label>
                <input
                  id="lease_expiry_date"
                  v-model="form.lease_expiry_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <!-- Ownership Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Ownership Type</label>
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
                    <p class="text-xs text-gray-600">You own 100% of the property</p>
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
                    <span class="font-medium">Joint Tenancy</span>
                    <p class="text-xs text-gray-600">Equal rights to whole property. Passes automatically to survivor (bypasses will).</p>
                  </div>
                </label>
                <label class="flex items-start">
                  <input
                    type="radio"
                    v-model="form.ownership_type"
                    value="tenants_in_common"
                    class="mr-2 mt-0.5"
                  />
                  <div>
                    <span class="font-medium">Tenants in Common</span>
                    <p class="text-xs text-gray-600">Specified shares (may be unequal). Your share passes via your will.</p>
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
                    <p class="text-xs text-gray-600">Property held in trust</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Joint Tenancy Details -->
            <div v-if="form.ownership_type === 'joint'" class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
              <p class="text-sm text-blue-800 font-medium">Joint Tenancy Details</p>

              <!-- Ownership Split Display -->
              <div class="bg-white p-3 rounded border border-blue-300">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-700">Your Share</p>
                    <p class="text-2xl font-bold text-blue-600">50%</p>
                  </div>
                  <div class="text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-700">Joint Owner's Share</p>
                    <p class="text-2xl font-bold text-blue-600">50%</p>
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">Equal shares - Passes to survivor automatically</p>
              </div>

              <!-- Joint Owner Selection -->
              <div>
                <label for="joint_owner_selection" class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner
                </label>
                <select
                  id="joint_owner_selection"
                  v-model="jointOwnerSelection"
                  @change="handleJointOwnerSelection"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select joint owner</option>
                  <option v-if="spouse" :value="'linked_' + spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                  <option value="other">Other (Enter Name)</option>
                </select>
              </div>

              <!-- Free Text Joint Owner Name -->
              <div v-if="jointOwnerSelection === 'other'">
                <label for="joint_owner_name" class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner Name
                </label>
                <input
                  id="joint_owner_name"
                  v-model="form.joint_owner_name"
                  type="text"
                  placeholder="Enter joint owner's full name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Note: This person doesn't have an account in the system. The property will only appear in your account.
                </p>
              </div>

              <p class="text-xs text-gray-500">
                Joint assets with linked accounts will appear in both owners' accounts with respective ownership percentages.
              </p>
            </div>

            <!-- Tenants in Common Details -->
            <div v-if="form.ownership_type === 'tenants_in_common'" class="space-y-4 p-4 bg-green-50 border border-green-200 rounded-md">
              <p class="text-sm text-green-800 font-medium">Tenants in Common Details</p>

              <!-- Ownership Percentage Input -->
              <div>
                <label for="ownership_percentage" class="block text-sm font-medium text-gray-700 mb-1">
                  Your Ownership Share (%)
                </label>
                <input
                  id="ownership_percentage"
                  v-model.number="form.ownership_percentage"
                  type="number"
                  min="1"
                  max="99"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Enter your percentage share. Shares can be unequal (e.g., 60/40, 70/30).
                </p>
              </div>

              <!-- Ownership Split Display -->
              <div class="bg-white p-3 rounded border border-green-300">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-700">Your Share</p>
                    <p class="text-2xl font-bold text-green-600">{{ form.ownership_percentage || 0 }}%</p>
                  </div>
                  <div class="text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-700">Co-Owner's Share</p>
                    <p class="text-2xl font-bold text-green-600">{{ coOwnerPercentage }}%</p>
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">Your share passes via your will or intestacy rules</p>
              </div>

              <!-- Joint Owner Selection -->
              <div>
                <label for="tenants_joint_owner_selection" class="block text-sm font-medium text-gray-700 mb-1">
                  Co-Owner
                </label>
                <select
                  id="tenants_joint_owner_selection"
                  v-model="jointOwnerSelection"
                  @change="handleJointOwnerSelection"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                  <option value="">Select co-owner</option>
                  <option v-if="spouse" :value="'linked_' + spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                  <option value="other">Other (Enter Name)</option>
                </select>
              </div>

              <!-- Free Text Joint Owner Name -->
              <div v-if="jointOwnerSelection === 'other'">
                <label for="tenants_joint_owner_name" class="block text-sm font-medium text-gray-700 mb-1">
                  Co-Owner Name
                </label>
                <input
                  id="tenants_joint_owner_name"
                  v-model="form.joint_owner_name"
                  type="text"
                  placeholder="Enter co-owner's full name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Note: This person doesn't have an account in the system. The property will only appear in your account.
                </p>
              </div>

              <p class="text-xs text-gray-500">
                Joint assets with linked accounts will appear in both owners' accounts with respective ownership percentages.
              </p>
            </div>

            <!-- Trust Details -->
            <div v-if="form.ownership_type === 'trust'" class="space-y-4 p-4 bg-purple-50 border border-purple-200 rounded-md">
              <p class="text-sm text-purple-800 font-medium">Trust Ownership Details</p>

              <!-- Trust Feature Notice -->
              <div class="p-3 bg-blue-50 border border-blue-200 rounded-md">
                <p class="text-sm text-blue-800">
                  <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  While it is technically possible to gift or transfer a % of property into Trust, this feature will be coming in the Trust's update.
                </p>
              </div>

              <!-- Ownership Split Display -->
              <div class="bg-white p-3 rounded border border-purple-300">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-700">Your Share</p>
                    <p class="text-2xl font-bold text-purple-600">0%</p>
                  </div>
                  <div class="text-gray-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-gray-700">Trust's Share</p>
                    <p class="text-2xl font-bold text-purple-600">100%</p>
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">Property held entirely in trust</p>
              </div>

              <!-- Trust Selection -->
              <div>
                <label for="trust_selection" class="block text-sm font-medium text-gray-700 mb-1">
                  Trust
                </label>
                <select
                  id="trust_selection"
                  v-model="trustSelection"
                  @change="handleTrustSelection"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                  <option value="">Select trust</option>
                  <option value="other">Other (Enter Trust Name)</option>
                  <!-- Registered trusts would be loaded dynamically here -->
                </select>
              </div>

              <!-- Free Text Trust Name -->
              <div v-if="trustSelection === 'other'">
                <label for="trust_name" class="block text-sm font-medium text-gray-700 mb-1">
                  Trust Name
                </label>
                <input
                  id="trust_name"
                  v-model="form.trust_name"
                  type="text"
                  placeholder="Enter trust name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Note: This trust is not formally registered in the system. You can add full trust details in the Estate Planning module.
                </p>
              </div>
            </div>
          </div>

          <!-- Step 3: Mortgage (Conditional - only if hasMortgage) -->
          <div v-if="hasMortgage" v-show="currentStep === stepMapping[3]" class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Mortgage Details</h4>

            <div>
              <label for="lender_name" class="block text-sm font-medium text-gray-700 mb-1">
                Lender Name
              </label>
              <input
                id="lender_name"
                v-model="mortgageForm.lender_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label for="mortgage_account_number" class="block text-sm font-medium text-gray-700 mb-1">Mortgage Account Number</label>
              <input
                id="mortgage_account_number"
                v-model="mortgageForm.mortgage_account_number"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label for="mortgage_type" class="block text-sm font-medium text-gray-700 mb-1">Mortgage Type</label>
              <select
                id="mortgage_type"
                v-model="mortgageForm.mortgage_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select mortgage type</option>
                <option value="repayment">Repayment</option>
                <option value="interest_only">Interest Only</option>
                <option value="mixed">Mixed</option>
              </select>
            </div>

            <!-- Mixed Mortgage Type Fields -->
            <div v-if="mortgageForm.mortgage_type === 'mixed'" class="bg-blue-50 border border-blue-200 rounded-md p-4 space-y-4">
              <div class="flex items-start gap-2 mb-3">
                <svg class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-blue-900">Mixed Mortgage - Repayment Split</p>
                  <p class="text-xs text-blue-700 mt-1">
                    Specify what percentage is on a repayment basis vs interest-only basis
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="repayment_percentage" class="block text-sm font-medium text-blue-900 mb-1">
                    Repayment Portion (%)
                  </label>
                  <input
                    id="repayment_percentage"
                    v-model.number="mortgageForm.repayment_percentage"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    placeholder="e.g., 40"
                  />
                  <p class="text-xs text-blue-700 mt-1">Percentage on repayment basis</p>
                </div>

                <div>
                  <label for="interest_only_percentage" class="block text-sm font-medium text-blue-900 mb-1">
                    Interest-Only Portion (%)
                  </label>
                  <input
                    id="interest_only_percentage"
                    v-model.number="mortgageForm.interest_only_percentage"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    placeholder="e.g., 60"
                  />
                  <p class="text-xs text-blue-700 mt-1">Percentage on interest-only basis</p>
                </div>
              </div>

              <div v-if="mortgageTypePercentageTotal !== 100 && (mortgageForm.repayment_percentage || mortgageForm.interest_only_percentage)"
                   class="bg-red-50 border border-red-200 rounded-md p-3">
                <p class="text-sm text-red-800">
                  ⚠️ Percentages must total 100%. Current total: {{ mortgageTypePercentageTotal }}%
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="original_loan_amount" class="block text-sm font-medium text-gray-700 mb-1">Original Loan Amount (£)</label>
                <input
                  id="original_loan_amount"
                  v-model.number="mortgageForm.original_loan_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="outstanding_balance" class="block text-sm font-medium text-gray-700 mb-1">
                  {{ isJointPropertyEdit ? 'Full Outstanding Balance (£)' : 'Outstanding Balance (£)' }}
                </label>
                <input
                  id="outstanding_balance"
                  v-model.number="mortgageForm.outstanding_balance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p v-if="isJointPropertyEdit" class="text-xs text-blue-600 mt-1">
                  Enter the full mortgage balance. Your {{ form.ownership_percentage }}% share will be calculated automatically.
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Hide standard interest rate when mixed rate type is selected -->
              <div v-if="mortgageForm.rate_type !== 'mixed'">
                <label for="interest_rate" class="block text-sm font-medium text-gray-700 mb-1">Interest Rate (%)</label>
                <input
                  id="interest_rate"
                  v-model.number="mortgageForm.interest_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div :class="{ 'md:col-span-2': mortgageForm.rate_type === 'mixed' }">
                <label for="rate_type" class="block text-sm font-medium text-gray-700 mb-1">Rate Type</label>
                <select
                  id="rate_type"
                  v-model="mortgageForm.rate_type"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select rate type</option>
                  <option value="fixed">Fixed</option>
                  <option value="variable">Variable</option>
                  <option value="tracker">Tracker</option>
                  <option value="discount">Discount</option>
                  <option value="mixed">Mixed</option>
                </select>
              </div>
            </div>

            <div v-if="mortgageForm.rate_type === 'fixed'">
              <label for="rate_fix_end_date" class="block text-sm font-medium text-gray-700 mb-1">Rate Fix End Date</label>
              <input
                id="rate_fix_end_date"
                v-model="mortgageForm.rate_fix_end_date"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Mixed Rate Type Fields -->
            <div v-if="mortgageForm.rate_type === 'mixed'" class="bg-green-50 border border-green-200 rounded-md p-4 space-y-4">
              <div class="flex items-start gap-2 mb-3">
                <svg class="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-green-900">Mixed Rate - Interest Rate Split</p>
                  <p class="text-xs text-green-700 mt-1">
                    Specify what percentage has a fixed rate vs variable rate and the rates for each portion
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="fixed_rate_percentage" class="block text-sm font-medium text-green-900 mb-1">
                    Fixed Rate Portion (%)
                  </label>
                  <input
                    id="fixed_rate_percentage"
                    v-model.number="mortgageForm.fixed_rate_percentage"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-green-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"
                    placeholder="e.g., 20"
                  />
                  <p class="text-xs text-green-700 mt-1">Percentage at fixed rate</p>
                </div>

                <div>
                  <label for="variable_rate_percentage" class="block text-sm font-medium text-green-900 mb-1">
                    Variable Rate Portion (%)
                  </label>
                  <input
                    id="variable_rate_percentage"
                    v-model.number="mortgageForm.variable_rate_percentage"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-green-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"
                    placeholder="e.g., 80"
                  />
                  <p class="text-xs text-green-700 mt-1">Percentage at variable rate</p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="fixed_interest_rate" class="block text-sm font-medium text-green-900 mb-1">
                    Fixed Interest Rate (%)
                  </label>
                  <input
                    id="fixed_interest_rate"
                    v-model.number="mortgageForm.fixed_interest_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-green-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"
                    placeholder="e.g., 3.5"
                  />
                  <p class="text-xs text-green-700 mt-1">Annual rate for fixed portion</p>
                </div>

                <div>
                  <label for="variable_interest_rate" class="block text-sm font-medium text-green-900 mb-1">
                    Variable Interest Rate (%)
                  </label>
                  <input
                    id="variable_interest_rate"
                    v-model.number="mortgageForm.variable_interest_rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-green-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"
                    placeholder="e.g., 4.2"
                  />
                  <p class="text-xs text-green-700 mt-1">Annual rate for variable portion</p>
                </div>
              </div>

              <div v-if="rateTypePercentageTotal !== 100 && (mortgageForm.fixed_rate_percentage || mortgageForm.variable_rate_percentage)"
                   class="bg-red-50 border border-red-200 rounded-md p-3">
                <p class="text-sm text-red-800">
                  ⚠️ Percentages must total 100%. Current total: {{ rateTypePercentageTotal }}%
                </p>
              </div>
            </div>

            <div>
              <label for="monthly_payment" class="block text-sm font-medium text-gray-700 mb-1">
                Monthly Payment (£)
              </label>
              <input
                id="monthly_payment"
                v-model.number="mortgageForm.monthly_payment"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <p class="text-sm text-gray-500 mt-1">Enter your monthly mortgage payment amount</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input
                  id="start_date"
                  v-model="mortgageForm.start_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label for="maturity_date" class="block text-sm font-medium text-gray-700 mb-1">Maturity Date</label>
                <input
                  id="maturity_date"
                  v-model="mortgageForm.maturity_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <!-- Mortgage Ownership Section -->
            <div class="space-y-4 pt-4 border-t border-gray-200">
              <h5 class="text-sm font-semibold text-gray-900">Mortgage Ownership</h5>

              <div>
                <label for="mortgage_ownership_type" class="block text-sm font-medium text-gray-700 mb-1">
                  Ownership Type
                </label>
                <select
                  id="mortgage_ownership_type"
                  v-model="mortgageForm.ownership_type"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="individual">Individual Owner</option>
                  <option value="joint">Joint Owner</option>
                </select>
              </div>

              <div v-if="mortgageForm.ownership_type === 'joint'">
                <label for="mortgage_joint_owner_selection" class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner
                </label>
                <select
                  id="mortgage_joint_owner_selection"
                  v-model="mortgageJointOwnerSelection"
                  @change="handleMortgageJointOwnerSelection"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select joint owner</option>
                  <option v-if="spouse" :value="'linked_' + spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                  <option value="other">Other (Enter Name)</option>
                </select>
              </div>

              <!-- Free Text Joint Owner Name -->
              <div v-if="mortgageJointOwnerSelection === 'other'">
                <label for="mortgage_joint_owner_name" class="block text-sm font-medium text-gray-700 mb-1">
                  Joint Owner Name
                </label>
                <input
                  id="mortgage_joint_owner_name"
                  v-model="mortgageForm.joint_owner_name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter joint owner's name"
                />
              </div>
            </div>
          </div>

          <!-- Step 4: Costs -->
          <div v-show="currentStep === stepMapping[4]" class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Monthly Costs</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Mortgage Payment (pulled from mortgage data) -->
              <div v-if="hasMortgage && mortgageForm.monthly_payment">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mortgage Payment (£/month)</label>
                <div class="w-full px-3 py-2 bg-blue-50 border border-blue-200 rounded-md text-gray-700 font-medium">
                  {{ formatCurrency(mortgageForm.monthly_payment) }}
                </div>
              </div>

              <!-- Council Tax -->
              <div>
                <label for="monthly_council_tax" class="block text-sm font-medium text-gray-700 mb-1">Council Tax (£/month)</label>
                <input
                  id="monthly_council_tax"
                  v-model.number="form.monthly_council_tax"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Gas -->
              <div>
                <label for="monthly_gas" class="block text-sm font-medium text-gray-700 mb-1">Gas (£/month)</label>
                <input
                  id="monthly_gas"
                  v-model.number="form.monthly_gas"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Electricity -->
              <div>
                <label for="monthly_electricity" class="block text-sm font-medium text-gray-700 mb-1">Electricity (£/month)</label>
                <input
                  id="monthly_electricity"
                  v-model.number="form.monthly_electricity"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Water -->
              <div>
                <label for="monthly_water" class="block text-sm font-medium text-gray-700 mb-1">Water (£/month)</label>
                <input
                  id="monthly_water"
                  v-model.number="form.monthly_water"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Building Insurance -->
              <div>
                <label for="monthly_building_insurance" class="block text-sm font-medium text-gray-700 mb-1">Building Insurance (£/month)</label>
                <input
                  id="monthly_building_insurance"
                  v-model.number="form.monthly_building_insurance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Contents Insurance -->
              <div>
                <label for="monthly_contents_insurance" class="block text-sm font-medium text-gray-700 mb-1">Contents Insurance (£/month)</label>
                <input
                  id="monthly_contents_insurance"
                  v-model.number="form.monthly_contents_insurance"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Service Charge (with tooltip) -->
              <div>
                <label for="monthly_service_charge" class="block text-sm font-medium text-gray-700 mb-1">
                  Service Charge (£/month)
                  <span class="relative inline-block group">
                    <svg class="inline w-4 h-4 text-gray-400 cursor-help ml-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span class="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-gray-900 rounded-md whitespace-nowrap z-10">
                      For flats/apartments: fees for communal areas, maintenance, lift, porter
                    </span>
                  </span>
                </label>
                <input
                  id="monthly_service_charge"
                  v-model.number="form.monthly_service_charge"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Maintenance Reserve (with tooltip) -->
              <div>
                <label for="monthly_maintenance_reserve" class="block text-sm font-medium text-gray-700 mb-1">
                  Maintenance Reserve (£/month)
                  <span class="relative inline-block group">
                    <svg class="inline w-4 h-4 text-gray-400 cursor-help ml-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span class="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-gray-900 rounded-md whitespace-nowrap z-10">
                      Monthly amount set aside for repairs, replacements, and future maintenance
                    </span>
                  </span>
                </label>
                <input
                  id="monthly_maintenance_reserve"
                  v-model.number="form.monthly_maintenance_reserve"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Other Monthly Costs -->
              <div>
                <label for="other_monthly_costs" class="block text-sm font-medium text-gray-700 mb-1">Other Monthly Costs (£/month)</label>
                <input
                  id="other_monthly_costs"
                  v-model.number="form.other_monthly_costs"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <!-- Total Monthly Costs Summary -->
            <div class="mt-6 p-4 bg-gray-50 border-2 border-gray-300 rounded-lg">
              <div class="flex justify-between items-center">
                <span class="text-lg font-semibold text-gray-900">Total Monthly Costs</span>
                <span class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalMonthlyCosts) }}</span>
              </div>
              <p class="text-sm text-gray-600 mt-2">Total Annual: {{ formatCurrency(totalMonthlyCosts * 12) }}</p>
            </div>
          </div>

          <!-- Step 5: BTL Details (Conditional - only if property_type is buy_to_let) -->
          <div v-if="form.property_type === 'buy_to_let'" v-show="currentStep === stepMapping[5]" class="space-y-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Buy to Let Details</h4>

            <div class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="monthly_rental_income" class="block text-sm font-medium text-gray-700 mb-1">Monthly Rental Income (£)</label>
                  <input
                    id="monthly_rental_income"
                    v-model.number="form.monthly_rental_income"
                    type="number"
                    step="any"
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label for="tenant_name" class="block text-sm font-medium text-gray-700 mb-1">Tenant Name</label>
                  <input
                    id="tenant_name"
                    v-model="form.tenant_name"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label for="tenant_email" class="block text-sm font-medium text-gray-700 mb-1">Tenant Email Address</label>
                  <input
                    id="tenant_email"
                    v-model="form.tenant_email"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="tenant@example.com"
                  />
                  <p class="text-xs text-gray-500 mt-1">This information is used in the Letter to Spouse section of the app</p>
                </div>

                <div>
                  <label for="lease_start_date" class="block text-sm font-medium text-gray-700 mb-1">Lease Start Date</label>
                  <input
                    id="lease_start_date"
                    v-model="form.lease_start_date"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label for="lease_end_date" class="block text-sm font-medium text-gray-700 mb-1">Lease End Date</label>
                  <input
                    id="lease_end_date"
                    v-model="form.lease_end_date"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <!-- Managing Agent Details Section -->
              <div class="mt-6 pt-6 border-t border-gray-200">
                <h5 class="text-md font-semibold text-gray-800 mb-4">Managing Agent Details (Optional)</h5>
                <p class="text-sm text-gray-600 mb-4">If you use a managing agent to manage this property, enter their details below.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="managing_agent_name" class="block text-sm font-medium text-gray-700 mb-1">Agent Name</label>
                    <input
                      id="managing_agent_name"
                      v-model="form.managing_agent_name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="e.g., John Smith"
                    />
                  </div>

                  <div>
                    <label for="managing_agent_company" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                    <input
                      id="managing_agent_company"
                      v-model="form.managing_agent_company"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="e.g., ABC Property Management Ltd"
                    />
                  </div>

                  <div>
                    <label for="managing_agent_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input
                      id="managing_agent_email"
                      v-model="form.managing_agent_email"
                      type="email"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="agent@propertymanagement.com"
                    />
                  </div>

                  <div>
                    <label for="managing_agent_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input
                      id="managing_agent_phone"
                      v-model="form.managing_agent_phone"
                      type="tel"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="e.g., 020 1234 5678"
                    />
                  </div>

                  <div>
                    <label for="managing_agent_fee" class="block text-sm font-medium text-gray-700 mb-1">Monthly Management Fee (£)</label>
                    <input
                      id="managing_agent_fee"
                      v-model.number="form.managing_agent_fee"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="e.g., 150.00"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-600">{{ error }}</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex justify-between rounded-b-lg">
          <button
            type="button"
            @click="previousStep"
            v-show="currentStep > 1"
            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors"
          >
            Previous
          </button>

          <div class="flex space-x-2 ml-auto">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors"
            >
              Cancel
            </button>

            <button
              v-if="currentStep < totalSteps && !isEditMode"
              type="button"
              @click="nextStep"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Next
            </button>

            <button
              v-if="currentStep < totalSteps && isEditMode"
              type="button"
              @click="nextStep"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors"
            >
              Next Step
            </button>

            <button
              v-if="currentStep >= totalSteps || isEditMode"
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Saving...' : 'Save Property' }}
            </button>
          </div>
        </div>
      </form>
      </div>
    </div>
  </div>
</template>

<script>
import CountrySelector from '@/components/Shared/CountrySelector.vue';

export default {
  name: 'PropertyForm',

  components: {
    CountrySelector,
  },

  props: {
    property: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      currentStep: 1,
      hasMortgage: false,
      jointOwnerSelection: '',
      trustSelection: '',
      mortgageJointOwnerSelection: '',
      form: {
        property_type: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        county: '',
        postcode: '',
        purchase_date: '',
        purchase_price: null,
        current_value: null,
        valuation_date: new Date().toISOString().split('T')[0],  // Default to current date
        ownership_type: 'individual',
        joint_ownership_type: null,
        tenure_type: 'freehold',
        lease_remaining_years: null,
        lease_expiry_date: '',
        country: 'United Kingdom',
        ownership_percentage: 100,
        joint_owner_id: null,
        joint_owner_name: '',
        household_id: null,
        trust_id: null,
        trust_name: '',
        monthly_council_tax: null,
        monthly_gas: null,
        monthly_electricity: null,
        monthly_water: null,
        monthly_building_insurance: null,
        monthly_contents_insurance: null,
        monthly_service_charge: null,
        monthly_maintenance_reserve: null,
        other_monthly_costs: null,
        monthly_rental_income: null,
        tenant_name: '',
        tenant_email: '',
        managing_agent_name: '',
        managing_agent_company: '',
        managing_agent_email: '',
        managing_agent_phone: '',
        managing_agent_fee: null,
        lease_start_date: '',
        lease_end_date: '',
      },
      mortgageForm: {
        lender_name: '',
        mortgage_account_number: '',
        mortgage_type: '',
        repayment_percentage: null,
        interest_only_percentage: null,
        original_loan_amount: null,
        outstanding_balance: null,
        interest_rate: null,
        rate_type: '',
        fixed_rate_percentage: null,
        variable_rate_percentage: null,
        fixed_interest_rate: null,
        variable_interest_rate: null,
        rate_fix_end_date: '',
        monthly_payment: null,
        start_date: '',
        maturity_date: '',
        ownership_type: 'individual',
        joint_owner_id: null,
        joint_owner_name: '',
      },
      submitting: false,
      error: null,
    };
  },

  computed: {
    isEditMode() {
      return this.property !== null;
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },

    // Dynamic steps based on property type and mortgage selection
    activeSteps() {
      const steps = ['Basic Info', 'Ownership'];

      // Add Mortgage step if user checked the mortgage checkbox
      if (this.hasMortgage) {
        steps.push('Mortgage');
      }

      // Always add Costs step
      steps.push('Costs');

      // Add BTL Details step only if property type is buy_to_let
      if (this.form.property_type === 'buy_to_let') {
        steps.push('BTL Details');
      }

      return steps;
    },

    totalSteps() {
      return this.activeSteps.length;
    },

    // Map logical step to actual step index based on active steps
    stepMapping() {
      const mapping = {};
      let logicalStep = 1;

      // Step 1: Basic Info (always present)
      mapping[1] = logicalStep++;

      // Step 2: Ownership (always present)
      mapping[2] = logicalStep++;

      // Step 3: Mortgage (conditional)
      if (this.hasMortgage) {
        mapping[3] = logicalStep++;
      }

      // Step 4: Costs (always present)
      mapping[4] = logicalStep++;

      // Step 5: BTL Details (conditional)
      if (this.form.property_type === 'buy_to_let') {
        mapping[5] = logicalStep++;
      }

      return mapping;
    },

    // Co-owner percentage for Tenants in Common (100 - user's %)
    coOwnerPercentage() {
      if (this.form.ownership_type === 'tenants_in_common' && this.form.ownership_percentage) {
        return 100 - this.form.ownership_percentage;
      }
      return 0;
    },

    // Total monthly costs including mortgage
    totalMonthlyCosts() {
      let total = 0;

      // Add mortgage payment if exists
      if (this.hasMortgage && this.mortgageForm.monthly_payment) {
        total += Number(this.mortgageForm.monthly_payment) || 0;
      }

      // Add all monthly costs
      total += Number(this.form.monthly_council_tax) || 0;
      total += Number(this.form.monthly_gas) || 0;
      total += Number(this.form.monthly_electricity) || 0;
      total += Number(this.form.monthly_water) || 0;
      total += Number(this.form.monthly_building_insurance) || 0;
      total += Number(this.form.monthly_contents_insurance) || 0;
      total += Number(this.form.monthly_service_charge) || 0;
      total += Number(this.form.monthly_maintenance_reserve) || 0;
      total += Number(this.form.other_monthly_costs) || 0;

      return total;
    },

    // Mixed mortgage type validation - repayment + interest-only must = 100%
    mortgageTypePercentageTotal() {
      const repayment = Number(this.mortgageForm.repayment_percentage) || 0;
      const interestOnly = Number(this.mortgageForm.interest_only_percentage) || 0;
      return repayment + interestOnly;
    },

    // Mixed rate type validation - fixed + variable must = 100%
    rateTypePercentageTotal() {
      const fixed = Number(this.mortgageForm.fixed_rate_percentage) || 0;
      const variable = Number(this.mortgageForm.variable_rate_percentage) || 0;
      return fixed + variable;
    },

    // Check if editing a joint property (joint or tenants_in_common with linked joint owner)
    isJointPropertyEdit() {
      return this.isEditMode &&
             (this.form.ownership_type === 'joint' || this.form.ownership_type === 'tenants_in_common') &&
             this.form.joint_owner_id &&
             this.form.ownership_percentage < 100;
    },
  },

  watch: {
    // Watch for property prop changes to repopulate form
    property: {
      immediate: true,
      handler(newProperty) {
        if (newProperty) {
          this.populateForm();
        }
      },
    },

    'form.ownership_type'(newVal) {
      // Set default ownership percentages based on ownership type
      if (newVal === 'individual') {
        this.form.ownership_percentage = 100;
      } else if (newVal === 'joint') {
        this.form.ownership_percentage = 50;
      } else if (newVal === 'trust') {
        this.form.ownership_percentage = 0;
      } else if (newVal === 'tenants_in_common') {
        // Leave as user-entered, default to 50 if not set
        if (!this.form.ownership_percentage || this.form.ownership_percentage === 100) {
          this.form.ownership_percentage = 50;
        }
      }

      // Also sync mortgage ownership_type with property ownership_type
      this.mortgageForm.ownership_type = newVal;
    },

    mortgageJointOwnerSelection(newVal) {
      this.handleMortgageJointOwnerSelection();
    },

    // When property type changes, adjust current step if we're on BTL step and it's no longer BTL
    'form.property_type'(newVal, oldVal) {
      // If we were on the BTL step and property type is no longer BTL
      if (oldVal === 'buy_to_let' && newVal !== 'buy_to_let') {
        // If current step is the BTL step, move back to the previous step
        if (this.currentStep === this.stepMapping[5]) {
          this.currentStep = Math.max(1, this.currentStep - 1);
        }
      }
    },

    // When mortgage checkbox changes, adjust current step if needed
    hasMortgage(newVal, oldVal) {
      // If unchecking mortgage while on mortgage step, move to next logical step
      if (oldVal && !newVal && this.currentStep === this.stepMapping[3]) {
        this.currentStep = this.stepMapping[4] || this.currentStep + 1;
      }
    },

    // Sync mortgage joint owner with property joint owner
    'form.joint_owner_id'(newVal) {
      // Update mortgage joint_owner_id to match property joint_owner_id
      this.mortgageForm.joint_owner_id = newVal;
    },

    // Sync mortgage joint owner name with property joint owner name
    'form.joint_owner_name'(newVal) {
      // Update mortgage joint_owner_name to match property joint_owner_name
      this.mortgageForm.joint_owner_name = newVal;
    },
  },

  mounted() {
    if (this.property) {
      this.populateForm();
    }
  },

  methods: {
    populateForm() {
      // Direct top-level fields
      this.form.property_type = this.property.property_type || '';
      this.form.ownership_type = this.property.ownership_type || 'individual';
      this.form.joint_ownership_type = this.property.joint_ownership_type || null;
      this.form.tenure_type = this.property.tenure_type || 'freehold';
      this.form.lease_remaining_years = this.property.lease_remaining_years || null;
      this.form.lease_expiry_date = this.formatDateForInput(this.property.lease_expiry_date);
      this.form.country = this.property.country || 'United Kingdom';
      this.form.ownership_percentage = this.property.ownership_percentage || 100;
      this.form.joint_owner_id = this.property.joint_owner_id || null;
      this.form.joint_owner_name = this.property.joint_owner_name || '';
      this.form.household_id = this.property.household_id || null;
      this.form.trust_id = this.property.trust_id || null;
      this.form.trust_name = this.property.trust_name || '';

      // Single-record pattern: DB stores FULL values directly
      // No conversion needed - just use values directly
      this.form.current_value = this.property.current_value || null;
      this.form.purchase_price = this.property.purchase_price || null;

      // Set joint owner selection state
      if (this.form.joint_owner_id) {
        this.jointOwnerSelection = 'linked_' + this.form.joint_owner_id;
      } else if (this.form.joint_owner_name) {
        this.jointOwnerSelection = 'other';
      }

      // Set trust selection state
      if (this.form.trust_id) {
        this.trustSelection = 'linked_' + this.form.trust_id;
      } else if (this.form.trust_name) {
        this.trustSelection = 'other';
      }

      // Address fields (may be nested or top-level)
      this.form.address_line_1 = this.property.address_line_1 || this.property.address?.line_1 || '';
      this.form.address_line_2 = this.property.address_line_2 || this.property.address?.line_2 || '';
      this.form.city = this.property.city || this.property.address?.city || '';
      this.form.county = this.property.county || this.property.address?.county || '';
      this.form.postcode = this.property.postcode || this.property.address?.postcode || '';

      // Valuation fields (may be nested or top-level) - convert ISO dates to YYYY-MM-DD
      this.form.purchase_date = this.formatDateForInput(this.property.purchase_date || this.property.valuation?.purchase_date);
      this.form.valuation_date = this.formatDateForInput(this.property.valuation_date || this.property.valuation?.valuation_date);

      // Monthly Cost fields
      this.form.monthly_council_tax = this.property.monthly_council_tax || null;
      this.form.monthly_gas = this.property.monthly_gas || null;
      this.form.monthly_electricity = this.property.monthly_electricity || null;
      this.form.monthly_water = this.property.monthly_water || null;
      this.form.monthly_building_insurance = this.property.monthly_building_insurance || null;
      this.form.monthly_contents_insurance = this.property.monthly_contents_insurance || null;
      this.form.monthly_service_charge = this.property.monthly_service_charge || null;
      this.form.monthly_maintenance_reserve = this.property.monthly_maintenance_reserve || null;
      this.form.other_monthly_costs = this.property.other_monthly_costs || null;

      // Rental fields (may be nested or top-level)
      this.form.monthly_rental_income = this.property.monthly_rental_income || this.property.rental?.monthly_rental_income || null;
      this.form.tenant_name = this.property.tenant_name || this.property.rental?.tenant_name || '';
      this.form.tenant_email = this.property.tenant_email || this.property.rental?.tenant_email || '';
      this.form.lease_start_date = this.formatDateForInput(this.property.lease_start_date || this.property.rental?.lease_start_date);
      this.form.lease_end_date = this.formatDateForInput(this.property.lease_end_date || this.property.rental?.lease_end_date);

      // Managing Agent fields
      this.form.managing_agent_name = this.property.managing_agent_name || '';
      this.form.managing_agent_company = this.property.managing_agent_company || '';
      this.form.managing_agent_email = this.property.managing_agent_email || '';
      this.form.managing_agent_phone = this.property.managing_agent_phone || '';
      this.form.managing_agent_fee = this.property.managing_agent_fee || null;

      // Check if property has mortgage(s) and populate mortgage form
      if (this.property.mortgages && this.property.mortgages.length > 0) {
        this.hasMortgage = true;
        const mortgage = this.property.mortgages[0]; // Get first mortgage
        this.mortgageForm.lender_name = mortgage.lender_name || '';
        this.mortgageForm.mortgage_account_number = mortgage.mortgage_account_number || '';
        this.mortgageForm.mortgage_type = mortgage.mortgage_type || '';
        this.mortgageForm.interest_rate = mortgage.interest_rate || null;
        this.mortgageForm.rate_type = mortgage.rate_type || '';
        this.mortgageForm.rate_fix_end_date = this.formatDateForInput(mortgage.rate_fix_end_date);
        this.mortgageForm.monthly_payment = mortgage.monthly_payment || null;
        this.mortgageForm.start_date = this.formatDateForInput(mortgage.start_date);
        this.mortgageForm.maturity_date = this.formatDateForInput(mortgage.maturity_date);
        this.mortgageForm.ownership_type = mortgage.ownership_type || 'individual';
        this.mortgageForm.joint_owner_id = mortgage.joint_owner_id || null;
        this.mortgageForm.joint_owner_name = mortgage.joint_owner_name || '';

        // Single-record pattern: DB stores FULL mortgage balances
        // No conversion needed - just use values directly
        this.mortgageForm.outstanding_balance = mortgage.outstanding_balance || null;
        this.mortgageForm.original_loan_amount = mortgage.original_loan_amount || null;

        // Set mortgage joint owner selection state
        if (this.mortgageForm.joint_owner_id) {
          this.mortgageJointOwnerSelection = 'linked_' + this.mortgageForm.joint_owner_id;
        } else if (this.mortgageForm.joint_owner_name) {
          this.mortgageJointOwnerSelection = 'other';
        }
      } else {
        // No existing mortgage - sync ownership from property form
        this.mortgageForm.ownership_type = this.form.ownership_type || 'individual';
        this.mortgageForm.joint_owner_id = this.form.joint_owner_id || null;
        this.mortgageForm.joint_owner_name = this.form.joint_owner_name || '';
      }
    },

    formatDateForInput(date) {
      if (!date) return '';
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
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
      // Validate current step before proceeding
      this.error = null;

      // Step 3: Mortgage validation (if mortgage is selected)
      if (this.currentStep === this.stepMapping[3] && this.hasMortgage) {
        if (!this.mortgageForm.outstanding_balance || this.mortgageForm.outstanding_balance <= 0) {
          this.error = 'Please enter the outstanding balance for the mortgage.';
          return;
        }
        if (!this.mortgageForm.monthly_payment || this.mortgageForm.monthly_payment <= 0) {
          this.error = 'Please enter the monthly mortgage payment.';
          return;
        }
      }

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
      // Allow direct navigation to any step
      // Clear any errors when navigating
      this.error = null;

      // Navigate to the requested step
      if (stepNumber >= 1 && stepNumber <= this.totalSteps) {
        this.currentStep = stepNumber;
      }
    },

    handleJointOwnerSelection() {
      if (this.jointOwnerSelection.startsWith('linked_')) {
        // Extract ID and set joint_owner_id
        this.form.joint_owner_id = parseInt(this.jointOwnerSelection.replace('linked_', ''));
        this.form.joint_owner_name = ''; // Clear free text field
      } else if (this.jointOwnerSelection === 'other') {
        // Clear linked ID when using free text
        this.form.joint_owner_id = null;
      }
    },

    handleMortgageJointOwnerSelection() {
      if (this.mortgageJointOwnerSelection.startsWith('linked_')) {
        // Extract ID and set mortgage joint_owner_id
        this.mortgageForm.joint_owner_id = parseInt(this.mortgageJointOwnerSelection.replace('linked_', ''));
        this.mortgageForm.joint_owner_name = ''; // Clear free text field
      } else if (this.mortgageJointOwnerSelection === 'other') {
        // Clear linked ID when using free text
        this.mortgageForm.joint_owner_id = null;
      }
    },

    handleTrustSelection() {
      if (this.trustSelection.startsWith('linked_')) {
        // Extract ID and set trust_id (when trusts are loaded)
        this.form.trust_id = parseInt(this.trustSelection.replace('linked_', ''));
        this.form.trust_name = ''; // Clear free text field
      } else if (this.trustSelection === 'other') {
        // Clear linked ID when using free text
        this.form.trust_id = null;
      }
    },

    validateForm() {
      // Basic validation
      if (!this.form.property_type || !this.form.address_line_1 || !this.form.city || !this.form.postcode) {
        this.error = 'Please fill in all required fields in Basic Information (Step 1).';
        this.currentStep = 1; // Go to step with error
        return false;
      }

      // Current value validation - must be a positive number
      if (!this.form.current_value || this.form.current_value <= 0) {
        this.error = 'Please fill in Current Value (Step 1).';
        this.currentStep = 1; // Go to step with error
        return false;
      }

      if (!this.form.ownership_type || this.form.ownership_percentage === null || this.form.ownership_percentage === undefined) {
        this.error = 'Please fill in ownership details (Step 2).';
        this.currentStep = 2; // Go to step with error
        return false;
      }

      this.error = null;
      return true;
    },

    async handleSubmit() {
      console.log('[PropertyForm] handleSubmit called');
      if (!this.validateForm()) {
        console.log('[PropertyForm] Validation failed:', this.error);
        // Scroll to top to show error message
        this.$el.querySelector('.px-6.py-4').scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      console.log('[PropertyForm] Validation passed, form data:', this.form);

      this.submitting = true;
      this.error = null;

      // Clean mortgage data - convert empty strings to null for date and nullable fields
      let cleanedMortgage = null;
      if (this.hasMortgage) {
        cleanedMortgage = { ...this.mortgageForm };
        // Convert empty strings to null for date fields
        if (cleanedMortgage.rate_fix_end_date === '') cleanedMortgage.rate_fix_end_date = null;
        if (cleanedMortgage.start_date === '') cleanedMortgage.start_date = null;
        if (cleanedMortgage.maturity_date === '') cleanedMortgage.maturity_date = null;
        // Convert empty strings to null for enum/select fields
        if (cleanedMortgage.mortgage_type === '') cleanedMortgage.mortgage_type = null;
        if (cleanedMortgage.rate_type === '') cleanedMortgage.rate_type = null;
        // Convert empty strings to null for text fields
        if (cleanedMortgage.mortgage_account_number === '') cleanedMortgage.mortgage_account_number = null;
        if (cleanedMortgage.joint_owner_name === '') cleanedMortgage.joint_owner_name = null;
        if (cleanedMortgage.country === '') cleanedMortgage.country = null;
        if (cleanedMortgage.notes === '') cleanedMortgage.notes = null;
        // Convert empty strings to null for numeric fields
        if (cleanedMortgage.original_loan_amount === '') cleanedMortgage.original_loan_amount = null;
        if (cleanedMortgage.interest_rate === '') cleanedMortgage.interest_rate = null;
        if (cleanedMortgage.repayment_percentage === '') cleanedMortgage.repayment_percentage = null;
        if (cleanedMortgage.interest_only_percentage === '') cleanedMortgage.interest_only_percentage = null;
        if (cleanedMortgage.remaining_term_months === '') cleanedMortgage.remaining_term_months = null;
        if (cleanedMortgage.joint_owner_id === '') cleanedMortgage.joint_owner_id = null;
        if (cleanedMortgage.fixed_rate_percentage === '') cleanedMortgage.fixed_rate_percentage = null;
        if (cleanedMortgage.variable_rate_percentage === '') cleanedMortgage.variable_rate_percentage = null;
        if (cleanedMortgage.fixed_interest_rate === '') cleanedMortgage.fixed_interest_rate = null;
        if (cleanedMortgage.variable_interest_rate === '') cleanedMortgage.variable_interest_rate = null;
      }

      // Emit 'save' event (NOT 'submit' - see CLAUDE.md)
      console.log('[PropertyForm] Emitting save event with:', { property: this.form, mortgage: cleanedMortgage });
      this.$emit('save', {
        property: this.form,
        mortgage: cleanedMortgage,
      });
    },

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>
