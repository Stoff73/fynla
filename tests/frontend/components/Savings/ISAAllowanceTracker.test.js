import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import ISAAllowanceTracker from '@/components/Savings/ISAAllowanceTracker.vue';

// The tracker renders the one ISA tracker's status as sent (ISATracker via GET
// /api/savings, CSJ 2026-10-01): used, remaining, the bar's shares and the tax
// year. It works none of them out, and has no Lifetime ISA rule of its own.
const status = (overrides = {}) => ({
  tax_year: '2026/27',
  total_allowance: 20000,
  cash_isa_used: 8000,
  stocks_shares_isa_used: 4000,
  lisa_used: 0,
  remaining: 8000,
  cash_isa_percent: 40,
  stocks_shares_isa_percent: 20,
  lisa_percent: 0,
  projected_usage: { cash_isa_projected: 8000, projected_remaining: 8000 },
  ...overrides,
});

const mountTracker = (isaAllowance) => mount(ISAAllowanceTracker, {
  global: {
    plugins: [createStore({
      modules: { savings: { namespaced: true, state: () => ({ isaAllowance }) } },
    })],
  },
});

describe('ISAAllowanceTracker', () => {
  it('shows the server\'s allowance, use, remaining and tax year', () => {
    const text = mountTracker(status()).text();

    expect(text).toContain('£20,000 total');
    expect(text).toContain('£8,000');
    expect(text).toContain('£4,000');
    expect(text).toContain('2026/27');
  });

  it('draws the bar from the server\'s shares', () => {
    const segments = mountTracker(status()).findAll('.h-full.flex > div');

    expect(segments).toHaveLength(2);
    expect(segments[0].attributes('style')).toContain('width: 40%');
    expect(segments[1].attributes('style')).toContain('width: 20%');
  });

  it('shows a Lifetime ISA subscription as the server counted it', () => {
    const text = mountTracker(status({ lisa_used: 4000, lisa_percent: 20, remaining: 4000 })).text();

    expect(text).toContain('Lifetime ISA: £4,000');
    expect(text).toContain('£4,000');
  });

  it('shows the projected remaining only when it is lower', () => {
    const text = mountTracker(status({ projected_usage: { cash_isa_projected: 10000, projected_remaining: 6000 } })).text();

    expect(text).toContain('Projected: £10,000');
    expect(text).toContain('Projected: £6,000');
  });

  it('shows nothing invented before the server answers', () => {
    expect(mountTracker(null).text()).toContain('£0 total');
  });
});
