<template>
  <div
    class="fixed top-12 left-4 right-4 z-50 bg-white rounded-xl shadow-lg border border-light-gray p-4
           animate-fade-in-slide"
    @click="$emit('dismiss')"
  >
    <div class="flex items-start gap-3">
      <div class="w-8 h-8 rounded-full bg-raspberry-100 flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-horizon-500 truncate">{{ notification.title }}</p>
        <p class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ notification.body }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InAppNotificationToast',
  props: {
    notification: { type: Object, required: true },
  },
  emits: ['dismiss'],
};
</script>
