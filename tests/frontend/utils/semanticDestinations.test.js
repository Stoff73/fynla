import { describe, it, expect } from 'vitest';
import { resolveWebDestination } from '@/utils/semanticDestinations';

describe('resolveWebDestination', () => {
  it('maps overview screens to the GateRoutes web paths', () => {
    expect(resolveWebDestination({ screen: 'investment', params: {}, fallback: 'dashboard' })).toBe('/investment');
    expect(resolveWebDestination({ screen: 'tax_strategy', params: {}, fallback: 'dashboard' })).toBe('/tax-strategy');
  });

  it('maps detail screens to the web detail routes with their identifiers', () => {
    expect(resolveWebDestination({ screen: 'savings_account_detail', params: { account_id: 42 }, fallback: 'savings' }))
      .toBe('/savings/account/42');
    expect(resolveWebDestination({ screen: 'pension_detail', params: { pension_id: 7, pension_type: 'dc' }, fallback: 'retirement' }))
      .toBe('/pension/dc/7');
    expect(resolveWebDestination({ screen: 'protection_policy_detail', params: { policy_id: 3, policy_type: 'life' }, fallback: 'protection' }))
      .toBe('/protection/policy/life/3');
  });

  it('falls back to the overview when the web SPA has no detail page or the id is unusable', () => {
    expect(resolveWebDestination({ screen: 'goal_detail', params: { goal_id: 9 }, fallback: 'goals' })).toBe('/goals');
    expect(resolveWebDestination({ screen: 'savings_account_detail', params: { account_id: '../x' }, fallback: 'savings' })).toBe('/savings');
  });

  it('returns null for nothing resolvable', () => {
    expect(resolveWebDestination(null)).toBeNull();
    expect(resolveWebDestination({ screen: 'unknown', params: {}, fallback: 'unknown' })).toBeNull();
  });
});
