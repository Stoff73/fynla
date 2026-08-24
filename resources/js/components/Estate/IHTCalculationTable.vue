<template>
  <div class="overflow-x-auto">
    <!-- Expand/Collapse All Button -->
    <div class="flex justify-end mb-2">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100 rounded-md transition-colors"
        @click="toggleExpandAll"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
          <path v-if="allExpanded" stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
        {{ allExpanded ? 'Collapse All' : 'Expand All' }}
      </button>
    </div>
    <table class="min-w-full divide-y divide-light-gray">
      <thead class="bg-eggshell-500">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ firstColumnHeader }}</th>
          <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Now</th>
          <th v-if="showMinus5Years" class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            <div>Age {{ projectionMinus5Age }}</div>
            <div class="text-[10px] font-normal text-horizon-400 normal-case mt-0.5">-5 years</div>
          </th>
          <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            <div>Age {{ estimatedAge }}</div>
            <div class="text-[10px] font-normal text-horizon-400 normal-case mt-0.5">Life expectancy</div>
          </th>
          <th v-if="showPlus5Years" class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            <div>Age {{ projectionPlus5Age }}</div>
            <div class="text-[10px] font-normal text-horizon-400 normal-case mt-0.5">+5 years</div>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-light-gray">
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
        <tr :class="showSpouse ? 'bg-white ' : 'bg-white  border-t-2 border-horizon-300'">
          <td class="px-4 py-3 text-sm font-bold text-horizon-500">Total Gross Assets</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.plus5) }}</td>
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
        <tr :class="showSpouse ? 'bg-white ' : 'bg-white  border-t-2 border-horizon-300'">
          <td class="px-4 py-3 text-sm font-bold text-horizon-500">{{ showSpouse ? 'Less: Total Liabilities' : 'Total Liabilities' }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.plus5) }}</td>
        </tr>

        <!--
          C3 (tax-compliance-reviewer F3). Business Property Relief takes value out
          of the chargeable estate, so without this row Gross Assets less Liabilities
          does not reach Net Estate for anyone holding a qualifying business — the
          exact defect W-0154 was raised to fix, reproduced one line down. Hidden
          when zero, which is every estate without one.
        -->
        <tr v-if="showBusinessRelief" class="bg-white">
          <td class="px-4 py-3 text-sm text-horizon-500 pl-8">
            Less: Business Property Relief
            <!--
              W-0466. This sub-line used to repeat the caveat's own claim —
              "Does not model Agricultural Property Relief or AIM shares" — which was
              a SECOND HOME for it (Rule 20) and a Rule 9 breach the caveat rewrite
              missed, because it fixed the engine's sentence and not this copy of it.
              The caveat itself renders below the table, from the engine, once.
            -->
            <span class="block text-body-xs text-neutral-500">
              Relief on qualifying business assets.
            </span>
          </td>
          <td class="px-4 py-3 text-sm text-right text-horizon-500">{{ formatLiability(businessRelief.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right text-horizon-500">{{ formatLiability(businessRelief.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right text-horizon-500">{{ formatLiability(businessRelief.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right text-horizon-500">{{ formatLiability(businessRelief.plus5) }}</td>
        </tr>

        <!-- Net Estate -->
        <tr class="bg-white ">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">Net Estate</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.plus5) }}</td>
        </tr>

        <!--
          W-0132 — one layout, the user's own position.

          These rows used to be the "charitable bequest OFF" half of a `v-if` /
          `v-else` pair. Answering Yes to a toggle on /estate swapped in a second
          allowance layout that deducted an ASSUMED donation of 10% of baseline and
          applied the reduced rate to the result — a client-side model of a gift the
          user had not made, standing in for their real position, on the page whose
          job is to show their real position.

          The server reads the recorded will, pools the household's legacies for the
          IHTA 1984 s23 exemption and runs the 10% test against the survivor's
          estate. That is the answer, and this table now renders it and computes
          nothing.
        -->
        <!-- Allowances Section Header (Collapsible) -->
        <tr class="bg-white  cursor-pointer hover:bg-eggshell-500 select-none" @click="toggleAllowances">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': expandedAllowances }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              Less: Tax-Free Allowances
            </span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.plus5) }}</td>
        </tr>

        <!--
          W-0134 — the allowance rows are built by `allowanceRows()` rather than
          written out per marital branch, because they have to ADD UP and fourteen
          near-identical hand-written rows across four branches did not.

          What the reader saw before: four rows of £325,000 / £325,000 / £175,000 /
          £175,000 summing to £1,000,000, above a subtotal of £850,000. The £150,000
          of chargeable transfers that produced the difference had no row anywhere
          and lived only in a sentence appended to a message string, and the single
          and widowed branches printed a nil-rate-band figure that was ALREADY net
          of that deduction with the deduction then shown again beneath it.

          Every row now carries its own value for each column. That matters more
          than it used to: since W-0136 the residence band is assessed against the
          estate it applies to, so the projected allowance is genuinely a different
          number from today's and one figure printed beside both columns is wrong
          in at least one of them.
        -->
        <template v-if="expandedAllowances">
          <tr v-for="row in allowanceRows" :key="row.key" class="bg-eggshell-500">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
              {{ row.label }}
              <span v-if="row.note" class="block text-xs text-neutral-500 mt-0.5">{{ row.note }}</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ row.sign }}{{ formatCurrency(row.now) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ row.sign }}{{ formatCurrency(row.minus5) }}</td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ row.sign }}{{ formatCurrency(row.projected) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ row.sign }}{{ formatCurrency(row.plus5) }}</td>
          </tr>

          <!-- Residence Nil Rate Band Not Available Message -->
          <tr v-if="!allowances.rnrbEligible && !projectedAllowances.rnrbEligible" class="bg-eggshell-500">
            <td :colspan="columnCount" class="px-4 py-2 text-xs text-neutral-500 pl-8">
              <strong>Note:</strong> Home allowance not available - no main residence identified or not left to direct descendants
            </td>
          </tr>

          <!-- Residence band reduction note, stated per column because the two differ -->
          <tr v-if="residenceBandNote" class="bg-eggshell-500">
            <td :colspan="columnCount" class="px-4 py-2 text-xs text-neutral-500 pl-8">
              <strong>Home Allowance Reduced:</strong> {{ residenceBandNote }}
            </td>
          </tr>

          <!-- Allowances Subtotal -->
          <tr class="bg-white ">
            <td class="px-4 py-2 text-sm font-semibold text-horizon-500 pl-8">Subtotal</td>
            <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.now) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.minus5) }}</td>
            <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.projected) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances.plus5) }}</td>
          </tr>
        </template>

        <!--
          W-0134 — the charitable exemption, as its own row BELOW the allowance
          block and outside it.

          It is not an allowance. IHTA 1984 s23 removes the legacy from the estate's
          transferable value; putting it inside the allowance subtotal would make the
          column add up while misstating the law. It is rendered whenever the server
          applied one. The old row was gated on the `charitableBequest` what-if
          toggle, so a deduction the server had already made was invisible on the
          page that made it and the column came out £20,000 short between Net Estate
          and Taxable Estate. That toggle no longer exists (W-0132).
        -->
        <tr v-if="hasCharitableExemption" class="bg-white ">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
            Less: Charitable Legacies
            <span class="block text-xs font-normal text-neutral-500 mt-0.5">Exempt from Inheritance Tax</span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(charitableExemption.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(charitableExemption.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(charitableExemption.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(charitableExemption.plus5) }}</td>
        </tr>

        <!-- Taxable Estate -->
        <tr class="bg-eggshell-500">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">Taxable Estate</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.plus5) }}</td>
        </tr>

        <!-- Inheritance Tax Liability -->
        <tr class="bg-white ">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
            Inheritance Tax Liability<span v-if="ihtRateLabel"> ({{ ihtRateLabel }})</span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.plus5) }}</td>
        </tr>
      </tbody>
    </table>

    <!--
      W-0466. Rendered here so every surface showing this table shows the caveat with
      it. `/plans/estate` printed an unqualified Inheritance Tax figure because the
      caveat markup lived in the other parent (tax-compliance-reviewer round five, G2).
    -->
    <div v-if="unmodelledReliefCaveat" class="bg-eggshell-500 rounded-lg p-4 mt-6">
      <h3 class="text-sm font-semibold text-violet-800">What this figure does not include</h3>
      <p class="mt-2 text-sm text-violet-800">{{ unmodelledReliefCaveat }}</p>
    </div>
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

    // Allowances as they stand TODAY.
    allowances: {
      type: Object,
      required: true,
      // Expected: { nrb, nrbFromSpouseModelled, nrbFromSpouse, nrbGiftDeduction, totalNrb,
      //   rnrbIndividual, rnrbFromSpouse, totalRnrb, rnrbEligible, rnrbStatus,
      //   rnrbTaperThreshold, showSeparateSpouseAllowances }
    },

    // Allowances AT DEATH. Same shape. Omitting it falls back to the current set,
    // which is what every caller effectively asserted before W-0136 — and it was
    // wrong for any household projected past the £2,000,000 residence-band taper
    // threshold, which is most of the ones that owe Inheritance Tax at all.
    allowancesProjected: {
      type: Object,
      default: null,
    },

    // The charitable legacies the server actually deducted (IHTA 1984 s23). The
    // `charitableDonation` what-if amount this was distinguished from is gone —
    // see W-0132 on the allowance block above.
    charitableExemption: {
      type: Object,
      default: () => ({ now: 0, minus5: 0, projected: 0, plus5: 0 }),
    },

    // C3 — Business Property Relief, deducted between Liabilities and Net Estate.
    // Defaults to zero so the row simply does not render for the overwhelming
    // majority of estates, which hold no qualifying business.
    businessRelief: {
      type: Object,
      default: () => ({ now: 0, minus5: 0, projected: 0, plus5: 0 }),
    },

    // W-0466 G3/G2 — the caveat lives HERE, with the table, rather than beside it
    // in each parent. Two surfaces render this component — the Inheritance Tax
    // screen and `/plans/estate` — and the first had the caveat while the second
    // printed an unqualified figure, because each parent enumerates its own
    // markup. One home, so a third consumer cannot inherit the gap (Rule 20).
    //
    // The SENTENCE is still the engine's; this only decides where it appears.
    unmodelledReliefCaveat: {
      type: String,
      default: null,
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

    // W-0132 — the rate the liability beside it was calculated at, as the server
    // reported it, stating the current and projected columns separately when they
    // differ. This was `effectiveIHTRateLabel`, a string the parent built from two
    // HARDCODED literals ('36%' / '40%') chosen by a user toggle — so the row
    // labelled its own figure with a rate that figure had not been computed at, and
    // bypassed the configured rates entirely (Rule 2). Its default of '40%' meant a
    // caller that passed nothing still asserted a rate. There is no default now: no
    // rate, no label.
    ihtRateLabel: {
      type: String,
      default: null,
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
      expandedAllowances: false,
    };
  },

  computed: {
    columnCount() {
      return 2 + (this.showMinus5Years ? 1 : 0) + (this.showPlus5Years ? 1 : 0);
    },

    projectedAllowances() {
      return this.allowancesProjected || this.allowances;
    },

    // Kept from the per-branch markup this replaced: a widow with no linked spouse
    // record is shown an assumed transfer, and needs telling that it is assumed.
    transferredAllowanceNote() {
      return this.hasSpouseLinked ? null : 'Default assumption — no spouse record is linked.';
    },

    totalAllowances() {
      const now = this.allowances.totalNrb + this.allowances.totalRnrb;
      const projected = this.projectedAllowances.totalNrb + this.projectedAllowances.totalRnrb;

      return { now, minus5: now, projected, plus5: projected };
    },

    /**
     * Every itemised allowance row, with its own value in every column.
     *
     * W-0134. The invariant this enforces, in all four marital branches:
     *
     *   individual band
     *     + modelled spouse band (married) or transferred band (widowed)
     *     − chargeable transfers in the last seven years
     *     + residence band
     *     = the subtotal printed beneath
     *
     * The individual row deliberately shows the GROSS band. It used to show
     * `totalNrb` in the single and widowed branches — already net of the gift
     * deduction — with the deduction then rendered again below it, so those two
     * branches double-counted it and could not be added by hand either.
     */
    allowanceRows() {
      return [...this.nilRateBandRows, ...this.residenceBandRows];
    },

    nilRateBandRows() {
      const now = this.allowances;
      const proj = this.projectedAllowances;
      const rows = [];
      const push = (key, label, nowValue, projValue, sign = '-', note = null) => {
        rows.push({
          key,
          label,
          note,
          sign,
          now: nowValue || 0,
          minus5: nowValue || 0,
          projected: projValue || 0,
          plus5: projValue || 0,
        });
      };

      const userName = this.assetsBreakdown.user?.name;
      const spouseName = this.assetsBreakdown.spouse?.name;

      if (now.showSeparateSpouseAllowances) {
        // Widowed: an individual band plus a band actually transferred on the
        // first death (IHTA 1984 s8A). This one IS an allowance held today.
        push('nrb-individual', 'Tax-Free Allowance (Individual)', now.nrb, proj.nrb);
        if ((now.nrbFromSpouse || 0) > 0 || (proj.nrbFromSpouse || 0) > 0) {
          push('nrb-transferred', "Transferred from Late Spouse's Estate", now.nrbFromSpouse, proj.nrbFromSpouse, '-', this.transferredAllowanceNote);
        }
      } else if (this.showSpouse) {
        push('nrb-individual', userName ? `${userName}'s Tax-Free Allowance` : 'Your Tax-Free Allowance', now.nrb, proj.nrb);
        // W-0134(b). This row is NOT an allowance the household holds today.
        // There is no transferable nil rate band while both spouses are alive; the
        // claim arises on the survivor's death, and this service models to that
        // second death. Labelling it is the fix — writing it into `nrb_transferred`
        // would make the column add up and the payload wrong.
        push(
          'nrb-spouse-modelled',
          spouseName ? `${spouseName}'s Tax-Free Allowance` : "Spouse's Tax-Free Allowance",
          now.nrbFromSpouseModelled,
          proj.nrbFromSpouseModelled,
          '-',
          'Modelled on second death — there is no transferable allowance while you are both alive.',
        );
      } else {
        push('nrb-individual', 'Tax-Free Allowance (Nil Rate Band)', now.nrb, proj.nrb);
      }

      // Gifts in the last seven years reduce the band, so this is an ADDITION to
      // the taxable estate and is signed the opposite way to the rows above it.
      if ((now.nrbGiftDeduction || 0) > 0 || (proj.nrbGiftDeduction || 0) > 0) {
        push(
          'nrb-gift-deduction',
          'Less allowance used by gifts in the last 7 years',
          now.nrbGiftDeduction,
          proj.nrbGiftDeduction,
          '+',
        );
      }

      return rows;
    },

    showBusinessRelief() {
      return (this.businessRelief?.now || 0) > 0.5 || (this.businessRelief?.projected || 0) > 0.5;
    },

    residenceBandRows() {
      const now = this.allowances;
      const proj = this.projectedAllowances;
      const rows = [];
      const push = (key, label, nowValue, projValue, sign = '-', note = null) => {
        rows.push({
          key,
          label,
          note,
          sign,
          now: nowValue || 0,
          minus5: nowValue || 0,
          projected: projValue || 0,
          plus5: projValue || 0,
        });
      };

      const userName = this.assetsBreakdown.user?.name;
      const spouseName = this.assetsBreakdown.spouse?.name;

      if (now.rnrbEligible || proj.rnrbEligible) {
        if (now.showSeparateSpouseAllowances) {
          // The gross components can exceed what is available once the residence
          // cap or the estate taper bites, so the reduction gets its own row
          // rather than leaving the reader with a subtotal they cannot reach.
          push('rnrb-individual', 'Home Allowance (Individual)', now.rnrbIndividual, proj.rnrbIndividual);
          if ((now.rnrbFromSpouse || 0) > 0 || (proj.rnrbFromSpouse || 0) > 0) {
            push('rnrb-transferred', "Home Allowance Transferred from Late Spouse's Estate", now.rnrbFromSpouse, proj.rnrbFromSpouse, '-', this.transferredAllowanceNote);
          }
          const nowReduction = Math.max(0, (now.rnrbIndividual || 0) + (now.rnrbFromSpouse || 0) - (now.totalRnrb || 0));
          const projReduction = Math.max(0, (proj.rnrbIndividual || 0) + (proj.rnrbFromSpouse || 0) - (proj.totalRnrb || 0));
          if (nowReduction > 0.5 || projReduction > 0.5) {
            push('rnrb-reduction', 'Less home allowance reduced by the value of your estate', nowReduction, projReduction, '+');
          }
        } else if (this.showSpouse) {
          // W-0154 F2. These two rows used to be `totalRnrb / 2` each — the total
          // halved and presented as though it were two measured components. It
          // reconciles only while the halves are equal, and they stop being equal
          // the moment the residence cap or the £2m taper bites, at which point
          // the table shows two numbers that sum to the total by construction and
          // describe nothing. The backend publishes the real components now.
          push('rnrb-user', userName ? `${userName}'s Home Allowance` : 'Your Home Allowance', now.rnrbIndividual, proj.rnrbIndividual);
          push(
            'rnrb-spouse-modelled',
            spouseName ? `${spouseName}'s Home Allowance` : "Spouse's Home Allowance",
            now.rnrbSpouseModelled,
            proj.rnrbSpouseModelled,
            '-',
            'Modelled on second death — there is no transferable home allowance while you are both alive.',
          );

          // The two reductions are separate facts and are shown separately: one is
          // "your home is worth less than the allowance", the other is "your estate
          // is too large to keep it". Collapsing them into a single residual would
          // tell a reader their band was reduced without telling them why.
          if ((now.rnrbResidenceCapReduction || 0) > 0.5 || (proj.rnrbResidenceCapReduction || 0) > 0.5) {
            push(
              'rnrb-residence-cap',
              'Less home allowance capped at the value of your home',
              now.rnrbResidenceCapReduction,
              proj.rnrbResidenceCapReduction,
              '+',
            );
          }
          if ((now.rnrbTaperReduction || 0) > 0.5 || (proj.rnrbTaperReduction || 0) > 0.5) {
            push(
              'rnrb-taper',
              'Less home allowance reduced by the size of your estate',
              now.rnrbTaperReduction,
              proj.rnrbTaperReduction,
              '+',
            );
          }
        } else {
          push('rnrb-single', 'Home Allowance (Residence Nil Rate Band)', now.totalRnrb, proj.totalRnrb);
        }
      }

      return rows;
    },

    /**
     * Why the residence band is lower than its maximum — stated per column,
     * because since W-0136 the two columns can differ. The footnote beneath the
     * table asserted "your combined estate is below the £2,000,000 taper
     * threshold" while the projected column showed £4.37m.
     */
    residenceBandNote() {
      const threshold = this.formatCurrency(this.allowances.rnrbTaperThreshold || 2000000);
      const describe = (allowances) => {
        if (allowances.rnrbStatus === 'tapered') {
          return (allowances.totalRnrb || 0) === 0
            ? `above the ${threshold} taper threshold, removing the home allowance entirely`
            : `above the ${threshold} taper threshold, reducing the home allowance to ${this.formatCurrency(allowances.totalRnrb)}`;
        }
        if (allowances.rnrbStatus === 'residence_capped') {
          return `capped at the net value of your main residence, ${this.formatCurrency(allowances.totalRnrb)}`;
        }

        return null;
      };

      const nowNote = describe(this.allowances);
      const projectedNote = describe(this.projectedAllowances);

      if (!nowNote && !projectedNote) return null;
      if (nowNote && projectedNote && nowNote === projectedNote) return `Your estate is ${nowNote}.`;

      const parts = [];
      if (nowNote) parts.push(`Today your estate is ${nowNote}.`);
      if (projectedNote) parts.push(`At age ${this.estimatedAge} your estate is ${projectedNote}.`);

      return parts.join(' ');
    },

    hasCharitableExemption() {
      return (this.charitableExemption.now || 0) > 0 || (this.charitableExemption.projected || 0) > 0;
    },

    allExpanded() {
      // Check if all expandable sections are expanded
      const hasExpandedAssets = Object.keys(this.expandedAssets).length > 0 &&
        Object.values(this.expandedAssets).some(v => v);
      const hasExpandedLiabilities = Object.keys(this.expandedLiabilities).length > 0 &&
        Object.values(this.expandedLiabilities).some(v => v);
      const hasExpandedAllowances = this.expandedAllowances;

      return hasExpandedAssets || hasExpandedLiabilities || hasExpandedAllowances;
    },
  },

  methods: {
    toggleAssetGroup(key) {
      this.expandedAssets = { ...this.expandedAssets, [key]: !this.expandedAssets[key] };
    },

    toggleLiabilityGroup(key) {
      this.expandedLiabilities = { ...this.expandedLiabilities, [key]: !this.expandedLiabilities[key] };
    },

    toggleAllowances() {
      this.expandedAllowances = !this.expandedAllowances;
    },

    toggleExpandAll() {
      const shouldExpand = !this.allExpanded;

      // Toggle all asset groups (keys use format: ownerKey-type)
      const assetKeys = [
        'user-all', 'user-property', 'user-investment', 'user-cash', 'user-business', 'user-chattel',
        'spouse-all', 'spouse-property', 'spouse-investment', 'spouse-cash', 'spouse-business', 'spouse-chattel'
      ];
      const newExpandedAssets = {};
      assetKeys.forEach(key => {
        newExpandedAssets[key] = shouldExpand;
      });
      this.expandedAssets = newExpandedAssets;

      // Toggle all liability groups (keys use format: ownerKey-type)
      const liabilityKeys = ['user-all', 'user-mortgages', 'user-other', 'spouse-all', 'spouse-mortgages', 'spouse-other'];
      const newExpandedLiabilities = {};
      liabilityKeys.forEach(key => {
        newExpandedLiabilities[key] = shouldExpand;
      });
      this.expandedLiabilities = newExpandedLiabilities;

      // Toggle allowances. There is one allowance section now — the separate
      // nil-rate-band and residence-band sections belonged to the what-if layout
      // deleted under W-0132.
      this.expandedAllowances = shouldExpand;
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
