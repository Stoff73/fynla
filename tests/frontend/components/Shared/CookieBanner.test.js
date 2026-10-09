import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CookieBanner from '@/components/Shared/CookieBanner.vue';

// CSJ 2026-10-09: the cookie banner must never show to someone signed in (a
// user cannot sign in without accepting cookies); it showed over the dashboard
// once the browser's consent cookie was gone.
describe('CookieBanner', () => {
  beforeEach(() => {
    document.cookie = 'fyn_cookie_consent=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    sessionStorage.clear();
  });

  it('asks a visitor who has not answered', () => {
    expect(mount(CookieBanner).vm.visible).toBe(true);
  });

  it('never asks someone signed in, even with no consent cookie', () => {
    sessionStorage.setItem('auth_token', '1|abc');
    expect(mount(CookieBanner).vm.visible).toBe(false);
  });

  it('does not ask a visitor who has answered', () => {
    document.cookie = 'fyn_cookie_consent=accepted; path=/';
    expect(mount(CookieBanner).vm.visible).toBe(false);
  });
});
