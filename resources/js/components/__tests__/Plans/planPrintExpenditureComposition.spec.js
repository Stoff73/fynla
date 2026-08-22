import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { planPrintMixin } from '../../Plans/Shared/planPrintMixin';

/**
 * W-0140, fifth surface. The adviser print pack builds the same Personal
 * Information card as the four plan panels, in its own renderer. It must carry the
 * same disclosure — a pack that states an expenditure the user never entered is the
 * copy an adviser reads out loud.
 */
const Host = {
  mixins: [planPrintMixin],
  template: '<div />',
};

function financialCardFor(composition) {
  const wrapper = mount(Host);

  return wrapper.vm.buildPersonalInformationHtml({
    full_name: 'Sarah Jones',
    gross_income: 120000,
    net_income: 78157,
    annual_expenditure: 14820,
    disposable_income: 63337,
    monthly_disposable: 5278,
    expenditure_composition: composition,
  }, 'estate');
}

describe('adviser print pack expenditure composition', () => {
  it('states that no expenditure is recorded', () => {
    const html = financialCardFor({
      recorded_annual: 0,
      commitments_annual: 14820,
      has_recorded_expenditure: false,
      basis: 'Financial commitments only — no expenditure recorded',
    });

    expect(html).toContain('Annual Expenditure');
    expect(html).toContain('£14,820');
    expect(html).toContain('Recorded Expenditure');
    expect(html).toContain('None recorded');
    expect(html).toContain('Financial Commitments');
    expect(html).toContain('Expenditure Basis');
    expect(html).toContain('no expenditure recorded');
  });

  it('shows both components, and no absence note, when expenditure is recorded', () => {
    const html = financialCardFor({
      recorded_annual: 29400,
      commitments_annual: 22994.4,
      has_recorded_expenditure: true,
      basis: 'Category entries plus financial commitments',
    });

    expect(html).toContain('£29,400');
    expect(html).toContain('£22,994');
    expect(html).not.toContain('None recorded');
    expect(html).not.toContain('Expenditure Basis');
  });

  it('omits the composition entirely when a plan carries none', () => {
    const html = financialCardFor(undefined);

    expect(html).toContain('Annual Expenditure');
    expect(html).not.toContain('Recorded Expenditure');
    expect(html).not.toContain('Financial Commitments');
  });
});
