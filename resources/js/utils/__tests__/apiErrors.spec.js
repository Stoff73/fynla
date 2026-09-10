import { describe, it, expect } from 'vitest';
import { tierLimitFrom, apiErrorMessage } from '../apiErrors';

/**
 * W-0544. A Free account adding a second property got a 403 whose body named
 * the limit, the message and the upgrade destination; onboarding rendered
 * "Failed to save property. Please try again." — advice that can never work.
 */
const tierLimit403 = {
  response: {
    status: 403,
    data: {
      success: false,
      error: 'tier_limit_reached',
      error_type: 'tier_limit_reached',
      entity_key: 'property',
      current_count: 1,
      hard_limit: 1,
      required_tier: 'premium',
      message: 'Property limit reached for your current plan.',
      action: 'subscription_options',
      destination: { screen: 'subscription', params: [], fallback: 'net_worth' },
    },
  },
  message: 'Request failed with status code 403',
};

describe('tierLimitFrom', () => {
  it('reads the cap, the label, the message and the destination off a tier-limit 403', () => {
    const limit = tierLimitFrom(tierLimit403);

    expect(limit).toMatchObject({
      entityKey: 'property',
      entityLabel: 'properties',
      cap: 1,
      requiredTier: 'premium',
      message: 'Property limit reached for your current plan.',
    });
    expect(limit.destination.screen).toBe('subscription');
  });

  it('is null for any other failure', () => {
    expect(tierLimitFrom({ response: { status: 422, data: { message: 'Invalid' } } })).toBeNull();
    expect(tierLimitFrom(new Error('network'))).toBeNull();
    expect(tierLimitFrom(undefined)).toBeNull();
  });
});

describe('apiErrorMessage', () => {
  it("prefers the server's message and never axios's status string", () => {
    expect(apiErrorMessage(tierLimit403, 'Failed to save property. Please try again.'))
      .toBe('Property limit reached for your current plan.');
  });

  it('falls back when the server sent no message', () => {
    expect(apiErrorMessage(new Error('Request failed with status code 500'), 'Failed to save. Please try again.'))
      .toBe('Failed to save. Please try again.');
    expect(apiErrorMessage({ response: { data: { message: '   ' } } }, 'fallback')).toBe('fallback');
  });
});
