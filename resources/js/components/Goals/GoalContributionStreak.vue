<template>
  <div class="goal-contribution-streak" :class="containerClass">
    <!-- Compact Mode -->
    <template v-if="compact">
      <div class="flex items-center gap-1.5">
        <span class="text-base">{{ streakIcon }}</span>
        <span class="font-medium" :class="textClass">{{ streak }} {{ streak === 1 ? 'month' : 'months' }}</span>
      </div>
    </template>

    <!-- Full Mode -->
    <template v-else>
      <div class="flex items-center gap-3">
        <!-- Fire Icon with Animation -->
        <div
          class="relative w-12 h-12 flex items-center justify-center"
          :class="{ 'animate-pulse': isAnimating && streak > 0 }"
        >
          <span class="text-3xl">{{ streakIcon }}</span>
          <!-- Intensity rings for higher streaks -->
          <div
            v-if="streak >= 6"
            class="absolute inset-0 rounded-full border-2 border-orange-300 animate-ping opacity-50"
          ></div>
        </div>

        <!-- Streak Info -->
        <div class="flex-1">
          <div class="flex items-baseline gap-2">
            <span class="text-2xl font-bold" :class="numberClass">{{ streak }}</span>
            <span class="text-sm text-gray-600">{{ streak === 1 ? 'month' : 'months' }} streak</span>
          </div>
          <p v-if="showMessage" class="text-sm" :class="messageClass">{{ streakMessage }}</p>
        </div>
      </div>

      <!-- Streak Meter -->
      <div v-if="showMeter" class="mt-3">
        <div class="flex gap-1">
          <div
            v-for="i in meterLength"
            :key="i"
            class="h-2 flex-1 rounded-full transition-all duration-300"
            :class="getMeterSegmentClass(i)"
          ></div>
        </div>
        <div v-if="longestStreak > 0" class="mt-1 text-xs text-gray-500 text-right">
          Best: {{ longestStreak }} months
        </div>
      </div>

      <!-- Encouragement -->
      <div v-if="showEncouragement && streak > 0" class="mt-3 p-2 rounded-lg" :class="encouragementBgClass">
        <p class="text-sm" :class="encouragementTextClass">{{ encouragementMessage }}</p>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: 'GoalContributionStreak',

  props: {
    streak: {
      type: Number,
      default: 0,
    },
    longestStreak: {
      type: Number,
      default: 0,
    },
    compact: {
      type: Boolean,
      default: false,
    },
    showMeter: {
      type: Boolean,
      default: true,
    },
    showMessage: {
      type: Boolean,
      default: true,
    },
    showEncouragement: {
      type: Boolean,
      default: false,
    },
    isAnimating: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      meterLength: 12, // 12 months
    };
  },

  computed: {
    streakIcon() {
      if (this.streak === 0) return '❄️';
      if (this.streak >= 12) return '🏆';
      if (this.streak >= 6) return '🔥';
      if (this.streak >= 3) return '🔥';
      return '✨';
    },

    containerClass() {
      if (this.compact) return '';
      return 'p-4 rounded-lg ' + this.backgroundClass;
    },

    backgroundClass() {
      if (this.streak === 0) return 'bg-gray-50';
      if (this.streak >= 12) return 'bg-orange-50';
      if (this.streak >= 6) return 'bg-orange-50';
      if (this.streak >= 3) return 'bg-orange-50';
      return 'bg-yellow-50';
    },

    textClass() {
      if (this.streak === 0) return 'text-gray-500';
      if (this.streak >= 6) return 'text-orange-600';
      if (this.streak >= 3) return 'text-orange-500';
      return 'text-yellow-600';
    },

    numberClass() {
      if (this.streak === 0) return 'text-gray-400';
      if (this.streak >= 12) return 'text-orange-600';
      if (this.streak >= 6) return 'text-orange-600';
      if (this.streak >= 3) return 'text-orange-500';
      return 'text-yellow-600';
    },

    messageClass() {
      if (this.streak === 0) return 'text-gray-500';
      return 'text-gray-600';
    },

    streakMessage() {
      if (this.streak === 0) return 'Start contributing to build a streak';
      if (this.streak === 1) return 'Great start! Keep it going';
      if (this.streak < 3) return 'Building momentum';
      if (this.streak < 6) return 'On a roll!';
      if (this.streak < 12) return 'Impressive consistency!';
      return 'Outstanding dedication!';
    },

    encouragementBgClass() {
      if (this.streak >= 6) return 'bg-orange-100';
      if (this.streak >= 3) return 'bg-yellow-100';
      return 'bg-green-100';
    },

    encouragementTextClass() {
      if (this.streak >= 6) return 'text-orange-700';
      if (this.streak >= 3) return 'text-yellow-700';
      return 'text-green-700';
    },

    encouragementMessage() {
      if (this.streak >= 12) {
        return "A full year of consistent contributions! You're building excellent financial habits.";
      }
      if (this.streak >= 6) {
        return "Half a year strong! Your consistency is paying off.";
      }
      if (this.streak >= 3) {
        return "Three months in a row! You're developing a great savings habit.";
      }
      if (this.streak >= 1) {
        return "Keep going! Regular contributions make a big difference.";
      }
      return "";
    },
  },

  methods: {
    getMeterSegmentClass(position) {
      if (position <= this.streak) {
        // Filled segment
        if (this.streak >= 12) return 'bg-orange-500';
        if (this.streak >= 6) return 'bg-orange-500';
        if (this.streak >= 3) return 'bg-orange-400';
        return 'bg-yellow-400';
      }
      // Empty segment
      return 'bg-gray-200';
    },
  },
};
</script>

<style scoped>
@keyframes ping {
  75%, 100% {
    transform: scale(1.5);
    opacity: 0;
  }
}

.animate-ping {
  animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>
