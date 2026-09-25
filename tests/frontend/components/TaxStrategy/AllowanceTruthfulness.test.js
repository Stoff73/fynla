import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import AllowanceCard from '@/components/TaxStrategy/AllowanceCard.vue';
import AllowanceGrid from '@/components/TaxStrategy/AllowanceGrid.vue';
import StrategyRecommendationList from '@/components/TaxStrategy/StrategyRecommendationList.vue';

// Allowances the server marks known:false (TaxStrategyCalculator::position)
// have no confirmed current-year use; saying "Fully used" misstates them.
// /m already words them "Current-year use not confirmed"
// (resources/mobile/views/TaxStrategy.vue remainingLabel).
const unknown = {
  key: 'cgt_allowance', label: 'Capital Gains Tax Allowance', amount: 3000, used: 0,
  remaining: 0, utilisation_pct: 0, status: 'muted', available: true, known: false,
};
const open = {
  key: 'isa_allowance', label: 'ISA Allowance', amount: 20000, used: 0,
  remaining: 20000, utilisation_pct: 0, status: 'raspberry', available: true, known: true,
};

const store = () => createStore({
  modules: { taxStrategy: { namespaced: true, getters: { taxYear: () => '2026/27' } } },
});

describe('allowance truthfulness', () => {
  it('never labels an unconfirmed allowance "Fully used"', () => {
    const w = mount(AllowanceCard, { props: { allowance: unknown } });

    expect(w.text()).toContain('Current-year use not confirmed');
    expect(w.text()).not.toContain('Fully used');
    expect(w.text()).not.toContain('used');
  });

  it('keeps unconfirmed allowances out of the headroom group and total', () => {
    const w = mount(AllowanceGrid, {
      props: { allowances: [unknown, open] },
      global: { plugins: [store()] },
    });
    const headroomSection = w.findAll('h3').find((h) => h.text() === 'Headroom available').element.parentElement;

    expect(headroomSection.textContent).toContain('ISA Allowance');
    expect(headroomSection.textContent).not.toContain('Capital Gains Tax Allowance');
    expect(w.text()).toContain('Current-year use not confirmed');
  });

  it('does not claim there is nothing to act on when household actions exist', () => {
    const recs = [{
      type: 'marriage_allowance_transfer', category: 'household', priority: 'medium',
      title: 'Claim Marriage Allowance', description: 'd', estimated_annual_tax_saved: 252, completed: false,
    }];
    const w = mount(StrategyRecommendationList, {
      global: {
        plugins: [createStore({
          modules: { taxStrategy: { namespaced: true, getters: { recommendations: () => recs } } },
        })],
        mocks: { $router: { push: () => {} } },
        stubs: ['router-link'],
      },
    });

    expect(w.text()).not.toContain('Nothing to act on right now');
  });
});

describe('empty recommendations copy (the /m rule)', () => {
  const mountList = (allowances) => mount(StrategyRecommendationList, {
    global: {
      plugins: [createStore({
        modules: { taxStrategy: { namespaced: true, getters: { recommendations: () => [], userAllowances: () => allowances } } },
      })],
      mocks: { $router: { push: () => {} } },
      stubs: ['router-link'],
    },
  });

  it('never calls allowances well-utilised while one has known headroom', () => {
    const w = mountList([open]);

    expect(w.text()).not.toContain('well-utilised');
    expect(w.text()).toContain('Your unused allowances are shown below');
  });

  it('says well-utilised only when no allowance has known headroom', () => {
    expect(mountList([unknown]).text()).toContain('well-utilised');
  });
});

describe('tax strategy header (the /m rule)', () => {
  it('does not claim allowances are well-utilised while one has known headroom', async () => {
    const { default: TaxYearHeader } = await import('@/components/TaxStrategy/TaxYearHeader.vue');
    const w = mount(TaxYearHeader, {
      global: {
        plugins: [createStore({
          modules: {
            taxStrategy: { namespaced: true, getters: { taxYear: () => '2026/27', recommendations: () => [], composedPlan: () => null, userAllowances: () => [open] } },
            auth: { namespaced: true, getters: { currentUser: () => ({ first_name: 'Lena' }) } },
          },
        })],
      },
    });

    expect(w.text()).not.toContain('well-utilised');
    expect(w.text()).not.toContain('tracking well');
  });
});
