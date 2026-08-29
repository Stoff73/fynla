<template>
  <div v-if="definitions" class="bg-white rounded-lg border border-light-gray shadow-sm p-6">
    <h3 class="text-lg font-bold text-horizon-500 mb-6">Your Income Definitions</h3>

    <!-- Total Income -->
    <div class="flex justify-between items-baseline mb-1">
      <span class="text-body-sm font-semibold text-horizon-500">Total Income</span>
      <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.total_income) }}</span>
    </div>
    <p class="text-xs text-neutral-500 mb-4">
      <span v-for="(value, key) in activeComponents" :key="key">
        {{ componentLabel(key) }} {{ formatCurrency(value) }}
        <span v-if="!isLastActive(key)"> &middot; </span>
      </span>
    </p>

    <!-- Deductions to Net Income -->
    <!--
      W-0189. Named "Less pension relief" until 2026-08-22, while the identical
      figure lower down was named "Less employee pension contributions" — the same
      £11,600 under two names, which is most of why the column read as two
      deductions rather than one. `pension_relief` IS the employee contribution in
      `IncomeDefinitionsService`; one quantity, one name.
    -->
    <div v-if="definitions.deductions.pension_relief > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Less employee pension contributions</span>
      <span>-{{ formatCurrency(definitions.deductions.pension_relief) }}</span>
    </div>
    <!-- Net Income -->
    <div class="border-t border-light-gray pt-2 mt-2 mb-4">
      <div class="flex justify-between items-baseline">
        <span class="text-body-sm font-semibold text-horizon-500">Net Income</span>
        <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.net_income) }}</span>
      </div>
    </div>

    <!--
      Deductions to ANI.

      W-0205. The Gift Aid row used to sit above Net Income. Gift Aid is not one
      of the reliefs ITA 2007 s24 lists, so it does not reduce net income — a
      donation extends the basic rate band instead. It belongs here, at s58.

      The donation is deducted exactly once either
      way; what changed is which line it is deducted at, and therefore what the
      Net Income figure above is.

      W-0485. The Blind Person's Allowance used to be rendered in this block, and
      subtracted here, and it is neither a s58 deduction nor deducted any more. It
      is an s38 allowance applied at s23 Step 3 — AFTER adjusted net income has
      been struck — so it now sits below the Adjusted Net Income line, where the
      order on the page matches the order in the statute.
    -->
    <div v-if="definitions.deductions.gift_aid_gross > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Less Gift Aid (grossed up)</span>
      <span>-{{ formatCurrency(definitions.deductions.gift_aid_gross) }}</span>
    </div>

    <!-- Adjusted Net Income -->
    <div class="border-t border-light-gray pt-2 mt-2 mb-1">
      <div class="flex justify-between items-baseline">
        <span class="text-body-sm font-semibold text-horizon-500">Adjusted Net Income</span>
        <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_net_income) }}</span>
      </div>
    </div>

    <p class="text-xs text-neutral-500 mb-2">Used to work out your Personal Allowance.</p>

    <!--
      W-0485 — below the line, not above it. The Blind Person's Allowance is an
      ITA 2007 s38 allowance given at s23 Step 3, so it reduces the income that is
      taxed, not the adjusted net income that decides the Personal Allowance taper
      and the High Income Child Benefit Charge.
    -->
    <div v-if="definitions.deductions.blind_persons_allowance > 0" class="flex justify-between text-body-sm text-neutral-500 mb-1">
      <span>Blind Person's Allowance (applied to taxable income)</span>
      <span>{{ formatCurrency(definitions.deductions.blind_persons_allowance) }}</span>
    </div>
    <p v-if="definitions.deductions.blind_persons_allowance > 0" class="text-xs text-neutral-500 mb-6">
      An allowance against the income you are taxed on. It does not change your Adjusted Net Income.
    </p>
    <div v-else class="mb-4"></div>

    <!--
      W-0189. Threshold Income and Adjusted Income are NOT the next two steps of the
      column above. Both are worked out from Total Income, and the employee
      contribution they involve is the same one already taken out at Net Income.

      Presented as a running column, the panel showed "Less employee pension
      contributions -\u00a311,600" between two figures that were both \u00a3147,690, and
      "Plus employer pension contributions +\u00a311,600" above a figure \u00a311,600 higher
      than the one two rows up but \u00a323,200 higher than the line it sat under. Two of
      three steps did not produce the figure beneath them. Nothing was wrong with the
      arithmetic; the deduction really is applied once, not twice. What was wrong was
      showing it as a step that had been applied a second time.

      So each figure now states its own working from the base it actually uses. The
      reader can check both by hand against Total Income.
    -->
    <div class="border-t border-light-gray pt-4 mb-4">
      <p class="text-xs text-neutral-500">
        Threshold Income and Adjusted Income are each worked out from your Total Income above, not from your Adjusted Net Income.
      </p>
    </div>

    <!-- Threshold Income -->
    <div class="flex justify-between items-baseline mb-1">
      <span class="text-body-sm font-semibold text-horizon-500">Threshold Income</span>
      <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.threshold_income) }}</span>
    </div>
    <p class="text-xs text-neutral-500 mb-1">{{ thresholdIncomeWorking }}</p>
    <p v-if="pensionArrangementNote" class="text-xs text-neutral-500 mb-1">{{ pensionArrangementNote }}</p>
    <p class="text-xs mb-6" :class="definitions.threshold_income > pensionTaperThresholdIncome ? 'text-raspberry-500' : 'text-spring-500'">
      {{ definitions.threshold_income > pensionTaperThresholdIncome ? `Above ${formatCurrency(pensionTaperThresholdIncome)} \u2014 pension taper may apply` : `Below ${formatCurrency(pensionTaperThresholdIncome)} \u2014 no pension taper triggered` }}
    </p>

    <!-- Adjusted Income -->
    <div class="flex justify-between items-baseline mb-1">
      <span class="text-body-sm font-semibold text-horizon-500">Adjusted Income</span>
      <span class="text-body font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_income) }}</span>
    </div>
    <p class="text-xs text-neutral-500 mb-1">{{ adjustedIncomeWorking }}</p>
    <p class="text-xs mb-6" :class="definitions.adjusted_income > pensionTaperAdjustedIncome ? 'text-raspberry-500' : 'text-spring-500'">
      {{ definitions.adjusted_income > pensionTaperAdjustedIncome ? `Above ${formatCurrency(pensionTaperAdjustedIncome)} \u2014 Annual Allowance reduced` : `Below ${formatCurrency(pensionTaperAdjustedIncome)} \u2014 full Annual Allowance available` }}
    </p>

    <!-- Adjusted Allowances -->
    <div class="bg-eggshell-500 rounded-lg p-4">
      <h4 class="text-sm font-bold text-horizon-500 mb-3">Your Allowances</h4>
      <div class="space-y-2">
        <div class="flex justify-between items-center">
          <span class="text-body-sm text-horizon-500">Personal Allowance</span>
          <div class="text-right">
            <span class="text-body-sm font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_allowances.personal_allowance) }}</span>
            <span v-if="definitions.adjusted_allowances.personal_allowance_tapered" class="text-xs text-raspberry-500 ml-2">
              (reduced from {{ formatCurrency(definitions.adjusted_allowances.personal_allowance_full) }})
            </span>
            <span v-else class="text-xs text-spring-500 ml-2">(full)</span>
          </div>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-body-sm text-horizon-500">Pension Annual Allowance</span>
          <div class="text-right">
            <span class="text-body-sm font-bold text-horizon-500">{{ formatCurrency(definitions.adjusted_allowances.pension_annual_allowance) }}</span>
            <span v-if="definitions.adjusted_allowances.pension_aa_tapered" class="text-xs text-raspberry-500 ml-2">
              (reduced from {{ formatCurrency(definitions.adjusted_allowances.pension_annual_allowance_full) }})
            </span>
            <span v-else class="text-xs text-spring-500 ml-2">(full)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { formatCurrency } from '@/utils/currency';

export default {
  name: 'IncomeDefinitionsPanel',
  props: {
    definitions: {
      type: Object,
      default: null,
    },
  },
  setup() {
    const store = useStore();
    return {
      formatCurrency,
      pensionTaperThresholdIncome: computed(() => store.getters['taxConfig/pensionTaperThresholdIncome']),
      pensionTaperAdjustedIncome: computed(() => store.getters['taxConfig/pensionTaperAdjustedIncome']),
    };
  },
  computed: {
    activeComponents() {
      if (!this.definitions?.components) return {};
      return Object.fromEntries(
        Object.entries(this.definitions.components).filter(([, v]) => v > 0)
      );
    },

    /**
     * The working behind Threshold Income, stated from the base it actually uses.
     *
     * Every figure named here appears elsewhere on the panel, so the subtraction
     * can be checked by hand: Total Income less the employee contribution already
     * shown above. Where there is no contribution the two figures are equal and the
     * sentence says so rather than implying a step happened.
     */
    thresholdIncomeWorking() {
      const total = this.formatCurrency(this.definitions.total_income);
      const employee = this.definitions.deductions.employee_pension_contributions;

      if (!(employee > 0)) {
        return `The same as your Total Income of ${total} — you have no employee pension contributions to deduct.`;
      }

      return `Your Total Income of ${total}, less the ${this.formatCurrency(employee)} you paid into your pension.`;
    },

    /**
     * The working behind Adjusted Income. Note the base: Total Income, not the
     * Threshold Income immediately above it. Adding the employer contribution to
     * Threshold Income gives a different, wrong number, which is what the old
     * layout invited the reader to do.
     */
    adjustedIncomeWorking() {
      const total = this.formatCurrency(this.definitions.total_income);
      const employer = this.definitions.deductions.employer_pension_contributions;

      if (!(employer > 0)) {
        return `The same as your Total Income of ${total} — you have no employer pension contributions to add.`;
      }

      return `Your Total Income of ${total}, plus the ${this.formatCurrency(employer)} your employer paid into your pension.`;
    },

    /**
     * Why the employee contribution is deducted once and not at both steps that
     * name it. `pension_arrangement` is published by `IncomeDefinitionsService`
     * and describes the treatment that was applied, not one this panel infers.
     */
    pensionArrangementNote() {
      switch (this.definitions.pension_arrangement) {
        case 'net_pay':
          return 'Your contributions are taken from your pay before tax, so they come out of your Total Income once. The same amount is not deducted again here.';
        case 'salary_sacrifice':
          // W-0204 — states the treatment that IS applied. The wording it replaces
          // was written to be truthful about a gap, and the gap is closed: the pay
          // given up is added back to Threshold Income under FA 2004 s228ZA(3), and
          // the contribution counts as your employer's because that is what it is.
          return this.definitions.employment_income_basis === 'assumed_gross'
            ? 'One of your workplace pensions uses salary sacrifice, so the pay you give up counts as your employer\'s contribution, not yours, and it is added back to your Threshold Income. Tell us whether the Employment Income you recorded is before or after the pay you give up, and we can be exact about the rest of your figures.'
            : 'One of your workplace pensions uses salary sacrifice. The pay you give up counts as your employer\'s contribution rather than yours, and it is added back to your Threshold Income — that is what decides whether your Annual Allowance is reduced.';
        default:
          return null;
      }
    },
  },
  methods: {
    componentLabel(key) {
      const labels = {
        employment: 'Employment',
        self_employment: 'Self-Employment',
        // Rent less allowable letting expenses — the same figure the tax
        // computation on this page uses, not gross rent (W-0175).
        rental: 'Rental profit',
        dividend: 'Dividends',
        interest: 'Interest',
        other: 'Other',
        trust: 'Trust',
        pension_income: 'Pension',
      };
      return labels[key] || key;
    },
    isLastActive(key) {
      const keys = Object.keys(this.activeComponents);
      return keys.indexOf(key) === keys.length - 1;
    },
  },
};
</script>
