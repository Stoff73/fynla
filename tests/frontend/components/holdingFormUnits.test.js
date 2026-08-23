import { describe, it, expect } from 'vitest';

const load = () => import('@/components/Investment/HoldingForm.vue');

describe('HoldingForm — units input (W-0039)', () => {
  it('compiles and carries quantity and dividend_yield in its form state', async () => {
    const mod = await load();
    const data = mod.default.data();

    expect(data.formData).toHaveProperty('quantity');
    expect(data.formData).toHaveProperty('dividend_yield');
  });

  it('previews the value as units x price, overriding the allocation fallback', async () => {
    const mod = await load();
    const ctx = {
      formData: { quantity: 351, current_price: 7.42, allocation_percent: 100 },
      selectedAccount: { current_value: 999999 },
    };

    // Mirrors App\Support\HoldingValuation, which is the authority: units win,
    // so the preview cannot contradict what the server will store.
    expect(mod.default.computed.calculatedHoldingValue.call(ctx)).toBeCloseTo(2604.42, 2);
  });

  it('falls back to allocation x account value when there are no units', async () => {
    const mod = await load();
    const ctx = {
      owner: null,
      formData: { quantity: null, current_price: null, allocation_percent: 25 },
      selectedAccount: { current_value: 100000 },
    };
    // Driven through the real `ownerValue` rather than injected, so the case
    // still proves the value comes from the SELECTED ACCOUNT. Supplying
    // `ownerValue` directly would hand the computed the answer and prove only
    // the multiplication (tests/CLAUDE.md §4, Fixture).
    ctx.ownerValue = mod.default.computed.ownerValue.call(ctx);

    expect(mod.default.computed.calculatedHoldingValue.call(ctx)).toBe(25000);
  });

  it('takes the pot from the owner, not an account, when a pension owns the holding', async () => {
    const mod = await load();
    // 320,000 pot at 50% is 160,000; the account beside it is a decoy at a
    // mutually distinct value, so reading the wrong one cannot pass.
    const ctx = {
      owner: { label: 'Pension', name: "David's SIPP", valueLabel: 'Fund Value:', value: 320000 },
      formData: { quantity: null, current_price: null, allocation_percent: 50 },
      selectedAccount: { current_value: 85000 },
    };
    ctx.ownerValue = mod.default.computed.ownerValue.call(ctx);

    expect(ctx.ownerValue).toBe(320000);
    expect(mod.default.computed.calculatedHoldingValue.call(ctx)).toBe(160000);
  });
});
