<template>
  <div class="income-source-card">
    <span :class="['source-badge', typeBadgeClass]">{{ sourceTypeLabel }}</span>
    <h5 class="source-name">{{ allocation.name || accountName }}</h5>
    <p class="fund-value-label">Projected fund value</p>
    <p class="fund-value">{{ formatCurrency(maxAmount) }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IncomeSourceSlider',

  mixins: [currencyMixin],

  props: {
    allocation: {
      type: Object,
      required: true,
    },
    account: {
      type: Object,
      default: null,
    },
  },

  computed: {
    sourceType() {
      return this.allocation.source_type || '';
    },

    sourceTypeLabel() {
      const labels = {
        pension_pot_pcls: 'Pension Commencement Lump Sum',
        pension_pot_drawdown: 'Pension Pot',
        dc_pension_pcls: 'Pension Commencement Lump Sum',
        dc_pension_drawdown: 'Pension',
        db_pension: 'Defined Benefit Pension',
        state_pension: 'State Pension',
        isa: 'ISA',
        gia: 'General Investment Account',
        onshore_bond: 'Onshore Bond',
        offshore_bond: 'Offshore Bond',
        bond: 'Bond',
        savings: 'Savings',
      };
      return labels[this.sourceType] || 'Income';
    },

    typeBadgeClass() {
      if (this.sourceType.includes('pcls')) return 'badge-pcls';
      if (this.sourceType.includes('pension_pot')) return 'badge-pension';
      if (this.sourceType.includes('pension')) return 'badge-pension';
      if (this.sourceType === 'isa') return 'badge-isa';
      if (this.sourceType === 'gia') return 'badge-gia';
      if (this.sourceType.includes('bond')) return 'badge-bond';
      return 'badge-default';
    },

    accountName() {
      return this.account?.name || this.allocation.name || 'Income Source';
    },

    maxAmount() {
      // For PCLS, use the pcls_available from account or allocation max
      if (this.sourceType === 'pension_pot_pcls' || this.sourceType === 'dc_pension_pcls') {
        return this.account?.pcls_available || this.allocation.max_amount || 0;
      }
      // For pension drawdown, show the taxable portion (total minus PCLS)
      if (this.sourceType === 'pension_pot_drawdown' || this.sourceType === 'dc_pension_drawdown') {
        if (this.allocation.max_amount) {
          return this.allocation.max_amount;
        }
        const total = this.account?.value || 0;
        const pcls = this.account?.pcls_available || total * 0.25;
        return total - pcls;
      }
      // For other sources, use the account value or allocation max
      return this.account?.value || this.allocation.max_amount || 0;
    },
  },
};
</script>

<style scoped>
.income-source-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  @apply border border-light-gray;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.income-source-card:hover {
  @apply border-horizon-300;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.source-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  @apply text-neutral-500;
  margin-bottom: 4px;
}

.source-name {
  font-size: 15px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 12px 0;
}

.fund-value-label {
  font-size: 12px;
  @apply text-neutral-500;
  margin: 0 0 4px 0;
}

.fund-value {
  font-size: 22px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}
</style>
