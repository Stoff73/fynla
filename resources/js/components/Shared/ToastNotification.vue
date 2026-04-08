<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="visible"
        class="fixed top-6 right-6 z-[9999] max-w-sm px-5 py-3 rounded-xl shadow-lg flex items-center gap-3"
        :class="typeClasses"
      >
        <svg v-if="type === 'success'" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <span class="text-sm font-medium">{{ message }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'ToastNotification',
  computed: {
    ...mapState('toast', ['message', 'type', 'visible']),
    typeClasses() {
      return this.type === 'success'
        ? 'bg-spring-500 text-white'
        : 'bg-raspberry-500 text-white';
    },
  },
};
</script>
