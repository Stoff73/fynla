<template>
  <div class="goal-milestone-tracker">
    <!-- Compact Mode -->
    <template v-if="compact">
      <div class="flex items-center gap-2">
        <div class="flex gap-1">
          <span
            v-for="milestone in milestones"
            :key="milestone.value"
            class="w-2 h-2 rounded-full"
            :class="milestone.reached ? 'bg-spring-500' : 'bg-horizon-200'"
          ></span>
        </div>
        <span class="text-xs text-neutral-500">{{ reachedCount }}/{{ milestones.length }} milestones</span>
      </div>
    </template>

    <!-- Full Mode -->
    <template v-else>
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-sm font-medium text-horizon-500">Milestones</h4>
        <span class="text-xs text-neutral-500">{{ reachedCount }}/{{ milestones.length }} reached</span>
      </div>

      <!-- Progress Line with Milestones -->
      <div class="relative pt-2 pb-6">
        <!-- Background Line -->
        <div class="absolute top-4 left-0 right-0 h-1 bg-horizon-200 rounded-full"></div>

        <!-- Progress Line -->
        <div
          class="absolute top-4 left-0 h-1 bg-gradient-to-r from-blue-500 to-green-500 rounded-full transition-all duration-500"
          :style="{ width: Math.min(progress, 100) + '%' }"
        ></div>

        <!-- Milestone Markers -->
        <div class="relative flex justify-between">
          <div
            v-for="milestone in milestones"
            :key="milestone.value"
            class="flex flex-col items-center"
            :style="{ width: '20%' }"
          >
            <!-- Marker Circle -->
            <div
              class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition-all duration-300 z-10"
              :class="getMilestoneClass(milestone)"
            >
              <span v-if="milestone.reached" class="text-xs">{{ milestone.icon }}</span>
              <span v-else class="text-xs text-horizon-400">{{ milestone.value }}</span>
            </div>

            <!-- Label -->
            <span
              class="mt-2 text-xs font-medium"
              :class="milestone.reached ? 'text-spring-600' : 'text-horizon-400'"
            >
              {{ milestone.label }}
            </span>
          </div>
        </div>
      </div>

      <!-- Next Milestone Info -->
      <div v-if="nextMilestone" class="mt-2 p-3 bg-violet-50 rounded-lg">
        <div class="flex items-center gap-2">
          <span class="text-lg">{{ nextMilestone.icon }}</span>
          <div class="flex-1">
            <p class="text-sm font-medium text-violet-900">
              Next: {{ nextMilestone.label }}
            </p>
            <p class="text-xs text-violet-700">
              {{ formatCurrency(amountToNextMilestone) }} more to reach {{ nextMilestone.value }}%
            </p>
          </div>
        </div>
      </div>

      <!-- Completion Celebration -->
      <div v-if="isComplete" class="mt-2 p-3 bg-spring-50 border border-spring-200 rounded-lg">
        <div class="flex items-center gap-2">
          <span class="text-lg">🎉</span>
          <p class="text-sm font-medium text-spring-700">
            Goal achieved! All milestones reached!
          </p>
        </div>
      </div>

      <!-- Recent Milestone Achievement -->
      <div v-if="showLastAchievement && lastReachedMilestone" class="mt-2 p-3 bg-violet-50 border border-violet-200 rounded-lg">
        <div class="flex items-center gap-2">
          <span class="text-lg">{{ lastReachedMilestone.icon }}</span>
          <p class="text-sm font-medium text-violet-700">
            {{ lastReachedMilestone.label }} milestone reached!
          </p>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'GoalMilestoneTracker',
  mixins: [currencyMixin],

  props: {
    progress: {
      type: Number,
      default: 0,
    },
    currentAmount: {
      type: Number,
      default: 0,
    },
    targetAmount: {
      type: Number,
      default: 0,
    },
    compact: {
      type: Boolean,
      default: false,
    },
    showLastAchievement: {
      type: Boolean,
      default: false,
    },
    achievedMilestones: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      milestoneConfig: [
        { value: 25, label: '25%', icon: '🌱' },
        { value: 50, label: '50%', icon: '⭐' },
        { value: 75, label: '75%', icon: '🚀' },
        { value: 100, label: '100%', icon: '🏆' },
      ],
    };
  },

  computed: {
    milestones() {
      return this.milestoneConfig.map(m => ({
        ...m,
        reached: this.progress >= m.value,
      }));
    },

    reachedCount() {
      return this.milestones.filter(m => m.reached).length;
    },

    isComplete() {
      return this.progress >= 100;
    },

    nextMilestone() {
      return this.milestones.find(m => !m.reached);
    },

    lastReachedMilestone() {
      const reached = this.milestones.filter(m => m.reached);
      return reached[reached.length - 1];
    },

    amountToNextMilestone() {
      if (!this.nextMilestone || !this.targetAmount) return 0;
      const nextAmount = (this.nextMilestone.value / 100) * this.targetAmount;
      return Math.max(0, nextAmount - this.currentAmount);
    },
  },

  methods: {
    getMilestoneClass(milestone) {
      if (milestone.reached) {
        return 'bg-spring-100 border-spring-500 text-spring-600';
      }
      return 'bg-white border-horizon-300';
    },
  },
};
</script>
