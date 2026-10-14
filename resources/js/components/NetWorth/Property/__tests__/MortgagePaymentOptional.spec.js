import { describe, expect, it } from 'vitest';

import PropertyForm from '../PropertyForm.vue';

// T77 (T69 walk, Robin 509): Fyn saves a mortgage with no payment (T25, T64),
// but the web form would not leave the mortgage step without one ("Please
// enter the monthly mortgage payment."), though the field is not marked
// required and the store allows none.
function stepFrom(mortgageForm) {
  const ctx = {
    error: null,
    currentStep: 3,
    stepMapping: { 1: 1, 3: 3, 4: 4, 5: 5 },
    hasMortgage: true,
    totalSteps: 5,
    mortgageForm,
    scrollToTop: () => {},
  };
  PropertyForm.methods.nextStep.call(ctx);
  return ctx;
}

describe('PropertyForm mortgage step', () => {
  it('moves on with a balance and no monthly payment', () => {
    const ctx = stepFrom({ outstanding_balance: 180000, monthly_payment: null });
    expect(ctx.error).toBeNull();
    expect(ctx.currentStep).toBe(4);
  });

  it('still asks for the balance', () => {
    const ctx = stepFrom({ outstanding_balance: null, monthly_payment: 780 });
    expect(ctx.error).toBe('Please enter the outstanding balance for the mortgage.');
    expect(ctx.currentStep).toBe(3);
  });
});
