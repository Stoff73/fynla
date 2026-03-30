<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">{{ hero.headline }}</h1>
        <p class="text-lg text-white/70 max-w-2xl mb-6">{{ hero.subheadline }}</p>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
          <router-link to="/?demo=true" class="px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            {{ hero.primaryCta || 'Try the Demo' }}
          </router-link>
          <router-link :to="hero.secondaryCtaLink || '/how-it-works'" class="text-white/85 underline underline-offset-3 hover:text-white transition-colors text-sm">
            {{ hero.secondaryCta || 'See How It Works' }}
          </router-link>
        </div>
        <p v-if="hero.socialProof" class="mt-4 text-xs text-white/50 italic max-w-lg">{{ hero.socialProof }}</p>
      </div>
    </div>

    <!-- Problem -->
    <section class="py-14 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-horizon-500 mb-6">{{ problem.headline }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="(item, i) in problem.points" :key="i" class="bg-eggshell-500 rounded-lg border border-light-gray p-4">
            <h3 class="text-sm font-semibold text-horizon-500 mb-2">{{ item.title }}</h3>
            <p class="text-xs text-neutral-500 leading-relaxed">{{ item.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Solution Cards -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-horizon-500 mb-6">{{ solution.headline }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="(card, i) in solution.cards" :key="i" class="bg-white rounded-lg border border-light-gray p-4">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-3" :class="cardBg(i)">
              <span class="text-sm">{{ card.icon || cardIcon(i) }}</span>
            </div>
            <h3 class="text-sm font-semibold text-horizon-500 mb-1.5">{{ card.title }}</h3>
            <p class="text-xs text-neutral-500 leading-relaxed">{{ card.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Optional Explainer -->
    <section v-if="explainer" class="py-14 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-horizon-500 mb-4">{{ explainer.headline }}</h2>
        <div class="bg-eggshell-500 rounded-lg border border-light-gray p-5">
          <p class="text-sm text-neutral-500 leading-relaxed whitespace-pre-line">{{ explainer.body }}</p>
        </div>
      </div>
    </section>

    <!-- Optional Emotional Section -->
    <section v-if="emotional" class="py-14 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-horizon-500 mb-4">{{ emotional.headline }}</h2>
        <p class="text-sm text-neutral-500 leading-relaxed">{{ emotional.body }}</p>
      </div>
    </section>

    <!-- Comparison Table -->
    <section v-if="comparison" class="py-14" :class="explainer || emotional ? 'bg-eggshell-500' : 'bg-white'">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-horizon-500 mb-6">{{ comparison.headline }}</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-light-gray">
                <th class="text-left py-2 pr-4 font-medium text-neutral-500"></th>
                <th v-for="col in comparison.columns" :key="col" class="text-left py-2 px-3 font-semibold text-horizon-500">{{ col }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in comparison.rows" :key="i" class="border-b border-light-gray">
                <td class="py-2 pr-4 text-neutral-500 font-medium">{{ row.label }}</td>
                <td v-for="(val, j) in row.values" :key="j" class="py-2 px-3 text-neutral-500">
                  <span v-if="val === true" class="text-spring-600 font-semibold">Yes</span>
                  <span v-else-if="val === false" class="text-neutral-400">No</span>
                  <span v-else>{{ val }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="py-14" :class="comparison ? 'bg-white' : 'bg-eggshell-500'">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-horizon-500 mb-6">Common Questions</h2>
        <div class="space-y-2">
          <div v-for="(item, idx) in faq" :key="idx" class="bg-white rounded-lg border border-light-gray overflow-hidden">
            <button @click="toggleFaq(idx)" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-savannah-50 transition-colors">
              <span class="text-sm font-medium text-horizon-500 pr-4">{{ item.q }}</span>
              <svg class="w-4 h-4 text-neutral-400 flex-shrink-0 transition-transform duration-200" :class="{ 'rotate-180': openFaq[idx] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="openFaq[idx]" class="px-4 pb-3 border-t border-light-gray">
              <p class="text-sm text-neutral-500 pt-3 leading-relaxed">{{ item.a }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="py-14 bg-gradient-to-br from-horizon-500 to-horizon-600">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-white mb-3">{{ finalCta.headline }}</h2>
        <p class="text-sm text-horizon-200 max-w-lg mx-auto mb-6">{{ finalCta.body }}</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <router-link to="/?demo=true" class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
            Try the Demo
          </router-link>
          <router-link to="/register" class="px-6 py-2.5 bg-white/10 text-white text-sm font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors">
            Start Your Free Trial
          </router-link>
        </div>
      </div>
    </section>

    <!-- Internal Links -->
    <section v-if="relatedLinks && relatedLinks.length" class="py-10 bg-eggshell-500">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h3 class="text-sm font-semibold text-neutral-500 uppercase tracking-wide mb-3">Related Features</h3>
        <div class="flex flex-wrap gap-2">
          <router-link
            v-for="link in relatedLinks"
            :key="link.to"
            :to="link.to"
            class="px-3 py-1.5 text-xs font-medium text-horizon-500 bg-white rounded-full border border-light-gray hover:border-raspberry-400 hover:text-raspberry-500 transition-colors"
          >
            {{ link.label }}
          </router-link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

const BG_CLASSES = [
  'bg-raspberry-100', 'bg-spring-100', 'bg-violet-100',
  'bg-savannah-100', 'bg-horizon-100', 'bg-raspberry-50',
];
const ICONS = ['1', '2', '3', '4', '5', '6'];

export default {
  name: 'FeaturePageLayout',
  components: { PublicLayout },
  props: {
    hero: { type: Object, required: true },
    problem: { type: Object, required: true },
    solution: { type: Object, required: true },
    comparison: { type: Object, default: null },
    explainer: { type: Object, default: null },
    emotional: { type: Object, default: null },
    faq: { type: Array, required: true },
    finalCta: { type: Object, required: true },
    relatedLinks: { type: Array, default: () => [] },
  },
  data() {
    return { openFaq: {} };
  },
  methods: {
    toggleFaq(idx) {
      this.openFaq = { ...this.openFaq, [idx]: !this.openFaq[idx] };
    },
    cardBg(i) { return BG_CLASSES[i % BG_CLASSES.length]; },
    cardIcon(i) { return ICONS[i] || String(i + 1); },
  },
};
</script>
