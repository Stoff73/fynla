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

    expect(wrapper.vm.runwayColour).toMatch(/^#[0-9a-f]{6}$/i);
    expect(wrapper.vm.chartOptions.fill.colours).toEqual([wrapper.vm.runwayColour]);
  });

  it('uses orange color for moderate runway (3-6 months)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 4.5,
      },
    });

    expect(wrapper.vm.runwayColour).toMatch(/^#[0-9a-f]{6}$/i);
    expect(wrapper.vm.runwayColour).not.toBe(mount(EmergencyFundGauge, {
      props: { runwayMonths: 6 },
    }).vm.runwayColour);
  });

  it('uses red color for critical runway (<3 months)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 2,
      },
    });

    expect(wrapper.vm.runwayColour).toMatch(/^#[0-9a-f]{6}$/i);
    expect(wrapper.vm.runwayColour).not.toBe(mount(EmergencyFundGauge, {
      props: { runwayMonths: 3 },
    }).vm.runwayColour);
  });

  it('handles edge case runway of 0', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 0,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(0);
    expect(wrapper.vm.runwayColour).toBe(mount(EmergencyFundGauge, {
      props: { runwayMonths: 2 },
    }).vm.runwayColour);
  });

  it('handles exactly 6 months (target)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 6,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(6);
    expect(wrapper.vm.runwayColour).toBe(mount(EmergencyFundGauge, {
      props: { runwayMonths: 8 },
    }).vm.runwayColour);
  });

  it('handles exactly 3 months (boundary)', () => {
    const wrapper = mount(EmergencyFundGauge, {
      props: {
        runwayMonths: 3,
      },
    });

    expect(wrapper.vm.runwayMonths).toBe(3);
    expect(wrapper.vm.runwayColour).toBe(mount(EmergencyFundGauge, {
      props: { runwayMonths: 4.5 },
    }).vm.runwayColour);
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

    expect(wrapper.vm.chartOptions.labels).toContain('Months Runway');
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

    // The radial gauge caps values above the six-month target.
    const percentage = wrapper.vm.runwayPercentage;
    expect(percentage).toBe(100);
  });
});
