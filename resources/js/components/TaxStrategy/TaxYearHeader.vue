<template>
  <header class="mb-6">
    <p class="text-caption text-neutral-500 uppercase tracking-wide mb-2">
      Tax year {{ taxYear }}
    </p>
    <h1 class="text-h1 font-black text-horizon-500 mb-3 leading-tight">
      {{ headline }}
    </h1>
    <p class="text-body text-neutral-500 max-w-3xl">
      {{ subline }}
    </p>
  </header>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TaxYearHeader',
  mixins: [currencyMixin],
  computed: {
    ...mapGetters('taxStrategy', ['taxYear', 'recommendations', 'composedPlan']),
    ...mapGetters('auth', ['currentUser']),
    firstName() {
      return this.currentUser?.first_name || 'there';
    },
    totalSavings() {
      // Prefer the composed plan's realisable total — it excludes the
      // lower-saving member of each conflict pair, matching the figure Fyn
      // quotes in the chat synthesis. Fall back to the raw sum when the
      // composed plan is absent (older cached payloads).
      const composed = Number(this.composedPlan?.combined_annual_saving);
      if (Number.isFinite(composed) && composed > 0) {
        return composed;
      }
      return this.recommendations
        .filter((r) => r.category !== 'warning')
        .reduce((sum, r) => sum + (Number(r.estimated_annual_tax_saved) || 0), 0);
    },
    actionableCount() {
      return this.recommendations.filter(
        (r) => r.category !== 'warning' && Number(r.estimated_annual_tax_saved) > 0,
      ).length;
    },
    warningCount() {
      return this.recommendations.filter((r) => r.category === 'warning').length;
    },
    headline() {
      if (this.totalSavings >= 1) {
        return `${this.firstName}, save up to ${this.formatCurrency(Math.round(this.totalSavings))} this year`;
      }
      if (this.warningCount > 0) {
        return `${this.firstName}, there's something to watch out for this year`;
      }
      return `${this.firstName}, the allowances are well-utilised`;
    },
    subline() {
      const n = this.actionableCount;
      const w = this.warningCount;
      const wsuffix = w === 1 ? '1 thing to watch out for' : `${w} things to watch out for`;
      if (n === 0 && w > 0) {
        return `No immediate savings to lock in — but there's ${wsuffix} on a pension contribution.`;
      }
      if (n === 0) {
        return 'Allowances and pension contributions are tracking well — keep going.';
      }
      const base = n === 1
        ? "We've found 1 way to cut the tax bill"
        : `We've found ${n} ways to cut the tax bill`;
      return w === 0 ? `${base} this year.` : `${base} this year — plus ${wsuffix}.`;
    },
  },
};
</script>
