<template>
  <div class="mb-1">
    <!-- Section header (only shown when expanded) -->
    <div v-if="!collapsed" class="px-4 pt-3 pb-1">
      <span class="text-[11px] font-semibold uppercase tracking-wider text-horizon-400">{{ label }}</span>
    </div>
    <!-- Divider when collapsed (thin line instead of label) -->
    <div v-else class="mx-3 my-2 border-t border-light-gray"></div>
    <div class="flex flex-col">
      <slot />
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
  },
};
</script>
