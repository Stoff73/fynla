import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { DEFAULT_RETIREMENT_AGE, DEFAULT_DB_NORMAL_RETIREMENT_AGE } from '../retirementAge';

/**
 * W-0196. Eleven components each carried their own retirement-age fallback, split
 * 67/68 exactly as the backend constants were, plus two hardcoded 65s for defined
 * benefit pensions where the model says 67. A user could see 67 on the accumulation
 * chart and 68 on the capital adequacy tab, computed from the same profile.
 *
 * **The guard is the item.** Fixing eleven sites without one just resets the clock:
 * the twelfth literal lands next week and every existing test stays green, because
 * they all assert on service output rather than on what the components hardcode.
 * This one reads the files.
 */
describe('one retirement-age default across the frontend (W-0196)', () => {
  const root = path.resolve(__dirname, '../../');

  const consolidated = [
    'components/Retirement/StrategiesTab.vue',
    'components/Retirement/IncomeProjectionChart.vue',
    'components/Retirement/RetirementIncomeTab.vue',
    'components/Retirement/CapitalAdequacyTab.vue',
    'components/Retirement/AccumulationChart.vue',
    'components/NetWorth/Property/PropertyDetailInline.vue',
    'components/NetWorth/InvestmentProjections.vue',
    'components/NetWorth/PensionDetailInline.vue',
    'components/Onboarding/steps/AssetsStep.vue',
    'components/UserProfile/ExpenditureForm.vue',
    'views/Investment/AccountPerformancePanel.vue',
  ];

  it('is 67, matching the value anchored to the pension projection', () => {
    expect(DEFAULT_RETIREMENT_AGE).toBe(67);
    expect(DEFAULT_DB_NORMAL_RETIREMENT_AGE).toBe(DEFAULT_RETIREMENT_AGE);
  });

  it.each(consolidated)('leaves no retirement-age literal in %s', (file) => {
    const source = fs.readFileSync(path.join(root, file), 'utf8');

    // Only retirement-age fallbacks. `state_pension_age || 67` is a DIFFERENT number
    // legislated by cohort and belongs to W-0197 and W-0516 — folding it in here
    // would merge two unrelated questions.
    const lines = source.split('\n').filter((line) => {
      if (line.includes('state_pension_age') || line.trimStart().startsWith('*')) return false;
      return /(retirement_age|retirementAge|RetirementAge)\s*(\|\||\?\?)\s*6[578]\b/.test(line);
    });

    expect(lines).toEqual([]);
  });

  it('is imported by every component that needed it', () => {
    consolidated.forEach((file) => {
      const source = fs.readFileSync(path.join(root, file), 'utf8');
      expect(source).toContain('constants/retirementAge');
    });
  });
});
