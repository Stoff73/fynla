<template>
  <div class="bg-light-pink-100 rounded-xl px-5 py-4 mb-10">
    <div class="flex items-center gap-3">
      <svg class="w-6 h-6 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a14 14 0 0114 14M5 11a8 8 0 018 8M6.5 18.5a1 1 0 11-2 0 1 1 0 012 0z" />
      </svg>
      <div class="flex-1">
        <p class="text-sm font-semibold text-horizon-500 leading-tight">Subscribe to Fynla news</p>
        <p class="text-xs text-neutral-500 mt-0.5">Get every announcement straight to your inbox.</p>
      </div>
    </div>

    <form v-if="status === 'idle' || status === 'error'" @submit.prevent="handleSubmit" class="mt-3 flex flex-col sm:flex-row gap-2">
      <label for="news-subscribe-email" class="sr-only">Email address</label>
      <input
        id="news-subscribe-email"
        v-model="email"
        type="email"
        required
        autocomplete="email"
        placeholder="your@email.com"
        class="flex-1 px-3 py-2 text-sm rounded-lg border border-light-gray bg-white text-horizon-500 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-raspberry-500"
        :disabled="submitting"
      />
      <button
        type="submit"
        class="px-4 py-2 text-sm font-semibold rounded-lg bg-raspberry-500 text-white hover:bg-raspberry-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
        :disabled="submitting"
      >
        {{ submitting ? 'Sending…' : 'Subscribe' }}
      </button>
    </form>

    <p v-if="status === 'error'" class="mt-2 text-xs text-raspberry-500" role="alert">{{ message }}</p>

    <div v-if="status === 'pending_confirmation'" class="mt-3 text-sm text-horizon-500" role="status">
      <strong>Check your inbox.</strong> {{ message }}
    </div>

    <div v-if="status === 'already_registered'" class="mt-3 text-sm text-horizon-500" role="status">
      You're already registered with Fynla &mdash;
      <router-link to="/login" class="underline font-semibold hover:text-raspberry-500 transition-colors">sign in</router-link>
      to manage your news preferences.
    </div>

    <div v-if="status === 'already_confirmed'" class="mt-3 text-sm text-horizon-500" role="status">
      <strong>You're already subscribed</strong> &mdash; thanks!
    </div>
  </div>
</template>

<script>
import newsSubscriberService from '@/services/newsSubscriberService';

export default {
  name: 'NewsSubscribeBanner',

  data() {
    return {
      email: '',
      status: 'idle',
      message: '',
      submitting: false,
    };
  },

  methods: {
    async handleSubmit() {
      this.submitting = true;
      this.status = 'idle';
      this.message = '';

      try {
        const result = await newsSubscriberService.subscribe(this.email);
        this.status = result.status;
        this.message = result.message || '';
        if (result.status === 'pending_confirmation') {
          this.email = '';
        }
      } catch (err) {
        this.status = 'error';
        if (err.response?.status === 429) {
          this.message = 'Too many attempts. Please try again in a few minutes.';
        } else if (err.response?.status === 422) {
          this.message = 'Please enter a valid email address.';
        } else {
          this.message = 'Could not subscribe right now. Please try again.';
        }
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
