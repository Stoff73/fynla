<template>
  <div v-if="status" class="isa-history">
    <div class="isa-history__summary">
      <span>{{ status.tax_year }}</span>
      <span>{{ fmt(displayTotal) }} contributed</span>
    </div>
    <p v-if="!rows.length" class="m-sub" style="margin-bottom:0">No recorded ISA contributions for this tax year.</p>
    <div v-else>
      <section v-for="row in rows" :key="`${row.account_type}-${row.account_id}`" class="isa-history__account">
        <div class="isa-history__head">
          <div>
            <p class="isa-history__name">{{ row.account_name }}</p>
            <p class="isa-history__owner">{{ row.owner?.name || row.owner?.label || 'Owner unavailable' }} · {{ isaLabel(row.isa_type) }}</p>
          </div>
          <span class="isa-history__amount">{{ fmt(row.contributed) }}</span>
        </div>
        <p class="isa-history__provenance">{{ provenanceLabel(row.provenance) }}</p>
        <div v-if="row.contributions?.length" class="isa-history__entries">
          <div v-for="(entry, index) in row.contributions" :key="entry.id || index" class="isa-history__entry">
            <span>{{ entry.date ? date(entry.date) : 'Annual summary' }}</span>
            <span>{{ fmt(entry.amount) }}</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
function currency(value) {
  if (value == null || Number.isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', {
    style: 'currency', currency: 'GBP', maximumFractionDigits: 0,
  }).format(Number(value));
}

export default {
  name: 'ISAContributionHistory',
  props: {
    status: { type: Object, default: null },
    accountId: { type: [Number, String], default: null },
    accountClass: { type: String, default: null },
  },
  computed: {
    allRows() {
      return (this.status?.owners || []).flatMap((owner) => owner.account_breakdown || []);
    },
    rows() {
      return this.allRows.filter((row) => {
        if (this.accountId != null && String(row.account_id) !== String(this.accountId)) return false;
        if (this.accountClass === 'savings') return String(row.account_type || '').endsWith('SavingsAccount');
        if (this.accountClass === 'investment') return String(row.account_type || '').endsWith('InvestmentAccount');
        return true;
      });
    },
    displayTotal() {
      if (this.accountId != null || this.accountClass) {
        return this.rows.reduce((sum, row) => sum + Number(row.contributed || 0), 0);
      }
      return Number(this.status?.total_used || 0);
    },
  },
  methods: {
    fmt: currency,
    date(value) {
      const parsed = new Date(value);
      return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    isaLabel(type) {
      return {
        cash_isa: 'Cash ISA',
        stocks_and_shares_isa: 'Stocks & Shares ISA',
        lifetime_isa: 'Lifetime ISA',
      }[type] || String(type || 'ISA').replace(/_/g, ' ');
    },
    provenanceLabel(provenance) {
      return {
        recorded_ledger: 'Recorded contribution ledger',
        legacy_annual_summary: 'Annual summary from the account record',
        legacy_current_year_summary: 'Annual summary from the account record',
      }[provenance] || 'Recorded account data';
    },
  },
};
</script>

<style scoped>
.isa-history__summary { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; color: var(--horizon-500); font-size: 13px; font-weight: 700; }
.isa-history__account { padding: 12px 0; border-bottom: 1px solid var(--horizon-100); }
.isa-history__account:first-child { padding-top: 2px; }
.isa-history__account:last-child { border-bottom: 0; padding-bottom: 0; }
.isa-history__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.isa-history__name { margin: 0; font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.isa-history__owner, .isa-history__provenance { margin: 3px 0 0; font-size: 11px; color: var(--neutral-500); }
.isa-history__amount { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.isa-history__entries { margin-top: 7px; }
.isa-history__entry { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; font-size: 12px; color: var(--neutral-500); }
</style>
