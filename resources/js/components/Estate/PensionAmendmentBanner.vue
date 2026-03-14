<template>
  <transition name="fade">
    <div
      v-if="show && !dismissed"
      class="pension-amendment-banner"
    >
      <div class="banner-content">
        <div class="banner-icon">
          <svg
            class="w-5 h-5 text-violet-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>

        <div class="banner-body">
          <h4 class="banner-title">Upcoming Change: Pension Inheritance Tax Rules</h4>
          <p class="banner-text">
            From April 2027, unused pension funds may be included in your estate for
            Inheritance Tax purposes. Under current rules, your estimated liability is
            <strong>{{ formatCurrency(currentLiability) }}</strong>.
            If the change goes ahead, this could increase to
            <strong>{{ formatCurrency(amendedLiability) }}</strong>.
          </p>

          <!-- Impact summary -->
          <div v-if="impactAmount > 0" class="impact-summary">
            <div class="impact-item">
              <span class="impact-label">Current Liability</span>
              <span class="impact-value">{{ formatCurrency(currentLiability) }}</span>
            </div>
            <div class="impact-arrow">
              <svg class="w-5 h-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </div>
            <div class="impact-item">
              <span class="impact-label">Potential Liability</span>
              <span class="impact-value amended">{{ formatCurrency(amendedLiability) }}</span>
            </div>
            <div class="impact-item increase">
              <span class="impact-label">Potential Increase</span>
              <span class="impact-value increase-value">+{{ formatCurrency(impactAmount) }}</span>
            </div>
          </div>
        </div>

        <!-- Dismiss button -->
        <button
          class="banner-dismiss"
          aria-label="Dismiss notification"
          @click="dismiss"
        >
          <svg
            class="w-5 h-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PensionAmendmentBanner',

  mixins: [currencyMixin],

  props: {
    show: {
      type: Boolean,
      default: false,
    },
    currentScenario: {
      type: Object,
      default: () => ({}),
    },
    amendedScenario: {
      type: Object,
      default: () => ({}),
    },
  },

  emits: ['dismiss'],

  data() {
    return {
      dismissed: false,
    };
  },

  computed: {
    currentLiability() {
      return this.currentScenario?.iht_liability ||
             this.currentScenario?.total_iht ||
             this.currentScenario?.liability ||
             0;
    },

    amendedLiability() {
      return this.amendedScenario?.iht_liability ||
             this.amendedScenario?.total_iht ||
             this.amendedScenario?.liability ||
             0;
    },

    impactAmount() {
      return Math.max(0, this.amendedLiability - this.currentLiability);
    },
  },

  methods: {
    dismiss() {
      this.dismissed = true;
      this.$emit('dismiss');
    },
  },
};
</script>

<style scoped>
.pension-amendment-banner {
  @apply bg-violet-50;
  @apply border-l-4 border-violet-500;
  border-radius: 0 12px 12px 0;
  overflow: hidden;
}

.banner-content {
  display: flex;
  gap: 14px;
  padding: 16px 20px;
  align-items: flex-start;
}

.banner-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.banner-body {
  flex: 1;
  min-width: 0;
}

.banner-title {
  font-size: 15px;
  font-weight: 700;
  @apply text-violet-800;
  margin: 0 0 8px 0;
}

.banner-text {
  font-size: 14px;
  @apply text-violet-700;
  line-height: 1.6;
  margin: 0;
}

.banner-text strong {
  font-weight: 700;
  @apply text-violet-900;
}

/* Impact summary */
.impact-summary {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 16px;
  padding: 12px 16px;
  background: white;
  border-radius: 8px;
  @apply border border-violet-200;
}

.impact-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.impact-item.increase {
  margin-left: auto;
  padding-left: 16px;
  @apply border-l border-violet-200;
}

.impact-label {
  font-size: 11px;
  font-weight: 600;
  @apply text-neutral-500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.impact-value {
  font-size: 16px;
  font-weight: 700;
  @apply text-horizon-500;
}

.impact-value.amended {
  @apply text-violet-700;
}

.impact-value.increase-value {
  @apply text-raspberry-600;
}

.impact-arrow {
  flex-shrink: 0;
}

/* Dismiss button */
.banner-dismiss {
  flex-shrink: 0;
  padding: 4px;
  @apply text-violet-400;
  background: none;
  border: none;
  cursor: pointer;
  border-radius: 4px;
  transition: color 0.15s ease, background-color 0.15s ease;
}

.banner-dismiss:hover {
  @apply text-violet-700;
  @apply bg-violet-100;
}

/* Fade transition */
.fade-enter-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}

.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (max-width: 640px) {
  .banner-content {
    padding: 14px 16px;
  }

  .banner-title {
    font-size: 14px;
  }

  .banner-text {
    font-size: 13px;
  }

  .impact-summary {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .impact-arrow {
    transform: rotate(90deg);
    align-self: center;
  }

  .impact-item.increase {
    margin-left: 0;
    padding-left: 0;
    padding-top: 10px;
    border-left: none;
    @apply border-t border-violet-200;
    width: 100%;
  }
}
</style>
