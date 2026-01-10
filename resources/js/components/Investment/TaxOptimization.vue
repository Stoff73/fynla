<template>
  <div class="tax-optimization-tab">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="flex items-center">
        <svg class="h-5 w-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span class="text-sm font-medium text-red-800">{{ error }}</span>
      </div>
    </div>

    <!-- Tax Optimization Content -->
    <div v-else>
      <!-- Header Section with Tax Efficiency Score -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Tax Efficiency Score Card -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-md p-6 lg:col-span-1">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Tax Efficiency Score</h3>
          <div class="flex items-center justify-center mb-4">
            <div class="relative">
              <apexchart
                v-if="taxAnalysis"
                type="radialBar"
                :options="efficiencyScoreChartOptions"
                :series="[taxAnalysis.efficiency_score.score]"
                height="200"
              />
            </div>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold mb-1" :class="getScoreColour(taxAnalysis?.efficiency_score?.grade)">
              {{ taxAnalysis?.efficiency_score?.grade || 'N/A' }}
            </p>
            <p class="text-sm text-gray-600">{{ taxAnalysis?.efficiency_score?.interpretation }}</p>
          </div>
        </div>

        <!-- Current Tax Position Summary -->
        <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Tax Position ({{ taxYear }})</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <p class="text-sm text-gray-600 mb-1">ISA Allowance Used</p>
              <p class="text-xl font-semibold text-gray-800">
                £{{ formatNumber(taxAnalysis?.current_position?.isa_allowance_used || 0) }}
              </p>
              <p class="text-xs text-gray-500">
                {{ ((taxAnalysis?.current_position?.isa_allowance_used || 0) / 20000 * 100).toFixed(0) }}% of £20k
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 mb-1">Unrealised Gains</p>
              <p class="text-xl font-semibold text-green-600">
                £{{ formatNumber(taxAnalysis?.current_position?.unrealised_gains || 0) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 mb-1">Unrealised Losses</p>
              <p class="text-xl font-semibold text-red-600">
                £{{ formatNumber(Math.abs(taxAnalysis?.current_position?.unrealised_losses || 0)) }}
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 mb-1">Dividend Income</p>
              <p class="text-xl font-semibold text-gray-800">
                £{{ formatNumber(taxAnalysis?.current_position?.projected_dividend_income || 0) }}
              </p>
              <p class="text-xs text-gray-500">per year</p>
            </div>
          </div>

          <!-- Potential Annual Savings -->
          <div class="mt-4 p-4 bg-gray-50 rounded-md">
            <div class="flex justify-between items-center">
              <span class="text-sm font-medium text-gray-700">Potential Annual Tax Savings:</span>
              <span class="text-2xl font-bold text-green-600">
                £{{ formatNumber(taxAnalysis?.potential_savings?.annual || 0) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-4 px-6 text-sm font-medium border-b-2 transition-colors duration-200',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
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
import TaxOptimizationOverview from './TaxOptimizationOverview.vue';
import ISAOptimizationStrategy from './ISAOptimizationStrategy.vue';
import CGTHarvestingOpportunities from './CGTHarvestingOpportunities.vue';
import BedAndISATransfers from './BedAndISATransfers.vue';
import TaxOptimizationRecommendations from './TaxOptimizationRecommendations.vue';

export default {
  name: 'TaxOptimization',

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
      taxYear: this.getCurrentTaxYear(),
      tabs: [
        { id: 'overview', name: 'Overview' },
        { id: 'isa', name: 'ISA Strategy' },
        { id: 'cgt', name: 'CGT Harvesting' },
        { id: 'bed-isa', name: 'Bed & ISA' },
        { id: 'recommendations', name: 'Recommendations' },
      ],
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
    efficiencyScoreChartOptions() {
      const score = this.taxAnalysis?.efficiency_score?.score || 0;
      const grade = this.taxAnalysis?.efficiency_score?.grade || 'N/A';

      return {
        chart: {
          type: 'radialBar',
          sparkline: {
            enabled: true,
          },
        },
        plotOptions: {
          radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: {
              size: '65%',
            },
            track: {
              background: '#e5e7eb',
              strokeWidth: '100%',
            },
            dataLabels: {
              name: {
                show: false,
              },
              value: {
                show: true,
                fontSize: '24px',
                fontWeight: 'bold',
                offsetY: 8,
                color: this.getScoreColourHex(grade),
                formatter: (val) => `${Math.round(val)}`,
              },
            },
          },
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'horizontal',
            shadeIntensity: 0.5,
            gradientToColours: [this.getScoreColourHex(grade)],
            stops: [0, 100],
          },
        },
        stroke: {
          lineCap: 'round',
        },
        colours: [this.getScoreColourHex(grade)],
      };
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
        console.error('Error loading tax optimization data:', err);
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
        console.error('Error loading tax analysis:', err);
        throw err;
      }
    },

    async loadISAStrategy() {
      try {
        const response = await investmentService.getISAStrategy();
        this.isaStrategy = response.data;
      } catch (err) {
        console.error('Error loading ISA strategy:', err);
        // Non-critical, don't throw
      }
    },

    async loadCGTHarvesting() {
      try {
        const response = await investmentService.getCGTHarvestingOpportunities();
        this.cgtHarvesting = response.data;
      } catch (err) {
        console.error('Error loading CGT harvesting:', err);
        // Non-critical, don't throw
      }
    },

    async loadBedAndISA() {
      try {
        const response = await investmentService.getBedAndISAOpportunities();
        this.bedAndISA = response.data;
      } catch (err) {
        console.error('Error loading Bed and ISA:', err);
        // Non-critical, don't throw
      }
    },

    async loadRecommendations() {
      try {
        const response = await investmentService.getTaxRecommendations();
        this.recommendations = response.data;
      } catch (err) {
        console.error('Error loading recommendations:', err);
        // Non-critical, don't throw
      }
    },

    getCurrentTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth() + 1; // 0-indexed
      const day = now.getDate();

      // UK tax year runs April 6 - April 5
      if (month < 4 || (month === 4 && day < 6)) {
        // Before April 6 - use previous year
        return `${year - 1}/${String(year).slice(-2)}`;
      } else {
        // After April 6 - use current year
        return `${year}/${String(year + 1).slice(-2)}`;
      }
    },

    getScoreColour(grade) {
      const colours = {
        'A': 'text-green-600',
        'B': 'text-blue-600',
        'C': 'text-yellow-600',
        'D': 'text-orange-600',
        'E': 'text-red-600',
        'F': 'text-red-700',
      };
      return colours[grade] || 'text-gray-600';
    },

    getScoreColourHex(grade) {
      const colours = {
        'A': '#10B981', // green-600
        'B': '#3B82F6', // blue-600
        'C': '#FBBF24', // yellow-600
        'D': '#F97316', // orange-600
        'E': '#EF4444', // red-600
        'F': '#DC2626', // red-700
      };
      return colours[grade] || '#6B7280'; // gray-600
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Math.round(value).toLocaleString('en-GB');
    },
  },
};
</script>

<style scoped>
/* Add any scoped styles here if needed */
</style>
