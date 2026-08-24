import { describe, it, expect } from 'vitest';
import { retirementHeadline, retirementIncomeHeadline } from '@/utils/retirementHeadline';

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

describe('retirementIncomeHeadline', () => {
  it('leads with secured income when there is no pot, and names it as such', () => {
    // The module page equivalent of the card rule. A projection of a pot that does
    // not exist is not a projection of zero income (W-0244).
    const headline = retirementIncomeHeadline({
      potValue: 0,
      guaranteedIncome: 35000,
      projectedIncome: 0,
    });

    expect(headline.value).toBe(35000);
    expect(headline.isGuaranteed).toBe(true);
    expect(headline.label).toBe('Guaranteed retirement income');
  });

  it('leads with the projection whenever a pot exists', () => {
    const headline = retirementIncomeHeadline({
      potValue: 200000,
      guaranteedIncome: 8000,
      projectedIncome: 18250,
    });

    expect(headline.value).toBe(18250);
    expect(headline.isGuaranteed).toBe(false);
    expect(headline.label).toBe('Projected retirement income');
  });

  it('passes a null projection through instead of inventing a zero', () => {
    // The page renders an em dash for null and "£0" for zero. Those are different
    // statements and the util must not convert the first into the second.
    const headline = retirementIncomeHeadline({
      potValue: 0,
      guaranteedIncome: 0,
      projectedIncome: null,
    });

    expect(headline.value).toBeNull();
    expect(headline.isGuaranteed).toBe(false);
  });

  it('reads string figures from JSON, and survives being called with nothing', () => {
    expect(retirementIncomeHeadline({ potValue: '0.00', guaranteedIncome: '35000.00' }).value).toBe(35000);
    expect(retirementIncomeHeadline().value).toBeNull();
  });
});
