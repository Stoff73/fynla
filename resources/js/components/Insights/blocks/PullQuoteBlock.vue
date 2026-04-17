<template>
  <blockquote class="my-10 px-8 py-6 border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-lg">
    <p
      class="text-xl md:text-2xl font-bold text-horizon-500 leading-snug italic"
      style="letter-spacing:-0.01em;"
    >
      &ldquo;{{ block.text }}&rdquo;
    </p>
    <footer v-if="block.attribution" class="mt-3 text-sm text-neutral-500 not-italic">
      &mdash; {{ block.attribution }}
    </footer>
  </blockquote>
</template>

<script>
export default {
  name: 'PullQuoteBlock',
  props: { block: { type: Object, required: true } },
};
</script>
