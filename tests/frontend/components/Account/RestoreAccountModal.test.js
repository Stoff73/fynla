import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import RestoreAccountModal from '@/components/Account/RestoreAccountModal.vue';
import authService from '@/services/authService';

vi.mock('@/services/authService', () => ({
  default: {
    restoreCheck: vi.fn(),
    restoreCheckHandoff: vi.fn(),
    restore: vi.fn(),
  },
}));

describe('RestoreAccountModal', () => {
  let wrapper;

  beforeEach(() => {
    vi.clearAllMocks();
    authService.restore.mockResolvedValue({ restored: true });
  });

  afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    document.body.innerHTML = '';
  });

  it('clears password and restoration token state before emitting success', async () => {
    authService.restoreCheck.mockResolvedValue({
      restoration_token: 'one-use-token',
      requires_mfa: false,
    });
    wrapper = mount(RestoreAccountModal, {
      props: {
        visible: true,
        email: 'returning@example.com',
        requiresPasswordVerification: true,
      },
    });
    wrapper.vm.passwordInput = 'Secret1!';

    await wrapper.vm.onRestore();

    expect(authService.restore).toHaveBeenCalledWith('one-use-token', null);
    expect(wrapper.vm.passwordInput).toBe('');
    expect(wrapper.vm.mfaCode).toBe('');
    expect(wrapper.vm.pendingRestorationToken).toBe('');
    expect(wrapper.emitted('restored')).toHaveLength(1);
  });

  it('clears multifactor and restoration token state before emitting success', async () => {
    wrapper = mount(RestoreAccountModal, {
      props: {
        visible: true,
        requiresPasswordVerification: true,
      },
    });
    wrapper.vm.mfaRequired = true;
    wrapper.vm.mfaCode = 'recovery-code';
    wrapper.vm.pendingRestorationToken = 'one-use-token';

    await wrapper.vm.onRestore();

    expect(authService.restore).toHaveBeenCalledWith('one-use-token', 'recovery-code');
    expect(wrapper.vm.mfaCode).toBe('');
    expect(wrapper.vm.pendingRestorationToken).toBe('');
    expect(wrapper.vm.mfaRequired).toBe(false);
    expect(wrapper.emitted('restored')).toHaveLength(1);
  });

  it('exposes a labelled modal, focuses the required field, and restores focus on close', async () => {
    const opener = document.createElement('button');
    document.body.appendChild(opener);
    opener.focus();

    wrapper = mount(RestoreAccountModal, {
      attachTo: document.body,
      props: {
        visible: true,
        requiresPasswordVerification: true,
      },
    });
    await nextTick();

    expect(wrapper.get('[role="dialog"]').attributes()).toMatchObject({
      'aria-modal': 'true',
      'aria-labelledby': 'restore-account-title',
    });
    expect(document.activeElement).toBe(wrapper.get('#restore-account-password').element);

    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
    expect(wrapper.emitted('cancel')).toHaveLength(1);
    await wrapper.setProps({ visible: false });
    await nextTick();
    expect(document.activeElement).toBe(opener);
  });

  it('keeps keyboard focus inside the dialog', async () => {
    wrapper = mount(RestoreAccountModal, {
      attachTo: document.body,
      props: {
        visible: true,
        restorationToken: 'ready-token',
      },
    });
    await nextTick();

    const cancel = wrapper.vm.$refs.cancelButton;
    const restore = wrapper.vm.$refs.restoreButton;
    restore.focus();
    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Tab' });
    expect(document.activeElement).toBe(cancel);

    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(restore);
  });
});
