<template>
  <div class="investment-readiness-gate">
    <!-- Cannot proceed: show full gate -->
    <div v-if="!canProceed" class="gate-blocked">
      <div class="gate-header">
        <div class="gate-icon">
          <svg
            class="w-8 h-8 text-raspberry-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
        </div>
        <div>
          <h3 class="gate-title">More Information Needed</h3>
          <p class="gate-subtitle">
            We need some additional information before we can provide investment recommendations.
            Please complete the items below.
          </p>
        </div>
      </div>

      <!-- Progress indicator -->
      <div class="progress-bar-container">
        <div class="progress-bar">
          <div
            class="progress-fill"
            :style="{ width: progressPercent + '%' }"
          ></div>
        </div>
        <span class="progress-label">{{ passedCount }} of {{ totalCount }} checks passed</span>
      </div>

      <!-- Blocking checks -->
      <div v-if="blockingChecks.length > 0" class="check-section">
        <div class="section-label blocking-label">
          <svg class="w-4 h-4 text-raspberry-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span>Required</span>
        </div>
        <div class="check-list">
          <div
            v-for="check in blockingChecks"
            :key="check.key"
            class="check-row blocking"
          >
            <div class="check-status">
              <div class="status-dot blocking-dot"></div>
            </div>
            <div class="check-details">
              <span class="check-message">{{ check.message }}</span>
              <span v-if="check.description" class="check-description">{{ check.description }}</span>
            </div>
            <router-link
              v-if="check.form_link"
              :to="check.form_link"
              class="action-link blocking-action"
            >
              Add
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Warning checks -->
      <div v-if="warningChecks.length > 0" class="check-section">
        <div class="section-label warning-label">
          <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span>Recommended</span>
        </div>
        <div class="check-list">
          <div
            v-for="check in warningChecks"
            :key="check.key"
            class="check-row warning"
          >
            <div class="check-status">
              <div class="status-dot warning-dot"></div>
            </div>
            <div class="check-details">
              <span class="check-message">{{ check.message }}</span>
              <span v-if="check.description" class="check-description">{{ check.description }}</span>
            </div>
            <router-link
              v-if="check.form_link"
              :to="check.form_link"
              class="action-link warning-action"
            >
              Add
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Info checks -->
      <div v-if="infoChecks.length > 0" class="check-section">
        <div class="section-label info-label">
          <svg class="w-4 h-4 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Optional</span>
        </div>
        <div class="check-list">
          <div
            v-for="check in infoChecks"
            :key="check.key"
            class="check-row info"
          >
            <div class="check-status">
              <div class="status-dot info-dot"></div>
            </div>
            <div class="check-details">
              <span class="check-message">{{ check.message }}</span>
              <span v-if="check.description" class="check-description">{{ check.description }}</span>
            </div>
            <router-link
              v-if="check.form_link"
              :to="check.form_link"
              class="action-link info-action"
            >
              Add
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Can proceed but warnings exist: show info bar -->
    <div v-else-if="warningChecks.length > 0" class="gate-warnings">
      <div class="warnings-bar">
        <svg
          class="w-5 h-5 text-violet-500 flex-shrink-0"
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
        <div class="warnings-content">
          <span class="warnings-title">
            {{ warningChecks.length }} {{ warningChecks.length === 1 ? 'item' : 'items' }} could improve your recommendations
          </span>
          <div class="warnings-list">
            <div
              v-for="check in warningChecks"
              :key="check.key"
              class="warning-item"
            >
              <span class="text-sm text-violet-700">{{ check.message }}</span>
              <router-link
                v-if="check.form_link"
                :to="check.form_link"
                class="text-sm font-semibold text-violet-600 hover:text-violet-800 transition-colors whitespace-nowrap"
              >
                Add
                <svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- All good: render child content -->
    <slot v-if="canProceed"></slot>
  </div>
</template>

<script>
export default {
  name: 'InvestmentReadinessGate',

  props: {
    readinessChecks: {
      type: Object,
      default: () => ({ checks: [] }),
    },
    canProceed: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    allChecks() {
      return this.readinessChecks?.checks || [];
    },

    failedChecks() {
      return this.allChecks.filter(check => !check.passed);
    },

    blockingChecks() {
      return this.failedChecks.filter(check => check.level === 'blocking');
    },

    warningChecks() {
      return this.failedChecks.filter(check => check.level === 'warning');
    },

    infoChecks() {
      return this.failedChecks.filter(check => check.level === 'info');
    },

    totalCount() {
      return this.allChecks.length;
    },

    passedCount() {
      return this.allChecks.filter(check => check.passed).length;
    },

    progressPercent() {
      if (this.totalCount === 0) return 0;
      return Math.round((this.passedCount / this.totalCount) * 100);
    },
  },
};
</script>

<style scoped>
.investment-readiness-gate {
  width: 100%;
}

/* Blocked gate */
.gate-blocked {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 28px;
}

.gate-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}

.gate-icon {
  flex-shrink: 0;
  padding: 12px;
  @apply bg-raspberry-50;
  border-radius: 12px;
}

.gate-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 6px 0;
}

.gate-subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  line-height: 1.5;
  margin: 0;
}

/* Progress bar */
.progress-bar-container {
  margin-bottom: 24px;
}

.progress-bar {
  height: 6px;
  @apply bg-savannah-200;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 6px;
}

.progress-fill {
  height: 100%;
  @apply bg-spring-500;
  border-radius: 3px;
  transition: width 0.5s ease;
}

.progress-label {
  font-size: 12px;
  @apply text-neutral-500;
}

/* Check sections */
.check-section {
  margin-bottom: 20px;
}

.check-section:last-child {
  margin-bottom: 0;
}

.section-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.blocking-label {
  @apply text-raspberry-600;
}

.warning-label {
  @apply text-violet-600;
}

.info-label {
  @apply text-horizon-400;
}

.check-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.check-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  @apply bg-savannah-100;
  border-radius: 8px;
  transition: background-color 0.15s ease;
}

.check-row:hover {
  background: white;
}

.check-row.blocking {
  @apply border-l-3 border-raspberry-500;
}

.check-row.warning {
  @apply border-l-3 border-violet-500;
}

.check-row.info {
  @apply border-l-3 border-horizon-300;
}

.check-status {
  flex-shrink: 0;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.blocking-dot {
  @apply bg-raspberry-500;
}

.warning-dot {
  @apply bg-violet-500;
}

.info-dot {
  @apply bg-horizon-300;
}

.check-details {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.check-message {
  font-size: 14px;
  @apply text-horizon-600;
  line-height: 1.4;
}

.check-description {
  font-size: 12px;
  @apply text-neutral-500;
  line-height: 1.4;
}

.action-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
  padding: 6px 12px;
  border-radius: 6px;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.blocking-action {
  @apply text-raspberry-600 bg-raspberry-50;
}

.blocking-action:hover {
  @apply bg-raspberry-100 text-raspberry-800;
}

.warning-action {
  @apply text-violet-600 bg-violet-50;
}

.warning-action:hover {
  @apply bg-violet-100 text-violet-800;
}

.info-action {
  @apply text-horizon-500 bg-eggshell-500;
}

.info-action:hover {
  @apply bg-savannah-200 text-horizon-700;
}

/* Warnings bar (when can proceed) */
.gate-warnings {
  margin-bottom: 16px;
}

.warnings-bar {
  display: flex;
  gap: 12px;
  padding: 14px 18px;
  @apply bg-violet-50;
  @apply border border-violet-200;
  border-radius: 10px;
  align-items: flex-start;
}

.warnings-content {
  flex: 1;
}

.warnings-title {
  font-size: 14px;
  font-weight: 600;
  @apply text-violet-800;
  display: block;
  margin-bottom: 8px;
}

.warnings-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.warning-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

@media (max-width: 640px) {
  .gate-blocked {
    padding: 20px;
  }

  .gate-header {
    flex-direction: column;
    gap: 12px;
  }

  .gate-title {
    font-size: 18px;
  }

  .check-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
  }

  .action-link {
    margin-left: 22px;
  }

  .warning-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}
</style>
