<template>
  <div class="gifting-strategy-tab">
    <!-- Back to Dashboard Link -->
    <button
      @click="$emit('switch-tab', 'iht')"
      class="inline-flex items-center text-sm text-violet-600 hover:text-violet-800 mb-4"
    >
      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Back to Estate Dashboard
    </button>
    <!-- Personalized Asset-Based Gifting Strategy Section -->
    <div v-if="personalizedStrategy" class="mb-8 bg-white rounded-lg p-6 border border-light-gray">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-4">
        <h2 class="text-xl sm:text-2xl font-bold text-horizon-500">Your Personalized Gifting Strategy</h2>
        <button
          @click="refreshPersonalizedStrategy"
          class="text-sm text-spring-500 hover:text-spring-700 flex items-center"
          :disabled="loadingPersonalizedStrategy"
        >
          <svg v-if="!loadingPersonalizedStrategy" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <div v-else class="animate-spin h-4 w-4 mr-1 border border-light-gray border-t-transparent rounded-full"></div>
          Refresh
        </button>
      </div>

      <p class="text-neutral-500 mb-6">
        Based on your specific assets and their liquidity, here's a tailored gifting strategy to reduce your Inheritance Tax liability.
      </p>

      <!-- Strategy Summary -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-raspberry-700 mb-1 font-medium">Current Inheritance Tax Liability</p>
          <p class="text-lg sm:text-xl lg:text-2xl font-bold text-raspberry-900">{{ formatCurrency(personalizedStrategy.summary.original_iht_liability) }}</p>
          <p class="text-xs text-raspberry-600">At projected death</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-violet-700 mb-1 font-medium">Annual Exemption</p>
          <p class="text-lg sm:text-xl lg:text-2xl font-bold text-violet-900">£{{ annualExemption.toLocaleString() }}/year</p>
          <p class="text-xs text-violet-600">Immediately exempt gifts</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-violet-500 mb-1 font-medium">Total to Gift</p>
          <p class="text-lg sm:text-xl lg:text-2xl font-bold text-violet-500">{{ formatCurrency(personalizedStrategy.summary.total_gifted) }}</p>
          <p class="text-xs text-violet-500">Via recommended strategies</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-light-gray">
          <p class="text-sm text-spring-700 mb-1 font-medium">Inheritance Tax Saved</p>
          <p class="text-lg sm:text-xl lg:text-2xl font-bold text-spring-900">{{ formatCurrency(personalizedStrategy.summary.total_iht_saved) }}</p>
          <p class="text-xs text-spring-600">{{ personalizedStrategy.summary.reduction_percentage }}% reduction</p>
        </div>
      </div>

      <!-- Asset-Based Strategies -->
      <div class="space-y-4">
        <h3 class="text-lg font-semibold text-horizon-500 mb-3">Your Asset-Based Strategies</h3>

        <div
          v-for="(strategy, index) in personalizedStrategy.strategies"
          :key="index"
          class="bg-white rounded-lg p-5 border border-light-gray hover:shadow-md transition-shadow"
        >
          <!-- Strategy Header -->
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-1 rounded text-xs font-medium bg-savannah-100 text-neutral-500">
                  Priority {{ strategy.priority }}
                </span>
                <span
                  class="px-2 py-1 rounded text-xs font-medium"
                  :class="getRiskLevelClass(strategy.risk_level)"
                >
                  {{ strategy.risk_level }} Risk
                </span>
                <span
                  v-if="strategy.category"
                  class="px-2 py-1 rounded text-xs font-medium bg-violet-500 text-white"
                >
                  {{ formatCategory(strategy.category) }}
                </span>
              </div>
              <h4 class="text-lg font-semibold text-horizon-500">{{ strategy.strategy_name }}</h4>
              <p class="text-sm text-neutral-500 mt-1">{{ strategy.description }}</p>
            </div>
            <div v-if="strategy.iht_saved > 0" class="text-right ml-4">
              <p class="text-sm text-neutral-500">Inheritance Tax Saved</p>
              <p class="text-lg sm:text-xl lg:text-2xl font-bold text-spring-600">{{ formatCurrency(strategy.iht_saved) }}</p>
            </div>
          </div>

          <!-- Strategy Details -->
          <div v-if="strategy.total_gifted > 0" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4 bg-eggshell-500 rounded p-3">
            <div v-if="strategy.annual_amount">
              <p class="text-xs text-neutral-500">Annual Amount</p>
              <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(strategy.annual_amount) }}</p>
            </div>
            <div v-if="strategy.total_gifted">
              <p class="text-xs text-neutral-500">Total to Gift</p>
              <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(strategy.total_gifted) }}</p>
            </div>
            <div v-if="strategy.years">
              <p class="text-xs text-neutral-500">Timeframe</p>
              <p class="text-lg font-bold text-horizon-500">{{ strategy.years }} years</p>
            </div>
          </div>

          <!-- Available Assets -->
          <div v-if="strategy.available_assets" class="mb-3 p-3 bg-white rounded border border-light-gray">
            <p class="text-xs font-medium text-violet-600 mb-1">Available Assets:</p>
            <p class="text-sm text-horizon-500">{{ strategy.available_assets }}</p>
          </div>

          <!-- Main Residence Info -->
          <div v-if="strategy.main_residence" class="mb-3 p-3 bg-white rounded border border-light-gray">
            <p class="text-xs font-medium text-violet-600 mb-1">Main Residence:</p>
            <p class="text-sm text-horizon-500 font-medium">{{ strategy.main_residence }}</p>
            <p class="text-sm text-neutral-500 mt-1">Value: {{ formatCurrency(strategy.current_value) }}</p>
            <p class="text-xs text-violet-600 mt-2 italic">{{ strategy.not_giftable_reason }}</p>
          </div>

          <!-- Gift Schedule (for PET strategies) -->
          <div v-if="strategy.gift_schedule && strategy.gift_schedule.length > 0" class="mb-3">
            <p class="text-sm font-medium text-horizon-500 mb-2">Gift Schedule:</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
              <div
                v-for="(gift, idx) in strategy.gift_schedule"
                :key="idx"
                class="p-2 bg-white rounded border border-light-gray"
              >
                <p class="text-xs text-violet-500">Year {{ gift.year }}</p>
                <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(gift.amount) }}</p>
                <p class="text-xs text-violet-500">Exempt: Year {{ gift.becomes_exempt }}</p>
              </div>
            </div>
          </div>

          <!-- Tax Considerations -->
          <div v-if="strategy.tax_considerations" class="mb-3 p-3 bg-white rounded border border-light-gray">
            <p class="text-xs font-medium text-violet-500 mb-2">Tax Considerations:</p>
            <div class="text-xs text-neutral-500 space-y-1">
              <p v-if="strategy.tax_considerations.cgt_rate">
                <span class="font-medium">Capital Gains Tax:</span> {{ strategy.tax_considerations.cgt_rate }}
              </p>
              <p v-if="strategy.tax_considerations.sdlt">
                <span class="font-medium">Stamp Duty:</span> {{ strategy.tax_considerations.sdlt }}
              </p>
              <p v-if="strategy.tax_considerations.iht_treatment">
                <span class="font-medium">Inheritance Tax:</span> {{ strategy.tax_considerations.iht_treatment }}
              </p>
            </div>
          </div>

          <!-- Implementation Steps -->
          <div class="border-t border-light-gray pt-3 mt-3">
            <p class="text-sm font-medium text-horizon-500 mb-2">Implementation Steps:</p>
            <ul class="space-y-1">
              <li
                v-for="(step, stepIdx) in strategy.implementation_steps"
                :key="stepIdx"
                class="flex items-start text-sm text-neutral-500"
              >
                <svg class="w-4 h-4 mr-2 mt-0.5 text-spring-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>{{ step }}</span>
              </li>
            </ul>
          </div>

          <!-- Alternative Strategies (for main residence) -->
          <div v-if="strategy.alternative_strategies" class="border-t border-light-gray pt-3 mt-3">
            <p class="text-sm font-medium text-horizon-500 mb-2">Alternative Strategies:</p>
            <ul class="space-y-1">
              <li
                v-for="(alt, altIdx) in strategy.alternative_strategies"
                :key="altIdx"
                class="flex items-start text-sm text-neutral-500"
              >
                <svg class="w-4 h-4 mr-2 mt-0.5 text-violet-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <span>{{ alt }}</span>
              </li>
            </ul>
          </div>

          <!-- Asset Details (expandable) -->
          <div v-if="strategy.asset_details && strategy.asset_details.length > 0" class="border-t border-light-gray pt-3 mt-3">
            <button
              @click="toggleAssetDetails(index)"
              class="w-full flex items-center justify-between text-sm font-medium text-horizon-500 hover:text-neutral-500"
            >
              <span>View Asset Details ({{ strategy.asset_details.length }} assets)</span>
              <svg
                class="w-5 h-5 transition-transform"
                :class="{ 'rotate-180': expandedAssetDetails[index] }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div v-if="expandedAssetDetails[index]" class="mt-3 space-y-2">
              <div
                v-for="(asset, assetIdx) in strategy.asset_details"
                :key="assetIdx"
                class="p-3 bg-eggshell-500 rounded border border-light-gray"
              >
                <div class="flex items-center justify-between mb-2">
                  <p class="font-medium text-horizon-500">{{ asset.name }}</p>
                  <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(asset.value) }}</p>
                </div>
                <p class="text-xs text-neutral-500 mb-2">Type: {{ formatAssetType(asset.type) }}</p>
                <div v-if="asset.gifting_considerations" class="text-xs text-neutral-500">
                  <p class="font-medium mb-1">Gifting Considerations:</p>
                  <ul class="list-disc list-inside space-y-0.5 pl-2">
                    <li v-for="(consideration, cIdx) in asset.gifting_considerations.slice(0, 3)" :key="cIdx">
                      {{ consideration }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Overall Summary -->
      <div class="mt-6 bg-white rounded-lg p-4 border border-light-gray">
        <h3 class="text-lg font-semibold text-horizon-500 mb-3">Overall Strategy Impact</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <p class="text-xs sm:text-sm text-neutral-500">Original Inheritance Tax Liability</p>
            <p class="text-base sm:text-lg font-bold text-raspberry-600">{{ formatCurrency(personalizedStrategy.summary.original_iht_liability) }}</p>
          </div>
          <div>
            <p class="text-xs sm:text-sm text-neutral-500">Total to Gift</p>
            <p class="text-base sm:text-lg font-bold text-violet-600">{{ formatCurrency(personalizedStrategy.summary.total_gifted) }}</p>
          </div>
          <div>
            <p class="text-xs sm:text-sm text-neutral-500">Total Inheritance Tax Saved</p>
            <p class="text-base sm:text-lg font-bold text-spring-600">{{ formatCurrency(personalizedStrategy.summary.total_iht_saved) }}</p>
          </div>
          <div>
            <p class="text-xs sm:text-sm text-neutral-500">Inheritance Tax Reduction</p>
            <p class="text-base sm:text-lg font-bold text-spring-500">{{ personalizedStrategy.summary.reduction_percentage }}%</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Error/Info Messages for Personalized Strategy -->
    <div v-if="personalizedStrategyError && !personalizedStrategy" class="mb-6 bg-violet-50 border border-violet-200 text-violet-800 px-4 py-3 rounded-lg">
      <div class="flex items-start">
        <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <div>
          <p class="font-medium">{{ personalizedStrategyError }}</p>
          <p v-if="requiresAssets" class="text-sm mt-1">
            Please add assets in the Estate Planning module to generate your personalized gifting strategy.
            <router-link to="/estate" class="underline font-medium">Go to Estate Planning</router-link>
          </p>
        </div>
      </div>
    </div>

    <!-- Divider -->
    <div class="border-t-2 border-light-gray my-8"></div>

    <!-- Actual Gifts Section Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-horizon-500 mb-2">Gifts Made (Actual)</h2>
      <p class="text-neutral-500">Track gifts you've actually made and monitor their Potentially Exempt Transfer status</p>
    </div>

    <!-- Gifting Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-lg p-6 border border-light-gray">
        <p class="text-sm text-violet-600 font-medium mb-2">Gifts Within 7 Years</p>
        <p class="text-3xl font-bold text-horizon-500">{{ giftsWithin7YearsCount }}</p>
      </div>
      <div class="bg-white rounded-lg p-6 border border-light-gray">
        <p class="text-sm text-violet-500 font-medium mb-2">Total Value</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedGiftsValue }}</p>
      </div>
      <div class="bg-white rounded-lg p-6 border border-light-gray">
        <p class="text-sm text-spring-600 font-medium mb-2">Annual Exemption Available</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedAnnualExemption }}</p>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="mb-4 bg-spring-50 border border-spring-200 text-spring-800 px-4 py-3 rounded-lg">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="mb-4 bg-raspberry-50 border border-raspberry-200 text-raspberry-800 px-4 py-3 rounded-lg">
      {{ errorMessage }}
    </div>

    <!-- HMRC 7-Year Rule & Taper Relief Info -->
    <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-violet-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-semibold text-violet-900">HM Revenue & Customs 7-Year Rule & Taper Relief</h3>
          <div class="mt-2 text-sm text-violet-800">
            <p class="mb-2">
              Potentially Exempt Transfers become completely exempt from Inheritance Tax if you survive for 7 years after making the gift.
            </p>
            <p class="font-medium mb-1">If death occurs within 7 years, taper relief applies:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
              <li><span class="font-semibold">Years 0-3:</span> 40% Inheritance Tax rate (no relief)</li>
              <li><span class="font-semibold">Years 3-4:</span> 32% Inheritance Tax rate (20% taper relief)</li>
              <li><span class="font-semibold">Years 4-5:</span> 24% Inheritance Tax rate (40% taper relief)</li>
              <li><span class="font-semibold">Years 5-6:</span> 16% Inheritance Tax rate (60% taper relief)</li>
              <li><span class="font-semibold">Years 6-7:</span> 8% Inheritance Tax rate (80% taper relief)</li>
              <li><span class="font-semibold">After 7 years:</span> 0% Inheritance Tax rate (100% relief - fully exempt)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Gift Button -->
    <div class="mb-6">
      <button
        v-preview-disabled="'add'"
        @click="openCreateGiftForm"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-raspberry-500 hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
      >
        <svg
          class="-ml-1 mr-2 h-5 w-5"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
            clip-rule="evenodd"
          />
        </svg>
        Record New Gift
      </button>
    </div>

    <!-- Gifts List -->
    <div class="bg-white rounded-lg border border-light-gray">
      <div class="px-6 py-4 border-b border-light-gray">
        <h3 class="text-lg font-semibold text-horizon-500">Gifts Made</h3>
      </div>
      <div v-if="gifts.length === 0" class="px-6 py-8 text-center text-neutral-500">
        No gifts recorded yet
      </div>
      <div v-else class="divide-y divide-light-gray">
        <div
          v-for="gift in sortedGifts"
          :key="gift.id"
          class="px-6 py-4 hover:bg-eggshell-500"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center">
                <p class="text-sm font-medium text-horizon-500">{{ gift.recipient }}</p>
                <span
                  :class="[
                    'ml-3 px-2 py-1 text-xs font-medium rounded-full',
                    getGiftStatusColour(gift),
                  ]"
                >
                  {{ getGiftStatus(gift) }}
                </span>
              </div>
              <div class="mt-1 flex items-center text-sm text-neutral-500">
                <span>{{ formatDate(gift.gift_date) }}</span>
                <span class="mx-2">•</span>
                <span>{{ formatCurrency(gift.gift_value) }}</span>
                <span class="mx-2">•</span>
                <span>{{ formatGiftType(gift.gift_type) }}</span>
              </div>

              <!-- Taper Relief Timeline (only for PETs within 7 years) -->
              <div v-if="shouldShowTaperRelief(gift)" class="mt-3">
                <div class="flex items-center justify-between text-xs text-neutral-500 mb-1">
                  <span>Taper Relief Timeline</span>
                  <span class="font-medium">{{ getTaperReliefPercentage(gift) }}% Inheritance Tax if death occurs now</span>
                </div>
                <div class="relative h-8 bg-savannah-100 rounded-lg overflow-hidden">
                  <!-- Progress bar showing years elapsed -->
                  <div
                    class="absolute inset-y-0 left-0 transition-all duration-300"
                    :style="{ width: getTimelineProgress(gift) + '%', backgroundColor: getTimelineColour(gift) }"
                  ></div>

                  <!-- Taper relief markers -->
                  <div class="absolute inset-0 flex">
                    <div v-for="year in 7" :key="year" class="flex-1 border-r border-horizon-300 last:border-r-0 flex items-center justify-center">
                      <span class="text-xs font-medium" :class="getYearLabelClass(gift, year)">
                        {{ getTaperReliefAtYear(year) }}%
                      </span>
                    </div>
                  </div>
                </div>
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>Gift date: {{ formatDate(gift.gift_date) }}</span>
                  <span>Inheritance Tax-free: {{ formatDate(getSevenYearDate(gift)) }}</span>
                </div>
              </div>
            </div>
            <div class="ml-4 flex-shrink-0">
              <button
                v-preview-disabled="'edit'"
                @click="editGift(gift)"
                class="text-violet-600 hover:text-violet-900 mr-3"
              >
                Edit
              </button>
              <button
                v-preview-disabled="'delete'"
                @click="handleDeleteGift(gift.id)"
                class="text-raspberry-600 hover:text-raspberry-900"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Gift Form Modal -->
    <div v-if="showGiftForm" class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <GiftForm
          :gift="currentGift"
          :mode="formMode"
          @save="handleSaveGift"
          @cancel="closeGiftForm"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import GiftForm from './GiftForm.vue';
import estateService from '@/services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { PRIMARY_COLORS, SUCCESS_COLORS, WARNING_COLORS } from '@/constants/designSystem';
import { ANNUAL_GIFT_EXEMPTION } from '@/constants/taxConfig';

import logger from '@/utils/logger';
export default {
  name: 'GiftingStrategy',

  emits: ['switch-tab'],

  mixins: [currencyMixin],

  components: {
    GiftForm,
  },

  data() {
    return {
      showGiftForm: false,
      currentGift: null,
      formMode: 'create',
      annualExemption: ANNUAL_GIFT_EXEMPTION,
      successMessage: '',
      errorMessage: '',
      messageTimeout: null,
      plannedStrategy: null,
      loadingStrategy: false,
      strategyError: null,
      requiresProfileUpdate: false,
      personalizedStrategy: null,
      loadingPersonalizedStrategy: false,
      personalizedStrategyError: null,
      requiresAssets: false,
      expandedAssetDetails: {},
    };
  },

  computed: {
    ...mapState('estate', ['gifts']),
    ...mapGetters('estate', ['giftsWithin7Years', 'giftsWithin7YearsValue']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    giftsWithin7YearsCount() {
      return this.giftsWithin7Years.length;
    },

    formattedGiftsValue() {
      return this.formatCurrency(this.giftsWithin7YearsValue);
    },

    formattedAnnualExemption() {
      return this.formatCurrency(this.annualExemption);
    },

    sortedGifts() {
      return [...this.gifts].sort((a, b) => new Date(b.gift_date) - new Date(a.gift_date));
    },

    petStrategy() {
      if (!this.plannedStrategy?.gifting_strategy?.strategies) {
        return null;
      }
      return this.plannedStrategy.gifting_strategy.strategies.find(
        s => s.strategy_name === 'Potentially Exempt Transfers (PETs)'
      );
    },

    otherStrategies() {
      if (!this.plannedStrategy?.gifting_strategy?.strategies) {
        return [];
      }
      return this.plannedStrategy.gifting_strategy.strategies.filter(
        s => s.strategy_name !== 'Potentially Exempt Transfers (PETs)' &&
             s.strategy_name !== 'Annual Exemption'
      );
    },
  },

  watch: {
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && fill.entityType === 'estate_gift') {
        if (fill.mode === 'edit' && fill.entityId) {
          const record = this.gifts.find(g => g.id === fill.entityId);
          if (record) {
            this.editGift(record);
          }
        } else {
          this.openCreateGiftForm();
        }
      }
    },
  },

  beforeUnmount() {
    if (this.messageTimeout) clearTimeout(this.messageTimeout);
  },

  mounted() {
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && fill.entityType === 'estate_gift' && fill.mode !== 'edit') {
      this.openCreateGiftForm();
    }

    // Preview users are real DB users - use normal API to fetch their data
    this.loadPlannedStrategy();
    this.loadPersonalizedStrategy();
  },

  methods: {
    ...mapActions('estate', ['createGift', 'updateGift', 'deleteGift']),

    async loadPlannedStrategy() {
      this.loadingStrategy = true;
      this.strategyError = null;
      this.requiresProfileUpdate = false;

      try {
        const response = await estateService.getPlannedGiftingStrategy();

        if (response.success) {
          this.plannedStrategy = response.data;
        } else {
          // API returned validation error in response body
          this.strategyError = response.message || 'Failed to load planned gifting strategy';
          this.requiresProfileUpdate = response.requires_profile_update || false;
          console.info('[GiftingStrategy] Validation error:', this.strategyError);
        }
      } catch (error) {
        if (error.response?.status === 422) {
          // Expected validation error - user needs to complete profile
          this.strategyError = error.response.data.message;
          this.requiresProfileUpdate = error.response.data.requires_profile_update || false;
          console.info('[GiftingStrategy] Profile incomplete:', this.strategyError);
        } else {
          // Unexpected error - log as error
          logger.error('[GiftingStrategy] Failed to load planned strategy:', error);
          this.strategyError = 'Unable to calculate gifting strategy. Please ensure your profile is complete.';
        }
      } finally {
        this.loadingStrategy = false;
      }
    },

    async refreshPlannedStrategy() {
      await this.loadPlannedStrategy();
    },

    async loadPersonalizedStrategy() {
      this.loadingPersonalizedStrategy = true;
      this.personalizedStrategyError = null;
      this.requiresAssets = false;

      try {
        const response = await estateService.getPersonalizedGiftingStrategy();

        if (response.success) {
          this.personalizedStrategy = response.data;
        } else {
          // API returned validation error in response body
          this.personalizedStrategyError = response.message || 'Failed to load personalized gifting strategy';
          this.requiresAssets = response.requires_assets || false;
          console.info('[GiftingStrategy] Validation error:', this.personalizedStrategyError);
        }
      } catch (error) {
        if (error.response?.status === 422) {
          // Expected validation error - user needs to add assets
          this.personalizedStrategyError = error.response.data.message;
          this.requiresAssets = error.response.data.requires_assets || false;
          console.info('[GiftingStrategy] Assets required:', this.personalizedStrategyError);
        } else {
          // Unexpected error - log as error
          logger.error('[GiftingStrategy] Failed to load personalized strategy:', error);
          this.personalizedStrategyError = 'Unable to calculate personalized strategy. Please ensure you have assets added.';
        }
      } finally {
        this.loadingPersonalizedStrategy = false;
      }
    },

    async refreshPersonalizedStrategy() {
      await this.loadPersonalizedStrategy();
    },

    getRiskLevelClass(riskLevel) {
      const riskLower = (riskLevel || '').toLowerCase();
      if (riskLower === 'low') return 'bg-spring-500 text-white';
      if (riskLower === 'medium') return 'bg-violet-500 text-white';
      if (riskLower === 'high') return 'bg-raspberry-500 text-white';
      return 'bg-eggshell-5000 text-white';
    },

    formatCategory(category) {
      const categories = {
        immediate_exemption: 'Immediate Exemption',
        liquid_assets: 'Liquid Assets',
        property: 'Property',
        main_residence: 'Main Residence',
        income: 'From Income',
      };
      return categories[category] || category;
    },

    formatAssetType(type) {
      const types = {
        property: 'Property',
        investment: 'Investment',
        pension: 'Pension',
        business: 'Business',
        other: 'Cash/Other',
      };
      return types[type] || type;
    },

    toggleAssetDetails(index) {
      this.$set(this.expandedAssetDetails, index, !this.expandedAssetDetails[index]);
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    formatGiftType(type) {
      const types = {
        pet: 'Potentially Exempt Transfer',
        clt: 'Chargeable Lifetime Transfer',
        exempt: 'Exempt',
        small_gift: 'Small Gift',
        annual_exemption: 'Annual Exemption',
      };
      return types[type] || type;
    },

    getGiftStatus(gift) {
      const giftDate = new Date(gift.gift_date);
      const sevenYearsLater = new Date(giftDate);
      sevenYearsLater.setFullYear(sevenYearsLater.getFullYear() + 7);
      const now = new Date();

      if (now >= sevenYearsLater) {
        return 'Survived 7 years - Inheritance Tax-free';
      }

      const yearsRemaining = Math.ceil((sevenYearsLater - now) / (365 * 24 * 60 * 60 * 1000));
      return `${yearsRemaining} ${yearsRemaining === 1 ? 'year' : 'years'} remaining`;
    },

    getGiftStatusColour(gift) {
      const giftDate = new Date(gift.gift_date);
      const sevenYearsLater = new Date(giftDate);
      sevenYearsLater.setFullYear(sevenYearsLater.getFullYear() + 7);
      const now = new Date();

      if (now >= sevenYearsLater) {
        return 'bg-spring-500 text-white';
      } else {
        return 'bg-violet-500 text-white';
      }
    },

    // Taper relief methods (HMRC rules)
    shouldShowTaperRelief(gift) {
      // Only show taper relief for PETs (Potentially Exempt Transfers)
      if (gift.gift_type !== 'pet') {
        return false;
      }

      const giftDate = new Date(gift.gift_date);
      const sevenYearsLater = new Date(giftDate);
      sevenYearsLater.setFullYear(sevenYearsLater.getFullYear() + 7);
      const now = new Date();

      // Only show if gift is still within 7 years
      return now < sevenYearsLater;
    },

    getTaperReliefAtYear(year) {
      // HMRC taper relief schedule:
      // Years 0-3: 40% Inheritance Tax rate (no relief)
      // Year 3-4: 32% (20% relief)
      // Year 4-5: 24% (40% relief)
      // Year 5-6: 16% (60% relief)
      // Year 6-7: 8% (80% relief)
      // Year 7+: 0% (100% relief - Inheritance Tax-free)

      if (year <= 3) return 40;
      if (year === 4) return 32;
      if (year === 5) return 24;
      if (year === 6) return 16;
      if (year === 7) return 8;
      return 0;
    },

    getTaperReliefPercentage(gift) {
      const giftDate = new Date(gift.gift_date);
      const now = new Date();

      // Calculate years elapsed (with decimal precision)
      const yearsElapsed = (now - giftDate) / (365.25 * 24 * 60 * 60 * 1000);

      // HMRC taper relief rules
      if (yearsElapsed < 3) return 40; // Full 40% Inheritance Tax rate
      if (yearsElapsed < 4) return 32; // 20% taper relief
      if (yearsElapsed < 5) return 24; // 40% taper relief
      if (yearsElapsed < 6) return 16; // 60% taper relief
      if (yearsElapsed < 7) return 8;  // 80% taper relief
      return 0; // 100% relief - Inheritance Tax-free
    },

    getTimelineProgress(gift) {
      const giftDate = new Date(gift.gift_date);
      const sevenYearsLater = new Date(giftDate);
      sevenYearsLater.setFullYear(sevenYearsLater.getFullYear() + 7);
      const now = new Date();

      const totalDuration = sevenYearsLater - giftDate;
      const elapsed = now - giftDate;

      return Math.min(100, Math.max(0, (elapsed / totalDuration) * 100));
    },

    getTimelineColour(gift) {
      const percentage = this.getTaperReliefPercentage(gift);

      if (percentage >= 32) return WARNING_COLORS[600]; // Warning - Amber (high Inheritance Tax rate)
      if (percentage >= 16) return PRIMARY_COLORS[600]; // Primary - Blue (moderate Inheritance Tax rate)
      return SUCCESS_COLORS[600]; // Success - Green (low Inheritance Tax rate)
    },

    getYearLabelClass(gift, year) {
      const giftDate = new Date(gift.gift_date);
      const now = new Date();
      const yearsElapsed = (now - giftDate) / (365.25 * 24 * 60 * 60 * 1000);

      // Green for years that have passed, red for years remaining
      if (yearsElapsed >= year) {
        return 'text-spring-600 font-bold'; // Passed - good
      }
      return 'text-raspberry-600 font-bold'; // Remaining - still at risk
    },

    getSevenYearDate(gift) {
      const giftDate = new Date(gift.gift_date);
      const sevenYearsLater = new Date(giftDate);
      sevenYearsLater.setFullYear(sevenYearsLater.getFullYear() + 7);
      return sevenYearsLater;
    },

    openCreateGiftForm() {
      this.currentGift = null;
      this.formMode = 'create';
      this.showGiftForm = true;
    },

    editGift(gift) {
      this.currentGift = gift;
      this.formMode = 'edit';
      this.showGiftForm = true;
    },

    closeGiftForm() {
      this.showGiftForm = false;
      this.currentGift = null;
      this.formMode = 'create';
      this.successMessage = '';
      this.errorMessage = '';
    },

    async handleSaveGift(giftData) {
      this.errorMessage = '';
      this.successMessage = '';

      try {
        if (this.formMode === 'edit') {
          await this.updateGift({
            id: giftData.id,
            giftData: giftData,
          });
          this.successMessage = 'Gift updated successfully';
        } else {
          await this.createGift(giftData);
          this.successMessage = 'Gift recorded successfully';
        }

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }

        // Close the form after successful save
        this.closeGiftForm();

        // Show success message briefly
        if (this.messageTimeout) clearTimeout(this.messageTimeout);
        this.messageTimeout = setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        logger.error('Failed to save gift:', error);
        this.errorMessage = error.response?.data?.message || error.message || 'Failed to save gift';
      }
    },

    async handleDeleteGift(id) {
      if (confirm('Are you sure you want to delete this gift record?')) {
        this.errorMessage = '';

        try {
          await this.deleteGift(id);
          this.successMessage = 'Gift deleted successfully';

          if (this.messageTimeout) clearTimeout(this.messageTimeout);
          this.messageTimeout = setTimeout(() => {
            this.successMessage = '';
          }, 3000);
        } catch (error) {
          logger.error('Failed to delete gift:', error);
          this.errorMessage = error.response?.data?.message || error.message || 'Failed to delete gift';
        }
      }
    },
  },
};
</script>
