import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount as baseMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AccountForm from '@/components/Investment/AccountForm.vue';

describe('AccountForm', () => {
  let wrapper;
  let store;

  const mount = (component, options = {}) => baseMount(component, {
    ...options,
    global: {
      ...options.global,
      plugins: [...(options.global?.plugins || []), store],
      stubs: {
        ...options.global?.stubs,
        RouterLink: { template: '<a><slot /></a>' },
      },
    },
  });

  const mockAccount = {
    id: 1,
    account_type: 'isa',
    provider: 'Vanguard',
    platform: 'Investment Account',
    platform_fee_percent: 0.15,
    isa_type: 'stocks_and_shares',
    isa_subscription_current_year: 5000,
    notes: 'Test notes',
  };

  beforeEach(() => {
    vi.clearAllMocks();
    store = createStore({
      modules: {
        aiFormFill: {
          namespaced: true,
          state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
          actions: { beginFieldSequence: () => {}, cancelFill: () => {} },
        },
        aiChat: {
          namespaced: true,
          mutations: { ADD_MESSAGE: () => {} },
        },
        taxConfig: {
          namespaced: true,
          getters: { isaAnnualAllowance: () => 20000 },
        },
        userProfile: {
          namespaced: true,
          getters: { spouse: () => null },
        },
        savings: {
          namespaced: true,
          getters: { currentYearISASubscription: () => 0 },
        },
        investment: {
          namespaced: true,
          getters: { investmentISASubscription: () => 0 },
          actions: { updateHolding: () => {}, fetchInvestmentData: () => {} },
        },
      },
    });
  });

  it('renders in create mode when no account prop', () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Add New Investment Account');
  });

  it('renders in edit mode when account prop is provided', () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    expect(wrapper.text()).toContain('Edit Investment Account');
  });

  it('does not render when show prop is false', () => {
    wrapper = mount(AccountForm, {
      props: {
        show: false,
        account: null,
      },
    });

    // Modal should not be visible
    const modal = wrapper.find('.fixed.inset-0');
    expect(modal.exists()).toBe(false);
  });

  it('displays core fields and reveals optional platform details on request', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    expect(wrapper.find('#account_type').exists()).toBe(true);
    expect(wrapper.find('#provider').exists()).toBe(true);
    expect(wrapper.find('#current_value').exists()).toBe(true);

    await wrapper.findAll('button').find(button => button.text().includes('Show additional information')).trigger('click');

    expect(wrapper.find('#platform').exists()).toBe(true);
    expect(wrapper.find('#platform_fee_value').exists()).toBe(true);
  });

  it('populates form with account data in edit mode', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.find('#account_type').element.value).toBe('isa');
    expect(wrapper.find('#provider').element.value).toBe('Vanguard');
    expect(wrapper.find('#platform').element.value).toBe('Investment Account');
  });

  it('shows ISA-specific fields when account type is ISA', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    const accountTypeSelect = wrapper.find('#account_type');
    await accountTypeSelect.setValue('isa');

    expect(wrapper.find('#isa_subscription_current_year').exists()).toBe(true);
    expect(wrapper.text()).toContain('ISA Subscription');
  });

  it('hides ISA-specific fields when account type is not ISA', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    const accountTypeSelect = wrapper.find('#account_type');
    await accountTypeSelect.setValue('gia');

    expect(wrapper.find('#isa_type').exists()).toBe(false);
    expect(wrapper.find('#isa_subscription_current_year').exists()).toBe(false);
  });

  it('calculates remaining ISA allowance correctly', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    await wrapper.vm.$nextTick();

    // £20,000 - £5,000 = £15,000
    expect(wrapper.vm.remainingAllowance).toBe(15000);
  });

  it('calculates ISA allowance used percentage correctly', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    await wrapper.vm.$nextTick();

    // (5000 / 20000) * 100 = 25%
    expect(wrapper.vm.allowanceUsedPercent).toBe(25);
  });

  it('displays remaining allowance with the spring success colour', async () => {
    const accountLowUsage = {
      ...mockAccount,
      isa_subscription_current_year: 5000, // 25% used
    };

    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: accountLowUsage,
      },
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.vm.remainingAllowanceClass).toBe('text-spring-600');
    expect(wrapper.vm.allowanceBarClass).toBe('bg-spring-600');
  });

  it('displays a low remaining allowance with the violet caution colour', async () => {
    const accountHighUsage = {
      ...mockAccount,
      isa_subscription_current_year: 18500, // 92.5% used, remaining = £1,500 which is < 2000
    };

    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: accountHighUsage,
      },
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.vm.remainingAllowanceClass).toBe('text-violet-600');
    expect(wrapper.vm.allowanceBarClass).toBe('bg-violet-500');
  });

  it('displays an exhausted allowance with the raspberry danger colour', async () => {
    const accountFullUsage = {
      ...mockAccount,
      isa_subscription_current_year: 20000, // 100% used
    };

    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: accountFullUsage,
      },
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.vm.remainingAllowanceClass).toBe('text-raspberry-600');
    expect(wrapper.vm.allowanceBarClass).toBe('bg-raspberry-600');
  });

  it('calculates current tax year correctly - before April', () => {
    // Mock date in March (month 2)
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2025, 2, 15)); // March 15, 2025

    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    expect(wrapper.vm.currentTaxYear).toBe('2024/2025');

    vi.useRealTimers();
  });

  it('calculates current tax year correctly - after April', () => {
    // Mock date in May (month 4)
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2025, 4, 15)); // May 15, 2025

    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    expect(wrapper.vm.currentTaxYear).toBe('2025/2026');

    vi.useRealTimers();
  });

  it('validates required fields', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    const form = wrapper.find('form');
    await form.trigger('submit.prevent');

    expect(wrapper.vm.errors.account_type).toBeTruthy();
    expect(wrapper.vm.errors.provider).toBeTruthy();
  });

  it('rejects a negative platform fee at the form boundary', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    wrapper.vm.formData.platform_fee_percent = -0.1;
    const isValid = wrapper.vm.validateForm();

    expect(isValid).toBe(false);
    expect(wrapper.vm.errors.platform_fee_value).toBe('Platform fee cannot be negative');
  });

  it('validates ISA subscription does not exceed allowance', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: { ...mockAccount, isa_subscription_current_year: 25000 }, // Exceeds £20,000
      },
    });

    await wrapper.vm.$nextTick();

    const isValid = wrapper.vm.validateForm();

    expect(isValid).toBe(false);
    expect(wrapper.vm.errors.isa_contribution_exceeds).toContain('£20,000');
  });

  it('emits save with form data on valid submission', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    wrapper.vm.formData = {
      account_type: 'gia',
      provider: 'Test Provider',
      platform: 'Test Platform',
      current_value: 10000,
      platform_fee_percent: 0.5,
      platform_fee_type: 'percentage',
      notes: 'Test notes',
    };

    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('save')).toBeTruthy();
    expect(wrapper.emitted('save')[0][0].provider).toBe('Test Provider');
  });

  it('emits close event when cancel button is clicked', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    const cancelButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Cancel')
    );
    await cancelButton.trigger('click');

    expect(wrapper.emitted('close')).toBeTruthy();
  });

  it('emits close event when close icon is clicked', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    const closeButton = wrapper.find('button svg').element.closest('button');
    await closeButton.click();

    expect(wrapper.emitted('close')).toBeTruthy();
  });

  it('resets form when closed', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    await wrapper.vm.$nextTick();
    expect(wrapper.vm.formData.provider).toBe('Vanguard');

    wrapper.vm.closeModal();

    expect(wrapper.vm.formData.provider).toBe('');
  });

  it('shows submit button text based on mode', async () => {
    // Create mode
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    expect(wrapper.text()).toContain('Add Account');

    // Edit mode
    await wrapper.setProps({ account: mockAccount });
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Update Account');
  });

  it('disables submit button while submitting', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    wrapper.vm.submitting = true;
    await wrapper.vm.$nextTick();

    const submitButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Saving')
    );

    expect(submitButton.attributes('disabled')).toBeDefined();
  });

  it('removes ISA fields from submit data when account type is not ISA', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: null,
      },
    });

    wrapper.vm.formData = {
      account_type: 'gia',
      provider: 'Test',
      platform: 'Test',
      current_value: 10000,
      isa_type: 'stocks_and_shares',
      isa_subscription_current_year: 5000,
    };

    await wrapper.find('form').trigger('submit.prevent');

    const emittedData = wrapper.emitted('save')[0][0];
    expect(emittedData.isa_type).toBeUndefined();
    expect(emittedData.isa_subscription_current_year).toBeUndefined();
  });

  it('displays ISA allowance progress bar', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    await wrapper.vm.$nextTick();

    const html = wrapper.html();
    expect(html).toContain('ISA Allowance Usage');
    expect(html).toMatch(/bg-savannah-200.*rounded-full.*h-3/);
  });

  it('displays ISA warning information', async () => {
    wrapper = mount(AccountForm, {
      props: {
        show: true,
        account: mockAccount,
      },
    });

    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('All ISA contributions');
    expect(wrapper.text()).toContain('count towards your £20,000 annual allowance');
  });
});
