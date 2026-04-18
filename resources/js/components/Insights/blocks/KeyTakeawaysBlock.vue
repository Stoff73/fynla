<template>
  <aside class="my-8 p-6 bg-light-pink-100 border-l-4 border-raspberry-500 rounded-r-lg">
    <h4 class="text-sm font-bold text-raspberry-700 uppercase tracking-wide mb-3">Key takeaways</h4>
    <ul class="space-y-2">
      <li
        v-for="(bullet, i) in block.bullets"
        :key="i"
        class="flex items-start gap-2 text-sm text-horizon-500"
      >
        <svg class="w-4 h-4 text-raspberry-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span v-html="sanitise(bullet)"></span>
      </li>
    </ul>
  </aside>
</template>

<script>
import { sanitiseInline } from '@/utils/insightsSanitize';

export default {
  name: 'KeyTakeawaysBlock',
  props: { block: { type: Object, required: true } },
  methods: {
    sanitise(bullet) { return sanitiseInline(bullet || ''); },
  },
};
</script>
