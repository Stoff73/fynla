<template>
  <div class="bg-white rounded-xl border border-light-gray p-4">
    <div class="flex items-start justify-between gap-2">
      <div class="flex-1 min-w-0">
        <h4 class="text-sm font-bold text-horizon-500">{{ info.title }}</h4>
        <p class="text-xs text-neutral-500 mt-1 leading-relaxed">{{ info.summary }}</p>
      </div>
      <button
        class="flex-shrink-0 w-7 h-7 rounded-full border border-light-gray flex items-center justify-center
               text-neutral-500 active:bg-savannah-100"
        @click="showPopup = true"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </button>
    </div>

    <LearnInfoPopup
      v-if="showPopup"
      :info="info"
      @close="showPopup = false"
      @ask-fyn="$emit('ask-fyn', info.title)"
    />
  </div>
</template>

<script>
import LearnInfoPopup from '@/mobile/learn/LearnInfoPopup.vue';

export default {
  name: 'LearnInfoCard',

  components: {
    LearnInfoPopup,
  },

  props: {
    info: {
      type: Object,
      required: true,
    },
  },

  emits: ['ask-fyn'],

  data() {
    return {
      showPopup: false,
    };
  },
};
</script>
