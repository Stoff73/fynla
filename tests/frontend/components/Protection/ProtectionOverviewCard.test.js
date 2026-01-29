import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ProtectionOverviewCard from '@/components/Protection/ProtectionOverviewCard.vue';

describe('ProtectionOverviewCard', () => {
  it('renders with props', () => {
    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 75,
        totalCoverage: 500000,
        premiumTotal: 150,
        criticalGaps: 2,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('75');
    expect(wrapper.text()).toContain('£500,000');
    expect(wrapper.text()).toContain('£150');
  });

  it('displays adequacy score with correct color (green for 80+)', () => {
    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 85,
        totalCoverage: 500000,
        premiumTotal: 150,
        criticalGaps: 0,
      },
    });

    const scoreElement = wrapper.find('[data-testid="adequacy-score"]');
    expect(scoreElement.text()).toContain('85');
    // Check for green color class
    expect(wrapper.html()).toMatch(/text-green|bg-green/);
  });

  it('displays adequacy score with orange color (60-79)', () => {
    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 70,
        totalCoverage: 300000,
        premiumTotal: 100,
        criticalGaps: 1,
      },
    });

    const scoreElement = wrapper.find('[data-testid="adequacy-score"]');
    expect(scoreElement.text()).toContain('70');
    // Check for orange/yellow color class
    expect(wrapper.html()).toMatch(/text-orange|text-yellow|bg-orange|bg-yellow/);
  });

  it('displays adequacy score with red color (<60)', () => {
    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 45,
        totalCoverage: 200000,
        premiumTotal: 80,
        criticalGaps: 3,
      },
    });

    const scoreElement = wrapper.find('[data-testid="adequacy-score"]');
    expect(scoreElement.text()).toContain('45');
    // Check for red color class
    expect(wrapper.html()).toMatch(/text-red|bg-red/);
  });

  it('navigates to Protection Dashboard on click', async () => {
    const mockRouter = {
      push: vi.fn(),
    };

    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 75,
        totalCoverage: 500000,
        premiumTotal: 150,
        criticalGaps: 2,
      },
      global: {
        mocks: {
          $router: mockRouter,
        },
      },
    });

    await wrapper.trigger('click');
    expect(mockRouter.push).toHaveBeenCalledWith('/protection');
  });

  it('displays critical gaps count', () => {
    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 60,
        totalCoverage: 400000,
        premiumTotal: 120,
        criticalGaps: 3,
      },
    });

    expect(wrapper.text()).toContain('3');
    expect(wrapper.text()).toMatch(/gap|critical/i);
  });

  it('formats currency values correctly', () => {
    const wrapper = mount(ProtectionOverviewCard, {
      props: {
        adequacyScore: 75,
        totalCoverage: 1234567,
        premiumTotal: 234.5,
        criticalGaps: 1,
      },
    });

    // Should format large numbers with commas
    expect(wrapper.text()).toMatch(/1,234,567|1\.2M/);
    // Should format premium with 2 decimal places
    expect(wrapper.text()).toMatch(/234\.50|234\.5|235/);
  });
});
