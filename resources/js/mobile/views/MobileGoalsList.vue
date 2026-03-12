<template>
  <div class="px-4 pt-4 pb-6">
    <!-- Filter chips -->
    <div class="flex gap-2 mb-4 overflow-x-auto scrollbar-hide">
      <button
        v-for="filter in filters"
        :key="filter.value"
        class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors"
        :class="activeFilter === filter.value
          ? 'bg-raspberry-500 text-white'
          : 'bg-white text-horizon-500 border border-light-gray'"
        @click="activeFilter = filter.value"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 gap-3">
      <div v-for="i in 3" :key="i" class="bg-savannah-100 animate-pulse rounded-xl h-24"></div>
    </div>

    <!-- Goals grid -->
    <div v-else-if="filteredGoals.length" class="grid grid-cols-1 gap-3">
      <MobileGoalCard
        v-for="goal in filteredGoals"
        :key="goal.id"
        :goal="goal"
        @click="navigateToGoal(goal.id)"
      />
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">&#127919;</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No goals yet</h3>
      <p class="text-sm text-neutral-500">
        Your financial goals will appear here once added
      </p>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import MobileGoalCard from '@/mobile/goals/MobileGoalCard.vue';

export default {
  name: 'MobileGoalsList',

  components: {
    MobileGoalCard,
  },

  data() {
    return {
      activeFilter: 'all',
      filters: [
        { label: 'All', value: 'all' },
        { label: 'Active', value: 'active' },
        { label: 'Completed', value: 'completed' },
      ],
    };
  },

  computed: {
    ...mapGetters('goals', {
      goals: 'activeGoals',
      completedGoals: 'completedGoals',
    }),

    loading() {
      return this.$store.state.goals.loading;
    },

    allGoals() {
      return this.$store.state.goals.goals || [];
    },

    filteredGoals() {
      switch (this.activeFilter) {
        case 'active':
          return this.goals;
        case 'completed':
          return this.completedGoals;
        default:
          return this.allGoals;
      }
    },
  },

  mounted() {
    this.fetchGoals();
  },

  methods: {
    ...mapActions('goals', ['fetchGoals']),

    navigateToGoal(goalId) {
      this.$router.push(`/m/goals/${goalId}`);
    },
  },
};
</script>
