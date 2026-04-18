<template>
  <div class="article-body">
    <component
      v-for="(block, index) in blocks"
      :key="index"
      :is="componentFor(block.type)"
      :block="block"
      :class="{ 'clear-both': breaksFloat(block.type) }"
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

// Blocks that should start a fresh horizontal section, breaking any preceding
// float so a floated image doesn't bleed into a new heading or a callout.
// Paragraphs and lists intentionally flow next to floats so text wraps.
const BREAKS_FLOAT = new Set([
  'heading',
  'divider',
  'pull_quote',
  'callout',
  'cta_button',
  'tax_year_stat',
  'related_articles',
  'key_takeaways',
]);

export default {
  name: 'ArticleBlockRenderer',
  props: {
    blocks: { type: Array, required: true },
  },
  methods: {
    componentFor(type) { return TYPE_MAP[type] || null; },
    breaksFloat(type) { return BREAKS_FLOAT.has(type); },
  },
};
</script>

<style scoped>
/* flow-root establishes a new block formatting context so floats inside the
   article body are contained — they cannot bleed into the footer below. */
.article-body {
  display: flow-root;
}
</style>
