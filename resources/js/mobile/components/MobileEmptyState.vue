<template>
  <div class="text-center py-16">
    <h3 class="text-base font-bold text-horizon-500 mb-1">{{ title }}</h3>
    <p class="text-sm text-neutral-500">{{ subtitle }}</p>
  </div>
</template>

<script>
export default {
  name: 'MobileEmptyState',
  props: {
    icon: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
  },
};
</script>
