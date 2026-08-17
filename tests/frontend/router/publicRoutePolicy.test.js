import { describe, expect, it } from 'vitest';

import { isAuthenticatedPublicUtilityPath } from '@/router/publicRoutePolicy.js';

describe('authenticated public-route policy', () => {
  it.each(['/help', '/privacy', '/terms'])(
    'keeps %s reachable from authenticated mobile settings',
    (path) => {
      expect(isAuthenticatedPublicUtilityPath(path)).toBe(true);
    },
  );

  it.each(['/', '/pricing', '/about', '/security'])(
    'continues redirecting authenticated marketing route %s',
    (path) => {
      expect(isAuthenticatedPublicUtilityPath(path)).toBe(false);
    },
  );
});
