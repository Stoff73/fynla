<template>
  <span
    :class="badgeClass"
    class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold cursor-help"
    :title="tooltipText"
  >
    <svg
      v-if="level === 'high' || level === 'very_high'"
      class="w-4 h-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M5 13l4 4L19 7"
      />
    </svg>
    <span v-else-if="level === 'medium'">?</span>
    <span v-else>!</span>
  </span>
</template>

<script>
export default {
  name: 'ConfidenceBadge',

  props: {
    /**
     * Confidence level: 'very_high', 'high', 'medium', 'low', 'very_low'
     * Or a numeric value between 0 and 1
     */
    confidence: {
      type: [String, Number],
      required: true,
    },
  },

  computed: {
    level() {
      if (typeof this.confidence === 'string') {
        return this.confidence;
      }

      // Convert numeric confidence to level
      const value = parseFloat(this.confidence);
      if (value >= 0.95) return 'very_high';
      if (value >= 0.8) return 'high';
      if (value >= 0.6) return 'medium';
      if (value >= 0.4) return 'low';
      return 'very_low';
    },

    badgeClass() {
      return {
        'bg-green-100 text-green-800': this.level === 'very_high' || this.level === 'high',
        'bg-orange-100 text-orange-800': this.level === 'medium',
        'bg-red-100 text-red-800': this.level === 'low' || this.level === 'very_low',
      };
    },

    tooltipText() {
      const percentage = typeof this.confidence === 'number'
        ? `(${Math.round(this.confidence * 100)}%)`
        : '';

      const labels = {
        very_high: 'Very high confidence - automatically extracted',
        high: 'High confidence - please verify',
        medium: 'Medium confidence - manual verification recommended',
        low: 'Low confidence - please check carefully',
        very_low: 'Very low confidence - manual entry may be needed',
      };

      return `${labels[this.level]} ${percentage}`.trim();
    },
  },
};
</script>
