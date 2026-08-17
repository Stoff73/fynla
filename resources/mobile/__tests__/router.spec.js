import { describe, it, expect, beforeEach } from 'vitest';
import { store } from '../store.js';
import router from '../router.js';

// Router-guard coverage for the signed-out deep-link case (CSJ audit,
// 2026-07-21): visiting an authenticated /m route directly (e.g. a bookmark
// or a fresh browser tab at /m/app/dashboard) with no token must redirect to
// the /m login screen rather than mount the view and let its data load fail
// with a generic "could not load" error.
describe('router.js — tokenless guard on authenticated routes', () => {
  beforeEach(async () => {
    store.token = null;
    store.user = null;
    await router.push('/login');
  });

  it('redirects a tokenless visit to the dashboard to /login', async () => {
    await router.push('/dashboard');
    expect(router.currentRoute.value.path).toBe('/login');
  });

  it('redirects a tokenless deep link to a module route to /login', async () => {
    await router.push('/net-worth');
    expect(router.currentRoute.value.path).toBe('/login');
  });

  it('allows navigation to an authenticated route once a token is present', async () => {
    store.token = 'live-token';
    await router.push('/dashboard');
    expect(router.currentRoute.value.path).toBe('/dashboard');
  });

  it('leaves the public /login route reachable without a token', async () => {
    await router.push('/login');
    expect(router.currentRoute.value.path).toBe('/login');
  });

  it.each([
    '/conversation-history',
    '/personal-information',
    '/settings',
    '/notifications',
    '/subscription',
  ])('protects the account route %s', async (path) => {
    await router.push(path);
    expect(router.currentRoute.value.path).toBe('/login');
  });

  it.each([
    '/conversation-history',
    '/personal-information',
    '/settings',
    '/notifications',
    '/subscription',
  ])('allows an authenticated visit to %s', async (path) => {
    store.token = 'live-token';
    await router.push(path);
    expect(router.currentRoute.value.path).toBe(path);
  });
});
