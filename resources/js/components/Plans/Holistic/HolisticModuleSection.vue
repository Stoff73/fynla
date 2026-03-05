<template>
  <div class="mb-6">
    <PlanSectionHeader :title="title" :subtitle="subtitle" :color="color" />
    <slot />
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'HolisticModuleSection',

  components: { PlanSectionHeader },

  props: {
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: null,
    },
    color: {
      type: String,
      default: 'violet',
    },
  },
};
</script>
