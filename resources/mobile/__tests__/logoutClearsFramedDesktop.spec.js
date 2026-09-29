import { describe, it, expect, beforeEach } from 'vitest';
import { store } from '../store.js';

// Production defect H1 (live test, 2026-09-29): /m frames the desktop SPA in
// the same tab (mobile-host.blade.php), and the desktop keeps its own copy of
// the bearer in sessionStorage('auth_token'). Signing out of /m left that copy
// behind, so the framed desktop still believed it was signed in and bounced an
// invitee off /register to the /m login.
describe('/m store.logout — framed desktop session', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  it('clears the desktop SPA token held in the same tab', () => {
    store.setToken('m-token');
    sessionStorage.setItem('auth_token', 'desktop-token');
    localStorage.setItem('fynla-state', JSON.stringify({ auth: { user: { first_name: 'Sam' } } }));

    store.logout();

    expect(localStorage.getItem('m_scaffold_token')).toBeNull();
    expect(sessionStorage.getItem('auth_token')).toBeNull();
    // The last user's name must not stay on a shared phone.
    expect(localStorage.getItem('fynla-state')).toBeNull();
  });

  it('leaves other tab state alone', () => {
    sessionStorage.setItem('unrelated', 'keep');
    store.logout();
    expect(sessionStorage.getItem('unrelated')).toBe('keep');
  });
});
