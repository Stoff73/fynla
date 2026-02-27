<template>
  <div class="ai-message-content text-sm leading-relaxed">
    <!-- Navigation action card -->
    <div
      v-if="message.role === 'navigation'"
      class="flex items-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-lg cursor-pointer
             hover:bg-blue-100 transition-colors"
      @click="handleNavigation"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-600 flex-shrink-0">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
      </svg>
      <span class="text-blue-700 font-medium">{{ message.metadata?.description || 'Go to page' }}</span>
    </div>

    <!-- Entity created card -->
    <div
      v-else-if="message.role === 'entity_created'"
      class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-green-600 flex-shrink-0">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
      </svg>
      <span class="text-green-700 font-medium">
        {{ entityLabel }} created: {{ message.content }}
      </span>
    </div>

    <!-- Regular text message -->
    <div v-else v-html="formattedContent"></div>
  </div>
</template>

<script>
export default {
    name: 'AiMessageContent',

    props: {
        message: {
            type: Object,
            required: true,
        },
    },

    emits: ['navigate'],

    computed: {
        formattedContent() {
            if (!this.message.content) return '';

            let text = this.escapeHtml(this.message.content);

            // Bold: **text**
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Italic: *text*
            text = text.replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');

            // Unordered lists: lines starting with - or *
            text = text.replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>');
            text = text.replace(/(<li>.*<\/li>\n?)+/g, '<ul class="list-disc ml-4 my-2 space-y-1">$&</ul>');

            // Numbered lists: lines starting with 1. 2. etc.
            text = text.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');

            // Currency formatting highlight
            text = text.replace(/£([\d,]+(?:\.\d{2})?)/g, '<span class="font-semibold text-gray-900">£$1</span>');

            // Line breaks
            text = text.replace(/\n\n/g, '</p><p class="mt-2">');
            text = text.replace(/\n/g, '<br>');

            return `<p>${text}</p>`;
        },

        entityLabel() {
            const type = this.message.metadata?.entity_type;
            const labels = {
                goal: 'Goal',
                life_event: 'Life event',
                savings_account: 'Savings account',
                investment_account: 'Investment account',
                dc_pension: 'Pension',
                db_pension: 'Pension',
                property: 'Property',
                mortgage: 'Mortgage',
                life_insurance_policy: 'Life insurance policy',
                critical_illness_policy: 'Critical illness policy',
                income_protection_policy: 'Income protection policy',
                estate_asset: 'Estate asset',
                estate_liability: 'Estate liability',
                estate_gift: 'Gift',
            };
            return labels[type] || 'Item';
        },
    },

    methods: {
        handleNavigation() {
            const routePath = this.message.metadata?.route_path;
            if (routePath) {
                this.$emit('navigate', routePath);
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
    },
};
</script>

<style scoped>
.ai-message-content :deep(ul) {
  list-style-type: disc;
  margin-left: 1rem;
}
.ai-message-content :deep(li) {
  margin-bottom: 0.25rem;
}
.ai-message-content :deep(p) {
  margin-bottom: 0;
}
.ai-message-content :deep(p + p) {
  margin-top: 0.5rem;
}
</style>
