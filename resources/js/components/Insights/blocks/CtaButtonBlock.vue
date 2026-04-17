<template>
  <div class="my-8 text-center">
    <a
      :href="block.href"
      :class="buttonClasses"
      class="inline-block px-6 py-3 rounded-lg text-sm font-semibold transition-colors"
    >
      {{ block.label }}
    </a>
  </div>
</template>

<script>
export default {
  name: 'CtaButtonBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    buttonClasses() {
      return this.block.style === 'secondary'
        ? 'border-2 border-horizon-500 text-horizon-500 hover:bg-horizon-500 hover:text-white'
        : 'bg-raspberry-500 text-white hover:bg-raspberry-600';
    },
  },
};
</script>
