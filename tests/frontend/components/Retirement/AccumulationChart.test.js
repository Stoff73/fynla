import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AccumulationChart from '@/components/Retirement/AccumulationChart.vue';

describe('AccumulationChart', () => {
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

  const createMockStore = (dcPensions = [], profile = {}) => {
    return createStore({
      modules: {
        retirement: {
          namespaced: true,
          state: {
            dcPensions,
            profile: {
              current_age: 40,
              target_retirement_age: 67,
              current_income: 50000,
              ...profile,
            },
          },
        },
      },
    });
  };

  it('renders with empty pensions', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays line chart type', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.chart.type).toBe('line');
  });

  it('has two series (with growth and contributions only)', () => {
    const dcPensions = [
      {
        current_fund_value: 100000,
        employee_contribution_percent: 5,
        employer_contribution_percent: 3,
        expected_return_percent: 5.0,
      },
    ];

    const store = createMockStore(dcPensions);
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const series = wrapper.vm.series;
    expect(series.length).toBe(2);
    expect(series[0].name).toBe('With Investment Growth');
    expect(series[1].name).toBe('Contributions Only');
  });

  it('calculates age range correctly', () => {
    const store = createMockStore([], { current_age: 40, target_retirement_age: 67 });
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const ages = wrapper.vm.ages;
    expect(ages[0]).toBe(40);
    expect(ages[ages.length - 1]).toBe(67);
    expect(ages.length).toBe(28); // 40 to 67 inclusive
  });

  it('projects fund value with growth', () => {
    const dcPensions = [
      {
        current_fund_value: 100000,
        employee_contribution_percent: 5,
        employer_contribution_percent: 3,
        expected_return_percent: 5.0,
      },
    ];

    const store = createMockStore(dcPensions, {
      current_age: 40,
      target_retirement_age: 43, // Short period for testing
      current_income: 50000,
    });

    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const projectionData = wrapper.vm.projectionData;
    // First year should be current value
    expect(projectionData[0]).toBe(100000);
    // Should grow each year with contributions and returns
    expect(projectionData[projectionData.length - 1]).toBeGreaterThan(100000);
  });

  it('projects contributions only without growth', () => {
    const dcPensions = [
      {
        current_fund_value: 100000,
        employee_contribution_percent: 5,
        employer_contribution_percent: 3,
        expected_return_percent: 5.0,
      },
    ];

    const store = createMockStore(dcPensions, {
      current_age: 40,
      target_retirement_age: 41, // 1 year
      current_income: 50000,
    });

    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const contributionsOnlyData = wrapper.vm.contributionsOnlyData;
    // First year should be current value
    expect(contributionsOnlyData[0]).toBe(100000);
    // After 1 year: 100000 + (50000 * 8% = 4000)
    expect(contributionsOnlyData[1]).toBe(104000);
  });

  it('shows with-growth projection higher than contributions-only', () => {
    const dcPensions = [
      {
        current_fund_value: 100000,
        employee_contribution_percent: 5,
        employer_contribution_percent: 3,
        expected_return_percent: 5.0,
      },
    ];

    const store = createMockStore(dcPensions, {
      current_age: 40,
      target_retirement_age: 50, // 10 years
      current_income: 50000,
    });

    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const projectionData = wrapper.vm.projectionData;
    const contributionsOnlyData = wrapper.vm.contributionsOnlyData;

    // With growth should be higher than contributions only
    const finalWithGrowth = projectionData[projectionData.length - 1];
    const finalContributionsOnly = contributionsOnlyData[contributionsOnlyData.length - 1];

    expect(finalWithGrowth).toBeGreaterThan(finalContributionsOnly);
  });

  it('uses the raspberry accent for the growth line', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.colors[0]).toBe('#E83E6D');
  });

  it('uses the horizon colour for the contributions-only line', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.colors[1]).toBe('#1F2A44');
  });

  it('formats y-axis as currency', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.yaxis.labels.formatter).toBeDefined();
    const formatted = chartOptions.yaxis.labels.formatter(100000);
    expect(formatted).toMatch(/£100,?000/);
  });

  it('uses smooth curves for lines', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.stroke.curve).toBe('smooth');
  });

  it('uses dashed line for contributions-only series', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.stroke.dashArray).toEqual([0, 5]); // Solid, then dashed
  });

  it('handles multiple DC pensions', () => {
    const dcPensions = [
      {
        current_fund_value: 50000,
        employee_contribution_percent: 5,
        employer_contribution_percent: 3,
        expected_return_percent: 5.0,
      },
      {
        current_fund_value: 30000,
        employee_contribution_percent: 4,
        employer_contribution_percent: 4,
        expected_return_percent: 6.0,
      },
    ];

    const store = createMockStore(dcPensions, {
      current_age: 40,
      target_retirement_age: 41,
      current_income: 50000,
    });

    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const projectionData = wrapper.vm.projectionData;
    // Should start with combined current value
    expect(projectionData[0]).toBe(80000); // 50000 + 30000
  });

  it('calculates years to retirement correctly', () => {
    const store = createMockStore([], { current_age: 45, target_retirement_age: 67 });
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.vm.yearsToRetirement).toBe(22);
  });

  it('shows legend with both series', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.legend.position).toBe('top');
  });

  it('enables chart toolbar', () => {
    const store = createMockStore();
    const wrapper = mount(AccumulationChart, {
      global: {
        plugins: [store],
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.chart.toolbar.show).toBe(true);
  });
});
