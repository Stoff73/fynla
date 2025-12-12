<template>
  <div class="actions-dashboard">
    <div class="header">
      <h1>Recommendations & Actions</h1>
      <p class="subtitle">Personalized financial planning recommendations from all modules</p>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="card">
        <div class="card-value">{{ summary.total_count }}</div>
        <div class="card-label">Total Recommendations</div>
      </div>
      <div class="card high">
        <div class="card-value">{{ summary.by_priority.high }}</div>
        <div class="card-label">High Priority</div>
      </div>
      <div class="card medium">
        <div class="card-value">{{ summary.by_priority.medium }}</div>
        <div class="card-label">Medium Priority</div>
      </div>
      <div class="card low">
        <div class="card-value">{{ summary.by_priority.low }}</div>
        <div class="card-label">Low Priority</div>
      </div>
    </div>

    <!-- Filters -->
    <RecommendationFilters
      v-model:module="filters.module"
      v-model:priority="filters.priority"
      v-model:timeline="filters.timeline"
      @filter="applyFilters"
    />

    <!-- Loading State -->
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Loading recommendations...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error">
      <p>{{ error }}</p>
      <button @click="fetchRecommendations">Retry</button>
    </div>

    <!-- Recommendations List -->
    <div v-else class="recommendations-list">
      <div v-if="filteredRecommendations.length === 0" class="empty-state">
        <p>No recommendations match your filters.</p>
        <button @click="clearFilters">Clear Filters</button>
      </div>

      <div
        v-for="recommendation in filteredRecommendations"
        :key="recommendation.recommendation_id"
        class="recommendation-card"
      >
        <div class="rec-header">
          <span :class="['priority-badge', recommendation.impact]">
            {{ recommendation.impact.toUpperCase() }}
          </span>
          <span class="module-badge">{{ recommendation.module }}</span>
          <span class="timeline-badge">{{ recommendation.timeline.replace('_', ' ') }}</span>
        </div>

        <div class="rec-content">
          <p class="rec-text">{{ recommendation.recommendation_text }}</p>

          <div v-if="recommendation.estimated_cost || recommendation.potential_benefit" class="rec-financials">
            <div v-if="recommendation.estimated_cost" class="cost">
              Cost: £{{ formatNumber(recommendation.estimated_cost) }}
            </div>
            <div v-if="recommendation.potential_benefit" class="benefit">
              Benefit: £{{ formatNumber(recommendation.potential_benefit) }}
            </div>
          </div>
        </div>

        <div class="rec-actions">
          <button @click="markInProgress(recommendation)" class="btn-secondary">
            In Progress
          </button>
          <button @click="markDone(recommendation)" class="btn-primary">
            Mark Done
          </button>
          <button @click="dismiss(recommendation)" class="btn-text">
            Dismiss
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import RecommendationFilters from '../../components/Actions/RecommendationFilters.vue';

export default {
  name: 'ActionsDashboard',

  components: {
    RecommendationFilters,
  },

  data() {
    return {
      filters: {
        module: '',
        priority: '',
        timeline: '',
      },
    };
  },

  computed: {
    ...mapState('recommendations', ['recommendations', 'summary', 'loading', 'error']),

    filteredRecommendations() {
      return this.recommendations;
    },
  },

  methods: {
    ...mapActions('recommendations', [
      'fetchRecommendations',
      'fetchSummary',
      'markRecommendationDone',
      'markRecommendationInProgress',
      'dismissRecommendation',
    ]),

    applyFilters() {
      const params = {};
      if (this.filters.module) params.module = this.filters.module;
      if (this.filters.priority) params.priority = this.filters.priority;
      if (this.filters.timeline) params.timeline = this.filters.timeline;

      this.fetchRecommendations(params);
    },

    clearFilters() {
      this.filters = {
        module: '',
        priority: '',
        timeline: '',
      };
      this.fetchRecommendations();
    },

    async markDone(recommendation) {
      await this.markRecommendationDone(recommendation);
      this.fetchRecommendations();
    },

    async markInProgress(recommendation) {
      await this.markRecommendationInProgress(recommendation);
      this.fetchRecommendations();
    },

    async dismiss(recommendation) {
      await this.dismissRecommendation(recommendation);
      this.fetchRecommendations();
    },

    formatNumber(value) {
      return new Intl.NumberFormat('en-GB').format(value);
    },
  },

  mounted() {
    this.fetchRecommendations();
    this.fetchSummary();
  },
};
</script>

<style scoped>
.actions-dashboard {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  margin-bottom: 30px;
}

.header h1 {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 8px;
}

.subtitle {
  color: #666;
  font-size: 14px;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 30px;
}

.card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
}

.card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.card-value {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 8px;
}

.card.high .card-value {
  color: #dc2626;
}

.card.medium .card-value {
  color: #f59e0b;
}

.card.low .card-value {
  color: #10b981;
}

.recommendations-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.recommendation-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
}

.recommendation-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  border-color: #6366f1;
}

.rec-header {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}

.priority-badge,
.module-badge,
.timeline-badge {
  padding: 4px 12px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.priority-badge.high {
  background: #fee2e2;
  color: #dc2626;
}

.priority-badge.medium {
  background: #fef3c7;
  color: #f59e0b;
}

.priority-badge.low {
  background: #d1fae5;
  color: #10b981;
}

.module-badge {
  background: #e0e7ff;
  color: #4f46e5;
}

.timeline-badge {
  background: #e5e7eb;
  color: #374151;
}

.rec-text {
  font-size: 16px;
  color: #111827;
  margin-bottom: 12px;
}

.rec-financials {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
  font-size: 14px;
}

.cost {
  color: #dc2626;
}

.benefit {
  color: #10b981;
}

.rec-actions {
  display: flex;
  gap: 12px;
}

.btn-primary,
.btn-secondary,
.btn-text {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background: #4f46e5;
  color: white;
}

.btn-secondary {
  background: #e5e7eb;
  color: #374151;
}

.btn-text {
  background: transparent;
  color: #6b7280;
}

.loading,
.error,
.empty-state {
  text-align: center;
  padding: 40px;
}

.spinner {
  border: 3px solid #f3f4f6;
  border-top: 3px solid #4f46e5;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
