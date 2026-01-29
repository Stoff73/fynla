import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EstateOverviewCard from '@/components/Estate/EstateOverviewCard.vue';

describe('EstateOverviewCard', () => {
  it('renders with all required props', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 750000,
        ihtLiability: 50000,
        probateReadiness: 75,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays net worth correctly formatted', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 850000,
        ihtLiability: 60000,
        probateReadiness: 80,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/£850,000|850000/);
  });

  it('displays IHT liability with red color for high values', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 1000000,
        ihtLiability: 200000, // High IHT
        probateReadiness: 60,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/200,000|200000/);
    expect(html).toMatch(/text-red-600/);
  });

  it('displays probate readiness percentage', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 500000,
        ihtLiability: 0,
        probateReadiness: 85,
      },
    });

    expect(wrapper.vm.probateReadiness).toBe(85);
    const html = wrapper.html();
    expect(html).toMatch(/85%/);
  });

  it('displays probate readiness with correct color coding', () => {
    const highReadiness = mount(EstateOverviewCard, {
      props: {
        netWorth: 600000,
        ihtLiability: 30000,
        probateReadiness: 90,
      },
    });

    expect(highReadiness.vm.probateReadinessColor).toMatch(/green/);

    const mediumReadiness = mount(EstateOverviewCard, {
      props: {
        netWorth: 600000,
        ihtLiability: 30000,
        probateReadiness: 60,
      },
    });

    expect(mediumReadiness.vm.probateReadinessColor).toMatch(/orange/);

    const lowReadiness = mount(EstateOverviewCard, {
      props: {
        netWorth: 600000,
        ihtLiability: 30000,
        probateReadiness: 30,
      },
    });

    expect(lowReadiness.vm.probateReadinessColor).toMatch(/red/);
  });

  it('emits click event when card is clicked', async () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 700000,
        ihtLiability: 40000,
        probateReadiness: 70,
      },
    });

    await wrapper.trigger('click');
    expect(wrapper.emitted()).toHaveProperty('click');
  });

  it('handles zero net worth', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 0,
        ihtLiability: 0,
        probateReadiness: 0,
      },
    });

    expect(wrapper.vm.netWorth).toBe(0);
    expect(wrapper.exists()).toBe(true);
  });

  it('handles negative net worth (liabilities exceed assets)', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: -50000,
        ihtLiability: 0,
        probateReadiness: 20,
      },
    });

    expect(wrapper.vm.netWorth).toBe(-50000);
  });

  it('displays estate planning card title', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 800000,
        ihtLiability: 50000,
        probateReadiness: 75,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/estate|planning/i);
  });

  it('shows IHT planning recommended banner when liability > 0', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 800000,
        ihtLiability: 50000,
        probateReadiness: 70,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/IHT planning recommended/i);
  });

  it('shows no IHT liability banner when liability is 0', () => {
    const wrapper = mount(EstateOverviewCard, {
      props: {
        netWorth: 300000,
        ihtLiability: 0,
        probateReadiness: 90,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/No IHT liability forecast/i);
  });
});
