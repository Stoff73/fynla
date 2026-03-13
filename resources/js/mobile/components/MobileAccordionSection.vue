<template>
  <div class="bg-white rounded-xl border border-light-gray overflow-hidden">
    <button
      class="w-full px-4 py-3.5 flex items-center justify-between active:bg-savannah-100 transition-colors"
      @click="toggle"
    >
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-bold text-horizon-500">{{ title }}</h3>
        <span
          v-if="badge != null"
          class="ml-1 px-1.5 py-0.5 rounded-full bg-savannah-100 text-xs font-semibold text-horizon-500"
        >
          {{ badge }}
        </span>
      </div>
      <svg
        class="w-4 h-4 text-neutral-500 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <transition name="expand">
      <div v-show="isOpen" class="border-t border-light-gray">
        <slot />
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'MobileAccordionSection',

  props: {
    title: { type: String, required: true },
    icon: { type: String, default: null },
    defaultOpen: { type: Boolean, default: false },
    badge: { type: [String, Number], default: null },
  },

  data() {
    return {
      isOpen: this.defaultOpen,
    };
  },

  methods: {
    toggle() {
      this.isOpen = !this.isOpen;
    },
  },
};
</script>
