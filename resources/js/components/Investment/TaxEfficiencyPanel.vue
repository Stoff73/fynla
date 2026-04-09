<template>
  <div class="tax-efficiency-panel">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
      <span class="ml-3 text-neutral-500">Analysing tax position...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-12">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-raspberry-400">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p class="text-lg font-medium text-horizon-500">Unable to load tax analysis</p>
      <p class="text-sm text-neutral-500 mb-4">{{ error }}</p>
      <button @click="fetchTaxData" class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700">
        Try Again
      </button>
    </div>

    <!-- No Data State -->
    <div v-else-if="!taxData || !taxData.success" class="text-center py-12 text-neutral-500">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
      </svg>
      <p class="text-lg font-medium">No Investment Data</p>
      <p class="text-sm">Add investment accounts to see tax efficiency analysis.</p>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-6">
      <!-- Tax Year Banner -->
      <div class="flex items-center justify-between bg-eggshell-500 rounded-lg px-4 py-3">
        <div class="flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-600 mr-2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
          </svg>
          <span class="font-semibold text-violet-800">Tax Year {{ taxData.tax_year }}</span>
        </div>
        <div class="text-sm" :class="daysRemainingClass">
          <span class="font-medium">{{ daysRemaining }}</span> days until tax year end
        </div>
      </div>

      <!-- Section A: Overview Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- ISA Allowance -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">ISA Allowance Used</p>
          <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(currentPosition.isa_used) }}</p>
          <div class="mt-2">
            <div class="w-full bg-spring-200 rounded-full h-2">
              <div class="bg-spring-600 h-2 rounded-full" :style="{ width: isaUtilizationPercent + '%' }"></div>
            </div>
            <p class="text-xs text-neutral-500 mt-1">{{ formatCurrency(currentPosition.isa_remaining) }} remaining</p>
          </div>
        </div>

        <!-- CGT Position -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Capital Gains Tax Position</p>
          <p class="text-2xl font-bold" :class="cgtPositionClass">
            {{ formatCurrency(currentPosition.net_unrealized_gains) }}
          </p>
          <p class="text-xs text-neutral-500 mt-1">
            Capital Gains Tax allowance: {{ formatCurrency(currentPosition.cgt_allowance) }}
          </p>
        </div>

        <!-- Potential Savings -->
        <div class="bg-eggshell-500 rounded-lg p-4">
          <p class="text-sm text-neutral-500 mb-1">Potential Annual Savings</p>
          <p class="text-2xl font-bold text-violet-600">{{ formatCurrency(potentialSavings.total_potential_savings) }}</p>
          <p class="text-xs text-neutral-500 mt-1">{{ opportunityCount }} opportunities identified</p>
        </div>
      </div>

      <!-- Section B: ISA Allowance Tracker -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">ISA Allowance Tracker</h3>
          <button
            v-if="currentPosition.isa_remaining > 0"
            @click="showISATransferModal = true"
            class="px-4 py-2 bg-spring-600 text-white text-sm rounded-lg hover:bg-spring-700 flex items-center"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Transfer to ISA
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Used This Year</p>
            <p class="text-2xl font-bold text-spring-600">{{ formatCurrency(currentPosition.isa_used) }}</p>
          </div>
          <div class="text-center p-4 bg-savannah-100 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Remaining Allowance</p>
            <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(currentPosition.isa_remaining) }}</p>
          </div>
          <div class="text-center p-4" :class="isaUrgencyClass">
            <p class="text-sm text-neutral-500 mb-1">Utilisation</p>
            <p class="text-2xl font-bold">{{ currentPosition.isa_utilization?.toFixed(1) || 0 }}%</p>
          </div>
        </div>

        <div class="mt-4">
          <div class="w-full bg-savannah-200 rounded-full h-3">
            <div
              class="bg-spring-500 h-3 rounded-full transition-all duration-500"
              :style="{ width: isaUtilizationPercent + '%' }"
            ></div>
          </div>
          <div class="flex justify-between text-xs text-neutral-500 mt-1">
            <span>£0</span>
            <span>{{ formatCurrency(currentPosition.isa_allowance) }}</span>
          </div>
        </div>
      </div>

      <!-- Section C: CGT Position Dashboard -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Capital Gains Tax Position</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Unrealised Gains</p>
            <p class="text-xl font-bold text-raspberry-600">{{ formatCurrency(currentPosition.unrealized_gains) }}</p>
          </div>
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Unrealised Losses</p>
            <p class="text-xl font-bold text-spring-600">-{{ formatCurrency(currentPosition.unrealized_losses) }}</p>
          </div>
          <div class="text-center p-4 bg-savannah-100 rounded-lg border border-horizon-300">
            <p class="text-sm text-neutral-500 mb-1">Net Position</p>
            <p class="text-xl font-bold" :class="netPositionClass">{{ formatCurrency(currentPosition.net_unrealized_gains) }}</p>
          </div>
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Capital Gains Tax Allowance</p>
            <p class="text-xl font-bold text-violet-600">{{ formatCurrency(currentPosition.cgt_allowance) }}</p>
          </div>
        </div>

        <!-- CGT Allowance Status -->
        <div v-if="cgtExcess > 0" class="p-4 bg-eggshell-500 rounded-lg">
          <div class="flex items-start">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-raspberry-600 mr-3 flex-shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <p class="font-semibold text-raspberry-800">Gains Exceed Capital Gains Tax Allowance</p>
              <p class="text-sm text-raspberry-700">
                Your unrealised gains exceed the annual Capital Gains Tax allowance by {{ formatCurrency(cgtExcess) }}.
                If realised, this would result in approximately {{ formatCurrency(cgtExcess * 0.20) }} in Capital Gains Tax.
              </p>
            </div>
          </div>
        </div>

        <div v-else class="p-4 bg-eggshell-500 rounded-lg">
          <div class="flex items-start">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-spring-600 mr-3 flex-shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <p class="font-semibold text-spring-800">Within Capital Gains Tax Allowance</p>
              <p class="text-sm text-spring-700">
                Your net gains are within the annual Capital Gains Tax allowance. You can realise up to {{ formatCurrency(cgtAllowanceRemaining) }} more in gains tax-free this year.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section D: Tax-Loss Harvesting Opportunities -->
      <div v-if="taxLossOpportunities.length > 0" class="bg-white rounded-lg border border-light-gray overflow-hidden">
        <div class="px-4 py-3 bg-eggshell-500 border-b border-light-gray flex items-center justify-between">
          <h3 class="text-lg font-semibold text-horizon-500">Tax-Loss Harvesting Opportunities</h3>
          <span class="px-2 py-1 bg-violet-500 text-white text-xs font-medium rounded">
            {{ taxLossOpportunities.length }} opportunities
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-light-gray bg-eggshell-500">
                <th class="text-left py-3 px-4 font-semibold text-neutral-500">Security</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Current Value</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Loss Amount</th>
                <th class="text-right py-3 px-4 font-semibold text-neutral-500">Tax Saving</th>
                <th class="text-center py-3 px-4 font-semibold text-neutral-500">Priority</th>
                <th class="text-center py-3 px-4 font-semibold text-neutral-500">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(opp, index) in taxLossOpportunities"
                :key="index"
                class="border-b border-savannah-100 hover:bg-eggshell-500"
              >
                <td class="py-3 px-4 font-medium text-horizon-500">{{ opp.security_name || 'Unknown' }}</td>
                <td class="text-right py-3 px-4">{{ formatCurrency(opp.current_value) }}</td>
                <td class="text-right py-3 px-4 text-raspberry-600 font-medium">-{{ formatCurrency(opp.loss_amount) }}</td>
                <td class="text-right py-3 px-4 text-spring-600 font-medium">{{ formatCurrency(opp.tax_saving) }}</td>
                <td class="text-center py-3 px-4">
                  <span :class="getPriorityClass(opp.priority)" class="px-2 py-1 text-xs font-medium rounded">
                    {{ opp.priority }}
                  </span>
                </td>
                <td class="text-center py-3 px-4">
                  <button
                    @click="openHarvestModal(opp)"
                    class="px-3 py-1 bg-violet-500 text-white text-xs rounded hover:bg-raspberry-500"
                  >
                    Harvest Loss
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3 bg-eggshell-500 rounded-b-lg">
          <p class="text-xs text-violet-700">
            <strong>Note:</strong> The 30-day bed-and-breakfasting rule applies. You cannot repurchase substantially the same securities within 30 days.
          </p>
        </div>
      </div>

      <!-- Section E: Bed & ISA Suggestions -->
      <div v-if="bedAndISAOpportunity" class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Bed & ISA Opportunity</h3>
          <span class="px-2 py-1 bg-spring-500 text-white text-xs font-medium rounded">
            Save {{ formatCurrency(bedAndISAOpportunity.potential_annual_saving) }}/year
          </span>
        </div>

        <p class="text-sm text-neutral-500 mb-4">
          Transfer holdings from your General Investment Account to your ISA to shelter future growth and dividends from tax.
          The holdings below have gains within your Capital Gains Tax allowance, so the transfer would be tax-free.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Transferable Amount</p>
            <p class="text-xl font-bold text-violet-600">{{ formatCurrency(bedAndISAOpportunity.transferable_amount) }}</p>
          </div>
          <div class="text-center p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm text-neutral-500 mb-1">Annual Tax Saving</p>
            <p class="text-xl font-bold text-spring-600">{{ formatCurrency(bedAndISAOpportunity.potential_annual_saving) }}</p>
          </div>
          <div class="text-center p-4 bg-savannah-100 rounded-lg border border-light-gray">
            <p class="text-sm text-neutral-500 mb-1">Capital Gains Tax on Transfer</p>
            <p class="text-xl font-bold text-horizon-500">{{ formatCurrency(bedAndISAOpportunity.cgt_on_transfer || 0) }}</p>
          </div>
        </div>

        <!-- Suitable Holdings -->
        <div v-if="bedAndISAOpportunity.suitable_holdings && bedAndISAOpportunity.suitable_holdings.length > 0">
          <h4 class="font-medium text-neutral-500 mb-2">Suitable Holdings for Transfer</h4>
          <div class="space-y-2">
            <div
              v-for="(holding, index) in bedAndISAOpportunity.suitable_holdings.slice(0, 5)"
              :key="index"
              class="flex items-center justify-between p-3 bg-eggshell-500 rounded-lg"
            >
              <span class="font-medium text-horizon-500">{{ holding.security_name }}</span>
              <div class="text-right">
                <span class="text-neutral-500">{{ formatCurrency(holding.current_value) }}</span>
                <span class="text-spring-600 ml-2">(+{{ formatCurrency(holding.gain) }} gain)</span>
              </div>
            </div>
          </div>
        </div>

        <button
          @click="showBedAndISAModal = true"
          class="mt-4 w-full px-4 py-3 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 font-medium"
        >
          View Execution Plan
        </button>
      </div>

      <!-- Section F: All Recommendations -->
      <div v-if="recommendations.length > 0" class="bg-white rounded-lg border border-light-gray overflow-hidden">
        <div class="px-4 py-3 bg-eggshell-500 border-b border-light-gray">
          <h3 class="text-lg font-semibold text-horizon-500">Tax Optimisation Recommendations</h3>
        </div>
        <div class="divide-y divide-savannah-100">
          <div
            v-for="rec in recommendations"
            :key="rec.rank"
            class="p-4 hover:bg-eggshell-500"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center mb-1">
                  <span :class="getPriorityClass(rec.priority)" class="px-2 py-0.5 text-xs font-medium rounded mr-2">
                    {{ rec.priority }}
                  </span>
                  <span class="font-medium text-horizon-500">{{ rec.title }}</span>
                </div>
                <p class="text-sm text-neutral-500">{{ rec.description }}</p>
                <p class="text-xs text-neutral-500 mt-1">
                  <strong>Action:</strong> {{ rec.action }}
                </p>
              </div>
              <div class="text-right ml-4">
                <p class="text-lg font-bold text-spring-600">{{ formatCurrency(rec.potential_saving) }}</p>
                <p class="text-xs text-neutral-500">potential saving</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Dividend Tax Info -->
      <div v-if="currentPosition.dividend_excess > 0" class="bg-eggshell-500 rounded-lg p-4">
        <div class="flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-violet-600 mr-3 flex-shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
          </svg>
          <div>
            <p class="font-semibold text-violet-800">Dividend Allowance Exceeded</p>
            <p class="text-sm text-violet-700">
              Your estimated annual dividend income of {{ formatCurrency(currentPosition.annual_dividend_income) }}
              exceeds the {{ formatCurrency(currentPosition.dividend_allowance) }} allowance by
              {{ formatCurrency(currentPosition.dividend_excess) }}.
              Consider moving dividend-paying investments to your ISA.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- ISA Transfer Modal -->
    <ISATransferModal
      v-if="showISATransferModal"
      :isa-remaining="currentPosition.isa_remaining"
      :opportunities="bedAndISAOpportunity?.suitable_holdings || []"
      @close="showISATransferModal = false"
    />

    <!-- Harvest Loss Modal -->
    <HarvestLossModal
      v-if="showHarvestModal"
      :holding="selectedHolding"
      @close="closeHarvestModal"
    />

    <!-- Bed & ISA Wizard Modal -->
    <BedAndISAWizardModal
      v-if="showBedAndISAModal"
      :opportunity="bedAndISAOpportunity"
      :isa-remaining="currentPosition.isa_remaining"
      @close="showBedAndISAModal = false"
    />
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import investmentService from '@/services/investmentService';
import ISATransferModal from './ISATransferModal.vue';
import HarvestLossModal from './HarvestLossModal.vue';
import BedAndISAWizardModal from './BedAndISAWizardModal.vue';
import { CGT_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

import logger from '@/utils/logger';
export default {
  name: 'TaxEfficiencyPanel',

  components: {
    ISATransferModal,
    HarvestLossModal,
    BedAndISAWizardModal,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      error: null,
      taxData: null,
      showISATransferModal: false,
      showHarvestModal: false,
      showBedAndISAModal: false,
      selectedHolding: null,
    };
  },

  computed: {
    currentPosition() {
      return this.taxData?.current_position || {};
    },

    potentialSavings() {
      return this.taxData?.potential_savings || { total_potential_savings: 0 };
    },

    recommendations() {
      return this.taxData?.recommendations || [];
    },

    opportunities() {
      return this.taxData?.opportunities || [];
    },

    opportunityCount() {
      return this.opportunities.length;
    },

    isaUtilizationPercent() {
      return Math.min(100, this.currentPosition.isa_utilization || 0);
    },

    cgtExcess() {
      const gains = this.currentPosition.net_unrealized_gains || 0;
      const allowance = this.currentPosition.cgt_allowance || CGT_ANNUAL_ALLOWANCE;
      return Math.max(0, gains - allowance);
    },

    cgtAllowanceRemaining() {
      const gains = this.currentPosition.net_unrealized_gains || 0;
      const allowance = this.currentPosition.cgt_allowance || CGT_ANNUAL_ALLOWANCE;
      return Math.max(0, allowance - gains);
    },

    cgtPositionClass() {
      if (this.cgtExcess > 0) return 'text-raspberry-600';
      return 'text-violet-600';
    },

    netPositionClass() {
      const net = this.currentPosition.net_unrealized_gains || 0;
      if (net > 0) return 'text-raspberry-600';
      if (net < 0) return 'text-spring-600';
      return 'text-horizon-500';
    },

    isaUrgencyClass() {
      const utilization = this.currentPosition.isa_utilization || 0;
      if (utilization >= 80) return 'bg-spring-500 text-white rounded-lg';
      if (utilization >= 50) return 'bg-violet-500 text-white rounded-lg';
      return 'bg-raspberry-500 text-white rounded-lg';
    },

    daysRemaining() {
      const now = new Date();
      const currentYear = now.getFullYear();
      const currentMonth = now.getMonth() + 1;
      const currentDay = now.getDate();

      let taxYearEnd;
      if (currentMonth < 4 || (currentMonth === 4 && currentDay <= 5)) {
        taxYearEnd = new Date(currentYear, 3, 5); // April 5 this year
      } else {
        taxYearEnd = new Date(currentYear + 1, 3, 5); // April 5 next year
      }

      const diffTime = taxYearEnd - now;
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    },

    daysRemainingClass() {
      if (this.daysRemaining <= 30) return 'text-raspberry-600 font-semibold';
      if (this.daysRemaining <= 90) return 'text-violet-600 font-medium';
      return 'text-neutral-500';
    },

    taxLossOpportunities() {
      const lossOpp = this.opportunities.find(o => o.type === 'tax_loss_harvesting');
      if (!lossOpp || !lossOpp.details) return [];

      // Return mock data structure for display
      // In reality, this would come from a more detailed API call
      return lossOpp.details.holdings_with_losses || [];
    },

    bedAndISAOpportunity() {
      const opp = this.opportunities.find(o => o.type === 'bed_and_isa');
      return opp?.details || null;
    },
  },

  mounted() {
    this.fetchTaxData();
  },

  methods: {
    async fetchTaxData() {
      this.loading = true;
      this.error = null;

      try {
        const response = await investmentService.analyzeTaxPosition();
        this.taxData = response.data || response;
      } catch (err) {
        logger.error('Failed to fetch tax data:', err);
        this.error = err.response?.data?.message || err.message || 'Failed to load tax analysis';
      } finally {
        this.loading = false;
      }
    },

    getPriorityClass(priority) {
      switch (priority?.toLowerCase()) {
        case 'high':
          return 'bg-raspberry-500 text-white';
        case 'medium':
          return 'bg-violet-500 text-white';
        case 'low':
          return 'bg-spring-500 text-white';
        default:
          return 'bg-eggshell-500 text-white';
      }
    },

    openHarvestModal(holding) {
      this.selectedHolding = holding;
      this.showHarvestModal = true;
    },

    closeHarvestModal() {
      this.selectedHolding = null;
      this.showHarvestModal = false;
    },
  },
};
</script>

<style scoped>
/* Component-specific styles */
</style>
