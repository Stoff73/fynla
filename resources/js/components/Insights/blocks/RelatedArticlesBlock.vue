<template>
  <section class="my-10">
    <h4 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-4">Related articles</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <router-link
        v-for="article in articles"
        :key="article.slug"
        :to="`/insights/${article.slug}`"
        class="p-4 bg-white border border-light-gray rounded-lg hover:bg-savannah-100 transition-colors"
      >
        <p class="text-xs text-neutral-400 uppercase tracking-wide mb-1">{{ article.category }}</p>
        <p class="text-sm font-bold text-horizon-500 leading-snug">{{ article.title }}</p>
      </router-link>
    </div>
  </section>
</template>

<script>
import insightsService from '@/services/insightsService';

export default {
  name: 'RelatedArticlesBlock',
  props: { block: { type: Object, required: true } },
  data() { return { articles: [] }; },
  async mounted() {
    if (!this.block.article_ids?.length) return;
    try {
      const res = await insightsService.list();
      const ids = new Set(this.block.article_ids);
      this.articles = (res.data || []).filter(a => ids.has(a.id)).slice(0, 4);
    } catch (e) {
      this.articles = [];
    }
  },
};
</script>
