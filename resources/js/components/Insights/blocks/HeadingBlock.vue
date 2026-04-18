<template>
  <component
    :is="`h${block.level}`"
    :class="headingClasses"
    class="font-bold text-horizon-500 mb-4"
    style="letter-spacing:-0.02em;"
    v-html="sanitised"
  />
</template>

<script>
import { sanitiseInline } from '@/utils/insightsSanitize';

export default {
  name: 'HeadingBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    sanitised() { return sanitiseInline(this.block.text || ''); },
    headingClasses() {
      return {
        'text-3xl md:text-4xl mt-10': this.block.level === 2,
        'text-2xl md:text-3xl mt-8': this.block.level === 3,
        'text-xl md:text-2xl mt-6': this.block.level === 4,
      };
    },
  },
};
</script>
