<template>
  <div class="card">
    <p class="text-sm font-semibold text-horizon-500">{{ levelLabel }}</p>
    <div class="mt-3 h-3 w-full rounded-full bg-horizon-100 overflow-hidden">
      <div class="h-full rounded-full bg-spring-500 transition-all" :style="{ width: progressPercent + '%' }"></div>
    </div>
    <p v-if="nextLevelName" class="mt-2 text-sm text-neutral-500">
      {{ nextActionsText }}
    </p>
    <p v-else class="mt-2 text-sm text-neutral-500">You've reached the top level.</p>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'LevelCard',
  computed: {
    ...mapState('gamification', ['level', 'levelName', 'progressPercent', 'nextLevelName', 'nextActions']),
    levelLabel() { return `Level ${this.level} · ${this.levelName}`; },
    nextActionsText() {
      if (!this.nextActions.length) return `Keep going to reach ${this.nextLevelName}.`;
      return `${this.nextActions.join(' and ')} to reach ${this.nextLevelName}.`;
    },
  },
};
</script>
