import { describe, it, expect, beforeEach } from 'vitest';
import { mount as baseMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AccountForm from '@/components/Investment/AccountForm.vue';

/**
 * W-0257 and W-0322 — what the account form actually POSTs about holdings.
 *
 * Both defects live in the gap between what the form shows and what it sends, so
 * they are asserted on the emitted `save` payload rather than on screen state.
 *
 * W-0322 is the destructive one. `InvestmentController::update` reads
 * `$holdings = $validated['holdings'] ?? null` and syncs `if ($holdings !== null)`
 * — and `[] !== null`. So a payload carrying an empty holdings array ran
 * `$account->holdings()->delete()`, wrote nothing back, and auto-created a single
 * 100% "Cash" holding for the remainder. Collapsing "Additional information" and
 * pressing Update therefore replaced every holding with Cash, silently.
 *
 * TEST DESIGN — the collision trap (`tests/CLAUDE.md` §4) is sharp here. Asserting
 * "holdings are not sent when collapsed" would pass against BOTH the fixed code
 * (key absent) and a hypothetical version that omits holdings always. And
 * asserting `toEqual([])` passes against the broken code AND against a correct
 * one, because `undefined` and `[]` are both falsy in the places this value gets
 * read. The two hypotheses only diverge on **key presence**, so that is what is
 * asserted — with `'holdings' in payload`, not on the value.
 */
describe('AccountForm — the holdings key in the save payload', () => {
  let store;

  const mount = (options = {}) => baseMount(AccountForm, {
    ...options,
    global: {
      ...options.global,
      plugins: [...(options.global?.plugins || []), store],
      stubs: { ...options.global?.stubs, RouterLink: { template: '<a><slot /></a>' } },
    },
  });

  const accountWithHoldings = {
    id: 26,
    account_type: 'isa',
    provider: 'Vanguard',
    current_value: 95000,
    isa_type: 'stocks_and_shares',
    holdings: [
      { id: 65, security_name: 'Fundsmith Equity', asset_type: 'fund', allocation_percent: 36.8, cost_basis: null },
      { id: 66, security_name: 'Scottish Mortgage Investment Trust', asset_type: 'fund', allocation_percent: 26.3, cost_basis: null },
      { id: 67, security_name: 'Vanguard FTSE All-World', asset_type: 'etf', allocation_percent: 36.9, cost_basis: null },
    ],
  };

  beforeEach(() => {
    store = createStore({
      modules: {
        aiFormFill: {
          namespaced: true,
          state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
          actions: { beginFieldSequence: () => {}, cancelFill: () => {} },
        },
        aiChat: { namespaced: true, mutations: { ADD_MESSAGE: () => {} } },
        taxConfig: { namespaced: true, getters: { isaAnnualAllowance: () => 20000 } },
        userProfile: { namespaced: true, getters: { spouse: () => null } },
        savings: { namespaced: true, getters: { currentYearISASubscription: () => 0 } },
        investment: {
          namespaced: true,
          getters: { investmentISASubscription: () => 0 },
          actions: { updateHolding: () => {}, fetchInvestmentData: () => {} },
        },
      },
    });
  });

  const savedPayload = (wrapper) => {
    const events = wrapper.emitted('save');
    return events ? events[events.length - 1][0] : null;
  };

  it('omits the holdings key entirely when the holdings section is collapsed', async () => {
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = false;
    await wrapper.vm.$nextTick();

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    const payload = savedPayload(wrapper);

    expect(payload).not.toBeNull();

    // The load-bearing assertion. `[]` is what destroyed the holdings; the key
    // being ABSENT is what makes the controller skip the sync altogether.
    expect('holdings' in payload).toBe(false);
  });

  it('still sends holdings when the section is open, so a user can genuinely clear them', async () => {
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = true;
    await wrapper.vm.$nextTick();

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    const payload = savedPayload(wrapper);

    expect(payload).not.toBeNull();
    expect('holdings' in payload).toBe(true);
    expect(payload.holdings).toHaveLength(3);
  });

  it('sends a real empty array when the user deletes every row from the open section', async () => {
    // The distinction the fix rests on: "not showing holdings" is not the same
    // statement as "the user removed them all", and only the second should clear.
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = true;
    wrapper.vm.formData.holdings = [];
    await wrapper.vm.$nextTick();

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    const payload = savedPayload(wrapper);

    expect(payload).not.toBeNull();
    expect('holdings' in payload).toBe(true);
    expect(payload.holdings).toEqual([]);
  });

  it('refuses to submit at all while the open holdings exceed 100%, and says why (W-0257)', async () => {
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = true;
    wrapper.vm.formData.holdings = [
      ...accountWithHoldings.holdings,
      { security_name: 'Baillie Gifford Managed', asset_type: 'fund', allocation_percent: 5 },
    ];
    await wrapper.vm.$nextTick();

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    // No save, and — unlike the defect — a stated reason the user can act on.
    expect(wrapper.emitted('save')).toBeFalsy();
    expect(wrapper.vm.errors.holdings).toContain('105%');

    const blocked = wrapper.find('[data-testid="account-form-blocked"]');
    expect(blocked.exists()).toBe(true);
    expect(blocked.text()).toContain('Reduce them by 5%');
  });

  it('does not block on an over-allocated set that is about to be discarded', async () => {
    // A collapsed section sends no holdings, so refusing to save over them would
    // be a second dead button with no way to reach the control that fixes it.
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = false;
    wrapper.vm.formData.holdings = [
      ...accountWithHoldings.holdings,
      { security_name: 'Baillie Gifford Managed', asset_type: 'fund', allocation_percent: 5 },
    ];
    await wrapper.vm.$nextTick();

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    expect(savedPayload(wrapper)).not.toBeNull();
    expect(wrapper.vm.errors.holdings).toBeUndefined();
  });

  it('stops naming the old total once the user has corrected it', async () => {
    // Found in the live browser, not here: after a blocked submit the field-level
    // message correctly vanished at 100%, while the message by the button still
    // read "103.1%". A stale instruction to fix something already fixed is only
    // marginally better than the silence it replaced.
    //
    // `errors.holdings` records that a submit was blocked; the TEXT comes from
    // the live computed, so the two cannot disagree about the total.
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = true;
    wrapper.vm.formData.holdings = [
      { security_name: 'Fundsmith Equity', asset_type: 'fund', allocation_percent: 36.8 },
      { security_name: 'Scottish Mortgage Investment Trust', asset_type: 'fund', allocation_percent: 26.3 },
      { security_name: 'Vanguard FTSE All-World', asset_type: 'etf', allocation_percent: 40 },
    ];
    await wrapper.vm.$nextTick();

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    expect(wrapper.find('[data-testid="account-form-blocked"]').text()).toContain('103.1%');

    // The user corrects it. 33.7 + 26.3 + 40 = 100.
    wrapper.vm.formData.holdings[0].allocation_percent = 33.7;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('[data-testid="account-form-blocked"]').exists()).toBe(false);
  });

  it('does not block when the section is open but the editor itself is hidden', async () => {
    // The subtler half of the same principle, and the one that is easy to miss.
    // `showHoldingsEditor` is false when the current value is zero or the account
    // type cannot hold holdings — and `formData.holdings` still carries whatever
    // the user entered before they zeroed the value or switched type.
    //
    // Guarding on `formData.holdings` alone would refuse the save and print a
    // message about rows that are NOWHERE ON SCREEN. That is a dead button with
    // an unexplained cause — the very defect this fix removes, reintroduced by
    // the fix itself.
    const wrapper = mount({ props: { show: true, account: accountWithHoldings } });

    wrapper.vm.showAdditionalInfo = true;
    wrapper.vm.formData.current_value = 0; // hides the editor
    wrapper.vm.formData.holdings = [
      ...accountWithHoldings.holdings,
      { security_name: 'Baillie Gifford Managed', asset_type: 'fund', allocation_percent: 5 },
    ];
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.showHoldingsEditor).toBe(false);
    expect(wrapper.find('[data-testid="holdings-over-allocated"]').exists()).toBe(false);

    wrapper.vm.submitForm();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.errors.holdings).toBeUndefined();
  });
});
