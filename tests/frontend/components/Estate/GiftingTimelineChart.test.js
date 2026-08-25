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

  it('reports the relief the SERVER computed, not one derived from the gift age', () => {
    // C4 — `calculateTaperRelief()` is gone. It was a fourth hardcoded copy of the
    // schedule and, like the others, answered from age alone. Relief exists only
    // where the gift bears tax (IHTM14611), which depends on the whole estate's
    // cumulation and cannot be known in a chart component.
    const wrapper = mountChart();

    expect(wrapper.vm.taperReliefFor({ taper: { taper_relief_percent: 60, taper_saving: 4000 } })).toBe('60%');
  });

  it('says a gift within the allowance has no relief, however old', () => {
    const wrapper = mountChart();

    expect(wrapper.vm.taperReliefFor({ taper: null })).toBe('None — within your allowance');
    expect(wrapper.vm.taperReliefFor({ taper: { taper_relief_percent: 80, taper_saving: 0 } }))
      .toBe('None — within your allowance');
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

  it('labels a gift by whether it is taxable, not by how old it is', () => {
    const wrapper = mountChart();

    expect(wrapper.vm.getGiftStatus(6, 'pet', { taper: null })).toBe('Within your allowance');
    expect(wrapper.vm.getGiftStatus(6, 'pet', { taper: { taper_relief_percent: 80, taper_saving: 3000 } }))
      .toBe('Taper Relief (80%)');
    expect(wrapper.vm.getGiftStatus(8, 'pet', { taper: null }))
      .toBe('Inheritance Tax-Exempt (7 years survived)');
  });

  it('configures the current range-bar chart', () => {
    const wrapper = mountChart();
    expect(wrapper.text()).toContain('7-Year Gifting Timeline');
    expect(wrapper.vm.chartOptions.chart.type).toBe('rangeBar');
  });
});
