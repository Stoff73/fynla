<template>
  <div class="mb-6">
    <PlanSectionHeader title="Current Situation" subtitle="Your estate and Inheritance Tax overview" color="violet" />

    <div class="space-y-4">
      <!-- IHT Calculation Table (same as estate module) -->
      <div class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Inheritance Tax Calculation</h3>
        <IHTCalculationTable v-if="tableProps" v-bind="tableProps" />

        <!-- Messages below table -->
        <div class="mt-3 space-y-1.5">
          <p v-if="situation.iht_rate_message" class="text-xs text-neutral-500">
            {{ situation.iht_rate_message }}
          </p>
          <p v-if="situation.nrb_message" class="text-xs text-neutral-500">
            <span class="font-medium text-neutral-500">Nil Rate Band:</span> {{ situation.nrb_message }}
          </p>
          <p v-if="situation.rnrb_message" class="text-xs text-neutral-500">
            <span class="font-medium text-neutral-500">Residence Nil Rate Band:</span> {{ situation.rnrb_message }}
          </p>
        </div>
      </div>

      <!-- Asset Breakdown -->
      <div class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Asset Breakdown</h3>
        <div class="grid grid-cols-3 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Liquid Assets</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.asset_breakdown.liquid) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Semi-Liquid Assets</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.asset_breakdown.semi_liquid) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Illiquid Assets</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.asset_breakdown.illiquid) }}</p>
          </div>
        </div>
      </div>

      <!-- Life Cover -->
      <div v-if="hasLifeCover" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Life Cover</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Cover in Trust</p>
            <p class="text-sm font-bold text-spring-700">{{ formatCurrency(situation.life_cover.cover_in_trust) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Cover Not in Trust</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.life_cover.cover_not_in_trust) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Total Policies</p>
            <p class="text-sm font-bold text-horizon-500">{{ situation.life_cover.policy_count }}</p>
          </div>
        </div>
      </div>

      <!-- Charitable Giving -->
      <div v-if="situation.charitable_giving" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Charitable Giving</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Current Charitable Rate</p>
            <p class="text-sm font-bold text-horizon-500">{{ situation.charitable_giving.current_percentage }}%</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Threshold for 36% Rate</p>
            <p class="text-sm font-bold text-horizon-500">{{ situation.charitable_giving.threshold }}%</p>
          </div>
          <div v-if="situation.charitable_giving.shortfall > 0" class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Shortfall to Qualify</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.charitable_giving.shortfall) }}</p>
          </div>
          <div v-if="situation.charitable_giving.potential_saving > 0" class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Potential Saving</p>
            <p class="text-sm font-bold text-spring-700">{{ formatCurrency(situation.charitable_giving.potential_saving) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';
import IHTCalculationTable from '@/components/Estate/IHTCalculationTable.vue';

export default {
  name: 'EstateCurrentSituation',
  components: { PlanSectionHeader, IHTCalculationTable },
  mixins: [currencyMixin],
  props: {
    situation: { type: Object, required: true },
  },
  computed: {
    tableProps() {
      if (!this.situation?.calculation || !this.situation?.assets_breakdown) return null;

      const summary = this.situation.iht_summary;
      const totalAllowances = (summary.current.nrb_available || 0) + (summary.current.rnrb_available || 0);

      return {
        // Raw breakdowns from IHTFormattingService
        assetsBreakdown: this.situation.assets_breakdown,
        liabilitiesBreakdown: this.situation.liabilities_breakdown,

        // Totals with 4-scenario shape (minus5/plus5 = 0, not displayed)
        totals: {
          grossAssets: {
            now: summary.current.gross_assets,
            projected: summary.projected.gross_assets,
            minus5: 0,
            plus5: 0,
          },
          liabilities: {
            now: summary.current.liabilities,
            projected: summary.projected.liabilities,
            minus5: 0,
            plus5: 0,
          },
          netEstate: {
            now: summary.current.net_estate,
            projected: summary.projected.net_estate,
            minus5: 0,
            plus5: 0,
          },
        },

        // Allowances (follow standardTableProps pattern)
        allowances: {
          nrb: summary.current.nrb_individual,
          nrbFromSpouse: summary.current.nrb_transferred,
          totalNrb: summary.current.nrb_available,
          rnrbIndividual: summary.current.rnrb_individual,
          rnrbFromSpouse: summary.current.rnrb_transferred,
          totalRnrb: summary.current.rnrb_available,
          rnrbEligible: (summary.current.rnrb_available || 0) > 0,
          rnrbTapered: false,
          rnrbTaperThreshold: 2000000,
          rnrbTaperAmount: 0,
          showSeparateSpouseAllowances: (summary.is_widowed &&
            ((summary.current.nrb_transferred || 0) > 0 ||
             (summary.current.rnrb_transferred || 0) > 0)) || false,
        },

        // Estate after NRB
        estateAfterNRB: {
          now: Math.max(0, (summary.current.net_estate || 0) - totalAllowances),
          projected: Math.max(0, (summary.projected.net_estate || 0) - totalAllowances),
          minus5: 0,
          plus5: 0,
        },

        // Taxable estate
        taxableEstate: {
          now: summary.current.taxable_estate,
          projected: summary.projected.taxable_estate,
          minus5: 0,
          plus5: 0,
        },

        // IHT liability
        ihtLiability: {
          now: summary.current.iht_liability,
          projected: summary.projected.iht_liability,
          minus5: 0,
          plus5: 0,
        },

        // Charitable donation (default zeros)
        charitableDonation: { now: 0, minus5: 0, projected: 0, plus5: 0 },

        // Display flags
        showSpouse: this.situation.has_linked_spouse && !!this.situation.assets_breakdown?.spouse,
        estimatedAge: summary.projected.estimated_age_at_death || 0,
        showMinus5Years: false,
        showPlus5Years: false,
        firstColumnHeader: 'Asset / Liability',
      };
    },
    hasLifeCover() {
      const lc = this.situation.life_cover;
      return lc && (lc.cover_in_trust > 0 || lc.cover_not_in_trust > 0 || lc.policy_count > 0);
    },
  },
};
</script>
