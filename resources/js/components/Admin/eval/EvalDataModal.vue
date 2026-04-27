<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-horizon-500 bg-opacity-50"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col mx-4">
      <div class="px-6 py-4 border-b border-light-gray flex items-start justify-between">
        <div>
          <h3 class="font-display text-h3 text-horizon-500">{{ title }}</h3>
          <p v-if="subtitle" class="text-sm text-neutral-500 mt-1">{{ subtitle }}</p>
        </div>
        <button
          @click="$emit('close')"
          class="text-neutral-400 hover:text-horizon-500 text-2xl leading-none"
        >
          ×
        </button>
      </div>
      <div class="flex-1 overflow-auto p-6 bg-savannah-50">
        <div v-if="loading" class="flex justify-center py-12">
          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="text-raspberry-700">{{ error }}</div>
        <pre v-else-if="content !== null && content !== undefined" class="text-xs font-mono text-horizon-500 whitespace-pre-wrap break-words">{{ content }}</pre>
        <div v-else class="text-neutral-400 italic">No content available.</div>
      </div>
      <div v-if="footer" class="px-6 py-3 border-t border-light-gray text-xs text-neutral-500">
        {{ footer }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EvalDataModal',
  props: {
    open: { type: Boolean, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    content: { type: String, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    footer: { type: String, default: '' },
  },
  emits: ['close'],
};
</script>
