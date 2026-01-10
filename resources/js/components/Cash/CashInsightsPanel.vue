<template>
  <div class="cash-insights-panel">
    <!-- Spending Chart -->
    <SpendingDonutChart :financial-commitments="financialCommitments" />

    <!-- Balance Trend Chart -->
    <BalanceTrendChart />

    <!-- My Goals Widget -->
    <div class="goals-card">
      <div class="card-header">
        <h4 class="card-title">My Goals</h4>
        <router-link to="/savings" class="view-all-link">View All</router-link>
      </div>

      <div v-if="displayedGoals.length > 0" class="goals-list">
        <div v-for="goal in displayedGoals" :key="goal.id" class="goal-item">
          <div class="goal-header">
            <span class="goal-name">{{ goal.goal_name }}</span>
            <span class="goal-progress-text">{{ getProgressPercent(goal) }}%</span>
          </div>
          <div class="goal-progress-bar">
            <div
              class="goal-progress-fill"
              :class="getProgressBarClass(goal)"
              :style="{ width: `${getProgressPercent(goal)}%` }"
            ></div>
          </div>
        </div>
      </div>

      <p v-else class="empty-text">No goals set yet</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import SpendingDonutChart from './SpendingDonutChart.vue';
import BalanceTrendChart from './BalanceTrendChart.vue';

export default {
  name: 'CashInsightsPanel',

  mixins: [currencyMixin],

  components: {
    SpendingDonutChart,
    BalanceTrendChart,
  },

  props: {
    goals: {
      type: Array,
      default: () => [],
    },
    financialCommitments: {
      type: Object,
      default: () => ({}),
    },
  },

  computed: {
    displayedGoals() {
      return this.goals.slice(0, 3);
    },
  },

  methods: {
    getProgressPercent(goal) {
      if (!goal.target_amount || goal.target_amount === 0) return 0;
      const percent = (goal.current_saved / goal.target_amount) * 100;
      return Math.min(100, Math.round(percent));
    },

    getProgressBarClass(goal) {
      const percent = this.getProgressPercent(goal);
      if (percent >= 75) return 'bg-green-500';
      if (percent >= 50) return 'bg-blue-500';
      if (percent >= 25) return 'bg-amber-500';
      return 'bg-red-500';
    },
  },
};
</script>

<style scoped>
.cash-insights-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Goals Card */
.goals-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-title {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0;
}

.view-all-link {
  font-size: 12px;
  color: #7c3aed;
  font-weight: 500;
  text-decoration: none;
}

.view-all-link:hover {
  text-decoration: underline;
}

.goals-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.goal-item {
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f4f6;
}

.goal-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.goal-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
}

.goal-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.goal-progress-text {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
}

.goal-progress-bar {
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}

.goal-progress-fill {
  height: 100%;
  border-radius: 3px;
}

.empty-text {
  font-size: 13px;
  color: #9ca3af;
  text-align: center;
  margin: 0;
}
</style>
