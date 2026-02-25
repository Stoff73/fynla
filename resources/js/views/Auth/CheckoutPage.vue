<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Complete Your Subscription</h1>

      <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <div class="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Coming Soon</h2>
        <p class="text-gray-500 mb-4">
          We're currently setting up our payment system. You'll be able to upgrade your plan here shortly.
        </p>
        <p class="text-sm text-gray-400">
          Your trial will continue as normal. We'll notify you when payments are live.
        </p>
        <router-link
          to="/dashboard"
          class="inline-flex items-center mt-6 px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
        >
          &larr; Back to Dashboard
        </router-link>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'CheckoutPage',

  components: {
    AppLayout,
  },
};
</script>
