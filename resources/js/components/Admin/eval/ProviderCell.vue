<template>
  <div v-if="run" class="space-y-1">
    <div class="text-xs font-mono text-horizon-500">{{ run.model }}</div>
    <div class="flex gap-2 text-xs text-neutral-500">
      <span>{{ run.duration_ms }}ms</span>
      <span class="text-neutral-300">·</span>
      <span>{{ run.sse_event_count }} ev</span>
      <span class="text-neutral-300">·</span>
      <span>{{ run.response_chars }} chars</span>
    </div>
    <div v-if="!run.has_fixture" class="text-xs text-violet-600">fixture missing</div>
  </div>
  <span v-else class="text-neutral-300 text-xs italic">—</span>
</template>

<script>
export default {
  name: 'ProviderCell',
  props: {
    run: { type: Object, default: null },
  },
};
</script>
