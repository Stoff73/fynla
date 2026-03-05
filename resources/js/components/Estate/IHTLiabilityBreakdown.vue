<template>
  <!-- Liability Breakdown for One Person (User or Spouse) -->
  <template v-if="ownerData && ownerData.liabilities">
    <!-- Owner Liabilities Header -->
    <tr class="bg-white  cursor-pointer hover:bg-eggshell-500 select-none" @click="$emit('toggle-liability', ownerKey + '-all')">
      <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
        <span class="inline-flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-all') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          {{ ownerData.name }}'s Liabilities
        </span>
      </td>
      <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(ownerData.total) }}</td>
      <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(ownerData.total) }}</td>
      <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(projectedTotal) }}</td>
      <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(projectedTotal) }}</td>
    </tr>

    <template v-if="isExpanded(ownerKey + '-all')">
      <!-- Mortgages -->
      <template v-if="ownerData.liabilities.mortgages?.length > 1">
        <tr class="cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-liability', ownerKey + '-mortgages')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-mortgages') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Mortgages</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.liabilities.mortgages.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupTotal(ownerData.liabilities.mortgages, 'outstanding_balance')) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupTotal(ownerData.liabilities.mortgages, 'outstanding_balance')) }}</td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupProjectedTotal(ownerData.liabilities.mortgages, 'outstanding_balance', 'projected_balance')) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupProjectedTotal(ownerData.liabilities.mortgages, 'outstanding_balance', 'projected_balance')) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-mortgages')">
          <tr v-for="(mortgage, index) in ownerData.liabilities.mortgages" :key="ownerKey + '-mortgage-' + index">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              {{ mortgage.property_address }}
              <span class="text-sm text-neutral-500 ml-2">{{ mortgage.mortgage_type }}</span>
              <span v-if="mortgage.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">{{ formatJointLabel(mortgage) }}</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(mortgage.outstanding_balance) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(mortgage.outstanding_balance) }}</td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getMortgageProjectedBalance(mortgage)) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getMortgageProjectedBalance(mortgage)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(mortgage, index) in ownerData.liabilities.mortgages" :key="ownerKey + '-mortgage-' + index">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">Mortgage:</span> {{ mortgage.property_address }}
            <span class="text-sm text-neutral-500 ml-2">{{ mortgage.mortgage_type }}</span>
            <span v-if="mortgage.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">{{ formatJointLabel(mortgage) }}</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(mortgage.outstanding_balance) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(mortgage.outstanding_balance) }}</td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getMortgageProjectedBalance(mortgage)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getMortgageProjectedBalance(mortgage)) }}</td>
        </tr>
      </template>

      <!-- Other Liabilities -->
      <template v-if="ownerData.liabilities.other_liabilities?.length > 1">
        <tr class="cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-liability', ownerKey + '-other')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-other') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Other Liabilities</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.liabilities.other_liabilities.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupTotal(ownerData.liabilities.other_liabilities, 'current_balance')) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupTotal(ownerData.liabilities.other_liabilities, 'current_balance')) }}</td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupProjectedTotal(ownerData.liabilities.other_liabilities, 'current_balance', 'projected_balance')) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liabilityGroupProjectedTotal(ownerData.liabilities.other_liabilities, 'current_balance', 'projected_balance')) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-other')">
          <tr v-for="(liability, index) in ownerData.liabilities.other_liabilities" :key="ownerKey + '-liability-' + index">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              <span class="text-sm text-neutral-500">{{ liability.type }}:</span> {{ liability.institution }}
              <span v-if="liability.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">{{ formatJointLabel(liability) }}</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liability.current_balance) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liability.current_balance) }}</td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getLiabilityProjectedBalance(liability)) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getLiabilityProjectedBalance(liability)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(liability, index) in ownerData.liabilities.other_liabilities" :key="ownerKey + '-liability-' + index">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">{{ liability.type }}:</span> {{ liability.institution }}
            <span v-if="liability.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">{{ formatJointLabel(liability) }}</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liability.current_balance) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(liability.current_balance) }}</td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getLiabilityProjectedBalance(liability)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatLiability(getLiabilityProjectedBalance(liability)) }}</td>
        </tr>
      </template>

      <!-- Liabilities Subtotal (only if > 0) -->
      <tr v-if="ownerData.total > 0" class="bg-white ">
        <td class="px-4 py-2 text-sm font-semibold text-horizon-500 pl-8">{{ subtotalLabel }}</td>
        <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(ownerData.total) }}</td>
        <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(ownerData.total) }}</td>
        <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(projectedTotal) }}</td>
        <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatLiability(projectedTotal) }}</td>
      </tr>
    </template>
  </template>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IHTLiabilityBreakdown',

  mixins: [currencyMixin],

  props: {
    ownerKey: {
      type: String,
      required: true, // 'user' or 'spouse'
    },
    ownerData: {
      type: Object,
      required: true,
      // Expected structure: { name: String, total: Number, projected_total: Number, liabilities: { mortgages: [], other_liabilities: [] } }
    },
    showMinus5Years: {
      type: Boolean,
      default: false,
    },
    showPlus5Years: {
      type: Boolean,
      default: false,
    },
    expandedLiabilities: {
      type: Object,
      required: true,
    },
    subtotalLabel: {
      type: String,
      default: 'Subtotal',
    },
  },

  emits: ['toggle-liability'],

  computed: {
    projectedTotal() {
      // Calculate projected total from liabilities
      if (!this.ownerData?.liabilities) return 0;
      let total = 0;

      // Sum mortgages
      if (Array.isArray(this.ownerData.liabilities.mortgages)) {
        this.ownerData.liabilities.mortgages.forEach(mortgage => {
          total += this.getMortgageProjectedBalance(mortgage);
        });
      }

      // Sum other liabilities
      if (Array.isArray(this.ownerData.liabilities.other_liabilities)) {
        this.ownerData.liabilities.other_liabilities.forEach(liability => {
          total += this.getLiabilityProjectedBalance(liability);
        });
      }

      return total;
    },
  },

  methods: {
    isExpanded(key) {
      return !!this.expandedLiabilities[key];
    },

    liabilityGroupTotal(liabilities, field) {
      return (liabilities || []).reduce((sum, l) => sum + (parseFloat(l[field]) || 0), 0);
    },

    liabilityGroupProjectedTotal(liabilities, currentField, projectedField) {
      return (liabilities || []).reduce((sum, l) => {
        const projected = l[projectedField] !== undefined && l[projectedField] !== null ? l[projectedField] : l[currentField];
        return sum + (parseFloat(projected) || 0);
      }, 0);
    },

    getMortgageProjectedBalance(mortgage) {
      return mortgage.projected_balance !== undefined && mortgage.projected_balance !== null
        ? mortgage.projected_balance
        : (mortgage.outstanding_balance || 0);
    },

    getLiabilityProjectedBalance(liability) {
      return liability.projected_balance !== undefined && liability.projected_balance !== null
        ? liability.projected_balance
        : (liability.current_balance || 0);
    },

    formatJointLabel(item) {
      // Returns "(Joint)" for 50/50 split, "(Joint - X%)" for non-50/50
      const pct = parseFloat(item.ownership_percentage) || 50;
      const rounded = Math.round(pct);
      if (rounded === 50) {
        return '(Joint)';
      }
      return `(Joint - ${rounded}%)`;
    },
  },
};
</script>
