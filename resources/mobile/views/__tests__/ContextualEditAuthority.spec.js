import { describe, expect, it } from 'vitest';

import Goals from '../modules/Goals.vue';
import Investment from '../modules/Investment.vue';
import InvestmentAccountDetail from '../modules/InvestmentAccountDetail.vue';
import Protection from '../modules/Protection.vue';
import Retirement from '../modules/Retirement.vue';
import Savings from '../modules/Savings.vue';
import SavingsAccount from '../modules/SavingsAccount.vue';

describe('/m contextual edit authority', () => {
  it('does not build an edit request for a read-only joint savings account', () => {
    const request = SavingsAccount.computed.contextualRequest.call({
      accountId: '41',
      account: { is_primary_owner: false },
    });

    expect(request).toBeNull();
  });

  it('does not build an edit request for a read-only joint investment account', () => {
    const request = InvestmentAccountDetail.computed.contextualRequest.call({
      accountId: '42',
      account: { is_primary_owner: false },
    });

    expect(request).toBeNull();
  });

  it('hides joint goal edits and uses the canonical goals fallback', () => {
    expect(Goals.methods.canEditGoal({ is_primary_owner: false })).toBe(false);
    expect(Goals.methods.canEditGoal({ is_primary_owner: true })).toBe(true);

    const request = Goals.methods.goalRequest('edit', 43);
    expect(request.current_destination.fallback).toBe('goals');
  });

  it('keeps populated product overviews on contextual Add rather than module-wide Edit', () => {
    const requests = [
      Savings.computed.contextualRequest.call({ accounts: [{ id: 1 }] }),
      Investment.computed.contextualRequest.call({ accounts: [{ id: 2 }] }),
      Retirement.computed.contextualRequest.call({ pensions: [{ id: 3 }] }),
      Protection.computed.contextualRequest.call({ policies: [{ id: 4 }] }),
      Goals.computed.contextualRequest.call({
        goals: [{ id: 5 }],
        goalRequest: Goals.methods.goalRequest,
      }),
    ];

    expect(requests.map((request) => request.action)).toEqual([
      'add',
      'add',
      'add',
      'add',
      'add',
    ]);
  });
});
