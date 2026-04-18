<template>
  <aside class="my-6">
    <div class="flex items-start gap-3">
      <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="iconColour" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" :d="iconPath" clip-rule="evenodd" />
      </svg>
      <div class="flex-1 text-sm leading-relaxed" v-html="sanitised"></div>
    </div>
  </aside>
</template>

<script>
import { sanitiseBlock } from '@/utils/insightsSanitize';

const ICON_COLOURS = {
  info: 'text-horizon-500',
  tip: 'text-spring-500',
  success: 'text-spring-500',
  warning: 'text-violet-500',
};

const ICONS = {
  info: 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
  tip: 'M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z',
  success: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
  warning: 'M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z',
};

export default {
  name: 'CalloutBlock',
  props: { block: { type: Object, required: true } },
  computed: {
    iconColour() { return ICON_COLOURS[this.block.variant] || ICON_COLOURS.info; },
    iconPath() { return ICONS[this.block.variant] || ICONS.info; },
    sanitised() { return sanitiseBlock(this.block.html || ''); },
  },
};
</script>
