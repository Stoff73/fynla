<template>
  <div class="plan-section-header mb-4">
    <div
      class="rounded-lg px-5 py-3"
      :class="headerClasses"
    >
      <h2 class="text-lg font-semibold" :class="textClass">{{ title }}</h2>
      <p v-if="subtitle" class="text-sm mt-0.5" :class="subtitleClass">{{ subtitle }}</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PlanSectionHeader',

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
      validator: (v) => ['violet', 'spring', 'raspberry', 'gray', 'horizon'].includes(v),
    },
  },

  computed: {
    headerClasses() {
      const map = {
        violet: 'bg-violet-50 border border-violet-200',
        spring: 'bg-spring-50 border border-spring-200',
        raspberry: 'bg-raspberry-50 border border-raspberry-200',
        gray: 'bg-eggshell-500 border border-light-gray',
        horizon: 'bg-horizon-50 border border-horizon-200',
      };
      return map[this.color] || map.violet;
    },
    textClass() {
      const map = {
        violet: 'text-violet-900',
        spring: 'text-spring-900',
        raspberry: 'text-raspberry-900',
        gray: 'text-horizon-500',
        horizon: 'text-horizon-500',
      };
      return map[this.color] || map.violet;
    },
    subtitleClass() {
      const map = {
        violet: 'text-violet-700',
        spring: 'text-spring-700',
        raspberry: 'text-raspberry-700',
        gray: 'text-neutral-500',
        horizon: 'text-horizon-400',
      };
      return map[this.color] || map.violet;
    },
  },
};
</script>
