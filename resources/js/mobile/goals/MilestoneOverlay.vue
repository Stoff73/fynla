<template>
  <transition name="fade">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60"
      @click="dismiss"
    >
      <div
        class="bg-white rounded-2xl p-8 mx-6 text-center max-w-sm relative overflow-hidden"
        @click.stop
      >
        <!-- Confetti particles -->
        <div class="confetti-container absolute inset-0 pointer-events-none overflow-hidden">
          <span
            v-for="i in 20"
            :key="i"
            class="confetti-piece"
            :style="confettiStyle(i)"
          ></span>
        </div>

        <!-- Content -->
        <div class="relative z-10">
          <!-- Fyn avatar -->
          <img
            src="/images/logos/favicon.png"
            alt="Fyn"
            class="w-16 h-16 mx-auto mb-4"
          />

          <h2 class="text-xl font-black text-horizon-500 mb-2">
            {{ headingText }}
          </h2>

          <p class="text-sm text-neutral-500 leading-relaxed mb-6">
            {{ bodyText }}
          </p>

          <button
            class="w-full py-2.5 bg-raspberry-500 text-white rounded-xl text-sm font-medium"
            @click="dismiss"
          >
            Keep going!
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
import { CONFETTI_COLORS } from '@/constants/designSystem';

export default {
  name: 'MilestoneOverlay',

  props: {
    milestone: {
      type: Object,
      required: true,
    },
  },

  emits: ['dismiss'],

  data() {
    return {
      dismissTimer: null,
    };
  },

  computed: {
    headingText() {
      if (this.milestone.percentage >= 100) {
        return 'Goal achieved!';
      }
      return `${this.milestone.percentage}% reached!`;
    },

    bodyText() {
      const name = this.milestone.goalName || 'your goal';
      if (this.milestone.percentage >= 100) {
        return `Brilliant work! You've fully funded ${name}. Time to set your next target.`;
      }
      return `You're ${this.milestone.percentage}% of the way to ${name}. Keep up the great work!`;
    },
  },

  mounted() {
    // Haptic feedback
    if (window.Capacitor?.Plugins?.Haptics) {
      window.Capacitor.Plugins.Haptics.impact({ style: 'medium' });
    }

    // Auto-dismiss after 5 seconds
    this.dismissTimer = setTimeout(() => {
      this.dismiss();
    }, 5000);
  },

  beforeUnmount() {
    if (this.dismissTimer) {
      clearTimeout(this.dismissTimer);
    }
  },

  methods: {
    dismiss() {
      if (this.dismissTimer) {
        clearTimeout(this.dismissTimer);
      }
      this.$emit('dismiss');
    },

    confettiStyle(index) {
      const colors = CONFETTI_COLORS;
      const color = colors[index % colors.length];
      const left = Math.random() * 100;
      const delay = Math.random() * 0.5;
      const duration = 1.5 + Math.random() * 1;
      const size = 4 + Math.random() * 6;

      return {
        backgroundColor: color,
        left: `${left}%`,
        width: `${size}px`,
        height: `${size}px`,
        animationDelay: `${delay}s`,
        animationDuration: `${duration}s`,
      };
    },
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.confetti-piece {
  position: absolute;
  top: -10px;
  border-radius: 2px;
  animation: confetti-fall linear forwards;
}

@keyframes confetti-fall {
  0% {
    transform: translateY(0) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translateY(300px) rotate(720deg);
    opacity: 0;
  }
}
</style>
