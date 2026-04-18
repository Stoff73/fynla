<template>
  <RichTextEditor
    :model-value="block.html || ''"
    @update:model-value="$emit('update', { ...block, html: $event })"
  />
</template>

<script>
import RichTextEditor from '../RichTextEditor.vue';

export default {
  name: 'EditParagraphBlock',
  components: { RichTextEditor },
  props: { block: { type: Object, required: true } },
  emits: ['update'],
};
</script>
