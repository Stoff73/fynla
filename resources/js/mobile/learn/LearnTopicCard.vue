<template>
  <div
    class="bg-white rounded-xl border border-light-gray p-4
           active:bg-savannah-100 transition-colors cursor-pointer"
    @click="$emit('click')"
  >
    <span class="text-2xl block mb-2">{{ topic.icon }}</span>
    <h4 class="text-sm font-bold text-horizon-500">{{ topic.label }}</h4>
    <p class="text-xs text-neutral-500 mt-1">
      {{ topic.guides.length }} {{ topic.guides.length === 1 ? 'guide' : 'guides' }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'LearnTopicCard',

  props: {
    topic: {
      type: Object,
      required: true,
    },
  },

  emits: ['click'],
};
</script>
