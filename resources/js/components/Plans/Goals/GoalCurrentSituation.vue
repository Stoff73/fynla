<template>
  <div class="mb-6">
    <PlanSectionHeader title="Current Situation" subtitle="Your goal progress and details" color="violet" />

    <div class="space-y-4">
      <!-- Goal Details -->
      <div class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Goal Details</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-neutral-500">Goal Name</p>
            <p class="text-sm font-medium text-horizon-500">{{ details.name || 'Unnamed Goal' }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Type</p>
            <p class="text-sm font-medium text-horizon-500">{{ formatGoalType(details.type) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Priority</p>
            <span
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
              :class="priorityClasses"
            >
              {{ formatPriority(details.priority) }}
            </span>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Target Amount</p>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(details.target_amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Current Amount</p>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(details.current_amount) }}</p>
          </div>
          <div v-if="details.target_date">
            <p class="text-xs text-neutral-500">Target Date</p>
            <p class="text-sm font-medium text-horizon-500">{{ formatTargetDate(details.target_date) }}</p>
          </div>
        </div>
        <div v-if="details.monthly_contribution > 0" class="mt-3 pt-3 border-t border-light-gray">
          <div class="flex justify-between">
            <span class="text-sm text-neutral-500">Monthly Contribution</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(details.monthly_contribution) }}</span>
          </div>
        </div>
      </div>

      <!-- Progress -->
      <div v-if="situation.progress" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Progress</h3>
        <div class="mb-3">
          <div class="flex items-center justify-between text-sm mb-1">
            <span class="text-neutral-500">{{ progressPercent }}% complete</span>
            <span class="font-medium text-horizon-500">{{ formatCurrency(remaining) }} remaining</span>
          </div>
          <div class="w-full bg-horizon-200 rounded-full h-2.5">
            <div
              class="h-2.5 rounded-full transition-all duration-500"
              :class="progressBarColor"
              :style="{ width: `${Math.min(100, progressPercent)}%` }"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">On Track</p>
            <p class="text-sm font-bold" :class="isOnTrack ? 'text-spring-700' : 'text-raspberry-700'">
              {{ isOnTrack ? 'Yes' : 'No' }}
            </p>
          </div>
          <div v-if="situation.progress.months_remaining !== null" class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Months Remaining</p>
            <p class="text-sm font-bold text-horizon-500">{{ situation.progress.months_remaining }}</p>
          </div>
          <div v-if="situation.progress.estimated_completion_date" class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Estimated Completion</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatTargetDate(situation.progress.estimated_completion_date) }}</p>
          </div>
        </div>
      </div>

      <!-- Affordability -->
      <div v-if="situation.affordability" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Affordability</h3>
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Category</p>
            <p class="text-sm font-bold" :class="affordabilityCategoryColor">
              {{ formatAffordabilityCategory(situation.affordability.category) }}
            </p>
          </div>
          <div v-if="situation.affordability.monthly_surplus !== undefined" class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Monthly Surplus</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.affordability.monthly_surplus) }}</p>
          </div>
        </div>
      </div>

      <!-- Linked Accounts -->
      <div v-if="hasLinkedAccounts" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Linked Accounts</h3>
        <div class="space-y-2">
          <div v-if="situation.linked_accounts.savings" class="flex items-center text-sm text-horizon-500">
            <svg class="w-4 h-4 text-spring-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Savings account linked
          </div>
          <div v-if="situation.linked_accounts.investment" class="flex items-center text-sm text-horizon-500">
            <svg class="w-4 h-4 text-spring-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Investment account linked
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'GoalCurrentSituation',
  components: { PlanSectionHeader },
  mixins: [currencyMixin],

  props: {
    situation: { type: Object, required: true },
    goal: { type: Object, default: null },
  },

  computed: {
    details() {
      return this.situation.goal_details || {};
    },
    progressPercent() {
      return Math.round(this.situation.progress?.progress_percentage || 0);
    },
    remaining() {
      const target = this.details.target_amount || 0;
      const current = this.details.current_amount || 0;
      return Math.max(0, target - current);
    },
    isOnTrack() {
      return this.situation.progress?.is_on_track || false;
    },
    progressBarColor() {
      if (this.progressPercent >= 75) return 'bg-spring-500';
      if (this.progressPercent >= 50) return 'bg-violet-500';
      if (this.progressPercent >= 25) return 'bg-violet-400';
      return 'bg-horizon-400';
    },
    priorityClasses() {
      const p = (this.details.priority || '').toLowerCase();
      if (p === 'high' || p === 'critical') return 'bg-raspberry-100 text-raspberry-800';
      if (p === 'medium') return 'bg-violet-100 text-violet-800';
      return 'bg-savannah-100 text-neutral-500';
    },
    affordabilityCategoryColor() {
      const cat = (this.situation.affordability?.category || '').toLowerCase();
      if (cat === 'comfortable') return 'text-spring-700';
      if (cat === 'moderate') return 'text-violet-700';
      return 'text-raspberry-700';
    },
    hasLinkedAccounts() {
      return this.situation.linked_accounts?.savings || this.situation.linked_accounts?.investment;
    },
  },

  methods: {
    formatGoalType(type) {
      if (!type) return 'General';
      return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
    formatPriority(priority) {
      if (!priority) return 'Normal';
      return priority.charAt(0).toUpperCase() + priority.slice(1);
    },
    formatTargetDate(dateStr) {
      if (!dateStr) return 'Not set';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    formatAffordabilityCategory(category) {
      if (!category) return 'Unknown';
      return category.charAt(0).toUpperCase() + category.slice(1);
    },
  },
};
</script>
