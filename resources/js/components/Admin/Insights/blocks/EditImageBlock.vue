<template>
  <div class="space-y-2">
    <div v-if="block.path" class="relative">
      <img :src="imageUrl" :alt="block.alt" class="w-full rounded max-h-60 object-cover" />
      <button
        type="button"
        @click="clearImage"
        class="absolute top-2 right-2 px-2 py-1 text-xs bg-raspberry-500 text-white rounded"
      >
        Replace
      </button>
    </div>
    <template v-else>
      <input type="file" accept="image/jpeg,image/png,image/webp" @change="handleUpload" />
      <p class="text-xs text-violet-500">
        Upload an image before saving — empty image blocks fail validation.
      </p>
    </template>

    <input
      :value="block.alt"
      @input="update('alt', $event.target.value)"
      placeholder="Alt text (required)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
    <input
      :value="block.caption"
      @input="update('caption', $event.target.value)"
      placeholder="Caption (optional)"
      class="w-full text-sm px-3 py-2 border border-light-gray rounded"
    />
    <select
      :value="block.alignment || 'full'"
      @change="update('alignment', $event.target.value)"
      class="text-sm border border-light-gray rounded px-2 py-1"
    >
      <option value="full">Full width</option>
      <option value="left">Float left</option>
      <option value="right">Float right</option>
    </select>
  </div>
</template>

<script>
import insightsService from '@/services/insightsService';

export default {
  name: 'EditImageBlock',
  props: {
    block: { type: Object, required: true },
    articleSlug: { type: String, default: '' },
  },
  emits: ['update'],
  computed: {
    imageUrl() {
      if (!this.block.path) return null;
      if (this.block.path.startsWith('http')) return this.block.path;
      return `/storage/${this.block.path}`;
    },
  },
  methods: {
    update(field, value) { this.$emit('update', { ...this.block, [field]: value }); },
    clearImage() { this.update('path', ''); },
    async handleUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      try {
        const res = await insightsService.uploadImage(file, this.articleSlug || 'draft');
        this.$emit('update', { ...this.block, path: res.data.path });
      } catch (e) {
        alert('Upload failed: ' + (e.response?.data?.message || e.message));
      }
    },
  },
};
</script>
