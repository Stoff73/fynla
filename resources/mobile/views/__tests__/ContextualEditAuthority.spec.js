import { describe, expect, it } from 'vitest';

import Goals from '../modules/Goals.vue';
import InvestmentAccountDetail from '../modules/InvestmentAccountDetail.vue';
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
});
