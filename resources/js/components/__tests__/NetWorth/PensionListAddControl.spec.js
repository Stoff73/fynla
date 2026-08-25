import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PensionList from '../../NetWorth/PensionList.vue';

/**
 * W-0010. The "Add Pension" control used to live inside the pension-cards column,
 * which only renders once `projections.pension_pot_projection.dc_pension_count`
 * is non-zero. A user whose only pension was Defined Benefit therefore had NO
 * add control anywhere on /net-worth/retirement, while the completeness banner
 * on that same page still asked them for a money-purchase pension and a State
 * Pension forecast — a closed loop.
 */
const dbPension = {
  id: 4,
  scheme_name: 'NHS Pension Scheme',
  scheme_type: 'career_average',
  accrued_annual_pension: 35000,
  lump_sum_entitlement: 105000,
  normal_retirement_age: 60,
};

const statePension = {
  id: 1,
  state_pension_forecast_annual: 11502,
  state_pension_age: 67,
  ni_years: 30,
};

function makeStore({ dcPensions = [], dbPensions = [], statePension: sp = null, projections = null } = {}) {
  return createStore({
    modules: {
      retirement: {
        namespaced: true,
        state: () => ({
          dcPensions,
          dbPensions,
          statePension: sp,
          loading: false,
          error: null,
          projections,
          projectionsLoading: false,
          profile: null,
          activeTab: 'current',
          requiredCapital: null,
          retirementIncome: null,
        }),
        actions: {
          fetchRetirementData: () => {},
          fetchProjections: () => {},
          fetchStrategies: () => {},
          fetchRequiredCapital: () => {},
          fetchRetirementIncome: () => {},
          createDCPension: () => {},
          updateDCPension: () => {},
          createDBPension: () => {},
          updateDBPension: () => {},
          updateStatePension: () => {},
          setActiveTab: () => {},
        },
      },
      auth: {
        namespaced: true,
        state: () => ({ user: { id: 17 }, subscriptionData: null }),
        getters: { currentUser: (s) => s.user },
      },
      subNav: {
        namespaced: true,
        state: () => ({}),
        getters: { pendingAction: () => null, actionCounter: () => 0 },
        actions: { consumeCta: () => {} },
      },
      preview: {
        namespaced: true,
        state: () => ({}),
        getters: { isPreviewMode: () => false },
      },
      netWorth: {
        namespaced: true,
        state: () => ({}),
        actions: { setDetailView: () => {} },
      },
      aiFormFill: {
        namespaced: true,
        state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
        actions: { cancelFill: () => {}, completeFill: () => {}, beginFieldSequence: () => {} },
      },
    },
  });
}

function mountList(options) {
  return shallowMount(PensionList, {
    global: {
      plugins: [makeStore(options)],
      directives: { 'preview-disabled': {} },
    },
  });
}

const addButton = (wrapper) => wrapper.findAll('button').filter((b) => b.text().includes('Add Pension'));

describe('PensionList add control', () => {
  it('offers an add control to a user whose only pension is Defined Benefit', () => {
    const wrapper = mountList({ dbPensions: [dbPension] });

    expect(addButton(wrapper)).toHaveLength(1);
  });

  it('offers an add control to a user whose only pension is the State Pension', () => {
    const wrapper = mountList({ statePension });

    expect(addButton(wrapper)).toHaveLength(1);
  });

  it('offers an add control once projections exist for a Defined Contribution pension', () => {
    const wrapper = mountList({
      dcPensions: [{ id: 1, scheme_name: 'Aviva', current_fund_value: 45000 }],
      projections: { pension_pot_projection: { dc_pension_count: 1 } },
    });

    expect(addButton(wrapper)).toHaveLength(1);
  });

  it('still shows only the empty-state control when there are no pensions at all', () => {
    const wrapper = mountList({});

    expect(addButton(wrapper)).toHaveLength(0);
    expect(wrapper.text()).toContain('Add Your First Pension');
  });

  it('lets a Defined Benefit-only user open the pension they entered', async () => {
    const wrapper = mountList({ dbPensions: [dbPension] });

    await wrapper.find('.guaranteed-item-clickable').trigger('click');

    expect(wrapper.vm.selectedPension).toEqual(dbPension);
    expect(wrapper.vm.selectedPensionType).toBe('db');
  });
});
