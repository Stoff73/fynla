<template>
  <div class="intestacy-rules">
    <!-- Header -->
    <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-violet-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-violet-800">No Will on Record</h3>
          <p class="mt-1 text-sm text-violet-700">
            Without a will, your estate will be distributed according to UK intestacy rules. This may not reflect your wishes.
          </p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
      <p class="mt-2 text-neutral-500">Calculating intestacy distribution...</p>
    </div>

    <!-- Intestacy Distribution Results -->
    <div v-else-if="distribution">
      <!-- Main Distribution Card -->
      <div class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">How Your Estate Would Be Distributed</h3>

        <div class="space-y-4">
          <!-- Scenario Summary -->
          <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-violet-900 mb-2">{{ distribution.scenario }}</h4>
            <p class="text-sm text-violet-700">{{ distribution.explanation }}</p>
          </div>

          <!-- Estate Value -->
          <div class="border-t border-light-gray pt-4">
            <div class="flex justify-between items-center mb-2">
              <span class="text-sm font-medium text-neutral-500">Total Estate Value</span>
              <span class="text-lg font-bold text-horizon-500">{{ formatCurrency(distribution.estate_value) }}</span>
            </div>
          </div>

          <!-- Beneficiaries -->
          <div v-if="distribution.beneficiaries && distribution.beneficiaries.length > 0" class="border-t border-light-gray pt-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-3">Distribution to Beneficiaries</h4>
            <div class="space-y-3">
              <div
                v-for="(beneficiary, index) in distribution.beneficiaries"
                :key="index"
                class="flex justify-between items-start p-3 bg-eggshell-500 rounded-lg"
              >
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-horizon-500">{{ beneficiary.relationship }}</span>
                    <span v-if="beneficiary.count > 1" class="text-xs text-neutral-500">({{ beneficiary.count }} person{{ beneficiary.count > 1 ? 's' : '' }})</span>
                  </div>
                  <p v-if="beneficiary.name" class="text-xs text-neutral-500 mt-1">{{ beneficiary.name }}</p>
                  <p class="text-xs text-neutral-500 mt-1">{{ beneficiary.share_description }}</p>
                </div>
                <div class="text-right">
                  <div class="text-sm font-bold text-horizon-500">{{ formatCurrency(beneficiary.amount) }}</div>
                  <div class="text-xs text-neutral-500">{{ beneficiary.percentage }}%</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Crown Estate Warning -->
          <div v-if="distribution.goes_to_crown" class="border-t border-light-gray pt-4">
            <div class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-raspberry-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <h4 class="text-sm font-medium text-raspberry-800">Estate Goes to the Crown</h4>
                  <p class="mt-1 text-sm text-raspberry-700">
                    Without any eligible relatives, your entire estate would pass to the Crown (government). Making a will allows you to leave your estate to people or causes you care about.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Decision Tree Visualization -->
      <div class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Intestacy Decision Path</h3>
        <div class="space-y-3">
          <div
            v-for="(step, index) in distribution.decision_path"
            :key="index"
            class="flex items-start gap-3"
          >
            <div class="flex-shrink-0 mt-1">
              <div :class="[
                'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold',
                step.answer === 'YES' ? 'bg-spring-100 text-spring-700' : 'bg-raspberry-100 text-raspberry-700'
              ]">
                {{ index + 1 }}
              </div>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-horizon-500">{{ step.question }}</p>
              <p class="text-xs text-neutral-500 mt-1">
                Answer: <span :class="step.answer === 'YES' ? 'text-spring-700 font-semibold' : 'text-raspberry-700 font-semibold'">{{ step.answer }}</span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Call to Action -->
      <div class="bg-horizon-500 rounded-lg p-6 text-white">
        <h3 class="text-lg font-semibold mb-2">Take Control of Your Legacy</h3>
        <p class="text-sm text-violet-100 mb-4">
          Creating a will ensures your estate is distributed according to your wishes, not government rules.
          You can specify beneficiaries, set up trusts, and minimize inheritance tax.
        </p>
        <button
          @click="$emit('create-will')"
          class="px-6 py-2 bg-white text-violet-700 rounded-button hover:bg-violet-50 font-semibold transition-colors"
        >
          Create Your Will
        </button>
      </div>

      <!-- Additional Information -->
      <div class="mt-6 bg-eggshell-500 rounded-lg border border-light-gray p-4">
        <h4 class="text-sm font-semibold text-horizon-500 mb-2">Important Notes</h4>
        <ul class="text-xs text-neutral-500 space-y-2">
          <li class="flex items-start gap-2">
            <span class="text-violet-600 mt-0.5">•</span>
            <span>Only blood relatives inherit under intestacy rules - step-children, unmarried partners, and friends receive nothing.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="text-violet-600 mt-0.5">•</span>
            <span>If a relative entitled to a share has died before you, their children usually inherit their share.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="text-violet-600 mt-0.5">•</span>
            <span>Jointly owned property (like your home) may pass directly to the surviving joint owner, regardless of intestacy rules.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="text-violet-600 mt-0.5">•</span>
            <span>Making a will allows you to choose guardians for children under 18.</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-4">
      <p class="text-sm text-raspberry-800">{{ error }}</p>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'IntestacyRules',

  emits: ['create-will'],

  mixins: [currencyMixin],

  props: {
    estateValue: {
      type: Number,
      default: 0
    }
  },

  data() {
    return {
      loading: true,
      distribution: null,
      error: null,
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.calculateIntestacy();
  },

  watch: {
    estateValue() {
      this.calculateIntestacy();
    }
  },

  methods: {
    async calculateIntestacy() {
      // Preview users are real DB users - use normal API for calculations
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/estate/calculate-intestacy', {
          estate_value: this.estateValue
        });
        this.distribution = response.data.data;
      } catch (error) {
        logger.error('Failed to calculate intestacy:', error);
        this.error = error.response?.data?.message || 'Failed to calculate intestacy distribution';
      } finally {
        this.loading = false;
      }
    },

    // formatCurrency provided by currencyMixin
  },
};
</script>

<style scoped>
/* Custom styles if needed */
</style>
