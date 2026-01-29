<template>
  <div class="account-detail-view">
    <!-- Account Header -->
    <div class="account-header bg-white rounded-lg shadow p-6 mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center">
          <div :class="['account-type-badge', accountTypeBadgeClass(account.account_type)]">
            {{ formatAccountType(account.account_type) }}
          </div>
          <div class="ml-4">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">{{ account.provider }}</h2>
            <p class="text-gray-600">{{ account.account_name }}</p>
          </div>
        </div>
        <div class="text-left sm:text-right">
          <p class="text-sm text-gray-600">Current Value</p>
          <p class="text-xl sm:text-2xl font-bold text-gray-900">{{ formatCurrency(account.current_value) }}</p>
          <p v-if="account.ownership_type === 'joint'" class="text-sm text-purple-600">
            Your {{ account.ownership_percentage || 50 }}% share: {{ formatCurrency(account.current_value * ((account.ownership_percentage || 50) / 100)) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Panel Content based on activeTab -->
    <transition name="fade" mode="out-in">
      <!-- Overview Panel -->
      <AccountSummaryPanel
        v-if="activeTab === 'account-overview'"
        :account="account"
        :key="'overview'"
      />

      <!-- Holdings Panel -->
      <AccountHoldingsPanel
        v-else-if="activeTab === 'account-holdings'"
        :account="account"
        :key="'holdings'"
        @open-holding-modal="$emit('open-holding-modal', account)"
      />

      <!-- Performance Panel -->
      <AccountPerformancePanel
        v-else-if="activeTab === 'account-performance'"
        :account="account"
        :key="'performance'"
      />

      <!-- Fees Panel (Coming Soon) -->
      <AccountFeesPanel
        v-else-if="activeTab === 'account-fees'"
        :account="account"
        :key="'fees'"
      />

      <!-- Tax Status Panel -->
      <TaxStatusPanel
        v-else-if="activeTab === 'account-tax-status'"
        product-category="investment"
        :product-type="account.account_type"
        :key="'tax-status'"
      />
    </transition>
  </div>
</template>

<script>
/**
 * @fileoverview Investment Account Detail View Component
 *
 * Displays detailed information for a single investment account including:
 * - Account overview with value and ownership details
 * - Holdings breakdown with individual positions
 * - Performance metrics and returns
 * - Fee analysis and platform costs
 * - Tax status information
 *
 * Uses tabbed navigation to switch between different detail panels.
 *
 * @component AccountDetailView
 * @requires AccountSummaryPanel - Overview panel with account summary
 * @requires AccountHoldingsPanel - Holdings list with position details
 * @requires AccountPerformancePanel - Performance metrics and charts
 * @requires AccountFeesPanel - Fee breakdown and analysis
 * @requires TaxStatusPanel - Tax wrapper information
 */
import AccountSummaryPanel from '@/views/Investment/AccountSummaryPanel.vue';
import AccountHoldingsPanel from '@/views/Investment/AccountHoldingsPanel.vue';
import AccountPerformancePanel from '@/views/Investment/AccountPerformancePanel.vue';
import AccountFeesPanel from '@/views/Investment/AccountFeesPanel.vue';
import TaxStatusPanel from '@/components/Common/TaxStatusPanel.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'AccountDetailView',

  mixins: [currencyMixin],

  components: {
    AccountSummaryPanel,
    AccountHoldingsPanel,
    AccountPerformancePanel,
    AccountFeesPanel,
    TaxStatusPanel,
  },

  props: {
    account: {
      type: Object,
      required: true,
    },
    activeTab: {
      type: String,
      required: true,
    },
  },

  emits: ['open-holding-modal'],

  computed: {
    // current_value IS the full value (single-record pattern)
    displayValue() {
      return this.account.current_value;
    },
  },

  methods: {
    formatAccountType(type) {
      const types = {
        'isa': 'ISA',
        'sipp': 'SIPP',
        'gia': 'GIA',
        'pension': 'Pension',
        'nsi': 'NS&I',
        'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond',
        'vct': 'VCT',
        'eis': 'EIS',
        'other': 'Other',
      };
      return types[type] || type;
    },

    formatOwnershipType(type) {
      const types = {
        individual: 'Individual',
        joint: 'Joint',
        trust: 'Trust',
      };
      return types[type] || 'Individual';
    },

    getOwnershipBadgeClass(type) {
      const classes = {
        individual: 'bg-gray-100 text-gray-800',
        joint: 'bg-purple-100 text-purple-800',
        trust: 'bg-orange-100 text-orange-800',
      };
      return classes[type] || 'bg-gray-100 text-gray-800';
    },

    accountTypeBadgeClass(type) {
      const classes = {
        isa: 'badge-isa',
        gia: 'badge-gia',
        sipp: 'badge-sipp',
        pension: 'badge-pension',
        nsi: 'badge-nsi',
        onshore_bond: 'badge-onshore_bond',
        offshore_bond: 'badge-offshore_bond',
        vct: 'badge-vct',
        eis: 'badge-eis',
        other: 'badge-other',
      };
      return classes[type] || 'badge-other';
    },
  },
};
</script>

<style scoped>
.account-detail-view {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.account-header {
  @apply border-l-4 border-primary-500;
}

.account-type-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
}

/* Account type badge colors */
.badge-isa {
  background: #d1fae5;
  color: #065f46;
}

.badge-gia {
  background: #dbeafe;
  color: #1e40af;
}

.badge-sipp,
.badge-pension {
  background: #e9d5ff;
  color: #6b21a8;
}

.badge-nsi {
  background: #e0e7ff;
  color: #3730a3;
}

.badge-onshore_bond,
.badge-offshore_bond {
  background: #ffedd5;
  color: #9a3412;
}

.badge-vct,
.badge-eis {
  background: #fce7f3;
  color: #9d174d;
}

.badge-other {
  background: #f3f4f6;
  color: #374151;
}

/* Fade transition for panel switching */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
