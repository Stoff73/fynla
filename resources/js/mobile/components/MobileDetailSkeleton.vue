<template>
  <div class="space-y-3">
    <div class="bg-white rounded-xl p-6 animate-pulse">
      <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
    </div>
    <div v-for="n in rows" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
      <div class="w-40 h-4 bg-savannah-100 rounded"></div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MobileDetailSkeleton',
  props: {
    rows: { type: Number, default: 3 },
  },
};
</script>
