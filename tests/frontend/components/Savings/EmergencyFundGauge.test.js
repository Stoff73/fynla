import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import EmergencyFundGauge from '@/components/Savings/EmergencyFundGauge.vue';
import { SUCCESS_COLORS, WARNING_COLORS, ERROR_COLORS } from '@/constants/designSystem';

// The gauge draws the server's figures (SavingsPosition, CSJ 2026-10-01): the
// share of the target covered, the status against the user's own target and
// the runway figure. It works none of them out.
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

  it('fills to the server\'s covered percentage and prints its figure', () => {
    const wrapper = mount(EmergencyFundGauge, { props: { percent: 75, status: 'part', figure: '4.5' } });

    expect(wrapper.vm.chartKey).toBe('gauge-75');
    expect(wrapper.vm.chartOptions.plotOptions.radialBar.dataLabels.value.formatter()).toBe('4.5');
  });

  it('colours by the server\'s status, not by a typed-in month count', () => {
    const colour = (status) => mount(EmergencyFundGauge, { props: { percent: 0, status, figure: '' } }).vm.runwayColour;

    expect(colour('on_track')).toBe(SUCCESS_COLORS[500]);
    expect(colour('part')).toBe(WARNING_COLORS[500]);
    expect(colour('low')).toBe(ERROR_COLORS[500]);
  });
});
