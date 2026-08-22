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

describe('PropertyForm states a share only where it lets one be set (W-0040)', () => {
  const emittedProperty = (wrapper) => wrapper.emitted('save')?.at(-1)?.[0]?.property;

  it('omits the share on a joint property, where the form shows no share input', async () => {
    const wrapper = mountForm();

    wrapper.vm.form.property_type = 'main_residence';
    wrapper.vm.form.address_line_1 = '1 Test Street';
    wrapper.vm.form.city = 'Manchester';
    wrapper.vm.form.postcode = 'M1 1AA';
    wrapper.vm.form.current_value = 400000;
    wrapper.vm.form.ownership_type = 'joint';
    wrapper.vm.form.joint_owner_name = 'Alex Smith';
    await wrapper.vm.$nextTick();

    wrapper.vm.currentStep = wrapper.vm.totalSteps;
    await wrapper.vm.handleSubmit();

    // The 100 sitting in form data is an uncleared individual default, not a
    // figure anyone chose. Sending it is what made a stated share and an
    // inherited one indistinguishable server-side.
    expect(emittedProperty(wrapper)).toBeTruthy();
    expect(emittedProperty(wrapper)).not.toHaveProperty('ownership_percentage');
  });

  it('states the share on a tenants-in-common property, where the input exists', async () => {
    const wrapper = mountForm();

    wrapper.vm.form.property_type = 'main_residence';
    wrapper.vm.form.address_line_1 = '1 Test Street';
    wrapper.vm.form.city = 'Manchester';
    wrapper.vm.form.postcode = 'M1 1AA';
    wrapper.vm.form.current_value = 400000;
    wrapper.vm.form.ownership_type = 'tenants_in_common';
    await wrapper.vm.$nextTick();
    wrapper.vm.form.ownership_percentage = 60;
    wrapper.vm.form.joint_owner_name = 'Mike Barrett';

    wrapper.vm.currentStep = wrapper.vm.totalSteps;
    await wrapper.vm.handleSubmit();

    expect(emittedProperty(wrapper)?.ownership_percentage).toBe(60);
  });
});
