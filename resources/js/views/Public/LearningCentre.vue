<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is Financial Planning?</h1>
        <p class="text-lg text-white/70 max-w-2xl mx-auto">
          It's simpler than it sounds. Financial planning means understanding what you have, what you need, and building a path from one to the other.
        </p>
      </div>
    </div>

    <!-- The Short Answer -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-3">The Short Answer</h2>
        <p class="text-sm text-neutral-700 leading-relaxed">
          Financial planning is the process of organising your money to meet your goals. It covers everything: saving, investing, pensions, protection, tax, and estate planning. You don't need to be wealthy to benefit — everyone with income and goals has a financial plan, whether they realise it or not.
        </p>
      </div>
    </section>

    <!-- The Six Areas -->
    <section class="py-10 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-4">The Six Areas of Financial Planning</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-for="area in areas" :key="area.title" class="bg-eggshell-500 rounded-lg border border-light-gray p-5">
            <h3 class="text-sm font-bold text-horizon-500 mb-1">{{ area.title }}</h3>
            <p class="text-xs text-neutral-600 leading-relaxed">{{ area.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Do I Need a Financial Adviser? -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-3">Do I Need a Financial Adviser?</h2>
        <p class="text-sm text-neutral-700 leading-relaxed mb-3">
          Not necessarily. A financial adviser provides regulated, personalised recommendations and can buy products on your behalf. A financial planning tool like Fynla gives you the analysis, projections, and planning — the part most people actually need.
        </p>
        <p class="text-sm text-neutral-700 leading-relaxed">
          For complex situations (trusts, business assets, cross-border tax), an adviser plus Fynla is the best combination.
        </p>
      </div>
    </section>

    <!-- How Fynla Helps -->
    <section class="py-10 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-3">How Fynla Helps</h2>
        <p class="text-sm text-neutral-700 leading-relaxed">
          Fynla covers all six areas in one platform. Add your data, see your complete picture, model scenarios, and make informed decisions. No jargon, no commission, no sales pressure.
        </p>
      </div>
    </section>

    <!-- Related Links -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-4">Related Guides</h2>
        <ul class="space-y-2">
          <li v-for="link in relatedLinks" :key="link.to">
            <router-link :to="link.to" class="flex items-center text-sm text-horizon-500 hover:text-raspberry-500 transition-colors">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 flex-shrink-0 mr-2"></span>
              {{ link.title }}
            </router-link>
          </li>
        </ul>
      </div>
    </section>

    <!-- CTA -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-base font-bold text-white mb-2">See your complete financial picture</h2>
        <p class="text-lg text-white/70 mb-5">Try Fynla free and understand where you stand in minutes.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-white text-horizon-500 text-sm font-semibold rounded-lg hover:bg-neutral-50 transition-colors">
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'LearningCentre',
  components: { PublicLayout },

  data() {
    return {
      areas: [
        { title: 'Protection', desc: 'Making sure your family is covered if something happens to you — life insurance, income protection, critical illness.' },
        { title: 'Savings & Debt', desc: 'Building an emergency fund, managing debt, and saving for goals.' },
        { title: 'Investments', desc: 'Growing your wealth over time through ISAs, pensions, and investment accounts.' },
        { title: 'Retirement', desc: 'Planning when you can stop working and how to fund it.' },
        { title: 'Tax', desc: 'Using your allowances efficiently — ISA, pension, Capital Gains Tax.' },
        { title: 'Estate', desc: 'Ensuring your wealth passes to the right people with minimal Inheritance Tax.' },
      ],
      relatedLinks: [
        { title: 'All learning guides', to: '/learn' },
        { title: 'Glossary A–Z', to: '/learn/glossary' },
        { title: 'How Fynla works', to: '/how-it-works' },
        { title: 'Starting Out: money basics', to: '/learn/guide/starting-out' },
      ],
    };
  },

  mounted() {
    document.title = 'What is Financial Planning? — A Simple UK Guide | Fynla';
    this.setMetaDescription();
  },

  methods: {
    setMetaDescription() {
      let meta = document.querySelector('meta[name="description"]');
      if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'description');
        document.head.appendChild(meta);
      }
      meta.setAttribute('content', 'Financial planning means understanding what you have, what you need, and how to get from one to the other. A jargon-free guide to planning your finances in the UK.');
    },
  },
};
</script>
