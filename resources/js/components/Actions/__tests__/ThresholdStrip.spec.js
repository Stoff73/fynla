import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ThresholdStrip from '../ThresholdStrip.vue';

const line = {
  key: 'pa_taper',
  title: 'Personal Allowance taper',
  range: { from: 100000, to: 125140 },
  position: { value: 112400, distance: 12400, unit: 'gbp', over: true },
  headline: 'You are £12,400 into the 60% band',
  body: 'The next £12,400 you earn costs 60p in the pound.',
  explanation: 'For every £2 you earn above £100,000 you lose £1 of your Personal Allowance.',
  income_mix: { employment: 112400 },
  cost: { income_tax: 2480, ni_class_1: 0, ni_class_4: 0, dividend_tax: 0, interest_tax: 0, benefits: [{ label: 'Tax-Free Childcare', detail: 'One child under 12', amount: 2000 }], total: 4480 },
  cost_total: 4480,
  lever: { title: 'Pay £12,400 into your pension', amount: 12400, recovers: 4480, downside: 'The money is locked until you are 55.', action: { route: '/tax-strategy' } },
};
const dateLine = { key: 'ni_cap', title: 'Salary sacrifice National Insurance cap', range: null, position: { value: 197, distance: 197, unit: 'days', over: false }, headline: '6 April 2027 · 197 days', body: '', explanation: '', income_mix: {}, cost: null, cost_total: 160, lever: null };
const openEndedLine = {
  key: 'higher_rate',
  title: 'Higher rate band',
  range: { from: 50270, to: null },
  position: { value: 60000, distance: 9730, unit: 'gbp', over: true },
  headline: 'You are in the higher rate band',
  body: '',
  explanation: '',
  income_mix: {},
  cost: null,
  cost_total: 0,
  lever: null,
};

const mountWith = (data) => mount(ThresholdStrip, {
  props: { data },
  global: { stubs: { 'router-link': { template: '<a><slot /></a>' } } },
});

describe('ThresholdStrip', () => {
  it('renders nothing when no line applies', () => {
    expect(mountWith({ strip: null, lines: [] }).html()).toBe('<!--v-if-->');
  });

  it('shows the strip line collapsed, then the cost and lever on expand', async () => {
    const w = mountWith({ strip: 'pa_taper', lines: [line, dateLine], suppressed: 2 });
    expect(w.text()).toContain('You are £12,400 into the 60% band');
    expect(w.text()).not.toContain('Tax-Free Childcare');
    await w.get('button').trigger('click');
    expect(w.text()).toContain('Tax-Free Childcare');
    expect(w.text()).toContain('£4,480');
    expect(w.text()).toContain('Pay £12,400 into your pension');
    expect(w.text()).toContain('Salary sacrifice National Insurance cap');
    expect(w.text()).toContain('2 more lines exist in the tax system that you are nowhere near. They are not listed.');
  });

  it('does not render a ribbon for an open-ended range', () => {
    const w = mountWith({ strip: 'higher_rate', lines: [openEndedLine] });
    expect(w.find('.ribbon').exists()).toBe(false);
  });
});
