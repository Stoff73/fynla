import { describe, it, expect } from 'vitest';
import { retirementHeadline } from '@/utils/retirementHeadline';

describe('retirementHeadline', () => {
  it('leads with the pension pot when there is one', () => {
    const headline = retirementHeadline({ pot_value: 500000, guaranteed_income: 0, target_income: 0 });

    expect(headline.value).toBe(500000);
    expect(headline.isAnnualIncome).toBe(false);
    expect(headline.caption).toBe('Your pension pot');
  });

  it('leads with the guaranteed income when there is no pot', () => {
    // The defect: a defined-benefit-only spouse was shown £0 and told to
    // "Plan your retirement" while her retirement page read £35,000 a year.
    const headline = retirementHeadline({ pot_value: 0, guaranteed_income: 35000, target_income: 0 });

    expect(headline.value).toBe(35000);
    expect(headline.isAnnualIncome).toBe(true);
    expect(headline.caption).toBe('Guaranteed retirement income');
  });

  it('never presents an annual income as a balance', () => {
    const potHousehold = retirementHeadline({ pot_value: 500000, guaranteed_income: 11502.4 });

    // A household with both keeps the pot as the headline, so the "/year" suffix
    // the caller appends can never land on a capital figure.
    expect(potHousehold.isAnnualIncome).toBe(false);
    expect(potHousehold.value).toBe(500000);
  });

  it('prefers the target caption once a target is set', () => {
    const headline = retirementHeadline({ pot_value: 500000, target_income: 40000 });

    expect(headline.caption).toBe('Towards your target');
  });

  it('prompts a user with neither a pot nor secured income', () => {
    const headline = retirementHeadline({ pot_value: 0, guaranteed_income: 0, target_income: 0 });

    expect(headline.value).toBe(0);
    expect(headline.isAnnualIncome).toBe(false);
    expect(headline.caption).toBe('Plan your retirement');
  });

  it('treats a missing module, and string figures from JSON, as numbers', () => {
    expect(retirementHeadline(undefined).value).toBe(0);
    expect(retirementHeadline({ pot_value: '0.00', guaranteed_income: '35000.00' }).value).toBe(35000);
    expect(retirementHeadline({ pot_value: null, guaranteed_income: null }).caption).toBe('Plan your retirement');
  });
});
