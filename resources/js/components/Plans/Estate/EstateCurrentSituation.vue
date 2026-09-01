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
          <!--
            W-0136 — the projected residence-band position, which differs from
            today's the moment the projected estate crosses the taper threshold.
          -->
          <p
            v-if="situation.projected_rnrb_message && situation.projected_rnrb_message !== situation.rnrb_message"
            class="text-xs text-neutral-500"
          >
            <span class="font-medium text-neutral-500">Residence Nil Rate Band at age {{ situation.iht_summary?.projected?.estimated_age_at_death }}:</span>
            {{ situation.projected_rnrb_message }}
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
            <p class="text-xs text-neutral-500">{{ charitableThresholdRateLabel }}</p>
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
        <!--
          W-0451 C1. Every figure above describes the survivor's will, and this
          panel said so nowhere — so read from the other spouse's account it
          presented that person's charitable position as the reader's own.

          The sentence is composed by the server and printed verbatim. It is NOT
          written here, because `planPrintMixin.js` draws the same panel for the
          printed plan and two copies of one sentence is the drift Rule 20
          forbids. The pair used to duplicate the "Threshold for 36% Rate" label
          as well; W-0461 converged it on `@/utils/estateRateLabels`, which both
          mechanisms now call.

          Empty for a single person, and whenever the reader IS the survivor.
        -->
        <p v-if="situation.charitable_giving.basis" class="text-xs text-neutral-500 mt-3">
          {{ situation.charitable_giving.basis }}
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { charitableThresholdRateLabel } from '@/utils/estateRateLabels';
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
    ...mapGetters('taxConfig', ['ihtRnrbTaperThreshold', 'ihtReducedRate']),

    /**
     * W-0461 instance 2. Composed in `@/utils/estateRateLabels` because
     * `planPrintMixin` draws this same panel for the printed plan — the
     * duplicate the comment below records is now one source, not two.
     */
    charitableThresholdRateLabel() {
      return charitableThresholdRateLabel(this.ihtReducedRate);
    },

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

        // Allowances (follow standardTableProps pattern).
        //
        // W-0134 — `nrbFromSpouseModelled` and `nrbGiftDeduction` are carried now.
        // Without them the plan's table printed £325,000 + £325,000 above a
        // £500,000 subtotal, exactly as the drill-down did, and the £150,000 of
        // chargeable transfers that made up the difference had no row.
        allowances: {
          nrb: summary.current.nrb_individual,
          nrbFromSpouseModelled: summary.current.nrb_spouse_modelled || 0,
          nrbGiftDeduction: summary.current.nrb_gift_deduction || 0,
          nrbFromSpouse: summary.current.nrb_transferred,
          totalNrb: summary.current.nrb_available,
          rnrbIndividual: summary.current.rnrb_individual,
          rnrbFromSpouse: summary.current.rnrb_transferred,
          totalRnrb: summary.current.rnrb_available,
          rnrbEligible: (summary.current.rnrb_available || 0) > 0,
          rnrbStatus: summary.current.rnrb_status || 'none',
          rnrbTaperThreshold: this.ihtRnrbTaperThreshold,
          showSeparateSpouseAllowances: this.showSeparateSpouseAllowances,
        },

        // W-0136 — the allowances AT DEATH, which are not the current ones: the
        // residence band tapers away above the threshold and is frequently
        // extinguished on a projection.
        allowancesProjected: {
          nrb: summary.current.nrb_individual,
          nrbFromSpouseModelled: summary.current.nrb_spouse_modelled || 0,
          nrbGiftDeduction: summary.current.nrb_gift_deduction || 0,
          nrbFromSpouse: summary.current.nrb_transferred,
          totalNrb: summary.projected.nrb_available ?? summary.current.nrb_available,
          rnrbIndividual: summary.projected.rnrb_individual || 0,
          rnrbFromSpouse: summary.projected.rnrb_transferred || 0,
          totalRnrb: summary.projected.rnrb_available || 0,
          rnrbEligible: (summary.projected.rnrb_available || 0) > 0,
          rnrbStatus: summary.projected.rnrb_status || 'none',
          rnrbTaperThreshold: this.ihtRnrbTaperThreshold,
          showSeparateSpouseAllowances: this.showSeparateSpouseAllowances,
        },

        // W-0465 G2 — Business Property Relief, in BOTH columns.
        //
        // Without this row a business-owning household reads Gross, Liabilities and a
        // Net Estate lower than gross − liabilities by up to £4,250,000, with nothing
        // saying why. Before the engine fix the projected net estate at least appeared
        // to add up — wrongly. **The number is now right and the column was less
        // readable than before**, which is the worse failure of the two.
        //
        // This is round one's F3 recurring on the second surface: F3 was fixed for
        // `IHTPlanning.vue` only, and `EstateCurrentSituation.vue` enumerates its own
        // props (tax-compliance-reviewer, round five).
        businessRelief: {
          now: summary.current.business_relief_deduction || 0,
          minus5: summary.current.business_relief_deduction || 0,
          projected: summary.projected.business_relief_deduction || 0,
          plus5: summary.projected.business_relief_deduction || 0,
        },

        // The caveat the engine publishes with the figure. `?? null` rather than
        // `|| ''` so "no caveat applies" stays distinguishable from "not published".
        unmodelledReliefCaveat: summary.current.unmodelled_relief_caveat ?? null,

        // The charitable legacies the server actually deducted (IHTA 1984 s23).
        charitableExemption: {
          now: summary.current.charitable_deduction || 0,
          minus5: summary.current.charitable_deduction || 0,
          projected: summary.projected.charitable_deduction || 0,
          plus5: summary.projected.charitable_deduction || 0,
        },

        // Estate after NRB
        estateAfterNRB: {
          now: Math.max(0, (summary.current.net_estate || 0) - totalAllowances),
          projected: Math.max(0, (summary.projected.net_estate || 0) - (summary.projected.total_allowances ?? totalAllowances)),
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
    showSeparateSpouseAllowances() {
      const summary = this.situation?.iht_summary;
      if (!summary) return false;

      return (summary.is_widowed &&
        ((summary.current.nrb_transferred || 0) > 0 ||
         (summary.current.rnrb_transferred || 0) > 0)) || false;
    },
    hasLifeCover() {
      const lc = this.situation.life_cover;
      return lc && (lc.cover_in_trust > 0 || lc.cover_not_in_trust > 0 || lc.policy_count > 0);
    },
  },
};
</script>
