import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import IncomeProjectionChart from '@/components/Retirement/IncomeProjectionChart.vue';

describe('IncomeProjectionChart', () => {
  beforeEach(() => {
    // Mock ApexCharts if not already mocked globally
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

  const createMockStore = (dcPensions = [], dbPensions = [], statePension = null, profile = {}) => {
    return createStore({
      modules: {
        retirement: {
          namespaced: true,
          state: {
            dcPensions,
            dbPensions,
            statePension,
            profile: {
              current_age: 45,
              target_retirement_age: 67,
              life_expectancy: 90,
              ...profile,
            },
          },
        },
      },
    });
  };

  const defaultDCPensions = [
    {
      id: 1,
      current_fund_value: 100000,
    },
  ];

  const defaultDBPensions = [
    {
      id: 2,
      accrued_annual_pension: 15000,
    },
  ];

  const defaultStatePension = {
    state_pension_forecast_annual: 11500,
    state_pension_age: 67,
  };

  const defaultProfile = {
    current_age: 45,
    target_retirement_age: 67,
    life_expectancy: 90,
  };

  it('renders with required props', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('creates stacked area chart', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.chart.type).toBe('area');
    expect(chartOptions.chart.stacked).toBe(true);
  });

  it('includes the Defined Contribution Pension series', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const series = wrapper.vm.series;
    const dcSeries = series.find((s) => s.name === 'Defined Contribution Pension');
    expect(dcSeries).toBeDefined();
  });

  it('includes the Defined Benefit Pension series', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const series = wrapper.vm.series;
    const dbSeries = series.find((s) => s.name === 'Defined Benefit Pension');
    expect(dbSeries).toBeDefined();
  });

  it('includes State Pension series', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const series = wrapper.vm.series;
    const stateSeries = series.find((s) => s.name === 'State Pension');
    expect(stateSeries).toBeDefined();
  });

  it('displays target income line', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.annotations).toBeDefined();
  });

  it('uses correct age range on x-axis', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const ages = wrapper.vm.ages;
    expect(ages[0]).toBe(67); // Retirement age
    expect(ages[ages.length - 1]).toBe(90); // Life expectancy
  });

  it('calculates Defined Contribution income from the pot projected to retirement', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const dcIncomeData = wrapper.vm.dcIncomeData;
    // £100,000 grown at 5% for 22 years, then a 4% annual drawdown.
    expect(dcIncomeData[0]).toBe(11701);
  });

  it('projects DB pension correctly', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const dbIncomeData = wrapper.vm.dbIncomeData;
    // Should be £15,000 per year
    expect(dbIncomeData[0]).toBe(15000);
  });

  it('projects State Pension correctly', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const statePensionData = wrapper.vm.statePensionData;
    // Should be £11,500 per year
    expect(statePensionData[0]).toBe(11500);
  });

  it('uses different colors for each series', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.colours.length).toBeGreaterThanOrEqual(3);
    // Colors should be unique
    const uniqueColors = new Set(chartOptions.colours);
    expect(uniqueColors.size).toBeGreaterThanOrEqual(3);
  });

  it('shows tooltips with breakdown', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.tooltip).toBeDefined();
    expect(chartOptions.tooltip.shared).toBe(true);
    expect(chartOptions.tooltip.intersect).toBe(false);
    expect(chartOptions.tooltip.y.formatter(15000)).toBe('£15,000');
  });

  it('formats y-axis as currency', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.yaxis.labels.formatter).toBeDefined();
  });

  it('handles no DC pensions', () => {
    const store = createMockStore(
      [], // No DC pensions
      defaultDBPensions,
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const dcIncomeData = wrapper.vm.dcIncomeData;
    expect(dcIncomeData.every((val) => val === 0)).toBe(true);
  });

  it('handles no DB pensions', () => {
    const store = createMockStore(
      defaultDCPensions,
      [], // No DB pensions
      defaultStatePension,
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const dbIncomeData = wrapper.vm.dbIncomeData;
    expect(dbIncomeData.every((val) => val === 0)).toBe(true);
  });

  it('handles no State Pension', () => {
    const store = createMockStore(
      defaultDCPensions,
      defaultDBPensions,
      null, // No State Pension
      defaultProfile
    );
    const wrapper = mount(IncomeProjectionChart, {
      props: {
        targetIncome: 30000,
      },
      global: {
        plugins: [store],
      },
    });

    const statePensionData = wrapper.vm.statePensionData;
    expect(statePensionData.every((val) => val === 0)).toBe(true);
  });
});
