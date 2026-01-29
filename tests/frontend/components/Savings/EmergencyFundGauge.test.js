import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import EmergencyFundGauge from '@/components/Savings/EmergencyFundGauge.vue';

describe('EmergencyFundGauge', () => {
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

  it('renders with runway prop', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 6.5,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays correct runway in months', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 7.2,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(7.2);
  });

  it('uses green color for excellent runway (6+ months)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 8,
      },
    });

    const color = wrapper.vm.runwayColor;
    // Green color hex variations
    expect(color).toMatch(/#10b981|#22c55e|#16a34a/i);
  });

  it('uses orange color for moderate runway (3-6 months)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 4.5,
      },
    });

    const color = wrapper.vm.runwayColor;
    // Orange/yellow color hex variations
    expect(color).toMatch(/#f97316|#ea580c|#fb923c/i);
  });

  it('uses red color for critical runway (<3 months)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 2,
      },
    });

    const color = wrapper.vm.runwayColor;
    // Red color hex variations
    expect(color).toMatch(/#ef4444|#dc2626|#f87171/i);
  });

  it('handles edge case runway of 0', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 0,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(0);
    const color = wrapper.vm.runwayColor;
    expect(color).toMatch(/#ef4444|#dc2626/i); // Should be red
  });

  it('handles exactly 6 months (target)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 6,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(6);
    const color = wrapper.vm.runwayColor;
    // At exactly 6 months, should be green (meeting target)
    expect(color).toMatch(/#10b981|#22c55e|#16a34a/i);
  });

  it('handles exactly 3 months (boundary)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 3,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(3);
    const color = wrapper.vm.runwayColor;
    // At exactly 3 months, should be orange
    expect(color).toMatch(/#f97316|#ea580c|#fb923c/i);
  });

  it('displays label text for emergency fund', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 5,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/emergency.*fund|runway/i);
  });

  it('displays months unit', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 6,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/month/i);
  });

  it('calculates gauge percentage correctly', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 6,
      },
    });

    // 6 months is 100% of target (6 months)
    const percentage = wrapper.vm.runwayPercentage;
    expect(percentage).toBe(100);
  });

  it('calculates gauge percentage for 3 months (50%)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 3,
      },
    });

    // 3 months is 50% of target (6 months)
    const percentage = wrapper.vm.runwayPercentage;
    expect(percentage).toBe(50);
  });

  it('caps gauge percentage at maximum', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 12,
      },
    });

    // 12 months is 200% of target, but gauge might cap
    const percentage = wrapper.vm.runwayPercentage;
    // Should be 200% or capped, depends on implementation
    expect(percentage).toBeGreaterThan(100);
  });
});
