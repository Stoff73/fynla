<template>
  <div class="overflow-x-auto">
    <table v-if="holdings.length > 0" class="min-w-full divide-y divide-light-gray">
      <thead>
        <tr>
          <th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
            Security Name
          </th>
          <th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
            Ticker / ISIN
          </th>
          <th class="px-3 py-2 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            Quantity
          </th>
          <th class="px-3 py-2 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            Value
          </th>
          <th class="px-3 py-2 text-center text-xs font-medium text-neutral-500 uppercase tracking-wider">
            Status
          </th>
          <th v-if="hasNotInImport" class="px-3 py-2 text-center text-xs font-medium text-neutral-500 uppercase tracking-wider">
            Remove
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-light-gray">
        <tr
          v-for="(holding, index) in holdings"
          :key="index"
          :class="rowClass(holding)"
        >
          <td class="px-3 py-2 text-sm text-horizon-500">
            {{ holding.security_name || 'Unknown' }}
          </td>
          <td class="px-3 py-2 text-sm text-neutral-500">
            <span v-if="holding.ticker">{{ holding.ticker }}</span>
            <span v-else-if="holding.isin" class="text-xs">{{ holding.isin }}</span>
            <span v-else class="text-horizon-400">-</span>
          </td>
          <td class="px-3 py-2 text-sm text-right text-horizon-500">
            {{ formatQuantity(holding.quantity) }}
          </td>
          <td class="px-3 py-2 text-sm text-right text-horizon-500">
            {{ formatCurrency(holding.current_value) }}
          </td>
          <td class="px-3 py-2 text-center">
            <span :class="statusBadgeClass(holding.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
              {{ statusLabel(holding.status) }}
            </span>
          </td>
          <td v-if="hasNotInImport" class="px-3 py-2 text-center">
            <input
              v-if="holding.status === 'not_in_import'"
              type="checkbox"
              class="h-4 w-4 text-raspberry-600 border-horizon-300 rounded focus:ring-violet-500"
              :checked="isMarkedForRemoval(index)"
              @change="toggleRemoval(index)"
            />
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else class="text-sm text-neutral-500 py-4 text-center">
      No holdings found in this sheet
    </p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'HoldingsReviewTable',

  mixins: [currencyMixin],

  props: {
    holdings: {
      type: Array,
      required: true,
    },
  },

  emits: ['update:holdings'],

  data() {
    return {
      removalSet: new Set(),
    };
  },

  computed: {
    hasNotInImport() {
      return this.holdings.some(h => h.status === 'not_in_import');
    },
  },

  methods: {
    formatQuantity(value) {
      if (value == null) return '-';
      return parseFloat(value).toLocaleString('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
      });
    },

    rowClass(holding) {
      if (holding.status === 'not_in_import') return 'bg-savannah-100 opacity-60';
      if (holding.status === 'add') return 'bg-spring-50';
      if (holding.status === 'update') return 'bg-violet-50';
      return '';
    },

    statusBadgeClass(status) {
      return {
        'bg-spring-100 text-spring-700': status === 'add',
        'bg-violet-100 text-violet-700': status === 'update',
        'bg-savannah-200 text-neutral-500': status === 'unchanged',
        'bg-savannah-100 text-neutral-500 italic': status === 'not_in_import',
      };
    },

    statusLabel(status) {
      return {
        add: 'New',
        update: 'Updated',
        unchanged: 'No Change',
        not_in_import: 'Not in Import',
      }[status] || status;
    },

    isMarkedForRemoval(index) {
      return this.removalSet.has(index);
    },

    toggleRemoval(index) {
      if (this.removalSet.has(index)) {
        this.removalSet.delete(index);
      } else {
        this.removalSet.add(index);
      }

      // Emit updated holdings with removal status applied
      const updated = this.holdings.map((h, i) => {
        if (h.status === 'not_in_import' && this.removalSet.has(i)) {
          return { ...h, status: 'remove' };
        }
        if (h.status === 'remove' && !this.removalSet.has(i)) {
          return { ...h, status: 'not_in_import' };
        }
        return h;
      });
      this.$emit('update:holdings', updated);
    },
  },
};
</script>
