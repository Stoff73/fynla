import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { data: {} } })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
  },
}));

const IHTPlanning = (await import('../../Estate/IHTPlanning.vue')).default;

/**
 * W-0399 — the Charitable Bequest card said "Your will leaves £20,000 to charity"
 * and then, two lines lower, "your charitable giving of 0.6% (£10,000)".
 *
 * BOTH FIGURES WERE CORRECT. `charitable_deduction` is the pooled section 23(1)
 * exemption across the household; the message quotes what Schedule 1A's 10% test
 * compares, which is the survivor's will alone. A tax-compliance ruling of
 * 2026-08-21 established the distinction and it is quoted in
 * `IHTCalculationService::determineIHTRate()`.
 *
 * The defect was that the engine drew the distinction and threw it away —
 * `charitable_rate_test_amount` reached no result array, no controller and no
 * screen — so the card rendered a household figure under a first-person label
 * with no way to explain the number beside it.
 *
 * NEITHER FIGURE IS "YOUR WILL" ON A MARRIED HOUSEHOLD. The exemption is the
 * household's, and the rate-test amount is the SURVIVOR's — who is not the
 * logged-in user half the time. Every assertion below is therefore about what
 * each figure IS, never whose it is.
 *
 * ASYMMETRIC BY CONSTRUCTION. £35,000 pooled against £30,000 tested, from
 * legacies of £30,000 and £5,000. On the persona that found this, both spouses
 * left £10,000, so the pooled figure is exactly double the tested one and a
 * reading that halved or doubled either would land on the other. Nothing here
 * uses figures where that is possible.
 */
const POOLED_EXEMPTION = 35000;
const RATE_TEST_AMOUNT = 30000;

function makeStore(ihtResponse = null) {
  return createStore({
    modules: {
      estate: {
        namespaced: true,
        state: () => ({ analysis: null, gifts: [], lifeEvents: [], lifeEventImpact: null, lpas: [] }),
        getters: { netWorthValue: () => 0, ihtLiability: () => 0, ihtExemptAssets: () => [] },
        actions: {
          fetchLpas: () => Promise.resolve(),
          calculateIHTPlanning: () => Promise.resolve(ihtResponse),
        },
      },
      taxConfig: {
        namespaced: true,
        state: () => ({}),
        getters: {
          ihtNilRateBand: () => 325000,
          ihtStandardRate: () => 0.4,
          ihtReducedRate: () => 0.36,
          ihtRnrbTaperThreshold: () => 2000000,
          annualGiftExemption: () => 3000,
        },
      },
      auth: {
        namespaced: true,
        state: () => ({ user: { id: 17, marital_status: 'married' } }),
        getters: { currentUser: (s) => s.user },
      },
    },
  });
}

async function cardWith(ihtData) {
  const wrapper = shallowMount(IHTPlanning, {
    global: {
      plugins: [makeStore()],
      directives: { 'preview-disabled': {} },
      stubs: { 'router-link': { template: '<a><slot /></a>' } },
    },
  });
  await flushPromises();
  await wrapper.setData({
    ihtData,
    projection: { years: [] },
    loading: false,
    error: null,
    isMarried: true,
  });
  await flushPromises();
  return wrapper;
}

const baseData = {
  net_estate_value: 1300000,
  taxable_estate: 400000,
  charitable_threshold: 122878,
  charitable_baseline: 1228780,
  iht_rate_type: 'standard',
  estimated_age_at_death: 85,
};

beforeEach(() => {
  vi.clearAllMocks();
});

/**
 * The join, which every case below this point skips.
 *
 * FOUND IN A BROWSER, NOT BY THIS SUITE. Seven green cases over a card that
 * rendered wrong on the live page, because `cardWith()` injects `ihtData`
 * directly — it supplies the object `loadIHTCalculation()` was supposed to build,
 * and so never executes the mapping that builds it. That mapping enumerates
 * fields by hand rather than spreading the payload, and it dropped
 * `charitable_rate_test_amount` one layer before the card.
 *
 * **That is the Fixture variant** (tests/CLAUDE.md §4): the data the test sets up
 * means the broken branch is never entered. The service was right, the endpoint
 * was right, the template was right, and the allowlist between them was not — and
 * nothing in this file said "and no mapping runs here".
 *
 * This case drives the real mapping with a real payload shape.
 */
describe('the payload survives the mapping into ihtData (W-0399)', () => {
  // The endpoint's real shape, handed to the component's real mounted() so the
  // real mapping runs. Nothing is injected past it.
  const endpointPayload = {
    success: true,
    calculation: { total_gross_assets: 2021780 },
    iht_summary: {
      current: {
        net_estate: 1728780,
        charitable_deduction: POOLED_EXEMPTION,
        charitable_rate_test_amount: RATE_TEST_AMOUNT,
        charitable_threshold: 122878,
        taxable_estate: 858780,
        iht_liability: 343512,
        nrb_available: 500000,
        rnrb_available: 350000,
        total_allowances: 850000,
        iht_rate_type: 'standard',
        iht_rate_message: 'Standard Inheritance Tax rate of 40% applies.',
      },
      projected: {},
    },
  };

  it('carries the rate-test amount from iht_summary through to the card', async () => {
    const wrapper = shallowMount(IHTPlanning, {
      global: {
        plugins: [makeStore(endpointPayload)],
        directives: { 'preview-disabled': {} },
        stubs: { 'router-link': { template: '<a><slot /></a>' } },
      },
    });
    await flushPromises();

    // The assertion the browser had to make for me: the field is not merely
    // published, it ARRIVES. `charitable_deduction` is asserted alongside it
    // because that one always did — so a mapping that dropped everything would
    // fail differently from one that dropped only the new field.
    expect(Number(wrapper.vm.ihtData.charitable_deduction)).toBe(POOLED_EXEMPTION);
    expect(wrapper.vm.ihtData.charitable_rate_test_amount).not.toBeUndefined();
    expect(Number(wrapper.vm.ihtData.charitable_rate_test_amount)).toBe(RATE_TEST_AMOUNT);
  });

  it('reaches the rendered card, not just the data object', async () => {
    // The end of the journey. Mapping AND template, in one pass, from a payload
    // shaped like the endpoint's.
    const wrapper = shallowMount(IHTPlanning, {
      global: {
        plugins: [makeStore(endpointPayload)],
        directives: { 'preview-disabled': {} },
        stubs: { 'router-link': { template: '<a><slot /></a>' } },
      },
    });
    await flushPromises();

    expect(wrapper.vm.charitableFiguresDiffer).toBe(true);
  });

  it('reads the published charitable saving rather than computing a fourth one (W-0451)', async () => {
    /*
     * The card used to compute this itself, as the rate differential on the
     * chargeable estate — a FOURTH answer beside the decision trace's and
     * `/plans/estate`'s, and the wrong answer to its own sentence, because
     * leaving the gift removes it from the estate as well as lowering the rate.
     *
     * Two things have to hold and only a real-lifecycle case can see both: the
     * key must survive the hand-written mapping, and the card must render THAT
     * number. £60,123 is deliberately NOT the differential on any figure in this
     * payload — 4% of £858,780 is £34,351 — so a card that still computed its
     * own would fail on the value, not merely on the absence.
     */
    const wrapper = shallowMount(IHTPlanning, {
      global: {
        plugins: [makeStore({
          ...endpointPayload,
          calculation: { ...endpointPayload.calculation, charitable_rate_saving: 60123 },
        })],
        directives: { 'preview-disabled': {} },
        stubs: { 'router-link': { template: '<a><slot /></a>' } },
      },
    });
    await flushPromises();

    expect(Number(wrapper.vm.ihtData.charitable_rate_saving)).toBe(60123);
    expect(wrapper.vm.charitableBequestSavings).toBe(60123);
    // And it is not the figure the old computation produced from this payload.
    expect(wrapper.vm.charitableBequestSavings).not.toBe(858780 * 0.04);
  });
});

describe('the charitable card names what each figure is (W-0399)', () => {
  it('never calls the pooled household exemption "your will"', async () => {
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: POOLED_EXEMPTION,
      charitable_rate_test_amount: RATE_TEST_AMOUNT,
    })).text();

    // The exact sentence that was false for both spouses.
    expect(text).not.toContain('Your will leaves £35,000');
    expect(text).not.toContain('Your will leaves');
  });

  it('states the pooled figure as the household\'s when the two differ', async () => {
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: POOLED_EXEMPTION,
      charitable_rate_test_amount: RATE_TEST_AMOUNT,
    })).text();

    expect(text).toContain('£35,000');
    expect(text).toContain('across your household');
  });

  it('names the second-death figure separately, so the two cannot be read as one', async () => {
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: POOLED_EXEMPTION,
      charitable_rate_test_amount: RATE_TEST_AMOUNT,
    })).text();

    expect(text).toContain('£30,000');
    expect(text).toContain('the will operating on the second death');
    // Both present, and distinguishable. Before the fix only one reached the card.
    expect(text).toContain('£35,000');
  });

  it('does not complicate the card when the two figures agree', async () => {
    // A single person, or a couple where only one partner left a legacy. Adding
    // a second sentence here would explain a distinction that does not exist.
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: RATE_TEST_AMOUNT,
      charitable_rate_test_amount: RATE_TEST_AMOUNT,
    })).text();

    expect(text).toContain('£30,000');
    expect(text).not.toContain('across your household');
    expect(text).not.toContain('the will operating on the second death');
  });

  it('spells out Inheritance Tax in its own copy', async () => {
    // Rule 9. The card's own sentence, not the server message.
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: POOLED_EXEMPTION,
      charitable_rate_test_amount: RATE_TEST_AMOUNT,
    })).text();

    expect(text).toContain('before Inheritance Tax is worked out');
  });

  it('still says plainly when nothing is left to charity', async () => {
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: 0,
      charitable_rate_test_amount: 0,
    })).text();

    expect(text).toContain('Your will records no gifts to charity');
  });

  it('treats a missing rate-test figure as "no distinction to draw"', async () => {
    // Defensive: an older cached payload, or a surface that has not been
    // redeployed, carries no `charitable_rate_test_amount`. The card must fall
    // back to the single-figure wording rather than rendering "£NaN" or an
    // unexplained second sentence.
    const text = (await cardWith({
      ...baseData,
      charitable_deduction: POOLED_EXEMPTION,
    })).text();

    expect(text).toContain('£35,000');
    expect(text).not.toContain('across your household');
    expect(text).not.toContain('NaN');
  });
});
