import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import TaxBreakdownCard from '@/components/Retirement/TaxBreakdownCard.vue';

describe('TaxBreakdownCard', () => {
  const breakdown = {
    effective_rate: 0.175,
    gross_income: 50000,
    total_tax: 8750,
    net_income: 41250,
    sources: [
      {
        name: 'Workplace pension drawdown',
        source_type: 'dc_pension_drawdown',
        amount: 30000,
        tax: 5000,
        tax_treatment: 'taxable',
        band_breakdown: {
          personal_allowance: 12570,
          basic_rate: 17430,
          higher_rate: 0,
          additional_rate: 0,
        },
      },
      {
        name: 'ISA withdrawals',
        source_type: 'isa',
        amount: 20000,
        tax: 0,
        tax_treatment: 'tax_free',
      },
    ],
    band_usage: {
      personal_allowance: { used: 12570, remaining: 0 },
      basic_rate: { used: 17430, remaining: 20270 },
      higher_rate: { used: 0, remaining: 74870 },
      additional_rate: { used: 0, remaining: 0 },
    },
  };

  const mountCard = (value = breakdown) => mount(TaxBreakdownCard, {
    props: { breakdown: value },
  });

  it('renders the current retirement tax summary', () => {
    const wrapper = mountCard();

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Tax Breakdown');
  });

  it('formats the effective tax rate', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('17.5% effective rate');
  });

  it('renders income sources with their full user-facing names', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Pension');
    expect(wrapper.text()).toContain('Workplace pension drawdown');
    expect(wrapper.text()).toContain('ISA');
    expect(wrapper.text()).toContain('ISA withdrawals');
  });

  it('distinguishes taxable and tax-free income using specific amounts', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('-£5,000 tax');
    expect(wrapper.text()).toContain('Tax-free');
  });

  it('renders gross income, tax, and net income totals', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Gross Income');
    expect(wrapper.text()).toContain('£50,000');
    expect(wrapper.text()).toContain('Total Tax');
    expect(wrapper.text()).toContain('-£8,750');
    expect(wrapper.text()).toContain('Net Income');
    expect(wrapper.text()).toContain('£41,250');
  });

  it('shows the source-level tax band breakdown', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Personal Allowance (0%)');
    expect(wrapper.text()).toContain('Basic Rate (20%)');
    expect(wrapper.text()).toContain('£17,430');
    expect(wrapper.text()).toContain('-£3,486');
  });

  it('calculates tax-band usage as a percentage of the band', () => {
    const wrapper = mountCard();

    expect(wrapper.vm.getUsagePercent({ used: 20, remaining: 80 })).toBe('20%');
    expect(wrapper.vm.getUsagePercent({ used: 0, remaining: 0 })).toBe('0%');
    expect(wrapper.vm.getUsagePercent(null)).toBe('0%');
  });

  it('renders the tax band usage amounts', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Tax Band Usage');
    expect(wrapper.text()).toContain('£12,570 used');
    expect(wrapper.text()).toContain('£20,270 remaining');
  });

  it('only shows higher and additional rate bands when they are used', () => {
    const wrapper = mountCard();

    expect(wrapper.findAll('.band-name').map(item => item.text())).toEqual([
      'Personal Allowance',
      'Basic Rate',
    ]);
  });

  it('shows higher and additional rate details when income reaches those bands', () => {
    const wrapper = mountCard({
      ...breakdown,
      band_usage: {
        ...breakdown.band_usage,
        higher_rate: { used: 10000, remaining: 64870 },
        additional_rate: { used: 5000, remaining: 0 },
      },
    });

    expect(wrapper.text()).toContain('Higher Rate');
    expect(wrapper.text()).toContain('Additional Rate');
    expect(wrapper.text()).toContain('No upper limit');
  });

  it('suggests using remaining Personal Allowance', () => {
    const wrapper = mountCard({
      ...breakdown,
      band_usage: {
        ...breakdown.band_usage,
        personal_allowance: { used: 10000, remaining: 2570 },
      },
    });

    expect(wrapper.vm.tips).toContain(
      'You have £2,570 of unused Personal Allowance. Consider drawing more pension income tax-free.',
    );
  });

  it('suggests spreading income when the basic rate band still has room', () => {
    const wrapper = mountCard({
      ...breakdown,
      band_usage: {
        ...breakdown.band_usage,
        higher_rate: { used: 5000, remaining: 69870 },
      },
    });

    expect(wrapper.vm.tips).toContain(
      'Consider spreading income across tax years to stay within the basic rate band.',
    );
  });

  it('handles an empty tax breakdown without fabricating income', () => {
    const wrapper = mountCard({});

    expect(wrapper.text()).toContain('0.0% effective rate');
    expect(wrapper.text()).toContain('Gross Income');
    expect(wrapper.text()).toContain('£0');
    expect(wrapper.vm.tips).toEqual([]);
  });

  it('maps retirement source types to full labels', () => {
    const wrapper = mountCard();

    expect(wrapper.vm.getSourceTypeLabel('db_pension')).toBe('Defined Benefit Pension');
    expect(wrapper.vm.getSourceTypeLabel('state_pension')).toBe('State Pension');
    expect(wrapper.vm.getSourceTypeLabel('gia')).toBe('General Investment Account');
    expect(wrapper.vm.getSourceTypeLabel('unknown')).toBe('Income');
  });

  it('uses canonical semantic badge classes for each source family', () => {
    const wrapper = mountCard();

    expect(wrapper.vm.getBadgeClass({ source_type: 'dc_pension_pcls' })).toBe('badge-pcls');
    expect(wrapper.vm.getBadgeClass({ source_type: 'db_pension' })).toBe('badge-pension');
    expect(wrapper.vm.getBadgeClass({ source_type: 'isa' })).toBe('badge-isa');
    expect(wrapper.vm.getBadgeClass({ source_type: 'unknown' })).toBe('badge-default');
  });
});
