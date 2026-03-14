<template>
  <div class="missing-data-card">
    <!-- Blocking checks -->
    <div v-if="blockingChecks.length > 0" class="check-group">
      <div class="group-header">
        <svg
          class="w-4 h-4 text-raspberry-500"
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
        <span class="text-xs font-semibold text-raspberry-700 uppercase tracking-wide">Required Information</span>
      </div>
      <div class="check-items">
        <div
          v-for="check in blockingChecks"
          :key="check.key"
          class="check-item blocking"
        >
          <div class="check-content">
            <div class="check-icon blocking-icon">
              <svg
                class="w-4 h-4 text-raspberry-500"
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
            <span class="check-message text-horizon-700">{{ check.message }}</span>
          </div>
          <router-link
            v-if="check.form_link"
            :to="check.form_link"
            class="check-link blocking-link"
          >
            Add this information
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Warning checks -->
    <div v-if="warningChecks.length > 0" class="check-group">
      <div class="group-header">
        <svg
          class="w-4 h-4 text-violet-500"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
        <span class="text-xs font-semibold text-violet-700 uppercase tracking-wide">Recommended Information</span>
      </div>
      <div class="check-items">
        <div
          v-for="check in warningChecks"
          :key="check.key"
          class="check-item warning"
        >
          <div class="check-content">
            <div class="check-icon warning-icon">
              <svg
                class="w-4 h-4 text-violet-500"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>
            <span class="check-message text-horizon-600">{{ check.message }}</span>
          </div>
          <router-link
            v-if="check.form_link"
            :to="check.form_link"
            class="check-link warning-link"
          >
            Add this information
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Info checks -->
    <div v-if="infoChecks.length > 0" class="check-group">
      <div class="group-header">
        <svg
          class="w-4 h-4 text-horizon-400"
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
        <span class="text-xs font-semibold text-horizon-400 uppercase tracking-wide">Optional Information</span>
      </div>
      <div class="check-items">
        <div
          v-for="check in infoChecks"
          :key="check.key"
          class="check-item info"
        >
          <div class="check-content">
            <div class="check-icon info-icon">
              <svg
                class="w-4 h-4 text-horizon-400"
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
            <span class="check-message text-neutral-500">{{ check.message }}</span>
          </div>
          <router-link
            v-if="check.form_link"
            :to="check.form_link"
            class="check-link info-link"
          >
            Add this information
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Empty state when all checks pass -->
    <div v-if="failedChecks.length === 0" class="all-passed">
      <svg
        class="w-5 h-5 text-spring-500"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      <span class="text-sm text-spring-700 font-medium">All required information has been provided</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MissingDataCard',

  props: {
    checks: {
      type: Array,
      required: true,
      validator(value) {
        return value.every(
          check => typeof check.key === 'string' &&
                   ['blocking', 'warning', 'info'].includes(check.level) &&
                   typeof check.passed === 'boolean' &&
                   typeof check.message === 'string'
        );
      },
    },
  },

  computed: {
    failedChecks() {
      return this.checks.filter(check => !check.passed);
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
  },
};
</script>

<style scoped>
.missing-data-card {
  @apply bg-savannah-100;
  border-radius: 12px;
  padding: 20px;
}

.check-group {
  margin-bottom: 20px;
}

.check-group:last-child {
  margin-bottom: 0;
}

.group-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}

.check-items {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.check-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  border-radius: 8px;
  padding: 12px 16px;
  gap: 12px;
}

.check-item.blocking {
  @apply border-l-4 border-raspberry-500;
}

.check-item.warning {
  @apply border-l-4 border-violet-500;
}

.check-item.info {
  @apply border-l-4 border-horizon-300;
}

.check-content {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.check-icon {
  flex-shrink: 0;
}

.check-message {
  font-size: 13px;
  line-height: 1.4;
}

.check-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
  transition: color 0.15s ease;
}

.blocking-link {
  @apply text-raspberry-600;
}

.blocking-link:hover {
  @apply text-raspberry-800;
}

.warning-link {
  @apply text-violet-600;
}

.warning-link:hover {
  @apply text-violet-800;
}

.info-link {
  @apply text-horizon-500;
}

.info-link:hover {
  @apply text-horizon-700;
}

.all-passed {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  @apply bg-spring-50;
  border-radius: 8px;
  @apply border border-spring-200;
}

@media (max-width: 640px) {
  .missing-data-card {
    padding: 16px;
  }

  .check-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
  }

  .check-link {
    margin-left: 34px;
  }
}
</style>
