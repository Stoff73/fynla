<template>
  <figure :class="wrapperClass">
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
    wrapperClass() {
      const align = this.block.alignment;
      // Floated images get no top margin so the first line of wrapping text
      // aligns with the image's top edge (rather than with the figure's
      // margin-top, which is what CSS floats wrap against).
      if (align === 'left') return 'float-left mr-6 mb-4 max-w-sm';
      if (align === 'right') return 'float-right ml-6 mb-4 max-w-sm';
      return 'my-6 mx-auto';
    },
  },
};
</script>
