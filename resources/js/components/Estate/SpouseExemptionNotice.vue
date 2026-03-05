<template>
  <div class="bg-spring-50 border border-spring-200 p-4">
    <div class="flex">
      <div class="flex-shrink-0">
        <svg
          class="h-5 w-5 text-spring-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      <div class="ml-3 flex-1">
        <h3 class="text-sm font-medium text-spring-800">Spouse Exemption</h3>
        <p class="mt-2 text-sm text-spring-700">{{ message }}</p>

        <!-- Call to action if no spouse linked -->
        <div v-if="!hasSpouse" class="mt-3">
          <router-link
            to="/profile"
            class="text-sm font-medium text-spring-800 underline hover:text-spring-900"
          >
            Link your spouse account →
          </router-link>
        </div>

        <!-- Data sharing status -->
        <div v-else-if="!dataSharingEnabled" class="mt-3">
          <p class="text-xs text-spring-600">
            Enable data sharing to unlock comprehensive joint Inheritance Tax planning features.
          </p>
          <router-link
            to="/settings"
            class="text-sm font-medium text-spring-800 underline hover:text-spring-900 inline-block mt-1"
          >
            Manage data sharing →
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SpouseExemptionNotice',

  props: {
    message: {
      type: String,
      required: true,
    },
    hasSpouse: {
      type: Boolean,
      default: false,
    },
    dataSharingEnabled: {
      type: Boolean,
      default: false,
    },
  },
};
</script>
