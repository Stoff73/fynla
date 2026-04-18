<template>
  <div class="space-y-2">
    <div class="flex items-center gap-2">
      <label class="text-xs font-semibold text-neutral-500 uppercase">Level:</label>
      <select
        :value="block.level"
        @input="update('level', Number($event.target.value))"
        class="text-sm border border-light-gray rounded px-2 py-1"
      >
        <option :value="2">H2</option>
        <option :value="3">H3</option>
        <option :value="4">H4</option>
      </select>
    </div>
    <RichTextEditor
      :model-value="block.text || ''"
      :tools="['bold', 'italic', 'underline', 'link', 'color']"
      @update:model-value="update('text', $event)"
    />
  </div>
</template>

<script>
import RichTextEditor from '../RichTextEditor.vue';

export default {
  name: 'EditHeadingBlock',
  components: { RichTextEditor },
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(field, value) { this.$emit('update', { ...this.block, [field]: value }); },
  },
};
</script>
