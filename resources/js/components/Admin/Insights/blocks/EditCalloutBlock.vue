<template>
  <div class="space-y-2">
    <select
      :value="block.variant"
      @change="update('variant', $event.target.value)"
      class="text-sm border border-light-gray rounded px-2 py-1"
    >
      <option value="info">Info (horizon)</option>
      <option value="tip">Tip (spring)</option>
      <option value="success">Success (spring)</option>
      <option value="warning">Warning (violet)</option>
    </select>
    <RichTextEditor
      :model-value="block.html || ''"
      @update:model-value="update('html', $event)"
    />
  </div>
</template>

<script>
import RichTextEditor from '../RichTextEditor.vue';

export default {
  name: 'EditCalloutBlock',
  components: { RichTextEditor },
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(f, v) { this.$emit('update', { ...this.block, [f]: v }); },
  },
};
</script>
