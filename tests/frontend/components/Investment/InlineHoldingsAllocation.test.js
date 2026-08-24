import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import InlineHoldingsEditor from '@/components/Investment/InlineHoldingsEditor.vue';
import {
  allocationTotal,
  allocationExcess,
  isOverAllocated,
  allocationErrorMessage,
} from '@/utils/holdingsAllocation';

/**
 * W-0257 — an account whose holdings exceeded 100% could not be saved and nothing
 * said why.
 *
 * Each allocation input carried `max = 100 − (sum of the OTHER holdings)`. While
 * the total sat at exactly 100 that is self-consistent and invisible: each max
 * equals that field's own current value. One digit past it and EVERY input is
 * below its own value, the browser refuses to fire submit, and the Update button
 * appears simply not to work — no request, no message, no route to recovery.
 *
 * TEST DESIGN — `tests/CLAUDE.md` §4, all four variants:
 *
 * - **Collision.** The trap here is asserting on an account already over 100%.
 *   The pre-fix and post-fix code disagree about a VALID account too: at a total
 *   of exactly 100, the old max for the 36.90 holding was 36.9 and the new one is
 *   100. So the sharpest probe is the ordinary case — a user at 100% raising one
 *   holding before lowering another, which is the interaction that actually
 *   bricked the form. Asserting only "105% shows an error" would pass against a
 *   version that still refuses to submit at 100.1%.
 *
 * - **Clamp.** `remainingPercent` is `Math.max(0, 100 − total)`, so over-allocated
 *   and exactly-full both render as "nothing left over" and the cash row simply
 *   vanishes. The excess is the quantity that clamp discards, so it is asserted
 *   directly rather than through anything derived from `remainingPercent`.
 *
 * - **Fixture.** `peak_earners` account 26 is three holdings summing to exactly
 *   100 — symmetric enough to hide a rounding bug. The fixtures below add a
 *   float-noise case (36.9 + 36.8 + 26.3 evaluates to 100.00000000000001 in
 *   binary), blank and string allocations, and a single-holding account.
 *
 * - **Mock.** Nothing is mocked. The component is mounted and the real DOM
 *   attribute is read.
 */

/** The real account 26 shape, verified in the database. */
const ACCOUNT_26 = [
  { id: 65, security_name: 'Fundsmith Equity', asset_type: 'fund', allocation_percent: 36.8, cost_basis: null },
  { id: 66, security_name: 'Scottish Mortgage Investment Trust', asset_type: 'fund', allocation_percent: 26.3, cost_basis: null },
  { id: 67, security_name: 'Vanguard FTSE All-World', asset_type: 'etf', allocation_percent: 36.9, cost_basis: null },
];

const mountEditor = (holdings, accountValue = 95000) =>
  mount(InlineHoldingsEditor, {
    props: { holdings, accountValue, accountId: 26 },
  });

describe('holdingsAllocation — the rule, in one place', () => {
  it('sums allocations, treating blank and unparseable entries as zero', () => {
    expect(allocationTotal(ACCOUNT_26)).toBeCloseTo(100, 6);
    expect(allocationTotal([
      { allocation_percent: 40 },
      { allocation_percent: null },
      { allocation_percent: '' },
      { allocation_percent: '25.5' },
      { allocation_percent: 'not a number' },
    ])).toBeCloseTo(65.5, 6);
    expect(allocationTotal([])).toBe(0);
    expect(allocationTotal(undefined)).toBe(0);
  });

  it('does not call binary float noise an over-allocation', () => {
    // 68.18 + 31.76 + 0.06 === 100.00000000000001 in IEEE 754 — a completely
    // ordinary two-fund portfolio with a cash sliver, and one of many such sets.
    // A naive `> 100` would refuse to save an account that is entirely correct:
    // the same disease as the bug this fixes, a wrong answer delivered politely.
    const noisy = [
      { allocation_percent: 68.18 },
      { allocation_percent: 31.76 },
      { allocation_percent: 0.06 },
    ];

    expect(allocationTotal(noisy)).toBeGreaterThan(100);
    expect(isOverAllocated(noisy)).toBe(false);
    expect(allocationErrorMessage(noisy)).toBeNull();

    // And the real persona account, which sums to exactly 100.
    expect(isOverAllocated(ACCOUNT_26)).toBe(false);
    expect(allocationErrorMessage(ACCOUNT_26)).toBeNull();
  });

  it('still catches a real over-allocation just past the tolerance', () => {
    // The tolerance must absorb float noise without absorbing a genuine mistake.
    // 0.02 over is far beyond the ~1e-14 that binary addition introduces.
    const over = [{ allocation_percent: 60 }, { allocation_percent: 40.02 }];

    expect(isOverAllocated(over)).toBe(true);
    expect(allocationExcess(over)).toBeCloseTo(0.02, 6);
  });

  it('measures the excess the "remaining" clamp throws away', () => {
    const over = [...ACCOUNT_26, { allocation_percent: 5 }];

    // What the clamped view reports for both: nothing left over.
    expect(Math.max(0, 100 - allocationTotal(ACCOUNT_26))).toBe(0);
    expect(Math.max(0, 100 - allocationTotal(over))).toBe(0);

    // What is still visible on the other side of the clamp.
    expect(allocationExcess(ACCOUNT_26)).toBe(0);
    expect(allocationExcess(over)).toBeCloseTo(5, 6);
  });

  it('names the total, the target and the difference', () => {
    const message = allocationErrorMessage([...ACCOUNT_26, { allocation_percent: 5 }]);

    // Not merely "invalid" — a message that does not say which number to change,
    // or by how much, is the dead button with extra steps.
    expect(message).toContain('105%');
    expect(message).toContain('5%');
    expect(message).toContain('100%');
  });
});

describe('InlineHoldingsEditor — allocation inputs (W-0257)', () => {
  it('bounds each allocation input at 100, not at the headroom left by the others', async () => {
    // THE regression assertion. Pre-fix, a valid 100% account rendered
    // max="36.9" on the 36.90 holding — its own value — so the user could not
    // raise it by so much as 0.1 without bricking the form. Post-fix every input
    // reads 100.
    const wrapper = mountEditor(ACCOUNT_26);
    const maxes = wrapper.findAll('input[type="number"][max]').map((i) => i.attributes('max'));

    expect(maxes.length).toBeGreaterThanOrEqual(3);
    expect(new Set(maxes)).toEqual(new Set(['100']));
    expect(maxes).not.toContain('36.9');
  });

  it('lets a user raise one holding before lowering another', async () => {
    // The ordinary interaction that produced a silently dead Update button. The
    // input must remain valid against its own attributes while the set is
    // temporarily over 100 — otherwise the browser blocks submit before any code
    // of ours runs.
    const wrapper = mountEditor(ACCOUNT_26);
    const input = wrapper.findAll('input[type="number"][max]')[2];

    await input.setValue(40);

    expect(input.element.validity.rangeOverflow).toBe(false);
    expect(input.element.checkValidity()).toBe(true);
  });

  it('says so, visibly, when the holdings total more than the account', async () => {
    const wrapper = mountEditor([...ACCOUNT_26, { allocation_percent: 5, security_name: 'Baillie Gifford Managed', asset_type: 'fund' }]);
    const alert = wrapper.find('[data-testid="holdings-over-allocated"]');

    expect(alert.exists()).toBe(true);
    expect(alert.text()).toContain('105%');
  });

  it('shows nothing at all when the holdings are valid', () => {
    expect(mountEditor(ACCOUNT_26).find('[data-testid="holdings-over-allocated"]').exists()).toBe(false);
    expect(mountEditor([{ allocation_percent: 60 }]).find('[data-testid="holdings-over-allocated"]').exists()).toBe(false);
    expect(mountEditor([]).find('[data-testid="holdings-over-allocated"]').exists()).toBe(false);
  });
});
