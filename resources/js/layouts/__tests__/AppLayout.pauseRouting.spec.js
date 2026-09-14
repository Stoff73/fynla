import { describe, expect, it, vi } from 'vitest';
import AppLayout from '../AppLayout.vue';

/**
 * MB-28. Entering a profile-review pause pushes /profile, which the router
 * redirects to /settings/personal. The return leg only fired when the path
 * was still literally '/profile', which it never is, so the user finished
 * the rest of the walk on the Settings page.
 *
 * Every routed view wraps its own <AppLayout>, so the instance that enters
 * the pause is destroyed by the route change and a NEW instance sees the
 * 'wide' event. The spec therefore uses a fresh context per event, sharing
 * only the store, which is what the live page does.
 */
describe('AppLayout — profile-review pause routing (MB-28)', () => {
  const onboardingLayout = AppLayout.watch.onboardingLayout;

  const fakeStore = () => {
    const state = { preProfileRoute: null };
    return {
      state,
      commit: vi.fn((mutation, value) => { if (mutation === 'aiChat/SET_PRE_PROFILE_ROUTE') state.preProfileRoute = value; }),
    };
  };
  const ctx = (store, path) => ({
    isOnboardingRoute: true,
    get preProfileRoute() { return store.state.preProfileRoute; },
    $store: store,
    $route: { path, fullPath: path },
    $router: { push: vi.fn(() => Promise.resolve()) },
  });

  it('returns to the pre-pause route from a fresh layout instance after /profile redirected to Settings', () => {
    const store = fakeStore();
    const entering = ctx(store, '/dashboard');
    onboardingLayout.call(entering, 'standard');
    expect(entering.$router.push).toHaveBeenCalledWith('/profile');
    expect(store.state.preProfileRoute).toBe('/dashboard');

    const returning = ctx(store, '/settings/personal'); // new instance, new route
    onboardingLayout.call(returning, 'wide');
    expect(returning.$router.push).toHaveBeenCalledWith('/dashboard');
    expect(store.state.preProfileRoute).toBeNull();
  });

  it('does nothing on the first wide event of a session when no pause is in progress', () => {
    const c = ctx(fakeStore(), '/dashboard');
    onboardingLayout.call(c, 'wide');
    expect(c.$router.push).not.toHaveBeenCalled();
  });

  it('does not overwrite the stored pre-pause route on a repeated standard event', () => {
    const store = fakeStore();
    onboardingLayout.call(ctx(store, '/dashboard'), 'standard');
    const again = ctx(store, '/settings/personal');
    onboardingLayout.call(again, 'standard');
    expect(store.state.preProfileRoute).toBe('/dashboard');
    expect(again.$router.push).not.toHaveBeenCalled();
  });
});
