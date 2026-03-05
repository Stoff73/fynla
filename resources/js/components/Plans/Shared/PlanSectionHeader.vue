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
      default: 'blue',
      validator: (v) => ['blue', 'green', 'purple', 'gray', 'teal'].includes(v),
    },
  },

  computed: {
    headerClasses() {
      const map = {
        blue: 'bg-blue-50 border border-blue-200',
        green: 'bg-green-50 border border-green-200',
        purple: 'bg-purple-50 border border-purple-200',
        gray: 'bg-eggshell-500 border border-light-gray',
        teal: 'bg-teal-50 border border-teal-200',
      };
      return map[this.color] || map.blue;
    },
    textClass() {
      const map = {
        blue: 'text-blue-900',
        green: 'text-green-900',
        purple: 'text-purple-900',
        gray: 'text-horizon-500',
        teal: 'text-teal-900',
      };
      return map[this.color] || map.blue;
    },
    subtitleClass() {
      const map = {
        blue: 'text-blue-700',
        green: 'text-green-700',
        purple: 'text-purple-700',
        gray: 'text-neutral-500',
        teal: 'text-teal-700',
      };
      return map[this.color] || map.blue;
    },
  },
};
</script>
