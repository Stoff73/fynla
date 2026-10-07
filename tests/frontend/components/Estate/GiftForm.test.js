import { describe, it, expect, vi } from 'vitest';

// The real tax config module, so the form reads its real getter; its API
// client (which pulls in the app store) is not needed here.
vi.mock('@/services/api', () => ({ default: { get: vi.fn() } }));
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import taxConfig from '@/store/modules/taxConfig';
import GiftForm from '@/components/Estate/GiftForm.vue';

// The config exactly as `/api/tax/config` sends it (TaxConfigSnapshotService):
// the small gifts limit travels as `small_gift_exemption`.
const mountForm = () => {
  const store = createStore({
    modules: {
      aiFormFill: { namespaced: true, state: () => ({ pendingFill: null, highlightedField: null, filling: false }) },
      taxConfig: {
        ...taxConfig,
        state: () => ({ ...taxConfig.state, config: { gifting_exemptions: { annual_exemption: 3000, small_gift_exemption: 250 } } }),
      },
    },
  });

  return mount(GiftForm, { global: { plugins: [store] } });
};

describe('GiftForm', () => {
  it('names the small gifts limit from the tax config the server sends', () => {
    // It read a key the server never sends and said "£0 limit" (2026-10-07).
    const wrapper = mountForm();

    expect(wrapper.find('option[value="small_gift"]').text()).toBe('Small Gift Exemption (£250 limit)');
  });

  it('recognises a small gift up to the limit', async () => {
    const wrapper = mountForm();
    await wrapper.find('#gift_value').setValue(200);
    await wrapper.find('#gift_type').setValue('small_gift');

    expect(wrapper.text()).toContain('This gift qualifies for the Small Gift Exemption (£250 or less per person per year)');
  });

  it('never calls a gift into a trust a Potentially Exempt Transfer', async () => {
    // A gift into most trusts is a Chargeable Lifetime Transfer (IHTA 1984 s3A(1A)).
    const wrapper = mountForm();
    await wrapper.find('#gift_value').setValue(10000);
    await wrapper.find('#gift_type').setValue('clt');

    expect(wrapper.text()).not.toContain('will be a Potentially Exempt Transfer');
    expect(wrapper.find('.exemption-info').exists()).toBe(false);
  });
});
