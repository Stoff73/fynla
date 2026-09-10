import { describe, it, expect } from 'vitest';
import { atTierCap, countCapFor } from '../tierLimitMixin';

/**
 * The one home for "is this user at the plan's cap" — the Net Worth pages
 * (via the mixin) and the onboarding assets step (directly) both decide from
 * here whether "+ Add" opens the form or the limit modal (W-0544).
 */
describe('tier caps', () => {
  const free = { tier: 'free', count_caps: { property: 1, savings_account: 2, pension_account: 2 } };

  it('reads a cap and reports at-cap once the count reaches it', () => {
    expect(countCapFor(free, 'property')).toBe(1);
    expect(atTierCap(free, 'property', 0)).toBe(false);
    expect(atTierCap(free, 'property', 1)).toBe(true);
    expect(atTierCap(free, 'savings_account', 3)).toBe(true);
  });

  it('treats a missing or null cap as unlimited, and no payload as unlimited', () => {
    expect(countCapFor(free, 'goal')).toBeNull();
    expect(atTierCap(free, 'goal', 99)).toBe(false);
    expect(atTierCap({ tier: 'premium', count_caps: { property: null } }, 'property', 40)).toBe(false);
    expect(atTierCap(null, 'property', 40)).toBe(false);
  });
});
