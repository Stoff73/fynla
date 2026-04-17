<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-horizon-500/50 flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full m-4 p-6">
      <h3 class="text-xl font-bold text-horizon-500 mb-4">Add a block</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <button
          v-for="option in blockOptions"
          :key="option.type"
          type="button"
          @click="pick(option.type)"
          class="p-4 bg-savannah-100 hover:bg-light-pink-100 rounded-lg text-left transition-colors"
        >
          <p class="font-semibold text-horizon-500 text-sm">{{ option.label }}</p>
          <p class="text-xs text-neutral-500 mt-1">{{ option.description }}</p>
        </button>
      </div>
      <button
        type="button"
        @click="$emit('close')"
        class="mt-4 text-sm text-neutral-500 hover:text-horizon-500"
      >
        Cancel
      </button>
    </div>
  </div>
</template>

<script>
const BLOCK_OPTIONS = [
  { type: 'heading', label: 'Heading', description: 'Section title (H2/H3/H4)', defaults: { level: 2, text: '' } },
  { type: 'paragraph', label: 'Paragraph', description: 'Rich text body content', defaults: { html: '' } },
  { type: 'list', label: 'List', description: 'Bulleted or numbered', defaults: { ordered: false, items: [''] } },
  { type: 'image', label: 'Image', description: 'Upload or embed', defaults: { path: '', alt: '', alignment: 'full' } },
  { type: 'pull_quote', label: 'Pull quote', description: 'Highlighted quote', defaults: { text: '' } },
  { type: 'callout', label: 'Callout', description: 'Info / tip / warning', defaults: { variant: 'info', html: '' } },
  { type: 'divider', label: 'Divider', description: 'Horizontal rule', defaults: {} },
  { type: 'cta_button', label: 'CTA button', description: 'Link button', defaults: { label: '', href: '', style: 'primary' } },
  { type: 'tax_year_stat', label: 'Tax year stat', description: 'Live value from TaxConfig', defaults: { stat_key: '', label: '' } },
  { type: 'related_articles', label: 'Related articles', description: 'Link 2-4 other posts', defaults: { article_ids: [] } },
  { type: 'key_takeaways', label: 'Key takeaways', description: 'Top-of-article bullets', defaults: { bullets: [''] } },
];

export default {
  name: 'BlockPickerModal',
  props: { isOpen: { type: Boolean, required: true } },
  emits: ['close', 'pick'],
  data() { return { blockOptions: BLOCK_OPTIONS }; },
  methods: {
    pick(type) {
      const option = BLOCK_OPTIONS.find(o => o.type === type);
      this.$emit('pick', { type, ...option.defaults });
    },
  },
};
</script>
