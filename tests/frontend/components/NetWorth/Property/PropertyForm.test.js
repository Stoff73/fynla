import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PropertyForm from '@/components/NetWorth/Property/PropertyForm.vue';

const mountForm = ({ property = null, spouse = null } = {}) => {
  const store = createStore({
    modules: {
      aiFormFill: {
        namespaced: true,
        state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
      },
      userProfile: {
        namespaced: true,
        getters: { spouse: () => spouse },
      },
    },
  });

  return mount(PropertyForm, {
    props: { property },
    global: {
      plugins: [store],
      stubs: { CountrySelector: true },
    },
  });
};

// CSJ ruling, 2026-08-22 (W-0228): a debt is shared exactly as the asset securing
// it is shared. A mortgage's ownership derives from its property; the form no longer
// collects a separate borrower split, and the old "Borrower(s)" select and its 50%
// hardcode are gone (W-0236).
//
// The two tests below previously asserted the OPPOSITE — that a new mortgage stayed
// individual/100 and that choosing joint borrowers produced a separate 50/50 split.
// They were correct before the ruling and are rewritten to it, not deleted, so the
// reversal stays legible. Do not restore the old expectations.
//
// Every case uses an asymmetric 30% deliberately: at 50/50 the mirrored share and a
// hardcoded 50 are the same number, so the test could not tell them apart
// (tests/CLAUDE.md §4, Collision variant).
describe('PropertyForm mortgage liability follows the property (W-0228)', () => {
  it('mirrors a tenants-in-common property onto its new mortgage', async () => {
    const wrapper = mountForm();

    wrapper.vm.form.ownership_type = 'tenants_in_common';
    wrapper.vm.form.ownership_percentage = 30;
    wrapper.vm.hasMortgage = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.mortgageForm.ownership_type).toBe('tenants_in_common');
    expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(30);
  });

  it('does not let a mortgage carry a share its property does not', async () => {
    const wrapper = mountForm();
    wrapper.vm.form.ownership_type = 'tenants_in_common';
    wrapper.vm.form.ownership_percentage = 30;
    wrapper.vm.hasMortgage = true;
    wrapper.vm.mortgageForm.ownership_type = 'joint';
    await wrapper.vm.$nextTick();

    // Not 50: there is no separate borrower split any more.
    expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(30);
  });

  it('moves the mortgage share when the property share moves', async () => {
    const wrapper = mountForm();

    wrapper.vm.form.ownership_type = 'tenants_in_common';
    wrapper.vm.form.ownership_percentage = 30;
    wrapper.vm.hasMortgage = true;
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(30);

    wrapper.vm.form.ownership_percentage = 70;
    await wrapper.vm.$nextTick();

    // The assertion that fails if the mirroring stops working: the answer must MOVE
    // when the real input moves, rather than equal a value the test supplied.
    expect(wrapper.vm.mortgageForm.ownership_percentage).toBe(70);
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

// W-0368 C2 route (a). `joint_owner_is_spouse` decides an Inheritance Tax
// valuation (IHTA 1984 s160/s161). It was read back in `populateForm()`'s
// selection logic but never copied onto the form, so the read could not be
// satisfied and — because `handleSubmit()` spreads the whole form — every edit
// wrote the untouched `null` default over the stored answer.
//
// `??` and never `||`: a stored `false` is the answer that turns the discount ON,
// and `||` would map it to `null` and silently disable the feature.
describe('PropertyForm carries the co-owner spouse answer round trip (W-0368)', () => {
  const baseProperty = {
    property_type: 'main_residence',
    ownership_type: 'tenants_in_common',
    ownership_percentage: 50,
    current_value: 360000,
    address_line_1: '1 Test Street',
    city: 'Manchester',
    postcode: 'M1 1AA',
  };

  it('reads a stored "not my spouse" back onto the form', () => {
    const wrapper = mountForm({
      property: { ...baseProperty, joint_owner_name: 'Ruth Alderton', joint_owner_is_spouse: false },
    });

    expect(wrapper.vm.form.joint_owner_is_spouse).toBe(false);
    expect(wrapper.vm.jointOwnerSelection).toBe('other');
  });

  it('reconstructs a spouse recorded by name as the spouse option, not "Other"', () => {
    const wrapper = mountForm({
      property: { ...baseProperty, joint_owner_name: 'Jane Isley', joint_owner_is_spouse: true },
      spouse: { id: null, name: 'Jane Isley' },
    });

    // Landing on "Other" here is the unsafe branch: touching the select would then
    // write `false` and discount a share held with a spouse.
    expect(wrapper.vm.form.joint_owner_is_spouse).toBe(true);
    expect(wrapper.vm.jointOwnerSelection).toBe('spouse_name');
  });

  it('keeps a never-asked property unanswered rather than answering it "no"', () => {
    const wrapper = mountForm({
      property: { ...baseProperty, joint_owner_name: 'Ruth Alderton', joint_owner_is_spouse: null },
    });

    expect(wrapper.vm.form.joint_owner_is_spouse).toBeNull();
  });

  it('submits the stored answer rather than wiping it', async () => {
    const wrapper = mountForm({
      property: { ...baseProperty, id: 7, joint_owner_name: 'Ruth Alderton', joint_owner_is_spouse: false },
    });

    wrapper.vm.currentStep = wrapper.vm.totalSteps;
    await wrapper.vm.handleSubmit();

    expect(wrapper.emitted('save')?.at(-1)?.[0]?.property?.joint_owner_is_spouse).toBe(false);
  });
});
