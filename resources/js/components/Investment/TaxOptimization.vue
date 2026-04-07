<template>
  <div class="tax-optimization-tab">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-raspberry-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-raspberry-800">{{ error }}</span>
      </div>
    </div>

    <!-- Tax Optimization Content -->
    <div v-else>
      <!-- Current Tax Position Summary -->
      <div class="mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Current Tax Position ({{ taxYear }})</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <p class="text-sm text-neutral-500 mb-1">ISA Allowance Used</p>
              <p class="text-xl font-semibold text-horizon-500">
                £{{ formatNumber(taxAnalysis?.current_position?.isa_allowance_used || 0) }}
              </p>
              <p class="text-xs text-neutral-500">
                {{ ((taxAnalysis?.current_position?.isa_allowance_used || 0) / ISA_ANNUAL_ALLOWANCE * 100).toFixed(0) }}% of {{ formatCurrency(ISA_ANNUAL_ALLOWANCE) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Unrealised Gains</p>
              <p class="text-xl font-semibold text-spring-600">
                £{{ formatNumber(taxAnalysis?.current_position?.unrealised_gains || 0) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Unrealised Losses</p>
              <p class="text-xl font-semibold text-raspberry-600">
                £{{ formatNumber(Math.abs(taxAnalysis?.current_position?.unrealised_losses || 0)) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-neutral-500 mb-1">Dividend Income</p>
              <p class="text-xl font-semibold text-horizon-500">
                £{{ formatNumber(taxAnalysis?.current_position?.projected_dividend_income || 0) }}
              </p>
              <p class="text-xs text-neutral-500">per year</p>
            </div>
          </div>

          <!-- Potential Annual Savings -->
          <div class="mt-4 p-4 bg-eggshell-500 rounded-md">
            <div class="flex justify-between items-center">
              <span class="text-sm font-medium text-neutral-500">Potential Annual Tax Savings:</span>
              <span class="text-2xl font-bold text-spring-600">
                £{{ formatNumber(taxAnalysis?.potential_savings?.annual || 0) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-4 px-6 text-sm font-medium border-b-2 transition-colors duration-200',
                activeTab === tab.id
                  ? 'border-violet-500 text-violet-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              ]"
            >
              {{ tab.name }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'">
            <TaxOptimizationOverview
              :analysis="taxAnalysis"
              @refresh="loadTaxAnalysis"
            />
          </div>

          <!-- ISA Strategy Tab -->
          <div v-if="activeTab === 'isa'">
            <ISAOptimizationStrategy
              :strategy="isaStrategy"
              @refresh="loadISAStrategy"
            />
          </div>

          <!-- CGT Harvesting Tab -->
          <div v-if="activeTab === 'cgt'">
            <CGTHarvestingOpportunities
              :opportunities="cgtHarvesting"
              @refresh="loadCGTHarvesting"
            />
          </div>

          <!-- Bed & ISA Tab -->
          <div v-if="activeTab === 'bed-isa'">
            <BedAndISATransfers
              :opportunities="bedAndISA"
              @refresh="loadBedAndISA"
            />
          </div>

          <!-- Recommendations Tab -->
          <div v-if="activeTab === 'recommendations'">
            <TaxOptimizationRecommendations
              :recommendations="recommendations"
              @refresh="loadRecommendations"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import investmentService from '@/services/investmentService';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';
import { getTaxYearStart } from '@/utils/dateFormatter';
import TaxOptimizationOverview from './TaxOptimizationOverview.vue';
import ISAOptimizationStrategy from './ISAOptimizationStrategy.vue';
import CGTHarvestingOpportunities from './CGTHarvestingOpportunities.vue';
import BedAndISATransfers from './BedAndISATransfers.vue';
import TaxOptimizationRecommendations from './TaxOptimizationRecommendations.vue';

import logger from '@/utils/logger';
export default {
  name: 'TaxOptimization',

  mixins: [currencyMixin],

  components: {
    TaxOptimizationOverview,
    ISAOptimizationStrategy,
    CGTHarvestingOpportunities,
    BedAndISATransfers,
    TaxOptimizationRecommendations,
  },

  data() {
    return {
      loading: true,
      error: null,
      activeTab: 'overview',
      taxAnalysis: null,
      isaStrategy: null,
      cgtHarvesting: null,
      bedAndISA: null,
      recommendations: null,
      ISA_ANNUAL_ALLOWANCE,
      taxYear: (() => { const y = getTaxYearStart().getFullYear(); return `${y}/${String(y + 1).slice(-2)}`; })(),
      tabs: [
        { id: 'overview', name: 'Overview' },
        { id: 'isa', name: 'ISA Strategy' },
        { id: 'cgt', name: 'Capital Gains Tax Harvesting' },
        { id: 'bed-isa', name: 'Bed & ISA' },
        { id: 'recommendations', name: 'Recommendations' },
      ],
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadAllData();
  },

  methods: {
    async loadAllData() {
      this.loading = true;
      this.error = null;

      try {
        await Promise.all([
          this.loadTaxAnalysis(),
          this.loadISAStrategy(),
          this.loadCGTHarvesting(),
          this.loadBedAndISA(),
          this.loadRecommendations(),
        ]);
      } catch (err) {
        logger.error('Error loading tax optimization data:', err);
        this.error = err.response?.data?.message || 'Failed to load tax optimization data';
      } finally {
        this.loading = false;
      }
    },

    async loadTaxAnalysis() {
      try {
        const response = await investmentService.analyzeTaxPosition({
          tax_year: this.taxYear,
        });
        this.taxAnalysis = response.data;
      } catch (err) {
        logger.error('Error loading tax analysis:', err);
        throw err;
      }
    },

    async loadISAStrategy() {
      try {
        const response = await investmentService.getISAStrategy();
        this.isaStrategy = response.data;
      } catch (err) {
        logger.error('Error loading ISA strategy:', err);
        // Non-critical, don't throw
      }
    },

    async loadCGTHarvesting() {
      try {
        const response = await investmentService.getCGTHarvestingOpportunities();
        this.cgtHarvesting = response.data;
      } catch (err) {
        logger.error('Error loading CGT harvesting:', err);
        // Non-critical, don't throw
      }
    },

    async loadBedAndISA() {
      try {
        const response = await investmentService.getBedAndISAOpportunities();
        this.bedAndISA = response.data;
      } catch (err) {
        logger.error('Error loading Bed and ISA:', err);
        // Non-critical, don't throw
      }
    },

    async loadRecommendations() {
      try {
        const response = await investmentService.getTaxRecommendations();
        this.recommendations = response.data;
      } catch (err) {
        logger.error('Error loading recommendations:', err);
        // Non-critical, don't throw
      }
    },

  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
