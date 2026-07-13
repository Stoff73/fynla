import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import InvestmentOverviewCard from '@/components/Investment/InvestmentOverviewCard.vue';

describe('InvestmentOverviewCard', () => {
  it('renders with props', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 150000,
        ytdReturn: 8.5,
        holdingsCount: 12,
        needsRebalancing: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('150');
    expect(wrapper.text()).toContain('8.5');
    expect(wrapper.text()).toContain('12');
  });

  it('displays portfolio value with currency formatting', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 250000,
        ytdReturn: 5.2,
        holdingsCount: 8,
        needsRebalancing: false,
      },
    });

    const text = wrapper.text();
    // Should format large currency values
    expect(text).toMatch(/£250,000|£250K|250k/i);
  });

  it('displays positive YTD return with the spring success colour', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 12.5,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('12.5');
    expect(html).toContain('text-spring-600');
  });

  it('displays negative YTD return with the raspberry danger colour', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: -5.2,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('-5.2');
    expect(html).toContain('text-raspberry-600');
  });

  it('displays zero YTD return with neutral color', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 0,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('0');
    expect(html).toContain('text-spring-500');
  });

  it('displays holdings count', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 5.0,
        holdingsCount: 25,
        needsRebalancing: false,
      },
    });

    expect(wrapper.text()).toContain('25');
    expect(wrapper.text()).toMatch(/holding|security|position/i);
  });

  it('displays rebalancing alert when needed', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 5.0,
        holdingsCount: 10,
        needsRebalancing: true,
      },
    });

    const html = wrapper.html();
    const text = wrapper.text();
    // Should show rebalancing alert
    expect(text).toMatch(/rebalanc/i);
    expect(html).toContain('text-violet-800');
  });

  it('displays balanced status when not needing rebalancing', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 5.0,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('text-spring-800');
  });

  it('navigates to Investment Dashboard on click', async () => {
    const mockRouter = {
      push: vi.fn(),
    };

    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 5.0,
        holdingsCount: 10,
        needsRebalancing: false,
      },
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.trigger('click');
    expect(mockRouter.push).toHaveBeenCalledWith('/investment');
  });

  it('handles zero portfolio value', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 0,
        ytdReturn: 0,
        holdingsCount: 0,
        needsRebalancing: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('0');
  });

  it('handles very large portfolio value', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 5000000,
        ytdReturn: 7.5,
        holdingsCount: 50,
        needsRebalancing: false,
      },
    });

    const text = wrapper.text();
    // Should format millions appropriately
    expect(text).toMatch(/5,000,000|5M|5\.0M/i);
  });

  it('handles single holding', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 50000,
        ytdReturn: 3.2,
        holdingsCount: 1,
        needsRebalancing: false,
      },
    });

    expect(wrapper.text()).toContain('1');
  });

  it('displays percentage symbol for YTD return', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 8.5,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    expect(wrapper.text()).toContain('%');
  });

  it('has hover effect applied', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 5.0,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('hover:shadow');
    expect(html).toContain('cursor-pointer');
  });

  it('handles very high positive returns', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 100000,
        ytdReturn: 150.5,
        holdingsCount: 10,
        needsRebalancing: false,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('150.5');
    expect(html).toContain('text-spring-600');
  });

  it('handles very high negative returns', () => {
    const wrapper = mount(InvestmentOverviewCard, {
      props: {
        portfolioValue: 50000,
        ytdReturn: -45.8,
        holdingsCount: 5,
        needsRebalancing: true,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toContain('-45.8');
    expect(html).toContain('text-raspberry-600');
  });
});
