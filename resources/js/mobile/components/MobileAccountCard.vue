<template>
  <div class="px-4 py-3">
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <h4 class="text-sm font-bold text-horizon-500 truncate">{{ account.provider || account.platform || account.name || 'Account' }}</h4>
          <span
            v-if="typeBadge"
            class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
            :class="typeBadgeClass"
          >
            {{ typeBadge }}
          </span>
        </div>
        <p v-if="account.account_name && account.account_name !== account.provider" class="text-xs text-neutral-500 mt-0.5 truncate">
          {{ account.account_name }}
        </p>
      </div>
      <div class="text-right ml-3">
        <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(account.balance || account.current_value || account.value || 0) }}</p>
        <p v-if="secondaryMetric" class="text-xs text-neutral-500 mt-0.5">{{ secondaryMetric }}</p>
      </div>
    </div>
    <div v-if="detailChips.length" class="flex flex-wrap gap-2 mt-2">
      <span
        v-for="chip in detailChips"
        :key="chip"
        class="text-xs text-neutral-400"
      >
        {{ chip }}
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'MobileAccountCard',

  mixins: [currencyMixin],

  props: {
    account: { type: Object, required: true },
    variant: {
      type: String,
      default: 'savings',
      validator: (v) => ['savings', 'investment'].includes(v),
    },
  },

  computed: {
    typeBadge() {
      const type = (this.account.account_type || this.account.type || '').toLowerCase();
      if (type.includes('isa')) return 'ISA';
      if (type.includes('sipp')) return 'SIPP';
      if (type.includes('gia')) return 'GIA';
      if (type.includes('lisa')) return 'LISA';
      if (type.includes('junior')) return 'JISA';
      return null;
    },

    typeBadgeClass() {
      const type = (this.account.account_type || this.account.type || '').toLowerCase();
      if (type.includes('isa')) return 'bg-light-blue-100 text-light-blue-700';
      if (type.includes('sipp')) return 'bg-violet-50 text-violet-500';
      if (type.includes('gia')) return 'bg-savannah-100 text-horizon-500';
      return 'bg-savannah-100 text-horizon-500';
    },

    secondaryMetric() {
      if (this.variant === 'savings' && this.account.interest_rate) {
        return `${this.account.interest_rate}% AER`;
      }
      if (this.variant === 'investment') {
        const count = this.account.holdings?.length || this.account.holdings_count || 0;
        return `${count} holding${count !== 1 ? 's' : ''}`;
      }
      return null;
    },

    detailChips() {
      const chips = [];
      if (this.account.ownership_type && this.account.ownership_type !== 'individual') {
        chips.push(this.account.ownership_type === 'joint' ? 'Joint' : this.account.ownership_type);
      }
      if (this.variant === 'investment' && this.account.risk_level) {
        chips.push(`Risk: ${this.account.risk_level}`);
      }
      if (this.variant === 'savings' && this.account.access_type) {
        const labels = { easy_access: 'Easy access', notice: 'Notice', fixed_rate: 'Fixed rate' };
        chips.push(labels[this.account.access_type] || this.account.access_type);
      }
      return chips;
    },
  },
};
</script>
