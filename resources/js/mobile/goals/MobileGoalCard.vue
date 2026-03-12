<template>
  <div
    class="bg-white rounded-xl border border-light-gray p-4 flex items-center gap-4
           active:bg-savannah-100 transition-colors cursor-pointer"
    @click="$emit('click')"
  >
    <!-- Progress ring -->
    <ProgressRing
      :percentage="progressPercentage"
      :size="56"
      :stroke-width="4"
      :status="status"
    />

    <!-- Details -->
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-bold text-horizon-500 truncate">{{ goal.name }}</h4>
      <p class="text-xs text-neutral-500 mt-0.5">
        {{ formatCurrency(goal.current_amount) }} of {{ formatCurrency(goal.target_amount) }}
      </p>
      <div class="flex items-center gap-1.5 mt-1">
        <span
          class="w-1.5 h-1.5 rounded-full"
          :class="statusDotClass"
        ></span>
        <span class="text-xs font-medium" :class="statusTextClass">
          {{ statusLabel }}
        </span>
        <span v-if="goal.target_date" class="text-xs text-neutral-400 ml-1">
          &middot; {{ formattedTargetDate }}
        </span>
      </div>
    </div>

    <!-- Percentage -->
    <span class="text-sm font-bold" :class="statusTextClass">
      {{ progressPercentage }}%
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import ProgressRing from '@/mobile/charts/ProgressRing.vue';

export default {
  name: 'MobileGoalCard',

  components: {
    ProgressRing,
  },

  mixins: [currencyMixin],

  props: {
    goal: {
      type: Object,
      required: true,
    },
  },

  emits: ['click'],

  computed: {
    progressPercentage() {
      const target = parseFloat(this.goal.target_amount) || 0;
      if (target === 0) return 0;
      const current = parseFloat(this.goal.current_amount) || 0;
      return Math.min(Math.round((current / target) * 100), 100);
    },

    status() {
      if (this.goal.status === 'completed') return 'on-track';
      if (this.goal.is_on_track) return 'on-track';
      if (this.goal.is_at_risk) return 'at-risk';
      return 'behind';
    },

    statusLabel() {
      if (this.goal.status === 'completed') return 'Completed';
      if (this.goal.is_on_track) return 'On track';
      if (this.goal.is_at_risk) return 'At risk';
      return 'Behind';
    },

    statusDotClass() {
      const map = {
        'on-track': 'bg-spring-500',
        'behind': 'bg-violet-500',
        'at-risk': 'bg-raspberry-500',
      };
      return map[this.status];
    },

    statusTextClass() {
      const map = {
        'on-track': 'text-spring-500',
        'behind': 'text-violet-500',
        'at-risk': 'text-raspberry-500',
      };
      return map[this.status];
    },

    formattedTargetDate() {
      if (!this.goal.target_date) return '';
      const date = new Date(this.goal.target_date);
      return date.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    },
  },
};
</script>
