<template>
  <span
    :class="[
      'inline-flex items-center gap-1 rounded-full font-medium',
      sizeClasses,
      colorClasses,
      hasCustomRisk ? 'ring-2 ring-blue-300 ring-offset-1' : ''
    ]"
    :title="tooltipText"
  >
    <!-- Risk indicator icon -->
    <svg
      v-if="showIcon"
      :class="iconSizeClasses"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M13 10V3L4 14h7v7l9-11h-7z"
      />
    </svg>
    <span>{{ displayLabel }}</span>
  </span>
</template>

<script>
import {
  RISK_ABBREVIATED_LABELS,
  RISK_DESCRIPTIONS,
  RISK_LEGACY_MAP,
  getRiskClasses,
  getRiskDisplayName,
} from '@/constants/designSystem';

export default {
  name: 'RiskBadge',

  props: {
    level: {
      type: String,
      required: true,
      validator: (value) => [
        'low',
        'lower_medium',
        'medium',
        'upper_medium',
        'high',
        // Legacy values
        'cautious',
        'balanced',
        'adventurous',
      ].includes(value),
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
    abbreviated: {
      type: Boolean,
      default: false,
    },
    showIcon: {
      type: Boolean,
      default: false,
    },
    hasCustomRisk: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    normalizedLevel() {
      // Map legacy values to new system using centralized constant
      return RISK_LEGACY_MAP[this.level] || this.level;
    },

    displayLabel() {
      if (this.abbreviated) {
        return RISK_ABBREVIATED_LABELS[this.normalizedLevel] || this.level;
      }
      // For non-abbreviated, append "Risk" to low and high levels
      const name = getRiskDisplayName(this.normalizedLevel);
      if (this.normalizedLevel === 'low' || this.normalizedLevel === 'high') {
        return `${name} Risk`;
      }
      return name;
    },

    tooltipText() {
      let text = RISK_DESCRIPTIONS[this.normalizedLevel] || '';
      if (this.hasCustomRisk) {
        text += ' (Custom setting)';
      }
      return text;
    },

    sizeClasses() {
      return {
        xs: 'px-1.5 py-0.5 text-xs',
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-1 text-sm',
        lg: 'px-3 py-1.5 text-sm',
      }[this.size];
    },

    iconSizeClasses() {
      return {
        xs: 'w-3 h-3',
        sm: 'w-3 h-3',
        md: 'w-4 h-4',
        lg: 'w-5 h-5',
      }[this.size];
    },

    colorClasses() {
      return getRiskClasses(this.normalizedLevel).combined;
    },
  },
};
</script>
