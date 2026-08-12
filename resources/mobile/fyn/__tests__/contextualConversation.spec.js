import { describe, expect, it } from 'vitest';

import { buildContextualConversationRequest } from '../contextualConversation.js';

describe('buildContextualConversationRequest', () => {
  it('builds the exact snake_case identifier-only contract', () => {
    const request = buildContextualConversationRequest({
      action: 'edit',
      resourceType: 'savings_account',
      resourceId: 42,
      currentDestination: {
        screen: 'savings_account_detail',
        params: {
          account_id: 42,
          account_name: 'Rainy Day',
          current_balance: 12500,
        },
        fallback: 'savings',
      },
      origin: {
        kind: 'surface_action',
        recommendationId: null,
        prompt: 'Update my £12,500 account',
      },
      name: 'Rainy Day',
      balance: 12500,
      prompt: 'Update my £12,500 account',
    });

    expect(request).toEqual({
      action: 'edit',
      resource_type: 'savings_account',
      resource_id: 42,
      current_destination: {
        screen: 'savings_account_detail',
        params: { account_id: 42 },
        fallback: 'savings',
      },
      origin: {
        kind: 'surface_action',
        recommendation_id: null,
      },
    });
    expect(JSON.stringify(request)).not.toMatch(/balance|value|name|prompt|£12,500/i);
  });

  it('omits resource_id for overview Add actions', () => {
    expect(buildContextualConversationRequest({
      action: 'add',
      resourceType: 'investment',
      currentDestination: {
        screen: 'investment',
        params: {},
        fallback: 'dashboard',
      },
      origin: { kind: 'surface_action' },
    })).toEqual({
      action: 'add',
      resource_type: 'investment',
      current_destination: {
        screen: 'investment',
        params: {},
        fallback: 'dashboard',
      },
      origin: {
        kind: 'surface_action',
        recommendation_id: null,
      },
    });
  });

  it('keeps only approved canonical detail identifiers', () => {
    const request = buildContextualConversationRequest({
      action: 'edit',
      resourceType: 'income',
      currentDestination: {
        screen: 'income_detail',
        params: {
          income_owner: 'user',
          income_source: 'employment',
          property_id: 12,
          mortgage_id: 13,
          liability_id: 14,
          amount: 72000,
          balance: 180000,
        },
        fallback: 'income',
      },
      origin: { kind: 'surface_action' },
    });

    expect(request.current_destination.params).toEqual({
      income_owner: 'user',
      income_source: 'employment',
      property_id: 12,
      mortgage_id: 13,
      liability_id: 14,
    });
    expect(JSON.stringify(request)).not.toMatch(/72000|180000|amount|balance/);
  });
});
