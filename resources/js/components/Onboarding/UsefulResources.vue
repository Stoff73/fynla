<template>
  <div v-if="links && links.length" class="bg-white border border-light-gray rounded-lg shadow-sm p-4">
    <h4 class="text-xs font-bold text-horizon-500 uppercase tracking-wide mb-3">Useful Resources</h4>
    <ul class="space-y-2">
      <li v-for="link in links" :key="link.url" class="flex items-center">
        <a
          :href="link.url"
          target="_blank"
          rel="noopener noreferrer"
          class="text-body-sm text-violet-500 hover:text-violet-700 underline font-medium"
        >
          {{ link.label }}
          <svg class="inline-block w-3 h-3 ml-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>
        <span class="text-[10px] text-neutral-500 ml-2">{{ link.source }}</span>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'UsefulResources',
  props: {
    links: {
      type: Array,
      required: true,
      validator: (val) => val.every(l => l.label && l.url && l.source),
    },
  },
};
</script>
