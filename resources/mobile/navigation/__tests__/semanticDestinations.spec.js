import { describe, expect, it, vi } from 'vitest';
import { resolveMobileDestination } from '../semanticDestinations.js';

describe('mobile semantic destinations', () => {
  it('prefers the typed screen over a conflicting legacy tax path', () => {
    expect(resolveMobileDestination({
      payload: '/tax-strategy',
      destination: {
        screen: 'retirement',
        params: {},
        fallback: 'net_worth',
      },
    })).toBe('/retirement');
  });

  it('uses the explicit fallback and reports an unknown screen without parameters', () => {
    const recordUnknown = vi.fn();

    expect(resolveMobileDestination({
      payload: '/tax-strategy',
      destination: {
        screen: 'future_screen',
        params: { account_id: 8472, current_value: '184500' },
        fallback: 'net_worth',
      },
    }, recordUnknown)).toBe('/net-worth');
    expect(recordUnknown).toHaveBeenCalledOnce();
    expect(recordUnknown).toHaveBeenCalledWith('future_screen');
  });

  it('falls back to the dashboard when both semantic names are unknown', () => {
    expect(resolveMobileDestination({
      destination: {
        screen: 'future_screen',
        params: {},
        fallback: 'future_fallback',
      },
    })).toBe('/dashboard');
  });

  it('keeps allowlisted legacy paths working during the additive rollout', () => {
    expect(resolveMobileDestination({ payload: '/investment' })).toBe('/investment');
    expect(resolveMobileDestination({ payload: '/not-an-app-route' })).toBe('/dashboard');
  });

  it('builds supported detail paths from URL-encoded identifiers', () => {
    expect(resolveMobileDestination({
      destination: {
        screen: 'investment_account_detail',
        params: { account_id: 'provider/ref 7' },
        fallback: 'investment',
      },
    })).toBe('/investment/account/provider%2Fref%207');
  });

  it.each([
    ['goal_detail', { goal_id: 12 }, '/goals/12'],
    ['property_detail', { property_id: 23 }, '/net-worth/property/23'],
    ['mortgage_detail', { mortgage_id: 34 }, '/net-worth/mortgage/34'],
    ['liability_detail', { liability_id: 45 }, '/net-worth/liability/45'],
    [
      'income_detail',
      { income_owner: 'user', income_source: 'self employment' },
      '/income/user/self%20employment',
    ],
  ])('resolves the canonical %s path', (screen, params, path) => {
    expect(resolveMobileDestination({
      destination: { screen, params, fallback: 'net_worth' },
    })).toBe(path);
  });

  it.each([
    ['conversation_history', '/conversation-history'],
    ['personal_information', '/personal-information'],
    ['subscription', '/subscription'],
    ['settings', '/settings'],
  ])('resolves the server-advertised %s destination', (screen, path) => {
    expect(resolveMobileDestination({
      destination: { screen, params: {}, fallback: 'dashboard' },
    })).toBe(path);
  });
});
