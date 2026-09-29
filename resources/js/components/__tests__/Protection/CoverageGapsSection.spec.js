import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CoverageGapsSection from '../../Protection/CoverageGapsSection.vue';

// The web page shows the server's breakdown (GET /api/protection
// `coverage_gaps`), the payload /m renders. It used to work the figures out in
// the browser from its own literals and disagree with "Your cover".
const breakdown = {
  calculated_at: '2026-09-29T14:00:00+01:00',
  categories: [
    { key: 'human_capital', label: 'Income replacement capital', need: 965051, cover: 100000, shortfall: 865051, status: 'gap', severity: 'high', explanation: 'What your family would need to replace your income.', inputs: { net_income_difference: 45356 }, assumptions: [{ key: 'sustainable_withdrawal_rate', value: 4.7, unit: 'percent' }], relevant_policies: [{ type: 'life', id: 1, provider: 'Aviva', cover: 100000 }] },
    { key: 'income_protection', label: 'Income protection', need: 36000, cover: 0, shortfall: 36000, status: 'gap', severity: 'high', explanation: '60% of gross earned income.', inputs: {}, assumptions: [], relevant_policies: [] },
    { key: 'debt_protection', label: 'Debt protection', need: 0, cover: 0, shortfall: 0, status: 'covered', severity: 'none', explanation: '', inputs: {}, assumptions: [], relevant_policies: [] },
  ],
};

describe('CoverageGapsSection', () => {
  it('shows each open gap with the server figures', () => {
    const text = mount(CoverageGapsSection, { props: { breakdown } }).text();

    expect(text).toContain('Income replacement capital');
    expect(text).toContain('£865,051 short');
    expect(text).toContain('£100,000 of £965,051');
    expect(text).toContain('£36,000 a year short');
    expect(text).not.toContain('Debt protection');
  });

  it('never shows a severity rating (Rule 12)', () => {
    const text = mount(CoverageGapsSection, { props: { breakdown } }).text();

    expect(text).not.toMatch(/\b(High|Medium|Low)\b/);
  });

  it('opens the working behind a gap', async () => {
    const wrapper = mount(CoverageGapsSection, { props: { breakdown } });
    await wrapper.get('[data-test="protection-gap-human_capital"] button').trigger('click');

    expect(wrapper.text()).toContain('What your family would need to replace your income.');
    expect(wrapper.text()).toContain('Sustainable Withdrawal Rate: 4.7%');
    expect(wrapper.text()).toContain('Aviva: £100,000');
  });

  it('says so when nothing is short', () => {
    const text = mount(CoverageGapsSection, { props: { breakdown: { categories: [breakdown.categories[2]] } } }).text();

    expect(text).toContain('No shortfalls identified');
  });
});
