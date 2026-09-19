import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiPost: vi.fn() }));

import { apiPost } from '../../api.js';
import { store } from '../../store.js';
import Login from '../Login.vue';

/**
 * MB-18 / MB-19. The /m login handled only the emailed-code path. A
 * two-factor user saw "MFA verification required." as an error (no code
 * step); a deleted, restorable account saw "We could not sign you in."
 * (no restore step). Web and native have both branches.
 */
describe('MobileLogin — two-factor and restore branches', () => {
  let push;
  let wrapper;

  const signIn = async () => {
    await wrapper.find('input[type=email]').setValue('ada@example.com');
    await wrapper.find('input[type=password]').setValue('Password1!');
    await wrapper.find('form').trigger('submit');
    await flushPromises();
  };

  beforeEach(() => {
    vi.clearAllMocks();
    store.setToken(null);
    store.user = null;
    push = vi.fn();
    wrapper = mount(Login, { global: { mocks: { $router: { push, replace: vi.fn() } } } });
  });

  it('opens the authenticator step on requires_mfa and signs in with the code', async () => {
    apiPost
      .mockResolvedValueOnce({ ok: true, status: 200, data: { success: true, requires_mfa: true, data: { mfa_token: 'mfa-1', email: 'a***a@example.com' } } })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { success: true, data: { access_token: 'tok', user: { id: 1 } } } });
    await signIn();

    expect(wrapper.text()).toContain('authenticator');
    expect(wrapper.text()).not.toContain('MFA verification required');
    const boxes = wrapper.findAll('.ml-code__box');
    expect(boxes).toHaveLength(6);
    for (let i = 0; i < 6; i += 1) await boxes[i].setValue(String(i + 1));
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(apiPost).toHaveBeenNthCalledWith(2, '/api/auth/mfa/verify', { code: '123456', mfa_token: 'mfa-1' });
    expect(store.token).toBe('tok');
    expect(push).toHaveBeenCalledWith('/dashboard');
  });

  it('signs in with a recovery code when the authenticator is unavailable', async () => {
    apiPost
      .mockResolvedValueOnce({ ok: true, status: 200, data: { requires_mfa: true, data: { mfa_token: 'mfa-1', email: 'a***a@example.com' } } })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { success: true, data: { access_token: 'tok', user: { id: 1 }, remaining_recovery_codes: 7 } } });
    await signIn();

    const toggle = wrapper.findAll('button').find((b) => /recovery code/i.test(b.text()));
    await toggle.trigger('click');
    await wrapper.find('input[autocomplete="one-time-code"]').setValue('ABCD-EFGH-IJKL');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(apiPost).toHaveBeenNthCalledWith(2, '/api/auth/mfa/recovery', { recovery_code: 'ABCD-EFGH-IJKL', mfa_token: 'mfa-1' });
    expect(store.token).toBe('tok');
  });

  it('returns to sign-in with a message when the authenticator code is wrong (the challenge is single-use)', async () => {
    apiPost
      .mockResolvedValueOnce({ ok: true, status: 200, data: { requires_mfa: true, data: { mfa_token: 'mfa-1', email: 'a***a@example.com' } } })
      .mockResolvedValueOnce({ ok: false, status: 401, data: { success: false, message: 'Invalid verification code.' } });
    await signIn();
    const boxes = wrapper.findAll('.ml-code__box');
    for (let i = 0; i < 6; i += 1) await boxes[i].setValue('0');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.find('input[type=email]').exists()).toBe(true);
    expect(wrapper.text()).toContain('Invalid verification code.');
    expect(wrapper.text()).toContain('sign in again');
    expect(store.token).toBeNull();
  });

  it('offers to restore a deleted account, asks for the code when required, and enters the app', async () => {
    apiPost
      .mockResolvedValueOnce({ ok: true, status: 200, data: { account_deleted_restorable: true, deleted_at: '2026-09-01T10:00:00Z', first_name: 'Ada', restoration_token: 'rt-1' } })
      .mockResolvedValueOnce({ ok: false, status: 422, data: { requires_mfa: true, message: 'Authenticator or recovery code required.' } })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { token: 'tok', user: { id: 1, first_name: 'Ada' }, redirect_to: '/dashboard?openFyn=journey&from=pensioncheck' } });
    await signIn();

    expect(wrapper.text()).toContain('Welcome back, Ada');
    expect(wrapper.text()).toContain('1 September 2026');
    expect(wrapper.text()).not.toContain('We could not sign you in');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(apiPost).toHaveBeenNthCalledWith(2, '/api/auth/restore', { restoration_token: 'rt-1' });
    const codeInput = wrapper.find('input[autocomplete="one-time-code"]');
    expect(codeInput.exists()).toBe(true);
    await codeInput.setValue('123456');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(apiPost).toHaveBeenNthCalledWith(3, '/api/auth/restore', { restoration_token: 'rt-1', mfa_code: '123456' });
    expect(store.token).toBe('tok');
    expect(push).toHaveBeenCalledWith({ path: '/dashboard', query: { from: 'pensioncheck' } });
  });

  it('still handles the emailed-code path exactly as before', async () => {
    apiPost
      .mockResolvedValueOnce({ ok: true, status: 200, data: { requires_verification: true, data: { challenge_token: 'ch-1', email: 'a***a@example.com' } } })
      .mockResolvedValueOnce({ ok: true, status: 200, data: { data: { access_token: 'tok', user: { id: 1 } } } });
    await signIn();
    expect(wrapper.text()).toContain('We sent a 6-digit code');
    const boxes = wrapper.findAll('.ml-code__box');
    for (let i = 0; i < 6; i += 1) await boxes[i].setValue(String(i + 1));
    await wrapper.find('form').trigger('submit');
    await flushPromises();
    expect(apiPost).toHaveBeenNthCalledWith(2, '/api/auth/verify-code', { email: 'ada@example.com', code: '123456', challenge_token: 'ch-1', type: 'login' });
    expect(store.token).toBe('tok');
  });
});

// Batch 7 (CSJ 2026-09-19): Laura "wouldn't be let in and no forgotten password option".
describe('MobileLogin — a way back in', () => {
  it('offers the forgotten-password link and names a lockout plainly', async () => {
    store.setToken(null);
    store.user = null;
    const wrapper = mount(Login, { global: { mocks: { $router: { push: vi.fn(), replace: vi.fn() } } } });
    const forgot = wrapper.findAll('a').find((a) => a.text() === 'Forgotten your password?');
    expect(forgot).toBeTruthy();
    expect(forgot.attributes('href')).toContain('login?forgot=1');

    apiPost.mockResolvedValueOnce({ ok: false, status: 423, data: { success: false, locked: true, message: 'Too many failed attempts. Try again in 1 minute.' } });
    await wrapper.find('input[type=email]').setValue('ada@example.com');
    await wrapper.find('input[type=password]').setValue('Password1!');
    await wrapper.find('form').trigger('submit');
    await flushPromises();
    expect(wrapper.text()).toContain('Too many failed attempts. Try again in 1 minute.');
  });
});
