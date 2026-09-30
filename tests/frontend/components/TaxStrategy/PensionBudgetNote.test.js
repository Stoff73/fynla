import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AllowanceCard from '@/components/TaxStrategy/AllowanceCard.vue';
import MobileTaxStrategy from '../../../../resources/mobile/views/TaxStrategy.vue';

// The server caps pension headroom at a year of affordable surplus
// (TaxStrategyService::withAffordablePensionHeadroom). When it does, the tile
// must say why its headroom is below "limit minus used".
const capped = {
  key: 'pension_annual_allowance', label: 'Pension Annual Allowance', amount: 60000, used: 3000,
  remaining: 9357.36, utilisation_pct: 5, status: 'raspberry', available: true, known: true,
  affordable_this_year: 9357.36,
};
const uncapped = { ...capped, remaining: 57000, affordable_this_year: 80000 };

describe('pension headroom limited by budget', () => {
  it('web tile explains a budget-limited headroom', () => {
    expect(mount(AllowanceCard, { props: { allowance: capped } }).text()).toContain('Limited to what you can afford this year');
    expect(mount(AllowanceCard, { props: { allowance: uncapped } }).text()).not.toContain('Limited to what you can afford');
  });

  it('/m tile explains a budget-limited headroom', () => {
    const note = MobileTaxStrategy.methods.budgetNote;

    expect(note(capped)).toBe('Limited to what you can afford this year');
    expect(note(uncapped)).toBe(null);
  });
});

// fynla.org 2026-09-30, Carter household: £7,500 paid in of £60,000 and
// nothing left to afford read "Fully used". It is not used: the money is.
describe('pension headroom brought to £0 by the budget', () => {
  const none = { ...capped, used: 7500, remaining: 0, utilisation_pct: 12.5, affordable_this_year: 0 };

  it('web tile says £0 of headroom, not "Fully used"', () => {
    const text = mount(AllowanceCard, { props: { allowance: none } }).text();

    expect(text).toContain('£0 of headroom');
    expect(text).not.toContain('Fully used');
    expect(text).toContain('Limited to what you can afford this year');
  });

  it('/m tile says £0 available, not "Fully used"', () => {
    const vm = { ...MobileTaxStrategy.methods, fmt: (n) => `£${Number(n).toLocaleString('en-GB')}` };

    expect(MobileTaxStrategy.methods.remainingLabel.call(vm, none)).toBe('£0 available');
  });

  it('still says "Fully used" when the allowance really is used', () => {
    const used = { ...capped, used: 60000, remaining: 0, utilisation_pct: 100 };
    const vm = { ...MobileTaxStrategy.methods, fmt: (n) => `£${n}` };

    expect(mount(AllowanceCard, { props: { allowance: used } }).text()).toContain('Fully used');
    expect(MobileTaxStrategy.methods.remainingLabel.call(vm, used)).toBe('Fully used');
  });
});
