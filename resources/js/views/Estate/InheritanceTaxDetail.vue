<template>
  <AppLayout>
    <div class="module-gradient py-2 sm:py-6">
      <!-- Back link -->
      <button
        class="detail-inline-back mb-4"
        @click="$router.push('/estate')"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Estate Planning
      </button>

      <IHTPlanning :table-only="true" />
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import IHTPlanning from '@/components/Estate/IHTPlanning.vue';

export default {
  name: 'InheritanceTaxDetail',
  components: { AppLayout, IHTPlanning },
};
</script>
