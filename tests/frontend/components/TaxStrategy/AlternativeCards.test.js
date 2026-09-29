import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@/services/taxStrategyService', () => ({ default: {} }));

import { readFileSync } from 'fs';
import { resolve } from 'path';
import taxStrategy from '@/store/modules/taxStrategy';
import PlanItemAlternativeNote from '@/components/TaxStrategy/PlanItemAlternativeNote.vue';

// SaveTax matrix L3-5 (29 Sep 2026): the ISA wrap, the spouse gift and the
// 50/50 split shelter the same interest; the page listed them as separate
// savings that visibly added up to more than the plan total.
const note = 'Alternative to "Gift £50,000 of savings to your spouse" — compare before doing both. Not counted in your total.';

describe('alternative tax-plan items', () => {
  it('feeds the household panels from the composed plan, which carries the note', () => {
    const composedGift = { type: 'savings_to_spouse', category: 'household', counted_in_total: true, conflict_note: null };
    const composedSplit = { type: 'joint_savings_psa_split', category: 'household', counted_in_total: false, conflict_note: note };
    const state = {
      dashboard: {
        recommendations: [{ type: 'joint_savings_psa_split', category: 'household' }],
        composed_plan: { items: [composedGift, composedSplit, { type: 'isa_topup_vs_psa', category: 'allowance' }] },
      },
    };
    const getters = { composedPlan: taxStrategy.getters.composedPlan(state) };
    getters.recommendations = taxStrategy.getters.recommendations(state);

    expect(taxStrategy.getters.householdRecommendations(state, getters)).toEqual([composedGift, composedSplit]);
  });

  it('falls back to the calculator list when no composed plan is loaded', () => {
    const state = { dashboard: { recommendations: [{ type: 'savings_to_spouse', category: 'household' }] } };
    const getters = { composedPlan: null, recommendations: taxStrategy.getters.recommendations(state) };

    expect(taxStrategy.getters.householdRecommendations(state, getters)).toHaveLength(1);
  });

  it('shows the composer\'s note on an alternative, and nothing on an item without one', () => {
    expect(mount(PlanItemAlternativeNote, { props: { item: { conflict_note: note } } }).text()).toBe(note);
    expect(mount(PlanItemAlternativeNote, { props: { item: { conflict_note: null } } }).html()).not.toContain('<p');
  });

  it.each([
    'resources/js/components/TaxStrategy/StrategyRecommendationList.vue',
    'resources/js/components/TaxStrategy/AssetShiftingPanel.vue',
    'resources/js/components/TaxStrategy/HouseholdCoordinationPanel.vue',
  ])('%s shows the note on every plan item', (file) => {
    expect(readFileSync(resolve(process.cwd(), file), 'utf8')).toContain('<PlanItemAlternativeNote');
  });

  it('/m shows the note on both its household and individual lists', () => {
    const src = readFileSync(resolve(process.cwd(), 'resources/mobile/views/TaxStrategy.vue'), 'utf8');

    expect(src.match(/class="mts-rec__alt">\{\{ rec\.conflict_note \}\}/g)).toHaveLength(2);
  });
});
