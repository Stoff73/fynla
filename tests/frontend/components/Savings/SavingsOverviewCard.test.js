import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SavingsOverviewCard from '@/components/Savings/SavingsOverviewCard.vue';

describe('SavingsOverviewCard', () => {
  it('renders with props', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 6.5,
        totalSavings: 50000,
        isaUsagePercent: 45,
        goalsStatus: {
          onTrack: 3,
          total: 5,
        },
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('6.5');
    expect(wrapper.text()).toContain('50,000');
    expect(wrapper.text()).toContain('45');
    expect(wrapper.text()).toContain('3');
    expect(wrapper.text()).toContain('5');
  });

  it('displays emergency fund runway with green color (6+ months)', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 7,
        totalSavings: 50000,
        isaUsagePercent: 45,
        goalsStatus: { onTrack: 3, total: 5 },
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('7');
    // Should have green color for 6+ months
    expect(html).toMatch(/text-green|bg-green/);
  });

  it('displays emergency fund runway with orange color (3-6 months)', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 4.5,
        totalSavings: 30000,
        isaUsagePercent: 25,
        goalsStatus: { onTrack: 2, total: 4 },
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('4.5');
    // Should have orange/yellow color for 3-6 months
    expect(html).toMatch(/text-orange|text-yellow|bg-orange|bg-yellow/);
  });

  it('displays emergency fund runway with red color (<3 months)', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 2,
        totalSavings: 10000,
        isaUsagePercent: 10,
        goalsStatus: { onTrack: 1, total: 3 },
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('2');
    // Should have red color for <3 months
    expect(html).toMatch(/text-red|bg-red/);
  });

  it('displays total savings with currency formatting', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 6,
        totalSavings: 123456.78,
        isaUsagePercent: 50,
        goalsStatus: { onTrack: 2, total: 3 },
      },
    });

    const text = wrapper.text();
    // Should format with commas or abbreviated
    expect(text).toMatch(/123,456|123\.5K|123K/i);
  });

  it('displays ISA usage percentage', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 6,
        totalSavings: 50000,
        isaUsagePercent: 75,
        goalsStatus: { onTrack: 2, total: 3 },
      },
    });

    expect(wrapper.text()).toContain('75');
    expect(wrapper.text()).toMatch(/ISA|isa/);
  });

  it('displays goals on track status', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 6,
        totalSavings: 50000,
        isaUsagePercent: 50,
        goalsStatus: {
          onTrack: 4,
          total: 6,
        },
      },
    });

    const text = wrapper.text();
    expect(text).toContain('4');
    expect(text).toContain('6');
    expect(text).toMatch(/goal|track/i);
  });

  it('navigates to Savings Dashboard on click', async () => {
    const mockRouter = {
      push: vi.fn(),
    };

    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 6,
        totalSavings: 50000,
        isaUsagePercent: 50,
        goalsStatus: { onTrack: 2, total: 3 },
      },
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.trigger('click');
    expect(mockRouter.push).toHaveBeenCalledWith('/savings');
  });

  it('handles zero emergency fund runway', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 0,
        totalSavings: 0,
        isaUsagePercent: 0,
        goalsStatus: { onTrack: 0, total: 0 },
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('0');
  });

  it('handles all goals on track', () => {
    const wrapper = mount(SavingsOverviewCard, {
      props: {
        emergencyFundRunway: 8,
        totalSavings: 75000,
        isaUsagePercent: 100,
        goalsStatus: {
          onTrack: 5,
          total: 5,
        },
      },
    });

    const text = wrapper.text();
    expect(text).toContain('5');
    // Should show all goals are on track
    expect(wrapper.exists()).toBe(true);
  });
});
