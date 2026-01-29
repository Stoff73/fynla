import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import IHTLiabilityGauge from '@/components/Estate/IHTLiabilityGauge.vue';

describe('IHTLiabilityGauge', () => {
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

  it('renders with estate value and IHT liability props', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 100000,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('calculates IHT percentage correctly', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 100000,
      },
    });

    expect(wrapper.vm.ihtPercentage).toBe(10);
  });

  it('uses green color for low IHT (<10%)', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 50000, // 5%
      },
    });

    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#10b981/i); // Green
  });

  it('uses orange color for moderate IHT (10-20%)', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 150000, // 15%
      },
    });

    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#f97316/i); // Orange
  });

  it('uses red color for high IHT (>20%)', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 250000, // 25%
      },
    });

    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#ef4444/i); // Red
  });

  it('handles zero estate value without error', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 0,
        ihtLiability: 0,
      },
    });

    expect(wrapper.vm.ihtPercentage).toBe(0);
  });

  it('handles edge case at exactly 10% threshold', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 100000, // Exactly 10%
      },
    });

    expect(wrapper.vm.ihtPercentage).toBe(10);
    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#f97316/i); // Should be orange at 10%
  });

  it('handles edge case at exactly 20% threshold', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 200000, // Exactly 20%
      },
    });

    expect(wrapper.vm.ihtPercentage).toBe(20);
    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#ef4444/i); // Should be red at 20%
  });

  it('displays status text for good IHT level', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 30000,
      },
    });

    const statusText = wrapper.vm.statusText;
    expect(statusText).toMatch(/good|low|excellent/i);
  });

  it('displays status text for warning IHT level', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 150000,
      },
    });

    const statusText = wrapper.vm.statusText;
    expect(statusText).toMatch(/warning|moderate|review/i);
  });

  it('displays status text for critical IHT level', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 300000,
      },
    });

    const statusText = wrapper.vm.statusText;
    expect(statusText).toMatch(/critical|high|action/i);
  });

  it('formats IHT liability in pounds', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1200000,
        ihtLiability: 145000,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/£145,000|145000/);
  });

  it('calculates effective IHT rate', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 1000000,
        ihtLiability: 100000,
      },
    });

    expect(wrapper.vm.effectiveRate).toBe(10);
  });

  it('handles very large estate values', () => {
    const wrapper = mount(IHTLiabilityGauge, {
      props: {
        estateValue: 10000000,
        ihtLiability: 2000000,
      },
    });

    expect(wrapper.vm.ihtPercentage).toBe(20);
  });
});
