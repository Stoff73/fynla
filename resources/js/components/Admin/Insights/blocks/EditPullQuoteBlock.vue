<template>
  <div class="space-y-2">
    <textarea
      :value="block.text"
      @input="update('text', $event.target.value)"
      placeholder="Quote text"
      rows="3"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
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
export default {
  name: 'EditPullQuoteBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(f, v) { this.$emit('update', { ...this.block, [f]: v }); },
  },
};
</script>
