<template>
  <div
    class="bg-white rounded-lg border p-4 transition-all duration-200"
    :class="action.enabled ? 'border-blue-200 bg-blue-50/30' : 'border-light-gray opacity-75'"
  >
    <div class="flex items-start justify-between">
      <div class="flex-1 min-w-0 mr-4">
        <div class="flex items-center space-x-2 mb-1">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
            :class="priorityClasses"
          >
            {{ priorityLabel }}
          </span>
          <span class="text-xs text-neutral-500">{{ action.category }}</span>
        </div>
        <h4 class="text-sm font-semibold text-horizon-500">{{ action.title }}</h4>
        <p class="text-sm text-neutral-500 mt-1">{{ action.description }}</p>
        <p v-if="action.estimated_impact" class="text-xs text-green-700 mt-1 font-medium">
          Estimated impact: {{ formatCurrency(action.estimated_impact) }} (this is not a real figure until we connect to a quote engine)
        </p>

        <!-- Funding source -->
        <div v-if="hasFundingSource" class="mt-3 pt-3 border-t border-savannah-100">
          <div class="flex items-center gap-2 flex-wrap">
            <label class="text-xs font-medium text-neutral-500 whitespace-nowrap">Fund from</label>
            <template v-if="eligibleAccounts.length > 1">
              <select
                class="text-xs border border-horizon-300 rounded px-2 py-1 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                :value="selectedAccountKey"
                @change="onFundingSourceChange($event)"
              >
                <option
                  v-for="account in eligibleAccounts"
                  :key="account.type + '_' + account.id"
                  :value="account.type + '_' + account.id"
                >
                  {{ account.name }} ({{ formatCurrency(account.balance) }})
                </option>
              </select>
            </template>
            <span v-else-if="eligibleAccounts.length === 1" class="text-xs text-horizon-500">
              {{ eligibleAccounts[0].name }} ({{ formatCurrency(eligibleAccounts[0].balance) }})
            </span>
            <span v-else class="text-xs text-neutral-500 italic">No eligible accounts</span>
          </div>
          <p v-if="selectedWarning" class="text-xs text-red-600 mt-1">
            {{ selectedWarning }}
          </p>
        </div>
      </div>
      <div class="flex-shrink-0">
        <button
          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
          :class="action.enabled ? 'bg-raspberry-500' : 'bg-horizon-300'"
          role="switch"
          :aria-checked="action.enabled"
          @click="$emit('toggle', action.id)"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
            :class="action.enabled ? 'translate-x-6' : 'translate-x-1'"
          />
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PlanActionCard',

  mixins: [currencyMixin],

  props: {
    action: {
      type: Object,
      required: true,
    },
  },

  emits: ['toggle', 'update-funding-source'],

  computed: {
    hasFundingSource() {
      return this.action.funding_source && this.action.funding_source.eligible_accounts;
    },

    eligibleAccounts() {
      return this.action.funding_source?.eligible_accounts || [];
    },

    selectedAccountKey() {
      const fs = this.action.funding_source;
      if (!fs || !fs.selected_id) return '';
      return fs.selected_type + '_' + fs.selected_id;
    },

    selectedWarning() {
      return this.action.funding_source?.warning || null;
    },
  },

  methods: {
    onFundingSourceChange(event) {
      const [type, id] = event.target.value.split('_');
      this.$emit('update-funding-source', {
        actionId: this.action.id,
        actionCategory: this.action.category,
        targetAccountId: this.action.account_id ?? 0,
        fundingSourceType: type,
        fundingSourceId: parseInt(id, 10),
      });
    },
  },
};
</script>
