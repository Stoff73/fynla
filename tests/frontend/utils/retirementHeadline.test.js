import { describe, it, expect } from 'vitest';
import { retirementHeadline } from '@/utils/retirementHeadline';

/*
 * The card renders the server's choice (MobileDashboardAggregator `card_value`
 * and `card_value_is_income`, from RetirementHeadline): one figure, every
 * surface (CSJ 2026-10-01). The client never chooses between fields.
 */
describe('retirementHeadline', () => {
  it('renders the pot the server sends', () => {
    const headline = retirementHeadline({ card_value: 500000, card_value_is_income: false, target_income: 0 });

    expect(headline.value).toBe(500000);
    expect(headline.isAnnualIncome).toBe(false);
    expect(headline.caption).toBe('Your pension pot');
  });

  it('renders the secured income a year when the server says so', () => {
    const headline = retirementHeadline({ card_value: 35000, card_value_is_income: true, target_income: 0 });

    expect(headline.value).toBe(35000);
    expect(headline.isAnnualIncome).toBe(true);
    expect(headline.caption).toBe('Guaranteed retirement income');
  });

  it('does not pick a field itself: pot and guaranteed income are ignored', () => {
    // The old client rule chose between pot_value and guaranteed_income. If it
    // still did, this would show 35000 a year.
    const headline = retirementHeadline({ card_value: 120000, card_value_is_income: false, pot_value: 0, guaranteed_income: 35000 });

    expect(headline.value).toBe(120000);
    expect(headline.isAnnualIncome).toBe(false);
  });

  it('prefers the target caption once a target is set', () => {
    const headline = retirementHeadline({ card_value: 500000, card_value_is_income: false, target_income: 40000 });

    expect(headline.caption).toBe('Towards your target');
  });

  it('prompts a user with neither a pot nor secured income', () => {
    const headline = retirementHeadline({ card_value: 0, card_value_is_income: false, target_income: 0 });

    expect(headline.value).toBe(0);
    expect(headline.caption).toBe('Plan your retirement');
  });
});
