import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CoverageGapChart from '@/components/Protection/CoverageGapChart.vue';
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS } from '@/constants/designSystem';

describe('CoverageGapChart', () => {
  const gaps = [
    {
      name: 'Required cover',
      data: [
        { x: 'Death', y: 300000 },
        { x: 'Critical Illness', y: 150000 },
      ],
    },
    {
      name: 'Existing cover',
      data: [
        { x: 'Death', y: 100000 },
        { x: 'Critical Illness', y: 50000 },
      ],
    },
    {
      name: 'Shortfall',
      data: [
        { x: 'Death', y: 200000 },
        { x: 'Critical Illness', y: 100000 },
      ],
    },
  ];

  const mountChart = (props = {}) => mount(CoverageGapChart, {
    props,
    global: {
      stubs: {
        apexchart: {
          name: 'ApexChart',
          props: ['type', 'options', 'series', 'height'],
          template: '<div class="apexchart-stub" />',
        },
      },
    },
  });

  it('renders the current gap-based protection visualisation', async () => {
    const wrapper = mountChart({ gaps });
    await wrapper.vm.$nextTick();

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.coverage-gap-chart').exists()).toBe(true);
  });

  it('passes required cover, existing cover, and shortfall data through unchanged', () => {
    const wrapper = mountChart({ gaps });

    expect(wrapper.vm.series).toEqual(gaps);
    expect(wrapper.vm.series.map(series => series.name)).toEqual([
      'Required cover',
      'Existing cover',
      'Shortfall',
    ]);
  });

  it('represents shortfalls as currency amounts instead of adequacy scores', () => {
    const wrapper = mountChart({ gaps });

    expect(wrapper.vm.series[2].data[0]).toEqual({ x: 'Death', y: 200000 });
    expect(wrapper.vm.chartOptions.tooltip.y.formatter(200000)).toMatch(/£200,000/);
  });

  it('uses default lifecycle gap data when no analysis has been supplied', () => {
    const wrapper = mountChart();

    expect(wrapper.vm.series).toHaveLength(5);
    expect(wrapper.vm.series[0].name).toBe('Now');
    expect(wrapper.vm.series.at(-1).name).toBe('Retirement');
  });

  it('configures a heatmap for the four protection needs', () => {
    const wrapper = mountChart({ gaps });

    expect(wrapper.vm.chartOptions.chart.type).toBe('heatmap');
    expect(wrapper.vm.chartOptions.xaxis.categories).toEqual([
      'Death',
      'Critical Illness',
      'Disability',
      'Unemployment',
    ]);
  });

  it('uses canonical semantic colours for low, medium, high, and critical gaps', () => {
    const wrapper = mountChart({ gaps });
    const ranges = wrapper.vm.chartOptions.plotOptions.heatmap.colourScale.ranges;

    expect(ranges.map(range => range.color)).toEqual([
      SUCCESS_COLORS[500],
      WARNING_COLORS[500],
      ERROR_COLORS[500],
      ERROR_COLORS[700],
    ]);
  });

  it('defines non-overlapping monetary gap bands', () => {
    const wrapper = mountChart({ gaps });
    const ranges = wrapper.vm.chartOptions.plotOptions.heatmap.colourScale.ranges;

    expect(ranges.map(({ from, to }) => ({ from, to }))).toEqual([
      { from: 0, to: 50000 },
      { from: 50001, to: 150000 },
      { from: 150001, to: 500000 },
      { from: 500001, to: 10000000 },
    ]);
  });

  it('formats missing tooltip values without fabricating cover', () => {
    const wrapper = mountChart({ gaps });
    const formatter = wrapper.vm.chartOptions.tooltip.y.formatter;

    expect(formatter(null)).toBe('No data');
    expect(formatter(undefined)).toBe('No data');
  });

  it('updates its stable chart key when supplied analysis changes', async () => {
    const wrapper = mountChart({ gaps });
    expect(wrapper.vm.chartKey).toBe('heatmap-3-3');

    await wrapper.setProps({ gaps: gaps.slice(0, 2) });

    expect(wrapper.vm.chartKey).toBe('heatmap-2-2');
  });
});
