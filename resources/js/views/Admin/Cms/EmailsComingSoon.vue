<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <AdminNav />
      <CmsSubNav />

      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Emails</h1>
        <p class="text-sm text-neutral-500 mt-1">Manage transactional and marketing email templates.</p>
      </header>

      <div class="card flex flex-col items-center justify-center text-center py-20">
        <div class="w-14 h-14 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
          <svg class="w-7 h-7 text-horizon-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-horizon-500 mb-1">Coming soon</h2>
        <p class="text-sm text-neutral-500 max-w-md">
          Email template management will live here — edit the welcome, verification, and
          lifecycle emails without touching code.
        </p>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNav from '@/components/Admin/AdminNav.vue';
import CmsSubNav from '@/components/Admin/CmsSubNav.vue';

export default {
  name: 'EmailsComingSoon',
  components: { AppLayout, AdminNav, CmsSubNav },
};
</script>
