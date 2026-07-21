import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import GiftingTimelineChart from '@/components/Estate/GiftingTimelineChart.vue';

const yearsAgo = (years) => {
  const date = new Date();
  date.setFullYear(date.getFullYear() - years);
  return date.toISOString().split('T')[0];
};

const gifts = [
  { id: 1, gift_date: yearsAgo(8), recipient: 'Alice', gift_value: 50000, gift_type: 'pet' },
  { id: 2, gift_date: yearsAgo(5), recipient: 'Jane', gift_value: 30000, gift_type: 'pet' },
  { id: 3, gift_date: yearsAgo(2), recipient: 'Bob', gift_value: 20000, gift_type: 'pet' },
];

const mountChart = (giftRecords = gifts) => mount(GiftingTimelineChart, {
  props: { gifts: giftRecords },
});

describe('GiftingTimelineChart', () => {
  it('renders with gift records', () => {
    expect(mountChart().exists()).toBe(true);
  });

  it('displays the empty state when no gifts are provided', () => {
    const wrapper = mountChart([]);
    expect(wrapper.vm.series).toEqual([]);
    expect(wrapper.text()).toContain('No gifts recorded yet');
  });

  it('creates one range per gift', () => {
    expect(mountChart().vm.series[0].data).toHaveLength(3);
  });

  it('calculates elapsed years for chart metadata', () => {
    const meta = mountChart().vm.series[0].data[1].meta;
    expect(Number(meta.years_elapsed)).toBeGreaterThan(4.9);
  });

  it('calculates remaining years for chart metadata', () => {
    const meta = mountChart().vm.series[0].data[1].meta;
    expect(Number(meta.years_remaining)).toBeGreaterThan(1.9);
    expect(Number(meta.years_remaining)).toBeLessThan(2.1);
  });

  it('identifies gifts that have survived seven years', () => {
    expect(mountChart().vm.series[0].data[0].meta.status).toContain('7 years survived');
  });

  it('calculates every taper-relief bracket', () => {
    const wrapper = mountChart();
    expect([2.5, 3.5, 4.5, 5.5, 6.5, 7.5].map(wrapper.vm.calculateTaperRelief)).toEqual([0, 20, 40, 60, 80, 100]);
  });

  it('uses the danger colour for a recent potentially exempt transfer', () => {
    const wrapper = mountChart();
    expect(wrapper.vm.getGiftColour(2, 'pet')).toBe(wrapper.vm.series[0].data[2].fillColour);
  });

  it('uses the warning colour while taper relief applies', () => {
    const wrapper = mountChart();
    expect(wrapper.vm.getGiftColour(5, 'pet')).toBe(wrapper.vm.series[0].data[1].fillColour);
  });

  it('uses the success colour after seven years', () => {
    const wrapper = mountChart();
    expect(wrapper.vm.getGiftColour(8, 'pet')).toBe(wrapper.vm.series[0].data[0].fillColour);
  });

  it('treats spouse and charity gifts as immediately exempt', () => {
    const wrapper = mountChart([{ id: 4, gift_date: yearsAgo(1), recipient: 'Spouse', gift_value: 1000, gift_type: 'exempt' }]);
    expect(wrapper.vm.series[0].data[0].meta.status).toContain('Exempt Gift');
  });

  it('preserves the supplied recipient order in chart data', () => {
    expect(mountChart().vm.series[0].data.map(item => item.x)).toEqual(['Alice', 'Jane', 'Bob']);
  });

  it('displays the taper-relief reference table', () => {
    const text = mountChart().text();
    expect(text).toContain('Taper Relief Rates');
    expect(text).toContain('80% relief');
  });

  it('formats gift values for chart labels', () => {
    expect(mountChart().vm.formatCurrency(50000)).toBe('£50,000');
  });

  it('handles exact taper boundaries', () => {
    const wrapper = mountChart();
    expect(wrapper.vm.calculateTaperRelief(3)).toBe(20);
    expect(wrapper.vm.calculateTaperRelief(7)).toBe(100);
  });

  it('configures the current range-bar chart', () => {
    const wrapper = mountChart();
    expect(wrapper.text()).toContain('7-Year Gifting Timeline');
    expect(wrapper.vm.chartOptions.chart.type).toBe('rangeBar');
  });
});
