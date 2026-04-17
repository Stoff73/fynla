<template>
  <div class="space-y-2">
    <p class="text-xs text-neutral-500">Select up to 4 articles:</p>
    <div class="max-h-60 overflow-y-auto border border-light-gray rounded p-2 space-y-1">
      <label
        v-for="article in availableArticles"
        :key="article.id"
        class="flex items-center gap-2 text-sm"
      >
        <input
          type="checkbox"
          :value="article.id"
          :checked="isSelected(article.id)"
          :disabled="!isSelected(article.id) && selectedCount >= 4"
          @change="toggle(article.id)"
        />
        {{ article.title }}
      </label>
      <p v-if="!availableArticles.length" class="text-xs text-neutral-400 italic py-2">
        No published articles available yet.
      </p>
    </div>
  </div>
</template>

<script>
import insightsService from '@/services/insightsService';

export default {
  name: 'EditRelatedArticlesBlock',
  props: { block: { type: Object, required: true } },
  emits: ['update'],
  data() { return { availableArticles: [] }; },
  computed: {
    selectedCount() { return (this.block.article_ids || []).length; },
  },
  async mounted() {
    try {
      const res = await insightsService.list();
      this.availableArticles = res.data || [];
    } catch (e) {
      this.availableArticles = [];
    }
  },
  methods: {
    isSelected(id) { return (this.block.article_ids || []).includes(id); },
    toggle(id) {
      const ids = this.block.article_ids || [];
      const next = ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id];
      this.$emit('update', { ...this.block, article_ids: next });
    },
  },
};
</script>
