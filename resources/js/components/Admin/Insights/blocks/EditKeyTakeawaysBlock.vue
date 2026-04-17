<template>
  <div class="space-y-2">
    <div v-for="(b, i) in (block.bullets || [])" :key="i" class="flex items-center gap-2">
      <input
        :value="b"
        @input="updateBullet(i, $event.target.value)"
        class="flex-1 text-sm px-3 py-2 border border-light-gray rounded"
      />
      <button type="button" @click="removeBullet(i)" class="text-sm text-raspberry-500">Remove</button>
    </div>
    <button type="button" @click="addBullet" class="text-sm font-semibold text-raspberry-500">+ Add takeaway</button>
  </div>
</template>

<script>
export default {
  name: 'EditKeyTakeawaysBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  methods: {
    updateBullet(i, v) {
      const bullets = [...(this.block.bullets || [])];
      bullets[i] = v;
      this.$emit('update', { ...this.block, bullets });
    },
    addBullet() { this.$emit('update', { ...this.block, bullets: [...(this.block.bullets || []), ''] }); },
    removeBullet(i) {
      const bullets = [...(this.block.bullets || [])];
      bullets.splice(i, 1);
      this.$emit('update', { ...this.block, bullets });
    },
  },
};
</script>
