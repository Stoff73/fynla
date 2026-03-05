<template>
  <teleport to="body">
    <transition name="fade">
      <div
        v-if="event"
        class="fixed z-50 pointer-events-none"
        :style="tooltipStyle"
      >
        <div class="bg-horizon-600 text-white rounded-lg shadow-lg p-3 max-w-xs">
          <!-- Header -->
          <div class="flex items-center gap-2 mb-2">
            <span
              class="w-6 h-6 rounded-full flex items-center justify-center"
              :style="{ backgroundColor: event.color }"
            >
              <span class="text-xs">{{ event.type === 'goal' ? 'G' : 'E' }}</span>
            </span>
            <span class="font-semibold text-sm">{{ event.name }}</span>
          </div>

          <!-- Details -->
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-horizon-400">Amount:</span>
              <span
                class="font-medium"
                :class="event.impact === 'income' ? 'text-spring-400' : 'text-raspberry-400'"
              >
                {{ event.impact === 'income' ? '+' : '-' }}{{ formatCurrency(event.amount) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-horizon-400">Age:</span>
              <span>{{ event.age }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-horizon-400">Year:</span>
              <span>{{ event.year }}</span>
            </div>
            <div v-if="event.certainty" class="flex justify-between">
              <span class="text-horizon-400">Certainty:</span>
              <span class="capitalize">{{ event.certainty }}</span>
            </div>
          </div>

          <!-- Type badge -->
          <div class="mt-2 pt-2 border-t border-horizon-500">
            <span
              class="inline-block px-2 py-0.5 rounded text-xs"
              :class="event.type === 'goal' ? 'bg-raspberry-600' : 'bg-purple-600'"
            >
              {{ event.type === 'goal' ? 'Goal' : 'Life Event' }}
            </span>
            <span class="text-xs text-horizon-400 ml-2 capitalize">
              {{ formatCategory(event.category) }}
            </span>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EventTooltip',
  mixins: [currencyMixin],

  props: {
    event: {
      type: Object,
      default: null,
    },
    position: {
      type: Object,
      default: () => ({ x: 0, y: 0 }),
    },
  },

  computed: {
    tooltipStyle() {
      return {
        left: `${this.position.x}px`,
        top: `${this.position.y - 10}px`,
        transform: 'translate(-50%, -100%)',
      };
    },
  },

  methods: {
    formatCategory(category) {
      return category
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
