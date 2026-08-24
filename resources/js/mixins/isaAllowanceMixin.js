/**
 * The one home for reading a user's ISA allowance position in a form.
 *
 * Both the savings account modal and the investment account modal render the
 * same "ISA Allowance Usage" panel and both guard the same £20,000 statutory
 * limit, but they reached the numbers three different ways: the savings modal
 * read the store getters directly, the investment modal read them and then
 * hand-threaded copies down to `StandardInvestmentFields` as props, and the
 * allowance itself only ever loaded as a side effect of the savings screen's
 * bulk fetch. On a cold load of `/net-worth/investments` the panel therefore
 * read £0 and the over-subscription guard never fired (W-0007).
 *
 * Any component that renders or guards on ISA usage uses this mixin. It loads
 * the allowance if nothing else has, and exposes the figures from the single
 * store getters — no prop copies.
 */
export const isaAllowanceMixin = {
  computed: {
    /** Cash ISA subscribed this tax year, across the household's own accounts. */
    cashISAUsed() {
      return this.$store.getters['savings/currentYearISASubscription'] || 0;
    },

    /** Stocks & Shares ISA subscribed this tax year. */
    totalStocksISAUsed() {
      return this.$store.getters['investment/investmentISASubscription'] || 0;
    },
  },

  created() {
    // Idempotent: a no-op when the allowance is already in the store, so the
    // savings screens that already load it in bulk pay nothing for this.
    this.$store.dispatch('savings/ensureISAAllowance');
  },
};

export default isaAllowanceMixin;
