<template>
  <component
    :is="block.ordered ? 'ol' : 'ul'"
    :class="block.ordered ? 'list-decimal' : 'list-disc'"
    class="pl-6 mb-5 text-neutral-600 space-y-2"
  >
    <li v-for="(item, i) in block.items" :key="i">{{ item }}</li>
  </component>
</template>

<script>
export default {
  name: 'ListBlock',
  props: { block: { type: Object, required: true } },
};
</script>
