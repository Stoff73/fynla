<template>
  <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
    <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
    <p class="text-white text-sm leading-relaxed">{{ summary }}</p>
  </div>
</template>

<script>
export default {
  name: 'MobileFynCard',
  props: {
    summary: { type: String, required: true },
  },
};
</script>
