import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PropertyForm from '@/components/NetWorth/Property/PropertyForm.vue';

const mountForm = () => {
  const store = createStore({
    modules: {
      aiFormFill: {
        namespaced: true,
        state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
      },
      userProfile: {
        namespaced: true,
        getters: { spouse: () => null },
      },
    },
  });

  return mount(PropertyForm, {
    global: {
      plugins: [store],
      stubs: { CountrySelector: true },
    },
  });
};

describe('PropertyForm mortgage liability', () => {
  it('keeps a new mortgage individual when the property is tenants in common', async () => {
    const wrapper = mountForm();

    wrapper.vm.form.ownership_type = 'tenants_in_common';
    wrapper.vm.form.ownership_percentage = 30;
    wrapper.vm.hasMortgage = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.mortgageForm.ownership_type).toBe('individual');
    expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(100);
    expect(wrapper.vm.mortgageForm.joint_owner_id).toBeNull();
  });

  it('sets a separate 50/50 split when joint borrowers are selected', async () => {
    const wrapper = mountForm();
    wrapper.vm.form.ownership_type = 'tenants_in_common';
    wrapper.vm.form.ownership_percentage = 30;
    wrapper.vm.hasMortgage = true;
    wrapper.vm.mortgageForm.ownership_type = 'joint';
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(50);
  });
});
