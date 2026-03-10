<template>
  <div class="px-4 pt-4 pb-6">
    <template v-if="moduleConfig">
      <!-- Hero metric card -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-3">{{ moduleConfig.icon }}</span>
        <h2 class="text-lg font-bold text-horizon-500 mb-1">{{ moduleConfig.label }}</h2>

        <!-- Hero metric -->
        <div v-if="summaryData && heroMetric" class="mt-4">
          <p class="text-2xl font-black text-horizon-500">{{ heroMetric.value }}</p>
          <p class="text-xs text-neutral-500 mt-1">{{ heroMetric.label }}</p>
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="mt-4">
          <div class="w-32 h-8 bg-savannah-100 animate-pulse rounded mx-auto"></div>
          <div class="w-20 h-4 bg-savannah-100 animate-pulse rounded mx-auto mt-2"></div>
        </div>
      </div>

      <!-- Fyn one-liner -->
      <div v-if="fynSummary" class="bg-horizon-500 rounded-xl p-4 mb-4 flex items-start gap-3">
        <img
          src="/images/logos/favicon.png"
          alt="Fyn"
          class="w-8 h-8 rounded-full flex-shrink-0"
        />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Summary details -->
      <div v-if="summaryData && summaryDetails.length" class="bg-white rounded-xl border border-light-gray divide-y divide-light-gray mb-4">
        <div
          v-for="detail in summaryDetails"
          :key="detail.label"
          class="px-4 py-3 flex justify-between items-center"
        >
          <span class="text-sm text-neutral-500">{{ detail.label }}</span>
          <span class="text-sm font-medium text-horizon-500">{{ detail.value }}</span>
        </div>
      </div>

      <!-- View full detail on web -->
      <button
        class="w-full py-3 bg-raspberry-500 text-white rounded-xl text-sm font-bold"
        @click="openOnWeb"
      >
        View full detail on web
      </button>
    </template>

    <!-- Module not found -->
    <div v-else class="text-center py-16">
      <p class="text-neutral-500">Module not found</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

const MODULE_CONFIG = {
  protection: {
    icon: '\uD83D\uDEE1\uFE0F',
    label: 'Protection',
    webPath: '/protection',
    storeModule: 'protection',
    fynDefault: 'Your protection policies help safeguard your family\'s financial future.',
  },
  savings: {
    icon: '\uD83D\uDCB0',
    label: 'Savings',
    webPath: '/savings',
    storeModule: 'savings',
    fynDefault: 'Your savings are the foundation of your financial security.',
  },
  investment: {
    icon: '\uD83D\uDCC8',
    label: 'Investment',
    webPath: '/investment',
    storeModule: 'investment',
    fynDefault: 'Your investment portfolio is working to grow your wealth over time.',
  },
  retirement: {
    icon: '\uD83C\uDFE6',
    label: 'Retirement',
    webPath: '/retirement',
    storeModule: 'retirement',
    fynDefault: 'Your retirement planning helps secure your future lifestyle.',
  },
  estate: {
    icon: '\uD83C\uDFE0',
    label: 'Estate Planning',
    webPath: '/estate',
    storeModule: 'estate',
    fynDefault: 'Your estate plan helps ensure your assets are distributed according to your wishes.',
  },
  goals: {
    icon: '\uD83C\uDFAF',
    label: 'Goals & Life Events',
    webPath: '/goals',
    storeModule: 'goals',
    fynDefault: 'Your goals give direction and purpose to your financial planning.',
  },
  coordination: {
    icon: '\uD83D\uDD17',
    label: 'Coordination',
    webPath: '/coordination',
    storeModule: null,
    fynDefault: 'Coordination brings together all your financial modules for a complete picture.',
  },
};

export default {
  name: 'ModuleSummary',

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
      summaryData: null,
    };
  },

  computed: {
    moduleId() {
      return this.$route.params.module;
    },

    moduleConfig() {
      return MODULE_CONFIG[this.moduleId] || null;
    },

    heroMetric() {
      if (!this.summaryData) return null;
      return this.summaryData.heroMetric || null;
    },

    fynSummary() {
      if (this.summaryData?.fynSummary) return this.summaryData.fynSummary;
      return this.moduleConfig?.fynDefault || null;
    },

    summaryDetails() {
      if (!this.summaryData?.details) return [];
      return this.summaryData.details;
    },
  },

  watch: {
    moduleId: {
      handler() {
        this.loadSummary();
      },
      immediate: true,
    },
  },

  methods: {
    async loadSummary() {
      if (!this.moduleConfig) return;

      this.loading = true;
      this.summaryData = null;

      try {
        // Try to load from the mobileDashboard store which has module summaries
        const dashboard = this.$store.state.mobileDashboard?.dashboard;
        if (dashboard?.modules) {
          const mod = dashboard.modules.find(m => m.name === this.moduleId);
          if (mod) {
            this.summaryData = {
              heroMetric: mod.hero_metric ? {
                value: mod.hero_metric.formatted || this.formatCurrency(mod.hero_metric.value),
                label: mod.hero_metric.label || 'Total value',
              } : null,
              fynSummary: mod.fyn_summary || null,
              details: (mod.details || []).map(d => ({
                label: d.label,
                value: d.formatted || d.value,
              })),
            };
          }
        }
      } catch {
        // Summary data unavailable
      } finally {
        this.loading = false;
      }
    },

    async openOnWeb() {
      if (!this.moduleConfig?.webPath) return;
      const url = window.location.origin + this.moduleConfig.webPath;
      try {
        const { Browser } = await import('@capacitor/browser');
        await Browser.open({ url });
      } catch {
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    },
  },
};
</script>
