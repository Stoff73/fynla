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
    <textarea
      :value="block.html"
      @input="update('html', $event.target.value)"
      placeholder="Callout HTML"
      rows="3"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded font-mono"
    />
  </div>
</template>

<script>
export default {
  name: 'EditCalloutBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(f, v) { this.$emit('update', { ...this.block, [f]: v }); },
  },
};
</script>
