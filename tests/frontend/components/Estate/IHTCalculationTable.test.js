import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import IHTCalculationTable from '@/components/Estate/IHTCalculationTable.vue';

/**
 * W-0134 and W-0136 — the estate column has to survive addition.
 *
 * What a user saw before these: four allowance rows of £325,000, £325,000,
 * £175,000 and £175,000 summing to £1,000,000, printed directly above a subtotal
 * of £850,000. The £150,000 of chargeable transfers that made up the difference
 * appeared in no row at all — only in a sentence appended to a message string —
 * and the charitable legacy the server had already deducted was gated behind a
 * what-if toggle, so the column between Net Estate and Taxable Estate came out
 * £20,000 short as well.
 *
 * These assert the ARITHMETIC of what is rendered rather than the presence of a
 * label, because a label can be present and still not add up, which is exactly
 * how this survived a browser pass.
 */

// The `peak_earners` household as measured on 2026-08-21: net estate £1,716,780,
// a £150,000 chargeable lifetime transfer inside the seven-year window, £20,000 of
// fixed charitable legacies, and a projection to age 84 of £4,368,401 — which is
// £2,368,401 above the taper threshold, so the residence band is extinguished.
const NOW_ALLOWANCES = {
  nrb: 325000,
  nrbFromSpouseModelled: 325000,
  nrbFromSpouse: 0,
  nrbGiftDeduction: 150000,
  totalNrb: 500000,
  rnrbIndividual: 175000,
  // W-0154 F2 — the residence band's components, matching the nil rate band above.
  // These rows used to be `totalRnrb / 2` each: the total halved and presented as
  // though it were two measured figures. That reconciles only while the halves are
  // equal, and they stop being equal the moment the residence cap or the taper
  // bites — at which point the table showed two numbers that summed to the total by
  // construction and described nothing.
  rnrbSpouseModelled: 175000,
  rnrbResidenceCapReduction: 0,
  rnrbTaperReduction: 0,
  rnrbFromSpouse: 0,
  totalRnrb: 350000,
  rnrbEligible: true,
  rnrbStatus: 'full',
  rnrbTaperThreshold: 2000000,
  showSeparateSpouseAllowances: false,
};

const PROJECTED_ALLOWANCES = {
  ...NOW_ALLOWANCES,
  rnrbIndividual: 175000,
  rnrbSpouseModelled: 175000,
  // Extinguished by the taper at £4,368,401 — and the £350,000 removed now has a
  // row of its own, rather than being a residual the reader had to infer.
  rnrbTaperReduction: 350000,
  totalRnrb: 0,
  rnrbEligible: false,
  rnrbStatus: 'tapered',
};

const mountTable = (overrides = {}) => mount(IHTCalculationTable, {
  props: {
    assetsBreakdown: { user: { name: 'David Jones' }, spouse: { name: 'Sarah Jones' } },
    liabilitiesBreakdown: {},
    totals: {
      grossAssets: { now: 2021780, projected: 4368401, minus5: 0, plus5: 0 },
      liabilities: { now: 305000, projected: 0, minus5: 0, plus5: 0 },
      netEstate: { now: 1716780, projected: 4368401, minus5: 0, plus5: 0 },
    },
    allowances: NOW_ALLOWANCES,
    allowancesProjected: PROJECTED_ALLOWANCES,
    charitableExemption: { now: 20000, projected: 20000, minus5: 20000, plus5: 20000 },
    estateAfterNRB: { now: 1216780, projected: 3868401, minus5: 0, plus5: 0 },
    taxableEstate: { now: 846780, projected: 3848401, minus5: 0, plus5: 0 },
    ihtLiability: { now: 338712, projected: 1539360, minus5: 0, plus5: 0 },
    showSpouse: true,
    estimatedAge: 84,
    ...overrides,
  },
});

/** Sum a row model's column, honouring the sign each row is rendered with. */
const columnTotal = (rows, column) => rows.reduce(
  (total, row) => total + (row.sign === '+' ? -row[column] : row[column]),
  0,
);

describe('IHTCalculationTable — the allowance column reconciles (W-0134)', () => {
  it('sums the rendered rows to the printed subtotal in the current column', () => {
    const vm = mountTable().vm;

    expect(columnTotal(vm.allowanceRows, 'now')).toBe(vm.totalAllowances.now);
    expect(vm.totalAllowances.now).toBe(850000);
  });

  it('sums the rendered rows to the printed subtotal in the projected column', () => {
    const vm = mountTable().vm;

    // The column that used to be impossible: the residence band is gone at death
    // but the rows beside it still described today's £350,000.
    expect(columnTotal(vm.allowanceRows, 'projected')).toBe(vm.totalAllowances.projected);
    expect(vm.totalAllowances.projected).toBe(500000);
  });

  it('gives the chargeable transfer its own row, signed as an addition', () => {
    const vm = mountTable().vm;
    const gift = vm.allowanceRows.find((row) => row.key === 'nrb-gift-deduction');

    expect(gift).toBeDefined();
    expect(gift.now).toBe(150000);
    expect(gift.sign).toBe('+');
  });

  it('labels the spouse band as a modelled second-death transfer, not an allowance held today', () => {
    const vm = mountTable().vm;
    const spouse = vm.allowanceRows.find((row) => row.key === 'nrb-spouse-modelled');

    // Writing 325,000 into `nrb_transferred` would make the column add up and the
    // payload wrong: there is no transferable band while both spouses are alive.
    expect(spouse.now).toBe(325000);
    expect(spouse.note).toMatch(/second death/i);
  });

  it('renders the charitable exemption independently of the what-if toggle', () => {
    const wrapper = mountTable();

    expect(wrapper.vm.hasCharitableExemption).toBe(true);
    expect(wrapper.text()).toContain('Charitable Legacies');
  });

  it('closes the gap between net estate and taxable estate with visible rows only', () => {
    const vm = mountTable().vm;

    const reconciled = vm.totals.netEstate.now
      - vm.totalAllowances.now
      - vm.charitableExemption.now;

    expect(reconciled).toBe(vm.taxableEstate.now);

    const projectedReconciled = vm.totals.netEstate.projected
      - vm.totalAllowances.projected
      - vm.charitableExemption.projected;

    expect(projectedReconciled).toBe(vm.taxableEstate.projected);
  });

  it('states the projected taper rather than repeating the current position', () => {
    const note = mountTable().vm.residenceBandNote;

    expect(note).toMatch(/At age 84/);
    expect(note).toMatch(/removing the home allowance entirely/);
  });
});

describe('IHTCalculationTable — the single and widowed columns reconcile too', () => {
  it('shows the gross band for a single person, so the gift row is not double-counted', () => {
    const allowances = {
      nrb: 325000,
      nrbFromSpouseModelled: 0,
      nrbFromSpouse: 0,
      nrbGiftDeduction: 100000,
      totalNrb: 225000,
      rnrbIndividual: 175000,
      rnrbFromSpouse: 0,
      totalRnrb: 175000,
      rnrbEligible: true,
      rnrbStatus: 'full',
      rnrbTaperThreshold: 2000000,
      showSeparateSpouseAllowances: false,
    };

    const vm = mountTable({
      assetsBreakdown: { user: { name: 'John Morgan' } },
      showSpouse: false,
      allowances,
      allowancesProjected: allowances,
    }).vm;

    // This branch used to print `totalNrb` — already net of the deduction — and
    // then render the deduction again beneath it.
    expect(vm.allowanceRows.find((row) => row.key === 'nrb-individual').now).toBe(325000);
    expect(columnTotal(vm.allowanceRows, 'now')).toBe(400000);
    expect(vm.totalAllowances.now).toBe(400000);
  });

  it('gives a widow a named row for a residence band reduced by the estate', () => {
    const allowances = {
      nrb: 325000,
      nrbFromSpouseModelled: 0,
      nrbFromSpouse: 325000,
      nrbGiftDeduction: 0,
      totalNrb: 650000,
      rnrbIndividual: 175000,
      rnrbFromSpouse: 175000,
      totalRnrb: 200000,
      rnrbEligible: true,
      rnrbStatus: 'tapered',
      rnrbTaperThreshold: 2000000,
      showSeparateSpouseAllowances: true,
    };

    const vm = mountTable({
      assetsBreakdown: { user: { name: 'Patricia Bennett' } },
      showSpouse: false,
      allowances,
      allowancesProjected: allowances,
    }).vm;

    // The gross components exceed what is available once the taper bites, so the
    // difference needs a row of its own rather than an unexplained subtotal.
    const reduction = vm.allowanceRows.find((row) => row.key === 'rnrb-reduction');

    expect(reduction).toBeDefined();
    expect(reduction.now).toBe(150000);
    expect(reduction.sign).toBe('+');
    expect(columnTotal(vm.allowanceRows, 'now')).toBe(vm.totalAllowances.now);
  });
});

describe('IHTCalculationTable — callers that predate the projected allowances', () => {
  it('falls back to the current allowances when none are supplied', () => {
    const vm = mountTable({ allowancesProjected: null }).vm;

    expect(vm.projectedAllowances).toBe(vm.allowances);
    expect(vm.totalAllowances.projected).toBe(vm.totalAllowances.now);
  });
});

/**
 * W-0132 — the row labelled its own figure with a rate that figure was not
 * calculated at, and a toggle could replace the figure with a fabricated one.
 *
 * Priya Raman's screen: "Inheritance Tax Liability (40%) … £397,651" beside a taxable
 * estate of £1,104,585. £397,651 is 36% of that. 40% would have been £441,834 — the
 * label and the number were £44,183 apart. The label came from
 * `charitableBequest ? '36%' : '40%'`, two literals decided by a user toggle that was
 * written but never read back, so it said 40% permanently.
 *
 * These check the RELATIONSHIP between the printed rate and the printed figures —
 * divide the liability by the taxable estate beside it and the answer has to be the
 * rate in the label. A label sourced from anything other than the calculation fails,
 * even though every number in the fixture was supplied to the component.
 */
describe('IHTCalculationTable — the stated rate is the rate the figure was calculated at (W-0132)', () => {
  // Priya's household as the server computed it: reduced rate, because her recorded
  // £10,000 legacy clears the £6,500 threshold.
  const REDUCED = {
    taxableEstate: { now: 1104585, projected: 1104585, minus5: 0, plus5: 0 },
    ihtLiability: { now: 397651, projected: 397651, minus5: 0, plus5: 0 },
    ihtRateLabel: '36%',
  };

  /** The percentage the printed liability actually represents, to the nearest whole. */
  const impliedRate = (liability, taxableEstate) => Math.round((liability / taxableEstate) * 100);

  it('prints a rate that divides into the figures beside it', () => {
    const wrapper = mountTable(REDUCED);
    const text = wrapper.text();

    const label = text.match(/Inheritance Tax Liability \(([\d]+)%\)/);
    expect(label).not.toBeNull();

    expect(Number(label[1])).toBe(impliedRate(REDUCED.ihtLiability.now, REDUCED.taxableEstate.now));
    expect(Number(label[1])).toBe(36);
  });

  it('states both columns when the projection reaches a different rate', () => {
    // The projection re-runs the 10% test against the projected estate (W-0136), so
    // one label across both columns would be wrong in one of them.
    const wrapper = mountTable({
      ...REDUCED,
      ihtRateLabel: '36% today, 40% at age 84',
    });

    expect(wrapper.text()).toContain('Inheritance Tax Liability (36% today, 40% at age 84)');
  });

  it('asserts no rate at all when the calculation supplied none', () => {
    // The prop used to default to '40%', so a caller that passed nothing still made
    // a claim about the rate. Silence is the only honest default.
    const wrapper = mountTable({ ihtRateLabel: null });

    expect(wrapper.text()).toContain('Inheritance Tax Liability');
    expect(wrapper.text()).not.toMatch(/Inheritance Tax Liability \(/);
    expect(wrapper.text()).not.toContain('40%');
  });

  it('renders the server figures unchanged, with no assumed donation deducted', () => {
    const wrapper = mountTable(REDUCED);
    const vm = wrapper.vm;

    // The what-if layout deducted 10% of baseline — £148,444 for Priya — and applied
    // the reduced rate to the remainder, producing £344,211 where the server said
    // £397,651. The component no longer has the props to do that with.
    expect(vm.taxableEstate.now).toBe(1104585);
    expect(vm.ihtLiability.now).toBe(397651);
    expect(wrapper.text()).not.toContain('Less: Charitable Bequest (10% minimum)');
    expect(wrapper.text()).not.toContain('Deducted from estate, qualifies for 36% rate');
  });

  it('has no toggle-driven alternate layout left to switch into', () => {
    // Both props are gone. Passing them must not resurrect a second layout — Vue
    // ignores unknown props, so this asserts the table renders one thing only.
    const wrapper = mountTable({ ...REDUCED, charitableBequest: true, charitableDonation: { now: 148444, minus5: 0, projected: 148444, plus5: 0 } });

    expect(wrapper.text()).toContain('Less: Tax-Free Allowances');
    expect(wrapper.text()).not.toContain('Less: Tax-Free Allowance (Nil Rate Band)');
    expect(wrapper.text()).not.toContain('148,444');
  });
});
