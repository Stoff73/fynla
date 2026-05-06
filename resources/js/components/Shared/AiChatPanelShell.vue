<template>
  <Teleport to="body" :disabled="docked">
    <Transition
      v-if="!docked"
      enter-active-class="transition ease-out duration-200"
      :enter-from-class="isMobile ? 'translate-y-full opacity-0' : 'translate-y-4 opacity-0'"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="translate-y-0 opacity-100"
      :leave-to-class="isMobile ? 'translate-y-full opacity-0' : 'translate-y-4 opacity-0'"
    >
      <div
        v-if="isOpen"
        :class="modalClasses"
        :style="modalStyle"
      >
        <slot />
      </div>
    </Transition>

    <div
      v-if="docked"
      class="flex flex-col bg-eggshell-500 w-full h-full"
    >
      <slot />
    </div>
  </Teleport>
</template>

<script>
/**
 * Outer wrapper for AiChatPanel — handles the structural difference
 * between the docked sidebar (always-visible flex column) and the
 * floating modal (Teleported to body, Transition-animated, gated on
 * isOpen). The slot is rendered exactly once into whichever branch is
 * active, so the chat body lives in one place inside AiChatPanel.vue
 * and both layouts pick up changes equally.
 */
export default {
    name: 'AiChatPanelShell',

    props: {
        docked: {
            type: Boolean,
            default: false,
        },
        isOpen: {
            type: Boolean,
            default: false,
        },
        isMobile: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        modalClasses() {
            return this.isMobile
                ? 'fixed inset-0 z-[70] flex flex-col bg-white chat-mobile-container'
                : 'fixed bottom-24 right-6 w-[525px] max-w-[calc(100vw-2rem)] z-[70] bg-white rounded-lg border border-light-gray shadow-md flex flex-col transition-all duration-200';
        },
        modalStyle() {
            return this.isMobile ? {} : { maxHeight: 'calc(100vh - 8rem)' };
        },
    },
};
</script>
