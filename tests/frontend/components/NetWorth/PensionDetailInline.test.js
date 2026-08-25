import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PensionDetailInline from '@/components/NetWorth/PensionDetailInline.vue';

/**
 * W-0441 (a pension's holdings had no way in) and W-0442 (the table hid what it
 * stored), plus the retirement-age display fault attached to W-0196.
 *
 * The pension arrives as a PROP, so passing one is the component's real
 * interface rather than an injection past a mapping layer. What is deliberately
 * NOT done here is `setData` of a prepared view-model — every assertion below
 * reads RENDERED TEXT, because three defects in one night lived between a
 * correct service and a correct component (`tests/CLAUDE.md` §4).
 */
const buildStore = (user = {}) => createStore({
  modules: {
    auth: { namespaced: true, state: () => ({ user }) },
    preview: { namespaced: true, getters: { isPreviewMode: () => false } },
    retirement: {
      namespaced: true,
      actions: {
        updateDCPension: () => {},
        updateDBPension: () => {},
        updateStatePension: () => {},
        deleteDCPension: () => {},
        deleteDBPension: () => {},
        fetchRetirementData: () => {},
      },
    },
  },
});

const mountPanel = ({ pension = {}, user = {} } = {}) => mount(PensionDetailInline, {
  props: {
    pensionType: 'dc',
    pension: {
      id: 10,
      scheme_name: "David's SIPP",
      provider: 'Interactive Investor',
      pension_type: 'sipp',
      current_fund_value: 320000,
      holdings: [],
      ...pension,
    },
  },
  global: {
    plugins: [buildStore(user)],
    directives: { 'preview-disabled': {} },
    stubs: {
      UnifiedPensionForm: true,
      ConfirmDialog: true,
      PensionPotProjectionChart: true,
      HoldingForm: true,
    },
  },
});

describe('PensionDetailInline — the retirement age it displays', () => {
  /**
   * **Three MUTUALLY DISTINCT values, and that is the whole point of this
   * fixture.** On the live database David's `users.target_retirement_age` and his
   * SIPP's `dc_pensions.retirement_age` are BOTH 60, so the correct source and
   * the wrong source produce the same number and no test built on real data could
   * tell them apart (`tests/CLAUDE.md` §4, Collision). 62 for the pension, 58 for
   * the user, and 67 for the literal that used to be the fallback.
   */
  it("shows the pension's own retirement age, not the user's household target", () => {
    const wrapper = mountPanel({
      pension: { retirement_age: 62 },
      user: { target_retirement_age: 58 },
    });

    const shown = wrapper.find('[data-testid="pension-retirement-age"]').text();

    expect(shown).toBe('62');
    expect(shown).not.toBe('58');
    expect(shown).not.toBe('67');
  });

  it('shows no age at all rather than a hardcoded 67 when the pension has none', () => {
    const wrapper = mountPanel({
      pension: { retirement_age: null },
      user: { target_retirement_age: 58 },
    });

    const shown = wrapper.find('[data-testid="pension-retirement-age"]').text();

    // An em dash, matching what /m already shows for the same field. Not 67 —
    // and not 58 either, which would be the user's target under a label a reader
    // takes as the pension's.
    expect(shown).toBe('—');
  });
});

describe('PensionDetailInline — the way in to holdings (W-0441)', () => {
  it('offers a Holdings tab on a pension that has no holdings yet', () => {
    const wrapper = mountPanel({ pension: { holdings: [] } });

    // The defect exactly: the tab was gated on already having holdings, so a
    // pension with none had no tab, and with no tab there was no way to add one.
    const tabs = wrapper.findAll('nav button').map(button => button.text());

    expect(tabs).toContain('Holdings');
    expect(wrapper.find('[data-testid="pension-add-holding"]').exists()).toBe(true);
  });

  it('says the pension has no holdings rather than showing a 0.00% fund charge', () => {
    const wrapper = mountPanel({ pension: { holdings: [] } });

    expect(wrapper.text()).toContain('No holdings recorded');
    // "0.00%" is a claim about the charges, not an absence of one. The £320,000
    // pension reporting it against a persona charging 0.25% is what raised this.
    expect(wrapper.text()).not.toContain('0.00%');
  });

  it('says a platform fee is not recorded rather than reporting it as zero', () => {
    const wrapper = mountPanel({ pension: { platform_fee_percent: null, holdings: [] } });

    expect(wrapper.text()).toContain('Not recorded');
    expect(wrapper.text()).toContain('No charges recorded for this pension yet');
  });

  it('reports the platform fee and the totals once one is on record', () => {
    // 0.25% is the persona's SIPP platform fee, and it is distinct from every
    // fund charge in the fixtures below, so a total reading the wrong source
    // cannot coincide with it.
    const wrapper = mountPanel({ pension: { platform_fee_percent: 0.25, platform_fee_type: 'percentage', holdings: [] } });

    expect(wrapper.text()).toContain('0.25% p.a.');
    expect(wrapper.text()).toContain('Total Annual Cost');
    // 0.25% of £320,000.
    expect(wrapper.text()).toContain('£800/year');
    expect(wrapper.text()).not.toContain('No charges recorded');
  });

  it('opens the shared holding form when Add Holding is pressed', async () => {
    const wrapper = mountPanel({ pension: { holdings: [] } });

    expect(wrapper.findComponent({ name: 'HoldingForm' }).exists()).toBe(false);

    await wrapper.find('[data-testid="pension-add-holding"]').trigger('click');

    // The same form the investment accounts use, given the pension as its owner
    // (Rule 20 — one holding form, not a pension-shaped copy of one).
    const form = wrapper.findComponent({ name: 'HoldingForm' });
    expect(form.exists()).toBe(true);
    expect(form.props('owner')).toMatchObject({ name: "David's SIPP", value: 320000 });
  });
});

describe('PensionDetailInline — the holdings table (W-0442)', () => {
  // Every figure is mutually distinct, and the value is deliberately NOT what the
  // allocation would derive: 50% of £320,000 is £160,000, while the row stores
  // £160,018 (4,211 units at £38.00). A table recomputing from the allocation
  // therefore prints a different number and fails here.
  const holding = {
    id: 42,
    security_name: 'Vanguard Global Equity',
    asset_type: 'fund',
    allocation_percent: 50,
    quantity: 4211,
    purchase_price: 32.50,
    current_price: 38.00,
    purchase_date: '2019-03-11',
    current_value: 160018,
    ocf_percent: 0.23,
  };

  it('displays the units, purchase price, current price and purchase date it stores', () => {
    const wrapper = mountPanel({ pension: { holdings: [holding] } });
    const row = wrapper.find('[data-testid="pension-holding-42"]').text();

    expect(row).toContain('4,211');
    // Pence, not whole pounds: `formatCurrency` would render both £32.50 and a
    // £1.35 unit price as "£33" and "£1", which is a different figure.
    expect(row).toContain('£32.50');
    expect(row).toContain('£38.00');
    expect(row).toContain('11/03/2019');
    expect(row).toContain('0.23%');
  });

  it('shows the value the holding stores, not one recomputed from the allocation', () => {
    const wrapper = mountPanel({ pension: { holdings: [holding] } });
    const row = wrapper.find('[data-testid="pension-holding-42"]').text();

    expect(row).toContain('£160,018');
    expect(row).not.toContain('£160,000');
  });

  it('shows an em dash for a holding with no unit count, never a zero', () => {
    const wrapper = mountPanel({
      pension: { holdings: [{ ...holding, id: 43, quantity: null }] },
    });

    // "No unit count recorded" and "zero units held" are different facts.
    expect(wrapper.find('[data-testid="pension-holding-43"] [data-testid="holding-units"]').text())
      .toBe('—');
  });

  it('reports a fund charge that moves off zero once holdings exist', () => {
    const wrapper = mountPanel({
      pension: {
        holdings: [
          holding,
          { ...holding, id: 43, security_name: 'BlackRock Corporate Bond', asset_type: 'bond', allocation_percent: 30, quantity: 800, current_price: 120, current_value: 96000, ocf_percent: 0.18 },
          { ...holding, id: 44, security_name: 'L&G UK Property', asset_type: 'property', allocation_percent: 20, quantity: 50000, current_price: 1.28, current_value: 64000, ocf_percent: 0.68 },
        ],
      },
    });

    // (160018 x 0.23 + 96000 x 0.18 + 64000 x 0.68) / 320000 = 0.30501294%, and
    // it is distinct from every individual charge in the set, so a weighting that
    // read only one of them could not land here.
    //
    // Asserted to FIVE places deliberately. Weighting the allocation-derived
    // £160,000 instead of the stored £160,018 gives 0.305 exactly — the two agree
    // to three places, so `toBeCloseTo(0.305, 3)` passes under both hypotheses and
    // proves nothing (tests/CLAUDE.md §4, Collision). The fifth place is where
    // they part.
    expect(wrapper.vm.weightedAverageOCF).toBeCloseTo(0.3050129, 5);
    expect(wrapper.text()).toContain('0.31%');
  });

  it('offers per-row edit and delete', async () => {
    const wrapper = mountPanel({ pension: { holdings: [holding] } });
    const row = wrapper.find('[data-testid="pension-holding-42"]');

    const editButton = row.findAll('button').find(button => button.text() === 'Edit');
    expect(editButton).toBeTruthy();
    expect(row.findAll('button').find(button => button.text() === 'Delete')).toBeTruthy();

    await editButton.trigger('click');

    const form = wrapper.findComponent({ name: 'HoldingForm' });
    expect(form.exists()).toBe(true);
    expect(form.props('holding')).toMatchObject({ id: 42, quantity: 4211 });
  });
});
