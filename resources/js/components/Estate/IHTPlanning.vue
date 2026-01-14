<template>
  <div class="iht-planning-tab">
    <!-- Error State - No Profile -->
    <div v-if="error && !ihtData" class="bg-white border-2 border-amber-500 rounded-lg p-6 mb-6">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-amber-800">Inheritance Tax Profile Required</h3>
          <p class="mt-2 text-sm text-amber-700">{{ error }}</p>
          <p class="mt-2 text-sm text-amber-700">Please set up your inheritance tax profile in the Estate module to see your tax calculation.</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-gray-600">Calculating inheritance tax liability...</p>
    </div>

    <!-- Spouse Exemption Notice (Always show for married users) -->
    <SpouseExemptionNotice
      v-if="showSpouseExemptionNotice && secondDeathData"
      :message="secondDeathData.spouse_exemption_message"
      :has-spouse="hasSpouse"
      :data-sharing-enabled="secondDeathData.data_sharing_enabled"
      class="mb-6"
    />

    <!-- Missing Data Alert (only show for spouse account missing) -->
    <MissingDataAlert
      v-if="secondDeathData?.missing_data && secondDeathData.missing_data.includes('spouse_account')"
      :missing-data="['spouse_account']"
      :message="getMissingDataMessage()"
      class="mb-6"
    />

    <!-- Old Spouse Exemption Notice (keep for backward compatibility with non-married) -->
    <div v-if="ihtData?.spouse_exemption_applies && ihtData?.spouse_exemption > 0 && !isMarried" class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-green-800">Spouse Exemption Applied</h3>
          <p class="mt-2 text-sm text-green-700">
            <strong>{{ formatCurrency(ihtData.spouse_exemption) }}</strong> ({{ formatPercent((ihtData.spouse_exemption / ihtData.net_estate_value)) }}) of your estate is exempt from inheritance tax due to unlimited spousal transfer on death.
          </p>
        </div>
      </div>
    </div>

    <!-- Inheritance Tax Summary - Second Death (Married Users) -->
    <div v-if="isMarried && secondDeathData?.second_death_analysis" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
      <!-- Joint Death NOW -->
      <div class="bg-white rounded-lg p-4 sm:p-6 border-2 border-blue-500">
        <p class="text-sm text-blue-600 font-medium mb-2">Joint Death (Now)</p>
        <p class="text-xs text-blue-500 mb-1">Current net estate</p>
        <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.net_estate_value || 0) }}</p>
        <p class="text-xs text-blue-600 mt-2">If both die today</p>
      </div>

      <!-- Joint Death PROJECTED -->
      <div class="bg-white rounded-lg p-4 sm:p-6 border-2 border-purple-500">
        <p class="text-sm text-purple-600 font-medium mb-2">Joint Death (Projected)</p>
        <p class="text-xs text-purple-500 mb-1">At age {{ secondDeathData.second_death_analysis.second_death.estimated_age_at_death }}</p>
        <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.net_estate_value || 0) }}</p>
        <p class="text-xs text-purple-600 mt-2">Projected net estate</p>
      </div>

      <!-- Total Inheritance Tax Payable -->
      <div class="bg-white rounded-lg p-4 sm:p-6 sm:col-span-2 lg:col-span-1 border-2 border-red-500">
        <p class="text-sm text-red-600 font-medium mb-2">Total Inheritance Tax Payable</p>
        <div class="space-y-3">
          <div>
            <p class="text-xs text-red-500 mb-1">If both die now:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.iht_liability || 0) }}</p>
          </div>
          <div class="border-t border-red-200 pt-2">
            <p class="text-xs text-red-500 mb-1">At age {{ secondDeathData.second_death_analysis.second_death.estimated_age_at_death }}:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.iht_liability || 0) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Inheritance Tax Summary - Standard (Non-Married Users) with Projected Values -->
    <div v-else-if="ihtData && projection" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
      <!-- Taxable Estate - Now vs Projected -->
      <div class="bg-white rounded-lg p-4 sm:p-6 border-2 border-purple-500">
        <p class="text-sm text-purple-600 font-medium mb-2">Taxable Estate</p>
        <div class="space-y-3">
          <div>
            <p class="text-xs text-purple-500 mb-1">Now:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ formatCurrency(ihtData?.taxable_estate || 0) }}</p>
          </div>
          <div class="border-t border-purple-200 pt-2">
            <p class="text-xs text-purple-500 mb-1">At age {{ ihtData.estimated_age_at_death }}:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ formatCurrency(projection.at_death.taxable_estate) }}</p>
          </div>
        </div>
      </div>

      <!-- Total Allowances -->
      <div class="bg-white rounded-lg p-4 sm:p-6 border-2 border-green-500">
        <p class="text-sm text-green-600 font-medium mb-2">Total Allowances</p>
        <p class="text-xs text-green-500 mb-1">
          {{ (ihtData?.rnrb_available > 0 ? 'Tax-Free Band + Home Allowance' : 'Tax-Free Band only') }}
        </p>
        <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{{ formatCurrency(ihtData?.total_allowance || 0) }}</p>
      </div>

      <!-- Inheritance Tax Liability - Now vs Projected -->
      <div class="bg-white rounded-lg p-4 sm:p-6 sm:col-span-2 lg:col-span-1 border-2 border-red-500">
        <p class="text-sm text-red-600 font-medium mb-2">Total Inheritance Tax Liability</p>
        <div class="space-y-3">
          <div>
            <p class="text-xs text-red-500 mb-1">If death now:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ formatCurrency(projection.now.iht_liability) }}</p>
          </div>
          <div class="border-t border-red-200 pt-2">
            <p class="text-xs text-red-500 mb-1">At age {{ ihtData.estimated_age_at_death }}:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">{{ formatCurrency(projection.at_death.iht_liability) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Strategies Section -->
    <div v-if="!loading && ihtData" class="mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Inheritance Tax Mitigation Strategies</h3>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Will Card -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" @click="navigateToWillTab">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
              <h4 class="text-sm font-semibold text-gray-900">Will</h4>
            </div>
            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div class="flex items-center text-xs">
              <span class="text-gray-600">Status:</span>
              <span v-if="hasWill" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-white border-l-4 border-green-500 text-green-800">
                <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                  <circle cx="4" cy="4" r="3" />
                </svg>
                Complete
              </span>
              <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                  <circle cx="4" cy="4" r="3" />
                </svg>
                Incomplete
              </span>
            </div>
            <div v-if="hasWill" class="text-xs text-gray-600">
              <p>Last updated: {{ formatDate(willLastUpdated) }}</p>
              <p class="text-gray-500 mt-1">Executor: {{ willExecutor }}</p>
            </div>
            <div v-else class="text-xs text-amber-600">
              <p>No will recorded</p>
            </div>
          </div>
        </div>

        <!-- Gifting Card -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" @click="navigateToGiftingTab">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-green-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
              </svg>
              <h4 class="text-sm font-semibold text-gray-900">Gifting</h4>
            </div>
            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div class="text-xs">
              <p class="text-gray-600">Annual Exemption:</p>
              <p class="text-lg font-bold text-green-700">£3,000</p>
            </div>
            <div class="text-xs">
              <p class="text-gray-600">Immediately Giftable:</p>
              <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(immediatelyGiftableAmount) }}</p>
            </div>
          </div>
        </div>

        <!-- Life Policy Card -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" @click="navigateToProtectionModule">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-purple-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
              </svg>
              <h4 class="text-sm font-semibold text-gray-900">Life Policy</h4>
            </div>
            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div class="text-xs">
              <p class="text-gray-600">Cover Needed:</p>
              <p class="text-lg font-bold text-purple-700">{{ formatCurrency(ihtData?.estate_iht_liability || 0) }}</p>
            </div>
            <div class="text-xs">
              <p class="text-gray-600">Recommended:</p>
              <p class="text-sm font-semibold text-gray-900">Whole of Life</p>
              <p class="text-xs text-gray-500 mt-1">Written in trust</p>
            </div>
          </div>
        </div>

        <!-- Trust Card (only show if taxable estate > £2m) -->
        <div v-if="(ihtData?.taxable_estate || 0) > 2000000" class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" @click="navigateToTrustsTab">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-indigo-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
              </svg>
              <h4 class="text-sm font-semibold text-gray-900">Trust</h4>
            </div>
            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div class="text-xs">
              <p class="text-gray-600">Total Trust Value:</p>
              <p class="text-lg font-bold text-indigo-700">{{ formatCurrency(totalTrustValue) }}</p>
            </div>
            <div class="text-xs">
              <p class="text-gray-600">Outside Estate:</p>
              <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(trustValueOutsideEstate) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ trustEfficiencyPercent }}% efficient</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inheritance Tax Breakdown - Second Death (Married Users) -->
    <div v-if="!loading && isMarried && secondDeathData?.second_death_analysis" class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Inheritance Tax Calculation (Joint Death Scenario)</h3>
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <p class="text-sm text-blue-800 mb-2">
          <strong>What this calculation shows:</strong> This scenario assumes both you and your spouse pass away at the same time,
          with the combined estate then passing to your beneficiaries. The projected age ({{ secondDeathData.second_death_analysis.second_death.estimated_age_at_death }})
          is based on your life expectancy and may differ from your spouse's.
        </p>
        <p class="text-sm text-blue-700">
          <strong>If one spouse dies first:</strong> Under most wills, the entire estate passes to the surviving spouse tax-free
          (spouse exemption). Inheritance Tax would then be calculated on the surviving spouse's estate at their death,
          potentially with different allowances and values.
        </p>
      </div>

      <!-- Estate Calculation Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Line Item</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Now</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Death at Age {{ secondDeathData.second_death_analysis.second_death.estimated_age_at_death }}</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <!-- User Assets Section -->
            <template v-if="secondDeathData.assets_breakdown && secondDeathData.assets_breakdown.user">
              <!-- User Assets Header -->
              <tr class="bg-white border-l-4 border-blue-500">
                <td class="px-4 py-3 text-sm font-semibold text-blue-900">{{ secondDeathData.assets_breakdown.user.name }}'s Assets</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-blue-900" colspan="2"></td>
              </tr>

              <!-- User Property Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.property" :key="'user-property-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Property:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- User Investment Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.investment" :key="'user-investment-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Investment:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- User Cash/Savings Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.cash" :key="'user-cash-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Cash/Savings:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- User Business Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.business" :key="'user-business-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Business:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- User Chattel Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.chattel" :key="'user-chattel-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Chattel:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- User Assets Subtotal -->
              <tr class="bg-white border-l-4 border-blue-500">
                <td class="px-4 py-2 text-sm font-semibold text-blue-900 pl-8">Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-blue-900">{{ formatCurrency(secondDeathData.assets_breakdown.user.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-blue-900">{{ formatCurrency(secondDeathData.assets_breakdown.user.projected_total) }}</td>
              </tr>
            </template>

            <!-- Spouse Assets Section -->
            <template v-if="secondDeathData.data_sharing_enabled && secondDeathData.assets_breakdown.spouse">
              <!-- Spouse Assets Header -->
              <tr class="bg-white border-l-4 border-purple-500">
                <td class="px-4 py-3 text-sm font-semibold text-purple-900">{{ secondDeathData.assets_breakdown.spouse.name }}'s Assets</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-purple-900" colspan="2"></td>
              </tr>

              <!-- Spouse Property Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.property" :key="'spouse-property-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Property:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Investment Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.investment" :key="'spouse-investment-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Investment:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Cash/Savings Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.cash" :key="'spouse-cash-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Cash/Savings:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Business Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.business" :key="'spouse-business-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Business:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Chattel Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.chattel" :key="'spouse-chattel-' + index" class="bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Chattel:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Assets Subtotal -->
              <tr class="bg-white border-l-4 border-purple-500">
                <td class="px-4 py-2 text-sm font-semibold text-purple-900 pl-8">Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-purple-900">{{ formatCurrency(secondDeathData.assets_breakdown.spouse.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-purple-900">{{ formatCurrency(secondDeathData.assets_breakdown.spouse.projected_total) }}</td>
              </tr>
            </template>

            <!-- Total Gross Assets -->
            <tr class="bg-white border-l-4 border-blue-500">
              <td class="px-4 py-3 text-sm font-semibold text-blue-900">Total Gross Assets</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-blue-900">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.gross_estate_value || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-blue-900">{{ formatCurrency(totalGrossAssetsProjected) }}</td>
            </tr>

            <!-- User Liabilities Section -->
            <template v-if="secondDeathData.liabilities_breakdown && secondDeathData.liabilities_breakdown.user">
              <!-- User Liabilities Header -->
              <tr class="bg-white border-l-4 border-red-500">
                <td class="px-4 py-3 text-sm font-semibold text-red-900">{{ secondDeathData.liabilities_breakdown.user.name }}'s Liabilities</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-red-900" colspan="2"></td>
              </tr>

              <!-- User Mortgages -->
              <tr v-for="(mortgage, index) in secondDeathData.liabilities_breakdown.user.liabilities.mortgages" :key="'user-mortgage-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-red-500">Mortgage:</span> {{ mortgage.property_address }}
                  <span class="text-xs text-gray-500 ml-2">{{ mortgage.mortgage_type }}</span>
                  <span v-if="mortgage.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(mortgage.outstanding_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(mortgage.projected_balance !== undefined && mortgage.projected_balance !== null ? mortgage.projected_balance : mortgage.outstanding_balance) }}</td>
              </tr>

              <!-- User Other Liabilities -->
              <tr v-for="(liability, index) in secondDeathData.liabilities_breakdown.user.liabilities.other_liabilities" :key="'user-liability-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-red-500">{{ liability.type }}:</span> {{ liability.institution }}
                  <span v-if="liability.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(liability.current_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(liability.projected_balance !== undefined && liability.projected_balance !== null ? liability.projected_balance : liability.current_balance) }}</td>
              </tr>

              <!-- User Liabilities Subtotal (only if > 0) -->
              <tr v-if="secondDeathData.liabilities_breakdown.user.total > 0" class="bg-white border-l-4 border-red-500">
                <td class="px-4 py-2 text-sm font-semibold text-red-900 pl-8">Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-red-900">-{{ formatCurrency(secondDeathData.liabilities_breakdown.user.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-red-900">-{{ formatCurrency(userLiabilitiesProjectedTotal) }}</td>
              </tr>
            </template>

            <!-- Spouse Liabilities Section -->
            <template v-if="secondDeathData.liabilities_breakdown && secondDeathData.liabilities_breakdown.spouse">
              <!-- Spouse Liabilities Header -->
              <tr class="bg-white border-l-4 border-orange-500">
                <td class="px-4 py-3 text-sm font-semibold text-orange-900">{{ secondDeathData.liabilities_breakdown.spouse.name }}'s Liabilities</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-orange-900" colspan="2"></td>
              </tr>

              <!-- Spouse Mortgages -->
              <tr v-for="(mortgage, index) in secondDeathData.liabilities_breakdown.spouse.liabilities.mortgages" :key="'spouse-mortgage-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-red-500">Mortgage:</span> {{ mortgage.property_address }}
                  <span class="text-xs text-gray-500 ml-2">{{ mortgage.mortgage_type }}</span>
                  <span v-if="mortgage.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(mortgage.outstanding_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(mortgage.projected_balance !== undefined && mortgage.projected_balance !== null ? mortgage.projected_balance : mortgage.outstanding_balance) }}</td>
              </tr>

              <!-- Spouse Other Liabilities -->
              <tr v-for="(liability, index) in secondDeathData.liabilities_breakdown.spouse.liabilities.other_liabilities" :key="'spouse-liability-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-red-500">{{ liability.type }}:</span> {{ liability.institution }}
                  <span v-if="liability.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint - 50%)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(liability.current_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(liability.projected_balance !== undefined && liability.projected_balance !== null ? liability.projected_balance : liability.current_balance) }}</td>
              </tr>

              <!-- Spouse Liabilities Subtotal (only if > 0) -->
              <tr v-if="secondDeathData.liabilities_breakdown.spouse.total > 0" class="bg-white border-l-4 border-orange-500">
                <td class="px-4 py-2 text-sm font-semibold text-orange-900 pl-8">Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-orange-900">-{{ formatCurrency(secondDeathData.liabilities_breakdown.spouse.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-orange-900">-{{ formatCurrency(spouseLiabilitiesProjectedTotal) }}</td>
              </tr>
            </template>

            <!-- Total Liabilities -->
            <tr class="bg-white border-l-4 border-red-500">
              <td class="px-4 py-3 text-sm font-semibold text-red-900">Less: Total Liabilities</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-red-900">-{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.liabilities || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-red-900">-{{ formatCurrency(totalLiabilitiesProjected) }}</td>
            </tr>

            <!-- Net Estate -->
            <tr class="bg-white border-l-4 border-purple-500">
              <td class="px-4 py-3 text-sm font-semibold text-purple-800">Net Estate</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-purple-800">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.net_estate_value || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-purple-800">{{ formatCurrency(netEstateProjected) }}</td>
            </tr>

            <!-- NRB (Individual) -->
            <tr>
              <td class="px-4 py-3 text-sm text-gray-600">Less: Tax-Free Allowance (Individual)</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.nrb || 325000) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.nrb || 325000) }}</td>
            </tr>

            <!-- NRB from Spouse -->
            <tr v-if="(secondDeathData.second_death_analysis.current_iht_calculation?.nrb_from_spouse || 0) > 0 || (secondDeathData.second_death_analysis.iht_calculation?.nrb_from_spouse || 0) > 0">
              <td class="px-4 py-3 text-sm text-gray-600">
                Less: Tax-Free Allowance from Spouse
                <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-amber-600">(Default - verify by linking spouse)</span>
              </td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.nrb_from_spouse || 325000) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.nrb_from_spouse || 325000) }}</td>
            </tr>

            <!-- RNRB (Individual) -->
            <tr v-if="secondDeathData.second_death_analysis.iht_calculation?.rnrb_eligible && ((secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_individual || 0) > 0 || (secondDeathData.second_death_analysis.iht_calculation?.rnrb_individual || 0) > 0)">
              <td class="px-4 py-3 text-sm text-gray-600">Less: Home Allowance (Individual)</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_individual || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.rnrb_individual || 0) }}</td>
            </tr>

            <!-- RNRB from Spouse -->
            <tr v-if="secondDeathData.second_death_analysis.iht_calculation?.rnrb_eligible && ((secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_from_spouse || 0) > 0 || (secondDeathData.second_death_analysis.iht_calculation?.rnrb_from_spouse || 0) > 0)">
              <td class="px-4 py-3 text-sm text-gray-600">
                Less: Home Allowance from Spouse
                <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-amber-600">(Default - verify by linking spouse)</span>
              </td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_from_spouse || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.rnrb_from_spouse || 0) }}</td>
            </tr>

            <!-- RNRB Taper Warning (Current) -->
            <tr v-if="secondDeathData.second_death_analysis.iht_calculation?.rnrb_eligible && secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_tapered">
              <td colspan="3" class="px-4 py-2 text-xs bg-white border-l-4 border-orange-500">
                <span class="text-orange-700">
                  <strong>Home Allowance Reduced:</strong> Estate value {{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.net_estate_value || 0) }} exceeds {{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_taper_threshold || 2000000) }} threshold.
                  <span v-if="(secondDeathData.second_death_analysis.current_iht_calculation?.rnrb || 0) === 0">Home allowance completely removed (reduced by {{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_taper_amount || 0) }}).</span>
                  <span v-else>Home allowance reduced by {{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.rnrb_taper_amount || 0) }} (£1 reduction for every £2 over threshold).</span>
                </span>
              </td>
            </tr>

            <!-- RNRB Not Available Message -->
            <tr v-if="!secondDeathData.second_death_analysis.iht_calculation?.rnrb_eligible">
              <td colspan="3" class="px-4 py-2 text-xs text-amber-700 bg-white border-l-4 border-amber-500">
                <strong>Note:</strong> Home allowance (residence nil rate band) not available - no main residence identified or property not left to direct descendants
              </td>
            </tr>

            <!-- Taxable Estate -->
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-900">Taxable Estate</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.taxable_estate || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(taxableEstateProjected) }}</td>
            </tr>

            <!-- Inheritance Tax Liability -->
            <tr class="bg-white border-l-4 border-red-500">
              <td class="px-4 py-3 text-sm font-semibold text-red-800">Inheritance Tax Liability (40%)</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-red-800">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.iht_liability || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-red-800">{{ formatCurrency(ihtLiabilityProjected) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Inheritance Tax Breakdown - Standard (Non-Married Users OR Married without spouse link) -->
    <div v-else-if="!loading && ihtData" class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">
        {{ isMarried ? 'Inheritance Tax Calculation (Joint Death Scenario)' : 'Inheritance Tax Calculation Breakdown' }}
      </h3>

      <!-- Explanation box for married users -->
      <div v-if="isMarried && projection" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <p class="text-sm text-blue-800 mb-2">
          <strong>What this calculation shows:</strong> This scenario assumes both you and your spouse pass away at the same time,
          with the combined estate then passing to your beneficiaries. The projected age ({{ projection.at_death.estimated_age_at_death }})
          is based on your life expectancy and may differ from your spouse's.
        </p>
        <p class="text-sm text-blue-700">
          <strong>If one spouse dies first:</strong> Under most wills, the entire estate passes to the surviving spouse tax-free
          (spouse exemption). Inheritance Tax would then be calculated on the surviving spouse's estate at their death,
          potentially with different allowances and values.
        </p>
      </div>

      <!-- Simple description for non-married users -->
      <p v-else-if="projection" class="text-sm text-gray-600 mb-6">Comparison of Inheritance Tax liability if death occurs now vs. at projected life expectancy (Age {{ projection.at_death.estimated_age_at_death }})</p>

      <!-- Detailed Asset & Liability Breakdown Table -->
      <div v-if="secondDeathData?.assets_breakdown" class="overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset / Liability</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Value</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div>Projected (Age {{ projection?.at_death?.estimated_age_at_death || '...' }})</div>
                <div class="text-[10px] font-normal text-gray-400 normal-case mt-0.5">This is a static future value calculation using 4.7%</div>
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <!-- User Assets Header -->
            <tr class="bg-white border-l-4 border-blue-500">
              <td class="px-4 py-3 text-sm font-semibold text-blue-900">{{ secondDeathData.assets_breakdown.user.name }}'s Assets</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-blue-900" colspan="2"></td>
            </tr>

            <!-- User Property Assets -->
            <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.property" :key="'user-property-' + index">
              <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                <span class="text-xs text-gray-500">Property:</span> {{ asset.name }}
                <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
              </td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
            </tr>

            <!-- User Investment Assets -->
            <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.investment" :key="'user-investment-' + index">
              <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                <span class="text-xs text-gray-500">Investment:</span> {{ asset.name }}
                <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
              </td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
            </tr>

            <!-- User Cash/Savings Assets -->
            <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.cash" :key="'user-cash-' + index">
              <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                <span class="text-xs text-gray-500">Cash/Savings:</span> {{ asset.name }}
                <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
              </td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
            </tr>

            <!-- User Business Assets -->
            <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.business" :key="'user-business-' + index">
              <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                <span class="text-xs text-gray-500">Business:</span> {{ asset.name }}
                <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
              </td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
            </tr>

            <!-- User Chattel Assets -->
            <tr v-for="(asset, index) in secondDeathData.assets_breakdown.user.assets.chattel" :key="'user-chattel-' + index">
              <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                <span class="text-xs text-gray-500">Chattel:</span> {{ asset.name }}
                <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
              </td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
              <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
            </tr>

            <!-- User Assets Subtotal -->
            <tr class="bg-white border-l-4 border-blue-500">
              <td class="px-4 py-2 text-sm font-semibold text-blue-900 pl-8">Assets Subtotal</td>
              <td class="px-4 py-2 text-sm text-right font-semibold text-blue-900">{{ formatCurrency(secondDeathData.assets_breakdown.user.total) }}</td>
              <td class="px-4 py-2 text-sm text-right font-semibold text-blue-900">{{ formatCurrency(secondDeathData.assets_breakdown.user.projected_total) }}</td>
            </tr>

            <!-- Spouse Assets Section (if married with data sharing) -->
            <template v-if="secondDeathData.assets_breakdown.spouse">
              <!-- Spouse Assets Header -->
              <tr class="bg-white border-l-4 border-green-500">
                <td class="px-4 py-3 text-sm font-semibold text-green-900">{{ secondDeathData.assets_breakdown.spouse.name }}'s Assets</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-green-900" colspan="2"></td>
              </tr>

              <!-- Spouse Property Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.property" :key="'spouse-property-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Property:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Investment Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.investment" :key="'spouse-investment-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Investment:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Cash/Savings Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.cash" :key="'spouse-cash-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Cash/Savings:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Business Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.business" :key="'spouse-business-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Business:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Chattel Assets -->
              <tr v-for="(asset, index) in secondDeathData.assets_breakdown.spouse.assets.chattel" :key="'spouse-chattel-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-gray-500">Chattel:</span> {{ asset.name }}
                  <span v-if="asset.is_joint" class="ml-2 text-xs text-amber-600 font-medium">(Joint)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.value) }}</td>
                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ formatCurrency(asset.projected_value) }}</td>
              </tr>

              <!-- Spouse Assets Subtotal -->
              <tr class="bg-white border-l-4 border-green-500">
                <td class="px-4 py-2 text-sm font-semibold text-green-900 pl-8">Assets Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-green-900">{{ formatCurrency(secondDeathData.assets_breakdown.spouse.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-green-900">{{ formatCurrency(secondDeathData.assets_breakdown.spouse.projected_total) }}</td>
              </tr>
            </template>

            <!-- Total Gross Assets -->
            <tr class="bg-white border-l-4 border-indigo-500 border-t-2 border-indigo-300">
              <td class="px-4 py-3 text-sm font-bold text-indigo-900">Total Gross Assets</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-indigo-900">{{ formatCurrency(secondDeathData.calculation?.total_gross_assets || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-indigo-900">{{ formatCurrency(secondDeathData.calculation?.projected_gross_assets || 0) }}</td>
            </tr>

            <!-- User Liabilities Section -->
            <template v-if="secondDeathData.liabilities_breakdown && secondDeathData.liabilities_breakdown.user">
              <!-- User Liabilities Header -->
              <tr class="bg-white border-l-4 border-red-500">
                <td class="px-4 py-3 text-sm font-semibold text-red-900">{{ secondDeathData.liabilities_breakdown.user.name }}'s Liabilities</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-red-900" colspan="2"></td>
              </tr>

              <!-- User Mortgages -->
              <tr v-for="(mortgage, index) in secondDeathData.liabilities_breakdown.user.liabilities?.mortgages" :key="'user-mortgage-2-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-red-500">Mortgage:</span> {{ mortgage.property_address }}
                </td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(mortgage.outstanding_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(mortgage.projected_balance) }}</td>
              </tr>

              <!-- User Other Liabilities -->
              <tr v-for="(liability, index) in secondDeathData.liabilities_breakdown.user.liabilities?.other_liabilities" :key="'user-liability-2-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-red-500">{{ liability.type }}:</span> {{ liability.institution }}
                </td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(liability.current_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-red-600">-{{ formatCurrency(liability.projected_balance) }}</td>
              </tr>

              <!-- User Liabilities Subtotal -->
              <tr v-if="secondDeathData.liabilities_breakdown.user.total > 0" class="bg-white border-l-4 border-red-500">
                <td class="px-4 py-2 text-sm font-semibold text-red-900 pl-8">Liabilities Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-red-900">-{{ formatCurrency(secondDeathData.liabilities_breakdown.user.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-red-900">-{{ formatCurrency(secondDeathData.liabilities_breakdown.user.projected_total) }}</td>
              </tr>
            </template>

            <!-- Spouse Liabilities Section -->
            <template v-if="secondDeathData.assets_breakdown.spouse && secondDeathData.liabilities_breakdown && secondDeathData.liabilities_breakdown.spouse">
              <!-- Spouse Liabilities Header -->
              <tr class="bg-white border-l-4 border-orange-500">
                <td class="px-4 py-3 text-sm font-semibold text-orange-900">{{ secondDeathData.liabilities_breakdown.spouse.name }}'s Liabilities</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-orange-900" colspan="2"></td>
              </tr>

              <!-- Spouse Mortgages -->
              <tr v-for="(mortgage, index) in secondDeathData.liabilities_breakdown.spouse.liabilities?.mortgages" :key="'spouse-mortgage-2-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-orange-500">Mortgage:</span> {{ mortgage.property_address }}
                </td>
                <td class="px-4 py-2 text-sm text-right text-orange-600">-{{ formatCurrency(mortgage.outstanding_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-orange-600">-{{ formatCurrency(mortgage.projected_balance) }}</td>
              </tr>

              <!-- Spouse Other Liabilities -->
              <tr v-for="(liability, index) in secondDeathData.liabilities_breakdown.spouse.liabilities?.other_liabilities" :key="'spouse-liability-2-' + index">
                <td class="px-4 py-2 text-sm text-gray-700 pl-8">
                  <span class="text-xs text-orange-500">{{ liability.type }}:</span> {{ liability.institution }}
                </td>
                <td class="px-4 py-2 text-sm text-right text-orange-600">-{{ formatCurrency(liability.current_balance) }}</td>
                <td class="px-4 py-2 text-sm text-right text-orange-600">-{{ formatCurrency(liability.projected_balance) }}</td>
              </tr>

              <!-- Spouse Liabilities Subtotal -->
              <tr v-if="secondDeathData.liabilities_breakdown.spouse.total > 0" class="bg-white border-l-4 border-orange-500">
                <td class="px-4 py-2 text-sm font-semibold text-orange-900 pl-8">Liabilities Subtotal</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-orange-900">-{{ formatCurrency(secondDeathData.liabilities_breakdown.spouse.total) }}</td>
                <td class="px-4 py-2 text-sm text-right font-semibold text-orange-900">-{{ formatCurrency(secondDeathData.liabilities_breakdown.spouse.projected_total) }}</td>
              </tr>
            </template>

            <!-- Total Liabilities -->
            <tr class="bg-white border-l-4 border-red-500 border-t-2 border-red-300">
              <td class="px-4 py-3 text-sm font-bold text-red-900">Total Liabilities</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-red-900">-{{ formatCurrency(secondDeathData.calculation?.total_liabilities || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-red-900">-{{ formatCurrency(secondDeathData.calculation?.projected_liabilities || 0) }}</td>
            </tr>

            <!-- Net Estate Total -->
            <tr class="bg-white border-l-4 border-purple-500">
              <td class="px-4 py-3 text-sm font-semibold text-purple-900">Net Estate</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-purple-900">{{ formatCurrency(ihtData?.net_estate_value || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-purple-900">{{ formatCurrency(projection?.at_death?.net_estate || 0) }}</td>
            </tr>

            <!-- Allowances Section -->
            <template v-if="secondDeathData?.assets_breakdown?.spouse">
              <!-- Married couple - show user and spouse NRB separately -->
              <tr class="bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600 pl-8">Less: {{ secondDeathData.assets_breakdown.user.name }}'s NRB</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              </tr>
              <tr class="bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600 pl-8">Less: {{ secondDeathData.assets_breakdown.spouse.name }}'s NRB</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              </tr>
              <tr class="bg-gray-50" v-if="ihtData?.rnrb_available > 0">
                <td class="px-4 py-3 text-sm text-gray-600 pl-8">Less: {{ secondDeathData.assets_breakdown.user.name }}'s RNRB</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency((ihtData?.rnrb_available || 0) / 2) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency((ihtData?.rnrb_available || 0) / 2) }}</td>
              </tr>
              <tr class="bg-gray-50" v-if="ihtData?.rnrb_available > 0">
                <td class="px-4 py-3 text-sm text-gray-600 pl-8">Less: {{ secondDeathData.assets_breakdown.spouse.name }}'s RNRB</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency((ihtData?.rnrb_available || 0) / 2) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency((ihtData?.rnrb_available || 0) / 2) }}</td>
              </tr>
            </template>
            <template v-else>
              <!-- Single person - show combined NRB -->
              <tr class="bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600">Less: Nil Rate Band (NRB)</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(ihtData?.nrb_available || 0) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(ihtData?.nrb_available || 0) }}</td>
              </tr>
              <tr class="bg-gray-50" v-if="ihtData?.rnrb_available > 0">
                <td class="px-4 py-3 text-sm text-gray-600">Less: Residence Nil Rate Band (RNRB)</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(ihtData?.rnrb_available || 0) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(ihtData?.rnrb_available || 0) }}</td>
              </tr>
            </template>

            <!-- Taxable Estate -->
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-900">Taxable Estate</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(ihtData?.taxable_estate || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(projection?.at_death?.taxable_estate || 0) }}</td>
            </tr>

            <!-- Inheritance Tax Liability -->
            <tr class="bg-white border-l-4 border-red-500">
              <td class="px-4 py-3 text-sm font-semibold text-red-800">Inheritance Tax Liability (40%)</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-red-800">{{ formatCurrency(ihtData?.estate_iht_liability || 0) }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold text-red-800">{{ formatCurrency(projection?.at_death?.iht_liability || 0) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Fallback: Old list style if no projection data -->
      <div v-else class="space-y-3 mb-6">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Estate Calculation</h4>

        <div class="flex justify-between items-center py-2 border-b border-gray-200">
          <span class="text-sm text-gray-600">Total Estate Value</span>
          <span class="text-sm font-medium text-gray-900">{{ formatCurrency(ihtData?.gross_estate_value || 0) }}</span>
        </div>

        <div v-if="ihtData?.liabilities > 0" class="flex justify-between items-center py-2 border-b border-gray-200">
          <span class="text-sm text-gray-600">Less: Liabilities</span>
          <span class="text-sm font-medium text-gray-900">-{{ formatCurrency(ihtData?.liabilities || 0) }}</span>
        </div>

        <div class="flex justify-between items-center py-2 border-b border-gray-200 bg-gray-50 rounded-lg">
          <span class="text-sm font-semibold text-purple-800">Gross Estate</span>
          <span class="text-sm font-bold text-purple-800">{{ formatCurrency(ihtData?.net_estate_value || 0) }}</span>
        </div>

        <div class="flex justify-between items-center py-2 border-b border-gray-200">
          <span class="text-sm text-gray-600">Less: NRB (Individual)</span>
          <span class="text-sm font-medium text-gray-900">-{{ formatCurrency(ihtData?.nrb || 325000) }}</span>
        </div>

        <div v-if="ihtData?.nrb_from_spouse > 0" class="flex justify-between items-center py-2 border-b border-gray-200">
          <span class="text-sm text-gray-600">
            Less: Tax-Free Allowance from Spouse
            <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-amber-600">(Default - verify by linking spouse)</span>
          </span>
          <span class="text-sm font-medium text-gray-900">-{{ formatCurrency(ihtData.nrb_from_spouse) }}</span>
        </div>

        <div v-if="ihtData?.rnrb_eligible && ihtData?.rnrb_individual > 0" class="flex justify-between items-center py-2 border-b border-gray-200">
          <span class="text-sm text-gray-600">Less: RNRB (Individual)</span>
          <span class="text-sm font-medium text-gray-900">-{{ formatCurrency(ihtData?.rnrb_individual || 0) }}</span>
        </div>

        <div v-if="ihtData?.rnrb_eligible && ihtData?.rnrb_from_spouse > 0" class="flex justify-between items-center py-2 border-b border-gray-200">
          <span class="text-sm text-gray-600">
            Less: Home Allowance from Spouse
            <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-amber-600">(Default - verify by linking spouse)</span>
          </span>
          <span class="text-sm font-medium text-gray-900">-{{ formatCurrency(ihtData?.rnrb_from_spouse || 0) }}</span>
        </div>

        <div class="flex justify-between items-center py-3 bg-gray-50 rounded">
          <span class="text-base font-semibold text-gray-900">Taxable Estate</span>
          <span class="text-base font-bold text-gray-900">{{ formatCurrency(ihtData?.taxable_estate || 0) }}</span>
        </div>

        <div class="flex justify-between items-center py-3 bg-gray-50 rounded-lg">
          <span class="text-base font-semibold text-red-800">Inheritance Tax Liability ({{ formatPercent(ihtData?.iht_rate || 0.4) }})</span>
          <span class="text-base font-bold text-red-800">{{ formatCurrency(ihtData?.estate_iht_liability || 0) }}</span>
        </div>
      </div>

      <!-- Gift Calculation -->
      <div v-if="hasGifts" class="space-y-3 pt-6 border-t border-gray-300">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Gift Liability (7-Year Rule with Taper Relief)</h4>

        <!-- PET Gifts -->
        <div v-if="hasPETGifts" class="mb-4">
          <p class="text-xs font-medium text-gray-600 mb-2">Potentially Exempt Transfers (PETs)</p>
          <div v-for="gift in petGifts" :key="gift.gift_id" class="mb-3 pl-4 py-2 bg-gray-50 rounded-lg">
            <div class="flex justify-between items-center mb-1">
              <div class="flex-1">
                <span class="text-sm font-medium text-gray-800">{{ gift.recipient }}</span>
                <span class="ml-2 text-xs text-gray-500">({{ formatDate(gift.gift_date) }})</span>
              </div>
              <span class="text-sm font-bold text-amber-800">{{ formatCurrency(gift.tax_liability) }}</span>
            </div>
            <div class="text-xs text-gray-600 space-y-1 mt-2">
              <div class="flex justify-between">
                <span>Gift value:</span>
                <span class="font-medium">{{ formatCurrency(gift.gift_value) }}</span>
              </div>
              <div class="flex justify-between text-green-700">
                <span>Less: NRB covered</span>
                <span class="font-medium">-{{ formatCurrency(gift.nrb_covered) }}</span>
              </div>
              <div class="flex justify-between border-t border-amber-200 pt-1">
                <span>Taxable amount:</span>
                <span class="font-medium">{{ formatCurrency(gift.taxable_amount) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Taper relief rate ({{ gift.years_ago }} years):</span>
                <span class="font-medium">{{ formatPercent(gift.taper_rate) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CLT Gifts -->
        <div v-if="hasCLTGifts" class="mb-4">
          <p class="text-xs font-medium text-gray-600 mb-2">Chargeable Lifetime Transfers (CLTs)</p>
          <div v-for="clt in cltGifts" :key="clt.gift_id" class="flex justify-between items-center py-2 pl-4 border-l-2 border-blue-400">
            <div class="flex-1">
              <span class="text-sm text-gray-700">{{ clt.recipient }} ({{ formatDate(clt.gift_date) }})</span>
              <span class="ml-2 text-xs text-gray-500">{{ clt.years_ago }} years ago</span>
            </div>
            <span class="text-sm font-medium text-blue-700">{{ formatCurrency(clt.tax_liability) }}</span>
          </div>
        </div>

        <div class="flex justify-between items-center py-3 bg-gray-50 rounded-lg">
          <span class="text-base font-semibold text-amber-800">Total Gift Inheritance Tax Liability</span>
          <span class="text-base font-bold text-amber-800">{{ formatCurrency(ihtData?.gift_iht_liability || 0) }}</span>
        </div>
      </div>
    </div>

    <!-- Tax Allowances Information (NRB & RNRB Messages) -->
    <div v-if="!loading && ihtData" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <!-- NRB Message -->
      <div v-if="ihtData.nrb_message" class="bg-gray-50 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">Nil Rate Band (NRB)</h3>
            <div class="mt-2 text-sm text-blue-700">
              <p>{{ ihtData.nrb_message }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- RNRB Message -->
      <div v-if="ihtData.rnrb_message" class="bg-gray-50 rounded-lg p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg v-if="ihtData.rnrb_status === 'full'" class="h-5 w-5 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <svg v-else-if="ihtData.rnrb_status === 'tapered'" class="h-5 w-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium" :class="[
              ihtData.rnrb_status === 'full' ? 'text-green-800' :
              ihtData.rnrb_status === 'tapered' ? 'text-amber-800' :
              'text-gray-800'
            ]">Residence Nil Rate Band (RNRB)</h3>
            <div class="mt-2 text-sm" :class="[
              ihtData.rnrb_status === 'full' ? 'text-green-700' :
              ihtData.rnrb_status === 'tapered' ? 'text-amber-700' :
              'text-gray-700'
            ]">
              <p>{{ ihtData.rnrb_message }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Dual Gifting Timeline (Married Users Only) -->
    <DualGiftingTimeline
      v-if="isMarried && secondDeathData?.user_gifting_timeline"
      :user-timeline="secondDeathData.user_gifting_timeline"
      :spouse-timeline="secondDeathData.spouse_gifting_timeline"
      :data-sharing-enabled="secondDeathData.data_sharing_enabled"
      class="mb-8"
    />

    <!-- Life Cover Recommendations (Married Users with Second Death Data) -->
    <LifeCoverRecommendations
      v-if="isMarried && secondDeathData?.life_cover_recommendations"
      :recommendations="secondDeathData.life_cover_recommendations"
      :iht-liability="secondDeathData.effective_iht_liability || secondDeathData.second_death_analysis?.iht_calculation?.iht_liability || 0"
      class="mb-8"
    />

    <!-- Standard Recommendations (Non-Married Users OR Married without full second death data) -->
    <div v-if="!secondDeathData?.mitigation_strategies && ihtData?.iht_liability > 0" class="bg-gray-50 rounded-lg p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-red-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">Inheritance Tax Mitigation Strategies</h3>
          <div class="mt-2 text-sm text-red-700">
            <p class="font-semibold mb-2">
              Your estate has a potential Inheritance Tax liability of {{ formatCurrency(ihtData?.iht_liability || 0) }}. Consider these strategies:
            </p>
            <ul class="list-disc list-inside space-y-1">
              <li>Regular gifting using PET and annual exemptions (£3,000/year)</li>
              <li>Charitable giving (can reduce Inheritance Tax rate from 40% to 36% if ≥10% to charity)</li>
              <li>Trust planning to remove assets from your estate</li>
              <li>Life insurance policies written in trust to cover Inheritance Tax liability</li>
              <li v-if="!ihtData?.rnrb || ihtData.rnrb === 0">Consider leaving your main residence to direct descendants to claim RNRB (up to £175,000)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="ihtData && ihtData.iht_liability === 0" class="bg-gray-50 rounded-lg p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-green-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-green-800">No Inheritance Tax Liability</h3>
          <div class="mt-2 text-sm text-green-700">
            <p class="mb-2">
              Good news! Your estate is currently below the Inheritance Tax threshold with {{ formatCurrency(ihtData?.total_allowance || 500000) }} in allowances available.
            </p>
            <p>
              Continue to monitor your estate value as asset prices change. Review your Inheritance Tax position annually or after significant life events.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Trust Planning Summary -->
    <div v-if="ihtData?.trust_details && ihtData.trust_details.length > 0" class="bg-white shadow rounded-lg p-6 mt-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Trust Planning Summary</h3>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs text-purple-600 font-medium">Total Trust Value</p>
          <p class="text-2xl font-bold text-purple-900 mt-1">{{ formatCurrency(totalTrustValue) }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs text-green-600 font-medium">Value Outside Estate</p>
          <p class="text-2xl font-bold text-green-900 mt-1">{{ formatCurrency(trustValueOutsideEstate) }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4">
          <p class="text-xs text-blue-600 font-medium">Inheritance Tax Efficiency</p>
          <p class="text-2xl font-bold text-blue-900 mt-1">{{ trustEfficiencyPercent }}%</p>
        </div>
      </div>

      <div class="space-y-3">
        <div v-for="trust in ihtData.trust_details" :key="trust.trust_id" class="border border-gray-200 rounded-lg p-3">
          <div class="flex justify-between items-start mb-2">
            <div>
              <h4 class="text-sm font-semibold text-gray-900">{{ trust.trust_name }}</h4>
              <p class="text-xs text-gray-500">{{ getTrustTypeName(trust.trust_type) }}</p>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ formatCurrency(trust.current_value) }}</span>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <span class="text-gray-500">Value in Estate:</span>
              <span class="font-medium ml-1" :class="trust.iht_value > 0 ? 'text-red-600' : 'text-green-600'">
                {{ formatCurrency(trust.iht_value) }}
              </span>
            </div>
            <div>
              <span class="text-gray-500">Outside Estate:</span>
              <span class="font-medium text-green-600 ml-1">{{ formatCurrency(trust.current_value - trust.iht_value) }}</span>
            </div>
          </div>

          <div v-if="trust.iht_value > 0" class="mt-2 pt-2 border-t border-gray-200">
            <p class="text-xs text-amber-700">
              <strong>Note:</strong> {{ getTrustIHTExplanation(trust.trust_type) }}
            </p>
          </div>
          <div v-else class="mt-2 pt-2 border-t border-gray-200">
            <p class="text-xs text-green-700">
              ✓ This trust's value is completely outside your estate for Inheritance Tax purposes
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import SpouseExemptionNotice from './SpouseExemptionNotice.vue';
import MissingDataAlert from './MissingDataAlert.vue';
import DualGiftingTimeline from './DualGiftingTimeline.vue';
import LifeCoverRecommendations from './LifeCoverRecommendations.vue';
import estateService from '../../services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IHTPlanning',

  mixins: [currencyMixin],

  components: {
    SpouseExemptionNotice,
    MissingDataAlert,
    DualGiftingTimeline,
    LifeCoverRecommendations,
  },

  data() {
    return {
      ihtData: null,
      secondDeathData: null,
      projection: null,
      userGender: 'male',
      isMarried: false,
      hasSpouse: false,
      showSpouseExemptionNotice: false,
      loading: false,
      error: null,
    };
  },

  computed: {
    ...mapState('estate', ['analysis', 'gifts']),
    ...mapGetters('estate', ['netWorthValue', 'ihtLiability', 'ihtExemptAssets']),

    hasSpouseLinked() {
      return this.hasSpouse;
    },

    formattedIHTLiability() {
      return this.formatCurrency(this.ihtData?.iht_liability || 0);
    },

    hasGifts() {
      return this.ihtData?.gifting_details &&
             (this.ihtData.gifting_details.pet_liability?.gift_count > 0 ||
              this.ihtData.gifting_details.clt_liability?.clt_count > 0);
    },

    hasTrusts() {
      return this.ihtData?.trust_details && this.ihtData.trust_details.length > 0;
    },

    activeTrustDetails() {
      if (!this.ihtData?.trust_details) return [];
      return this.ihtData.trust_details.filter(t => t.iht_value > 0);
    },

    totalTrustValue() {
      if (!this.ihtData?.trust_details) return 0;
      return this.ihtData.trust_details.reduce((sum, t) => {
        const value = parseFloat(t.current_value) || 0;
        return sum + value;
      }, 0);
    },

    trustValueOutsideEstate() {
      if (!this.ihtData?.trust_details) return 0;
      return this.ihtData.trust_details.reduce((sum, t) => {
        const currentValue = parseFloat(t.current_value) || 0;
        const ihtValue = parseFloat(t.iht_value) || 0;
        return sum + (currentValue - ihtValue);
      }, 0);
    },

    trustEfficiencyPercent() {
      const total = this.totalTrustValue;
      if (!total || total === 0) return 0;
      const outsideEstate = this.trustValueOutsideEstate;
      return Math.round((outsideEstate / total) * 100);
    },

    hasPETGifts() {
      return this.ihtData?.gifting_details?.pet_liability?.gift_count > 0;
    },

    hasCLTGifts() {
      return this.ihtData?.gifting_details?.clt_liability?.clt_count > 0;
    },

    petGifts() {
      return this.ihtData?.gifting_details?.pet_liability?.gifts || [];
    },

    cltGifts() {
      return this.ihtData?.gifting_details?.clt_liability?.clts || [];
    },

    // Strategy Cards Computed Properties
    hasWill() {
      return this.secondDeathData?.will_info?.has_will || false;
    },

    willLastUpdated() {
      return this.secondDeathData?.will_info?.last_updated || null;
    },

    willExecutor() {
      return this.secondDeathData?.will_info?.executor_name || 'Not specified';
    },

    immediatelyGiftableAmount() {
      // Calculate assets that can be gifted immediately (liquid assets)
      const netWorth = this.ihtData?.net_estate_value || 0;
      const taxableEstate = this.ihtData?.taxable_estate || 0;

      // Estimate liquid assets as a percentage of net worth (simplified)
      // In a real scenario, this would come from the backend with actual liquid asset calculations
      return netWorth * 0.3; // Assume 30% of assets are liquid and giftable
    },

    // Projected subtotals for second death breakdown
    userAssetsProjectedTotal() {
      if (!this.secondDeathData?.assets_breakdown?.user?.assets) return 0;
      const assets = this.secondDeathData.assets_breakdown.user.assets;
      let total = 0;

      // Sum all asset types
      Object.keys(assets).forEach(assetType => {
        if (Array.isArray(assets[assetType])) {
          assets[assetType].forEach(asset => {
            total += (asset.projected_value || asset.value || 0);
          });
        }
      });

      return total;
    },

    spouseAssetsProjectedTotal() {
      if (!this.secondDeathData?.assets_breakdown?.spouse?.assets) return 0;
      const assets = this.secondDeathData.assets_breakdown.spouse.assets;
      let total = 0;

      // Sum all asset types
      Object.keys(assets).forEach(assetType => {
        if (Array.isArray(assets[assetType])) {
          assets[assetType].forEach(asset => {
            total += (asset.projected_value || asset.value || 0);
          });
        }
      });

      return total;
    },

    userLiabilitiesProjectedTotal() {
      if (!this.secondDeathData?.liabilities_breakdown?.user?.liabilities) return 0;
      const liabilities = this.secondDeathData.liabilities_breakdown.user.liabilities;
      let total = 0;

      // Sum mortgages (use projected_balance, which may be 0 if paid off)
      if (Array.isArray(liabilities.mortgages)) {
        liabilities.mortgages.forEach(mortgage => {
          const value = mortgage.projected_balance !== undefined && mortgage.projected_balance !== null
            ? mortgage.projected_balance
            : (mortgage.outstanding_balance || 0);
          total += value;
        });
      }

      // Sum other liabilities (use projected_balance, which equals current_balance)
      if (Array.isArray(liabilities.other_liabilities)) {
        liabilities.other_liabilities.forEach(liability => {
          const value = liability.projected_balance !== undefined && liability.projected_balance !== null
            ? liability.projected_balance
            : (liability.current_balance || 0);
          total += value;
        });
      }

      return total;
    },

    spouseLiabilitiesProjectedTotal() {
      if (!this.secondDeathData?.liabilities_breakdown?.spouse?.liabilities) return 0;
      const liabilities = this.secondDeathData.liabilities_breakdown.spouse.liabilities;
      let total = 0;

      // Sum mortgages (use projected_balance, which may be 0 if paid off)
      if (Array.isArray(liabilities.mortgages)) {
        liabilities.mortgages.forEach(mortgage => {
          const value = mortgage.projected_balance !== undefined && mortgage.projected_balance !== null
            ? mortgage.projected_balance
            : (mortgage.outstanding_balance || 0);
          total += value;
        });
      }

      // Sum other liabilities (use projected_balance, which equals current_balance)
      if (Array.isArray(liabilities.other_liabilities)) {
        liabilities.other_liabilities.forEach(liability => {
          const value = liability.projected_balance !== undefined && liability.projected_balance !== null
            ? liability.projected_balance
            : (liability.current_balance || 0);
          total += value;
        });
      }

      return total;
    },

    // Total Gross Assets projected (sum of user + spouse subtotals)
    totalGrossAssetsProjected() {
      return this.userAssetsProjectedTotal + this.spouseAssetsProjectedTotal;
    },

    // Total Liabilities projected (sum of user + spouse subtotals)
    totalLiabilitiesProjected() {
      return this.userLiabilitiesProjectedTotal + this.spouseLiabilitiesProjectedTotal;
    },

    // Net Estate projected (Total Gross Assets - Total Liabilities)
    netEstateProjected() {
      return this.totalGrossAssetsProjected - this.totalLiabilitiesProjected;
    },

    // Taxable Estate projected (Net Estate - NRB - RNRB)
    taxableEstateProjected() {
      const totalNRB = this.secondDeathData?.second_death_analysis?.iht_calculation?.total_nrb || 650000;
      const rnrb = this.secondDeathData?.second_death_analysis?.iht_calculation?.rnrb || 0;
      return Math.max(0, this.netEstateProjected - totalNRB - rnrb);
    },

    // IHT Liability projected (40% of Taxable Estate)
    ihtLiabilityProjected() {
      return this.taxableEstateProjected * 0.40;
    },
  },

  mounted() {
    this.checkUserMaritalStatus();
    this.loadIHTCalculation();
  },

  watch: {
    '$route'() {
      // Reload when navigating back to this tab
      this.loadIHTCalculation();
    },
  },

  methods: {
    ...mapActions('estate', ['calculateIHT', 'calculateSecondDeathIHTPlanning']),

    checkUserMaritalStatus() {
      const user = this.$store.state.auth?.user;
      if (user) {
        this.isMarried = user.marital_status === 'married';
        this.hasSpouse = user.spouse_id !== null;
        this.userGender = user.gender || 'male';
      }
    },

    navigateToGiftingTab() {
      // Emit event to parent EstateDashboard to switch to Gifting tab
      this.$emit('switch-tab', 'gifting');
    },

    navigateToWillTab() {
      // Emit event to parent EstateDashboard to switch to Will tab
      this.$emit('switch-tab', 'will');
    },

    navigateToProtectionModule() {
      // Emit event to parent EstateDashboard to switch to Life Policy Strategy tab
      this.$emit('switch-tab', 'life-policy');
    },

    navigateToTrustsTab() {
      // Emit event to parent EstateDashboard to switch to Trusts tab
      this.$emit('switch-tab', 'trusts');
    },

    async loadIHTCalculation() {
      // Preview mode now uses real database users, so we use the API call
      // The old client-side computePreviewIHTData() is no longer needed
      this.loading = true;
      this.error = null;

      try {
        // Both married and single users now use the unified calculateIHT endpoint
        const response = await this.calculateSecondDeathIHTPlanning();

        if (response && response.success) {
          // Store the full response for detailed breakdown
          this.secondDeathData = response;

          // Extract IHT summary for display
          if (response.iht_summary) {
            this.ihtData = {
              // Current values
              net_estate_value: response.iht_summary.current.net_estate,
              gross_estate_value: response.calculation?.total_gross_assets || response.iht_summary.current.net_estate, // Fallback to net_estate
              nrb_available: response.iht_summary.current.nrb_available,
              nrb: response.iht_summary.current.nrb_available, // Legacy alias
              nrb_message: response.iht_summary.current.nrb_message,
              rnrb_available: response.iht_summary.current.rnrb_available,
              rnrb_eligible: response.iht_summary.current.rnrb_available > 0, // Eligible if RNRB > 0
              rnrb_individual: response.iht_summary.current.rnrb_available, // Legacy alias (combined now)
              nrb_from_spouse: 0, // Not separately tracked in new system
              rnrb_from_spouse: 0, // Not separately tracked in new system
              rnrb_status: response.iht_summary.current.rnrb_status,
              rnrb_message: response.iht_summary.current.rnrb_message,
              total_allowance: response.iht_summary.current.total_allowances,
              taxable_estate: response.iht_summary.current.taxable_estate,
              estate_iht_liability: response.iht_summary.current.iht_liability,
              iht_rate: response.iht_summary.current.effective_rate / 100,
              liabilities: response.calculation?.total_liabilities || 0,

              // Projected values
              projected_net_estate: response.iht_summary.projected.net_estate,
              projected_taxable_estate: response.iht_summary.projected.taxable_estate,
              projected_iht_liability: response.iht_summary.projected.iht_liability,
              years_to_death: response.iht_summary.projected.years_to_death,
              estimated_age_at_death: response.iht_summary.projected.estimated_age_at_death,
            };

            // Create projection object for display
            this.projection = {
              now: {
                net_estate: response.iht_summary.current.net_estate,
                taxable_estate: response.iht_summary.current.taxable_estate,
                iht_liability: response.iht_summary.current.iht_liability,
                assets: response.calculation?.total_gross_assets || response.iht_summary.current.net_estate,
                liabilities: response.calculation?.total_liabilities || 0,
                mortgages: 0, // Included in liabilities
              },
              at_death: {
                net_estate: response.iht_summary.projected.net_estate,
                taxable_estate: response.iht_summary.projected.taxable_estate,
                iht_liability: response.iht_summary.projected.iht_liability,
                years_to_death: response.iht_summary.projected.years_to_death,
                estimated_age_at_death: response.iht_summary.projected.estimated_age_at_death,
                assets: response.calculation?.projected_gross_assets || response.iht_summary.projected.net_estate,
                liabilities: response.calculation?.projected_liabilities || 0,
                mortgages: 0, // Included in liabilities
              }
            };
          }
        }
      } catch (error) {
        console.error('❌ Failed to load IHT calculation:', error);
        this.error = error.message || 'Failed to calculate Inheritance Tax liability';
      } finally {
        this.loading = false;
      }
    },

    computePreviewIHTData() {
      // Get preview data from estate store
      const estateState = this.$store.state.estate;
      const previewData = this.$store.state.preview?.personaData;

      // Calculate total assets
      const assetsValue = estateState.assets.reduce((sum, a) => sum + parseFloat(a.current_value || 0), 0);
      const investmentsValue = estateState.investmentAccounts.reduce((sum, i) => sum + parseFloat(i.current_value || 0), 0);
      const totalAssets = assetsValue + investmentsValue;

      // Calculate total liabilities
      const totalLiabilities = estateState.liabilities.reduce((sum, l) => sum + parseFloat(l.current_balance || 0), 0);

      // Calculate net estate
      const netEstate = totalAssets - totalLiabilities;

      // IHT allowances (UK 2025/26)
      const nrb = 325000; // Nil Rate Band
      const hasMainResidence = estateState.assets.some(a => a.asset_type === 'property');
      const rnrb = hasMainResidence ? 175000 : 0; // Residence Nil Rate Band
      const totalAllowance = nrb + rnrb;

      // Calculate taxable estate and IHT liability
      const taxableEstate = Math.max(0, netEstate - totalAllowance);
      const ihtLiability = taxableEstate * 0.40;

      // Get user data for age calculations
      const user = previewData?.user;
      const userAge = user?.age || 40;
      const estimatedDeathAge = user?.gender === 'female' ? 84 : 81;
      const yearsToDeathVal = estimatedDeathAge - userAge;

      // Calculate projected values (4.7% annual growth assumption)
      const growthRate = 0.047;
      const projectedNetEstate = netEstate * Math.pow(1 + growthRate, yearsToDeathVal);
      const projectedTaxableEstate = Math.max(0, projectedNetEstate - totalAllowance);
      const projectedIHTLiability = projectedTaxableEstate * 0.40;

      // Set ihtData for display
      this.ihtData = {
        net_estate_value: netEstate,
        gross_estate_value: totalAssets,
        nrb_available: nrb,
        nrb: nrb,
        rnrb_available: rnrb,
        rnrb_eligible: hasMainResidence,
        total_allowance: totalAllowance,
        taxable_estate: taxableEstate,
        estate_iht_liability: ihtLiability,
        iht_liability: ihtLiability,
        iht_rate: 0.40,
        liabilities: totalLiabilities,
        estimated_age_at_death: estimatedDeathAge,
        years_to_death: yearsToDeathVal,
      };

      // Set projection data for display
      this.projection = {
        now: {
          net_estate: netEstate,
          taxable_estate: taxableEstate,
          iht_liability: ihtLiability,
          assets: totalAssets,
          liabilities: totalLiabilities,
        },
        at_death: {
          net_estate: projectedNetEstate,
          taxable_estate: projectedTaxableEstate,
          iht_liability: projectedIHTLiability,
          years_to_death: yearsToDeathVal,
          estimated_age_at_death: estimatedDeathAge,
          assets: totalAssets * Math.pow(1 + growthRate, yearsToDeathVal),
          liabilities: 0, // Assume paid off
        },
      };

      // Set isMarried based on preview data
      this.isMarried = previewData?.user?.marital_status === 'married';
      this.hasSpouse = !!previewData?.spouse;

      // For married users in preview, set secondDeathData with basic structure
      if (this.isMarried) {
        this.secondDeathData = {
          success: true,
          spouse_exemption_message: 'In preview mode, assets passing to spouse are exempt from Inheritance Tax. Inheritance Tax would only apply on second death.',
          data_sharing_enabled: true,
          will_info: {
            has_will: previewData?.user?.has_will || false,
          },
        };
        this.showSpouseExemptionNotice = true;
      }
    },

    getMissingDataMessage() {
      if (!this.secondDeathData?.missing_data) return '';

      const missingItems = this.secondDeathData.missing_data;
      if (missingItems.includes('spouse_account')) {
        return 'Link your spouse account to enable full second death Inheritance Tax planning.';
      }
      return 'Some information is required to complete the second death Inheritance Tax calculation.';
    },

    formatNumber(value) {
      return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatPercent(value) {
      return `${(value * 100).toFixed(0)}%`;
    },

    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    getTaperReliefRate(yearsAgo) {
      if (yearsAgo < 3) return 40;
      if (yearsAgo < 4) return 32;
      if (yearsAgo < 5) return 24;
      if (yearsAgo < 6) return 16;
      if (yearsAgo < 7) return 8;
      return 0;
    },

    getTrustTypeName(type) {
      const names = {
        bare: 'Bare Trust',
        interest_in_possession: 'Interest in Possession',
        discretionary: 'Discretionary',
        accumulation_maintenance: 'A&M Trust',
        life_insurance: 'Life Insurance',
        discounted_gift: 'Discounted Gift',
        loan: 'Loan Trust',
        mixed: 'Mixed',
        settlor_interested: 'Settlor-Interested',
      };
      return names[type] || type;
    },

    getTrustIHTExplanation(type) {
      const explanations = {
        discounted_gift: 'For a Discounted Gift Trust, the retained income value (discount) counts in your estate.',
        loan: 'For a Loan Trust, the outstanding loan balance counts in your estate. Growth is outside.',
        interest_in_possession: 'For an Interest in Possession Trust, the full value counts in the life tenant\'s estate.',
        settlor_interested: 'For a Settlor-Interested Trust, the full value remains in your estate (reservation of benefit).',
      };
      return explanations[type] || 'This trust type has specific Inheritance Tax treatment rules.';
    },
  },
};
</script>
