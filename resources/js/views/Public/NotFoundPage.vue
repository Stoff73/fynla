<template>
  <PublicLayout>
    <div class="bg-eggshell-500 min-h-[70vh] flex flex-col items-center justify-center px-4 py-16 text-center">
      <h1 class="text-4xl md:text-6xl font-black text-horizon-500 mb-4">Oh no, we messed up!</h1>
      <p class="text-base text-neutral-500 mb-2">Sorry but we've misplaced the page you're looking for!</p>
      <p class="text-lg text-neutral-500 mb-10">
        <router-link to="/" class="text-raspberry-500 hover:text-raspberry-600 font-semibold">Go back to our homepage</router-link>
      </p>
      <img
        src="/images/Fyn/Design Character 002.png"
        alt="Fyn looking sorry"
        class="h-64 md:h-80 w-auto"
        loading="lazy"
      />
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'NotFoundPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Page Not Found \u2014 Fynla';
  },
};
</script>
