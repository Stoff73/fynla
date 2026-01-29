import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CoverageAdequacyGauge from '@/components/Protection/CoverageAdequacyGauge.vue';

describe('CoverageAdequacyGauge', () => {
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

  it('renders with score prop', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 75,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays correct score (0-100)', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 85,
      },
    });

    expect(wrapper.vm.score).toBe(85);
  });

  it('uses green color for excellent score (80+)', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 90,
      },
    });

    const color = wrapper.vm.gaugeColor;
    // Green color hex variations
    expect(color).toMatch(/#10b981|#22c55e|#16a34a/i);
  });

  it('uses orange color for good score (60-79)', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 70,
      },
    });

    const color = wrapper.vm.gaugeColor;
    // Orange/yellow color hex variations
    expect(color).toMatch(/#f97316|#ea580c|#fb923c/i);
  });

  it('uses red color for critical score (<60)', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 45,
      },
    });

    const color = wrapper.vm.gaugeColor;
    // Red color hex variations
    expect(color).toMatch(/#ef4444|#dc2626|#f87171/i);
  });

  it('handles edge case score of 0', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 0,
      },
    });

    expect(wrapper.vm.score).toBe(0);
    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#ef4444|#dc2626/i); // Should be red
  });

  it('handles edge case score of 100', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 100,
      },
    });

    expect(wrapper.vm.score).toBe(100);
    const color = wrapper.vm.gaugeColor;
    expect(color).toMatch(/#10b981|#22c55e/i); // Should be green
  });

  it('displays label text', () => {
    const wrapper = mount(CoverageAdequacyGauge, {
      props: {
        score: 75,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/adequacy|coverage|score/i);
  });
});
