<template>
  <div class="risk-level-selector">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-neutral-500 mb-2">
      {{ label }}
    </label>

    <!-- Risk Level Cards -->
    <div class="flex gap-1 sm:gap-2">
      <button
        v-for="level in riskLevels"
        :key="level.value"
        type="button"
        :disabled="!isLevelAllowed(level.value)"
        class="flex-1 py-2 px-1 sm:px-3 rounded-lg text-xs sm:text-sm font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 relative"
        :class="getButtonClasses(level.value)"
        :style="getButtonStyle(level.value)"
        @click="selectLevel(level.value)"
        @mouseenter="hoveredLevel = level.value"
        @mouseleave="hoveredLevel = null"
      >
        <span class="hidden sm:inline">{{ level.label }}</span>
        <span class="sm:hidden">{{ level.shortLabel }}</span>
      </button>
    </div>

    <!-- Selected Level Info (expandable) -->
    <transition name="fade-slide">
      <div
        v-if="modelValue && showInfo"
        class="mt-3 p-4 rounded-lg border"
        :class="getInfoPanelClasses()"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h4 class="text-sm font-semibold" :class="getInfoTextClasses()">
              {{ selectedLevelConfig?.label }}
            </h4>
            <p class="text-sm mt-1" :class="getInfoDescClasses()">
              {{ selectedLevelConfig?.description }}
            </p>
          </div>
          <button
            v-if="collapsible"
            type="button"
            class="ml-2 text-horizon-400 hover:text-neutral-500"
            @click="showInfo = false"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Asset Allocation (optional) -->
        <div v-if="showAllocation && selectedLevelConfig?.allocation" class="mt-4">
          <h5 class="text-xs font-medium text-neutral-500 mb-2">Typical Asset Allocation</h5>
          <div class="flex gap-2">
            <div
              v-for="asset in selectedLevelConfig.allocation"
              :key="asset.type"
              class="flex-1 text-center"
            >
              <div
                class="h-16 rounded relative overflow-hidden"
                :class="getAssetBarClasses(asset.type)"
              >
                <div
                  class="absolute bottom-0 left-0 right-0 transition-all duration-300"
                  :class="getAssetFillClasses(asset.type)"
                  :style="{ height: asset.percentage + '%' }"
                ></div>
                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white drop-shadow">
                  {{ asset.percentage }}%
                </span>
              </div>
              <span class="text-xs text-neutral-500 mt-1 block capitalize">{{ asset.type }}</span>
            </div>
          </div>
        </div>

        <!-- Expected Returns (optional) -->
        <div v-if="showReturns && selectedLevelConfig?.returns" class="mt-4 pt-3 border-t border-light-gray">
          <div class="flex justify-between text-sm">
            <span class="text-neutral-500">Expected Return Range:</span>
            <span class="font-medium text-horizon-500">
              {{ selectedLevelConfig.returns.low }}% - {{ selectedLevelConfig.returns.high }}%
            </span>
          </div>
        </div>
      </div>
    </transition>

    <!-- Toggle Info Button (when collapsed) -->
    <button
      v-if="modelValue && collapsible && !showInfo"
      type="button"
      class="mt-2 text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1"
      @click="showInfo = true"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      View risk level details
    </button>
  </div>
</template>

<script>
import { RISK_COLORS, TEXT_COLORS, BORDER_COLORS, BG_COLORS } from '@/constants/designSystem';

export default {
  name: 'RiskLevelSelector',

  props: {
    modelValue: {
      type: String,
      default: null,
    },
    label: {
      type: String,
      default: null,
    },
    allowedLevels: {
      type: Array,
      default: () => ['low', 'lower_medium', 'medium', 'upper_medium', 'high'],
    },
    profileLevel: {
      type: String,
      default: null,
      validator: (value) => !value || ['low', 'lower_medium', 'medium', 'upper_medium', 'high'].includes(value),
    },
    compact: {
      type: Boolean,
      default: false,
    },
    showAllocation: {
      type: Boolean,
      default: true,
    },
    showReturns: {
      type: Boolean,
      default: true,
    },
    collapsible: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    riskConfig: {
      type: Object,
      default: null,
    },
  },

  emits: ['update:modelValue', 'change'],

  data() {
    return {
      showInfo: true,
      hoveredLevel: null,
      defaultRiskLevels: [
        {
          value: 'low',
          label: 'Low',
          shortLabel: 'Low',
          description: 'Prioritises capital preservation. Prefers minimal volatility and accepts lower returns.',
          allocation: [
            { type: 'equity', percentage: 10 },
            { type: 'bond', percentage: 70 },
            { type: 'cash', percentage: 20 },
          ],
          returns: { low: 2, high: 4 },
        },
        {
          value: 'lower_medium',
          label: 'Lower-Medium',
          shortLabel: 'L-Med',
          description: 'Seeks stability with modest growth. Comfortable with small fluctuations.',
          allocation: [
            { type: 'equity', percentage: 30 },
            { type: 'bond', percentage: 55 },
            { type: 'cash', percentage: 15 },
          ],
          returns: { low: 3, high: 5 },
        },
        {
          value: 'medium',
          label: 'Medium',
          shortLabel: 'Med',
          description: 'Balanced approach. Accepts moderate volatility for reasonable growth potential.',
          allocation: [
            { type: 'equity', percentage: 50 },
            { type: 'bond', percentage: 40 },
            { type: 'cash', percentage: 10 },
          ],
          returns: { low: 4, high: 6 },
        },
        {
          value: 'upper_medium',
          label: 'Upper-Medium',
          shortLabel: 'U-Med',
          description: 'Prioritises growth. Comfortable with significant short-term fluctuations.',
          allocation: [
            { type: 'equity', percentage: 75 },
            { type: 'bond', percentage: 20 },
            { type: 'cash', percentage: 5 },
          ],
          returns: { low: 5, high: 8 },
        },
        {
          value: 'high',
          label: 'High',
          shortLabel: 'High',
          description: 'Seeks maximum growth. Accepts high volatility and potential for substantial losses.',
          allocation: [
            { type: 'equity', percentage: 90 },
            { type: 'bond', percentage: 5 },
            { type: 'cash', percentage: 5 },
          ],
          returns: { low: 6, high: 10 },
        },
      ],
    };
  },

  computed: {
    riskLevels() {
      if (this.riskConfig) {
        return this.riskConfig;
      }
      return this.defaultRiskLevels;
    },

    selectedLevelConfig() {
      return this.riskLevels.find(l => l.value === this.modelValue);
    },
  },

  methods: {
    isLevelAllowed(level) {
      if (this.disabled) return false;
      // Handle both array of strings ['low', 'medium'] and array of objects [{key: 'low'}, ...]
      return this.allowedLevels.some(allowed =>
        typeof allowed === 'string' ? allowed === level : allowed.key === level
      );
    },

    selectLevel(level) {
      if (!this.isLevelAllowed(level)) return;
      this.$emit('update:modelValue', level);
      this.$emit('change', level);
      this.showInfo = true;
    },

    getButtonStyle(level) {
      const isSelected = this.modelValue === level;
      const isProfileLevel = this.profileLevel === level;
      const isAllowed = this.isLevelAllowed(level);
      const isHovered = this.hoveredLevel === level;

      // Use design system risk colors
      const color = RISK_COLORS[level] || RISK_COLORS.medium;

      // Disabled/not allowed - grey
      if (!isAllowed) {
        return {
          'background-color': BORDER_COLORS.default,
          'color': TEXT_COLORS.placeholder,
          'border': `1px solid ${BORDER_COLORS.hover}`,
          'opacity': '0.6',
        };
      }

      // Selected state OR profile level (when no selection made) - full solid color
      if (isSelected || (isProfileLevel && !this.modelValue)) {
        return {
          'background-color': color.bg,
          'color': BG_COLORS.card,
          'border': `2px solid ${color.bg}`,
          'box-shadow': '0 2px 4px 0 rgba(0, 0, 0, 0.15)',
        };
      }

      // Profile level when something else is selected - medium highlight
      if (isProfileLevel) {
        return {
          'background-color': color.bgLight,
          'color': color.text,
          'border': `2px solid ${color.border}`,
        };
      }

      // Allowed but not selected or profile - grey by default, bold color on hover
      if (isHovered) {
        return {
          'background-color': color.bg,
          'color': BG_COLORS.card,
          'border': `2px solid ${color.bg}`,
          'box-shadow': '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
        };
      }

      return {
        'background-color': BG_COLORS.subtle,
        'color': TEXT_COLORS.muted,
        'border': `2px solid ${BORDER_COLORS.hover}`,
      };
    },

    getButtonClasses(level) {
      const isSelected = this.modelValue === level;
      const isProfileLevel = this.profileLevel === level;
      const isAllowed = this.isLevelAllowed(level);

      if (!isAllowed) {
        return 'cursor-not-allowed opacity-50';
      }

      // Selected OR profile level (when nothing selected) - show ring
      if (isSelected || (isProfileLevel && !this.modelValue)) {
        return 'ring-2 ring-offset-1';
      }

      // Allowed but not selected/active - just cursor pointer (hover handled via inline styles)
      return 'cursor-pointer';
    },

    getInfoPanelClasses() {
      const level = this.modelValue;
      const classes = {
        low: 'bg-violet-50 border-violet-200',
        lower_medium: 'bg-pink-50 border-pink-200',
        medium: 'bg-green-50 border-green-200',
        upper_medium: 'bg-teal-50 border-teal-200',
        high: 'bg-blue-50 border-blue-200',
      };
      return classes[level] || 'bg-eggshell-500 border-light-gray';
    },

    getInfoTextClasses() {
      const level = this.modelValue;
      const classes = {
        low: 'text-violet-800',
        lower_medium: 'text-pink-800',
        medium: 'text-green-800',
        upper_medium: 'text-teal-800',
        high: 'text-blue-800',
      };
      return classes[level] || 'text-horizon-500';
    },

    getInfoDescClasses() {
      const level = this.modelValue;
      const classes = {
        low: 'text-violet-700',
        lower_medium: 'text-pink-700',
        medium: 'text-green-700',
        upper_medium: 'text-teal-700',
        high: 'text-blue-700',
      };
      return classes[level] || 'text-neutral-500';
    },

    getAssetBarClasses(assetType) {
      return {
        equity: 'bg-purple-100',
        bond: 'bg-blue-100',
        cash: 'bg-green-100',
      }[assetType] || 'bg-savannah-100';
    },

    getAssetFillClasses(assetType) {
      return {
        equity: 'bg-purple-500',
        bond: 'bg-blue-500',
        cash: 'bg-green-500',
      }[assetType] || 'bg-neutral-500';
    },
  },
};
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.2s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
