<template>
  <!-- Docked right panel — matches AppLayout docked AiChatPanel -->
  <aside class="hidden lg:flex lg:flex-col fixed right-0 top-16 bottom-0 w-[356px] border-l border-light-gray bg-white z-40" style="box-shadow: -6px 0 18px rgba(0, 0, 0, 0.12), 0 -4px 14px rgba(0, 0, 0, 0.06), 0 4px 14px rgba(0, 0, 0, 0.06);">
    <!-- Header -->
    <div class="border-b border-light-gray px-6 py-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-horizon-500">Fyn</h3>
      </div>
    </div>

    <!-- Messages area -->
    <div class="flex-1 overflow-y-auto space-y-4 p-6">
      <!-- Welcome message -->
      <div class="flex justify-start">
        <div class="max-w-[85%] rounded-lg px-3 py-2 bg-savannah-100 border border-light-gray">
          <p class="text-sm leading-relaxed text-horizon-500">Hi! I'm Fyn, your financial companion. I can help you understand your finances, answer questions about pensions, tax, savings, and more.</p>
        </div>
      </div>

      <!-- Second message -->
      <div class="flex justify-start">
        <div class="max-w-[85%] rounded-lg px-3 py-2 bg-savannah-100 border border-light-gray">
          <p class="text-sm leading-relaxed text-horizon-500 mb-2">Register for free to start chatting with me. I'll help you:</p>
          <ul class="text-sm text-horizon-500 space-y-1">
            <li class="flex items-start gap-1.5">
              <svg class="w-3.5 h-3.5 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
              Set up your financial dashboard
            </li>
            <li class="flex items-start gap-1.5">
              <svg class="w-3.5 h-3.5 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
              Get personalised recommendations
            </li>
            <li class="flex items-start gap-1.5">
              <svg class="w-3.5 h-3.5 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
              Answer your financial questions
            </li>
          </ul>
        </div>
      </div>

      <!-- Suggested prompts -->
      <div class="flex flex-col items-center justify-center py-2">
        <p class="text-sm text-neutral-500 mb-4">How can I help with your finances?</p>
        <div class="space-y-2 w-full">
          <button
            v-for="prompt in suggestedPrompts"
            :key="prompt"
            class="w-full text-left px-3 py-2 text-sm bg-savannah-100 hover:bg-savannah-100 border border-light-gray rounded-lg transition-colors text-neutral-500 cursor-default"
          >
            {{ prompt }}
          </button>
        </div>
      </div>
    </div>

    <!-- Input area -->
    <div class="border-t border-light-gray p-4">
      <div class="flex items-center gap-2">
        <div class="flex-1 relative">
          <input
            type="text"
            placeholder="Ask Fyn anything..."
            class="w-full px-3 py-2.5 text-sm bg-eggshell-500 border border-light-gray rounded-lg text-neutral-400 cursor-default focus:outline-none"
            readonly
            @click="$router.push('/register?from=fyn')"
          />
        </div>
        <router-link
          to="/register?from=fyn"
          class="flex-shrink-0 w-10 h-10 flex items-center justify-center bg-raspberry-500 hover:bg-raspberry-600 text-white rounded-lg transition-colors no-underline"
          title="Get started"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
          </svg>
        </router-link>
      </div>
    </div>
  </aside>
</template>

<script>
export default {
  name: 'StaticFynChat',
  data() {
    return {
      suggestedPrompts: [
        'How much do I need to retire?',
        'Am I using my ISA allowance?',
        'What is my net worth?',
      ],
    };
  },
};
</script>
