import { describe, it, expect } from 'vitest';
import CashOverview from '../CashOverview.vue';

/**
 * T65 (release ac prod walk, 2026-10-13): Maya's Fidelity Junior ISA showed in
 * Ada's "Cash ISAs" card. A Junior ISA is the child's money (GOV.UK); CSJ
 * 2026-10-13: its own card, out of the holder's totals.
 */
const accounts = [
  { id: 1, institution: 'Nationwide', account_type: 'savings_account', is_isa: false },
  { id: 2, institution: 'Marcus', account_type: 'cash_isa', is_isa: true, isa_type: 'cash' },
  { id: 3, institution: 'Fidelity', account_type: 'junior_isa', is_isa: true, isa_type: 'junior', beneficiary_name: 'Maya' },
  { id: 4, institution: 'Hargreaves', account_type: 'savings_account', is_isa: true, isa_type: 'junior_isa' },
];
const { isaAccounts, juniorIsaAccounts, juniorIsaAccountsTotal } = CashOverview.computed;

describe('CashOverview — a child\'s Junior ISA', () => {
  it('keeps Junior ISAs off the Cash ISAs card and on their own', () => {
    expect(isaAccounts.call({ accounts }).map((a) => a.id)).toEqual([2]);
    expect(juniorIsaAccounts.call({ accounts }).map((a) => a.id)).toEqual([3, 4]);
    expect(juniorIsaAccountsTotal.call({ groupTotals: { junior_isas: 4200 } })).toBe(4200);
  });
});
