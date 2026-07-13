// @vitest-environment node

import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('public pricing yearly saving', () => {
  beforeEach(async () => {
    vi.resetModules();
    globalThis.window = globalThis;
    globalThis.document = { getElementById: vi.fn(() => null) };
    globalThis.location = { pathname: '/pricing' };
    await import('../../../public/pages/js/pricing.js');
  });

  it('rounds the configured yearly discount to 17 percent', () => {
    expect(window.FynlaPricing.annualSavingPercent(499, 4990)).toBe(17);
  });

  it('returns another live percentage without a fixed claim', () => {
    expect(window.FynlaPricing.annualSavingPercent(1000, 9000)).toBe(25);
  });

  it.each([
    [null, 4990],
    [0, 4990],
    [499, null],
    [499, 0],
    [499, 5988],
    [499, 7000],
  ])('returns null when prices cannot support a saving (%s, %s)', (monthly, yearly) => {
    expect(window.FynlaPricing.annualSavingPercent(monthly, yearly)).toBeNull();
  });
});
