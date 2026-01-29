<template>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ firstColumnHeader }}</th>
          <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Now</th>
          <th v-if="showMinus5Years" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
            <div>Age {{ projectionMinus5Age }}</div>
            <div class="text-[10px] font-normal text-gray-400 normal-case mt-0.5">-5 years</div>
          </th>
          <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
            <div class="flex items-center justify-end">
              <button
                @click="$emit('toggle-minus-5')"
                class="p-1 hover:bg-gray-100 rounded transition-colors"
                :title="showMinus5Years ? 'Hide -5 years' : 'Show -5 years'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="showMinus5Years ? 'M8.25 4.5l7.5 7.5-7.5 7.5' : 'M15.75 19.5L8.25 12l7.5-7.5'" />
                </svg>
              </button>
              <span class="mx-1">Age {{ estimatedAge }}</span>
              <button
                @click="$emit('toggle-plus-5')"
                class="p-1 hover:bg-gray-100 rounded transition-colors"
                :title="showPlus5Years ? 'Hide +5 years' : 'Show +5 years'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="showPlus5Years ? 'M15.75 19.5L8.25 12l7.5-7.5' : 'M8.25 4.5l7.5 7.5-7.5 7.5'" />
                </svg>
              </button>
            </div>
            <div class="text-[10px] font-normal text-gray-400 normal-case mt-0.5 text-right">Life expectancy</div>
          </th>
          <th v-if="showPlus5Years" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
            <div>Age {{ projectionPlus5Age }}</div>
            <div class="text-[10px] font-normal text-gray-400 normal-case mt-0.5">+5 years</div>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <!-- User Assets Section -->
        <IHTAssetBreakdown
          v-if="assetsBreakdown.user"
          owner-key="user"
          :owner-data="assetsBreakdown.user"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-assets="expandedAssets"
          :get-projected-minus-5="getProjectedValueMinus5"
          :get-projected-plus-5="getProjectedValuePlus5"
          :subtotal-label="showSpouse ? 'Subtotal' : 'Assets Subtotal'"
          @toggle-asset="toggleAssetGroup"
        />

        <!-- Spouse Assets Section -->
        <IHTAssetBreakdown
          v-if="showSpouse && assetsBreakdown.spouse"
          owner-key="spouse"
          :owner-data="assetsBreakdown.spouse"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-assets="expandedAssets"
          :get-projected-minus-5="getProjectedValueMinus5"
          :get-projected-plus-5="getProjectedValuePlus5"
          subtotal-label="Subtotal"
          @toggle-asset="toggleAssetGroup"
        />

        <!-- Total Gross Assets -->
        <tr :class="showSpouse ? 'bg-white border-l-4 border-gray-400' : 'bg-white border-l-4 border-gray-400 border-t-2 border-gray-300'">
          <td class="px-4 py-3 text-sm font-bold text-gray-900">Total Gross Assets</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.grossAssets.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.grossAssets.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.grossAssets.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.grossAssets.plus5) }}</td>
        </tr>

        <!-- User Liabilities Section -->
        <IHTLiabilityBreakdown
          v-if="liabilitiesBreakdown.user"
          owner-key="user"
          :owner-data="liabilitiesBreakdown.user"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-liabilities="expandedLiabilities"
          :subtotal-label="showSpouse ? 'Subtotal' : 'Liabilities Subtotal'"
          @toggle-liability="toggleLiabilityGroup"
        />

        <!-- Spouse Liabilities Section -->
        <IHTLiabilityBreakdown
          v-if="showSpouse && liabilitiesBreakdown.spouse"
          owner-key="spouse"
          :owner-data="liabilitiesBreakdown.spouse"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-liabilities="expandedLiabilities"
          subtotal-label="Subtotal"
          @toggle-liability="toggleLiabilityGroup"
        />

        <!-- Total Liabilities -->
        <tr :class="showSpouse ? 'bg-white border-l-4 border-gray-400' : 'bg-white border-l-4 border-gray-400 border-t-2 border-gray-300'">
          <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ showSpouse ? 'Less: Total Liabilities' : 'Total Liabilities' }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatLiability(totals.liabilities.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatLiability(totals.liabilities.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatLiability(totals.liabilities.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatLiability(totals.liabilities.plus5) }}</td>
        </tr>

        <!-- Net Estate -->
        <tr class="bg-white border-l-4 border-gray-400">
          <td class="px-4 py-3 text-sm font-semibold text-gray-900">Net Estate</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.netEstate.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.netEstate.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.netEstate.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(totals.netEstate.plus5) }}</td>
        </tr>

        <!-- Allowances Section -->
        <template v-if="showSpouse">
          <!-- Married couple - show both NRB allowances -->
          <template v-if="allowances.showSeparateSpouseAllowances">
            <tr>
              <td class="px-4 py-3 text-sm font-semibold text-gray-700">Less: Tax-Free Allowance (Individual)</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrb) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrb) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrb) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrb) }}</td>
            </tr>
            <tr v-if="allowances.nrbFromSpouse > 0">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                Less: Tax-Free Allowance from Spouse
                <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-gray-600 font-normal">(Default - verify by linking spouse)</span>
              </td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
            </tr>
          </template>
          <template v-else>
            <!-- Married but showing combined view -->
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700 pl-8">Less: {{ assetsBreakdown.user?.name }}'s Tax-Free Allowance</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
            </tr>
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700 pl-8">Less: {{ assetsBreakdown.spouse?.name }}'s Tax-Free Allowance</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(325000) }}</td>
            </tr>
          </template>
        </template>
        <template v-else>
          <!-- Single person - show combined NRB -->
          <tr class="bg-gray-50">
            <td class="px-4 py-3 text-sm font-semibold text-gray-700">Less: Tax-Free Allowance</td>
            <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalNrb) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalNrb) }}</td>
            <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalNrb) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalNrb) }}</td>
          </tr>
        </template>

        <!-- Estate after NRB (charitable bequest baseline) -->
        <tr class="bg-blue-50 border-l-4 border-blue-400">
          <td class="px-4 py-3 text-sm font-semibold text-blue-800">
            Estate after Tax-Free Allowance{{ showSpouse ? 's' : '' }}
            <span class="block text-xs font-normal text-blue-600 mt-0.5">Charitable bequest baseline (before home allowance)</span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-bold text-blue-800">{{ formatCurrency(estateAfterNRB.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-blue-800">{{ formatCurrency(estateAfterNRB.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-blue-800">{{ formatCurrency(estateAfterNRB.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-blue-800">{{ formatCurrency(estateAfterNRB.plus5) }}</td>
        </tr>

        <!-- Charitable Bequest (if enabled) - positioned after NRB, before RNRB -->
        <tr v-if="charitableBequest" class="bg-green-50 border-l-4 border-green-400">
          <td class="px-4 py-3 text-sm font-semibold text-green-800">
            Less: Charitable Bequest (10% minimum)
            <span class="block text-xs font-normal text-green-600 mt-0.5">Qualifies estate for reduced 36% rate</span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-semibold text-green-800">-{{ formatCurrency(charitableDonation.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-green-800">-{{ formatCurrency(charitableDonation.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-semibold text-green-800">-{{ formatCurrency(charitableDonation.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-green-800">-{{ formatCurrency(charitableDonation.plus5) }}</td>
        </tr>

        <!-- RNRB Allowances -->
        <template v-if="allowances.rnrbEligible && allowances.totalRnrb > 0">
          <template v-if="showSpouse && allowances.showSeparateSpouseAllowances">
            <tr v-if="allowances.rnrbIndividual > 0">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700">Less: Home Allowance (Individual)</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
            </tr>
            <tr v-if="allowances.rnrbFromSpouse > 0">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                Less: Home Allowance from Spouse
                <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-gray-600 font-normal">(Default - verify by linking spouse)</span>
              </td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
            </tr>
          </template>
          <template v-else-if="showSpouse">
            <!-- Married showing combined RNRB -->
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700 pl-8">Less: {{ assetsBreakdown.user?.name }}'s Home Allowance</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
            </tr>
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700 pl-8">Less: {{ assetsBreakdown.spouse?.name }}'s Home Allowance</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
            </tr>
          </template>
          <template v-else>
            <!-- Single person RNRB -->
            <tr class="bg-gray-50">
              <td class="px-4 py-3 text-sm font-semibold text-gray-700">Less: Home Allowance</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-gray-900">-{{ formatCurrency(allowances.totalRnrb) }}</td>
            </tr>
          </template>
        </template>

        <!-- RNRB Taper Warning -->
        <tr v-if="allowances.rnrbTapered" class="bg-white border-l-4 border-gray-400">
          <td :colspan="columnCount" class="px-4 py-2 text-xs">
            <span class="text-gray-900">
              <strong>Home Allowance Reduced:</strong> Estate value {{ formatCurrency(totals.netEstate.now) }} exceeds {{ formatCurrency(allowances.rnrbTaperThreshold || 2000000) }} threshold.
              <span v-if="allowances.totalRnrb === 0">Home allowance completely removed (reduced by {{ formatCurrency(allowances.rnrbTaperAmount || 0) }}).</span>
              <span v-else>Home allowance reduced by {{ formatCurrency(allowances.rnrbTaperAmount || 0) }} (£1 reduction for every £2 over threshold).</span>
            </span>
          </td>
        </tr>

        <!-- RNRB Not Available Message -->
        <tr v-if="!allowances.rnrbEligible" class="bg-white border-l-4 border-gray-400">
          <td :colspan="columnCount" class="px-4 py-2 text-xs text-gray-900">
            <strong>Note:</strong> Home allowance (residence nil rate band) not available - no main residence identified or property not left to direct descendants
          </td>
        </tr>

        <!-- Taxable Estate -->
        <tr class="bg-gray-50">
          <td class="px-4 py-3 text-sm font-semibold text-gray-900">Taxable Estate</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(taxableEstate.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(taxableEstate.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(taxableEstate.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(taxableEstate.plus5) }}</td>
        </tr>

        <!-- Inheritance Tax Liability -->
        <tr class="bg-white border-l-4 border-gray-400">
          <td class="px-4 py-3 text-sm font-semibold text-gray-900">
            Inheritance Tax Liability ({{ effectiveIHTRateLabel }})
            <span v-if="charitableBequest" class="ml-2 text-xs font-normal text-green-600">(Reduced rate)</span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(ihtLiability.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(ihtLiability.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(ihtLiability.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ formatCurrency(ihtLiability.plus5) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import IHTAssetBreakdown from './IHTAssetBreakdown.vue';
import IHTLiabilityBreakdown from './IHTLiabilityBreakdown.vue';

export default {
  name: 'IHTCalculationTable',

  mixins: [currencyMixin],

  components: {
    IHTAssetBreakdown,
    IHTLiabilityBreakdown,
  },

  props: {
    // Data
    assetsBreakdown: {
      type: Object,
      required: true,
      // Expected: { user: {...}, spouse?: {...} }
    },
    liabilitiesBreakdown: {
      type: Object,
      required: true,
      // Expected: { user: {...}, spouse?: {...} }
    },

    // Totals (pre-computed in parent)
    totals: {
      type: Object,
      required: true,
      // Expected: { grossAssets: {now, minus5, projected, plus5}, liabilities: {...}, netEstate: {...} }
    },

    // Allowances
    allowances: {
      type: Object,
      required: true,
      // Expected: { nrb, nrbFromSpouse, totalNrb, rnrbIndividual, rnrbFromSpouse, totalRnrb, rnrbEligible, rnrbTapered, rnrbTaperThreshold, rnrbTaperAmount, showSeparateSpouseAllowances }
    },

    // Estate after NRB (pre-computed in parent)
    estateAfterNRB: {
      type: Object,
      required: true,
      // Expected: { now, minus5, projected, plus5 }
    },

    // Taxable estate (pre-computed in parent)
    taxableEstate: {
      type: Object,
      required: true,
      // Expected: { now, minus5, projected, plus5 }
    },

    // IHT Liability (pre-computed in parent, accounts for charitable bequest)
    ihtLiability: {
      type: Object,
      required: true,
      // Expected: { now, minus5, projected, plus5 }
    },

    // Charitable bequest
    charitableBequest: {
      type: Boolean,
      default: false,
    },
    charitableDonation: {
      type: Object,
      default: () => ({ now: 0, minus5: 0, projected: 0, plus5: 0 }),
    },
    effectiveIHTRateLabel: {
      type: String,
      default: '40%',
    },

    // Display options
    showSpouse: {
      type: Boolean,
      default: false,
    },
    hasSpouseLinked: {
      type: Boolean,
      default: false,
    },
    estimatedAge: {
      type: Number,
      required: true,
    },
    projectionMinus5Age: {
      type: Number,
      default: 0,
    },
    projectionPlus5Age: {
      type: Number,
      default: 0,
    },

    // Column visibility
    showMinus5Years: {
      type: Boolean,
      default: false,
    },
    showPlus5Years: {
      type: Boolean,
      default: false,
    },

    // Growth rate for projections
    growthRate: {
      type: Number,
      default: 0.047,
    },
    yearsToDeathMinus5: {
      type: Number,
      default: 0,
    },
    yearsToDeathPlus5: {
      type: Number,
      default: 0,
    },

    // Table header customization
    firstColumnHeader: {
      type: String,
      default: 'Line Item',
    },
  },

  emits: ['toggle-minus-5', 'toggle-plus-5'],

  data() {
    return {
      expandedAssets: {},
      expandedLiabilities: {},
    };
  },

  computed: {
    columnCount() {
      return 2 + (this.showMinus5Years ? 1 : 0) + (this.showPlus5Years ? 1 : 0);
    },
  },

  methods: {
    toggleAssetGroup(key) {
      this.expandedAssets = { ...this.expandedAssets, [key]: !this.expandedAssets[key] };
    },

    toggleLiabilityGroup(key) {
      this.expandedLiabilities = { ...this.expandedLiabilities, [key]: !this.expandedLiabilities[key] };
    },

    formatLiability(value) {
      const num = parseFloat(value) || 0;
      if (num === 0) return this.formatCurrency(0);
      return `-${this.formatCurrency(num)}`;
    },

    getProjectedValueMinus5(currentValue) {
      return currentValue * Math.pow(1 + this.growthRate, this.yearsToDeathMinus5);
    },

    getProjectedValuePlus5(currentValue) {
      return currentValue * Math.pow(1 + this.growthRate, this.yearsToDeathPlus5);
    },
  },
};
</script>
