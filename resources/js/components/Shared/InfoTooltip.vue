<template>
  <span class="inline-flex items-center relative" ref="tooltipContainer">
    <button
      type="button"
      class="inline-flex items-center justify-center w-4 h-4 rounded-full text-neutral-500 hover:text-violet-500 focus:text-violet-500 focus:outline-none transition-colors duration-150"
      :class="{ 'animate-pulse': shouldPulse }"
      @mouseenter="showTooltip"
      @mouseleave="hideTooltip"
      @click.stop="toggleMobilePopover"
      @focus="showTooltip"
      @blur="hideTooltip"
      @keydown.enter.prevent="toggleMobilePopover"
      @keydown.space.prevent="toggleMobilePopover"
      :aria-describedby="tooltipId"
      aria-label="More information"
      role="button"
      tabindex="0"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Desktop tooltip -->
    <transition name="tooltip-fade">
      <div
        v-show="isVisible && !isMobile"
        :id="tooltipId"
        role="tooltip"
        class="absolute z-50 max-w-xs bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg pointer-events-none"
        :class="positionClasses"
      >
        <div class="font-semibold mb-1">Why we ask:</div>
        <div>{{ why }}</div>
        <template v-if="howUsed">
          <div class="font-semibold mt-1.5 mb-0.5">How it's used:</div>
          <div>{{ howUsed }}</div>
        </template>
        <div class="mt-1.5 text-white/60 text-[11px]">Skip this if you're not sure — you can add it later.</div>
        <div class="tooltip-arrow" :class="arrowClasses"></div>
      </div>
    </transition>

    <!-- Mobile popover -->
    <transition name="tooltip-fade">
      <div
        v-show="isVisible && isMobile"
        :id="tooltipId"
        role="tooltip"
        class="absolute z-50 max-w-xs bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg left-0 top-full mt-2"
      >
        <div class="font-semibold mb-1">Why we ask:</div>
        <div>{{ why }}</div>
        <template v-if="howUsed">
          <div class="font-semibold mt-1.5 mb-0.5">How it's used:</div>
          <div>{{ howUsed }}</div>
        </template>
        <div class="mt-1.5 text-white/60 text-[11px]">Skip this if you're not sure — you can add it later.</div>
        <div class="mt-2 text-right">
          <button
            type="button"
            class="text-xs text-violet-200 hover:text-white font-medium transition-colors duration-150"
            @click.stop="hideTooltip"
          >
            Got it
          </button>
        </div>
        <div class="tooltip-arrow tooltip-arrow--top"></div>
      </div>
    </transition>
  </span>
</template>

<script>
import storage from '@/utils/storage';

let tooltipCounter = 0;

export default {
  name: 'InfoTooltip',

  props: {
    why: {
      type: String,
      required: true,
    },
    howUsed: {
      type: String,
      default: '',
    },
    position: {
      type: String,
      default: 'top',
      validator: (val) => ['top', 'bottom', 'left', 'right'].includes(val),
    },
  },

  data() {
    return {
      isVisible: false,
      isMobile: false,
      shouldPulse: false,
      tooltipId: `info-tooltip-${++tooltipCounter}`,
    };
  },

  computed: {
    positionClasses() {
      switch (this.position) {
        case 'bottom':
          return 'left-1/2 -translate-x-1/2 top-full mt-2';
        case 'left':
          return 'right-full mr-2 top-1/2 -translate-y-1/2';
        case 'right':
          return 'left-full ml-2 top-1/2 -translate-y-1/2';
        default: // top
          return 'left-1/2 -translate-x-1/2 bottom-full mb-2';
      }
    },
    arrowClasses() {
      switch (this.position) {
        case 'bottom':
          return 'tooltip-arrow--top';
        case 'left':
          return 'tooltip-arrow--right';
        case 'right':
          return 'tooltip-arrow--left';
        default:
          return 'tooltip-arrow--bottom';
      }
    },
  },

  mounted() {
    this.detectMobile();
    window.addEventListener('resize', this.detectMobile);
    document.addEventListener('click', this.handleClickOutside);

    if (!storage.session.get('tooltip_pulse_shown')) {
      this.shouldPulse = true;
      setTimeout(() => {
        this.shouldPulse = false;
        storage.session.set('tooltip_pulse_shown', '1');
      }, 2000);
    }
  },

  beforeUnmount() {
    window.removeEventListener('resize', this.detectMobile);
    document.removeEventListener('click', this.handleClickOutside);
  },

  methods: {
    detectMobile() {
      this.isMobile = window.matchMedia('(hover: none)').matches || window.innerWidth < 768;
    },

    showTooltip() {
      if (!this.isMobile) {
        this.isVisible = true;
      }
    },

    hideTooltip() {
      this.isVisible = false;
    },

    toggleMobilePopover() {
      if (this.isMobile) {
        this.isVisible = !this.isVisible;
      }
    },

    handleClickOutside(event) {
      if (this.isVisible && this.$refs.tooltipContainer && !this.$refs.tooltipContainer.contains(event.target)) {
        this.isVisible = false;
      }
    },
  },
};
</script>

<style scoped>
.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
  transition: opacity 0.15s ease;
}

.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
  opacity: 0;
}

.tooltip-arrow {
  position: absolute;
  width: 0;
  height: 0;
}

.tooltip-arrow--bottom {
  bottom: -4px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 5px solid theme('colors.horizon.500');
}

.tooltip-arrow--top {
  top: -4px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-bottom: 5px solid theme('colors.horizon.500');
}

.tooltip-arrow--left {
  left: -4px;
  top: 50%;
  transform: translateY(-50%);
  border-top: 5px solid transparent;
  border-bottom: 5px solid transparent;
  border-right: 5px solid theme('colors.horizon.500');
}

.tooltip-arrow--right {
  right: -4px;
  top: 50%;
  transform: translateY(-50%);
  border-top: 5px solid transparent;
  border-bottom: 5px solid transparent;
  border-left: 5px solid theme('colors.horizon.500');
}
</style>
