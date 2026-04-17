<template>
  <div class="article-body">
    <component
      v-for="(block, index) in blocks"
      :key="index"
      :is="componentFor(block.type)"
      :block="block"
    />
  </div>
</template>

<script>
import HeadingBlock from './blocks/HeadingBlock.vue';
import ParagraphBlock from './blocks/ParagraphBlock.vue';
import ListBlock from './blocks/ListBlock.vue';
import ImageBlock from './blocks/ImageBlock.vue';
import PullQuoteBlock from './blocks/PullQuoteBlock.vue';
import CalloutBlock from './blocks/CalloutBlock.vue';
import DividerBlock from './blocks/DividerBlock.vue';
import CtaButtonBlock from './blocks/CtaButtonBlock.vue';
import TaxYearStatBlock from './blocks/TaxYearStatBlock.vue';
import RelatedArticlesBlock from './blocks/RelatedArticlesBlock.vue';
import KeyTakeawaysBlock from './blocks/KeyTakeawaysBlock.vue';

const TYPE_MAP = {
  heading: HeadingBlock,
  paragraph: ParagraphBlock,
  list: ListBlock,
  image: ImageBlock,
  pull_quote: PullQuoteBlock,
  callout: CalloutBlock,
  divider: DividerBlock,
  cta_button: CtaButtonBlock,
  tax_year_stat: TaxYearStatBlock,
  related_articles: RelatedArticlesBlock,
  key_takeaways: KeyTakeawaysBlock,
};

export default {
  name: 'ArticleBlockRenderer',
  props: {
    blocks: { type: Array, required: true },
  },
  methods: {
    componentFor(type) { return TYPE_MAP[type] || null; },
  },
};
</script>
