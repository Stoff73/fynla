import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PlanExpenditureComposition from '../../Plans/Shared/PlanExpenditureComposition.vue';
import EstatePersonalInformation from '../../Plans/Estate/EstatePersonalInformation.vue';
import InvestmentPersonalInformation from '../../Plans/Investment/InvestmentPersonalInformation.vue';
import RetirementPersonalInformation from '../../Plans/Retirement/RetirementPersonalInformation.vue';
import ProtectionPersonalInformation from '../../Plans/Protection/ProtectionPersonalInformation.vue';

/**
 * W-0140. Sarah has recorded no expenditure at all, and the plan showed £14,820
 * under "Annual Expenditure" — to the pound, her share of three properties'
 * monthly commitments. The figure keeps its meaning (entries plus commitments,
 * because Disposable Income has to subtract commitments to be true); what it must
 * no longer do is present commitments as spending the user described.
 *
 * The panel is repeated in four plans and again in the adviser print pack, so the
 * disclosure lives in one component and one util. These tests pin the statement,
 * and that all four panels carry it.
 */
const NONE_RECORDED = {
  recorded_annual: 0,
  commitments_annual: 14820,
  has_recorded_expenditure: false,
  basis: 'Financial commitments only — no expenditure recorded',
};

const RECORDED = {
  recorded_annual: 29400,
  commitments_annual: 22994.4,
  has_recorded_expenditure: true,
  basis: 'Category entries plus financial commitments',
};

describe('PlanExpenditureComposition', () => {
  it('says no expenditure is recorded instead of showing an amount', () => {
    const wrapper = mount(PlanExpenditureComposition, { props: { composition: NONE_RECORDED } });
    const text = wrapper.text();

    expect(text).toContain('Recorded Expenditure');
    expect(text).toContain('None recorded');
    expect(text).not.toContain('£0');
    expect(text).toContain('Financial Commitments');
    expect(text).toContain('£14,820');
    expect(text).toContain('no expenditure recorded');
  });

  it('shows both components when the user has recorded expenditure, and no absence note', () => {
    const wrapper = mount(PlanExpenditureComposition, { props: { composition: RECORDED } });
    const text = wrapper.text();

    expect(text).toContain('£29,400');
    expect(text).toContain('£22,994');
    expect(text).not.toContain('None recorded');
    expect(text).not.toContain('Category entries plus financial commitments');
  });

  it('renders nothing when the plan carries no composition', () => {
    const wrapper = mount(PlanExpenditureComposition, { props: { composition: null } });

    expect(wrapper.text()).toBe('');
  });
});

describe('every plan personal information panel carries the disclosure', () => {
  const panels = {
    estate: EstatePersonalInformation,
    investment: InvestmentPersonalInformation,
    retirement: RetirementPersonalInformation,
    protection: ProtectionPersonalInformation,
  };

  const info = {
    full_name: 'Sarah Jones',
    gross_income: 120000,
    net_income: 78157,
    annual_expenditure: 14820,
    disposable_income: 63337,
    monthly_disposable: 5278,
    expenditure_composition: NONE_RECORDED,
  };

  Object.entries(panels).forEach(([name, component]) => {
    it(`${name} states that none is recorded`, () => {
      const wrapper = mount(component, { props: { info } });
      const text = wrapper.text();

      expect(text).toContain('Annual Expenditure');
      expect(text).toContain('£14,820');
      expect(text).toContain('None recorded');
      expect(text).toContain('no expenditure recorded');
    });
  });
});
