import { describe, it, expect, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import SaveAccountModal from '@/components/Savings/SaveAccountModal.vue';

/**
 * T71 (T65 walk, 2026-10-13): the Junior ISA panel said "All ISA contributions
 * (Cash ISA + Stocks & Shares ISA) count towards your £9,000 annual allowance".
 * The £9,000 is the CHILD's Junior ISA limit, across the child's cash and
 * stocks and shares Junior ISAs (GOV.UK, Junior ISAs: "the total amount paid
 * in cannot go over £9,000 in the 2026 to 2027 tax year"), and every figure
 * comes from the tax configuration (Rule 2). The 16-17 note offered adult
 * ISAs that need the holder to be 18 (GOV.UK: "You must be 18 or over to open
 * an ISA"), so it is gone.
 */
function mountModal(accountType, children = []) {
  const store = createStore({
    modules: {
      taxConfig: {
        namespaced: true,
        // Deliberately not the statutory figures, to prove they are read.
        getters: { isaAnnualAllowance: () => 21000, juniorIsaAllowance: () => 9100, lifetimeIsaAllowance: () => 4000, premiumBondsMaxHolding: () => 40000 },
      },
      userProfile: {
        namespaced: true,
        getters: { spouse: () => null, juniorIsaEligibleChildren: () => children },
        actions: { fetchFamilyMembers: vi.fn() },
      },
      lifeStage: { namespaced: true, getters: { formFields: () => () => ({}) } },
      aiFormFill: { namespaced: true, actions: { beginFieldSequence: vi.fn(), cancelFill: vi.fn() } },
    },
  });
  return mount(SaveAccountModal, {
    props: { defaultAccountType: accountType },
    global: { plugins: [store], stubs: { CountrySelector: true } },
  });
}

describe('SaveAccountModal — the ISA allowance line', () => {
  it('names the child\'s Junior ISA allowance from the configuration', async () => {
    const wrapper = mountModal('junior_isa', [{ id: 9, first_name: 'Maya', last_name: 'Walk', relationship: 'child', date_of_birth: '2009-01-01' }]);
    await flushPromises();
    const text = wrapper.text();

    expect(text).toContain("All payments into the child's Junior ISAs (cash and stocks and shares) count towards the child's £9,100 Junior ISA allowance");
    expect(text).not.toContain('your £9,000');
    expect(text).not.toContain('At 16-17');
  });

  it('names the adult ISA allowance from the configuration', async () => {
    const wrapper = mountModal('cash_isa');
    await flushPromises();

    expect(wrapper.text()).toContain('count towards your £21,000 annual allowance');
  });
});

// T79: the Premium Bonds limit was a typed-in £50,000 (Rule 2).
describe('SaveAccountModal — the Premium Bonds limit', () => {
  it('refuses a holding over the configured maximum, naming it', async () => {
    const wrapper = mountModal('premium_bonds');
    await flushPromises();
    wrapper.vm.formData.institution = 'NS&I';
    wrapper.vm.formData.current_balance = 45000;
    await wrapper.vm.handleSubmit();

    expect(wrapper.vm.isaAllowanceError).toBe('Premium Bonds have a maximum holding of £40,000 per person. You have entered £45,000.');
    expect(wrapper.emitted('save')).toBeUndefined();
  });
});

// T80 (T71 walk, Tunde 524): the rate started at 0, so an account saved with
// no rate given was stored as 0% (the placeholder-as-fact class, T25, R10).
describe('SaveAccountModal — a rate never given', () => {
  it('starts empty and saves no rate when none is typed', async () => {
    const wrapper = mountModal('savings_account');
    await flushPromises();
    expect(wrapper.vm.formData.interest_rate).toBeNull();

    wrapper.vm.formData.institution = 'Marcus';
    wrapper.vm.formData.current_balance = 6500;
    expect(wrapper.vm.prepareAccountData().interest_rate).toBeNull();
  });

  it('saves no rate for Premium Bonds, whose rate is never asked', async () => {
    const wrapper = mountModal('premium_bonds');
    await flushPromises();
    wrapper.vm.formData.interest_rate = 0;
    expect(wrapper.vm.prepareAccountData().interest_rate).toBeNull();
  });

  it('keeps a rate the user typed', async () => {
    const wrapper = mountModal('savings_account');
    await flushPromises();
    wrapper.vm.formData.interest_rate = 4.3;
    expect(wrapper.vm.prepareAccountData().interest_rate).toBe(4.3);
  });
});
