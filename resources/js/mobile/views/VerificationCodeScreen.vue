<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <div class="text-center mb-8">
      <div class="w-16 h-16 mx-auto mb-3 bg-violet-100 rounded-full flex items-center justify-center">
        <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <h1 class="text-xl font-bold text-horizon-500">Verification code</h1>
      <p class="text-neutral-500 text-sm mt-1">
        We've sent a code to {{ maskedEmail }}
      </p>
    </div>

    <!-- Code Input -->
    <div class="flex justify-center gap-3 mb-6">
      <input
        v-for="(digit, index) in codeDigits"
        :key="index"
        :ref="el => { if (el) digitRefs[index] = el; }"
        v-model="codeDigits[index]"
        type="text"
        inputmode="numeric"
        pattern="[0-9]"
        maxlength="1"
        :disabled="loading"
        class="w-12 h-14 text-center text-xl font-bold rounded-xl border border-light-gray bg-white
               text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500
               disabled:opacity-50"
        @input="handleDigitInput(index)"
        @keydown.backspace="handleBackspace(index)"
        @paste="handlePaste"
      />
    </div>

    <!-- Error -->
    <p v-if="error" class="text-raspberry-500 text-sm text-center mb-4">{{ error }}</p>

    <!-- Submit -->
    <button
      :disabled="loading || code.length < 6"
      class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
             active:bg-raspberry-600 disabled:opacity-50 transition-colors"
      @click="handleVerify"
    >
      <span v-if="loading" class="flex items-center justify-center gap-2">
        <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        Verifying...
      </span>
      <span v-else>Verify</span>
    </button>

    <!-- Resend -->
    <button
      :disabled="resendCooldown > 0"
      class="w-full py-3 mt-3 text-raspberry-500 font-semibold text-sm"
      @click="handleResend"
    >
      {{ resendCooldown > 0 ? `Resend code in ${resendCooldown}s` : 'Resend code' }}
    </button>
  </div>
</template>

<script>
import authService from '@/services/authService';
import { setToken } from '@/services/tokenStorage';

export default {
  name: 'VerificationCodeScreen',

  data() {
    return {
      codeDigits: ['', '', '', '', '', ''],
      digitRefs: [],
      loading: false,
      error: null,
      resendCooldown: 60,
      resendTimer: null,
    };
  },

  computed: {
    code() {
      return this.codeDigits.join('');
    },
    maskedEmail() {
      const email = this.$route.query.email || '';
      if (!email.includes('@')) return email;
      const [local, domain] = email.split('@');
      return local.slice(0, 2) + '***@' + domain;
    },
  },

  mounted() {
    this.startResendTimer();
    this.$nextTick(() => {
      if (this.digitRefs[0]) this.digitRefs[0].focus();
    });
  },

  beforeUnmount() {
    if (this.resendTimer) clearInterval(this.resendTimer);
  },

  methods: {
    handleDigitInput(index) {
      const val = this.codeDigits[index];
      if (val && index < 5) {
        this.$nextTick(() => {
          if (this.digitRefs[index + 1]) this.digitRefs[index + 1].focus();
        });
      }
      if (this.code.length === 6) {
        this.handleVerify();
      }
    },

    handleBackspace(index) {
      if (!this.codeDigits[index] && index > 0) {
        this.$nextTick(() => {
          if (this.digitRefs[index - 1]) this.digitRefs[index - 1].focus();
        });
      }
    },

    handlePaste(event) {
      const pasted = (event.clipboardData || window.clipboardData).getData('text').trim();
      if (/^\d{6}$/.test(pasted)) {
        event.preventDefault();
        pasted.split('').forEach((digit, i) => {
          this.codeDigits[i] = digit;
        });
        this.handleVerify();
      }
    },

    async handleVerify() {
      if (this.code.length < 6) return;
      this.loading = true;
      this.error = null;

      try {
        const challengeToken = this.$route.query.challengeToken;

        // authService.verifyCode() returns response.data (the API body)
        // Shape: { success, data: { access_token, user, ... } }
        const result = await authService.verifyCode(challengeToken, this.code, 'login');

        const token = result.data?.access_token;
        if (token) {
          await setToken(token);
          this.$store.commit('auth/setToken', token);
          await this.$store.dispatch('auth/fetchUser');

          this.$router.push('/m/home');
        }
      } catch (error) {
        this.error = error.message || 'Invalid code. Please try again.';
        this.codeDigits = ['', '', '', '', '', ''];
        this.$nextTick(() => {
          if (this.digitRefs[0]) this.digitRefs[0].focus();
        });
      } finally {
        this.loading = false;
      }
    },

    async handleResend() {
      try {
        const challengeToken = this.$route.query.challengeToken;
        await authService.resendCode(challengeToken, 'login');
        this.resendCooldown = 60;
        this.startResendTimer();
      } catch (error) {
        this.error = 'Failed to resend code. Please try again.';
      }
    },

    startResendTimer() {
      if (this.resendTimer) clearInterval(this.resendTimer);
      this.resendTimer = setInterval(() => {
        if (this.resendCooldown > 0) {
          this.resendCooldown--;
        } else {
          clearInterval(this.resendTimer);
        }
      }, 1000);
    },
  },
};
</script>
