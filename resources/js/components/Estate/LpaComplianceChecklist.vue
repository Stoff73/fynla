<!--
  Renders the result of the Lasting Power of Attorney checks.

  W-0100: this component must never tell the user their Lasting Power of
  Attorney is compliant, approved, valid or sufficient. It reports what was
  checked, what was not, and what the checks found. Every word below comes from
  the API payload, which composes it in `LpaCheckPolicy` — the one home for this
  wording (Rule 20). Do not hardcode a label, a heading or a disclosure here,
  and do not render the outcome as a coloured badge: green carries "approved"
  without a word being written, which is the claim this component exists not to
  make.
-->
<template>
  <div class="lpa-compliance-checklist">
    <div v-if="loading" class="text-center py-6">
      <div class="w-8 h-8 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
      <p class="mt-2 text-sm text-neutral-500">Running checks...</p>
    </div>

    <div v-else-if="compliance" class="space-y-3">
      <!-- What the checks found. Plain text, never a badge — see the note above. -->
      <div class="mb-4">
        <h4 class="text-sm font-bold text-horizon-500">{{ compliance.heading }}</h4>
        <p class="text-sm text-neutral-600 mt-1">{{ compliance.outcome_label }}</p>
      </div>

      <!-- Individual Checks -->
      <div
        v-for="check in compliance.checks"
        :key="check.key"
        class="flex items-start space-x-3 py-2 border-b border-light-gray last:border-b-0"
      >
        <!-- Status Icon -->
        <div class="flex-shrink-0 mt-0.5">
          <svg v-if="check.status === 'pass'" class="w-5 h-5 text-spring-500" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <svg v-else-if="check.status === 'fail'" class="w-5 h-5 text-raspberry-500" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          <svg v-else class="w-5 h-5 text-violet-500" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>

        <!-- Check Details -->
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-horizon-500">{{ check.title }}</p>
          <p class="text-xs text-neutral-500 mt-0.5">{{ check.description }}</p>
        </div>
      </div>

      <!-- Summary -->
      <div class="mt-4 pt-3 border-t border-light-gray">
        <div class="flex items-center space-x-4 text-xs text-neutral-500">
          <span class="flex items-center">
            <span class="w-2 h-2 rounded-full bg-spring-500 mr-1"></span>
            {{ compliance.passed }} passed
          </span>
          <span v-if="compliance.failed > 0" class="flex items-center">
            <span class="w-2 h-2 rounded-full bg-raspberry-500 mr-1"></span>
            {{ compliance.failed }} failed
          </span>
          <span v-if="compliance.warnings > 0" class="flex items-center">
            <span class="w-2 h-2 rounded-full bg-violet-500 mr-1"></span>
            {{ compliance.warnings }} {{ compliance.warnings === 1 ? 'warning' : 'warnings' }}
          </span>
        </div>
      </div>

      <!-- What we did not check. Shown here, with the result — not in a footer. -->
      <div class="mt-4 pt-3 border-t border-light-gray">
        <h4 class="text-sm font-bold text-horizon-500 mb-1">{{ compliance.not_checked_heading }}</h4>
        <p class="text-xs text-neutral-500">{{ compliance.not_checked_intro }}</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          <li
            v-for="item in compliance.not_checked"
            :key="item"
            class="text-xs text-neutral-500"
          >
            {{ item }}
          </li>
        </ul>
        <p class="text-xs text-neutral-500 mt-2">{{ compliance.not_checked_close }}</p>
        <p class="text-xs text-neutral-500 mt-2">{{ compliance.referral }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LpaComplianceChecklist',

  props: {
    compliance: {
      type: Object,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
};
</script>
