<template>
  <div class="decision-path">
    <!-- Header with collapse toggle -->
    <button
      v-if="collapsible"
      class="decision-header"
      @click="toggleExpanded"
    >
      <div class="flex items-center gap-2">
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
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
          />
        </svg>
        <span class="text-sm font-semibold text-horizon-500">Decision Trail</span>
      </div>
      <svg
        :class="['w-5 h-5 text-horizon-400 transition-transform duration-200', { 'rotate-180': isExpanded }]"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M19 9l-7 7-7-7"
        />
      </svg>
    </button>

    <div v-else class="decision-header-static">
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
          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
        />
      </svg>
      <span class="text-sm font-semibold text-horizon-500">Decision Trail</span>
    </div>

    <!-- Collapsible content -->
    <transition
      name="expand"
      @enter="onEnter"
      @after-enter="onAfterEnter"
      @leave="onLeave"
    >
      <div v-show="isExpanded || !collapsible" class="decision-body">
        <!-- Timeline steps -->
        <div class="timeline">
          <div
            v-for="(step, index) in steps"
            :key="index"
            class="timeline-step"
          >
            <!-- Dot and connecting line -->
            <div class="timeline-indicator">
              <div
                :class="[
                  'timeline-dot',
                  step.passed ? 'dot-passed' : 'dot-failed'
                ]"
              >
                <svg
                  v-if="step.passed"
                  class="w-3 h-3 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="3"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <svg
                  v-else
                  class="w-3 h-3 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="3"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </div>
              <div
                v-if="index < steps.length - 1"
                :class="[
                  'timeline-line',
                  step.passed ? 'line-passed' : 'line-failed'
                ]"
              ></div>
            </div>

            <!-- Step content -->
            <div class="timeline-content">
              <p
                :class="[
                  'step-question',
                  step.passed ? 'text-horizon-600' : 'text-horizon-800 font-semibold'
                ]"
              >
                {{ step.question }}
              </p>
              <p
                :class="[
                  'step-answer',
                  step.passed ? 'text-spring-600' : 'text-raspberry-600'
                ]"
              >
                {{ step.answer }}
              </p>
            </div>
          </div>
        </div>

        <!-- Outcome bar -->
        <div v-if="outcome" class="outcome-bar">
          <svg
            class="w-5 h-5 text-violet-600 flex-shrink-0"
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
          <span class="outcome-text">{{ outcome }}</span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'SavingsDecisionPath',

  props: {
    steps: {
      type: Array,
      required: true,
      validator(value) {
        return value.every(
          step => typeof step.question === 'string' &&
                  typeof step.answer === 'string' &&
                  typeof step.passed === 'boolean'
        );
      },
    },
    outcome: {
      type: String,
      default: '',
    },
    collapsible: {
      type: Boolean,
      default: true,
    },
  },

  data() {
    return {
      isExpanded: !this.collapsible,
    };
  },

  methods: {
    toggleExpanded() {
      this.isExpanded = !this.isExpanded;
    },

    onEnter(el) {
      el.style.height = '0';
      el.style.overflow = 'hidden';
      // Force reflow
      void el.offsetHeight;
      el.style.height = el.scrollHeight + 'px';
      el.style.transition = 'height 0.3s ease';
    },

    onAfterEnter(el) {
      el.style.height = '';
      el.style.overflow = '';
      el.style.transition = '';
    },

    onLeave(el) {
      el.style.height = el.scrollHeight + 'px';
      el.style.overflow = 'hidden';
      // Force reflow
      void el.offsetHeight;
      el.style.height = '0';
      el.style.transition = 'height 0.3s ease';
    },
  },
};
</script>

<style scoped>
.decision-path {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  overflow: hidden;
}

.decision-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 16px 20px;
  background: none;
  border: none;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.decision-header:hover {
  @apply bg-savannah-100;
}

.decision-header-static {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
}

.decision-body {
  padding: 0 20px 20px;
}

/* Timeline */
.timeline {
  position: relative;
  padding-top: 4px;
}

.timeline-step {
  display: flex;
  gap: 14px;
  position: relative;
}

.timeline-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.timeline-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  z-index: 1;
}

.dot-passed {
  @apply bg-spring-500;
}

.dot-failed {
  @apply bg-raspberry-500;
}

.timeline-line {
  width: 2px;
  flex: 1;
  min-height: 24px;
  margin: 4px 0;
}

.line-passed {
  @apply bg-spring-200;
}

.line-failed {
  @apply bg-raspberry-200;
}

.timeline-content {
  padding-bottom: 20px;
  flex: 1;
  min-width: 0;
}

.timeline-step:last-child .timeline-content {
  padding-bottom: 0;
}

.step-question {
  font-size: 13px;
  line-height: 1.4;
  margin: 0 0 2px 0;
}

.step-answer {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.4;
  margin: 0;
}

/* Outcome bar */
.outcome-bar {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 16px;
  padding: 14px 16px;
  @apply bg-violet-50;
  @apply border-l-4 border-violet-500;
  border-radius: 0 8px 8px 0;
}

.outcome-text {
  font-size: 14px;
  font-weight: 600;
  @apply text-violet-800;
  line-height: 1.5;
}

/* Expand transition */
.expand-enter-active,
.expand-leave-active {
  overflow: hidden;
}

@media (max-width: 640px) {
  .decision-header,
  .decision-header-static {
    padding: 12px 16px;
  }

  .decision-body {
    padding: 0 16px 16px;
  }

  .timeline-content {
    padding-bottom: 16px;
  }

  .step-question,
  .step-answer {
    font-size: 12px;
  }

  .outcome-text {
    font-size: 13px;
  }
}
</style>
