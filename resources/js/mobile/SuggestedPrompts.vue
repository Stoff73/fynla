<template>
  <div class="pt-8 pb-4">
    <!-- Fyn greeting -->
    <div class="text-center mb-8">
      <img
        src="/images/logos/favicon.png"
        alt="Fyn"
        class="w-14 h-14 mx-auto mb-3"
      />
      <h3 class="text-lg font-bold text-horizon-500">How can I help?</h3>
      <p class="text-sm text-neutral-500 mt-1">Ask me anything about your finances</p>
    </div>

    <!-- Prompt grid -->
    <div class="grid grid-cols-2 gap-3">
      <button
        v-for="prompt in prompts"
        :key="prompt.id"
        class="bg-white rounded-xl border border-light-gray p-4 text-left
               active:bg-savannah-100 transition-colors"
        @click="$emit('select', prompt.text)"
      >
        <span class="text-lg block mb-2">{{ prompt.icon }}</span>
        <span class="text-sm font-medium text-horizon-500 leading-snug block">
          {{ prompt.text }}
        </span>
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SuggestedPrompts',

  emits: ['select'],

  data() {
    return {
      prompts: [
        {
          id: 'overview',
          icon: '\uD83D\uDCCA',
          text: 'How am I doing financially?',
        },
        {
          id: 'focus',
          icon: '\uD83C\uDFAF',
          text: 'What should I focus on?',
        },
        {
          id: 'protection',
          icon: '\uD83D\uDEE1\uFE0F',
          text: 'Review my protection',
        },
        {
          id: 'goals',
          icon: '\uD83C\uDF1F',
          text: 'Help me with my goals',
        },
      ],
    };
  },
};
</script>
