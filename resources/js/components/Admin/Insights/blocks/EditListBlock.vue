<template>
  <div class="space-y-2">
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" :checked="block.ordered" @change="update('ordered', $event.target.checked)" />
      Numbered list
    </label>
    <div v-for="(item, i) in (block.items || [])" :key="i" class="flex items-center gap-2">
      <input
        :value="item"
        @input="updateItem(i, $event.target.value)"
        class="flex-1 text-sm px-3 py-2 border border-light-gray rounded"
      />
      <button type="button" @click="removeItem(i)" class="text-sm text-raspberry-500">Remove</button>
    </div>
    <button type="button" @click="addItem" class="text-sm font-semibold text-raspberry-500">+ Add item</button>
  </div>
</template>

<script>
export default {
  name: 'EditListBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    update(field, value) { this.$emit('update', { ...this.block, [field]: value }); },
    updateItem(i, value) {
      const items = [...(this.block.items || [])];
      items[i] = value;
      this.update('items', items);
    },
    addItem() { this.update('items', [...(this.block.items || []), '']); },
    removeItem(i) {
      const items = [...(this.block.items || [])];
      items.splice(i, 1);
      this.update('items', items);
    },
  },
};
</script>
