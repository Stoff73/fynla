<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-xl font-black text-white mb-2">Get in Touch</h1>
        <p class="text-sm text-white/80 max-w-xl mx-auto">
          Questions, feedback, or just want to say hello? We would love to hear from you.
        </p>
      </div>
    </section>

    <!-- Contact Cards -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white rounded-lg border border-light-gray p-5 text-center">
            <p class="text-sm font-semibold text-horizon-500 mb-2">General Enquiries</p>
            <a href="mailto:hello@fynla.org" class="text-sm text-raspberry-500 hover:text-raspberry-600 underline">hello@fynla.org</a>
            <p class="text-xs text-neutral-400 mt-2">Questions about Fynla, partnerships, or anything else.</p>
          </div>
          <div class="bg-white rounded-lg border border-light-gray p-5 text-center">
            <p class="text-sm font-semibold text-horizon-500 mb-2">Technical Support</p>
            <a href="mailto:support@fynla.org" class="text-sm text-raspberry-500 hover:text-raspberry-600 underline">support@fynla.org</a>
            <p class="text-xs text-neutral-400 mt-2">Help with your account, data, or technical issues.</p>
          </div>
          <div class="bg-white rounded-lg border border-light-gray p-5 text-center">
            <p class="text-sm font-semibold text-horizon-500 mb-2">Press and Media</p>
            <a href="mailto:press@fynla.org" class="text-sm text-raspberry-500 hover:text-raspberry-600 underline">press@fynla.org</a>
            <p class="text-xs text-neutral-400 mt-2">Media enquiries, interviews, and press resources.</p>
          </div>
        </div>

        <!-- FAQ Link -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">Frequently Asked Questions</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Many common questions are answered in our FAQ. It covers pricing, data security, supported features, and more.
          </p>
          <router-link to="/faq" class="text-sm text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">View FAQ</router-link>
        </div>

        <!-- Try the Demo -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">Try the Demo</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Want to see Fynla in action before getting in touch? Our interactive demo lets you explore the full platform
            with sample data &mdash; no sign-up required.
          </p>
          <a href="/?demo=true" class="text-sm text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">Launch demo</a>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-white mb-2">Ready to start planning?</h2>
        <p class="text-sm text-white/80 mb-6">Create your free account and see where you stand financially.</p>
        <router-link to="/register" class="inline-block px-6 py-2.5 bg-white text-horizon-600 text-sm font-semibold rounded-lg hover:bg-eggshell-500 transition-colors">
          Get started
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'ContactPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Contact & Support | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Get in touch with the Fynla team. Questions about your account, technical support, or general enquiries.');
    }
  },
};
</script>
