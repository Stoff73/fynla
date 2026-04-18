<template>
  <div class="space-y-2">
    <div v-for="(b, i) in (block.bullets || [])" :key="i" class="flex items-start gap-2">
      <div class="flex-1">
        <RichTextEditor
          :model-value="b"
          :tools="['bold', 'italic', 'underline', 'link']"
          @update:model-value="updateBullet(i, $event)"
        />
      </div>
      <button type="button" @click="removeBullet(i)" class="text-sm text-raspberry-500 mt-2">Remove</button>
    </div>
    <button type="button" @click="addBullet" class="text-sm font-semibold text-raspberry-500">+ Add takeaway</button>
  </div>
</template>

<script>
import RichTextEditor from '../RichTextEditor.vue';

export default {
  name: 'EditKeyTakeawaysBlock',
  components: { RichTextEditor },
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
