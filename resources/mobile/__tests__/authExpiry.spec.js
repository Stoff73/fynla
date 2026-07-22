import { describe, it, expect, beforeEach, vi } from 'vitest';
import { handleAuthExpiry } from '../authExpiry.js';
import { store } from '../store.js';

// Unit coverage for the shared /m auth-expiry helper (extracted from the
// onboardingChat mixin — CSJ audit, 2026-07-21 — so every data-view load()
// that does NOT mix in onboardingChat can re-authenticate the same way a
// dead-token chat tap already does). apiGet/apiPost/apiStream all resolve
// { ok, status, ... } on a non-2xx response rather than throwing, so this is
// checked on the resolved value, not via try/catch.
describe('authExpiry.js — handleAuthExpiry (shared /m 401 handler)', () => {
  let push;

  beforeEach(() => {
    push = vi.fn();
    store.token = 'dead-token';
    store.user = { id: 1 };
  });

  it('logs out and redirects to /m login on a 401, and reports it handled', () => {
    expect(handleAuthExpiry({ status: 401 }, { push })).toBe(true);
    expect(store.token).toBeNull();
    expect(store.user).toBeNull();
    expect(push).toHaveBeenCalledWith('/login');
  });

  it('is a no-op for a healthy response', () => {
    store.token = 'still-alive';
    expect(handleAuthExpiry({ status: 200 }, { push })).toBe(false);
    expect(store.token).toBe('still-alive');
    expect(push).not.toHaveBeenCalled();
  });

  it('is a no-op for a non-401 error status (e.g. a plain 500)', () => {
    store.token = 'still-alive';
    expect(handleAuthExpiry({ status: 500 }, { push })).toBe(false);
    expect(store.token).toBe('still-alive');
    expect(push).not.toHaveBeenCalled();
  });

  it('is a no-op when there is no response object at all', () => {
    store.token = 'still-alive';
    expect(handleAuthExpiry(null, { push })).toBe(false);
    expect(store.token).toBe('still-alive');
    expect(push).not.toHaveBeenCalled();
  });
});
