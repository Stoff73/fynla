import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import EmergencyFundGauge from '@/components/Savings/EmergencyFundGauge.vue';

/**
 * The gauge draws the server's figures (2026-10-01 one-figure audit items 22
 * and 23): the runway as the label, and `analysis.emergency_fund.percent_of_target`
 * as the fill and the colour band. It used to divide the runway by a target of
 * 6 months on the client, so a self-employed user (9-month target) or a retired
 * one (3) saw a fill that disagreed with every other surface.
 */
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

  const gauge = (runwayMonths, percentOfTarget) => mount(EmergencyFundGauge, {
    props: { runwayMonths, percentOfTarget },
  });

  it('renders the runway it is given', () => {
    const wrapper = gauge(7.2, 120);

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.vm.chartOptions.plotOptions.radialBar.dataLabels.value.formatter()).toBe('7.2');
  });

  it('fills to the server percentage, not runway over six months', () => {
    // 4.5 months against a 9-month target is 50%. Runway over 6 would be 75%.
    expect(gauge(4.5, 50).vm.runwayPercentage).toBe(50);
  });

  it('caps the fill at the full ring', () => {
    expect(gauge(12, 200).vm.runwayPercentage).toBe(100);
  });

  it('draws an empty ring when there is no percentage to draw', () => {
    expect(gauge(0, null).vm.runwayPercentage).toBe(0);
  });

  it('colours by the share of the user\'s own target, in the /m bands', () => {
    const atTarget = gauge(3, 100).vm.runwayColour;
    const half = gauge(1.5, 50).vm.runwayColour;
    const below = gauge(1, 33).vm.runwayColour;

    expect(atTarget).toMatch(/^#[0-9a-f]{6}$/i);
    expect(new Set([atTarget, half, below]).size).toBe(3);
    // Three months at a three-month target is on target, even though it is
    // below the six months the old hardcoded bands demanded.
    expect(atTarget).toBe(gauge(8, 150).vm.runwayColour);
    expect(gauge(4.5, 50).vm.runwayColour).toBe(half);
    expect(wrapperColour(gauge(4.5, 50))).toEqual([half]);
  });

  it('names the basis of the figure', () => {
    // "Months from cash savings", not "Months Runway": the figure counts ALL cash,
    // including notice and fixed-term accounts (W-0276).
    expect(gauge(6, 100).vm.chartOptions.labels).toContain('Months from cash savings');
  });
});

function wrapperColour(wrapper) {
  return wrapper.vm.chartOptions.fill.colours;
}
