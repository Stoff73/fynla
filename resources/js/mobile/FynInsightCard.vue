<template>
  <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3">
    <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
    <p class="text-white text-sm leading-relaxed">{{ insight }}</p>
  </div>
</template>

<script>
export default {
  name: 'FynInsightCard',
  props: {
    insight: { type: String, required: true },
  },
};
</script>
