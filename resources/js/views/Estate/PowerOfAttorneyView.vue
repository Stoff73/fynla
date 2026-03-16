<template>
  <AppLayout>
    <div class="py-2 sm:py-6">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">Lasting Power of Attorney</h1>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>

        <!-- LPA Tab Content -->
        <PowerOfAttorneyTab v-else />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import PowerOfAttorneyTab from '@/components/Estate/PowerOfAttorneyTab.vue';

export default {
  name: 'PowerOfAttorneyView',

  components: {
    AppLayout,
    PowerOfAttorneyTab,
  },

  data() {
    return {
      loading: true,
    };
  },

  async mounted() {
    try {
      await this.$store.dispatch('estate/fetchLpas');
    } catch (error) {
      console.error('Failed to load LPA data:', error);
    } finally {
      this.loading = false;
    }
  },
};
</script>
