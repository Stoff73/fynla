<template>
  <div class="advice-response-panel space-y-3 max-w-[85%] w-full">
    <!-- Headline -->
    <div class="rounded-lg bg-white border border-light-gray px-4 py-3">
      <p class="text-base font-semibold text-horizon-500">{{ response.headline }}</p>
    </div>

    <!-- Key figures grid -->
    <div
      v-if="response.key_figures && response.key_figures.length"
      class="grid grid-cols-2 gap-2"
    >
      <div
        v-for="(figure, idx) in response.key_figures"
        :key="`kf-${idx}`"
        class="rounded-lg bg-savannah-100 border border-light-gray px-3 py-2"
      >
        <p class="text-xs text-horizon-400 uppercase tracking-wide">{{ figure.label }}</p>
        <p class="text-base font-semibold text-horizon-500 mt-0.5">{{ figure.value }}</p>
      </div>
    </div>

    <!-- Breakdowns -->
    <div
      v-if="response.breakdowns && response.breakdowns.length"
      class="rounded-lg bg-white border border-light-gray px-4 py-3 space-y-2"
    >
      <p class="text-xs text-horizon-400 uppercase tracking-wide">Breakdown</p>
      <ul class="space-y-1.5">
        <li
          v-for="(item, idx) in response.breakdowns"
          :key="`bd-${idx}`"
          class="flex items-baseline justify-between gap-2 text-sm"
        >
          <span class="text-horizon-500">
            <span class="font-semibold">{{ item.label }}</span>
            <span v-if="item.detail" class="text-horizon-400 text-xs ml-1.5">{{ item.detail }}</span>
          </span>
          <span class="text-horizon-500 font-semibold">{{ item.value }}</span>
        </li>
      </ul>
    </div>

    <!-- Recommendations -->
    <div
      v-if="response.recommendations && response.recommendations.length"
      class="rounded-lg bg-white border border-light-gray px-4 py-3 space-y-2"
    >
      <p class="text-xs text-horizon-400 uppercase tracking-wide">Recommendations</p>
      <ul class="space-y-2">
        <li
          v-for="(rec, idx) in response.recommendations"
          :key="`rec-${idx}`"
          class="space-y-1"
        >
          <div class="flex items-baseline justify-between gap-2">
            <p class="text-sm font-semibold text-horizon-500">{{ rec.title }}</p>
            <span :class="['text-xs uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full whitespace-nowrap', priorityBadgeClass(rec.priority)]">
              {{ rec.priority }}
            </span>
          </div>
          <p v-if="rec.reason" class="text-xs text-horizon-400 leading-relaxed">{{ rec.reason }}</p>
        </li>
      </ul>
    </div>

    <!-- Next steps -->
    <div
      v-if="response.next_steps && response.next_steps.length"
      class="flex flex-wrap gap-2"
    >
      <button
        v-for="(step, idx) in response.next_steps"
        :key="`ns-${idx}`"
        type="button"
        @click="$emit('navigate', step.route_path)"
        class="text-xs font-semibold text-raspberry-500 border border-raspberry-500 hover:bg-raspberry-500 hover:text-white transition rounded-full px-3 py-1"
      >
        {{ step.label }}
      </button>
    </div>

    <!-- Signposting -->
    <p class="text-xs italic text-horizon-400 leading-relaxed pt-1">
      {{ response.signposting }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'AdviceResponsePanel',

  props: {
    response: {
      type: Object,
      required: true,
    },
  },

  emits: ['navigate'],

  methods: {
    priorityBadgeClass(priority) {
      switch (priority) {
        case 'high':
          return 'bg-raspberry-500 text-white';
        case 'medium':
          return 'bg-violet-100 text-violet-700';
        case 'low':
          return 'bg-savannah-100 text-horizon-500';
        case 'info':
        default:
          return 'bg-neutral-100 text-horizon-500';
      }
    },
  },
};
</script>
