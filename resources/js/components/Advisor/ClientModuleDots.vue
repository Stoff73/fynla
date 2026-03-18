<template>
  <div class="flex gap-1 items-center">
    <div
      v-for="mod in modules"
      :key="mod.key"
      class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold"
      :class="dotClass(mod.status)"
    >
      {{ mod.letter }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'ClientModuleDots',

  props: {
    moduleStatus: {
      type: Object,
      default: () => ({}),
    },
  },

  computed: {
    modules() {
      return [
        { key: 'protection', letter: 'P', status: this.moduleStatus?.protection?.status || 'empty' },
        { key: 'savings', letter: 'S', status: this.moduleStatus?.savings?.status || 'empty' },
        { key: 'investment', letter: 'I', status: this.moduleStatus?.investment?.status || 'empty' },
        { key: 'retirement', letter: 'R', status: this.moduleStatus?.retirement?.status || 'empty' },
        { key: 'estate', letter: 'E', status: this.moduleStatus?.estate?.status || 'empty' },
      ];
    },
  },

  methods: {
    dotClass(status) {
      switch (status) {
        case 'complete':
          return 'bg-spring-500 text-white';
        case 'partial':
          return 'bg-violet-500 text-white';
        case 'skipped':
          return 'bg-eggshell-500 text-horizon-300 line-through border border-light-gray';
        case 'empty':
        default:
          return 'bg-light-gray text-neutral-500';
      }
    },
  },
};
</script>

