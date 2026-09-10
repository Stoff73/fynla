/**
 * tierLimitMixin
 *
 * Surfaces the user's freemium count caps to views so a capped "Add" action can
 * show an upgrade modal up front instead of letting the user fill a form that
 * fails server-side (SP2 spec §8.3). Caps come from `auth.subscriptionData`
 * (the `/payment/subscription-status` payload, single source of truth —
 * `tier_configurations.count_caps`). A null/absent cap means unlimited.
 *
 * The pure helpers below are the one home for the arithmetic; the mixin binds
 * them to `this.$store` for Options API views, and composition API screens
 * (the onboarding assets step) import them directly.
 */
export const TIER_LABELS = {
  free: 'Free',
  premium: 'Premium',
};

/** Count cap for an entity at the user's tier. null = unlimited / not gated. */
export function countCapFor(tierData, entityKey) {
  const caps = tierData?.count_caps;
  if (!caps || caps[entityKey] === undefined || caps[entityKey] === null) {
    return null;
  }
  return caps[entityKey];
}

/** True when the user is at or over the cap for this entity. */
export function atTierCap(tierData, entityKey, currentCount) {
  const cap = countCapFor(tierData, entityKey);
  return cap !== null && currentCount >= cap;
}

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
    tierCountCap(entityKey) {
      return countCapFor(this.tierData, entityKey);
    },
    isAtTierCap(entityKey, currentCount) {
      return atTierCap(this.tierData, entityKey, currentCount);
    },
  },
};
