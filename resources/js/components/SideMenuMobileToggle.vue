<template>
  <button
    class="fixed top-4 left-4 z-[61] sm:hidden inline-flex items-center justify-center p-2 rounded-md bg-white shadow-md border border-light-gray text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100 transition-colors"
    @click="$emit('toggle')"
  >
    <span class="sr-only">Open navigation menu</span>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  </button>
</template>

<script>
export default {
  name: 'SideMenuMobileToggle',
  emits: ['toggle'],
};
</script>
