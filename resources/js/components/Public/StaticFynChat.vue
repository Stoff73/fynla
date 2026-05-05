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
    <div ref="scrollEl" class="flex-1 overflow-y-auto space-y-4 p-6">
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
          <ul class="text-sm text-horizon-500 space-y-1 list-disc list-inside ml-1">
            <li>Set up your financial dashboard</li>
            <li>Get personalised recommendations</li>
            <li>Answer your financial questions</li>
          </ul>
          <router-link
            :to="registerLink"
            class="mt-3 inline-block px-3 py-1.5 bg-raspberry-500 hover:bg-raspberry-600 text-white text-sm font-semibold rounded-lg transition-colors no-underline"
          >
            Register now to ask Fyn
          </router-link>
        </div>
      </div>

      <!-- Suggested prompts — hidden when responseMessage is set (interactive campaign mode) -->
      <div v-if="!interactive" class="flex flex-col items-center justify-center py-2">
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

      <!-- Conversation turns (interactive mode only) -->
      <template v-for="(turn, i) in turns" :key="i">
        <div v-if="turn.role === 'user'" class="flex justify-end">
          <div class="max-w-[85%] rounded-lg px-3 py-2 bg-raspberry-500 text-white">
            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ turn.text }}</p>
          </div>
        </div>
        <div v-else class="flex justify-start">
          <div class="max-w-[85%] rounded-lg px-3 py-2 bg-savannah-100 border border-light-gray">
            <p class="text-sm leading-relaxed text-horizon-500 mb-2">{{ turn.text }}</p>
            <router-link
              :to="registerLink"
              class="inline-block px-3 py-1.5 bg-raspberry-500 hover:bg-raspberry-600 text-white text-sm font-semibold rounded-lg transition-colors no-underline"
            >
              Register now to ask Fyn
            </router-link>
          </div>
        </div>
      </template>
    </div>

    <!-- Input area -->
    <div class="border-t border-light-gray p-4">
      <form class="flex items-center gap-2" @submit.prevent="handleSubmit">
        <div class="flex-1 relative">
          <input
            v-model="draft"
            type="text"
            placeholder="Ask Fyn anything..."
            :readonly="!interactive"
            class="w-full px-3 py-2.5 text-sm bg-eggshell-500 border border-light-gray rounded-lg focus:outline-none"
            :class="interactive ? 'text-horizon-500 cursor-text' : 'text-neutral-400 cursor-default'"
            @click="handleInputClick"
          />
        </div>
        <button
          type="submit"
          class="flex-shrink-0 px-4 h-10 flex items-center justify-center bg-raspberry-500 hover:bg-raspberry-600 text-white text-sm font-semibold rounded-lg transition-colors"
        >
          {{ interactive ? 'Send' : 'Get started' }}
        </button>
      </form>
    </div>
  </aside>
</template>

<script>
export default {
  name: 'StaticFynChat',
  props: {
    // `?from=<source>` query param appended to every register link inside the
    // panel. Defaults to 'fyn' so existing pages (CampaignPage, QuickStartPage)
    // keep their original behaviour. SaveTaxCampaignPage passes 'savetax' so
    // post-register the campaign flow runs.
    source: {
      type: String,
      default: 'fyn',
    },
    // When provided, the panel switches to interactive mode:
    //   - Hides the "How can I help…" suggested prompts.
    //   - Makes the input editable.
    //   - On submit, appends the user's message and a Fyn reply containing
    //     this string + a Register CTA pointing at /register?from=<source>.
    responseMessage: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      draft: '',
      turns: [],
      suggestedPrompts: [
        'How much do I need to retire?',
        'Am I using my ISA allowance?',
        'What is my net worth?',
      ],
    };
  },
  computed: {
    interactive() {
      return Boolean(this.responseMessage);
    },
    registerLink() {
      return { path: '/register', query: { from: this.source } };
    },
  },
  methods: {
    handleInputClick() {
      if (!this.interactive) {
        this.$router.push(this.registerLink);
      }
    },
    handleSubmit() {
      const text = this.draft.trim();
      // Send with no text is treated as the page-wide "go register" CTA, even
      // in interactive mode — keeps the rule "every CTA on the page routes to
      // /register?from=<source>" honest.
      if (!this.interactive || !text) {
        this.$router.push(this.registerLink);
        return;
      }
      this.turns.push({ role: 'user', text });
      this.turns.push({ role: 'fyn', text: this.responseMessage });
      this.draft = '';
      this.$nextTick(() => {
        const el = this.$refs.scrollEl;
        if (el) el.scrollTop = el.scrollHeight;
      });
    },
  },
};
</script>
