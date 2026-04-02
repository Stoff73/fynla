<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">{{ hero.headline }}</h1>
        <p class="text-lg text-white/70 max-w-2xl mb-6">{{ hero.subheadline }}</p>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            {{ hero.primaryCta || 'Try the demo' }}
          </a>
          <router-link :to="hero.secondaryCtaLink || '/how-it-works'" class="text-white/85 underline underline-offset-3 hover:text-white transition-colors text-sm">
            {{ hero.secondaryCta || 'See how it works' }}
          </router-link>
        </div>
      </div>
    </div>

    <!-- Quote -->
    <section v-if="hero.socialProof" class="py-14 bg-light-pink-100">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <blockquote>
          <p class="text-2xl sm:text-3xl md:text-4xl font-semibold text-horizon-500 italic leading-relaxed">
            <span class="inline-block text-6xl sm:text-7xl md:text-8xl text-raspberry-500/25 not-italic" style="font-family: Georgia, 'Times New Roman', serif; line-height:0.5; vertical-align:-0.15em; margin-right:0.05em;">&ldquo;</span>{{ cleanQuote }}<span class="inline-block text-6xl sm:text-7xl md:text-8xl text-raspberry-500/25 not-italic" style="font-family: Georgia, 'Times New Roman', serif; line-height:0.5; vertical-align:-0.5em; margin-left:0.05em;">&rdquo;</span>
          </p>
        </blockquote>
      </div>
    </section>

    <!-- Problem -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">{{ problem.headline }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="(item, i) in problem.points" :key="i" class="bg-light-pink-100 rounded-lg p-6">
            <h3 class="text-lg font-bold text-horizon-500 mb-2">{{ item.title }}</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">{{ item.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Solution Cards -->
    <section class="py-14 bg-horizon-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-8 text-center">{{ solution.headline }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="(card, i) in solution.cards" :key="i" class="bg-white/10 rounded-lg border border-white/20 p-6">
            <h3 class="text-lg font-bold text-white mb-2">{{ card.title }}</h3>
            <p class="text-sm text-white/70 leading-relaxed">{{ card.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Optional Explainer -->
    <section v-if="explainer" class="py-14 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-4 text-center">{{ explainer.headline }}</h2>
        <div class="bg-eggshell-500 rounded-lg border border-light-gray p-5">
          <p class="text-sm text-neutral-500 leading-relaxed whitespace-pre-line">{{ explainer.body }}</p>
        </div>
      </div>
    </section>

    <!-- Optional Emotional Section -->
    <section v-if="emotional" class="py-14 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-4">{{ emotional.headline }}</h2>
        <p class="text-sm text-neutral-500 leading-relaxed">{{ emotional.body }}</p>
      </div>
    </section>

    <!-- Comparison Table -->
    <section v-if="comparison" class="py-14 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">{{ comparison.headline }}</h2>
        <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-light-gray">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-light-gray">
                <th class="text-left py-4 px-6 font-medium text-neutral-500"></th>
                <th v-for="col in comparison.columns" :key="col" class="text-left py-4 px-6 font-bold text-horizon-500">{{ col }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in comparison.rows" :key="i" class="border-b border-light-gray last:border-b-0">
                <td class="py-3 px-6 text-horizon-500 font-bold">{{ row.label }}</td>
                <td v-for="(val, j) in row.values" :key="j" class="py-3 px-6 text-neutral-500">
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
    <section class="py-14 bg-horizon-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-3">Frequently asked questions</h2>
        </div>
        <div class="space-y-4">
          <div v-for="(item, idx) in faq" :key="idx" class="bg-white/10 rounded-xl border border-white/20 overflow-hidden">
            <button @click="toggleFaq(idx)" class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-white/20 transition-colors">
              <span class="text-base font-semibold text-white pr-4">{{ item.q }}</span>
              <svg class="w-5 h-5 text-white/50 flex-shrink-0 transition-transform duration-200" :class="{ 'rotate-180': openFaq[idx] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="openFaq[idx]" class="px-6 pb-4 pt-1 border-t border-white/10">
              <p class="text-sm text-white/80 pt-3 leading-relaxed">{{ item.a }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">{{ finalCta.headline }}</h2>
        <p class="text-sm text-neutral-500 max-w-lg mx-auto mb-6">{{ finalCta.body }}</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
            Try the demo
          </a>
          <router-link to="/register" class="px-6 py-2.5 bg-horizon-500 text-white text-sm font-semibold rounded-lg hover:bg-horizon-600 transition-colors">
            Start your free trial
          </router-link>
        </div>
      </div>
    </section>

    <!-- Related Links -->
    <section v-if="relatedLinks && relatedLinks.length" class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4 flex-wrap">
          <h3 class="text-sm font-semibold text-neutral-500 uppercase tracking-wide">Related links</h3>
          <router-link
            v-for="link in relatedLinks"
            :key="link.to"
            :to="link.to"
            class="px-3 py-1.5 text-xs font-medium text-horizon-500 bg-white rounded-full hover:text-raspberry-500 transition-colors"
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
  computed: {
    cleanQuote() {
      // Strip surrounding quotes from socialProof if present
      const raw = this.hero.socialProof || '';
      return raw.replace(/^["'""]+|["'""]+$/g, '');
    },
  },
  methods: {
    toggleFaq(idx) {
      this.openFaq = { ...this.openFaq, [idx]: !this.openFaq[idx] };
    },
  },
};
</script>
