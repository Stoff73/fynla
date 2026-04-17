<template>
  <figure :class="alignmentClass" class="my-6">
    <img :src="imageUrl" :alt="block.alt" class="rounded-lg w-full h-auto" />
    <figcaption v-if="block.caption" class="text-xs text-neutral-500 mt-2 text-center">
      {{ block.caption }}
    </figcaption>
  </figure>
</template>

<script>
export default {
  name: 'ImageBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    imageUrl() {
      if (!this.block.path) return '';
      if (this.block.path.startsWith('http')) return this.block.path;
      return `/storage/${this.block.path}`;
    },
    alignmentClass() {
      return {
        'mx-auto': this.block.alignment === 'full' || !this.block.alignment,
        'float-left mr-6 max-w-sm': this.block.alignment === 'left',
        'float-right ml-6 max-w-sm': this.block.alignment === 'right',
      };
    },
  },
};
</script>
