<template>
  <div class="space-y-2">
    <RichTextEditor
      :model-value="block.text || ''"
      :tools="['bold', 'italic', 'underline', 'link']"
      @update:model-value="update('text', $event)"
    />
    <input
      :value="block.attribution"
      @input="update('attribution', $event.target.value)"
      placeholder="Attribution (optional)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
  </div>
</template>

<script>
import RichTextEditor from '../RichTextEditor.vue';

export default {
  name: 'EditPullQuoteBlock',
  components: { RichTextEditor },
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(f, v) { this.$emit('update', { ...this.block, [f]: v }); },
  },
};
</script>
