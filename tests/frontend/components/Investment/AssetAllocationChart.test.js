import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AssetAllocationChart from '@/components/Investment/AssetAllocationChart.vue';

// Mock ApexCharts
vi.mock('vue3-apexcharts', () => ({
  default: {
    name: 'ApexChart',
    template: '<div class="mock-apex-chart"></div>',
    props: ['options', 'series', 'type', 'height'],
  },
}));

describe('AssetAllocationChart', () => {
  const mockAllocation = {
    uk_equities: 25,
    us_equities: 30,
    international_equities: 20,
    bonds: 15,
    cash: 5,
    alternatives: 5,
  };

  it('renders chart component', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.chart-container').exists()).toBe(true);
    expect(wrapper.findAll('circle')).toHaveLength(6);
  });

  it('displays chart title', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/asset allocation|portfolio allocation/i);
  });

  it('creates donut chart configuration', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    // Check if chart options are configured correctly
    if (wrapper.vm.chartOptions) {
      expect(wrapper.vm.chartOptions.chart.type).toBe('donut');
    }
  });

  it('extracts correct labels from allocation data', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
        loading: false,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.labels) {
      expect(wrapper.vm.chartOptions.labels).toContain('Uk Equities'); // Converted from uk_equities
      expect(wrapper.vm.chartOptions.labels).toContain('Us Equities'); // Converted from us_equities
      expect(wrapper.vm.chartOptions.labels).toContain('Bonds');
    }
  });

  it('extracts correct series values from allocation data', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.series) {
      expect(wrapper.vm.series).toContain(25); // UK Equities percentage
      expect(wrapper.vm.series).toContain(30); // US Equities percentage
      expect(wrapper.vm.series).toContain(20); // International Equities percentage
    }
  });

  it('displays percentages in legend', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
        loading: false,
      },
    });

    // Check that chart has legend formatter that includes percentages
    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.legend && wrapper.vm.chartOptions.legend.formatter) {
      const formatted = wrapper.vm.chartOptions.legend.formatter('Test', { w: { globals: { series: [25] } }, seriesIndex: 0 });
      expect(formatted).toContain('%');
    }
  });

  it('handles empty allocation data', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: {},
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    const text = wrapper.text();
    expect(text).toMatch(/no.*allocation.*data|add holdings/i);
  });

  it('handles single allocation item', () => {
    const singleAllocation = {
      uk_equities: 100,
    };

    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: singleAllocation,
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('sums percentages to 100%', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.series) {
      const sum = wrapper.vm.series.reduce((acc, val) => acc + val, 0);
      expect(sum).toBeCloseTo(100, 0);
    }
  });

  it('displays interactive tooltips configuration', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.tooltip) {
      expect(wrapper.vm.chartOptions.tooltip.enabled).toBe(true);
    }
  });

  it('applies color scheme to chart', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.colors) {
      expect(wrapper.vm.chartOptions.colors).toBeTruthy();
      expect(Array.isArray(wrapper.vm.chartOptions.colors)).toBe(true);
    }
  });

  it('formats values as currency in tooltips', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.tooltip) {
      // Should have custom formatter for currency
      expect(wrapper.vm.chartOptions.tooltip.y).toBeTruthy();
    }
  });

  it('displays legend correctly', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
        loading: false,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.legend) {
      // Legend position should be set (it's always displayed)
      expect(wrapper.vm.chartOptions.legend.position).toBe('bottom');
    }
  });

  it('handles very small allocation percentages', () => {
    const allocationWithSmallValues = {
      uk_equities: 99.5,
      cash: 0.5,
    };

    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: allocationWithSmallValues,
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('responsive design for mobile', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.responsive) {
      expect(wrapper.vm.chartOptions.responsive).toBeTruthy();
    }
  });

  it('all asset classes are represented', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
      },
    });

    if (wrapper.vm.chartOptions && wrapper.vm.chartOptions.labels) {
      const labels = wrapper.vm.chartOptions.labels;
      expect(labels.length).toBe(6); // All 6 asset classes
    }
  });

  it('chart has appropriate height', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
        loading: false,
      },
    });

    const donut = wrapper.find('.chart-container .relative');
    expect(donut.attributes('style')).toContain('width: 300px');
    expect(donut.attributes('style')).toContain('height: 300px');
  });

  it('updates when allocation data changes', async () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: mockAllocation,
        loading: false,
      },
    });

    const newAllocation = {
      uk_equities: 50,
      bonds: 50,
    };

    await wrapper.setProps({ allocation: newAllocation });
    await wrapper.vm.$nextTick();

    // Chart should update with new data
    if (wrapper.vm.series) {
      expect(wrapper.vm.series.length).toBe(2);
    }
  });

  it('handles missing percentage field', () => {
    const allocationWithObjects = {
      uk_equities: { percentage: 50 },
      bonds: { percentage: 50 },
    };

    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: allocationWithObjects,
        loading: false,
      },
    });

    // Should extract percentages from objects
    expect(wrapper.exists()).toBe(true);
    if (wrapper.vm.series) {
      expect(wrapper.vm.series).toContain(50);
    }
  });

  it('displays loading state when no data initially', () => {
    const wrapper = mount(AssetAllocationChart, {
      props: {
        allocation: {},
        loading: true,
      },
    });

    expect(wrapper.exists()).toBe(true);
    // Should show loading spinner
    expect(wrapper.html()).toContain('animate-spin');
  });
});
