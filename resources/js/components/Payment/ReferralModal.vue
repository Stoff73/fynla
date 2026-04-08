<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
  >
    <div class="flex items-center justify-center min-h-screen px-4">
      <div class="fixed inset-0 bg-savannah-1000/75 transition-opacity" @click="$emit('close')"></div>

      <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 z-10">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-h4 font-semibold text-horizon-500">Refer a Friend</h2>
          <button @click="$emit('close')" class="text-neutral-500 hover:text-horizon-500 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <p class="text-body-sm text-neutral-500 mb-4">
          Invite a friend to Fynla. When they subscribe, you'll both get bonus time — an extra week with a monthly plan, or an extra month with an annual plan.
        </p>

        <!-- Referral Code -->
        <div v-if="code" class="bg-savannah-100 rounded-lg p-4 mb-4 text-center">
          <p class="text-caption text-neutral-500 mb-1">Your referral code</p>
          <p class="text-h4 font-bold text-horizon-500 font-mono tracking-wider">{{ code }}</p>
        </div>

        <!-- Email Input -->
        <div class="mb-4">
          <label class="text-body-sm font-medium text-horizon-500 mb-1 block">Friend's email address</label>
          <div class="flex gap-2">
            <input
              v-model="email"
              type="email"
              placeholder="friend@example.com"
              class="flex-1 px-3 py-2 border border-light-gray rounded-lg text-body-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              @keyup.enter="sendInvitation"
              :disabled="sending"
            />
            <button
              @click="sendInvitation"
              :disabled="!email.trim() || sending"
              class="px-4 py-2 bg-raspberry-500 text-white text-body-sm font-medium rounded-lg hover:bg-raspberry-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ sending ? 'Sending...' : 'Send' }}
            </button>
          </div>
          <p v-if="error" class="text-caption text-raspberry-600 mt-1">{{ error }}</p>
          <p v-if="success" class="text-caption text-spring-600 mt-1">{{ success }}</p>
        </div>

        <!-- Referral History -->
        <div v-if="referrals.length > 0" class="border-t border-light-gray pt-4">
          <p class="text-body-sm font-medium text-horizon-500 mb-2">Your referrals</p>
          <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-thin">
            <div
              v-for="r in referrals"
              :key="r.id"
              class="flex items-center justify-between text-body-sm"
            >
              <span class="text-neutral-500 truncate mr-2">{{ r.email }}</span>
              <span
                class="text-caption font-medium px-2 py-0.5 rounded-full"
                :class="statusClass(r.status)"
              >
                {{ statusLabel(r.status) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'ReferralModal',

  props: {
    show: { type: Boolean, default: false },
  },

  emits: ['close'],

  data() {
    return {
      code: null,
      email: '',
      sending: false,
      error: null,
      success: null,
      referrals: [],
    };
  },

  watch: {
    show(val) {
      if (val) {
        this.fetchCode();
        this.fetchReferrals();
        this.error = null;
        this.success = null;
        this.email = '';
      }
    },
  },

  methods: {
    async fetchCode() {
      try {
        const response = await api.get('/referral/code');
        if (response.data.success) {
          this.code = response.data.data.code;
        }
      } catch {
        // Non-critical
      }
    },

    async fetchReferrals() {
      try {
        const response = await api.get('/referral/list');
        if (response.data.success) {
          this.referrals = response.data.data.referrals;
        }
      } catch {
        // Non-critical
      }
    },

    async sendInvitation() {
      const emailVal = this.email.trim();
      if (!emailVal) return;

      this.sending = true;
      this.error = null;
      this.success = null;

      try {
        const response = await api.post('/referral/invite', { email: emailVal });
        if (response.data.success) {
          this.success = 'Invitation sent successfully.';
          this.email = '';
          this.fetchReferrals();
        } else {
          this.error = response.data.message;
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to send invitation.';
      } finally {
        this.sending = false;
      }
    },

    statusClass(status) {
      return {
        pending: 'bg-savannah-100 text-neutral-500',
        registered: 'bg-violet-100 text-violet-700',
        converted: 'bg-spring-100 text-spring-700',
        expired: 'bg-neutral-100 text-neutral-500',
      }[status] || 'bg-neutral-100 text-neutral-500';
    },

    statusLabel(status) {
      return {
        pending: 'Pending',
        registered: 'Registered',
        converted: 'Subscribed',
        expired: 'Expired',
      }[status] || status;
    },
  },
};
</script>
