<template>
  <component
    :is="block.ordered ? 'ol' : 'ul'"
    :class="block.ordered ? 'list-decimal' : 'list-disc'"
    class="pl-6 mb-5 text-neutral-600 space-y-2"
  >
    <li v-for="(item, i) in block.items" :key="i" v-html="sanitise(item)"></li>
  </component>
</template>

<script>
import { sanitiseInline } from '@/utils/insightsSanitize';

export default {
  name: 'ListBlock',
  props: { block: { type: Object, required: true } },
  methods: {
    sanitise(item) { return sanitiseInline(item || ''); },
  },
};
</script>
