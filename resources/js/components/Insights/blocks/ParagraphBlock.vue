<template>
  <div class="text-base leading-relaxed text-neutral-600 mb-5" v-html="sanitised"></div>
</template>

<script>
import DOMPurify from 'dompurify';

export default {
  name: 'ParagraphBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    sanitised() {
      return DOMPurify.sanitize(this.block.html || '', {
        ALLOWED_TAGS: ['p', 'strong', 'em', 'a', 'br'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
      });
    },
  },
};
</script>
