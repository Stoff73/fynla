import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import TrustsOverviewCard from '@/components/Trusts/TrustsOverviewCard.vue';

/**
 * W-0538. Wiring this card into the dashboard made a pre-existing flaw visible:
 * `fetchTrusts` catches its error and leaves `trusts` empty, and the template
 * reads an empty list as "No trusts set up". Measured on csjones — an admin whose
 * tier lacks the estate capability took a 403 on /api/estate/trusts and was told
 * they had no trusts while holding one.
 *
 * "We could not load this" and "you have none" are different sentences, and only
 * one of them is a claim about the user's money.
 */
const mountCard = (fetchTrusts) => mount(TrustsOverviewCard, {
  global: {
    plugins: [createStore({
      modules: {
        trusts: { namespaced: true, state: () => ({ trusts: [] }), actions: { fetchTrusts } },
      },
    })],
    mocks: { $router: { push: vi.fn() } },
  },
});

const settle = async (wrapper) => {
  await new Promise((resolve) => setTimeout(resolve, 0));
  await wrapper.vm.$nextTick();
};

describe('TrustsOverviewCard when the trusts cannot be loaded', () => {
  it('does not claim the user has no trusts', async () => {
    const wrapper = mountCard(() => Promise.reject(new Error('Request failed with status code 403')));
    await settle(wrapper);

    expect(wrapper.text()).not.toContain('No trusts set up');
  });

  it('says instead that it could not load them', async () => {
    const wrapper = mountCard(() => Promise.reject(new Error('Request failed with status code 403')));
    await settle(wrapper);

    expect(wrapper.text()).toContain("We couldn't load your trusts");
  });

  it('still says "no trusts" when the load succeeded and there genuinely are none', async () => {
    const wrapper = mountCard(() => Promise.resolve());
    await settle(wrapper);

    expect(wrapper.text()).toContain('No trusts set up');
  });
});
