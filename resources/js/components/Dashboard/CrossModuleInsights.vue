<template>
  <div v-if="strategies.length > 0" class="card">
    <h3 class="text-lg font-bold text-horizon-500 mb-4">Cross-Module Insights</h3>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Strategies -->
    <div v-else class="space-y-4">
      <div
        v-for="(strategy, index) in strategies"
        :key="strategy.type || index"
        class="rounded-lg border border-light-gray bg-white p-4 hover:shadow-sm transition-shadow"
      >
        <!-- Module badges and priority -->
        <div class="flex items-center gap-2 mb-2 flex-wrap">
          <span
            v-for="mod in strategy.modules"
            :key="mod"
            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
            :class="getModuleBadgeClass(mod)"
          >
            {{ formatModuleName(mod) }}
          </span>
          <span
            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
            :class="getPriorityBadgeClass(strategy.priority)"
          >
            {{ strategy.priority }}
          </span>
        </div>

        <!-- Title and description -->
        <h4 class="text-sm font-bold text-horizon-500 mb-1">{{ strategy.title }}</h4>
        <p class="text-sm text-neutral-500 leading-relaxed mb-3">{{ strategy.description }}</p>

        <!-- Action and impact -->
        <div class="pt-3 border-t border-light-gray space-y-2">
          <p class="text-xs text-neutral-500">
            <span class="font-semibold text-horizon-500">Suggested action:</span> {{ strategy.action }}
          </p>
          <p v-if="strategy.estimated_impact" class="text-xs text-spring-600 font-medium">
            {{ strategy.estimated_impact }}
          </p>
        </div>

        <!-- Navigate button -->
        <div class="mt-3">
          <button
            v-preview-disabled
            class="text-xs text-raspberry-500 hover:text-raspberry-600 font-medium"
            @click="navigateToModule(strategy.modules[0])"
          >
            Review in {{ formatModuleName(strategy.modules[0]) }} &rarr;
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { previewModeMixin } from '@/mixins/previewModeMixin';
import api from '@/services/api';

export default {
  name: 'CrossModuleInsights',
  mixins: [previewModeMixin],

  data() {
    return {
      strategies: [],
      loading: false,
    };
  },

  mounted() {
    this.fetchStrategies();
  },

  methods: {
    async fetchStrategies() {
      this.loading = true;
      try {
        const response = await api.post('/holistic/analyze');
        if (response.data?.success) {
          this.strategies = response.data.data?.cross_module_strategies || [];
        }
      } catch {
        // Non-critical — silently fail
        this.strategies = [];
      } finally {
        this.loading = false;
      }
    },

    formatModuleName(mod) {
      const names = {
        tax_optimisation: 'Tax',
        investment: 'Investment',
        retirement: 'Retirement',
        protection: 'Protection',
        savings: 'Savings',
        goals: 'Goals',
        estate: 'Estate',
      };
      return names[mod] || mod;
    },

    getModuleBadgeClass(mod) {
      const classes = {
        tax_optimisation: 'bg-violet-100 text-violet-600',
        investment: 'bg-light-blue-100 text-light-blue-600',
        retirement: 'bg-savannah-200 text-horizon-500',
        protection: 'bg-raspberry-100 text-raspberry-600',
        savings: 'bg-spring-100 text-spring-600',
        goals: 'bg-light-pink-100 text-light-pink-600',
        estate: 'bg-horizon-100 text-horizon-500',
      };
      return classes[mod] || 'bg-savannah-100 text-neutral-500';
    },

    getPriorityBadgeClass(priority) {
      if (priority === 'high') return 'bg-raspberry-100 text-raspberry-600';
      if (priority === 'medium') return 'bg-violet-100 text-violet-600';
      return 'bg-savannah-100 text-neutral-500';
    },

    navigateToModule(mod) {
      const routes = {
        tax_optimisation: '/dashboard',
        investment: '/investment',
        retirement: '/net-worth/retirement',
        protection: '/protection',
        savings: '/net-worth/cash',
        goals: '/goals',
        estate: '/estate-planning',
      };
      this.$router.push(routes[mod] || '/dashboard');
    },
  },
};
</script>
