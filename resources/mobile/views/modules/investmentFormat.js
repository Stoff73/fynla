// Shared formatting helpers for the mobile Investment views.

export { formatCurrency } from '../../utils/currency.js';

// Human-readable account-type label. Acronyms spelled out per CLAUDE.md Rule #9
// (only ISA stays abbreviated; never "S&S", "GIA", etc.).
const ACCOUNT_TYPE_LABELS = {
  stocks_and_shares_isa: 'Stocks & Shares ISA',
  cash_isa: 'Cash ISA',
  lifetime_isa: 'Lifetime ISA',
  innovative_finance_isa: 'Innovative Finance ISA',
  junior_isa: 'Junior ISA',
  gia: 'General Investment Account',
  general_investment_account: 'General Investment Account',
  investment_bond: 'Investment Bond',
  onshore_bond: 'Onshore Bond',
  offshore_bond: 'Offshore Bond',
  vct: 'Venture Capital Trust',
  eis: 'Enterprise Investment Scheme',
  seis: 'Seed Enterprise Investment Scheme',
  employee_share_scheme: 'Employee Share Scheme',
};

export function accountTypeLabel(account) {
  const type = account?.account_type;
  if (!type) return 'Investment account';
  if (type === 'other' && account.account_type_other) return account.account_type_other;
  if (ACCOUNT_TYPE_LABELS[type]) return ACCOUNT_TYPE_LABELS[type];
  return String(type)
    .replace(/_/g, ' ')
    .replace(/\bisa\b/gi, 'ISA')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function isIsaAccount(account) {
  return typeof account?.account_type === 'string' && account.account_type.includes('isa');
}
