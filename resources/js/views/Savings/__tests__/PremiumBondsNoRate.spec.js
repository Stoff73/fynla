import { describe, it, expect, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { paysInterest } from '@/utils/interestRate';
import SavingsAccountDetailInline from '@/views/Savings/SavingsAccountDetailInline.vue';

/**
 * T68 (release ac prod walk, 2026-10-13): Premium Bonds read "Not recorded"
 * for an interest rate on the web Bank Accounts page. They pay prizes from a
 * prize fund, not interest (NS&I, Premium Bonds), and the savings form never
 * asks for their rate, so no rate or interest line is shown for them.
 */
function mountWith(account) {
  const store = createStore({
    modules: {
      savings: {
        namespaced: true,
        actions: {
          fetchAccount: vi.fn(() => account),
          updateAccount: vi.fn(),
          deleteAccount: vi.fn(),
        },
      },
    },
  });
  return mount(SavingsAccountDetailInline, {
    props: { accountId: account.id },
    global: {
      plugins: [store],
      stubs: { SaveAccountModal: true, ConfirmDialog: true, TaxStatusPanel: true },
    },
  });
}

describe('Premium Bonds carry no interest rate (T68)', () => {
  it('is the one rule: Premium Bonds pay no interest, other accounts do', () => {
    expect(paysInterest({ account_type: 'premium_bonds' })).toBe(false);
    expect(paysInterest({ type: 'premium_bonds' })).toBe(false);
    expect(paysInterest({ account_type: 'savings_account' })).toBe(true);
    expect(paysInterest({ account_type: 'nsi' })).toBe(true);
  });

  it('shows no Interest Rate or Annual Interest line for Premium Bonds', async () => {
    const wrapper = mountWith({ id: 7, institution: 'NS&I', account_type: 'premium_bonds', current_balance: 10000, interest_rate: null, ownership_type: 'individual' });
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain('NS&I');
    expect(text).not.toContain('Interest Rate');
    expect(text).not.toContain('Annual Interest');
  });

  it('keeps "Not recorded" for a savings account with no rate given', async () => {
    const wrapper = mountWith({ id: 8, institution: 'Marcus', account_type: 'savings_account', current_balance: 6500, interest_rate: null, ownership_type: 'individual' });
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain('Interest Rate');
    expect(text).toContain('Not recorded');
  });
});
