import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import IncomeDefinitionsPanel from '@/components/UserProfile/IncomeDefinitionsPanel.vue';

/**
 * W-0189 — a panel that prints a chain of steps has to produce the figures it prints.
 *
 * What a user saw before this, verbatim from David Jones on `/valuable-info?section=income`:
 *
 *   Total Income                            £159,290
 *   Less pension relief                      -£11,600
 *   Net Income                               £147,690   <- 159,290 - 11,600, correct
 *   Adjusted Net Income                      £147,690
 *   Less employee pension contributions      -£11,600
 *   Threshold Income                         £147,690   <- unchanged by the line above it
 *   Plus employer pension contributions      +£11,600
 *   Adjusted Income                          £170,890   <- not 147,690 + 11,600
 *
 * Two of the three steps did not produce the figure beneath them. The FIGURES were
 * right: threshold income is total income less the employee contribution, and that
 * contribution has already come out once at Net Income, so deducting it again would
 * be wrong. What was wrong was showing it as a step that had been applied twice.
 *
 * These tests therefore assert that the panel's own stated working reaches the
 * figure it is printed beneath — the sentence is parsed and the arithmetic done, so
 * a working line that names the wrong base fails even though every individual number
 * on the page came from the fixture. Asserting "the rendered total equals
 * definitions.total_income" would pass on the broken layout, which is the trap.
 */

const taperStore = () => createStore({
  modules: {
    taxConfig: {
      namespaced: true,
      getters: {
        pensionTaperThresholdIncome: () => 200000,
        pensionTaperAdjustedIncome: () => 260000,
      },
    },
  },
});

// David Jones as measured from `IncomeDefinitionsService::calculate(16)` on
// 2026-08-22: £145,000 employment, £14,290 rental profit, and an 8%/8% workplace
// pension on a £145,000 salary.
const DAVID = {
  total_income: 159289.6,
  net_income: 147689.6,
  adjusted_net_income: 147689.6,
  threshold_income: 147689.6,
  adjusted_income: 170889.6,
  components: { employment: 145000, rental: 14289.6 },
  pension_arrangement: 'net_pay',
  deductions: {
    pension_relief: 11600,
    gift_aid_gross: 0,
    blind_persons_allowance: 0,
    employee_pension_contributions: 11600,
    employer_pension_contributions: 11600,
  },
  adjusted_allowances: {
    personal_allowance: 0,
    personal_allowance_full: 12570,
    personal_allowance_tapered: true,
    pension_annual_allowance: 60000,
    pension_annual_allowance_full: 60000,
    pension_aa_tapered: false,
  },
};

// Sarah Jones, same household, no Defined Contribution pension. Every figure on her
// panel is £128,880 and it was internally consistent before this fix — it must stay
// consistent, and must not sprout steps that do nothing.
const SARAH = {
  ...DAVID,
  total_income: 128880,
  net_income: 128880,
  adjusted_net_income: 128880,
  threshold_income: 128880,
  adjusted_income: 128880,
  components: { employment: 128880 },
  pension_arrangement: 'none',
  deductions: {
    pension_relief: 0,
    gift_aid_gross: 0,
    blind_persons_allowance: 0,
    employee_pension_contributions: 0,
    employer_pension_contributions: 0,
  },
};

const mountPanel = (definitions) => mount(IncomeDefinitionsPanel, {
  props: { definitions },
  global: { plugins: [taperStore()] },
});

/** Every "£1,234" or "£1,234.56" in a string, as numbers, in order. */
const poundsIn = (text) => (text.match(/£[\d,]+(?:\.\d+)?/g) || [])
  .map((figure) => Number(figure.replace(/[£,]/g, '')));

/** The text of the row whose label matches, plus the figure printed beside it. */
const rowFigure = (wrapper, label) => {
  const row = wrapper.findAll('div').find((node) => {
    const spans = node.findAll(':scope > span');

    return spans.length === 2 && spans[0].text() === label;
  });

  return row ? poundsIn(row.findAll(':scope > span')[1].text())[0] : null;
};

describe('IncomeDefinitionsPanel — the printed working reaches the printed figure', () => {
  it('states Threshold Income as Total Income less the employee contribution, and the subtraction lands on the figure above it', () => {
    const wrapper = mountPanel(DAVID);
    const text = wrapper.text();

    const working = text.match(/Your Total Income of £[\d,.]+, less the £[\d,.]+ you paid into your pension\./);
    expect(working).not.toBeNull();

    const [base, deduction] = poundsIn(working[0]);

    // The base named in the sentence must be the figure printed on the Total Income
    // row, not the Adjusted Net Income directly above it — the two differ by exactly
    // the deduction, which is why the old layout could subtract and produce an
    // unchanged figure. Both sides are read off the rendered page, so the sentence
    // and the column cannot drift apart without failing here.
    expect(base).toBe(rowFigure(wrapper, 'Total Income'));
    expect(base).not.toBe(rowFigure(wrapper, 'Adjusted Net Income'));
    expect(base - deduction).toBe(rowFigure(wrapper, 'Threshold Income'));
  });

  it('states Adjusted Income as Total Income plus the employer contribution, and the addition lands on the figure above it', () => {
    const wrapper = mountPanel(DAVID);

    const working = wrapper.text().match(/Your Total Income of £[\d,.]+, plus the £[\d,.]+ your employer paid into your pension\./);
    expect(working).not.toBeNull();

    const [base, addition] = poundsIn(working[0]);

    // £147,690 + £11,600 = £159,290, which is not the £170,890 printed. The base is
    // Total Income and the panel has to say so.
    expect(base).toBe(rowFigure(wrapper, 'Total Income'));
    expect(base).not.toBe(rowFigure(wrapper, 'Threshold Income'));
    expect(base + addition).toBe(rowFigure(wrapper, 'Adjusted Income'));
  });

  it('runs the column from Total Income to Adjusted Net Income and reaches it by hand', () => {
    const wrapper = mountPanel(DAVID);

    const total = rowFigure(wrapper, 'Total Income');
    const employee = rowFigure(wrapper, 'Less employee pension contributions');

    expect(total - employee).toBeCloseTo(rowFigure(wrapper, 'Net Income'), 2);
    expect(rowFigure(wrapper, 'Net Income')).toBeCloseTo(rowFigure(wrapper, 'Adjusted Net Income'), 2);
  });

  it('deducts the employee contribution once, showing it in exactly one row', () => {
    const wrapper = mountPanel(DAVID);

    // It appeared twice, under two names, for the same £11,600 — "Less pension
    // relief" high in the column and "Less employee pension contributions" lower
    // down. A reader following the column downwards deducted it twice.
    const deductionRows = wrapper.findAll('div').filter((node) => {
      const spans = node.findAll(':scope > span');

      return spans.length === 2 && /^(Less pension relief|Less employee pension contributions)$/.test(spans[0].text());
    });

    expect(deductionRows).toHaveLength(1);
    expect(wrapper.text()).not.toContain('Less pension relief');
  });

  it('shows no step between Adjusted Net Income and Threshold Income', () => {
    const wrapper = mountPanel(DAVID);
    const text = wrapper.text();

    const between = text.slice(
      text.indexOf('Adjusted Net Income'),
      text.indexOf('Threshold Income')
    );

    // The old panel put "-£11,600" here and produced an unchanged total beneath it.
    expect(between).not.toMatch(/-£/);
  });

  it('shows no step between Threshold Income and Adjusted Income', () => {
    const wrapper = mountPanel(DAVID);
    const text = wrapper.text();

    const between = text.slice(
      text.indexOf('Threshold Income'),
      text.indexOf('Adjusted Income')
    );

    expect(between).not.toMatch(/\+£/);
  });

  it('names the arrangement that explains why the contribution is deducted once', () => {
    expect(mountPanel(DAVID).text())
      .toContain('taken from your pay before tax');

    expect(mountPanel({ ...DAVID, pension_arrangement: 'salary_sacrifice' }).text())
      .toContain('salary sacrifice');
  });

  it('tells a user with no pension that the figures are unchanged, rather than showing steps that do nothing', () => {
    const wrapper = mountPanel(SARAH);
    const text = wrapper.text();

    expect(text).toContain('you have no employee pension contributions to deduct');
    expect(text).toContain('you have no employer pension contributions to add');
    expect(text).not.toContain('taken from your pay before tax');

    // Her whole panel is one figure, and it stays one figure.
    expect(rowFigure(wrapper, 'Threshold Income')).toBe(SARAH.total_income);
    expect(rowFigure(wrapper, 'Adjusted Income')).toBe(SARAH.total_income);
  });

  /**
   * W-0205 — the Gift Aid row sat above Net Income, so for a donor the figure under
   * that label was net income less the grossed-up donation. Gift Aid is not one of
   * the reliefs ITA 2007 s24 lists; it belongs at s58, with the Blind Person's
   * Allowance. This pins the POSITION, because the figures were never the defect.
   */
  it('prints the Gift Aid deduction below Net Income, with the other adjusted-net-income step', () => {
    const donor = {
      ...SARAH,
      net_income: 128880,
      adjusted_net_income: 126380,
      deductions: { ...SARAH.deductions, gift_aid_gross: 2500 },
    };

    const wrapper = mountPanel(donor);
    const text = wrapper.text();

    const giftAidAt = text.indexOf('Less Gift Aid (grossed up)');
    const netIncomeAt = text.indexOf('Net Income');
    const adjustedNetIncomeAt = text.indexOf('Adjusted Net Income');

    expect(giftAidAt).toBeGreaterThan(-1);
    expect(giftAidAt).toBeGreaterThan(netIncomeAt);
    expect(giftAidAt).toBeLessThan(adjustedNetIncomeAt);

    // And the deduction lands on the figure it is printed above.
    expect(rowFigure(wrapper, 'Net Income')).toBe(128880);
    expect(rowFigure(wrapper, 'Adjusted Net Income')).toBe(126380);
  });

  it('shows no Gift Aid row for a non-donor', () => {
    expect(mountPanel(SARAH).text()).not.toContain('Less Gift Aid');
  });
});
