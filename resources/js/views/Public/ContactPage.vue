<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Get in <span class="text-raspberry-300">touch</span>
        </h1>
        <p class="text-lg text-white/70">
          Questions, feedback, or just want to say hello? We would love to hear from you.
        </p>
      </div>
    </div>

    <!-- Contact form -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-center">
        <div class="w-full max-w-2xl">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-6 text-center">Send us a message</h2>

          <form @submit.prevent="handleSubmit" class="space-y-5">
            <!-- Name -->
            <div>
              <label for="contact-name" class="block text-sm font-medium text-horizon-500 mb-1">Name <span class="text-raspberry-500">*</span></label>
              <input
                id="contact-name"
                v-model="form.name"
                type="text"
                required
                class="w-full px-4 py-2.5 rounded-lg border-[3px] border-light-blue-100 text-sm text-horizon-500 focus:outline-none focus:border-horizon-500 focus:ring-1 focus:ring-horizon-500"
                placeholder="Your name"
              />
              <p v-if="errors.name" class="text-xs text-raspberry-500 mt-1">{{ errors.name }}</p>
            </div>

            <!-- Email -->
            <div>
              <label for="contact-email" class="block text-sm font-medium text-horizon-500 mb-1">Email <span class="text-raspberry-500">*</span></label>
              <input
                id="contact-email"
                v-model="form.email"
                type="email"
                required
                class="w-full px-4 py-2.5 rounded-lg border-[3px] border-light-blue-100 text-sm text-horizon-500 focus:outline-none focus:border-horizon-500 focus:ring-1 focus:ring-horizon-500"
                placeholder="your@email.com"
              />
              <p v-if="errors.email" class="text-xs text-raspberry-500 mt-1">{{ errors.email }}</p>
            </div>

            <!-- Reason -->
            <div>
              <label for="contact-reason" class="block text-sm font-medium text-horizon-500 mb-1">Reason for contact <span class="text-raspberry-500">*</span></label>
              <select
                id="contact-reason"
                v-model="form.reason"
                required
                class="w-full px-4 py-2.5 rounded-lg border-[3px] border-light-blue-100 text-sm text-horizon-500 focus:outline-none focus:border-horizon-500 focus:ring-1 focus:ring-horizon-500 bg-white"
              >
                <option value="" disabled>Select a reason</option>
                <option value="general">General enquiry</option>
                <option value="support">Technical support</option>
                <option value="press">Press and media</option>
              </select>
              <p v-if="errors.reason" class="text-xs text-raspberry-500 mt-1">{{ errors.reason }}</p>
            </div>

            <!-- Message -->
            <div>
              <label for="contact-message" class="block text-sm font-medium text-horizon-500 mb-1">Message <span class="text-raspberry-500">*</span></label>
              <textarea
                id="contact-message"
                v-model="form.message"
                required
                rows="5"
                class="w-full px-4 py-2.5 rounded-lg border-[3px] border-light-blue-100 text-sm text-horizon-500 focus:outline-none focus:border-horizon-500 focus:ring-1 focus:ring-horizon-500 resize-y"
                placeholder="How can we help?"
              ></textarea>
              <p v-if="errors.message" class="text-xs text-raspberry-500 mt-1">{{ errors.message }}</p>
            </div>

            <!-- Captcha -->
            <div>
              <label class="block text-sm font-medium text-horizon-500 mb-1">Verify you're human <span class="text-raspberry-500">*</span></label>
              <div class="flex items-center gap-3">
                <span class="text-sm text-horizon-500 font-medium">{{ captchaA }} + {{ captchaB }} =</span>
                <input
                  v-model="captchaAnswer"
                  type="number"
                  required
                  class="w-24 px-4 py-2.5 rounded-lg border-[3px] border-light-blue-100 text-sm text-horizon-500 focus:outline-none focus:border-horizon-500 focus:ring-1 focus:ring-horizon-500"
                  placeholder="?"
                />
              </div>
              <p v-if="errors.captcha" class="text-xs text-raspberry-500 mt-1">{{ errors.captcha }}</p>
            </div>

            <!-- Submit -->
            <div>
              <button
                type="submit"
                :disabled="submitting"
                class="px-8 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors disabled:opacity-50"
              >
                {{ submitting ? 'Sending...' : 'Send message' }}
              </button>
            </div>

            <!-- Success / Error messages -->
            <p v-if="successMessage" class="text-sm text-spring-600 font-medium">{{ successMessage }}</p>
            <p v-if="errorMessage" class="text-sm text-raspberry-500 font-medium">{{ errorMessage }}</p>
          </form>
        </div>
      </div>
    </section>

    <!-- FAQ & Demo -->
    <section class="py-10 bg-horizon-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-light-pink-100 rounded-xl p-6">
            <h2 class="text-lg sm:text-xl font-bold text-horizon-500 mb-2">Frequently asked questions</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-4">
              Many common questions are answered in our FAQ. It covers pricing, data security, supported features, and more.
            </p>
            <router-link to="/faq" class="inline-block px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
              View FAQ
            </router-link>
          </div>
          <div class="bg-light-pink-100 rounded-xl p-6">
            <h2 class="text-lg sm:text-xl font-bold text-horizon-500 mb-2">Try the demo</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-4">
              Want to see Fynla in action before getting in touch? Our interactive demo lets you explore the full platform
              with sample data &mdash; no sign-up required.
            </p>
            <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-block px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
              Launch demo
            </a>
          </div>
          <div class="bg-light-pink-100 rounded-xl p-6">
            <h2 class="text-lg sm:text-xl font-bold text-horizon-500 mb-2">Ask Fyn</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-4">
              Don't want to wait for a human? You can always ask Fyn! Our AI assistant answers financial planning questions instantly.
            </p>
            <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-block px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
              Ask Fyn
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Email boxes -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Contact us the traditional way</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-horizon-500 rounded-xl p-6 text-center">
            <p class="text-lg sm:text-xl font-bold text-white mb-2">General enquiries</p>
            <a href="mailto:hello@fynla.org" class="text-sm text-raspberry-300 hover:text-light-pink-200 transition-colors">hello@fynla.org</a>
            <p class="text-xs text-white/60 mt-2">Questions about Fynla, partnerships, or anything else.</p>
          </div>
          <div class="bg-horizon-500 rounded-xl p-6 text-center">
            <p class="text-lg sm:text-xl font-bold text-white mb-2">Technical support</p>
            <a href="mailto:support@fynla.org" class="text-sm text-raspberry-300 hover:text-light-pink-200 transition-colors">support@fynla.org</a>
            <p class="text-xs text-white/60 mt-2">Help with your account, data, or technical issues.</p>
          </div>
          <div class="bg-horizon-500 rounded-xl p-6 text-center">
            <p class="text-lg sm:text-xl font-bold text-white mb-2">Marketing and media</p>
            <a href="mailto:marketing@fynla.org" class="text-sm text-raspberry-300 hover:text-light-pink-200 transition-colors">marketing@fynla.org</a>
            <p class="text-xs text-white/60 mt-2">Partnerships, press enquiries, and media resources.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-2">Just want to start planning?</h2>
        <p class="text-sm text-neutral-500 mb-6">Create your free account and see where you stand financially.</p>
        <router-link to="/register" class="inline-block px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
          Get started
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import api from '@/services/api';

export default {
  name: 'ContactPage',
  components: { PublicLayout },

  data() {
    return {
      form: {
        name: '',
        email: '',
        reason: '',
        message: '',
      },
      captchaA: 0,
      captchaB: 0,
      captchaAnswer: '',
      errors: {},
      submitting: false,
      successMessage: '',
      errorMessage: '',
    };
  },

  created() {
    this.generateCaptcha();
  },

  methods: {
    generateCaptcha() {
      this.captchaA = Math.floor(Math.random() * 10) + 1;
      this.captchaB = Math.floor(Math.random() * 10) + 1;
      this.captchaAnswer = '';
    },

    async handleSubmit() {
      this.errors = {};
      this.successMessage = '';
      this.errorMessage = '';

      if (!this.form.name.trim()) {
        this.errors.name = 'Name is required.';
      }
      if (!this.form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email)) {
        this.errors.email = 'Please enter a valid email address.';
      }
      if (!this.form.reason) {
        this.errors.reason = 'Please select a reason for contact.';
      }
      if (!this.form.message.trim()) {
        this.errors.message = 'Message is required.';
      }
      if (parseInt(this.captchaAnswer) !== this.captchaA + this.captchaB) {
        this.errors.captcha = 'Incorrect answer. Please try again.';
      }

      if (Object.keys(this.errors).length > 0) return;

      this.submitting = true;

      try {
        const response = await api.post('/contact', this.form);
        this.successMessage = response.data.message || 'Your message has been sent. We\'ll get back to you soon.';
        this.form = { name: '', email: '', reason: '', message: '' };
        this.generateCaptcha();
      } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
          this.errors = {};
          for (const [field, messages] of Object.entries(error.response.data.errors)) {
            this.errors[field] = messages[0];
          }
        } else if (error.response?.status === 429) {
          this.errorMessage = 'Too many submissions. Please try again later.';
        } else {
          this.errorMessage = 'Something went wrong. Please try again or email us directly.';
        }
      } finally {
        this.submitting = false;
      }
    },
  },

  mounted() {
    document.title = 'Contact & support | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Get in touch with the Fynla team. Questions about your account, technical support, or general enquiries.');
    }
  },
};
</script>
