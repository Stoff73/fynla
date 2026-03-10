<template>
  <div
    class="pull-to-refresh relative overflow-hidden"
    @touchstart="onTouchStart"
    @touchmove="onTouchMove"
    @touchend="onTouchEnd"
  >
    <!-- Pull indicator -->
    <div
      class="flex items-center justify-center transition-transform"
      :style="{ transform: `translateY(${indicatorOffset}px)`, height: '0px' }"
    >
      <div v-if="refreshing || pullDistance > threshold" class="py-3">
        <div class="w-6 h-6 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>
      <div v-else-if="pullDistance > 0" class="py-3">
        <svg
          class="w-5 h-5 text-neutral-500 transition-transform"
          :style="{ transform: `rotate(${pullRotation}deg)` }"
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </div>
    </div>

    <!-- Content -->
    <div :style="{ transform: `translateY(${contentOffset}px)`, transition: pulling ? 'none' : 'transform 0.3s ease' }">
      <slot />
    </div>
  </div>
</template>

<script>
export default {
  name: 'PullToRefresh',
  emits: ['refresh'],

  data() {
    return {
      pulling: false,
      refreshing: false,
      startY: 0,
      pullDistance: 0,
      threshold: 60,
    };
  },

  computed: {
    indicatorOffset() {
      return Math.min(this.pullDistance, 80);
    },
    contentOffset() {
      if (this.refreshing) return 50;
      return Math.min(this.pullDistance * 0.5, 40);
    },
    pullRotation() {
      return Math.min((this.pullDistance / this.threshold) * 180, 180);
    },
  },

  methods: {
    onTouchStart(e) {
      if (this.refreshing) return;
      const scrollTop = this.$el.querySelector('.pull-to-refresh + *')?.scrollTop || this.$el.scrollTop;
      if (scrollTop > 0) return;
      this.startY = e.touches[0].clientY;
      this.pulling = true;
    },

    onTouchMove(e) {
      if (!this.pulling || this.refreshing) return;
      const deltaY = e.touches[0].clientY - this.startY;
      this.pullDistance = Math.max(0, deltaY);
      if (this.pullDistance > 0) {
        e.preventDefault();
      }
    },

    async onTouchEnd() {
      if (!this.pulling) return;
      this.pulling = false;

      if (this.pullDistance >= this.threshold) {
        this.refreshing = true;
        this.$emit('refresh');
        // Auto-reset after timeout
        setTimeout(() => {
          this.refreshing = false;
          this.pullDistance = 0;
        }, 3000);
      } else {
        this.pullDistance = 0;
      }
    },

    done() {
      this.refreshing = false;
      this.pullDistance = 0;
    },
  },
};
</script>
