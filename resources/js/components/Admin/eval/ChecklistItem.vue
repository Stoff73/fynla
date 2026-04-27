<template>
  <div
    class="rounded p-2 border"
    :class="passing ? 'bg-spring-50 border-spring-200' : 'bg-raspberry-50 border-raspberry-200'"
  >
    <div class="flex items-start gap-2">
      <span class="text-base font-bold mt-0.5" :class="passing ? 'text-spring-700' : 'text-raspberry-700'">
        {{ passing ? '✓' : '✗' }}
      </span>
      <div class="flex-1">
        <div class="text-sm font-medium" :class="passing ? 'text-spring-800' : 'text-raspberry-800'">
          {{ label }}
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ChecklistItem',
  props: {
    passing: { type: Boolean, required: true },
    label: { type: String, required: true },
  },
};
</script>
