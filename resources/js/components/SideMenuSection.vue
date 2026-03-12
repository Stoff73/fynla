<template>
  <div class="mb-1">
    <!-- Section header (clickable when sidebar expanded) -->
    <button
      v-if="!collapsed"
      class="flex items-center justify-between w-full px-4 pt-3 pb-1 group"
      @click="$emit('toggle')"
    >
      <span class="text-[11px] font-semibold uppercase tracking-wider text-horizon-400 group-hover:text-horizon-500 transition-colors">{{ label }}</span>
      <svg
        class="w-3 h-3 text-horizon-400 group-hover:text-horizon-500 transition-transform duration-200"
        :class="expanded ? 'rotate-180' : ''"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <!-- Divider when sidebar collapsed (icon-only mode) -->
    <div v-else class="mx-3 my-2 border-t border-light-gray"></div>
    <!-- Collapsible content -->
    <div
      class="grid transition-[grid-template-rows] duration-200 ease-out"
      :class="collapsed || expanded ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
    >
      <div class="overflow-hidden">
        <div class="flex flex-col">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SideMenuSection',
  props: {
    label: {
      type: String,
      required: true,
    },
    collapsed: {
      type: Boolean,
      default: false,
    },
    expanded: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['toggle'],
};
</script>
