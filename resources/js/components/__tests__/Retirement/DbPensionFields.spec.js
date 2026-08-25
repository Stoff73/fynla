import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import DBPensionForm from '../../Retirement/DBPensionForm.vue';
import {
  DB_SCHEME_TYPE_OPTIONS,
  DB_SCHEME_STATUS_OPTIONS,
  DB_INFLATION_PROTECTION_OPTIONS,
  buildDbPensionPayload,
  formatSchemeStatus,
} from '../../Retirement/dbPensionFields';

/**
 * W-0017. Sarah Jones's NHS 2015 scheme from tests/Persona/peak_earners.md:
 * public-sector career average, £35,000 a year from 60, 50% spouse benefit,
 * Consumer Prices Index revaluation, £105,000 lump sum, 18 years' service.
 * Four of those seven had nowhere to go on the form, so the row saved as
 * final_salary / NULL / NULL / 'none'.
 */
const persona = {
  schemeName: 'NHS Pension Scheme',
  schemeType: 'career_average',
  annualIncome: 35000,
  serviceYears: 18,
  pensionableSalary: 62000,
  normalRetirementAge: 60,
  spousePensionPercent: 50,
  inflationProtection: 'cpi',
  lumpSum: 105000,
};

function makeStore() {
  return createStore({
    modules: {
      aiFormFill: {
        namespaced: true,
        state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
        actions: { beginFieldSequence: () => {} },
      },
      auth: {
        namespaced: true,
        state: () => ({ user: { id: 17 } }),
        getters: { currentUser: (s) => s.user },
      },
    },
  });
}

describe('buildDbPensionPayload', () => {
  it('maps the persona scheme onto every db_pensions column', () => {
    expect(buildDbPensionPayload(persona)).toEqual({
      scheme_name: 'NHS Pension Scheme',
      scheme_type: 'career_average',
      // Unanswered persists as null, which DBPension::isInPayment() reads as
      // "fall back to age" rather than as a stated status (W-0032).
      scheme_status: null,
      accrued_annual_pension: 35000,
      pensionable_service_years: 18,
      pensionable_salary: 62000,
      normal_retirement_age: 60,
      spouse_pension_percent: 50,
      inflation_protection: 'cpi',
      revaluation_method: null,
      lump_sum_entitlement: 105000,
    });
  });

  it('only carries the numeric revaluation rate for the fixed option', () => {
    expect(buildDbPensionPayload({ ...persona, inflationProtection: 'fixed', revaluationRate: 2.5 }).revaluation_method)
      .toBe('2.5%');

    expect(buildDbPensionPayload({ ...persona, inflationProtection: 'cpi', revaluationRate: 2.5 }).revaluation_method)
      .toBeNull();
  });

  it('defaults inflation protection to the column default rather than sending null', () => {
    expect(buildDbPensionPayload({ ...persona, inflationProtection: '' }).inflation_protection).toBe('none');
  });

  it('offers every scheme type and inflation protection value the column accepts', () => {
    expect(DB_SCHEME_TYPE_OPTIONS.map((o) => o.value))
      .toEqual(['final_salary', 'career_average', 'public_sector']);
    expect(DB_INFLATION_PROTECTION_OPTIONS.map((o) => o.value))
      .toEqual(['cpi', 'rpi', 'fixed', 'none']);
  });

  /**
   * W-0032. The select used to send its own display text ("In Payment") to a
   * column that did not exist. It now sends the stored vocabulary, which
   * DBPension::SCHEME_STATUSES declares — if these drift, a status the user
   * picks fails validation instead of persisting.
   */
  it('sends the stored scheme status vocabulary, not the display labels', () => {
    expect(DB_SCHEME_STATUS_OPTIONS.map((o) => o.value))
      .toEqual(['active', 'deferred', 'in_payment']);

    expect(buildDbPensionPayload({ ...persona, schemeStatus: 'in_payment' }).scheme_status)
      .toBe('in_payment');
  });

  it('reads an unrecorded scheme status back as unknown rather than guessing Active', () => {
    expect(formatSchemeStatus('in_payment')).toBe('In Payment');
    expect(formatSchemeStatus('active')).toBe('Active');
    expect(formatSchemeStatus(null)).toBe('Not recorded');
    expect(formatSchemeStatus(undefined)).toBe('Not recorded');
  });

  it('spells out acronyms in every user-facing label (Rule 9)', () => {
    const labels = [...DB_SCHEME_TYPE_OPTIONS, ...DB_INFLATION_PROTECTION_OPTIONS].map((o) => o.label);

    expect(labels).not.toContain('CARE');
    expect(labels).toContain('Consumer Prices Index');
    expect(labels).toContain('Retail Prices Index');
  });
});

describe('DBPensionForm', () => {
  const mountForm = (pension = null) => shallowMount(DBPensionForm, {
    props: { pension },
    global: { plugins: [makeStore()] },
  });

  it('emits the persona scheme with all seven fields intact', async () => {
    const wrapper = mountForm();

    await wrapper.setData({
      formData: {
        ...wrapper.vm.formData,
        employer_name: 'NHS Pension Scheme',
        scheme_status: 'active',
        scheme_type: 'career_average',
        annual_income: 35000,
        service_years: 18,
        final_salary: 62000,
        normal_retirement_age: 60,
        spouse_pension_percent: 50,
        inflation_protection: 'cpi',
        pcls_available: 105000,
      },
    });

    wrapper.vm.handleSubmit();

    expect(wrapper.emitted('save')[0][0]).toEqual({
      scheme_name: 'NHS Pension Scheme',
      scheme_type: 'career_average',
      scheme_status: 'active',
      accrued_annual_pension: 35000,
      pensionable_service_years: 18,
      pensionable_salary: 62000,
      normal_retirement_age: 60,
      spouse_pension_percent: 50,
      inflation_protection: 'cpi',
      revaluation_method: null,
      lump_sum_entitlement: 105000,
    });
  });

  /**
   * The edit watcher used to spread the db_pensions record straight onto
   * formData, so every input was bound to a key the record does not have — the
   * edit form opened blank and then refused to submit.
   */
  it('populates the edit form from the db_pensions column names', () => {
    const wrapper = mountForm({
      id: 4,
      scheme_name: 'NHS Pension Scheme',
      scheme_type: 'career_average',
      scheme_status: 'deferred',
      accrued_annual_pension: '35000.00',
      pensionable_service_years: '18.00',
      pensionable_salary: '62000.00',
      normal_retirement_age: 60,
      spouse_pension_percent: '50.00',
      inflation_protection: 'cpi',
      revaluation_method: '2.5%',
      lump_sum_entitlement: '105000.00',
    });

    expect(wrapper.vm.formData).toMatchObject({
      employer_name: 'NHS Pension Scheme',
      scheme_type: 'career_average',
      // W-0032: restored on edit, so saving again cannot silently clear it.
      scheme_status: 'deferred',
      annual_income: 35000,
      service_years: 18,
      final_salary: 62000,
      normal_retirement_age: 60,
      spouse_pension_percent: 50,
      inflation_protection: 'cpi',
      revaluation_rate: 2.5,
      pcls_available: 105000,
    });
  });
});
