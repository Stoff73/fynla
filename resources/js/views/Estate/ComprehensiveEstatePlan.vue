<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header with Download Button -->
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Your Comprehensive Estate Plan</h1>
          <p class="text-gray-600 mt-1">A complete estate planning strategy combining gifting, trusts, and life insurance</p>
        </div>
        <button
          @click="downloadPDF"
          class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 flex items-center gap-2 shadow-md"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Download PDF
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="text-center">
          <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-primary-600 mb-4"></div>
          <p class="text-gray-600 text-lg">Generating your comprehensive estate plan...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-start">
          <svg class="h-6 w-6 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">Failed to Generate Estate Plan</h3>
            <p class="mt-2 text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Estate Plan Document -->
      <div v-else-if="plan" id="estate-plan-document" class="bg-white shadow-lg rounded-lg">
        <!-- Document Header -->
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white p-8 rounded-t-lg">
          <h2 class="text-3xl font-bold mb-2">{{ plan.executive_summary.title }}</h2>
          <p class="text-primary-100">Generated on {{ plan.plan_metadata.generated_date }} at {{ plan.plan_metadata.generated_time }}</p>
          <p class="text-primary-100">Plan Version: {{ plan.plan_metadata.plan_version }}</p>

          <!-- Plan Type Badge -->
          <div class="mt-4">
            <span
              v-if="plan.plan_metadata.plan_type"
              :class="getPlanTypeBadgeClass(plan.plan_metadata.is_complete)"
              class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
            >
              <svg v-if="plan.plan_metadata.is_complete" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <svg v-else class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              {{ plan.plan_metadata.plan_type }} Plan
            </span>
            <span v-if="!plan.plan_metadata.is_complete" class="ml-2 text-primary-100 text-sm">
              ({{ plan.plan_metadata.completeness_score }}% profile complete)
            </span>
          </div>
        </div>

        <!-- Profile Completeness Warning Banner -->
        <div v-if="plan.completeness_warning" class="border-b border-gray-200">
          <div
            :class="getCompletenessWarningClass(plan.completeness_warning.severity)"
            class="p-6"
          >
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6" :class="getWarningIconColour(plan.completeness_warning.severity)" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-4 flex-1">
                <h4 class="text-base font-semibold mb-2" :class="getWarningTitleColour(plan.completeness_warning.severity)">
                  Plan Completeness: {{ plan.completeness_warning.score }}%
                </h4>
                <p class="text-sm mb-4" :class="getWarningTextColour(plan.completeness_warning.severity)">
                  {{ plan.completeness_warning.disclaimer }}
                </p>

                <!-- Missing Fields -->
                <div v-if="plan.completeness_warning.missing_fields && plan.completeness_warning.missing_fields.length > 0" class="mt-4">
                  <p class="text-sm font-medium mb-2" :class="getWarningTextColour(plan.completeness_warning.severity)">
                    To improve this plan, please complete:
                  </p>
                  <ul class="list-disc list-inside space-y-1">
                    <li
                      v-for="field in plan.completeness_warning.missing_fields"
                      :key="field.field"
                      class="text-sm"
                      :class="getWarningTextColour(plan.completeness_warning.severity)"
                    >
                      {{ field.message }}
                    </li>
                  </ul>
                  <router-link
                    to="/profile"
                    class="inline-block mt-4 px-4 py-2 text-sm font-medium rounded-md transition-colors"
                    :class="getCompleteProfileButtonClass(plan.completeness_warning.severity)"
                  >
                    Complete Your Profile →
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="p-8">
          <!-- Executive Summary -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">Executive Summary</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
              <!-- IHT Liabilities Card -->
              <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                <p class="text-sm text-red-600 font-medium mb-3">IHT Liability</p>

                <!-- Show both NOW and PROJECTED if available -->
                <div v-if="plan.executive_summary.iht_liabilities.projected" class="space-y-3">
                  <div>
                    <p class="text-xs text-red-500 mb-1">If die now:</p>
                    <p class="text-2xl font-bold text-red-700">{{ formatCurrency(plan.executive_summary.iht_liabilities.current) }}</p>
                  </div>
                  <div class="border-t border-red-200 pt-2">
                    <p class="text-xs text-red-500 mb-1">At age {{ plan.executive_summary.iht_liabilities.projected_age }}:</p>
                    <p class="text-2xl font-bold text-red-700">{{ formatCurrency(plan.executive_summary.iht_liabilities.projected) }}</p>
                  </div>
                </div>

                <!-- Show only current if projected not available -->
                <div v-else>
                  <p class="text-3xl font-bold text-red-700">{{ formatCurrency(plan.executive_summary.iht_liabilities.current) }}</p>
                  <p class="text-xs text-red-600 mt-2">Estate: {{ formatCurrency(plan.executive_summary.current_position.net_estate) }}</p>
                </div>
              </div>

              <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                <p class="text-sm text-green-600 font-medium mb-1">Potential IHT Saving</p>
                <p class="text-3xl font-bold text-green-700">{{ formatCurrency(plan.executive_summary.potential_saving) }}</p>
                <p class="text-xs text-green-600 mt-2">Annual Cost: {{ formatCurrency(plan.executive_summary.annual_cost) }}</p>
              </div>

              <!-- Key Actions Card - Now showing list -->
              <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                <p class="text-sm text-blue-600 font-medium mb-3">Key Actions Required</p>
                <ul class="space-y-1.5 text-sm text-blue-900">
                  <li
                    v-for="(action, index) in plan.executive_summary.key_actions.slice(0, 3)"
                    :key="index"
                    class="flex items-start gap-2"
                  >
                    <svg class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-xs leading-tight">{{ action }}</span>
                  </li>
                </ul>
                <p v-if="plan.executive_summary.key_actions.length > 3" class="text-xs text-blue-600 mt-3 font-medium">
                  +{{ plan.executive_summary.key_actions.length - 3 }} more actions
                </p>
              </div>
            </div>
          </section>

          <!-- User Profile -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">Your Profile</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-3">
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Name:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.name }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Email:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.email }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Date of Birth:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.date_of_birth }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Age:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.age }}</span>
                </div>
              </div>

              <div class="space-y-3">
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Marital Status:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.marital_status }}</span>
                </div>
                <div v-if="plan.user_profile.spouse" class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Spouse:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.spouse.name }}</span>
                </div>
                <div v-if="plan.user_profile.children && plan.user_profile.children.length > 0" class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">{{ plan.user_profile.children.length === 1 ? 'Child' : 'Children' }}:</span>
                  <span class="font-semibold text-gray-900">
                    {{ plan.user_profile.children.map(c => c.name).join(', ') }}
                  </span>
                </div>
                <div v-if="plan.user_profile.step_children && plan.user_profile.step_children.length > 0" class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">{{ plan.user_profile.step_children.length === 1 ? 'Step-Child' : 'Step-Children' }}:</span>
                  <span class="font-semibold text-gray-900">
                    {{ plan.user_profile.step_children.map(c => c.name).join(', ') }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <!-- Estate Overview -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">Estate Overview for IHT</h3>

            <!-- User Estate Section -->
            <div v-if="plan.estate_breakdown && plan.estate_breakdown.user" class="mb-8">
              <h4 class="text-lg font-semibold text-gray-800 mb-3">{{ plan.estate_breakdown.user.name }}'s Estate</h4>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                  <p class="text-sm text-blue-600 mb-1">Total Assets</p>
                  <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(plan.estate_breakdown.user.total_assets) }}</p>
                  <p class="text-xs text-blue-600 mt-1">{{ plan.estate_breakdown.user.asset_count }} assets</p>
                </div>
                <div class="bg-amber-50 rounded-lg p-4">
                  <p class="text-sm text-amber-600 mb-1">Total Liabilities</p>
                  <p class="text-2xl font-bold text-amber-900">{{ formatCurrency(plan.estate_breakdown.user.total_liabilities) }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                  <p class="text-sm text-green-600 mb-1">Net Estate</p>
                  <p class="text-2xl font-bold text-green-900">{{ formatCurrency(plan.estate_breakdown.user.net_estate) }}</p>
                </div>
              </div>

              <!-- User Detailed Asset Breakdown -->
              <div v-if="plan.estate_breakdown.user.detailed_assets && Object.keys(plan.estate_breakdown.user.detailed_assets).length > 0">
                <div v-for="(assets, type) in plan.estate_breakdown.user.detailed_assets" :key="type" class="mb-4">
                  <div class="bg-gray-100 p-3 rounded-t font-semibold text-gray-900">
                    {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                  </div>
                  <div class="border border-gray-200 rounded-b overflow-hidden">
                    <div
                      v-for="(asset, index) in assets"
                      :key="index"
                      class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-gray-50"
                    >
                      <div class="flex items-center gap-3">
                        <span class="text-gray-900">{{ asset.name }}</span>
                        <span v-if="asset.is_iht_exempt" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                          IHT Exempt
                        </span>
                      </div>
                      <span class="font-semibold text-gray-900">{{ formatCurrency(asset.value) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- User Detailed Liabilities Breakdown -->
              <div v-if="plan.estate_breakdown.user.detailed_liabilities && plan.estate_breakdown.user.detailed_liabilities.length > 0" class="mt-6">
                <div class="bg-amber-100 p-3 rounded-t font-semibold text-amber-900">
                  Liabilities
                </div>
                <div class="border border-amber-200 rounded-b overflow-hidden">
                  <div
                    v-for="(liability, index) in plan.estate_breakdown.user.detailed_liabilities"
                    :key="index"
                    class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-amber-50"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-gray-900">{{ liability.name }}</span>
                      <span class="px-2 py-0.5 bg-amber-200 text-amber-800 text-xs rounded-full">
                        {{ liability.type }}
                      </span>
                    </div>
                    <span class="font-semibold text-amber-900">{{ formatCurrency(liability.balance) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Spouse Estate Section -->
            <div v-if="plan.estate_breakdown && plan.estate_breakdown.spouse" class="mb-8">
              <h4 class="text-lg font-semibold text-gray-800 mb-3">{{ plan.estate_breakdown.spouse.name }}'s Estate</h4>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                  <p class="text-sm text-blue-600 mb-1">Total Assets</p>
                  <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(plan.estate_breakdown.spouse.total_assets) }}</p>
                  <p class="text-xs text-blue-600 mt-1">{{ plan.estate_breakdown.spouse.asset_count }} assets</p>
                </div>
                <div class="bg-amber-50 rounded-lg p-4">
                  <p class="text-sm text-amber-600 mb-1">Total Liabilities</p>
                  <p class="text-2xl font-bold text-amber-900">{{ formatCurrency(plan.estate_breakdown.spouse.total_liabilities) }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                  <p class="text-sm text-green-600 mb-1">Net Estate</p>
                  <p class="text-2xl font-bold text-green-900">{{ formatCurrency(plan.estate_breakdown.spouse.net_estate) }}</p>
                </div>
              </div>

              <!-- Spouse Detailed Asset Breakdown -->
              <div v-if="plan.estate_breakdown.spouse.detailed_assets && Object.keys(plan.estate_breakdown.spouse.detailed_assets).length > 0">
                <div v-for="(assets, type) in plan.estate_breakdown.spouse.detailed_assets" :key="type" class="mb-4">
                  <div class="bg-gray-100 p-3 rounded-t font-semibold text-gray-900">
                    {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                  </div>
                  <div class="border border-gray-200 rounded-b overflow-hidden">
                    <div
                      v-for="(asset, index) in assets"
                      :key="index"
                      class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-gray-50"
                    >
                      <div class="flex items-center gap-3">
                        <span class="text-gray-900">{{ asset.name }}</span>
                        <span v-if="asset.is_iht_exempt" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                          IHT Exempt
                        </span>
                      </div>
                      <span class="font-semibold text-gray-900">{{ formatCurrency(asset.value) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Spouse Detailed Liabilities Breakdown -->
              <div v-if="plan.estate_breakdown.spouse.detailed_liabilities && plan.estate_breakdown.spouse.detailed_liabilities.length > 0" class="mt-6">
                <div class="bg-amber-100 p-3 rounded-t font-semibold text-amber-900">
                  Liabilities
                </div>
                <div class="border border-amber-200 rounded-b overflow-hidden">
                  <div
                    v-for="(liability, index) in plan.estate_breakdown.spouse.detailed_liabilities"
                    :key="index"
                    class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-amber-50"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-gray-900">{{ liability.name }}</span>
                      <span class="px-2 py-0.5 bg-amber-200 text-amber-800 text-xs rounded-full">
                        {{ liability.type }}
                      </span>
                    </div>
                    <span class="font-semibold text-amber-900">{{ formatCurrency(liability.balance) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Combined Estate Section -->
            <div v-if="plan.estate_breakdown && plan.estate_breakdown.combined" class="mb-8">
              <h4 class="text-lg font-semibold text-gray-800 mb-3">Combined Estate</h4>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                  <p class="text-sm text-blue-600 mb-1">Total Assets</p>
                  <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(plan.estate_breakdown.combined.total_assets) }}</p>
                  <p class="text-xs text-blue-600 mt-1">{{ plan.estate_breakdown.combined.asset_count }} assets</p>
                </div>
                <div class="bg-amber-50 rounded-lg p-4">
                  <p class="text-sm text-amber-600 mb-1">Total Liabilities</p>
                  <p class="text-2xl font-bold text-amber-900">{{ formatCurrency(plan.estate_breakdown.combined.total_liabilities) }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                  <p class="text-sm text-green-600 mb-1">Net Estate</p>
                  <p class="text-2xl font-bold text-green-900">{{ formatCurrency(plan.estate_breakdown.combined.net_estate) }}</p>
                </div>
              </div>

              <!-- Combined Detailed Asset Breakdown -->
              <div v-if="plan.estate_breakdown.combined.detailed_assets && Object.keys(plan.estate_breakdown.combined.detailed_assets).length > 0">
                <div v-for="(assets, type) in plan.estate_breakdown.combined.detailed_assets" :key="type" class="mb-4">
                  <div class="bg-gray-100 p-3 rounded-t font-semibold text-gray-900">
                    {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                  </div>
                  <div class="border border-gray-200 rounded-b overflow-hidden">
                    <div
                      v-for="(asset, index) in assets"
                      :key="index"
                      class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-gray-50"
                    >
                      <div class="flex items-center gap-3">
                        <span class="text-gray-900">{{ asset.name }}</span>
                        <span v-if="asset.is_iht_exempt" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                          IHT Exempt
                        </span>
                      </div>
                      <span class="font-semibold text-gray-900">{{ formatCurrency(asset.value) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Combined Detailed Liabilities Breakdown -->
              <div v-if="plan.estate_breakdown.combined.detailed_liabilities && plan.estate_breakdown.combined.detailed_liabilities.length > 0" class="mt-6">
                <div class="bg-amber-100 p-3 rounded-t font-semibold text-amber-900">
                  Liabilities
                </div>
                <div class="border border-amber-200 rounded-b overflow-hidden">
                  <div
                    v-for="(liability, index) in plan.estate_breakdown.combined.detailed_liabilities"
                    :key="index"
                    class="flex justify-between items-center p-3 border-b last:border-b-0 hover:bg-amber-50"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-gray-900">{{ liability.name }}</span>
                      <span class="px-2 py-0.5 bg-amber-200 text-amber-800 text-xs rounded-full">
                        {{ liability.type }}
                      </span>
                    </div>
                    <span class="font-semibold text-amber-900">{{ formatCurrency(liability.balance) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Current IHT Position -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">IHT Position</h3>

            <!-- Married Couple - Show NOW and PROJECTED -->
            <div v-if="plan.current_iht_position.has_projection" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- NOW Scenario -->
              <div>
                <h4 class="text-lg font-semibold text-gray-800 mb-3">If Both Die Now</h4>
                <div class="bg-gray-50 rounded-lg p-6">
                  <div class="space-y-3">
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700">Gross Estate Value</span>
                      <span class="font-bold text-gray-900">{{ formatCurrency(plan.current_iht_position.now.gross_estate) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700">Less: Liabilities</span>
                      <span class="font-bold text-gray-900">-{{ formatCurrency(plan.current_iht_position.now.liabilities) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700">Net Estate</span>
                      <span class="font-bold text-gray-900">{{ formatCurrency(plan.current_iht_position.now.net_estate) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">NRB (User)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.now.user_nrb) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">NRB (Spouse)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.now.spouse_nrb) }}</span>
                    </div>
                    <div v-if="plan.current_iht_position.now.user_rnrb > 0" class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">RNRB (User)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.now.user_rnrb) }}</span>
                    </div>
                    <div v-if="plan.current_iht_position.now.spouse_rnrb > 0" class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">RNRB (Spouse)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.now.spouse_rnrb) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b bg-amber-50 -mx-6 px-6 py-3">
                      <span class="font-semibold text-gray-900">Taxable Estate</span>
                      <span class="font-bold text-amber-700">{{ formatCurrency(plan.current_iht_position.now.taxable_estate) }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-red-50 -mx-6 px-6 py-3 rounded">
                      <span class="font-semibold text-red-900">IHT Liability (40%)</span>
                      <span class="font-bold text-red-700 text-xl">{{ formatCurrency(plan.current_iht_position.now.iht_liability) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PROJECTED Scenario -->
              <div>
                <h4 class="text-lg font-semibold text-gray-800 mb-3">At Expected Death (Age {{ plan.current_iht_position.projected.age_at_death }})</h4>
                <div class="bg-gray-50 rounded-lg p-6">
                  <div class="space-y-3">
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700">Gross Estate Value</span>
                      <span class="font-bold text-gray-900">{{ formatCurrency(plan.current_iht_position.projected.gross_estate) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700">Less: Liabilities</span>
                      <span class="font-bold text-gray-900">-{{ formatCurrency(plan.current_iht_position.projected.liabilities) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700">Net Estate</span>
                      <span class="font-bold text-gray-900">{{ formatCurrency(plan.current_iht_position.projected.net_estate) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">NRB (User)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.projected.user_nrb) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">NRB (Spouse)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.projected.spouse_nrb) }}</span>
                    </div>
                    <div v-if="plan.current_iht_position.projected.user_rnrb > 0" class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">RNRB (User)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.projected.user_rnrb) }}</span>
                    </div>
                    <div v-if="plan.current_iht_position.projected.spouse_rnrb > 0" class="flex justify-between items-center pb-3 border-b">
                      <span class="text-gray-700 text-sm">RNRB (Spouse)</span>
                      <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.projected.spouse_rnrb) }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b bg-amber-50 -mx-6 px-6 py-3">
                      <span class="font-semibold text-gray-900">Taxable Estate</span>
                      <span class="font-bold text-amber-700">{{ formatCurrency(plan.current_iht_position.projected.taxable_estate) }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-red-50 -mx-6 px-6 py-3 rounded">
                      <span class="font-semibold text-red-900">IHT Liability (40%)</span>
                      <span class="font-bold text-red-700 text-xl">{{ formatCurrency(plan.current_iht_position.projected.iht_liability) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Single Person - Show current only -->
            <div v-else class="bg-gray-50 rounded-lg p-6">
              <div class="space-y-3">
                <div class="flex justify-between items-center pb-3 border-b">
                  <span class="text-gray-700">Gross Estate Value</span>
                  <span class="font-bold text-gray-900">{{ formatCurrency(plan.current_iht_position.gross_estate) }}</span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b">
                  <span class="text-gray-700">Available NRB</span>
                  <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.available_nrb) }}</span>
                </div>
                <div v-if="plan.current_iht_position.rnrb > 0" class="flex justify-between items-center pb-3 border-b">
                  <span class="text-gray-700">RNRB</span>
                  <span class="font-bold text-green-600">-{{ formatCurrency(plan.current_iht_position.rnrb) }}</span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b bg-amber-50 -mx-6 px-6 py-3">
                  <span class="font-semibold text-gray-900">Taxable Estate</span>
                  <span class="font-bold text-amber-700">{{ formatCurrency(plan.current_iht_position.taxable_estate) }}</span>
                </div>
                <div class="flex justify-between items-center bg-red-50 -mx-6 px-6 py-3 rounded">
                  <span class="font-semibold text-red-900">IHT Liability (40%)</span>
                  <span class="font-bold text-red-700 text-xl">{{ formatCurrency(plan.current_iht_position.iht_liability) }}</span>
                </div>
                <div class="flex justify-between items-center pt-2">
                  <span class="text-sm text-gray-600">Effective IHT Rate</span>
                  <span class="text-sm font-medium text-gray-700">{{ plan.current_iht_position.effective_rate.toFixed(1) }}%</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Optimised Combined Strategy -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">Optimised Combined Strategy</h3>

            <div class="bg-gradient-to-r from-emerald-50 to-green-50 rounded-lg p-6 mb-6 border border-emerald-200">
              <h4 class="text-lg font-bold text-gray-900 mb-4">{{ plan.optimized_recommendation.strategy_name }}</h4>

              <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Total IHT Saving</p>
                  <p class="text-xl font-bold text-green-600">{{ formatCurrency(plan.optimized_recommendation.summary.total_iht_saving) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Remaining Liability</p>
                  <p class="text-xl font-bold text-amber-600">{{ formatCurrency(plan.optimized_recommendation.summary.remaining_liability) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Annual Costs</p>
                  <p class="text-xl font-bold text-blue-600">{{ formatCurrency(plan.optimized_recommendation.summary.annual_costs) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Effectiveness</p>
                  <p class="text-xl font-bold text-emerald-600">{{ plan.optimized_recommendation.summary.effectiveness_percentage.toFixed(0) }}%</p>
                </div>
              </div>
            </div>

            <!-- Priority Recommendations -->
            <div class="space-y-6">
              <div
                v-for="(rec, index) in plan.optimized_recommendation.recommendations"
                :key="index"
                class="border rounded-lg overflow-hidden"
                :class="getPriorityBorderClass(rec.priority)"
              >
                <div class="px-6 py-4" :class="getPriorityHeaderClass(rec.priority)">
                  <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-white" :class="getPriorityTextClass(rec.priority)">
                      Priority {{ rec.priority }}
                    </span>
                    <h5 class="text-lg font-bold" :class="getPriorityTitleClass(rec.priority)">{{ rec.category }}</h5>
                  </div>
                </div>

                <div class="px-6 py-4 bg-white">
                  <div class="space-y-4">
                    <div
                      v-for="(action, actionIndex) in rec.actions"
                      :key="actionIndex"
                      class="border-l-4 pl-4 py-2"
                      :class="getPriorityAccentClass(rec.priority)"
                    >
                      <h6 class="font-semibold text-gray-900 mb-2">{{ action.action }}</h6>
                      <p class="text-sm text-gray-700 mb-3">{{ action.details }}</p>
                      <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                          <span class="text-gray-600">IHT Saving:</span>
                          <span class="font-semibold text-green-600 ml-2">
                            {{ typeof action.iht_saving === 'number' ? formatCurrency(action.iht_saving) : action.iht_saving }}
                          </span>
                        </div>
                        <div>
                          <span class="text-gray-600">Cost:</span>
                          <span class="font-semibold text-amber-600 ml-2">{{ formatCurrency(action.cost) }}</span>
                        </div>
                        <div>
                          <span class="text-gray-600">Timeframe:</span>
                          <span class="font-semibold text-blue-600 ml-2">{{ action.timeframe }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Implementation Timeline -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">Implementation Timeline</h3>

            <div class="space-y-3">
              <div
                v-for="(item, index) in plan.implementation_timeline"
                :key="index"
                class="flex items-start gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <div class="flex-shrink-0">
                  <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold" :class="getTimelineBadgeClass(item.priority)">
                    {{ item.priority }}
                  </div>
                </div>
                <div class="flex-1">
                  <p class="font-semibold text-gray-900">{{ item.action }}</p>
                  <p class="text-sm text-gray-600 mt-1">{{ item.category }}</p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-gray-700">{{ item.timeframe }}</p>
                  <p v-if="item.iht_saving > 0" class="text-sm text-green-600">Save: {{ formatCurrency(item.iht_saving) }}</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Next Steps -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-primary-600">Next Steps</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div
                v-for="(steps, timeframe) in plan.next_steps"
                :key="timeframe"
                class="bg-gray-50 rounded-lg p-6"
              >
                <h4 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                  <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                  {{ timeframe }}
                </h4>
                <ul v-if="steps.length > 0" class="space-y-2">
                  <li v-for="(step, index) in steps" :key="index" class="text-sm text-gray-700 flex items-start gap-2">
                    <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    {{ step }}
                  </li>
                </ul>
                <p v-else class="text-sm text-gray-500 italic">No actions required in this timeframe</p>
              </div>
            </div>
          </section>

          <!-- Professional Disclaimer -->
          <section class="mb-8">
            <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded">
              <div class="flex items-start">
                <svg class="h-6 w-6 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h4 class="text-sm font-medium text-blue-800">Important: Professional Advice Required</h4>
                  <p class="mt-2 text-sm text-blue-700">
                    This estate plan is for educational and planning purposes only. It is not regulated financial advice.
                    Before implementing any of the strategies outlined in this plan, you must consult with qualified professionals including:
                  </p>
                  <ul class="mt-2 text-sm text-blue-700 list-disc list-inside space-y-1">
                    <li>A qualified independent financial adviser (IFA) for investment and insurance advice</li>
                    <li>A solicitor for trust drafting, will preparation, and legal matters</li>
                    <li>A chartered tax adviser or accountant for tax planning</li>
                  </ul>
                  <p class="mt-2 text-sm text-blue-700">
                    Estate planning involves complex legal, tax, and financial considerations. Professional guidance is essential
                    to ensure strategies are suitable for your circumstances and correctly implemented.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <!-- Footer -->
          <div class="text-center text-sm text-gray-500 pt-8 border-t">
            <p>Generated by Fynla (Financial Planning System) - Version {{ plan.plan_metadata.plan_version }}</p>
            <p class="mt-1">{{ plan.plan_metadata.generated_date }} at {{ plan.plan_metadata.generated_time }}</p>
            <p class="mt-2">🤖 Generated with <a href="https://claude.com/claude-code" class="text-primary-600 hover:underline" target="_blank">Claude Code</a></p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import estateService from '@/services/estateService';

export default {
  name: 'ComprehensiveEstatePlan',

  components: {
    AppLayout,
  },

  data() {
    return {
      plan: null,
      loading: false,
      error: null,
    };
  },

  mounted() {
    this.loadEstatePlan();
  },

  methods: {
    async loadEstatePlan() {
      this.loading = true;
      this.error = null;

      try {
        const response = await estateService.getComprehensiveEstatePlan();
        if (response.success) {
          this.plan = response.data;
        } else {
          this.error = response.message || 'Failed to load estate plan';
        }
      } catch (error) {
        console.error('Failed to load estate plan:', error);
        this.error = error.response?.data?.message || 'An error occurred while generating your estate plan';
      } finally {
        this.loading = false;
      }
    },

    downloadPDF() {
      // Use browser print functionality with custom CSS for PDF
      window.print();
    },

    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value || 0);
    },

    getPriorityBorderClass(priority) {
      if (priority === 1) return 'border-emerald-300';
      if (priority === 2) return 'border-blue-300';
      if (priority === 3) return 'border-purple-300';
      return 'border-gray-300';
    },

    getPriorityHeaderClass(priority) {
      if (priority === 1) return 'bg-emerald-50';
      if (priority === 2) return 'bg-blue-50';
      if (priority === 3) return 'bg-purple-50';
      return 'bg-gray-50';
    },

    getPriorityTextClass(priority) {
      if (priority === 1) return 'text-emerald-700';
      if (priority === 2) return 'text-blue-700';
      if (priority === 3) return 'text-purple-700';
      return 'text-gray-700';
    },

    getPlanTypeBadgeClass(isComplete) {
      return isComplete
        ? 'bg-blue-100 text-blue-800'
        : 'bg-amber-100 text-amber-800';
    },

    getCompletenessWarningClass(severity) {
      const classes = {
        critical: 'bg-red-50 border-l-4 border-red-500',
        warning: 'bg-amber-50 border-l-4 border-amber-500',
        success: 'bg-green-50 border-l-4 border-green-500',
      };
      return classes[severity] || classes.warning;
    },

    getWarningIconColour(severity) {
      const colours = {
        critical: 'text-red-600',
        warning: 'text-amber-600',
        success: 'text-green-600',
      };
      return colours[severity] || colours.warning;
    },

    getWarningTitleColour(severity) {
      const colours = {
        critical: 'text-red-900',
        warning: 'text-amber-900',
        success: 'text-green-900',
      };
      return colours[severity] || colours.warning;
    },

    getWarningTextColour(severity) {
      const colours = {
        critical: 'text-red-700',
        warning: 'text-amber-700',
        success: 'text-green-700',
      };
      return colours[severity] || colours.warning;
    },

    getCompleteProfileButtonClass(severity) {
      const classes = {
        critical: 'bg-red-600 hover:bg-red-700 text-white',
        warning: 'bg-amber-600 hover:bg-amber-700 text-white',
        success: 'bg-green-600 hover:bg-green-700 text-white',
      };
      return classes[severity] || classes.warning;
    },

    getPriorityTitleClass(priority) {
      if (priority === 1) return 'text-emerald-900';
      if (priority === 2) return 'text-blue-900';
      if (priority === 3) return 'text-purple-900';
      return 'text-gray-900';
    },

    getPriorityAccentClass(priority) {
      if (priority === 1) return 'border-emerald-400';
      if (priority === 2) return 'border-blue-400';
      if (priority === 3) return 'border-purple-400';
      return 'border-gray-400';
    },

    getTimelineBadgeClass(priority) {
      if (priority === 1) return 'bg-emerald-600';
      if (priority === 2) return 'bg-blue-600';
      if (priority === 3) return 'bg-purple-600';
      return 'bg-gray-600';
    },
  },
};
</script>

<style scoped>
/* Print-specific styles for PDF download */
@media print {
  .max-w-7xl {
    max-width: 100%;
  }

  button {
    display: none !important;
  }

  #estate-plan-document {
    box-shadow: none;
  }

  @page {
    margin: 1cm;
  }
}
</style>
