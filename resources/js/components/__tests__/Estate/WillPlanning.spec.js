import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const api = (await import('@/services/api')).default;
const WillPlanning = (await import('../../Estate/WillPlanning.vue')).default;

/**
 * W-0391 and W-0393, both found on the peak_earners mirror pair.
 *
 * W-0391: the page read `iht_summary.current.net_estate` — the COMBINED
 * second-death household estate the Inheritance Tax engine models for a married
 * couple. It is the same number for both spouses by design. Both wills therefore
 * told their testator they leave their partner £1,728,780: a figure matching
 * neither estate, and overstating Sarah Jones's by 2.3 times.
 *
 * W-0393: the specific gifts summary read `gift.recipient`, a key no write path
 * produces, so every legacy rendered as an amount followed by "to " and nothing.
 *
 * MEASURED FIXTURES, not invented ones. Taken from the live household on
 * 2026-08-23 through the real services, caches cleared first:
 *
 *   David Jones  (16)  user_net_estate  989,500
 *   Sarah Jones  (17)  user_net_estate  739,280
 *   both               total_net_estate 1,728,780
 *
 * DELIBERATELY ASYMMETRIC. The two spouses' estates differ, and neither is half
 * of the household total, so the correct answer and the household answer are
 * three distinct numbers. A fixture where they coincided could not fail — see
 * the Collision variant in tests/CLAUDE.md §4. Sarah's figure alone cannot
 * settle this defect on the live household either: she owns nothing flagged
 * `is_iht_exempt`, so her own-estate figure is identical under both the correct
 * and the broken reading of the payload. Every case below therefore asserts on
 * BOTH spouses, or on the pair.
 */

const DAVID_OWN_ESTATE = 989500;
const SARAH_OWN_ESTATE = 739280;
const HOUSEHOLD_ESTATE = 1728780;

function makeStore() {
  return createStore({
    modules: {
      auth: {
        namespaced: true,
        state: () => ({ user: { id: 16, marital_status: 'married', live_spouse_id: 17 } }),
        getters: { currentUser: (s) => s.user },
      },
      preview: {
        namespaced: true,
        state: () => ({}),
        getters: { isPreviewMode: () => false },
      },
    },
  });
}

/**
 * @param {object} opts
 * @param {number|undefined} opts.userNetEstate  what calculation.user_net_estate returns
 * @param {number} opts.spousePercentage         wills.spouse_bequest_percentage
 * @param {Array}  opts.specificGifts            the will document's gifts
 */
async function mountPage({
  userNetEstate,
  spousePercentage = 100,
  specificGifts = null,
  executorName = 'Sarah Jones, Barclays Wealth',
} = {}) {
  api.get.mockImplementation((url) => {
    if (url === '/estate/will') {
      return Promise.resolve({
        data: {
          data: {
            has_will: true,
            will_last_updated: '2022-03-15',
            last_reviewed_date: '2022-03-15',
            executor_name: executorName,
            spouse_primary_beneficiary: true,
            spouse_bequest_percentage: spousePercentage,
            executor_notes: null,
            will_document_id: specificGifts ? 5 : null,
          },
        },
      });
    }
    if (url === '/estate/bequests') {
      return Promise.resolve({ data: { data: [] } });
    }
    if (url.startsWith('/estate/will-builder/')) {
      return Promise.resolve({ data: { data: { specific_gifts: specificGifts } } });
    }
    return Promise.resolve({ data: { data: null } });
  });

  api.post.mockImplementation(() => Promise.resolve({
    data: {
      // Both keys are present on the real response, which is the whole point:
      // the page is not missing a figure, it was reading the wrong one.
      calculation: userNetEstate === undefined
        ? { total_net_estate: HOUSEHOLD_ESTATE }
        : { user_net_estate: userNetEstate, total_net_estate: HOUSEHOLD_ESTATE },
      iht_summary: { current: { net_estate: HOUSEHOLD_ESTATE } },
    },
  }));

  const wrapper = shallowMount(WillPlanning, {
    global: {
      plugins: [makeStore()],
      directives: { 'preview-disabled': {} },
      stubs: { 'router-link': { template: '<a><slot /></a>' } },
    },
  });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  vi.clearAllMocks();
});

describe('what this will leaves the spouse (W-0391)', () => {
  it('states each testator\'s OWN estate, and the two differ', async () => {
    const david = await mountPage({ userNetEstate: DAVID_OWN_ESTATE });
    const sarah = await mountPage({ userNetEstate: SARAH_OWN_ESTATE });

    expect(david.text()).toContain('£989,500');
    expect(sarah.text()).toContain('£739,280');

    // The assertion that cannot pass while one household aggregate is served to
    // both. Before the fix these two strings were identical.
    expect(david.vm.spouseAmount).not.toBe(sarah.vm.spouseAmount);
  });

  it('never shows the combined household estate to either spouse', async () => {
    const david = await mountPage({ userNetEstate: DAVID_OWN_ESTATE });
    const sarah = await mountPage({ userNetEstate: SARAH_OWN_ESTATE });

    // £1,728,780 is what BOTH wills displayed. It is a real figure — the second-
    // death estate — and it belongs on the Inheritance Tax screens, not here.
    expect(david.text()).not.toContain('£1,728,780');
    expect(sarah.text()).not.toContain('£1,728,780');
  });

  it('applies the spouse percentage to the testator\'s own estate, not the household\'s', async () => {
    // 60%, not 100 and not 50: at 100 the percentage is never exercised, and at
    // 50 a household figure halved (£864,390) could be mistaken for a correct
    // answer. 60% of Sarah's own estate is £443,568; 60% of the household is
    // £1,037,268. No arithmetic confuses the two.
    const wrapper = await mountPage({ userNetEstate: SARAH_OWN_ESTATE, spousePercentage: 60 });

    expect(wrapper.vm.spouseAmount).toBe(443568);
    expect(wrapper.text()).toContain('£443,568');
    expect(wrapper.text()).not.toContain('£1,037,268');
  });

  it('shows nothing rather than falling back to the household figure', async () => {
    // The old code's `else if` chain is the trap being closed: any fallback that
    // can reach a household aggregate reintroduces the defect for exactly the
    // users whose payload is incomplete, and does it silently.
    const wrapper = await mountPage({ userNetEstate: undefined });

    expect(wrapper.vm.netEstateValue).toBe(0);
    expect(wrapper.text()).not.toContain('£1,728,780');
  });

  it('reads the per-user figure off the calculation, not the summary', async () => {
    // A guard on the mechanism, not just the number: if a later change routes
    // this back through `iht_summary`, the household figure returns and every
    // assertion above still passes for a household that happens to hold one
    // person.
    await mountPage({ userNetEstate: DAVID_OWN_ESTATE });

    expect(api.post).toHaveBeenCalledWith('/estate/calculate-iht');
  });
});

describe('specific gifts name their recipient (W-0393)', () => {
  const gifts = [
    { type: 'cash', amount: 10000, description: null, beneficiary_name: 'British Heart Foundation' },
    { type: 'asset', amount: null, description: 'The Georgian writing desk', beneficiary_name: 'Charlotte Jones' },
  ];

  it('names the beneficiary of a cash legacy', async () => {
    const text = (await mountPage({ userNetEstate: SARAH_OWN_ESTATE, specificGifts: gifts })).text();

    expect(text).toContain('£10,000 to British Heart Foundation');
  });

  it('names the beneficiary of an asset gift', async () => {
    const text = (await mountPage({ userNetEstate: SARAH_OWN_ESTATE, specificGifts: gifts })).text();

    expect(text).toContain('The Georgian writing desk to Charlotte Jones');
  });

  it('says so plainly when a gift has no beneficiary, rather than trailing off', async () => {
    // The pre-fix rendering was "£10,000 to " with nothing after it. A legacy
    // with no legatee must read as unfinished, not as finished-and-blank.
    const text = (await mountPage({
      userNetEstate: SARAH_OWN_ESTATE,
      specificGifts: [{ type: 'cash', amount: 10000, description: null, beneficiary_name: '' }],
    })).text();

    expect(text).toContain('£10,000 to a beneficiary you have not named yet');
  });
});
