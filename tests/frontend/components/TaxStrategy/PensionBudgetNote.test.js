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
