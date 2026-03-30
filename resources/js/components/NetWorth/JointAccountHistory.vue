<template>
  <div class="joint-account-history">
    <div class="header">
      <h2>Joint Account Edit History</h2>
      <p class="subtitle">Track changes made to jointly owned assets</p>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls">
      <label for="type-filter">Filter by Type:</label>
      <select id="type-filter" v-model="selectedType" @change="fetchLogs">
        <option value="">All Types</option>
        <option value="property">Property</option>
        <option value="mortgage">Mortgage</option>
        <option value="investment">Investment</option>
        <option value="savings">Savings</option>
      </select>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="w-10 h-10 border-[3px] border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Loading edit history...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="logs.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3>No Edit History</h3>
      <p>When you or your joint account holder make changes to shared assets, they will appear here.</p>
    </div>

    <!-- Log List -->
    <div v-else class="log-list">
      <div v-for="log in logs" :key="log.id" class="log-item">
        <div class="log-header">
          <div class="log-meta">
            <span class="log-type" :class="getTypeClass(log.asset_type)">
              {{ formatAssetType(log.asset_type) }}
            </span>
            <span class="log-action" :class="log.action">
              {{ formatAction(log.action) }}
            </span>
          </div>
          <span class="log-date">{{ formatDate(log.created_at) }}</span>
        </div>

        <div class="log-body">
          <div class="log-users">
            <span class="editor">
              <strong>Edited by:</strong> {{ log.edited_by }}
            </span>
            <span class="affected">
              <strong>Affected:</strong> {{ log.affected_user }} account
            </span>
          </div>

          <div class="log-asset">
            <strong>Asset:</strong> {{ log.asset_name || 'Unknown' }}
          </div>

          <div v-if="log.changes && Object.keys(log.changes).length > 0" class="log-changes">
            <h4>Changes:</h4>
            <table class="changes-table">
              <thead>
                <tr>
                  <th>Field</th>
                  <th>Before</th>
                  <th>After</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(change, field) in log.changes" :key="field">
                  <td class="field-name">{{ formatFieldName(field) }}</td>
                  <td class="before-value">{{ formatValue(field, change.before) }}</td>
                  <td class="after-value">{{ formatValue(field, change.after) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="fetchLogs" class="retry-button">Try Again</button>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'JointAccountHistory',
  mixins: [currencyMixin],

  data() {
    return {
      logs: [],
      loading: false,
      error: null,
      selectedType: '',
    };
  },

  computed: {
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.fetchLogs();
  },

  methods: {
    async fetchLogs() {
      // Preview users are real DB users - use normal API to fetch their data
      this.loading = true;
      this.error = null;

      try {
        const params = {};
        if (this.selectedType) {
          params.type = this.selectedType;
        }

        const response = await api.get('/joint-account-logs', { params });
        // Handle nested response structure: response.data.data.logs
        const responseData = response.data;
        this.logs = responseData?.data?.logs || responseData?.logs || [];
      } catch (err) {
        logger.error('Failed to fetch joint account logs:', err);
        this.error = 'Failed to load edit history. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    formatAssetType(type) {
      const types = {
        property: 'Property',
        mortgage: 'Mortgage',
        investment: 'Investment',
        savings: 'Savings',
      };
      return types[type] || type;
    },

    getTypeClass(type) {
      return `type-${type}`;
    },

    formatAction(action) {
      const actions = {
        update: 'Updated',
        create: 'Created',
        delete: 'Deleted',
      };
      return actions[action] || action;
    },

    formatDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },

    formatFieldName(field) {
      // Convert snake_case to Title Case
      return field
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },

    formatValue(field, value) {
      if (value === null || value === undefined) return '-';

      // Format currency fields
      const currencyFields = [
        'current_value',
        'purchase_price',
        'outstanding_balance',
        'original_loan_amount',
        'monthly_payment',
        'monthly_rental_income',
        'monthly_council_tax',
        'monthly_gas',
        'monthly_electricity',
        'monthly_water',
        'monthly_building_insurance',
        'monthly_contents_insurance',
        'monthly_service_charge',
        'monthly_maintenance_reserve',
        'other_monthly_costs',
      ];

      if (currencyFields.includes(field)) {
        return this.formatCurrency(value);
      }

      // Format percentage fields
      if (field.includes('percentage') || field.includes('rate')) {
        return `${value}%`;
      }

      return value;
    },

    // formatCurrency provided by currencyMixin
  },
};
</script>

<style scoped>
.joint-account-history {
  padding: 24px;
}

.header {
  margin-bottom: 24px;
}

.header h2 {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.filter-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  padding: 16px;
  @apply bg-savannah-100;
  border-radius: 8px;
}

.filter-controls label {
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
}

.filter-controls select {
  padding: 8px 12px;
  @apply border border-horizon-300;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  cursor: pointer;
}

.filter-controls select:focus {
  outline: none;
  @apply border-raspberry-500;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
  @apply text-neutral-500;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
  text-align: center;
  @apply bg-savannah-100;
  border-radius: 12px;
  @apply border-2 border-dashed border-light-gray;
}

.empty-icon {
  width: 48px;
  height: 48px;
  @apply text-horizon-400;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 18px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
}

.empty-state p {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
  max-width: 400px;
}

.log-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.log-item {
  background: white;
  @apply border border-light-gray;
  border-radius: 12px;
  overflow: hidden;
}

.log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  @apply bg-savannah-100;
  @apply border-b border-light-gray;
}

.log-meta {
  display: flex;
  gap: 8px;
}

.log-type {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.log-type.type-property {
  @apply bg-violet-100;
  @apply text-violet-700;
}

.log-type.type-mortgage {
  @apply bg-violet-100;
  @apply text-violet-700;
}

.log-type.type-investment {
  @apply bg-spring-100;
  @apply text-spring-700;
}

.log-type.type-savings {
  @apply bg-indigo-100;
  @apply text-indigo-700;
}

.log-action {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.log-action.update {
  @apply bg-violet-100;
  @apply text-violet-700;
}

.log-action.create {
  @apply bg-spring-100;
  @apply text-spring-700;
}

.log-action.delete {
  @apply bg-raspberry-100;
  @apply text-raspberry-700;
}

.log-date {
  font-size: 13px;
  @apply text-neutral-500;
}

.log-body {
  padding: 16px;
}

.log-users {
  display: flex;
  gap: 24px;
  margin-bottom: 12px;
  font-size: 14px;
  @apply text-neutral-500;
}

.log-asset {
  font-size: 14px;
  @apply text-neutral-500;
  margin-bottom: 16px;
}

.log-changes h4 {
  font-size: 14px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 12px 0;
}

.changes-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.changes-table th,
.changes-table td {
  padding: 10px 12px;
  text-align: left;
  @apply border-b border-light-gray;
}

.changes-table th {
  @apply bg-savannah-100;
  font-weight: 600;
  @apply text-neutral-500;
}

.field-name {
  @apply text-neutral-500;
}

.before-value {
  @apply text-raspberry-600;
}

.after-value {
  @apply text-spring-600;
}

.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
  @apply bg-raspberry-50;
  border-radius: 8px;
  @apply text-raspberry-700;
}

.retry-button {
  margin-top: 12px;
  padding: 8px 16px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.retry-button:hover {
  @apply bg-raspberry-500;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .joint-account-history {
    padding: 16px;
  }

  .log-header {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }

  .log-users {
    flex-direction: column;
    gap: 8px;
  }

  .changes-table {
    font-size: 12px;
  }

  .changes-table th,
  .changes-table td {
    padding: 8px;
  }
}
</style>
