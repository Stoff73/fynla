<template>
  <div
    class="event-icon flex items-center justify-center rounded-full shadow-sm border border-white/30 transition-opacity"
    :class="{ 'opacity-40': isCompleted }"
    :style="{
      width: `${size}px`,
      height: `${size}px`,
      backgroundColor: color,
    }"
  >
    <svg
      :width="iconSize"
      :height="iconSize"
      viewBox="0 0 24 24"
      fill="none"
      stroke="white"
      stroke-width="1.5"
      stroke-linecap="round"
      stroke-linejoin="round"
      class="flex-shrink-0"
    >
      <path :d="svgPath" />
    </svg>
  </div>
</template>

<script>
import { EVENT_ICONS } from '@/constants/eventIcons';
import { getIconSvg } from '@/constants/eventIconSvgs';

export default {
  name: 'EventIcon',

  props: {
    event: {
      type: Object,
      required: true,
    },
    size: {
      type: Number,
      default: 22,
    },
    isCompleted: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    iconName() {
      // Try to get icon from event directly
      if (this.event.icon) {
        return this.event.icon;
      }

      // Look up from constants based on category
      const category = this.event.category || this.event.type;
      const config = EVENT_ICONS[category];
      return config?.icon || 'FlagIcon';
    },

    svgPath() {
      const svg = getIconSvg(this.iconName);
      return svg?.d || '';
    },

    color() {
      return this.event.color || '#64748B';
    },

    iconSize() {
      // Icon SVG should be ~60% of the circle size
      return Math.round(this.size * 0.6);
    },
  },
};
</script>

<style scoped>
.event-icon {
  flex-shrink: 0;
}
</style>
