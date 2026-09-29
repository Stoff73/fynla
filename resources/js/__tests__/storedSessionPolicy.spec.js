import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetStoredSessionCheck, storedSessionIsLive } from '@/router/storedSessionPolicy.js';

// Production defect H1 (live test, 2026-09-29): a desktop token revoked by the
// /m sign-out survived in sessionStorage, so guest-only /register treated the
// invitee as signed in and redirected them away. Guest-only and public routes
// now confirm the stored token with the server before acting on it.
function makeStore(token, fetchUser) {
  const state = { auth: { token, user: { id: 1 } } };
  return {
    state,
    dispatch: vi.fn(fetchUser),
    commit: vi.fn((type, value) => {
      if (type === 'auth/setToken') state.auth.token = value;
      if (type === 'auth/setUser') state.auth.user = value;
    }),
  };
}

describe('storedSessionIsLive', () => {
  beforeEach(() => {
    resetStoredSessionCheck();
    sessionStorage.clear();
  });

  it('drops a token the server rejects with 401 and reports it dead', async () => {
    sessionStorage.setItem('auth_token', 'revoked');
    const store = makeStore('revoked', () => Promise.reject({ status: 401 }));

    expect(await storedSessionIsLive(store)).toBe(false);
    expect(store.state.auth.token).toBeNull();
    expect(store.state.auth.user).toBeNull();
    expect(sessionStorage.getItem('auth_token')).toBeNull();
  });

  it('treats a 419 the same as a 401', async () => {
    const store = makeStore('expired', () => Promise.reject({ status: 419 }));
    expect(await storedSessionIsLive(store)).toBe(false);
  });

  it('keeps the session on a network error or 5xx', async () => {
    sessionStorage.setItem('auth_token', 'live');
    const store = makeStore('live', () => Promise.reject({ message: 'Network Error' }));

    expect(await storedSessionIsLive(store)).toBe(true);
    expect(store.state.auth.token).toBe('live');
    expect(sessionStorage.getItem('auth_token')).toBe('live');
  });

  it('asks the server once per token', async () => {
    const store = makeStore('live', () => Promise.resolve({ id: 1 }));

    expect(await storedSessionIsLive(store)).toBe(true);
    expect(await storedSessionIsLive(store)).toBe(true);
    expect(store.dispatch).toHaveBeenCalledTimes(1);
    expect(store.dispatch).toHaveBeenCalledWith('auth/fetchUser');
  });

  it('reports no session without calling the server when there is no token', async () => {
    const store = makeStore(null, () => Promise.resolve());
    expect(await storedSessionIsLive(store)).toBe(false);
    expect(store.dispatch).not.toHaveBeenCalled();
  });
});
