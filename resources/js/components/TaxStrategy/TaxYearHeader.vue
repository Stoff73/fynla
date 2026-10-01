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
    ...mapGetters('taxStrategy', ['taxYear', 'summary']),
    // "well-utilised" is only true when no allowance has known headroom (the
    // /m rule, resources/mobile/views/TaxStrategy.vue).
    // Every figure is the server's (TaxStrategyService::withDisplayState, CSJ
    // 2026-10-01), the same /m and iOS read: the composed plan's total, the
    // counts of actions and warnings and the allowances with headroom.
    knownHeadroomCount() {
      return Number(this.summary.headroom_count) || 0;
    },
    ...mapGetters('auth', ['currentUser']),
    firstName() {
      return this.currentUser?.first_name || 'there';
    },
    totalSavings() {
      return Number(this.summary.total_saving) || 0;
    },
    actionableCount() {
      return Number(this.summary.actionable_count) || 0;
    },
    warningCount() {
      return Number(this.summary.warning_count) || 0;
    },
    headline() {
      if (this.totalSavings >= 1) {
        return `${this.firstName}, save up to ${this.formatCurrency(Math.round(this.totalSavings))} this year`;
      }
      if (this.warningCount > 0) {
        return `${this.firstName}, there's something to watch out for this year`;
      }
      return this.knownHeadroomCount > 0
        ? `${this.firstName}, you have unused allowances this year`
        : `${this.firstName}, the allowances are well-utilised`;
    },
    subline() {
      const n = this.actionableCount;
      const w = this.warningCount;
      const wsuffix = w === 1 ? '1 thing to watch out for' : `${w} things to watch out for`;
      if (n === 0 && w > 0) {
        return `No immediate savings to lock in — but there's ${wsuffix} on a pension contribution.`;
      }
      if (n === 0) {
        return this.knownHeadroomCount > 0
          ? 'No additional recommended actions are available from the information on file right now. Your unused allowances are shown below.'
          : 'Your allowances are well-utilised — nothing to act on right now.';
      }
      const base = n === 1
        ? "We've found 1 way to cut the tax bill"
        : `We've found ${n} ways to cut the tax bill`;
      return w === 0 ? `${base} this year.` : `${base} this year — plus ${wsuffix}.`;
    },
  },
};
</script>
