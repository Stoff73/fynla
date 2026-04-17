<template>
  <textarea
    :value="block.html"
    @input="$emit('update', { ...block, html: $event.target.value })"
    placeholder="Paragraph HTML (plain text, or with <strong>/<em>/<a>)"
    rows="4"
    class="w-full text-sm text-neutral-600 px-3 py-2 border border-light-gray rounded resize-y font-mono"
  />
</template>

<script>
export default {
  name: 'EditParagraphBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
};
</script>
