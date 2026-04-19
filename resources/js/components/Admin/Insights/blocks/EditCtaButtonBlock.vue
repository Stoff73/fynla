<template>
  <div class="space-y-2">
    <input
      :value="block.label"
      @input="update('label', $event.target.value)"
      placeholder="Button label"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
    <input
      :value="block.href"
      @input="update('href', $event.target.value)"
      placeholder="URL (https:// or /relative)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
    <select
      :value="block.style || 'primary'"
      @change="update('style', $event.target.value)"
      class="text-sm border border-light-gray rounded px-2 py-1"
    >
      <option value="primary">Primary (raspberry)</option>
      <option value="secondary">Secondary (horizon outline)</option>
    </select>
  </div>
</template>

<script>
export default {
  name: 'EditCtaButtonBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(f, v) { this.$emit('update', { ...this.block, [f]: v }); },
  },
};
</script>
