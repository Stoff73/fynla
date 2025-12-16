<template>
  <div class="risk-level-selector">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-2">
      {{ label }}
    </label>

    <!-- Risk Level Buttons -->
    <div class="flex gap-1 sm:gap-2">
      <button
        v-for="level in riskLevels"
        :key="level.value"
        type="button"
        :disabled="!isLevelAllowed(level.value)"
        class="flex-1 py-2 px-1 sm:px-3 rounded-lg text-xs sm:text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-offset-1"
        :class="getButtonClasses(level.value)"
        :style="getButtonStyle(level.value)"
        @click="selectLevel(level.value)"
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
            class="ml-2 text-gray-400 hover:text-gray-600"
            @click="showInfo = false"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Asset Allocation (optional) -->
        <div v-if="showAllocation && selectedLevelConfig?.allocation" class="mt-4">
          <h5 class="text-xs font-medium text-gray-600 mb-2">Typical Asset Allocation</h5>
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
              <span class="text-xs text-gray-600 mt-1 block capitalize">{{ asset.type }}</span>
            </div>
          </div>
        </div>

        <!-- Expected Returns (optional) -->
        <div v-if="showReturns && selectedLevelConfig?.returns" class="mt-4 pt-3 border-t border-gray-200">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Expected Return Range:</span>
            <span class="font-medium text-gray-900">
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
      return this.allowedLevels.includes(level);
    },

    selectLevel(level) {
      if (!this.isLevelAllowed(level)) return;
      this.$emit('update:modelValue', level);
      this.$emit('change', level);
      this.showInfo = true;
    },

    getButtonStyle(level) {
      const isSelected = this.modelValue === level;
      const isAllowed = this.isLevelAllowed(level);

      // Color definitions (using inline styles as fallback)
      const colors = {
        low: { bg: '#16a34a', bgLight: '#f0fdf4', border: '#bbf7d0', text: '#15803d' },
        lower_medium: { bg: '#0d9488', bgLight: '#f0fdfa', border: '#99f6e4', text: '#0f766e' },
        medium: { bg: '#2563eb', bgLight: '#eff6ff', border: '#bfdbfe', text: '#1d4ed8' },
        upper_medium: { bg: '#d97706', bgLight: '#fffbeb', border: '#fde68a', text: '#b45309' },
        high: { bg: '#dc2626', bgLight: '#fef2f2', border: '#fecaca', text: '#b91c1c' },
      };

      const color = colors[level] || colors.medium;

      if (!isAllowed) {
        return {
          backgroundColor: '#f3f4f6',
          color: '#9ca3af',
          borderWidth: '1px',
          borderColor: '#e5e7eb',
        };
      }

      if (isSelected) {
        return {
          backgroundColor: color.bg,
          color: '#ffffff',
          boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        };
      }

      return {
        backgroundColor: color.bgLight,
        color: color.text,
        borderWidth: '1px',
        borderColor: color.border,
      };
    },

    getButtonClasses(level) {
      // Keep this for hover states and other Tailwind utilities
      const isSelected = this.modelValue === level;
      const isAllowed = this.isLevelAllowed(level);

      if (!isAllowed) {
        return 'cursor-not-allowed';
      }

      if (isSelected) {
        return '';
      }

      // Unselected - add hover effect
      switch (level) {
        case 'low':
          return 'hover:bg-green-100';
        case 'lower_medium':
          return 'hover:bg-teal-100';
        case 'medium':
          return 'hover:bg-blue-100';
        case 'upper_medium':
          return 'hover:bg-amber-100';
        case 'high':
          return 'hover:bg-red-100';
        default:
          return 'hover:bg-gray-100';
      }
    },

    getInfoPanelClasses() {
      const level = this.modelValue;
      const classes = {
        low: 'bg-green-50 border-green-200',
        lower_medium: 'bg-teal-50 border-teal-200',
        medium: 'bg-blue-50 border-blue-200',
        upper_medium: 'bg-amber-50 border-amber-200',
        high: 'bg-red-50 border-red-200',
      };
      return classes[level] || 'bg-gray-50 border-gray-200';
    },

    getInfoTextClasses() {
      const level = this.modelValue;
      const classes = {
        low: 'text-green-800',
        lower_medium: 'text-teal-800',
        medium: 'text-blue-800',
        upper_medium: 'text-amber-800',
        high: 'text-red-800',
      };
      return classes[level] || 'text-gray-800';
    },

    getInfoDescClasses() {
      const level = this.modelValue;
      const classes = {
        low: 'text-green-700',
        lower_medium: 'text-teal-700',
        medium: 'text-blue-700',
        upper_medium: 'text-amber-700',
        high: 'text-red-700',
      };
      return classes[level] || 'text-gray-700';
    },

    getAssetBarClasses(assetType) {
      return {
        equity: 'bg-purple-100',
        bond: 'bg-blue-100',
        cash: 'bg-green-100',
      }[assetType] || 'bg-gray-100';
    },

    getAssetFillClasses(assetType) {
      return {
        equity: 'bg-purple-500',
        bond: 'bg-blue-500',
        cash: 'bg-green-500',
      }[assetType] || 'bg-gray-500';
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
