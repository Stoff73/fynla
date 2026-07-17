/**
 * tierLimitMixin
 *
 * Surfaces the user's freemium count caps to views so a capped "Add" action can
 * show an upgrade modal up front instead of letting the user fill a form that
 * fails server-side (SP2 spec §8.3). Caps come from `auth.subscriptionData`
 * (the `/payment/subscription-status` payload, single source of truth —
 * `tier_configurations.count_caps`). A null/absent cap means unlimited.
 */
const TIER_LABELS = {
  free: 'Free',
  premium: 'Premium',
};

export const tierLimitMixin = {
  computed: {
    tierData() {
      return this.$store.state.auth?.subscriptionData || null;
    },
    tierLabel() {
      return TIER_LABELS[this.tierData?.tier] || 'your current';
    },
  },
  methods: {
    /** Count cap for an entity at the user's tier. null = unlimited / not gated. */
    tierCountCap(entityKey) {
      const caps = this.tierData?.count_caps;
      if (!caps || caps[entityKey] === undefined || caps[entityKey] === null) {
        return null;
      }
      return caps[entityKey];
    },
    /** True when the user is at or over the cap for this entity. */
    isAtTierCap(entityKey, currentCount) {
      const cap = this.tierCountCap(entityKey);
      return cap !== null && currentCount >= cap;
    },
  },
};
