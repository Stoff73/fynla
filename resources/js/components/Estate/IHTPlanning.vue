<template>
  <div class="iht-planning-tab">
    <!-- Error State - No Profile -->
    <div v-if="error && !ihtData" class="bg-violet-50 border border-violet-200 rounded-lg p-6 mb-6">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-horizon-500">Inheritance Tax Profile Required</h3>
          <p class="mt-2 text-sm text-horizon-500">{{ error }}</p>
          <p class="mt-2 text-sm text-horizon-500">Please set up your inheritance tax profile in the Estate module to see your tax calculation.</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
      <p class="mt-2 text-neutral-500">Calculating inheritance tax liability...</p>
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
    <div v-if="ihtData?.spouse_exemption_applies && ihtData?.spouse_exemption > 0 && !isMarried" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-horizon-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-spring-800">Spouse Exemption Applied</h3>
          <p class="mt-2 text-sm text-spring-700">
            <strong>{{ formatCurrency(ihtData.spouse_exemption) }}</strong> ({{ formatPercent((ihtData.spouse_exemption / ihtData.net_estate_value)) }}) of your estate is exempt from inheritance tax due to unlimited spousal transfer on death.
          </p>
        </div>
      </div>
    </div>

    <!-- Summary Cards (hidden when showing table-only detail view) -->
    <template v-if="!tableOnly">

    <!-- Inheritance Tax Summary - Second Death (Married Users) -->
    <div v-if="isMarried && secondDeathData?.second_death_analysis" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
      <!-- Joint Death NOW -->
      <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm">
        <p class="text-sm text-neutral-500 font-medium mb-2">Joint Death (Now)</p>
        <p class="text-xs text-neutral-500 mb-1">Current net estate</p>
        <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.net_estate_value || 0) }}</p>
        <p class="text-xs text-neutral-500 mt-2">If both die today</p>
      </div>

      <!-- Joint Death PROJECTED -->
      <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm">
        <p class="text-sm text-neutral-500 font-medium mb-2">Joint Death (Projected)</p>
        <p class="text-xs text-neutral-500 mb-1">At age {{ secondDeathData.second_death_analysis.second_death.estimated_age_at_death }}</p>
        <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.net_estate_value || 0) }}</p>
        <p class="text-xs text-neutral-500 mt-2">Projected net estate</p>
      </div>

      <!-- Total Inheritance Tax Payable -->
      <div class="bg-white rounded-lg p-5 sm:col-span-2 lg:col-span-1 border border-light-gray shadow-sm">
        <p class="text-sm text-neutral-500 font-medium mb-2">Total Inheritance Tax Payable</p>
        <div class="space-y-3">
          <div>
            <p class="text-xs text-neutral-500 mb-1">If both die now:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-horizon-500">{{ formatCurrency(secondDeathData.second_death_analysis.current_iht_calculation?.iht_liability || 0) }}</p>
          </div>
          <div class="border-t border-light-gray pt-2">
            <p class="text-xs text-neutral-500 mb-1">At age {{ secondDeathData.second_death_analysis.second_death.estimated_age_at_death }}:</p>
            <p class="text-lg sm:text-xl lg:text-2xl font-bold text-horizon-500">{{ formatCurrency(secondDeathData.second_death_analysis.iht_calculation?.iht_liability || 0) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Inheritance Tax Summary & Strategies - Standard (Non-Married Users) -->
    <div v-else-if="ihtData && projection" class="mb-8">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Row 1, Col 1: IHT Summary Card (click navigates to full IHT table) -->
        <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow cursor-pointer" @click="$router.push('/estate/inheritance-tax')">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm text-neutral-500 font-medium">
              Inheritance Tax Summary
              <span v-if="charitableBequest" class="ml-1 text-xs text-spring-600">({{ effectiveIHTRateLabel }} rate)</span>
            </p>
            <svg class="h-4 w-4 text-horizon-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-neutral-500 mb-1">Taxable Estate</p>
              <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(standardTableProps?.taxableEstate?.now || 0) }}</p>
              <p class="text-xs text-horizon-400 mt-1">Age {{ ihtData.estimated_age_at_death }}: {{ formatCurrency(standardTableProps?.taxableEstate?.projected || 0) }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500 mb-1">Inheritance Tax Liability</p>
              <p class="text-sm font-bold" :class="charitableBequest ? 'text-spring-700' : 'text-horizon-500'">{{ formatCurrency(standardTableProps?.ihtLiability?.now || 0) }}</p>
              <p class="text-xs text-horizon-400 mt-1">Age {{ ihtData.estimated_age_at_death }}: {{ formatCurrency(standardTableProps?.ihtLiability?.projected || 0) }}</p>
            </div>
          </div>
        </div>

        <!-- Row 1, Col 2: Will Card -->
        <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow cursor-pointer" @click="navigateToWillTab">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-neutral-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
              <h4 class="text-sm font-semibold text-horizon-500">Will</h4>
            </div>
            <svg class="h-4 w-4 text-horizon-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div class="flex items-center text-xs">
              <span class="text-neutral-500">Status:</span>
              <span v-if="hasWill" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-white  text-spring-800">
                <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                Complete
              </span>
              <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-50 text-horizon-500">
                <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                Incomplete
              </span>
            </div>
            <div v-if="hasWill" class="text-xs text-neutral-500">
              <p>Last updated: {{ formatDate(willLastUpdated) }}</p>
              <p class="text-neutral-500 mt-1">Executor: {{ willExecutor }}</p>
            </div>
            <div v-else class="text-xs text-neutral-500">
              <p>No will recorded</p>
            </div>
          </div>
        </div>

        <!-- Row 1, Col 3: Power of Attorney Card -->
        <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow cursor-pointer" @click="navigateToLpa">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-horizon-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
              </svg>
              <h4 class="text-sm font-semibold text-horizon-500">Power of Attorney</h4>
            </div>
            <svg class="h-4 w-4 text-horizon-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div v-if="lpasByType.length > 0" class="space-y-1">
              <div v-for="lpa in lpasByType" :key="lpa.type" class="flex items-center justify-between text-xs">
                <span class="text-neutral-500">{{ lpa.label }}</span>
                <span :class="[
                  'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                  lpa.status === 'registered' ? 'bg-spring-50 text-spring-800' : 'bg-violet-50 text-horizon-500'
                ]">
                  {{ lpa.statusLabel }}
                </span>
              </div>
            </div>
            <div v-else class="text-xs text-neutral-500">
              <p>Appoint someone to act on your behalf</p>
            </div>
          </div>
        </div>

        <!-- Row 2, Col 1: Charitable Bequest Card -->
        <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-raspberry-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
              </svg>
              <h4 class="text-sm font-semibold text-horizon-500">Charitable Bequest</h4>
            </div>
            <div v-if="savingCharitableBequest" class="animate-spin h-4 w-4 border-2 border-raspberry-500 border-t-transparent rounded-full"></div>
          </div>
          <div class="space-y-3">
            <div class="text-xs">
              <p class="text-neutral-500 mb-2">Leave {{ formatCurrency(charitableDonationAmount) }}+ to charity to reduce your Inheritance Tax rate?</p>
              <div class="flex items-center space-x-4">
                <label class="inline-flex items-center cursor-pointer">
                  <input type="radio" :checked="charitableBequest === true" :disabled="savingCharitableBequest" class="form-radio text-raspberry-500 h-4 w-4" @change="toggleCharitableBequest(true)">
                  <span class="ml-1.5 text-sm text-neutral-500">Yes</span>
                </label>
                <label class="inline-flex items-center cursor-pointer">
                  <input type="radio" :checked="charitableBequest === false" :disabled="savingCharitableBequest" class="form-radio text-raspberry-500 h-4 w-4" @change="toggleCharitableBequest(false)">
                  <span class="ml-1.5 text-sm text-neutral-500">No</span>
                </label>
              </div>
            </div>
            <div v-if="charitableBequest" class="text-xs">
              <p class="text-neutral-500">Potential Inheritance Tax Savings:</p>
              <p class="text-lg font-bold text-raspberry-700">{{ formatCurrency(charitableBequestSavings) }}</p>
              <p class="text-xs text-neutral-500 mt-1">Rate reduces from 40% to 36%</p>
            </div>
          </div>
        </div>

        <!-- Row 2, Col 2: Life Policy Card -->
        <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow cursor-pointer" @click="navigateToProtectionModule">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-neutral-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
              </svg>
              <h4 class="text-sm font-semibold text-horizon-500">Life Policy</h4>
            </div>
            <svg class="h-4 w-4 text-horizon-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-neutral-500">Cover Needed:</span>
              <span class="font-bold text-horizon-500">{{ formatCurrency(standardTableProps?.ihtLiability?.now || 0) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-neutral-500">Recommended:</span>
              <span class="font-semibold text-horizon-500">Whole of Life</span>
            </div>
            <div v-if="isMarried" class="flex items-center justify-between">
              <span class="text-neutral-500">Type:</span>
              <span class="font-semibold text-horizon-500">Joint Second Death</span>
            </div>
            <p class="text-neutral-500 mt-1">Written in trust</p>
          </div>
        </div>

        <!-- Row 2, Col 3: Gifting Card -->
        <div class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow cursor-pointer" @click="navigateToGiftingTab">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-spring-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
              </svg>
              <h4 class="text-sm font-semibold text-horizon-500">Gifting</h4>
            </div>
            <svg class="h-4 w-4 text-horizon-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-neutral-500">Annual Exemption:</span>
              <span class="font-bold text-spring-700">£{{ annualGiftExemption.toLocaleString() }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-neutral-500">Small Gift Allowance:</span>
              <span class="font-semibold text-horizon-500">£250 per person</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-neutral-500">7-Year Potentially Exempt Transfer:</span>
              <span class="font-semibold text-spring-700">Available</span>
            </div>
          </div>
        </div>

        <!-- Trust Card (only show if taxable estate > £2m) -->
        <div v-if="(ihtData?.taxable_estate || 0) > 2000000" class="bg-white rounded-lg p-5 border border-light-gray shadow-sm hover:shadow-md transition-shadow cursor-pointer" @click="navigateToTrustsTab">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
              <svg class="h-6 w-6 text-violet-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
              </svg>
              <h4 class="text-sm font-semibold text-horizon-500">Trust</h4>
            </div>
            <svg class="h-4 w-4 text-horizon-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
          <div class="space-y-2">
            <div class="text-xs">
              <p class="text-neutral-500">Total Trust Value:</p>
              <p class="text-lg font-bold text-violet-500">{{ formatCurrency(totalTrustValue) }}</p>
            </div>
            <div class="text-xs">
              <p class="text-neutral-500">Outside Estate:</p>
              <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(trustValueOutsideEstate) }}</p>
              <p class="text-xs text-neutral-500 mt-1">{{ trustEfficiencyPercent }}% efficient</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    </template>

    <!-- IHT Calculation Table (shown in /estate/inheritance-tax detail view via tableOnly prop) -->
    <template v-if="tableOnly">
      <!-- Inheritance Tax Breakdown - Second Death (Married Users) -->
      <div v-if="!loading && isMarried && secondDeathData?.second_death_analysis" class="bg-white rounded-lg border border-light-gray p-6 mb-8">
        <div class="flex items-center gap-2 mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Inheritance Tax Calculation (Joint Death Scenario)</h3>
        </div>
        <IHTCalculationTable
          v-if="secondDeathTableProps"
          v-bind="secondDeathTableProps"
          :charitable-bequest="charitableBequest"
          :effective-i-h-t-rate-label="effectiveIHTRateLabel"
          :has-spouse-linked="hasSpouseLinked"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :growth-rate="growthRate"
          :years-to-death-minus-5="yearsToDeathMinus5"
          :years-to-death-plus-5="yearsToDeathPlus5"
          @toggle-minus-5="showMinus5Years = !showMinus5Years"
          @toggle-plus-5="showPlus5Years = !showPlus5Years"
        />
      </div>

      <!-- Inheritance Tax Breakdown - Standard (Non-Married Users) -->
      <div v-else-if="!loading && ihtData && standardTableProps" class="bg-white rounded-lg border border-light-gray p-6 mb-8">
        <div class="flex items-center gap-2 mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">
            {{ isMarried ? 'Inheritance Tax Calculation (Joint Death Scenario)' : 'Inheritance Tax Calculation Breakdown' }}
          </h3>
        </div>
        <p v-if="!isMarried && projection" class="text-sm text-neutral-500 mb-6">Comparison of Inheritance Tax liability if death occurs now vs. at projected life expectancy (Age {{ projection.at_death.estimated_age_at_death }})</p>
        <IHTCalculationTable
          v-bind="standardTableProps"
          :charitable-bequest="charitableBequest"
          :effective-i-h-t-rate-label="effectiveIHTRateLabel"
          :has-spouse-linked="false"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :growth-rate="growthRate"
          :years-to-death-minus-5="yearsToDeathMinus5"
          :years-to-death-plus-5="yearsToDeathPlus5"
          @toggle-minus-5="showMinus5Years = !showMinus5Years"
          @toggle-plus-5="showPlus5Years = !showPlus5Years"
        />
      </div>

      <!-- Tax Allowances Information -->
      <div v-if="!loading && ihtData" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div v-if="ihtData.nrb_message" class="bg-eggshell-500 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-violet-800">Tax-Free Allowance</h3>
          <p class="mt-2 text-sm text-violet-800">{{ ihtData.nrb_message }}</p>
        </div>
        <div v-if="ihtData.rnrb_message" class="bg-eggshell-500 rounded-lg p-4">
          <h3 class="text-sm font-semibold" :class="ihtData.rnrb_status === 'full' ? 'text-spring-800' : 'text-horizon-500'">Home Allowance</h3>
          <p class="mt-2 text-sm" :class="ihtData.rnrb_status === 'full' ? 'text-spring-800' : 'text-horizon-500'">{{ ihtData.rnrb_message }}</p>
        </div>
      </div>
    </template>

    <!-- Letter to Spouse Cross-Validation Warnings -->
    <LetterEstateWarnings
      v-if="!loading"
      :summary-only="true"
      :show-view-action="true"
      class="mb-8"
      @view-details="navigateToLetter"
    />

    <!-- Cash Projection Breakdown Table (Hidden from view - logic retained) -->
    <div v-if="false && !loading && cashProjectionBreakdown" class="bg-white rounded-lg border border-light-gray p-6 mb-8">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-horizon-500">Cash Projection Methodology</h3>
        <button
          class="text-sm text-violet-600 hover:text-violet-800"
          @click="showCashProjectionTable = !showCashProjectionTable"
        >
          {{ showCashProjectionTable ? 'Hide Details' : 'Show Details' }}
        </button>
      </div>

      <!-- Summary -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div class="bg-eggshell-500 rounded p-3">
          <p class="text-xs text-neutral-500">Starting Cash</p>
          <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(cashProjectionBreakdown.starting_cash) }}</p>
        </div>
        <div class="bg-eggshell-500 rounded p-3">
          <p class="text-xs text-neutral-500">Pre-Retirement Surplus</p>
          <p class="text-sm font-semibold" :class="preRetirementSurplus >= 0 ? 'text-spring-700' : 'text-raspberry-700'">
            {{ formatCurrency(preRetirementSurplus) }}/year
          </p>
        </div>
        <div class="bg-eggshell-500 rounded p-3">
          <p class="text-xs text-neutral-500">Retirement Surplus</p>
          <p class="text-sm font-semibold" :class="retirementSurplus >= 0 ? 'text-spring-700' : 'text-raspberry-700'">
            {{ formatCurrency(retirementSurplus) }}/year
          </p>
        </div>
        <div class="bg-eggshell-500 rounded p-3">
          <p class="text-xs text-neutral-500">Final Cash (Age {{ cashProjectionBreakdown.death_age }})</p>
          <p class="text-sm font-semibold" :class="cashProjectionBreakdown.final_cash_raw >= 0 ? 'text-horizon-500' : 'text-raspberry-700'">
            {{ formatCurrency(cashProjectionBreakdown.final_cash_capped) }}
            <span v-if="cashProjectionBreakdown.final_cash_raw < 0" class="text-xs text-raspberry-500 block">
              ({{ formatCurrency(cashProjectionBreakdown.final_cash_raw) }} shortfall from investments)
            </span>
          </p>
        </div>
      </div>

      <!-- Year-by-Year Table -->
      <div v-if="showCashProjectionTable" class="overflow-x-auto">
        <table class="min-w-full text-xs">
          <thead>
            <tr class="bg-savannah-100">
              <th class="px-2 py-2 text-left font-medium text-neutral-500">Year</th>
              <th class="px-2 py-2 text-left font-medium text-neutral-500">Age</th>
              <th class="px-2 py-2 text-left font-medium text-neutral-500">Phase</th>
              <th class="px-2 py-2 text-right font-medium text-neutral-500">Income</th>
              <th class="px-2 py-2 text-right font-medium text-neutral-500">Expenses</th>
              <th class="px-2 py-2 text-right font-medium text-neutral-500">Surplus</th>
              <th class="px-2 py-2 text-right font-medium text-neutral-500">Running Total</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in cashProjectionBreakdown.years"
              :key="row.year"
              :class="[
                row.running_total < 0 ? 'bg-raspberry-50' : '',
                row.phase === 'Pre-Retirement' ? 'bg-violet-50/30' : '',
                row.age === cashProjectionBreakdown.retirement_age ? 'border-t-2 border-violet-300' : '',
                row.age === cashProjectionBreakdown.state_pension_age ? 'border-t border-spring-300' : ''
              ]"
            >
              <td class="px-2 py-1 text-neutral-500">{{ row.year }}</td>
              <td class="px-2 py-1 text-horizon-500 font-medium">{{ row.age }}</td>
              <td class="px-2 py-1">
                <span
                  class="px-1.5 py-0.5 rounded text-xs"
                  :class="row.phase === 'Pre-Retirement' ? 'bg-violet-50 text-violet-700' : 'bg-spring-100 text-spring-700'"
                >
                  {{ row.phase }}
                </span>
              </td>
              <td class="px-2 py-1 text-right text-horizon-500">{{ formatCurrency(row.income) }}</td>
              <td class="px-2 py-1 text-right text-horizon-500">{{ formatCurrency(row.expenses) }}</td>
              <td class="px-2 py-1 text-right font-medium" :class="row.surplus >= 0 ? 'text-spring-700' : 'text-raspberry-700'">
                {{ formatCurrency(row.surplus) }}
              </td>
              <td class="px-2 py-1 text-right font-medium" :class="row.running_total >= 0 ? 'text-horizon-500' : 'text-raspberry-700'">
                {{ formatCurrency(row.running_total) }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap gap-4 text-xs text-neutral-500">
          <div class="flex items-center gap-1">
            <span class="w-3 h-3 bg-violet-50 border border-violet-200 rounded"></span>
            <span>Pre-Retirement</span>
          </div>
          <div class="flex items-center gap-1">
            <span class="w-3 h-3 bg-raspberry-50 border border-raspberry-200 rounded"></span>
            <span>Negative Balance (shortfall from investments)</span>
          </div>
        </div>
      </div>

      <!-- Methodology Note -->
      <div class="mt-4 text-xs text-neutral-500 bg-eggshell-500 rounded p-3">
        <p class="font-medium text-neutral-500 mb-1">Methodology:</p>
        <ul class="list-disc list-inside space-y-0.5">
          <li>Pre-retirement: Employment income minus estimated expenses (70% of income if no profile)</li>
          <li>Retirement: Target retirement income + state pension minus retirement expenses</li>
          <li>Negative cash indicates shortfall that would be drawn from investment accounts</li>
        </ul>
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
    <div v-if="!secondDeathData?.mitigation_strategies && ihtData?.iht_liability > 0" class="bg-eggshell-500 rounded-lg p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-horizon-400"
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
          <h3 class="text-sm font-medium text-horizon-500">Inheritance Tax Mitigation Strategies</h3>
          <div class="mt-2 text-sm text-neutral-500">
            <p class="font-semibold mb-2">
              Your estate has a potential Inheritance Tax liability of {{ formatCurrency(ihtData?.iht_liability || 0) }}. Consider these strategies:
            </p>
            <ul class="list-disc list-inside space-y-1">
              <li>Regular gifting using Potentially Exempt Transfers and annual exemptions (£{{ annualGiftExemption.toLocaleString() }}/year)</li>
              <li>Charitable giving (can reduce Inheritance Tax rate from 40% to 36% if ≥10% to charity)</li>
              <li>Trust planning to remove assets from your estate</li>
              <li>Life insurance policies written in trust to cover Inheritance Tax liability</li>
              <li v-if="!ihtData?.rnrb || ihtData.rnrb === 0">Consider leaving your main residence to direct descendants to claim the Home Allowance (up to £175,000)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="ihtData && ihtData.iht_liability === 0" class="bg-eggshell-500 rounded-lg p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5 text-horizon-400"
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
          <h3 class="text-sm font-medium text-spring-800">No Inheritance Tax Liability</h3>
          <div class="mt-2 text-sm text-spring-700">
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
      <h3 class="text-lg font-medium text-horizon-500 mb-4">Trust Planning Summary</h3>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 font-medium">Total Trust Value</p>
          <p class="text-2xl font-bold text-horizon-500 mt-1">{{ formatCurrency(totalTrustValue) }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-spring-600 font-medium">Value Outside Estate</p>
          <p class="text-2xl font-bold text-horizon-500 mt-1">{{ formatCurrency(trustValueOutsideEstate) }}</p>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-xs text-neutral-500 font-medium">Inheritance Tax Efficiency</p>
          <p class="text-2xl font-bold text-horizon-500 mt-1">{{ trustEfficiencyPercent }}%</p>
        </div>
      </div>

      <div class="space-y-3">
        <div v-for="trust in ihtData.trust_details" :key="trust.trust_id" class="border border-light-gray rounded-lg p-3">
          <div class="flex justify-between items-start mb-2">
            <div>
              <h4 class="text-sm font-semibold text-horizon-500">{{ trust.trust_name }}</h4>
              <p class="text-xs text-neutral-500">{{ getTrustTypeName(trust.trust_type) }}</p>
            </div>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(trust.current_value) }}</span>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
              <span class="text-neutral-500">Value in Estate:</span>
              <span class="font-medium ml-1" :class="trust.iht_value > 0 ? 'text-neutral-500' : 'text-spring-600'">
                {{ formatCurrency(trust.iht_value) }}
              </span>
            </div>
            <div>
              <span class="text-neutral-500">Outside Estate:</span>
              <span class="font-medium text-spring-600 ml-1">{{ formatCurrency(trust.current_value - trust.iht_value) }}</span>
            </div>
          </div>

          <div v-if="trust.iht_value > 0" class="mt-2 pt-2 border-t border-light-gray">
            <p class="text-xs text-horizon-500">
              <strong>Note:</strong> {{ getTrustIHTExplanation(trust.trust_type) }}
            </p>
          </div>
          <div v-else class="mt-2 pt-2 border-t border-light-gray">
            <p class="text-xs text-spring-700">
              ✓ This trust's value is completely outside your estate for Inheritance Tax purposes
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Life Events Impact on Estate (dashboard only, not in IHT detail view) -->
    <div v-if="!tableOnly && estateLifeEvents.length > 0 && ihtData" class="bg-white rounded-lg p-4 sm:p-6 border border-light-gray">
      <EstateLifeEventsImpact
        :events="estateLifeEventsWithIHT"
        :summary="estateLifeEventsSummary"
        :review-triggers="estateReviewTriggers"
      />
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import SpouseExemptionNotice from './SpouseExemptionNotice.vue';
import MissingDataAlert from './MissingDataAlert.vue';
import DualGiftingTimeline from './DualGiftingTimeline.vue';
import LifeCoverRecommendations from './LifeCoverRecommendations.vue';
import IHTCalculationTable from './IHTCalculationTable.vue';
import EstateLifeEventsImpact from './EstateLifeEventsImpact.vue';
import LetterEstateWarnings from './LetterEstateWarnings.vue';
import estateService from '../../services/estateService';
import userProfileService from '../../services/userProfileService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { IHT_NIL_RATE_BAND, IHT_STANDARD_RATE, IHT_REDUCED_RATE, ANNUAL_GIFT_EXEMPTION } from '@/constants/taxConfig';

import logger from '@/utils/logger';
export default {
  name: 'IHTPlanning',

  props: {
    tableOnly: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['switch-tab', 'will-updated'],

  mixins: [currencyMixin],

  components: {
    SpouseExemptionNotice,
    MissingDataAlert,
    DualGiftingTimeline,
    LifeCoverRecommendations,
    IHTCalculationTable,
    EstateLifeEventsImpact,
    LetterEstateWarnings,
  },

  data() {
    return {
      annualGiftExemption: ANNUAL_GIFT_EXEMPTION,
      ihtData: null,
      secondDeathData: null,
      projection: null,
      cashProjectionBreakdown: null,
      userGender: 'male',
      isMarried: false,
      hasSpouse: false,
      showSpouseExemptionNotice: false,
      loading: false,
      error: null,
      showMinus5Years: false,
      showPlus5Years: false,
      showCashProjectionTable: true,
      expandedAllowances: false,
      charitableBequest: null,
      savingCharitableBequest: false,
    };
  },

  computed: {
    ...mapState('estate', ['analysis', 'gifts', 'lifeEvents', 'lifeEventImpact', 'lpas']),
    ...mapGetters('estate', ['netWorthValue', 'ihtLiability', 'ihtExemptAssets']),
    ...mapGetters('auth', ['currentUser']),

    hasSpouseLinked() {
      return this.hasSpouse;
    },

    lpaRegisteredCount() {
      return (this.lpas || []).filter(l => l.status === 'registered').length;
    },

    lpaDraftCount() {
      return (this.lpas || []).filter(l => l.status === 'draft' || l.status === 'completed').length;
    },

    lpasByType() {
      const types = { property_financial: 'Property & Financial', health_welfare: 'Health & Welfare' };
      const statuses = { registered: 'Registered', draft: 'Draft', completed: 'Completed' };
      return (this.lpas || []).map(lpa => ({
        type: lpa.lpa_type,
        label: types[lpa.lpa_type] || lpa.lpa_type,
        status: lpa.status,
        statusLabel: statuses[lpa.status] || lpa.status,
      }));
    },

    formattedIHTLiability() {
      return this.formatCurrency(this.ihtData?.iht_liability || 0);
    },

    // Cash projection computed properties
    preRetirementSurplus() {
      if (!this.cashProjectionBreakdown) return 0;
      return this.cashProjectionBreakdown.pre_retirement_income - this.cashProjectionBreakdown.pre_retirement_expenses;
    },

    retirementSurplus() {
      if (!this.cashProjectionBreakdown) return 0;
      const income = this.cashProjectionBreakdown.retirement_income +
        this.cashProjectionBreakdown.state_pension_user +
        this.cashProjectionBreakdown.state_pension_spouse;
      return income - this.cashProjectionBreakdown.retirement_expenses;
    },

    hasGifts() {
      return this.ihtData?.gifting_details &&
             (this.ihtData.gifting_details.pet_liability?.gift_count > 0 ||
              this.ihtData.gifting_details.clt_liability?.clt_count > 0);
    },

    valueColumnCount() {
      return 2 + (this.showMinus5Years ? 1 : 0) + (this.showPlus5Years ? 1 : 0);
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

    // Life events with IHT impact calculations
    estateLifeEvents() {
      return this.lifeEvents || [];
    },

    estateLifeEventsWithIHT() {
      if (!this.ihtData || !this.estateLifeEvents.length) return [];

      const netEstate = this.ihtData.net_estate_value || 0;
      const totalAllowances = (this.ihtData.nil_rate_band || IHT_NIL_RATE_BAND) + (this.ihtData.rnrb || 0);
      const ihtRate = IHT_STANDARD_RATE;
      const currentIHT = Math.max(0, (netEstate - totalAllowances) * ihtRate);

      return this.estateLifeEvents.map(event => {
        const amount = parseFloat(event.amount) || 0;
        const isIncome = event.impact_type === 'income';
        const estateAfter = isIncome ? netEstate + amount : netEstate - amount;
        const taxableAfter = Math.max(0, estateAfter - totalAllowances);
        const ihtAfter = taxableAfter * ihtRate;
        const ihtChange = ihtAfter - currentIHT;

        return {
          ...event,
          projected_iht_change: Math.round(ihtChange * 100) / 100,
          projected_iht_after_event: Math.round(ihtAfter * 100) / 100,
        };
      });
    },

    estateLifeEventsSummary() {
      if (!this.lifeEventImpact) return null;
      return {
        total_incoming: this.lifeEventImpact.upcoming_income || 0,
        total_outgoing: this.lifeEventImpact.upcoming_expense || 0,
        net_estate_impact: this.lifeEventImpact.net_impact || 0,
      };
    },

    estateReviewTriggers() {
      if (!this.estateLifeEventsWithIHT.length) return [];

      const triggers = [];
      this.estateLifeEventsWithIHT.forEach(event => {
        const amount = parseFloat(event.amount) || 0;
        const isIncome = event.impact_type === 'income';

        if (isIncome && amount >= 50000) {
          triggers.push({
            event_name: event.event_name,
            reason: 'Large incoming amount of ' + this.formatCurrency(amount) + ' will increase your taxable estate',
            recommendation: event.projected_iht_change > 0
              ? 'Consider a gifting strategy to mitigate the additional ' + this.formatCurrency(Math.abs(event.projected_iht_change)) + ' Inheritance Tax liability'
              : 'Review your estate plan to ensure the additional funds are efficiently allocated',
            priority: event.projected_iht_change > 10000 ? 'high' : 'medium',
          });
        } else if (!isIncome && event.event_type === 'gift_given' && amount >= ANNUAL_GIFT_EXEMPTION) {
          triggers.push({
            event_name: event.event_name,
            reason: 'Planned gift of ' + this.formatCurrency(amount) + ' is a Potentially Exempt Transfer',
            recommendation: 'Ensure this gift is recorded for Inheritance Tax purposes. It will become exempt after 7 years.',
            priority: 'medium',
          });
        }
      });
      return triggers;
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

    charitableBequestSavings() {
      // Calculate potential IHT savings if 10%+ is left to charity (rate drops from 40% to 36%)
      const taxableEstate = this.ihtData?.taxable_estate || 0;
      const currentIHT = taxableEstate * IHT_STANDARD_RATE;
      const reducedIHT = taxableEstate * IHT_REDUCED_RATE;
      return currentIHT - reducedIHT;
    },

    // Charitable bequest calculations for IHT table
    // Baseline = Net Estate - NRB - other exemptions (NOT including RNRB)
    charitableBaseline() {
      const netEstate = this.ihtData?.net_estate_value || 0;
      const nrb = this.ihtData?.nrb_available || IHT_NIL_RATE_BAND;
      // Baseline excludes RNRB - only NRB and other exemptions (like spouse exemption)
      return Math.max(0, netEstate - nrb);
    },

    charitableDonationAmount() {
      // Minimum 10% of baseline required for reduced 36% rate
      return this.charitableBaseline * 0.10;
    },

    effectiveIHTRateLabel() {
      return this.charitableBequest ? '36%' : '40%';
    },

    // Charitable donation for projected values
    // Baseline = Net Estate - NRB (excluding RNRB)
    charitableDonationProjected() {
      const netEstate = this.projection?.at_death?.net_estate || this.secondDeathData?.second_death_analysis?.iht_calculation?.net_estate_value || 0;
      const nrb = this.ihtData?.nrb_available || IHT_NIL_RATE_BAND;
      const baseline = Math.max(0, netEstate - nrb);
      return baseline * 0.10;
    },

    // Estate after NRB (baseline for charitable bequest) - for non-married users
    estateAfterNRB() {
      const netEstate = this.ihtData?.net_estate_value || 0;
      const nrb = this.ihtData?.nrb_available || IHT_NIL_RATE_BAND;
      return Math.max(0, netEstate - nrb);
    },

    estateAfterNRBProjected() {
      const netEstate = this.projection?.at_death?.net_estate || 0;
      const nrb = this.ihtData?.nrb_available || IHT_NIL_RATE_BAND;
      return Math.max(0, netEstate - nrb);
    },

    estateAfterNRBMinus5() {
      const netEstate = this.projectionMinus5?.net_estate || 0;
      const nrb = this.ihtData?.nrb_available || IHT_NIL_RATE_BAND;
      return Math.max(0, netEstate - nrb);
    },

    estateAfterNRBPlus5() {
      const netEstate = this.projectionPlus5?.net_estate || 0;
      const nrb = this.ihtData?.nrb_available || IHT_NIL_RATE_BAND;
      return Math.max(0, netEstate - nrb);
    },

    // Estate after NRB for married users (second death scenario)
    secondDeathEstateAfterNRB() {
      const netEstate = this.secondDeathData?.second_death_analysis?.current_iht_calculation?.net_estate_value || 0;
      const totalNRB = (this.secondDeathData?.second_death_analysis?.current_iht_calculation?.nrb || IHT_NIL_RATE_BAND) +
                       (this.secondDeathData?.second_death_analysis?.current_iht_calculation?.nrb_from_spouse || IHT_NIL_RATE_BAND);
      return Math.max(0, netEstate - totalNRB);
    },

    secondDeathEstateAfterNRBProjected() {
      const totalNRB = (this.secondDeathData?.second_death_analysis?.iht_calculation?.nrb || IHT_NIL_RATE_BAND) +
                       (this.secondDeathData?.second_death_analysis?.iht_calculation?.nrb_from_spouse || IHT_NIL_RATE_BAND);
      return Math.max(0, this.netEstateProjected - totalNRB);
    },

    secondDeathEstateAfterNRBMinus5() {
      const netEstate = this.secondDeathProjectionMinus5?.net_estate || 0;
      const totalNRB = (this.secondDeathData?.second_death_analysis?.current_iht_calculation?.nrb || IHT_NIL_RATE_BAND) +
                       (this.secondDeathData?.second_death_analysis?.current_iht_calculation?.nrb_from_spouse || IHT_NIL_RATE_BAND);
      return Math.max(0, netEstate - totalNRB);
    },

    secondDeathEstateAfterNRBPlus5() {
      const netEstate = this.secondDeathProjectionPlus5?.net_estate || 0;
      const totalNRB = (this.secondDeathData?.second_death_analysis?.iht_calculation?.nrb || IHT_NIL_RATE_BAND) +
                       (this.secondDeathData?.second_death_analysis?.iht_calculation?.nrb_from_spouse || IHT_NIL_RATE_BAND);
      return Math.max(0, netEstate - totalNRB);
    },

    // Charitable donation amounts for second death scenario
    charitableDonationSecondDeath() {
      return this.secondDeathEstateAfterNRB * 0.10;
    },

    charitableDonationSecondDeathProjected() {
      return this.secondDeathEstateAfterNRBProjected * 0.10;
    },

    charitableDonationSecondDeathMinus5() {
      return this.secondDeathEstateAfterNRBMinus5 * 0.10;
    },

    charitableDonationSecondDeathPlus5() {
      return this.secondDeathEstateAfterNRBPlus5 * 0.10;
    },

    // Projected subtotals for second death breakdown
    userAssetsProjectedTotal() {
      if (!this.secondDeathData?.assets_breakdown?.user?.assets) return 0;
      const assets = this.secondDeathData.assets_breakdown.user.assets;
      let total = 0;

      // Sum all asset types (use ?? to handle 0 as valid projected value)
      Object.keys(assets).forEach(assetType => {
        if (Array.isArray(assets[assetType])) {
          assets[assetType].forEach(asset => {
            total += (asset.projected_value ?? asset.value ?? 0);
          });
        }
      });

      return total;
    },

    spouseAssetsProjectedTotal() {
      if (!this.secondDeathData?.assets_breakdown?.spouse?.assets) return 0;
      const assets = this.secondDeathData.assets_breakdown.spouse.assets;
      let total = 0;

      // Sum all asset types (use ?? to handle 0 as valid projected value)
      Object.keys(assets).forEach(assetType => {
        if (Array.isArray(assets[assetType])) {
          assets[assetType].forEach(asset => {
            total += (asset.projected_value ?? asset.value ?? 0);
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
      const totalNRB = this.secondDeathData?.second_death_analysis?.iht_calculation?.total_nrb || IHT_NIL_RATE_BAND * 2;
      const rnrb = this.secondDeathData?.second_death_analysis?.iht_calculation?.rnrb || 0;
      return Math.max(0, this.netEstateProjected - totalNRB - rnrb);
    },

    // IHT Liability projected (40% of Taxable Estate)
    ihtLiabilityProjected() {
      return this.taxableEstateProjected * IHT_STANDARD_RATE;
    },

    // Growth rate for projections (4.7% annual)
    growthRate() {
      return 0.047;
    },

    // Base years to death from projection data
    baseYearsToDeath() {
      return this.projection?.at_death?.years_to_death || 0;
    },

    // Ages for each projection column
    projectedAgeMinus5() {
      const baseAge = this.projection?.at_death?.estimated_age_at_death || 0;
      return Math.max(baseAge - 5, this.getCurrentAge());
    },

    projectedAgePlus5() {
      const baseAge = this.projection?.at_death?.estimated_age_at_death || 0;
      return baseAge + 5;
    },

    // Years to each projection point
    yearsToDeath() {
      return this.baseYearsToDeath;
    },

    yearsToDeathMinus5() {
      return Math.max(0, this.baseYearsToDeath - 5);
    },

    yearsToDeathPlus5() {
      return this.baseYearsToDeath + 5;
    },

    // Projection for -5 years from estimated death
    projectionMinus5() {
      if (!this.projection?.now) return null;

      const years = this.yearsToDeathMinus5;
      const currentNetEstate = this.projection.now.net_estate || 0;
      const currentAssets = this.projection.now.assets || 0;
      const currentLiabilities = this.projection.now.liabilities || 0;
      const totalAllowance = (this.ihtData?.nrb_available || IHT_NIL_RATE_BAND) + (this.ihtData?.rnrb_available || 0);

      // Calculate projected values using compound growth
      const projectedAssets = currentAssets * Math.pow(1 + this.growthRate, years);
      const projectedLiabilities = currentLiabilities; // Liabilities stay constant (conservative)
      const projectedNetEstate = projectedAssets - projectedLiabilities;
      const projectedTaxableEstate = Math.max(0, projectedNetEstate - totalAllowance);
      const projectedIHTLiability = projectedTaxableEstate * IHT_STANDARD_RATE;

      return {
        estimated_age_at_death: this.projectedAgeMinus5,
        years_to_death: years,
        net_estate: projectedNetEstate,
        assets: projectedAssets,
        liabilities: projectedLiabilities,
        taxable_estate: projectedTaxableEstate,
        iht_liability: projectedIHTLiability,
      };
    },

    // Projection for +5 years from estimated death
    projectionPlus5() {
      if (!this.projection?.now) return null;

      const years = this.yearsToDeathPlus5;
      const currentNetEstate = this.projection.now.net_estate || 0;
      const currentAssets = this.projection.now.assets || 0;
      const currentLiabilities = this.projection.now.liabilities || 0;
      const totalAllowance = (this.ihtData?.nrb_available || IHT_NIL_RATE_BAND) + (this.ihtData?.rnrb_available || 0);

      // Calculate projected values using compound growth
      const projectedAssets = currentAssets * Math.pow(1 + this.growthRate, years);
      const projectedLiabilities = currentLiabilities; // Liabilities stay constant (conservative)
      const projectedNetEstate = projectedAssets - projectedLiabilities;
      const projectedTaxableEstate = Math.max(0, projectedNetEstate - totalAllowance);
      const projectedIHTLiability = projectedTaxableEstate * IHT_STANDARD_RATE;

      return {
        estimated_age_at_death: this.projectedAgePlus5,
        years_to_death: years,
        net_estate: projectedNetEstate,
        assets: projectedAssets,
        liabilities: projectedLiabilities,
        taxable_estate: projectedTaxableEstate,
        iht_liability: projectedIHTLiability,
      };
    },

    // Second Death Projection for -5 years from estimated death (married users)
    secondDeathProjectionMinus5() {
      if (!this.secondDeathData?.second_death_analysis?.current_iht_calculation) {
        return { net_estate: 0, taxable_estate: 0, iht_liability: 0 };
      }

      const years = this.yearsToDeathMinus5;
      const currentGrossAssets = this.secondDeathData.second_death_analysis.current_iht_calculation.gross_estate_value || 0;
      const currentLiabilities = this.secondDeathData.second_death_analysis.current_iht_calculation.liabilities || 0;

      // Get total allowance for second death (includes spouse's transferred allowances)
      const nrb = this.secondDeathData.second_death_analysis.iht_calculation?.nrb || IHT_NIL_RATE_BAND;
      const nrbFromSpouse = this.secondDeathData.second_death_analysis.iht_calculation?.nrb_from_spouse || IHT_NIL_RATE_BAND;
      const rnrb = this.secondDeathData.second_death_analysis.iht_calculation?.rnrb_individual || 0;
      const rnrbFromSpouse = this.secondDeathData.second_death_analysis.iht_calculation?.rnrb_from_spouse || 0;
      const totalAllowance = nrb + nrbFromSpouse + rnrb + rnrbFromSpouse;

      // Calculate projected values using compound growth
      const projectedAssets = currentGrossAssets * Math.pow(1 + this.growthRate, years);
      const projectedLiabilities = currentLiabilities; // Liabilities stay constant (conservative)
      const projectedNetEstate = projectedAssets - projectedLiabilities;
      const projectedTaxableEstate = Math.max(0, projectedNetEstate - totalAllowance);
      const projectedIHTLiability = projectedTaxableEstate * IHT_STANDARD_RATE;

      return {
        net_estate: projectedNetEstate,
        taxable_estate: projectedTaxableEstate,
        iht_liability: projectedIHTLiability,
      };
    },

    // Second Death Projection for +5 years from estimated death (married users)
    secondDeathProjectionPlus5() {
      if (!this.secondDeathData?.second_death_analysis?.current_iht_calculation) {
        return { net_estate: 0, taxable_estate: 0, iht_liability: 0 };
      }

      const years = this.yearsToDeathPlus5;
      const currentGrossAssets = this.secondDeathData.second_death_analysis.current_iht_calculation.gross_estate_value || 0;
      const currentLiabilities = this.secondDeathData.second_death_analysis.current_iht_calculation.liabilities || 0;

      // Get total allowance for second death (includes spouse's transferred allowances)
      const nrb = this.secondDeathData.second_death_analysis.iht_calculation?.nrb || IHT_NIL_RATE_BAND;
      const nrbFromSpouse = this.secondDeathData.second_death_analysis.iht_calculation?.nrb_from_spouse || IHT_NIL_RATE_BAND;
      const rnrb = this.secondDeathData.second_death_analysis.iht_calculation?.rnrb_individual || 0;
      const rnrbFromSpouse = this.secondDeathData.second_death_analysis.iht_calculation?.rnrb_from_spouse || 0;
      const totalAllowance = nrb + nrbFromSpouse + rnrb + rnrbFromSpouse;

      // Calculate projected values using compound growth
      const projectedAssets = currentGrossAssets * Math.pow(1 + this.growthRate, years);
      const projectedLiabilities = currentLiabilities; // Liabilities stay constant (conservative)
      const projectedNetEstate = projectedAssets - projectedLiabilities;
      const projectedTaxableEstate = Math.max(0, projectedNetEstate - totalAllowance);
      const projectedIHTLiability = projectedTaxableEstate * IHT_STANDARD_RATE;

      return {
        net_estate: projectedNetEstate,
        taxable_estate: projectedTaxableEstate,
        iht_liability: projectedIHTLiability,
      };
    },

    // Helper to calculate projected asset value for a specific number of years
    calculateProjectedValue() {
      return (currentValue, years) => {
        return currentValue * Math.pow(1 + this.growthRate, years);
      };
    },

    // ========================================
    // Normalized data for IHTCalculationTable
    // ========================================

    // Normalized table data for married users WITH spouse link (second death scenario)
    secondDeathTableProps() {
      if (!this.secondDeathData?.second_death_analysis) return null;

      const analysis = this.secondDeathData.second_death_analysis;
      const currentCalc = analysis.current_iht_calculation || {};
      const projectedCalc = analysis.iht_calculation || {};

      return {
        assetsBreakdown: this.secondDeathData.assets_breakdown || {},
        liabilitiesBreakdown: this.secondDeathData.liabilities_breakdown || {},
        totals: {
          grossAssets: {
            now: currentCalc.gross_estate_value || 0,
            minus5: this.getProjectedValueMinus5(currentCalc.gross_estate_value || 0),
            projected: this.totalGrossAssetsProjected,
            plus5: this.getProjectedValuePlus5(currentCalc.gross_estate_value || 0),
          },
          liabilities: {
            now: currentCalc.liabilities || 0,
            minus5: currentCalc.liabilities || 0,
            projected: this.totalLiabilitiesProjected,
            plus5: this.totalLiabilitiesProjected,
          },
          netEstate: {
            now: currentCalc.net_estate_value || 0,
            minus5: this.secondDeathProjectionMinus5.net_estate,
            projected: this.netEstateProjected,
            plus5: this.secondDeathProjectionPlus5.net_estate,
          },
        },
        allowances: {
          nrb: currentCalc.nrb || IHT_NIL_RATE_BAND,
          nrbFromSpouse: currentCalc.nrb_from_spouse || IHT_NIL_RATE_BAND,
          totalNrb: (currentCalc.nrb || IHT_NIL_RATE_BAND) + (currentCalc.nrb_from_spouse || IHT_NIL_RATE_BAND),
          rnrbIndividual: currentCalc.rnrb_individual || 0,
          rnrbFromSpouse: currentCalc.rnrb_from_spouse || 0,
          totalRnrb: (currentCalc.rnrb_individual || 0) + (currentCalc.rnrb_from_spouse || 0),
          rnrbEligible: projectedCalc.rnrb_eligible || false,
          rnrbTapered: currentCalc.rnrb_tapered || false,
          rnrbTaperThreshold: currentCalc.rnrb_taper_threshold || 2000000,
          rnrbTaperAmount: currentCalc.rnrb_taper_amount || 0,
          showSeparateSpouseAllowances: true,
        },
        estateAfterNRB: {
          now: this.secondDeathEstateAfterNRB,
          minus5: this.secondDeathEstateAfterNRBMinus5,
          projected: this.secondDeathEstateAfterNRBProjected,
          plus5: this.secondDeathEstateAfterNRBPlus5,
        },
        taxableEstate: {
          now: this.charitableBequest
            ? Math.max(0, (currentCalc.taxable_estate || 0) - this.charitableDonationSecondDeath)
            : (currentCalc.taxable_estate || 0),
          minus5: this.charitableBequest
            ? Math.max(0, this.secondDeathProjectionMinus5.taxable_estate - this.charitableDonationSecondDeathMinus5)
            : this.secondDeathProjectionMinus5.taxable_estate,
          projected: this.charitableBequest
            ? Math.max(0, this.taxableEstateProjected - this.charitableDonationSecondDeathProjected)
            : this.taxableEstateProjected,
          plus5: this.charitableBequest
            ? Math.max(0, this.secondDeathProjectionPlus5.taxable_estate - this.charitableDonationSecondDeathPlus5)
            : this.secondDeathProjectionPlus5.taxable_estate,
        },
        ihtLiability: {
          now: this.charitableBequest
            ? Math.max(0, (currentCalc.taxable_estate || 0) - this.charitableDonationSecondDeath) * IHT_REDUCED_RATE
            : (currentCalc.iht_liability || 0),
          minus5: this.charitableBequest
            ? Math.max(0, this.secondDeathProjectionMinus5.taxable_estate - this.charitableDonationSecondDeathMinus5) * IHT_REDUCED_RATE
            : this.secondDeathProjectionMinus5.iht_liability,
          projected: this.charitableBequest
            ? Math.max(0, this.taxableEstateProjected - this.charitableDonationSecondDeathProjected) * IHT_REDUCED_RATE
            : this.ihtLiabilityProjected,
          plus5: this.charitableBequest
            ? Math.max(0, this.secondDeathProjectionPlus5.taxable_estate - this.charitableDonationSecondDeathPlus5) * IHT_REDUCED_RATE
            : this.secondDeathProjectionPlus5.iht_liability,
        },
        charitableDonation: {
          now: this.charitableDonationSecondDeath,
          minus5: this.charitableDonationSecondDeathMinus5,
          projected: this.charitableDonationSecondDeathProjected,
          plus5: this.charitableDonationSecondDeathPlus5,
        },
        showSpouse: this.secondDeathData.data_sharing_enabled && !!this.secondDeathData.assets_breakdown?.spouse,
        estimatedAge: analysis.second_death?.estimated_age_at_death || 0,
        projectionMinus5Age: this.projectionMinus5?.estimated_age_at_death || 0,
        projectionPlus5Age: this.projectionPlus5?.estimated_age_at_death || 0,
        firstColumnHeader: 'Line Item',
      };
    },

    // Normalized table data for non-married OR married without spouse link (standard scenario)
    standardTableProps() {
      if (!this.ihtData || !this.secondDeathData?.assets_breakdown) return null;

      return {
        assetsBreakdown: this.secondDeathData.assets_breakdown || {},
        liabilitiesBreakdown: this.secondDeathData.liabilities_breakdown || {},
        totals: {
          grossAssets: {
            now: this.secondDeathData.calculation?.total_gross_assets || 0,
            minus5: this.getProjectedValueMinus5(this.secondDeathData.calculation?.total_gross_assets || 0),
            projected: this.secondDeathData.calculation?.projected_gross_assets || 0,
            plus5: this.getProjectedValuePlus5(this.secondDeathData.calculation?.total_gross_assets || 0),
          },
          liabilities: {
            now: this.secondDeathData.calculation?.total_liabilities || 0,
            minus5: this.secondDeathData.calculation?.total_liabilities || 0,
            projected: this.secondDeathData.calculation?.projected_liabilities || 0,
            plus5: this.secondDeathData.calculation?.projected_liabilities || 0,
          },
          netEstate: {
            now: this.ihtData?.net_estate_value || 0,
            minus5: this.projectionMinus5?.net_estate || 0,
            projected: this.projection?.at_death?.net_estate || 0,
            plus5: this.projectionPlus5?.net_estate || 0,
          },
        },
        allowances: {
          nrb: this.ihtData?.nrb_individual || IHT_NIL_RATE_BAND,
          nrbFromSpouse: this.ihtData?.nrb_transferred || 0,
          totalNrb: this.ihtData?.nrb_available || IHT_NIL_RATE_BAND,
          rnrbIndividual: this.ihtData?.rnrb_individual || 0,
          rnrbFromSpouse: this.ihtData?.rnrb_transferred || 0,
          totalRnrb: this.ihtData?.rnrb_available || 0,
          rnrbEligible: (this.ihtData?.rnrb_available || 0) > 0,
          rnrbTapered: false,
          rnrbTaperThreshold: 2000000,
          rnrbTaperAmount: 0,
          // Show breakdown for widows with transferred allowances
          showSeparateSpouseAllowances: (this.ihtData?.is_widowed && (this.ihtData?.nrb_transferred > 0 || this.ihtData?.rnrb_transferred > 0)) || false,
        },
        estateAfterNRB: {
          now: this.estateAfterNRB,
          minus5: this.estateAfterNRBMinus5,
          projected: this.estateAfterNRBProjected,
          plus5: this.estateAfterNRBPlus5,
        },
        taxableEstate: {
          now: this.charitableBequest
            ? Math.max(0, (this.ihtData?.taxable_estate || 0) - this.charitableDonationAmount)
            : (this.ihtData?.taxable_estate || 0),
          minus5: this.charitableBequest
            ? Math.max(0, (this.projectionMinus5?.taxable_estate || 0) - this.charitableDonationAmount)
            : (this.projectionMinus5?.taxable_estate || 0),
          projected: this.charitableBequest
            ? Math.max(0, (this.projection?.at_death?.taxable_estate || 0) - this.charitableDonationProjected)
            : (this.projection?.at_death?.taxable_estate || 0),
          plus5: this.charitableBequest
            ? Math.max(0, (this.projectionPlus5?.taxable_estate || 0) - this.charitableDonationProjected)
            : (this.projectionPlus5?.taxable_estate || 0),
        },
        ihtLiability: {
          now: this.charitableBequest
            ? Math.max(0, (this.ihtData?.taxable_estate || 0) - this.charitableDonationAmount) * IHT_REDUCED_RATE
            : (this.ihtData?.estate_iht_liability || 0),
          minus5: this.charitableBequest
            ? Math.max(0, (this.projectionMinus5?.taxable_estate || 0) - this.charitableDonationAmount) * IHT_REDUCED_RATE
            : (this.projectionMinus5?.iht_liability || 0),
          projected: this.charitableBequest
            ? Math.max(0, (this.projection?.at_death?.taxable_estate || 0) - this.charitableDonationProjected) * IHT_REDUCED_RATE
            : (this.projection?.at_death?.iht_liability || 0),
          plus5: this.charitableBequest
            ? Math.max(0, (this.projectionPlus5?.taxable_estate || 0) - this.charitableDonationProjected) * IHT_REDUCED_RATE
            : (this.projectionPlus5?.iht_liability || 0),
        },
        charitableDonation: {
          now: this.charitableDonationAmount,
          minus5: this.charitableDonationAmount, // Same as now for this scenario
          projected: this.charitableDonationProjected,
          plus5: this.charitableDonationProjected, // Same as projected for this scenario
        },
        showSpouse: !!this.secondDeathData?.assets_breakdown?.spouse,
        estimatedAge: this.projection?.at_death?.estimated_age_at_death || 0,
        projectionMinus5Age: this.projectionMinus5?.estimated_age_at_death || 0,
        projectionPlus5Age: this.projectionPlus5?.estimated_age_at_death || 0,
        firstColumnHeader: 'Asset / Liability',
      };
    },
  },

  mounted() {
    this.checkUserMaritalStatus();
    this.loadCharitableBequest();
    this.loadIHTCalculation();
    this.$store.dispatch('estate/fetchLpas').catch(() => {});
  },

  watch: {
    '$route'() {
      // Reload when navigating back to this tab
      this.loadIHTCalculation();
    },
  },

  methods: {
    ...mapActions('estate', ['calculateIHT', 'calculateIHTPlanning']),

    navigateToLetter() {
      this.$router.push({ name: 'ValuableInfo' });
    },

    toggleAllowances() {
      this.expandedAllowances = !this.expandedAllowances;
    },

    checkUserMaritalStatus() {
      const user = this.currentUser;
      if (user) {
        this.isMarried = user.marital_status === 'married';
        // Widowed and divorced users should not see spouse options
        const excludedStatuses = ['widowed', 'divorced'];
        this.hasSpouse = user.spouse_id !== null && !excludedStatuses.includes(user.marital_status);
        this.userGender = user.gender || 'male';
      }
    },

    getCurrentAge() {
      const user = this.currentUser;
      if (user?.date_of_birth) {
        const dob = new Date(user.date_of_birth);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
          age--;
        }
        return age;
      }
      return 40; // Default age if not available
    },

    navigateToGiftingTab() {
      // Emit event to parent EstateDashboard to switch to Gifting tab
      this.$emit('switch-tab', 'gifting');
    },

    navigateToWillTab() {
      this.$router.push('/estate/will-builder');
    },

    navigateToProtectionModule() {
      // Emit event to parent EstateDashboard to switch to Life Policy Strategy tab
      this.$emit('switch-tab', 'life-policy');
    },

    navigateToTrustsTab() {
      // Emit event to parent EstateDashboard to switch to Trusts tab
      this.$emit('switch-tab', 'trusts');
    },

    navigateToLpa() {
      this.$router.push('/estate/power-of-attorney');
    },

    loadCharitableBequest() {
      const user = this.currentUser;
      if (user) {
        this.charitableBequest = user.charitable_bequest;
      }
    },

    async toggleCharitableBequest(value) {
      this.savingCharitableBequest = true;
      try {
        await userProfileService.updateCharitableBequest(value);
        this.charitableBequest = value;
        // Refresh user data in store
        await this.$store.dispatch('auth/fetchUser');
        // Reload IHT calculation to reflect charitable bequest changes
        await this.loadIHTCalculation();
      } catch (error) {
        logger.error('Failed to update charitable bequest:', error);
        // Revert to previous value on error
        this.charitableBequest = !value;
      } finally {
        this.savingCharitableBequest = false;
      }
    },

    async loadIHTCalculation() {
      // Preview mode now uses real database users, so we use the API call
      // The old client-side computePreviewIHTData() is no longer needed
      this.loading = true;
      this.error = null;

      try {
        // Both married and single users now use the unified calculateIHT endpoint
        const response = await this.calculateIHTPlanning();

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
              nrb_individual: response.iht_summary.current.nrb_individual || response.iht_summary.current.nrb_available,
              nrb_transferred: response.iht_summary.current.nrb_transferred || 0,
              nrb: response.iht_summary.current.nrb_available, // Legacy alias
              nrb_message: response.iht_summary.current.nrb_message,
              rnrb_available: response.iht_summary.current.rnrb_available,
              rnrb_eligible: response.iht_summary.current.rnrb_available > 0, // Eligible if RNRB > 0
              rnrb_individual: response.iht_summary.current.rnrb_individual || 0,
              rnrb_transferred: response.iht_summary.current.rnrb_transferred || 0,
              rnrb_status: response.iht_summary.current.rnrb_status,
              is_widowed: response.iht_summary.is_widowed || false,
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

            // Store cash projection breakdown for methodology table
            this.cashProjectionBreakdown = response.cash_projection_breakdown || null;
          }
        }
      } catch (error) {
        logger.error('❌ Failed to load IHT calculation:', error);
        this.error = error.message || 'Failed to calculate Inheritance Tax liability';
      } finally {
        this.loading = false;
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

    // Calculate projected asset value at -5 years from life expectancy
    getProjectedValueMinus5(currentValue) {
      const years = this.yearsToDeathMinus5;
      return currentValue * Math.pow(1 + 0.047, years);
    },

    // Calculate projected asset value at +5 years from life expectancy
    getProjectedValuePlus5(currentValue) {
      const years = this.yearsToDeathPlus5;
      return currentValue * Math.pow(1 + 0.047, years);
    },
  },
};
</script>
