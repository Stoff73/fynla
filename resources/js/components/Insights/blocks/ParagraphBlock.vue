<template>
  <div class="text-base leading-relaxed text-neutral-600 mb-5" v-html="sanitised"></div>
</template>

<script>
import { sanitiseBlock } from '@/utils/insightsSanitize';

export default {
  name: 'ParagraphBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    // Use block-level sanitise: Tiptap wraps content in <p> tags and
    // multi-paragraph blocks need those preserved.
    sanitised() { return sanitiseBlock(this.block.html || ''); },
  },
};
</script>
