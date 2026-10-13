import { describe, it, expect, beforeEach, vi } from 'vitest';
// api.js and authService build the whole store; only the mutations are needed.
vi.mock('@/services/api', () => ({ default: {} }));
vi.mock('@/services/authService', () => ({ default: { getToken: () => null } }));
import auth from '@/store/modules/auth';

// T72 (csjones walk, 2026-10-13): /m frames the web sign-in. Meera was still
// signed in to /m (localStorage 'm_scaffold_token'); Tunde signed in through
// the web form inside /m, and the framed handoff (router/index.js) kept the
// /m token it found, so Tunde landed on Meera's dashboard. A new web sign-in
// is a new person on this browser: any /m session from before it must go.
describe('auth setToken — a new sign-in replaces the /m session', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  it('removes the /m token left by an earlier session when a new token is set', () => {
    localStorage.setItem('m_scaffold_token', 'meera-mobile-token');
    const state = { token: null };

    auth.mutations.setToken(state, 'tunde-web-token');

    expect(state.token).toBe('tunde-web-token');
    expect(localStorage.getItem('m_scaffold_token')).toBeNull();
  });

  it('removes it when one signed-in user is replaced by another', () => {
    localStorage.setItem('m_scaffold_token', 'meera-mobile-token');
    const state = { token: 'meera-web-token' };

    auth.mutations.setToken(state, 'tunde-web-token');

    expect(localStorage.getItem('m_scaffold_token')).toBeNull();
  });

  it('keeps the /m token when the same token is set again', () => {
    localStorage.setItem('m_scaffold_token', 'tunde-mobile-token');
    const state = { token: 'tunde-web-token' };

    auth.mutations.setToken(state, 'tunde-web-token');

    expect(localStorage.getItem('m_scaffold_token')).toBe('tunde-mobile-token');
  });

  it('keeps the /m token when the token is cleared (sign-out clears it through clearAuth)', () => {
    localStorage.setItem('m_scaffold_token', 'tunde-mobile-token');
    const state = { token: 'tunde-web-token' };

    auth.mutations.setToken(state, null);

    expect(localStorage.getItem('m_scaffold_token')).toBe('tunde-mobile-token');
  });
});
