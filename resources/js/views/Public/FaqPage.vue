<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Frequently Asked <span class="text-raspberry-300">Questions</span>
        </h1>
        <p class="text-lg text-white/70">
          Everything you need to know about Fynla — from what it does, to how it works, to whether it's right for you.
        </p>
      </div>
    </div>

    <!-- Category Navigation -->
    <section class="bg-white border-b border-light-gray sticky top-20 z-40">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex overflow-x-auto gap-1 py-2 scrollbar-hide">
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="activeCategory = cat.id"
            class="px-3 py-1.5 text-xs font-medium rounded-full whitespace-nowrap transition-colors"
            :class="activeCategory === cat.id
              ? 'bg-raspberry-500 text-white'
              : 'text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500'"
          >
            {{ cat.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- FAQ Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div v-for="cat in filteredCategories" :key="cat.id" class="mb-10">
          <h2 class="text-lg font-bold text-horizon-500 mb-4">{{ cat.label }}</h2>
          <div class="space-y-2">
            <div
              v-for="(item, idx) in cat.items"
              :key="idx"
              class="bg-white rounded-lg border border-light-gray overflow-hidden"
            >
              <button
                @click="toggle(cat.id, idx)"
                class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-savannah-50 transition-colors"
              >
                <span class="text-sm font-medium text-horizon-500 pr-4">{{ item.q }}</span>
                <svg
                  class="w-4 h-4 text-neutral-400 flex-shrink-0 transition-transform duration-200"
                  :class="{ 'rotate-180': isOpen(cat.id, idx) }"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="isOpen(cat.id, idx)"
                class="px-4 pb-3 border-t border-light-gray"
              >
                <p class="text-sm text-neutral-500 pt-3 leading-relaxed">{{ item.a }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Still Have Questions -->
    <section class="py-12 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-horizon-500 mb-2">Still Have Questions?</h2>
        <p class="text-sm text-neutral-500 mb-6">Can't find what you're looking for? Get in touch and we'll help.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a
            href="mailto:hello@fynla.org"
            class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
          >
            Contact Us
          </a>
          <a
            href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
            class="px-6 py-2.5 bg-white text-horizon-500 text-sm font-semibold rounded-lg border border-light-gray hover:bg-savannah-50 transition-colors"
          >
            Try the Demo
          </a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'FaqPage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      activeCategory: 'all',
      openItems: {},
      categories: [
        { id: 'all', label: 'All' },
        { id: 'about', label: 'About Fynla' },
        { id: 'getting-started', label: 'Getting Started' },
        { id: 'features', label: 'Features' },
        { id: 'pricing', label: 'Pricing & Plans' },
        { id: 'security', label: 'Security & Privacy' },
        { id: 'technical', label: 'Technical' },
      ],
      faqData: [
        {
          id: 'about',
          label: 'About Fynla',
          items: [
            {
              q: 'What is Fynla?',
              a: 'Fynla is a UK financial planning platform that helps you see your complete financial picture — pensions, property, investments, protection, tax, and retirement planning — in one place. It\'s designed for people who want to plan their finances properly but don\'t want to pay thousands for an IFA.',
            },
            {
              q: 'Is Fynla a financial adviser?',
              a: 'No. Fynla is a planning tool, not a regulated financial adviser. We help you understand your financial position, model scenarios, and make informed decisions — but we don\'t recommend specific financial products or give personalised financial advice. For complex situations, we\'d encourage you to use Fynla alongside a qualified adviser.',
            },
            {
              q: 'Who is Fynla for?',
              a: 'Anyone in the UK who wants to understand and plan their finances. We have features for every stage of life — from students building their first savings habits to pre-retirees planning drawdown strategies. Most of our users are people who know they should be planning but haven\'t had the right tools to do it properly.',
            },
            {
              q: 'How is Fynla different from budgeting apps like Emma or Plum?',
              a: 'Budgeting apps track where your money went (backward-looking). Fynla plans where your money is going (forward-looking). Fynla covers pensions, retirement projections, Inheritance Tax planning, protection analysis, and long-term financial modelling — things budgeting apps don\'t touch.',
            },
            {
              q: 'How is Fynla different from an IFA or wealth manager?',
              a: 'An IFA provides personalised advice and can execute transactions (buy products, transfer pensions). Fynla provides the planning, projections, and analysis — the part most people actually need — at a fraction of the cost. Many users find that Fynla either replaces their need for an IFA or makes their IFA sessions shorter and more productive.',
            },
          ],
        },
        {
          id: 'getting-started',
          label: 'Getting Started',
          items: [
            {
              q: 'How long does it take to set up?',
              a: 'Most people complete their initial setup in 15-20 minutes. You\'ll add your key financial data — pensions, property, savings, insurance — and immediately see your dashboard. You can add more detail over time.',
            },
            {
              q: 'Can I try it before signing up?',
              a: 'Yes. Our interactive demo lets you explore every feature using pre-built personas with realistic sample data. No account needed, no personal data required.',
            },
            {
              q: 'Do I need to connect my bank accounts?',
              a: 'No. Fynla doesn\'t require bank connections. You enter your data manually, which means you stay in full control and don\'t need to share banking credentials. We\'re exploring optional open banking integrations for the future.',
            },
            {
              q: 'What data do I need to get started?',
              a: 'At minimum: your pension values (check provider statements or online portals), property value estimate, savings/investment balances, and any outstanding debts. The more complete your data, the more accurate your plan — but you can start with the basics and add detail over time.',
            },
          ],
        },
        {
          id: 'features',
          label: 'Features & Capabilities',
          items: [
            {
              q: 'What features does Fynla include?',
              a: 'Fynla has 32 features across financial planning, retirement, pensions, property, investments, protection, tax, and estate planning. Key highlights include: net worth dashboard, pension tracker, "When Can I Retire?" calculator, Inheritance Tax planning, protection gap analysis, Monte Carlo simulations, ICE letters, and Fyn AI assistant. See our features page for the full list.',
            },
            {
              q: 'What is Fyn?',
              a: 'Fyn is our AI assistant built into the platform. Ask Fyn any financial planning question in plain English — from "what\'s an ISA?" to "how does taper relief work?" — and get a clear, jargon-free answer. Fyn also helps you navigate the platform and understand your plan.',
            },
            {
              q: 'Does Fynla do Monte Carlo simulations?',
              a: 'Yes (Premium tier). Fynla runs thousands of market scenarios against your financial plan to give you a probability-based confidence level rather than a single projection. This is the same approach used by professional financial planners.',
            },
            {
              q: 'Can I plan as a couple?',
              a: 'Yes. A single Fynla subscription supports both individual and joint planning. See combined net worth, joint retirement income projections, household protection needs, and Inheritance Tax for your combined estate.',
            },
            {
              q: 'Does Fynla cover the April 2027 pension Inheritance Tax changes?',
              a: 'Yes. Our Inheritance Tax planning suite (Premium tier) lets you model your estate under both current rules and the post-April 2027 rules where unused pension pots are included in your estate for Inheritance Tax purposes.',
            },
          ],
        },
        {
          id: 'pricing',
          label: 'Pricing & Plans',
          items: [
            {
              q: 'How much does Fynla cost?',
              a: 'Student tier from approximately \u00A33/month, Standard at \u00A38.50/month, and Premium at \u00A320/month. Annual billing gives you a discount. No hidden fees, no commission, no lock-in. See our pricing page for full details.',
            },
            {
              q: 'Is there a free trial?',
              a: 'Yes. Try Fynla free with full access to all features in your chosen tier. No credit card required to start.',
            },
            {
              q: 'Can I change plans?',
              a: 'Yes, upgrade or downgrade at any time. Changes take effect from your next billing cycle.',
            },
            {
              q: 'What if I cancel?',
              a: 'You can export your data at any time. Cancel monthly plans any time; annual plans run to the end of the 12-month period. Your data is retained for 30 days after cancellation, then permanently deleted.',
            },
          ],
        },
        {
          id: 'security',
          label: 'Security & Privacy',
          items: [
            {
              q: 'Is my financial data safe?',
              a: 'Yes. Your data is encrypted in transit and at rest using industry-standard encryption. We don\'t share your data with third parties, don\'t sell data to advertisers, and don\'t earn commission from financial product providers.',
            },
            {
              q: 'Where is my data stored?',
              a: 'Fynla data is stored on secure UK-hosted servers.',
            },
            {
              q: 'Does Fynla sell my data?',
              a: 'Never. Your subscription is our only revenue source. We don\'t sell data, don\'t share it with product providers, and don\'t display advertising.',
            },
            {
              q: 'Can I export my data?',
              a: 'Yes. You can export your data at any time in standard formats.',
            },
          ],
        },
        {
          id: 'technical',
          label: 'Technical',
          items: [
            {
              q: 'Does Fynla work on mobile?',
              a: 'Yes. Fynla is fully responsive and works on smartphones, tablets, and desktops.',
            },
            {
              q: 'Which browsers are supported?',
              a: 'Fynla works on all modern browsers — Chrome, Firefox, Safari, and Edge.',
            },
            {
              q: 'Do I need to install anything?',
              a: 'No. Fynla is a web application — just sign up and start using it in your browser.',
            },
          ],
        },
      ],
    };
  },

  computed: {
    filteredCategories() {
      if (this.activeCategory === 'all') {
        return this.faqData;
      }
      return this.faqData.filter(cat => cat.id === this.activeCategory);
    },
  },

  methods: {
    toggle(catId, idx) {
      const key = `${catId}-${idx}`;
      this.openItems = { ...this.openItems, [key]: !this.openItems[key] };
    },
    isOpen(catId, idx) {
      return !!this.openItems[`${catId}-${idx}`];
    },
  },
};
</script>
