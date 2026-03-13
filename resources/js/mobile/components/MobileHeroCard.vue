<template>
  <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
    <h2 class="text-lg font-bold text-horizon-500">{{ title }}</h2>
    <p class="text-2xl font-black text-horizon-500 mt-3">{{ value }}</p>
    <p class="text-xs text-neutral-500 mt-1">{{ subtitle }}</p>
    <slot />
  </div>
</template>

<script>
export default {
  name: 'MobileHeroCard',
  props: {
    icon: { type: String, default: null },
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtitle: { type: String, required: true },
  },
};
</script>
