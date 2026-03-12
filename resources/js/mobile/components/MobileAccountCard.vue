<template>
  <div class="px-4 py-3 cursor-pointer active:bg-savannah-100 transition-colors" @click="expanded = !expanded">
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
      <div class="flex items-center gap-2 ml-3">
        <div class="text-right">
          <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(account.current_balance || account.balance || account.current_value || account.value || 0) }}</p>
          <p v-if="secondaryMetric" class="text-xs text-neutral-500 mt-0.5">{{ secondaryMetric }}</p>
        </div>
        <svg
          class="w-4 h-4 text-neutral-400 transition-transform flex-shrink-0"
          :class="{ 'rotate-180': expanded }"
          fill="none" viewBox="0 0 24 24" stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>
    <div v-if="detailChips.length && !expanded" class="flex flex-wrap gap-2 mt-2">
      <span
        v-for="chip in detailChips"
        :key="chip"
        class="text-xs text-neutral-400"
      >
        {{ chip }}
      </span>
    </div>

    <!-- Expanded detail section -->
    <div v-if="expanded" class="mt-3 pt-3 border-t border-light-gray space-y-2">
      <!-- Savings details -->
      <template v-if="variant === 'savings'">
        <div v-if="account.interest_rate" class="flex justify-between text-xs">
          <span class="text-neutral-500">Interest rate</span>
          <span class="text-horizon-500 font-medium">{{ account.interest_rate }}% AER</span>
        </div>
        <div v-if="account.access_type" class="flex justify-between text-xs">
          <span class="text-neutral-500">Access type</span>
          <span class="text-horizon-500 font-medium">{{ accessTypeLabel }}</span>
        </div>
        <div v-if="account.is_emergency_fund" class="flex justify-between text-xs">
          <span class="text-neutral-500">Emergency fund</span>
          <span class="text-spring-500 font-medium">Yes</span>
        </div>
        <div v-if="account.is_isa" class="flex justify-between text-xs">
          <span class="text-neutral-500">ISA</span>
          <span class="text-spring-500 font-medium">Yes</span>
        </div>
        <div v-if="account.maturity_date" class="flex justify-between text-xs">
          <span class="text-neutral-500">Maturity date</span>
          <span class="text-horizon-500 font-medium">{{ account.maturity_date }}</span>
        </div>
        <div v-if="account.ownership_type && account.ownership_type !== 'individual'" class="flex justify-between text-xs">
          <span class="text-neutral-500">Ownership</span>
          <span class="text-horizon-500 font-medium">{{ ownershipLabel }} ({{ account.ownership_percentage || 50 }}%)</span>
        </div>
      </template>

      <!-- Investment details -->
      <template v-if="variant === 'investment'">
        <div v-if="account.risk_level" class="flex justify-between text-xs">
          <span class="text-neutral-500">Risk level</span>
          <span class="text-horizon-500 font-medium">{{ account.risk_level }}</span>
        </div>
        <div v-if="account.annual_fee != null" class="flex justify-between text-xs">
          <span class="text-neutral-500">Annual fee</span>
          <span class="text-horizon-500 font-medium">{{ account.annual_fee }}%</span>
        </div>
        <div v-if="account.ownership_type && account.ownership_type !== 'individual'" class="flex justify-between text-xs">
          <span class="text-neutral-500">Ownership</span>
          <span class="text-horizon-500 font-medium">{{ ownershipLabel }} ({{ account.ownership_percentage || 50 }}%)</span>
        </div>
        <!-- Holdings list -->
        <div v-if="account.holdings && account.holdings.length" class="mt-2">
          <p class="text-xs font-semibold text-horizon-500 mb-1.5">Holdings</p>
          <div class="space-y-1.5">
            <div
              v-for="holding in account.holdings"
              :key="holding.id"
              class="flex justify-between text-xs"
            >
              <span class="text-neutral-500 truncate flex-1 mr-2">{{ holding.security_name || holding.name || 'Holding' }}</span>
              <span class="text-horizon-500 font-medium flex-shrink-0">{{ formatCurrency(holding.current_value || holding.value || 0) }}</span>
            </div>
          </div>
        </div>
      </template>
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

  data() {
    return { expanded: false };
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

    accessTypeLabel() {
      const labels = { easy_access: 'Easy access', notice: 'Notice', fixed_rate: 'Fixed rate' };
      return labels[this.account.access_type] || this.account.access_type;
    },

    ownershipLabel() {
      const labels = { joint: 'Joint', tenants_in_common: 'Tenants in common', trust: 'Trust' };
      return labels[this.account.ownership_type] || this.account.ownership_type;
    },
  },
};
</script>
