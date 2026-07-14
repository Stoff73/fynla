import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CashFlowProjectionChart from '@/components/Estate/CashFlowProjectionChart.vue';

describe('CashFlowProjectionChart', () => {
  beforeEach(() => {
    if (!global.ApexCharts) {
      global.ApexCharts = class {
        constructor() {}
        render() {}
        updateOptions() {}
        updateSeries() {}
        destroy() {}
      };
    }
  });

  it('renders with income and expenses props', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays empty state when no data provided', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 0,
        currentExpenses: 0,
      },
    });

    expect(wrapper.vm.hasData).toBe(false);
    const html = wrapper.html();
    expect(html).toMatch(/no.*data|empty/i);
  });

  it('projects cash flow for default 10 years', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    expect(wrapper.vm.projectionYears).toBe(10);
    expect(wrapper.vm.projectionData.length).toBe(10);
  });

  it('calculates net cash flow correctly', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const firstYear = wrapper.vm.projectionData[0];
    expect(firstYear.netCashFlow).toBe(15000);
  });

  it('applies growth rate to projections', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    wrapper.vm.growthRate = 3; // 3% growth

    const firstYear = wrapper.vm.projectionData[0];
    const secondYear = wrapper.vm.projectionData[1];

    expect(secondYear.income).toBeGreaterThan(firstYear.income);
    expect(secondYear.expenses).toBeGreaterThan(firstYear.expenses);
  });

  it('calculates total projected income', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const totalIncome = wrapper.vm.totalProjectedIncome;
    expect(totalIncome).toBeGreaterThan(600000); // At least 10 years * 60k
  });

  it('calculates total projected expenses', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const totalExpenses = wrapper.vm.totalProjectedExpenses;
    expect(totalExpenses).toBeGreaterThan(450000); // At least 10 years * 45k
  });

  it('calculates cumulative surplus correctly', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const cumulative = wrapper.vm.calculateCumulative();
    expect(cumulative.length).toBe(10);
    expect(cumulative[cumulative.length - 1]).toBeGreaterThan(0); // Positive cumulative
  });

  it('identifies deficit scenarios', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 30000,
        currentExpenses: 50000, // Spending more than earning
      },
    });

    const firstYear = wrapper.vm.projectionData[0];
    expect(firstYear.netCashFlow).toBeLessThan(0);
  });

  it('calculates cumulative surplus class correctly', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    expect(wrapper.vm.cumulativeSurplusClass).toBe('positive');
  });

  it('calculates cumulative deficit class correctly', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 30000,
        currentExpenses: 50000,
      },
    });

    expect(wrapper.vm.cumulativeSurplusClass).toBe('negative');
  });

  it('changes projection period when updated', async () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    wrapper.vm.projectionYears = 20;
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.projectionData.length).toBe(20);
  });

  it('handles 0% growth rate', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    wrapper.vm.growthRate = 0;

    const firstYear = wrapper.vm.projectionData[0];
    const lastYear = wrapper.vm.projectionData[9];

    expect(firstYear.income).toBe(60000);
    expect(lastYear.income).toBe(60000); // No growth
  });

  it('handles 5% growth rate', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    wrapper.vm.growthRate = 5;

    const secondYear = wrapper.vm.projectionData[1];

    const expectedIncome = Math.round(60000 * 1.05);
    expect(secondYear.income).toBeCloseTo(expectedIncome, -2);
  });

  it('formats currency values correctly', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const formatted = wrapper.vm.formatCurrency(60000);
    expect(formatted).toMatch(/£60,000|60000/);
  });

  it('formats compact currency values for axis labels', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const formatted = wrapper.vm.chartOptions.yaxis[0].labels.formatter(1500000);
    expect(formatted).toMatch(/£1\.5M|1.5M/);
  });

  it('generates chart series with net cash flow and cumulative', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const series = wrapper.vm.series;
    expect(series).toHaveLength(2);
    expect(series[0].name).toMatch(/net.*cash.*flow/i);
    expect(series[1].name).toMatch(/cumulative/i);
  });

  it('projects years starting from current year', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const currentYear = new Date().getFullYear();
    const firstYear = wrapper.vm.projectionData[0];
    expect(firstYear.year).toBe(currentYear);
  });

  it('displays surplus icon for positive cumulative', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const icon = wrapper.vm.cumulativeSurplusIcon;
    expect(icon).toMatch(/check|arrow.*up/i);
  });

  it('displays deficit icon for negative cumulative', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 30000,
        currentExpenses: 50000,
      },
    });

    const icon = wrapper.vm.cumulativeSurplusIcon;
    expect(icon).toMatch(/exclamation|warning|triangle/i);
  });

  it('creates bar chart configuration', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.chart.type).toBe('bar');
  });

  it('uses correct colors for positive and negative cash flow', () => {
    const wrapper = mount(CashFlowProjectionChart, {
      props: {
        currentIncome: 60000,
        currentExpenses: 45000,
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.plotOptions.bar.colours.ranges).toHaveLength(2);
  });
});
