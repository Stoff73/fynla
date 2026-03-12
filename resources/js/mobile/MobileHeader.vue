<template>
  <header class="mobile-header bg-white border-b border-light-gray flex items-center px-4"
          style="min-height: 44px;">
    <!-- Left: Back button -->
    <div class="w-10">
      <button
        v-if="showBack"
        class="p-1 -ml-1 text-horizon-500"
        @click="$emit('back')"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
    </div>

    <!-- Centre: Title -->
    <h1 class="flex-1 text-center text-base font-bold text-horizon-500 truncate">
      {{ title }}
    </h1>

    <!-- Right: Action -->
    <div class="w-10 flex justify-end">
      <button
        v-if="rightAction"
        class="p-1 text-horizon-500"
        @click="$emit('action')"
      >
        <slot name="right-action">
          <svg v-if="rightAction === 'share'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
        </slot>
      </button>
    </div>
  </header>
</template>

<script>
export default {
  name: 'MobileHeader',
  props: {
    title: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
    rightAction: { type: String, default: null },
  },
  emits: ['back', 'action'],
};
</script>
