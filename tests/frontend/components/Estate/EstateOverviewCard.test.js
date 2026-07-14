import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import EstateOverviewCard from '@/components/Estate/EstateOverviewCard.vue';

describe('EstateOverviewCard', () => {
  let router;

  const mountCard = (props = {}) => mount(EstateOverviewCard, {
    props: {
      taxableEstate: 750000,
      ihtLiability: 50000,
      probateReadiness: 0,
      ...props,
    },
    global: {
      mocks: {
        $router: router,
        $store: { state: { auth: { user: null } } },
      },
    },
  });

  beforeEach(() => {
    router = { push: vi.fn() };
  });

  it('renders with the current required estate values', () => {
    expect(mountCard().exists()).toBe(true);
  });

  it('formats the current taxable estate', () => {
    expect(mountCard({ taxableEstate: 850000 }).text()).toContain('£850,000');
  });

  it('uses the danger palette for a high Inheritance Tax liability', () => {
    const wrapper = mountCard({ ihtLiability: 200000 });
    expect(wrapper.text()).toContain('£200,000');
    expect(wrapper.vm.ihtLiabilityColour).toContain('raspberry');
  });

  it('uses the success palette when no Inheritance Tax is due', () => {
    expect(mountCard({ ihtLiability: 0 }).vm.ihtLiabilityColour).toContain('spring');
  });

  it('renders supplied future estate and liability values', () => {
    const text = mountCard({ futureTaxableEstate: 900000, futureIHTLiability: 160000 }).text();
    expect(text).toContain('£900,000');
    expect(text).toContain('£160,000');
  });

  it('derives a future liability when only a future taxable estate is supplied', () => {
    const wrapper = mountCard({ futureTaxableEstate: 250000, futureIHTLiability: null });
    expect(wrapper.vm.formattedFutureIHTLiability).toBe('£100,000');
  });

  it('labels married users as a joint-death calculation', () => {
    expect(mountCard({ isMarried: true }).text()).toContain('Joint Death');
  });

  it('renders the supplied future-death age', () => {
    expect(mountCard({ futureDeathAge: 85 }).text()).toContain('Death at Age 85');
  });

  it('navigates to estate planning when selected', async () => {
    const wrapper = mountCard();
    await wrapper.trigger('click');
    expect(router.push).toHaveBeenCalledWith('/estate');
  });

  it('recommends planning when a liability exists', () => {
    expect(mountCard({ ihtLiability: 50000 }).text()).toContain('Inheritance Tax planning recommended');
  });

  it('confirms when no liability is forecast', () => {
    expect(mountCard({ ihtLiability: 0 }).text()).toContain('No Inheritance Tax liability forecast');
  });
});
