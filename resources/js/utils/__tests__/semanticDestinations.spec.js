import { describe, it, expect } from 'vitest';
import { resolveWebDestination } from '../semanticDestinations';

describe('resolveWebDestination', () => {
  it('opens an investment account inside the investments list (item 8)', () => {
    expect(resolveWebDestination({
      screen: 'investment_account_detail',
      params: { account_id: 131 },
      fallback: 'investment',
    })).toBe('/net-worth/investments?account=131');
  });

  it('falls back to the investment overview without an account id', () => {
    expect(resolveWebDestination({
      screen: 'investment_account_detail',
      params: {},
      fallback: 'investment',
    })).toBe('/investment');
  });
});
