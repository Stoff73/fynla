// The one /m currency formatter: whole pounds, en-GB, a dash for nothing.
export function formatCurrency(value) {
  if (value == null || value === '' || Number.isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}
