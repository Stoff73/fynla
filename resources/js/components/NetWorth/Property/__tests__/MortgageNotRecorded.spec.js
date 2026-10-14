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

  // T63 (release ac prod walk): the Overview read "Purchase Price: £0".
  it('names a purchase price never given as Not recorded', () => {
    const { formatPurchasePrice } = PropertyDetailInline.methods;
    expect(formatPurchasePrice.call({ ...ctx, property: { purchase_price: null } })).toBe('Not recorded');
    expect(formatPurchasePrice.call({ ...ctx, property: { purchase_price: 250000 } })).toBe('£250,000');
  });

  // T61 (release ac prod walk): the same card read "Full Annual Payment: £0".
  // T81 (T77 walk, Robin 509): a postcode and purchase date never given were
  // blank lines beside "Purchase Price: Not recorded".
  it('names a postcode and purchase date never given as Not recorded', () => {
    const { formatPostcode, formatPurchaseDate, formatDate } = PropertyDetailInline.methods;
    const withProperty = (property) => ({ ...ctx, property, formatDate });
    expect(formatPostcode.call(withProperty({ postcode: null }))).toBe('Not recorded');
    expect(formatPostcode.call(withProperty({ postcode: 'GU21 6AA' }))).toBe('GU21 6AA');
    expect(formatPurchaseDate.call(withProperty({ purchase_date: null }))).toBe('Not recorded');
    expect(formatPurchaseDate.call(withProperty({ purchase_date: '2019-04-01' }))).toBe('01/04/2019');
  });

  it('names a missing annual payment and payment share as Not recorded', () => {
    const { formatMortgageAnnualPayment, formatMortgagePaymentShare } = PropertyDetailInline.methods;
    const withShare = (share) => ({ ...ctx, mortgageShare: () => share });

    expect(formatMortgageAnnualPayment.call(withShare({ annual_payment: null }), { id: 1 })).toBe('Not recorded');
    expect(formatMortgageAnnualPayment.call(withShare(null), { id: 1 })).toBe('Not recorded');
    expect(formatMortgageAnnualPayment.call(withShare({ annual_payment: 9360 }), { id: 1 })).toBe('£9,360');
    expect(formatMortgagePaymentShare.call(withShare({ user_monthly_payment: null }), { id: 1 })).toBe('Not recorded');
    expect(formatMortgagePaymentShare.call(withShare({ user_monthly_payment: 390 }), { id: 1 })).toBe('£390');
  });
});
