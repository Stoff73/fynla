<template>
  <div class="ai-message-content text-sm leading-relaxed">
    <!-- Navigation action card -->
    <div
      v-if="message.role === 'navigation'"
      class="flex items-center gap-2 p-3 bg-violet-50 border border-violet-200 rounded-lg cursor-pointer
             hover:bg-violet-100 transition-colors"
      @click="handleNavigation"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-violet-600 flex-shrink-0">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
      </svg>
      <span class="text-violet-700 font-medium">{{ message.metadata?.description || 'Go to page' }}</span>
    </div>

    <!-- Entity created card -->
    <div
      v-else-if="message.role === 'entity_created'"
      class="flex items-center gap-2 p-3 bg-spring-50 border border-spring-200 rounded-lg"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-spring-600 flex-shrink-0">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
      </svg>
      <span class="text-spring-700 font-medium">
        {{ entityLabel }} created: {{ message.content }}
      </span>
    </div>

    <!-- Regular text message -->
    <div v-else v-html="formattedContent"></div>
  </div>
</template>

<script>
import { sanitizeHtml } from '@/utils/sanitizeHtml';

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

            // Strip leaked tool call metadata lines (e.g. "- get_module_analysis: module: estate")
            const toolPattern = /(\n\s*-\s*(?:get_module_analysis|get_tax_information|get_recommendations|list_records|list_goals|list_life_events|navigate_to_page|generate_financial_plan|create_\w+|update_\w+|delete_record|set_expenditure):.*)+\s*$/s;
            let cleaned = this.message.content.replace(toolPattern, '').trimEnd();

            let text = this.escapeHtml(cleaned);

            // Headings: ### text, ## text (before bold/italic to prevent conflicts)
            text = text.replace(/^###\s+(.+)$/gm, '<h4 class="font-bold text-horizon-500 text-sm mt-3 mb-1">$1</h4>');
            text = text.replace(/^##\s+(.+)$/gm, '<h3 class="font-bold text-horizon-500 text-base mt-3 mb-1">$1</h3>');

            // Bold: **text**
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Italic: *text*
            text = text.replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');

            // Unordered lists: lines starting with - or *
            text = text.replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>');
            text = text.replace(/(<li>.*<\/li>\n?)+/g, '<ul class="list-disc ml-4 my-2 space-y-1">$&</ul>');

            // Numbered lists: lines starting with 1. 2. etc.
            text = text.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');

            // Convert leaked route paths to clickable links (e.g. "/estate" or "/valuable-info?section=expenditure")
            text = text.replace(/(?:\()(\/[a-z][a-z0-9\-\/]*(?:\?[a-z0-9_=&]+)?)(?:\))/gi, (match, path) => {
                const label = this.routeLabel(path);
                return `(<a href="${path}" class="text-raspberry-500 hover:underline cursor-pointer" data-route="${path}">${label}</a>)`;
            });
            text = text.replace(/(?<![("\/a-z])(\/(?:dashboard|net-worth|estate|protection|retirement|goals|plans|holistic-plan|trusts|risk-profile|settings|valuable-info|onboarding|actions|planning)[a-z0-9\-\/]*(?:\?[a-z0-9_=&]+)?)(?!["\/a-z])/gi, (match, path) => {
                const label = this.routeLabel(path);
                return `<a href="${path}" class="text-raspberry-500 hover:underline cursor-pointer" data-route="${path}">${label}</a>`;
            });

            // Currency formatting highlight
            text = text.replace(/£([\d,]+(?:\.\d{2})?)/g, '<span class="font-semibold text-horizon-500">£$1</span>');

            // Line breaks
            text = text.replace(/\n\n/g, '</p><p class="mt-2">');
            text = text.replace(/\n/g, '<br>');

            return sanitizeHtml(`<p>${text}</p>`);
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

        routeLabel(path) {
            const labels = {
                '/dashboard': 'Dashboard',
                '/estate': 'Estate Planning',
                '/estate/will-builder': 'Will Builder',
                '/estate/power-of-attorney': 'Power of Attorney',
                '/protection': 'Protection',
                '/net-worth/wealth-summary': 'Net Worth',
                '/net-worth/cash': 'Bank Accounts',
                '/net-worth/investments': 'Investments',
                '/net-worth/retirement': 'Retirement',
                '/net-worth/property': 'Property',
                '/net-worth/liabilities': 'Liabilities',
                '/net-worth/chattels': 'Personal Valuables',
                '/net-worth/business': 'Business',
                '/goals': 'Goals',
                '/trusts': 'Trusts',
                '/plans': 'Plans',
                '/holistic-plan': 'Holistic Plan',
                '/risk-profile': 'Risk Profile',
                '/settings': 'Account Settings',
                '/settings/security': 'Security Settings',
                '/settings/assumptions': 'Assumptions',
                '/actions': 'Actions',
                '/planning/what-if': 'What If Scenarios',
                '/planning/journeys': 'Journeys',
            };

            // Exact match
            const base = path.split('?')[0];
            if (labels[base]) return labels[base];

            // Query param sections
            if (path.includes('valuable-info')) {
                const section = path.match(/section=(\w+)/)?.[1];
                const sectionLabels = { income: 'Income', expenditure: 'Expenditure', letter: 'Expression of Wishes' };
                return sectionLabels[section] || 'Your Details';
            }

            return base.split('/').pop().replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },
    },

    mounted() {
        this.$el.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-route]');
            if (link) {
                e.preventDefault();
                this.$emit('navigate', link.dataset.route);
            }
        });
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
