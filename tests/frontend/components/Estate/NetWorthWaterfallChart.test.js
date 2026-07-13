import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import NetWorthWaterfallChart from '@/components/Estate/NetWorthWaterfallChart.vue';

const assets = [
  { id: 1, asset_type: 'property', current_value: 500000 },
  { id: 2, asset_type: 'pension', current_value: 300000 },
  { id: 3, asset_type: 'investment', current_value: 150000 },
];
const liabilities = [
  { id: 1, liability_type: 'mortgage', current_balance: 200000 },
  { id: 2, liability_type: 'personal_loan', current_balance: 30000 },
  { id: 3, liability_type: 'credit_card', current_balance: 5000 },
];

const mountChart = (props = {}) => mount(NetWorthWaterfallChart, {
  props: { assets, liabilities, ...props },
});

describe('NetWorthWaterfallChart', () => {
  it('renders with asset and liability records', () => {
    expect(mountChart().exists()).toBe(true);
  });

  it('totals assets from current values', () => {
    expect(mountChart().vm.totalAssets).toBe(950000);
  });

  it('totals liabilities from current balances', () => {
    expect(mountChart().vm.totalLiabilities).toBe(235000);
  });

  it('calculates net worth', () => {
    expect(mountChart().vm.netWorth).toBe(715000);
  });

  it('builds one aggregate chart series', () => {
    const series = mountChart().vm.chartSeries;
    expect(series).toHaveLength(1);
    expect(series[0].name).toBe('Amount');
  });

  it('uses the current three-point waterfall summary', () => {
    expect(mountChart().vm.chartSeries[0].data.map(point => point.x)).toEqual([
      'Total Assets',
      'Total Liabilities',
      'Net Worth',
    ]);
  });

  it('starts with total assets', () => {
    expect(mountChart().vm.chartSeries[0].data[0].y).toBe(950000);
  });

  it('represents liabilities as a negative value', () => {
    expect(mountChart().vm.chartSeries[0].data[1].y).toBe(-235000);
  });

  it('ends with calculated net worth', () => {
    expect(mountChart().vm.chartSeries[0].data[2].y).toBe(715000);
  });

  it('assigns semantic colours to each summary point', () => {
    const colours = mountChart().vm.chartSeries[0].data.map(point => point.fillColor);
    expect(colours.every(colour => /^#[0-9a-f]{6}$/i.test(colour))).toBe(true);
    expect(colours[0]).not.toBe(colours[1]);
    expect(colours[2]).toBeDefined();
  });

  it('formats currency values', () => {
    expect(mountChart().vm.formatCurrency(950000)).toBe('£950,000');
  });

  it('returns no series when both inputs are empty', () => {
    expect(mountChart({ assets: [], liabilities: [] }).vm.chartSeries).toEqual([]);
  });

  it('returns no chart options when both inputs are empty', () => {
    expect(mountChart({ assets: [], liabilities: [] }).vm.chartOptions).toBeNull();
  });

  it('handles an asset-only estate', () => {
    const wrapper = mountChart({ liabilities: [] });
    expect(wrapper.vm.netWorth).toBe(950000);
    expect(wrapper.vm.chartSeries[0].data[1].y).toBe(-0);
  });

  it('handles a liability-only estate', () => {
    const wrapper = mountChart({ assets: [] });
    expect(wrapper.vm.netWorth).toBe(-235000);
  });

  it('handles negative net worth', () => {
    const wrapper = mountChart({ assets: [{ current_value: 100000 }], liabilities: [{ current_balance: 150000 }] });
    expect(wrapper.vm.netWorth).toBe(-50000);
  });

  it('configures a bar chart', () => {
    expect(mountChart().vm.chartOptions.chart.type).toBe('bar');
  });

  it('uses category labels supplied by series points', () => {
    expect(mountChart().vm.chartOptions.xaxis.type).toBe('category');
  });

  it('formats tooltip liabilities as positive currency amounts', () => {
    expect(mountChart().vm.chartOptions.tooltip.y.formatter(-235000)).toBe('£235,000');
  });

  it('changes its chart key when totals change', async () => {
    const wrapper = mountChart();
    const originalKey = wrapper.vm.chartKey;
    await wrapper.setProps({ assets: [{ current_value: 100 }] });
    expect(wrapper.vm.chartKey).not.toBe(originalKey);
  });
});
