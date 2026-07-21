<template>
  <div v-if="show" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="limit-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-horizon-500/75 transition-opacity" @click="$emit('close')"></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-card px-6 pt-6 pb-6 text-left overflow-hidden shadow-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
        <h3 id="limit-modal-title" class="text-lg font-bold text-horizon-500">
          You've reached your {{ tierLabel }} limit
        </h3>

        <p class="mt-3 text-sm text-neutral-500">
          Your {{ tierLabel }} plan includes up to {{ cap }} {{ entityLabel }}.
          Upgrade your plan to add more.
        </p>

        <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
          <button
            type="button"
            class="inline-flex justify-center rounded-button border border-light-gray px-4 py-2 text-sm font-medium text-horizon-500 bg-white hover:bg-savannah-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
            @click="$emit('close')"
          >
            Maybe later
          </button>
          <router-link
            :to="subscriptionOptionsLocation"
            class="inline-flex justify-center rounded-button px-4 py-2 text-sm font-medium text-white bg-raspberry-600 hover:bg-raspberry-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
            @click="$emit('close')"
          >
            Upgrade
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { subscriptionOptionsLocation } from '@/utils/subscriptionNavigation';

export default {
  name: 'LimitReachedModal',

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    /** Plural entity noun shown in the body, e.g. "cash accounts". */
    entityLabel: {
      type: String,
      required: true,
    },
    /** The user's count cap for this entity. */
    cap: {
      type: Number,
      required: true,
    },
    /** Display label for the user's tier, e.g. "Free". */
    tierLabel: {
      type: String,
      default: 'your current',
    },
  },

  emits: ['close'],

  computed: {
    subscriptionOptionsLocation,
  },
};
</script>
