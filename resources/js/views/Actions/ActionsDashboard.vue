<template>
  <AppLayout>
    <div class="py-8">
      <!-- SaveTax campaign — Tax Strategy tile (visible to all savetax users) -->
      <router-link
        v-if="onboardingSelection === 'savetax'"
        to="/tax-strategy"
        class="block rounded-lg border border-raspberry-200 bg-light-pink-100 p-4 mb-5 hover:border-raspberry-500 transition-colors"
      >
        <h3 class="text-body-sm font-bold text-horizon-500 mb-1">Your tax strategy</h3>
        <p class="text-caption text-neutral-500">View allowance utilisation, model what-if scenarios, and see recommended actions.</p>
      </router-link>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center h-64">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <template v-else>
        <!-- WP-2 (one actions model) — this page is the desktop home of the
             SAME unified actions list the dashboard and /m consume
             (RecommendationsAggregator ids via GET /api/recommendations/actions):
             every open action, ranked, uncapped, with the same mark-done —
             plus the completed history that previously had no home. -->
        <div class="top-priorities module-gradient">
          <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center gap-2">
            Your actions
            <span class="text-xs font-bold text-white bg-raspberry-500 px-2 py-0.5 rounded-full">{{ openActions.length }}</span>
          </h3>

          <div v-if="!openActions.length" class="text-body-sm text-neutral-500">
            You're all caught up — nothing to action right now.
          </div>

          <div v-else class="space-y-2.5">
            <div
              v-for="(action, index) in openActions"
              :key="action.id"
              class="action-row"
            >
              <div class="flex items-center gap-3.5 min-w-0 cursor-pointer" @click="goToAction(action)">
                <div class="order-num">{{ index + 1 }}</div>
                <div class="min-w-0">
                  <div class="text-[15px] font-semibold text-horizon-500">{{ action.title }}</div>
                  <div class="text-xs text-neutral-500 mt-0.5">{{ moduleLabel(action.module) }}<span v-if="action.meta"> · {{ action.meta }}</span></div>
                </div>
              </div>
              <div class="flex items-center gap-2.5 flex-shrink-0">
                <span
                  v-if="action.type !== 'recommendation'"
                  class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-violet-100 text-violet-700"
                >Unlock</span>
                <button
                  v-if="action.type === 'recommendation'"
                  type="button"
                  class="text-xs font-semibold text-spring-700 border border-spring-500 rounded-full px-3 py-1 hover:bg-spring-50 transition-colors disabled:opacity-60"
                  :disabled="marking === action.id"
                  @click.stop="markDone(action)"
                >
                  Mark as done
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Done — the completed history (recommendation_tracking), newest first. -->
        <div v-if="completedActions.length" class="top-priorities module-gradient mt-6">
          <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center gap-2">
            Done
            <span class="text-xs font-bold text-white bg-spring-500 px-2 py-0.5 rounded-full">{{ completedActions.length }}</span>
          </h3>
          <div class="space-y-2.5">
            <div
              v-for="row in completedActions"
              :key="'done-' + row.id"
              class="action-row action-row--done"
            >
              <div class="min-w-0">
                <div class="text-[15px] font-semibold text-horizon-500">{{ row.recommendation_text }}</div>
                <div class="text-xs text-neutral-500 mt-0.5">{{ moduleLabel(row.module) }}</div>
              </div>
              <span class="text-xs font-bold text-spring-700 whitespace-nowrap flex-shrink-0">Done {{ doneDate(row) }}</span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import logger from '@/utils/logger';

// Desktop route per module — where an action is taken.
const MODULE_ROUTES = {
  protection: '/protection',
  savings: '/savings',
  investment: '/investments',
  retirement: '/pension',
  estate: '/estate',
  goals: '/goals',
  tax: '/tax-strategy',
};

const MODULE_LABELS = {
  protection: 'Protection',
  savings: 'Savings',
  investment: 'Investment',
  retirement: 'Retirement',
  estate: 'Estate Planning',
  goals: 'Goals',
  tax: 'Tax Strategy',
  general: 'General',
};

export default {
  name: 'ActionsDashboard',

  components: {
    AppLayout,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      marking: null,
      openActions: [],
      completedActions: [],
    };
  },

  computed: {
    onboardingSelection() {
      return this.$store.state.auth?.user?.onboarding_fyn_selection ?? null;
    },
  },

  methods: {
    moduleLabel(module) {
      return MODULE_LABELS[module] || MODULE_LABELS.general;
    },

    goToAction(action) {
      const route = MODULE_ROUTES[action.module];
      if (route) this.$router.push(route);
    },

    doneDate(row) {
      if (!row.completed_at) return '';
      const d = new Date(row.completed_at);
      return isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-GB');
    },

    // Same completion write every other surface uses (stable aggregator id),
    // then reload so the item moves into Done.
    async markDone(action) {
      if (!action.id || this.marking) return;
      this.marking = action.id;
      try {
        await api.post(`/recommendations/${action.id}/mark-done`, {
          module: action.module || 'general',
          recommendation_text: action.title || '',
        });
        await this.load();
      } catch (e) {
        logger.error('[Actions] mark-done failed:', e);
      } finally {
        this.marking = null;
      }
    },

    async load() {
      try {
        const { data } = await api.get('/recommendations/actions');
        this.openActions = data?.data?.open ?? [];
        this.completedActions = data?.data?.completed ?? [];
      } catch (e) {
        logger.error('[Actions] Failed to fetch unified actions:', e);
      }
    },
  },

  async mounted() {
    this.loading = true;
    try {
      await this.load();
    } finally {
      this.loading = false;
    }
  },
};
</script>

<style scoped>
.top-priorities {
  @apply bg-white rounded-card border border-light-gray p-6;
}

.order-num {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  @apply bg-horizon-500 text-white;
  font-size: 16px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  @apply bg-eggshell-500 border border-transparent;
  border-radius: 10px;
  transition: all 0.2s;
}

.action-row:hover {
  @apply border-light-gray bg-white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.action-row--done {
  opacity: 0.8;
}
</style>
