<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-16 sm:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">Insights</h1>
        <p class="text-base text-white/80 max-w-xl mx-auto">
          Timely commentary on what's changing — and what it means for you.
        </p>
      </div>
    </section>

    <!-- Intro + Articles -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-500 mb-10 leading-relaxed">
          We don't call this a blog. These are focused, practical pieces about tax changes, pension rules, budget updates, and their impact on your financial plan.
        </p>

        <!-- Article Cards -->
        <div class="space-y-4">
          <router-link
            v-for="article in articles"
            :key="article.slug"
            :to="article.slug"
            class="block bg-white rounded-lg border border-light-gray p-5 hover:shadow-md hover:border-raspberry-300 transition-all group"
          >
            <p class="text-xs text-neutral-400 mb-1">{{ article.date }}</p>
            <h2 class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-1">
              {{ article.title }}
            </h2>
            <p class="text-xs text-neutral-500 mb-2">{{ article.summary }}</p>
            <span class="text-xs font-semibold text-raspberry-500">Read &rarr;</span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Stay Updated -->
    <section class="py-12 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-lg font-bold text-horizon-500 mb-2">Want to stay updated?</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          More insights are on the way. Register for a free Fynla account and we'll let you know when new articles are published.
        </p>
        <router-link
          to="/register"
          class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Register for free
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'InsightsHubPage',
  components: { PublicLayout },

  data() {
    return {
      articles: [
        {
          slug: '/insights/pension-iht-changes-2027',
          title: 'Pension Inheritance Tax Changes: April 2027',
          date: 'March 2026',
          summary: 'From April 2027, unused pension pots will be included in your estate for Inheritance Tax. Here\'s what\'s changing and what you can do.',
        },
        {
          slug: '/insights/isa-allowance-2025-26',
          title: 'ISA Allowance 2025/26: Make the Most of Your \u00A320,000',
          date: 'March 2026',
          summary: 'The ISA allowance remains at \u00A320,000. Types, deadlines, and strategies for maximising your tax-free savings.',
        },
      ],
    };
  },

  mounted() {
    document.title = 'Insights \u2014 UK Financial Planning News & Commentary | Fynla';
  },
};
</script>
