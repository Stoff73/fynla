<template>
  <div class="space-y-8 md:pr-12">
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <template v-else>
      <!-- Users -->
      <section>
        <div class="flex items-baseline justify-between mb-4">
          <h2 class="text-h5 font-bold text-horizon-500">Users</h2>
          <p v-if="previousLoginLabel" class="text-xs text-neutral-500">
            Δ since last login {{ previousLoginLabel }}
          </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <button
            v-for="card in userCards"
            :key="card.id"
            type="button"
            @click="$emit('change-tab', card.tab)"
            class="card text-left transition-all hover:border-raspberry-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-violet-500"
          >
            <div class="flex items-start justify-between gap-2">
              <p class="text-sm font-medium text-neutral-500">{{ card.label }}</p>
              <span
                v-if="card.delta !== null && card.delta !== undefined"
                :class="['text-xs font-semibold whitespace-nowrap', deltaColor(card.delta)]"
              >
                {{ formatDelta(card.delta) }}
              </span>
            </div>
            <p class="text-2xl font-bold text-horizon-500 mt-1">{{ formatValue(card.value) }}</p>
            <p class="text-xs text-neutral-500 mt-1">{{ card.hint }}</p>
          </button>
        </div>
      </section>

      <!-- AI -->
      <section>
        <h2 class="text-h5 font-bold text-horizon-500 mb-4">AI</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <button
            v-for="card in aiCards"
            :key="card.id"
            type="button"
            @click="$emit('change-tab', card.tab)"
            class="card text-left transition-all hover:border-raspberry-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-violet-500"
          >
            <div class="flex items-start justify-between gap-2">
              <p class="text-sm font-medium text-neutral-500">{{ card.label }}</p>
              <span
                v-if="card.delta !== null && card.delta !== undefined"
                :class="['text-xs font-semibold whitespace-nowrap', deltaColor(card.delta)]"
              >
                {{ formatDelta(card.delta) }}
              </span>
            </div>
            <p class="text-2xl font-bold text-horizon-500 mt-1">{{ formatValue(card.value) }}</p>
            <p class="text-xs text-neutral-500 mt-1">{{ card.hint }}</p>
          </button>
        </div>
      </section>
    </template>

    <div v-if="error" class="card bg-raspberry-50 border-raspberry-200">
      <div class="flex items-start">
        <svg class="h-6 w-6 text-raspberry-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-raspberry-900">Error Loading Dashboard</h3>
          <p class="mt-2 text-sm text-raspberry-800">{{ error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import adminService from '../../services/adminService';
import logger from '@/utils/logger';
import { formatDate } from '@/utils/dateFormatter';

export default {
  name: 'AdminDashboard',
  emits: ['change-tab'],

  data() {
    return {
      loading: true,
      error: null,
      stats: {},
    };
  },

  computed: {
    deltas() {
      return this.stats.deltas || {};
    },
    previousLoginLabel() {
      if (!this.stats.previous_login_at) return null;
      const d = new Date(this.stats.previous_login_at);
      if (Number.isNaN(d.getTime())) return null;
      const datePart = formatDate(d);
      const timePart = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
      return `(${datePart} ${timePart})`;
    },
    userCards() {
      return [
        {
          id: 'total-users',
          label: 'Total users',
          value: this.stats.total_users,
          delta: this.deltas.total_users,
          hint: 'All registered accounts',
          tab: 'users',
        },
        {
          id: 'registered-7d',
          label: 'Registered (last 7 days)',
          value: this.stats.registered_7d,
          delta: this.deltas.registered_7d,
          hint: 'New accounts this week',
          tab: 'user-metrics',
        },
        {
          id: 'subscribed-7d',
          label: 'Subscribed (last 7 days)',
          value: this.stats.subscribed_7d,
          delta: this.deltas.subscribed_7d,
          hint: 'Distinct users with a completed payment this week',
          tab: 'user-metrics',
        },
      ];
    },
    aiCards() {
      return [
        {
          id: 'ai-total-tokens',
          label: 'Total tokens used',
          value: this.stats.ai_total_tokens,
          delta: this.deltas.ai_total_tokens,
          hint: 'All time, input + output',
          tab: 'ai-audit',
        },
        {
          id: 'ai-tokens-7d',
          label: 'Tokens (last 7 days)',
          value: this.stats.ai_tokens_7d,
          delta: this.deltas.ai_tokens_7d,
          hint: 'Input + output this week',
          tab: 'ai-audit',
        },
        {
          id: 'ai-conversations-7d',
          label: 'Conversations (last 7 days)',
          value: this.stats.ai_conversations_7d,
          delta: this.deltas.ai_conversations_7d,
          hint: 'Chats started this week',
          tab: 'ai-audit',
        },
      ];
    },
  },

  mounted() {
    this.loadDashboard();
  },

  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await adminService.getDashboard();
        if (response.data.success) {
          this.stats = response.data.data;
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to load dashboard:', error);
        this.error = error.response?.data?.message || 'Failed to load dashboard data';
      } finally {
        this.loading = false;
      }
    },
    formatValue(value) {
      if (value === null || value === undefined) return '—';
      const n = Number(value);
      if (Number.isNaN(n)) return String(value);
      return n.toLocaleString('en-GB');
    },
    formatDelta(delta) {
      const n = Number(delta);
      if (Number.isNaN(n)) return '';
      if (n === 0) return '±0';
      const sign = n > 0 ? '▲' : '▼';
      return `${sign} ${Math.abs(n).toLocaleString('en-GB')}`;
    },
    deltaColor(delta) {
      const n = Number(delta);
      if (!Number.isFinite(n) || n === 0) return 'text-neutral-500';
      return n > 0 ? 'text-spring-600' : 'text-raspberry-600';
    },
  },
};
</script>
