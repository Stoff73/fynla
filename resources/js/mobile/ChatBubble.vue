<template>
  <div
    class="flex"
    :class="isUser ? 'justify-end' : 'justify-start'"
  >
    <!-- Fyn avatar for assistant messages -->
    <img
      v-if="!isUser"
      src="/images/logos/favicon.png"
      alt="Fyn"
      class="w-7 h-7 rounded-full flex-shrink-0 mt-1 mr-2"
    />

    <!-- Bubble -->
    <div
      class="px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap break-words"
      :class="bubbleClasses"
      style="max-width: 85%;"
    >
      {{ content }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'ChatBubble',

  props: {
    role: {
      type: String,
      required: true,
      validator: (val) => ['user', 'assistant'].includes(val),
    },
    content: {
      type: String,
      default: '',
    },
    metadata: {
      type: Object,
      default: () => ({}),
    },
  },

  computed: {
    isUser() {
      return this.role === 'user';
    },

    bubbleClasses() {
      if (this.isUser) {
        return 'bg-raspberry-50 text-horizon-500 rounded-2xl rounded-br-sm';
      }
      return 'bg-white text-horizon-500 rounded-2xl rounded-bl-sm shadow-sm';
    },
  },
};
</script>
