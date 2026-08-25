import { describe, expect, it } from 'vitest';
import LetterToSpouse from '@/components/UserProfile/LetterToSpouse.vue';

/**
 * W-0421 — the exported document is the half of this defect that outlives a fix.
 *
 * The screen can be reloaded; a PDF a widow has saved, printed or emailed to a
 * solicitor cannot. So the printed path gets its own coverage rather than being
 * assumed to follow the cards: before this change it had a `switch (type)` of its
 * own, naming a different raw column per section — `current_balance`,
 * `current_value`, `sum_assured` — which is why the two could disagree at all.
 *
 * These cases call the component's own print builder with a recording
 * `formatCurrency`, so the assertion is about **which number reaches the
 * document**, not about how it is formatted. A build that reverted to the raw
 * columns would hand it `undefined`, and a build that reverted to the full record
 * would hand it the co-owner's money — both visible here.
 */
const buildContext = () => {
  const amounts = [];

  return {
    amounts,
    ...LetterToSpouse.methods,
    formatCurrency(value) {
      amounts.push(value);
      return `[${value}]`;
    },
  };
};

// A tenants-in-common property whose co-owner holds 60% and has no account here.
const strangerShared = {
  id: 20,
  name: 'Unit 12, Victoria Mill',
  subtext: 'Buy to Let',
  value: 118000,
  full_value: 295000,
  ownership_type: 'tenants_in_common',
  ownership_percentage: 40,
  mortgage_balance: 48000,
};

const whollyOwned = {
  id: 21,
  name: 'Hargreaves Lansdown',
  subtext: 'Hargreaves Lansdown',
  value: 95000,
  full_value: 95000,
  ownership_type: 'individual',
  ownership_percentage: 100,
  // The value `investment_accounts.account_type` actually holds. The badge
  // used to be tested against `stocks_and_shares_isa`, which is an `isa_type`
  // value this column never carries, so it had never once fired.
  account_type: 'isa',
};

describe('the exported document states the reader’s share', () => {
  it('prints the user share as the card amount, never the whole record', () => {
    const context = buildContext();

    const html = context.buildFinancialHtml.call(
      context,
      'Properties',
      [strangerShared],
      118000,
      'properties',
    );

    // The share reached the document; the whole property did so only as the
    // explicit "your share of" note, never as the headline figure.
    expect(context.amounts).toContain(118000);
    expect(html).toContain('[118000]');
    expect(html).not.toContain('<div class="card-amount ">[295000]</div>');

    // A reverted build would pass `undefined` here, which formats to a blank and
    // reads as a property worth nothing.
    expect(context.amounts).not.toContain(undefined);

    // And the rest of the card still arrives — an empty section and a fixed
    // defect look identical.
    expect(html).toContain('Unit 12, Victoria Mill');
    expect(html).toContain('Buy to Let');
  });

  it('discloses the whole record beside a share, and not beside a wholly owned one', () => {
    const context = buildContext();

    const shared = context.buildFinancialHtml.call(context, 'Properties', [strangerShared], 118000, 'properties');
    const sole = context.buildFinancialHtml.call(context, 'Investments', [whollyOwned], 95000, 'investments');

    expect(shared).toContain('Your share of [295000]');
    expect(sole).not.toContain('Your share of');
  });

  it('prints the section total it was given rather than re-adding the items', () => {
    const context = buildContext();

    const html = context.buildFinancialHtml.call(
      context,
      'Properties',
      [strangerShared, { ...whollyOwned, value: 212500, full_value: 425000 }],
      330500,
      'properties',
    );

    expect(html).toContain('[330500]');
  });

  it('renders nothing at all for an empty section', () => {
    const context = buildContext();

    expect(context.buildFinancialHtml.call(context, 'Properties', [], 0, 'properties')).toBe('');
    expect(context.buildFinancialHtml.call(context, 'Properties', null, 0, 'properties')).toBe('');
  });
});

describe('the badges are decided once for both surfaces', () => {
  it('spells out tenants in common on screen as well as in print', () => {
    const context = buildContext();

    const badges = context.itemBadges.call(context, strangerShared, 'properties');
    const ownership = badges.find(badge => badge.print === 'badge-purple');

    // The printed document already spelled this out; the screen said "TIC".
    expect(ownership.label).toBe('Tenants in Common');
    expect(ownership.screen).toContain('violet');
  });

  it('marks a stocks and shares ISA on an investment account', () => {
    const context = buildContext();

    const labels = context.itemBadges.call(context, whollyOwned, 'investments').map(badge => badge.label);

    expect(labels).toContain('ISA');
    expect(labels).not.toContain('Joint');
  });

  it('still accepts the isa_type spelling, which is treated as the same thing elsewhere', () => {
    const context = buildContext();
    const legacy = { ...whollyOwned, account_type: 'stocks_and_shares_isa' };

    expect(context.itemBadges.call(context, legacy, 'investments').map(b => b.label)).toContain('ISA');
  });

  it('marks a general investment account, and does not call it an ISA', () => {
    const context = buildContext();
    const gia = { ...whollyOwned, account_type: 'gia' };

    const labels = context.itemBadges.call(context, gia, 'investments').map(b => b.label);

    expect(labels).toContain('General Investment Account');
    expect(labels).not.toContain('ISA');
  });

  it('does not mark a cash ISA badge on a section that is not savings', () => {
    const context = buildContext();
    const cashIsa = { ...whollyOwned, account_type: 'cash_isa', is_isa: true };

    expect(context.itemBadges.call(context, cashIsa, 'savings').map(b => b.label)).toContain('ISA');
    expect(context.itemBadges.call(context, cashIsa, 'liabilities').map(b => b.label)).not.toContain('ISA');
  });
});

describe('a record is shared only when the share differs from the whole', () => {
  it('reads the difference rather than re-deriving the ownership rule', () => {
    const context = buildContext();

    expect(context.isSharedItem.call(context, strangerShared)).toBe(true);
    expect(context.isSharedItem.call(context, whollyOwned)).toBe(false);
    // Pensions and protection carry no full_value; they must not be called shared.
    expect(context.isSharedItem.call(context, { value: 180000 })).toBe(false);
    expect(context.isSharedItem.call(context, { value: 180000, full_value: null })).toBe(false);
  });
});
