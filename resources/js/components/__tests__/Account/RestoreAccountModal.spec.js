import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('@/services/authService', () => ({
  default: { restoreCheck: vi.fn(), restoreCheckHandoff: vi.fn(), restore: vi.fn() },
}));
vi.mock('@/utils/logger', () => ({ default: { error: vi.fn(), warn: vi.fn(), info: vi.fn() } }));

import authService from '@/services/authService';
import RestoreAccountModal from '@/components/Account/RestoreAccountModal.vue';

/**
 * MB-20. On the sign-in path the modal already holds a restoration token, so
 * the check step that sets mfaRequired never runs. POST /auth/restore then
 * answers 422 requires_mfa, and the modal showed "Authenticator or recovery
 * code required." with no input — a two-factor user could never restore from
 * the sign-in page.
 */
describe('RestoreAccountModal — two-factor on the sign-in path (MB-20)', () => {
  const restoreButton = (wrapper) => wrapper.findAll('button').find((b) => /restore/i.test(b.text()) && !/cancel/i.test(b.text()));

  beforeEach(() => vi.clearAllMocks());

  it('asks for the code when restore answers requires_mfa, then restores with it', async () => {
    authService.restore
      .mockRejectedValueOnce({ response: { status: 422, data: { requires_mfa: true, message: 'Authenticator or recovery code required.' } } })
      .mockResolvedValueOnce({ token: 'tok', user: { id: 1 }, redirect_to: '/dashboard' });

    const wrapper = mount(RestoreAccountModal, {
      props: { visible: true, firstName: 'Ada', deletedAt: '2026-09-01T10:00:00Z', restorationToken: 'rt-1' },
      attachTo: document.body,
    });
    await flushPromises();

    expect(wrapper.find('#restore-account-mfa').exists()).toBe(false);
    await restoreButton(wrapper).trigger('click');
    await flushPromises();

    expect(authService.restore).toHaveBeenNthCalledWith(1, 'rt-1', null);
    expect(wrapper.find('#restore-account-mfa').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Authenticator or recovery code required.');

    await wrapper.find('#restore-account-mfa').setValue('123456');
    await restoreButton(wrapper).trigger('click');
    await flushPromises();

    expect(authService.restore).toHaveBeenNthCalledWith(2, 'rt-1', '123456');
    expect(wrapper.emitted('restored')).toHaveLength(1);
    wrapper.unmount();
  });

  it('still shows a wrong code as an error and keeps the code field', async () => {
    authService.restore
      .mockRejectedValueOnce({ response: { status: 422, data: { requires_mfa: true } } })
      .mockRejectedValueOnce({ response: { status: 401, data: { message: 'Invalid authenticator or recovery code.' } } });

    const wrapper = mount(RestoreAccountModal, {
      props: { visible: true, firstName: 'Ada', restorationToken: 'rt-1' },
      attachTo: document.body,
    });
    await flushPromises();
    await restoreButton(wrapper).trigger('click');
    await flushPromises();
    await wrapper.find('#restore-account-mfa').setValue('000000');
    await restoreButton(wrapper).trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Invalid authenticator or recovery code.');
    expect(wrapper.find('#restore-account-mfa').exists()).toBe(true);
    expect(wrapper.emitted('restored')).toBeUndefined();
    wrapper.unmount();
  });
});
