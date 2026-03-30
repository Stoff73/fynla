<template>
  <AppLayout>
    <div class="py-8">
      <!-- Back button -->
      <button class="detail-inline-back mb-6" @click="$router.push('/actions')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Actions
      </button>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-6">
        <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
          <div class="bg-savannah-100 animate-pulse rounded h-8 w-2/3 mb-4"></div>
          <div class="bg-savannah-100 animate-pulse rounded h-4 w-1/4 mb-4"></div>
          <div class="bg-savannah-100 animate-pulse rounded h-16 w-full"></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
            <div class="bg-savannah-100 animate-pulse rounded h-6 w-1/3 mb-4"></div>
            <div class="bg-savannah-100 animate-pulse rounded h-32 w-full"></div>
          </div>
          <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
            <div class="bg-savannah-100 animate-pulse rounded h-6 w-1/3 mb-4"></div>
            <div class="bg-savannah-100 animate-pulse rounded h-32 w-full"></div>
          </div>
        </div>
      </div>

      <!-- Action not found -->
      <div v-else-if="!action" class="bg-white rounded-card shadow-card border border-light-gray p-6 text-center">
        <p class="text-body text-neutral-500">Action not found.</p>
        <button class="mt-4 btn-primary" @click="$router.push('/actions')">Back to Actions</button>
      </div>

      <!-- Action detail -->
      <template v-else>
        <!-- Header card -->
        <div class="bg-white rounded-card shadow-card border border-light-gray p-6 mb-6">
          <div class="flex items-start justify-between mb-4">
            <h1 class="text-h3 font-bold text-horizon-500">{{ action.title }}</h1>
            <span
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium capitalize flex-shrink-0 ml-4"
              :class="priorityClass"
            >
              {{ action.priority }}
            </span>
          </div>
          <span class="inline-flex items-center px-3 py-1 rounded-md text-body-sm font-medium bg-eggshell-500 text-horizon-500 mb-4">
            {{ action.category }}
          </span>
          <p class="text-body text-neutral-500 mb-4">{{ action.description }}</p>
          <div
            v-if="action.estimated_impact"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-spring-50 border border-spring-200"
          >
            <span class="text-body-sm text-neutral-500 mr-2">Estimated impact:</span>
            <span class="text-body font-bold text-spring-700">{{ formatCurrency(action.estimated_impact) }}</span>
          </div>
          <p v-if="action.action_detail" class="text-body-sm text-neutral-500 mt-4 italic">
            {{ action.action_detail }}
          </p>
        </div>

        <!-- Decision trace panels — side by side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
            <h2 class="text-h4 font-bold text-horizon-500 mb-4">Decision Tree</h2>
            <DecisionTreeDiagram
              :steps="action.decision_trace || []"
              :outcome="outcome"
            />
          </div>
          <div class="bg-white rounded-card shadow-card border border-light-gray p-6">
            <h2 class="text-h4 font-bold text-horizon-500 mb-4">Decision Trace</h2>
            <DecisionTraceTimeline
              :steps="action.decision_trace || []"
              :outcome="outcome"
            />
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import DecisionTreeDiagram from '@/components/Actions/DecisionTreeDiagram.vue';
import DecisionTraceTimeline from '@/components/Actions/DecisionTraceTimeline.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'ActionDetailView',

  components: {
    AppLayout,
    DecisionTreeDiagram,
    DecisionTraceTimeline,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
    };
  },

  computed: {
    planType() {
      return this.$route.params.planType;
    },

    actionId() {
      return this.$route.params.actionId;
    },

    plan() {
      return this.$store.getters['plans/getPlan'](this.planType);
    },

    action() {
      if (!this.plan?.actions) return null;
      return this.plan.actions.find(a => a.id === this.actionId);
    },

    outcome() {
      if (!this.action) return { title: '', description: '' };
      return {
        title: this.action.title,
        description: this.action.description,
      };
    },

    priorityClass() {
      const classes = {
        critical: 'bg-raspberry-100 text-raspberry-700',
        high: 'bg-raspberry-50 text-raspberry-600',
        medium: 'bg-violet-100 text-violet-700',
        low: 'bg-eggshell-500 text-neutral-500',
      };
      return classes[this.action?.priority] || classes.medium;
    },
  },

  async mounted() {
    if (!this.plan) {
      try {
        await this.$store.dispatch('plans/fetchPlan', this.planType);
      } catch (e) {
        logger.error('[ActionDetail] Failed to fetch plan:', e);
      }
    }
    this.loading = false;
  },
};
</script>
