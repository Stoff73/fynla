<template>
  <div
    v-if="visible"
    class="strategy-disclaimer"
    :class="[variant, { 'dismissible': dismissible && !alwaysShow }]"
  >
    <div class="disclaimer-content">
      <div class="disclaimer-icon">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      <div class="disclaimer-text">
        <p class="disclaimer-title">{{ title }}</p>
        <p class="disclaimer-body">{{ message }}</p>
        <slot></slot>
      </div>
      <button
        v-if="dismissible && !alwaysShow"
        class="dismiss-button"
        @click="dismiss"
        aria-label="Dismiss"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            clip-rule="evenodd"
          />
        </svg>
      </button>
    </div>
  </div>
</template>

<script>
import storage from '@/utils/storage';

export default {
  name: 'StrategyDisclaimer',
  emits: ['dismissed'],
  props: {
    variant: {
      type: String,
      default: 'info', // info, warning, important
      validator: (value) => ['info', 'warning', 'important'].includes(value),
    },
    type: {
      type: String,
      default: 'general', // general, investment, retirement, protection, estate
      validator: (value) =>
        ['general', 'investment', 'retirement', 'protection', 'estate'].includes(value),
    },
    title: {
      type: String,
      default: 'Important Information',
    },
    message: {
      type: String,
      default: '',
    },
    dismissible: {
      type: Boolean,
      default: true,
    },
    alwaysShow: {
      type: Boolean,
      default: false,
    },
    storageKey: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      visible: true,
    };
  },
  computed: {
    disclaimerMessage() {
      if (this.message) return this.message;

      const messages = {
        general:
          'The strategies presented are for informational purposes only and do not constitute financial advice. Your personal circumstances may require different approaches. Please consult a qualified financial adviser before making important financial decisions.',
        investment:
          'Investment strategies involve risk. Past performance is not indicative of future results. The value of investments can fall as well as rise, and you may get back less than you invest. These projections are based on assumptions that may not reflect actual market conditions.',
        retirement:
          'Retirement projections are estimates based on current assumptions about inflation, returns, and tax rules. Actual outcomes may differ significantly. Pension rules and tax regulations may change. Consider seeking advice from a pension specialist.',
        protection:
          'Protection recommendations are based on general guidelines and may not reflect your specific needs. Policy terms, exclusions, and premiums vary between providers. Always read policy documents carefully and consider seeking advice from a protection specialist.',
        estate:
          'Estate planning strategies are based on current tax rules which may change. Inheritance tax calculations are estimates and actual liability may differ. Trusts and other arrangements may have legal implications. Professional legal and tax advice is recommended.',
      };

      return messages[this.type] || messages.general;
    },
    effectiveStorageKey() {
      return this.storageKey || `disclaimer_dismissed_${this.type}`;
    },
  },
  mounted() {
    if (this.dismissible && !this.alwaysShow) {
      const dismissed = storage.get(this.effectiveStorageKey);
      if (dismissed) {
        this.visible = false;
      }
    }
  },
  methods: {
    dismiss() {
      this.visible = false;
      if (this.dismissible) {
        storage.set(this.effectiveStorageKey, 'true');
      }
      this.$emit('dismissed');
    },
    show() {
      this.visible = true;
      storage.remove(this.effectiveStorageKey);
    },
  },
};
</script>

<style scoped>
.strategy-disclaimer {
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.strategy-disclaimer.info {
  @apply bg-blue-50;
  @apply border border-blue-200;
}

.strategy-disclaimer.warning {
  @apply bg-violet-50;
  @apply border border-blue-300;
}

.strategy-disclaimer.important {
  @apply bg-red-50;
  @apply border border-red-200;
}

.disclaimer-content {
  display: flex;
  gap: 0.75rem;
}

.disclaimer-icon {
  flex-shrink: 0;
}

.strategy-disclaimer.info .disclaimer-icon {
  @apply text-raspberry-500;
}

.strategy-disclaimer.warning .disclaimer-icon {
  @apply text-blue-500;
}

.strategy-disclaimer.important .disclaimer-icon {
  @apply text-red-500;
}

.disclaimer-text {
  flex: 1;
}

.disclaimer-title {
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 0.25rem;
}

.strategy-disclaimer.info .disclaimer-title {
  @apply text-blue-800;
}

.strategy-disclaimer.warning .disclaimer-title {
  @apply text-blue-800;
}

.strategy-disclaimer.important .disclaimer-title {
  @apply text-red-800;
}

.disclaimer-body {
  font-size: 0.875rem;
  line-height: 1.5;
}

.strategy-disclaimer.info .disclaimer-body {
  @apply text-blue-900;
}

.strategy-disclaimer.warning .disclaimer-body {
  @apply text-blue-900;
}

.strategy-disclaimer.important .disclaimer-body {
  @apply text-red-900;
}

.dismiss-button {
  flex-shrink: 0;
  padding: 0.25rem;
  border-radius: 0.25rem;
  background: transparent;
  border: none;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.15s;
}

.dismiss-button:hover {
  opacity: 1;
}

.strategy-disclaimer.info .dismiss-button {
  @apply text-raspberry-500;
}

.strategy-disclaimer.warning .dismiss-button {
  @apply text-blue-500;
}

.strategy-disclaimer.important .dismiss-button {
  @apply text-red-500;
}
</style>
