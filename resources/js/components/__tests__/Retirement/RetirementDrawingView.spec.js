import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RetirementDrawingView from '@/components/Retirement/RetirementDrawingView.vue';

// TODO item 6 (CSJ 2026-10-01): someone drawing their pension sees this year's
// income and how long the pot lasts, from the server's drawdown_position.
const position = (pot = {}) => ({
  retired_since: { date: '2020-01-01', age: 61 },
  income: {
    lines: [{ key: 'drawdown_278', label: 'Drawdown from Aviva personal pension', amount: 30000 }],
    state_pension_status: 'missing',
    total: 30000, income_tax: 3486, national_insurance: 0, take_home: 26514,
  },
  pot: {
    value: 200000, drawing_per_year: 30000, end_age: 100,
    lasts_to_age: { middle: 76, lower: 75 },
    life_expectancy: { age: 86, source: 'ons' },
    income_to_last_to_life_expectancy: 13400,
    ...pot,
  },
});

describe('RetirementDrawingView', () => {
  it('shows the income, the tax and how long the pot lasts', () => {
    const text = mount(RetirementDrawingView, { props: { position: position() } }).text();

    expect(text).toContain('Retired since January 2020, at 61');
    expect(text).toContain('Drawdown from Aviva personal pension£30,000');
    expect(text).toContain('Income Tax£3,486');
    expect(text).not.toContain('National Insurance');
    expect(text).toContain('Take-home£26,514');
    expect(text).toContain('Middle outcomelasts to age 76');
    expect(text).toContain('86 (Office for National Statistics)');
    expect(text).toContain('about £13,400 a year');
  });

  it('says a pot outlasts the horizon, and the user\'s own life expectancy figure', () => {
    const text = mount(RetirementDrawingView, {
      props: { position: position({ lasts_to_age: { middle: null, lower: null }, life_expectancy: { age: 90, source: 'user_override' } }) },
    }).text();

    expect(text).toContain('lasts beyond 100');
    expect(text).toContain('90 (your figure)');
  });

  it('asks for the State Pension when it is missing', async () => {
    const wrapper = mount(RetirementDrawingView, { props: { position: position() } });
    await wrapper.find('button').trigger('click');

    expect(wrapper.emitted('add-state-pension')).toHaveLength(1);
  });

  it('offers to update a State Pension not recorded as being paid', () => {
    const p = position();
    p.income.state_pension_status = 'not_paid';
    const text = mount(RetirementDrawingView, { props: { position: p } }).text();

    expect(text).toContain('State Pension: not recorded as being paid');
    expect(text).toContain('Update');
    expect(text).not.toContain('Add it');
  });
});
