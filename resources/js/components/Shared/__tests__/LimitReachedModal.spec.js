import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LimitReachedModal from '../LimitReachedModal.vue';

/**
 * The plan-cap wording has one home: the modal derives the entity label, the
 * cap and the tier from an entity_key, so no page carries its own literal.
 */
const mountWith = (props, subscriptionData = { tier: 'free', count_caps: { savings_account: 2 } }) =>
  mount(LimitReachedModal, {
    props: { show: true, ...props },
    global: {
      mocks: { $store: { state: { auth: { subscriptionData } } } },
      stubs: { 'router-link': { template: '<a><slot /></a>' } },
    },
  });

describe('LimitReachedModal', () => {
  it('reads label, cap and tier from the entity key', () => {
    const text = mountWith({ entityKey: 'savings_account' }).text();
    expect(text).toContain('Your Free plan includes up to 2 savings accounts.');
  });

  it('prefers a server-reported cap and falls back to Free with no payload', () => {
    const text = mountWith({ entityKey: 'property', cap: 3 }, null).text();
    expect(text).toContain('Your Free plan includes up to 3 properties.');
  });

  it('never renders an empty entity', () => {
    expect(mountWith({ entityKey: 'widget_thing' }).text()).toContain('widget thing');
    expect(mountWith({}).text()).toContain('items');
  });
});
