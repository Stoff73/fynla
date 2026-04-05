<template>
  <div v-if="shouldShow" class="mb-6">
    <div
      class="rounded-lg border-l-4 p-4"
      :class="alertClasses"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg
            class="h-5 w-5"
            :class="iconColour"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium" :class="titleColour">
            {{ alertTitle }}
          </h3>
          <div class="mt-2 text-sm" :class="textColour">
            <p>{{ alertMessage }}</p>

            <!-- Progress Bar -->
            <div class="mt-3">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold">Profile Completeness</span>
                <span class="text-xs font-semibold">{{ completenessLabel }}</span>
              </div>
              <div class="w-full bg-savannah-200 rounded-full h-2">
                <div
                  class="h-2 rounded-full transition-all duration-300"
                  :class="progressBarColour"
                  :style="`width: ${completenessData.completeness_score}%`"
                ></div>
              </div>
            </div>

            <!-- Missing Fields List -->
            <div v-if="missingFields.length > 0" class="mt-4">
              <p class="font-medium mb-2">To improve your plan, please complete:</p>
              <ul class="list-disc list-inside space-y-1">
                <li
                  v-for="(field, key) in missingFields"
                  :key="key"
                  class="flex items-start"
                >
                  <span class="mr-2">•</span>
                  <router-link
                    :to="field.link"
                    class="hover:underline flex-1"
                    :class="linkColour"
                  >
                    {{ field.message }}
                  </router-link>
                  <span
                    v-if="field.priority === 'high'"
                    class="ml-2 text-xs font-semibold px-2 py-0.5 rounded"
                    :class="priorityBadgeClass"
                  >
                    Priority
                  </span>
                </li>
              </ul>
            </div>

            <!-- Recommendations -->
            <div v-if="completenessData.recommendations && completenessData.recommendations.length > 0" class="mt-4">
              <button
                @click="showRecommendations = !showRecommendations"
                class="text-sm font-medium hover:underline focus:outline-none"
                :class="linkColour"
              >
                {{ showRecommendations ? 'Hide' : 'Show' }} Recommendations
              </button>
              <ul v-show="showRecommendations" class="mt-2 space-y-1 text-sm">
                <li v-for="(rec, index) in completenessData.recommendations" :key="index">
                  → {{ rec }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Dismiss Button -->
          <button
            v-if="dismissible"
            @click="dismiss"
            class="mt-3 text-sm font-medium hover:underline"
            :class="linkColour"
          >
            Dismiss (will reappear until profile is complete)
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProfileCompletenessAlert',
  emits: ['dismissed'],

  props: {
    completenessData: {
      type: Object,
      required: true,
      default: () => ({
        completeness_score: 0,
        is_complete: false,
        missing_fields: {},
        recommendations: [],
      }),
    },
    dismissible: {
      type: Boolean,
      default: true,
    },
    autoHideWhenComplete: {
      type: Boolean,
      default: true,
    },
  },

  data() {
    return {
      isDismissed: false,
      showRecommendations: false,
    };
  },

  computed: {
    shouldShow() {
      if (this.isDismissed) return false;
      if (this.autoHideWhenComplete && this.completenessData.is_complete) return false;
      return this.completenessData.completeness_score < 100;
    },

    missingFields() {
      return Object.values(this.completenessData.missing_fields || {});
    },

    severity() {
      const score = this.completenessData.completeness_score;
      if (score < 50) return 'critical';
      if (score < 100) return 'warning';
      return 'success';
    },

    alertClasses() {
      switch (this.severity) {
        case 'critical':
          return 'bg-raspberry-50 border-raspberry-400';
        case 'warning':
          return 'bg-violet-50 border-violet-400';
        default:
          return 'bg-spring-50 border-spring-400';
      }
    },

    iconColour() {
      switch (this.severity) {
        case 'critical':
          return 'text-raspberry-400';
        case 'warning':
          return 'text-violet-400';
        default:
          return 'text-spring-400';
      }
    },

    titleColour() {
      switch (this.severity) {
        case 'critical':
          return 'text-raspberry-800';
        case 'warning':
          return 'text-violet-800';
        default:
          return 'text-spring-800';
      }
    },

    textColour() {
      switch (this.severity) {
        case 'critical':
          return 'text-raspberry-700';
        case 'warning':
          return 'text-violet-700';
        default:
          return 'text-spring-700';
      }
    },

    linkColour() {
      switch (this.severity) {
        case 'critical':
          return 'text-raspberry-700 hover:text-raspberry-900';
        case 'warning':
          return 'text-violet-700 hover:text-violet-900';
        default:
          return 'text-spring-700 hover:text-spring-900';
      }
    },

    progressBarColour() {
      switch (this.severity) {
        case 'critical':
          return 'bg-raspberry-600';
        case 'warning':
          return 'bg-violet-500';
        default:
          return 'bg-spring-600';
      }
    },

    completenessLabel() {
      const score = this.completenessData.completeness_score;
      if (score < 30) return 'Needs attention';
      if (score < 80) return 'Getting there';
      return 'Almost complete';
    },

    priorityBadgeClass() {
      switch (this.severity) {
        case 'critical':
          return 'bg-raspberry-200 text-raspberry-800';
        case 'warning':
          return 'bg-violet-200 text-violet-800';
        default:
          return 'bg-spring-200 text-spring-800';
      }
    },

    alertTitle() {
      const score = this.completenessData.completeness_score;
      if (score < 50) {
        return 'Profile Incomplete - Action Required';
      } else if (score < 100) {
        return 'Profile Partially Complete';
      } else {
        return 'Profile Complete';
      }
    },

    alertMessage() {
      const score = this.completenessData.completeness_score;
      if (score < 50) {
        return 'Your profile is missing critical information. Complete your profile to receive personalized financial planning advice.';
      } else if (score < 100) {
        return 'Some information is missing from your profile. Completing these fields will improve the quality and personalization of your financial plan.';
      } else {
        return 'Your profile is complete! You\'ll receive fully personalized financial planning recommendations.';
      }
    },
  },

  methods: {
    dismiss() {
      this.isDismissed = true;
      this.$emit('dismissed');
    },
  },
};
</script>
