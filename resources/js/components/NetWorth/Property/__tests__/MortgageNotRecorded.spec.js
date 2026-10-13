import { describe, it, expect } from 'vitest';
import PropertyDetailInline from '../PropertyDetailInline.vue';

/**
 * T25 (walk 2026-10-11, Q1, N7): a mortgage given only its balance was stored
 * as lender "To be completed", type "repayment", rate 0 and payment 0. Those
 * are null now and read "Not recorded" (as R10 did for savings rates).
 */
const { formatMortgageType, mortgageLender, formatMortgagePayment, formatMortgageRate } = PropertyDetailInline.methods;
const ctx = { formatCurrency: (v) => `£${Number(v).toLocaleString('en-GB')}`, calculateFullMonthlyPayment: PropertyDetailInline.methods.calculateFullMonthlyPayment };

describe('PropertyDetailInline — what was not given (T25)', () => {
  it('names a missing type, lender, payment and rate as Not recorded', () => {
    expect(formatMortgageType.call(ctx, null)).toBe('Not recorded');
    expect(formatMortgageType.call(ctx, 'interest_only')).toBe('Interest Only');
    expect(mortgageLender.call(ctx, { lender_name: null })).toBe('Not recorded');
    expect(mortgageLender.call(ctx, { lender_name: 'BM Solutions' })).toBe('BM Solutions');
    expect(formatMortgagePayment.call(ctx, { monthly_payment: null })).toBe('Not recorded');
    expect(formatMortgagePayment.call(ctx, { monthly_payment: 780 })).toBe('£780');
    expect(formatMortgageRate.call(ctx, { interest_rate: null })).toBe('Not recorded');
    expect(formatMortgageRate.call(ctx, { interest_rate: '5.2000' })).toBe('5.20%');
  });
});
