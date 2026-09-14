<template>
  <div class="ml-login">
    <div class="ml-login__brand">
      <img :src="logoUrl" alt="Fynla" class="ml-login__logo-img" />
    </div>

    <div class="ml-card">
      <!-- Step 1: email + password -->
      <form v-if="step === 'credentials'" class="ml-form" @submit.prevent="submitCredentials">
        <h1 class="ml-card__title">Sign in</h1>
        <p class="ml-card__sub">Welcome back. Sign in to your Fynla account.</p>

        <label class="ml-field">
          <span class="ml-field__label">Email address</span>
          <input v-model="email" type="email" inputmode="email" autocomplete="email" class="ml-field__input" placeholder="you@example.com" required />
        </label>

        <label class="ml-field">
          <span class="ml-field__label">Password</span>
          <input v-model="password" type="password" autocomplete="current-password" class="ml-field__input" placeholder="••••••••" required />
        </label>

        <p v-if="error" class="ml-error">{{ error }}</p>

        <button type="submit" class="ml-btn" :disabled="loading">{{ loading ? 'Signing in…' : 'Sign in' }}</button>

        <p class="ml-foot">New to Fynla? <a :href="registerUrl" class="ml-link">Create an account</a></p>
      </form>

      <!-- Step 2: emailed verification code -->
      <form v-else-if="step === 'verify'" class="ml-form" @submit.prevent="submitCode">
        <h1 class="ml-card__title">Enter verification code</h1>

        <!-- Verification message -->
        <div class="ml-verify-msg" role="status">
          <span class="ml-verify-msg__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
          </span>
          <p class="ml-verify-msg__text">We sent a 6-digit code to <strong>{{ maskedEmail }}</strong>. Enter it below to continue.</p>
        </div>

        <div class="ml-code" role="group" aria-label="Verification code">
          <input
            v-for="(d, i) in 6"
            :key="i"
            ref="codeInputs"
            v-model="digits[i]"
            type="text"
            inputmode="numeric"
            maxlength="1"
            class="ml-code__box"
            :aria-label="`Digit ${i + 1}`"
            @input="onDigit(i, $event)"
            @keydown.delete="onDelete(i, $event)"
            @paste="onPaste($event)"
          />
        </div>

        <p v-if="error" class="ml-error">{{ error }}</p>

        <button type="submit" class="ml-btn" :disabled="loading || code.length !== 6">{{ loading ? 'Verifying…' : 'Verify and continue' }}</button>

        <div class="ml-verify-actions">
          <button type="button" class="ml-link ml-link--btn" :disabled="resending" @click="resend">{{ resending ? 'Sending…' : 'Resend code' }}</button>
          <button type="button" class="ml-link ml-link--btn" @click="backToCredentials">Use a different account</button>
        </div>
        <p class="ml-foot">Didn't receive the email? Check your spam folder.</p>
      </form>

      <!-- Step 2 (two-factor account): authenticator or recovery code (MB-18) -->
      <form v-else-if="step === 'mfa'" class="ml-form" @submit.prevent="submitMfa">
        <template v-if="!useRecovery">
          <h1 class="ml-card__title">Enter your authenticator code</h1>
          <p class="ml-card__sub">Open your authenticator app and enter the 6-digit code for Fynla.</p>

          <div class="ml-code" role="group" aria-label="Authenticator code">
            <input
              v-for="(d, i) in 6"
              :key="i"
              ref="codeInputs"
              v-model="digits[i]"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="ml-code__box"
              :aria-label="`Digit ${i + 1}`"
              @input="onDigit(i, $event)"
              @keydown.delete="onDelete(i, $event)"
              @paste="onPaste($event)"
            />
          </div>
        </template>
        <template v-else>
          <h1 class="ml-card__title">Enter a recovery code</h1>
          <p class="ml-card__sub">Use one of the recovery codes you saved when you set up two-factor authentication. Each code works once.</p>
          <label class="ml-field">
            <span class="ml-field__label">Recovery code</span>
            <input v-model="recoveryCode" type="text" inputmode="text" autocomplete="one-time-code" autocapitalize="characters" class="ml-field__input" placeholder="XXXX-XXXX-XXXX" />
          </label>
        </template>

        <p v-if="error" class="ml-error">{{ error }}</p>

        <button type="submit" class="ml-btn" :disabled="loading || !mfaReady">{{ loading ? 'Verifying…' : 'Verify and continue' }}</button>

        <div class="ml-verify-actions">
          <button type="button" class="ml-link ml-link--btn" @click="toggleRecovery">{{ useRecovery ? 'Use my authenticator app instead' : 'Lost access to your authenticator? Use a recovery code' }}</button>
          <button type="button" class="ml-link ml-link--btn" @click="backToCredentials">Use a different account</button>
        </div>
      </form>

      <!-- Step 2 (deleted account): restore (MB-19) -->
      <form v-else class="ml-form" @submit.prevent="submitRestore">
        <h1 class="ml-card__title">Welcome back, {{ restore.firstName || 'there' }}</h1>
        <p class="ml-card__sub">
          <template v-if="restore.deletedDate">This account was deleted on <strong>{{ restore.deletedDate }}</strong>. </template>
          <template v-else>This account was deleted recently. </template>
          You can restore it and carry on where you left off.
        </p>

        <label v-if="restore.mfaRequired" class="ml-field">
          <span class="ml-field__label">Enter your authenticator or recovery code</span>
          <input v-model="restore.mfaCode" type="text" inputmode="text" autocomplete="one-time-code" class="ml-field__input" />
        </label>

        <p v-if="error" class="ml-error">{{ error }}</p>

        <button type="submit" class="ml-btn" :disabled="loading || (restore.mfaRequired && !restore.mfaCode.trim())">{{ loading ? 'Restoring…' : 'Restore my account' }}</button>

        <div class="ml-verify-actions">
          <button type="button" class="ml-link ml-link--btn" @click="backToCredentials">Use a different account</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { apiPost } from '../api.js';
import { store } from '../store.js';

export default {
  name: 'MobileLogin',
  data() {
    return {
      step: 'credentials',
      email: '',
      password: '',
      digits: ['', '', '', '', '', ''],
      challengeToken: null,
      maskedEmail: '',
      // Two-factor challenge (single-use on the server: a wrong code needs a
      // fresh sign-in, see submitMfa).
      mfaToken: null,
      useRecovery: false,
      recoveryCode: '',
      // Deleted-but-restorable account (POST /api/auth/login answers
      // account_deleted_restorable with a restoration token).
      restore: { token: null, firstName: '', deletedDate: '', mfaRequired: false, mfaCode: '' },
      loading: false,
      resending: false,
      error: '',
    };
  },
  computed: {
    code() {
      return this.digits.join('').trim();
    },
    mfaReady() {
      return this.useRecovery ? this.recoveryCode.trim().length > 0 : this.code.length === 6;
    },
    registerUrl() {
      return (import.meta.env.VITE_ROUTER_BASE || '/') + 'register';
    },
    logoUrl() {
      return (import.meta.env.VITE_ROUTER_BASE || '/') + 'images/logos/LogoHiResFynlaLight.png';
    },
  },
  methods: {
    enterApp(data) {
      const token = data?.access_token || data?.token;
      if (!token) { this.error = 'We could not sign you in. Please try again.'; return; }
      store.setToken(token);
      store.user = data.user?.data || data.user || null;
      this.$router.push('/dashboard');
    },
    async submitCredentials() {
      if (this.loading) return;
      this.loading = true;
      this.error = '';
      try {
        const res = await apiPost('/api/auth/login', { email: this.email.trim(), password: this.password });
        const d = res.data || {};
        if (d.data && (d.data.access_token || d.data.token)) {
          this.enterApp(d.data);           // preview / no-verification users
        } else if (d.account_deleted_restorable) {
          // MB-19 — the same branch web (RestoreAccountModal) and native have.
          this.restore = {
            token: d.restoration_token || null,
            firstName: d.first_name || '',
            deletedDate: d.deleted_at ? new Date(d.deleted_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : '',
            mfaRequired: false,
            mfaCode: '',
          };
          this.step = 'restore';
        } else if (d.requires_mfa) {
          // MB-18 — two-factor account: authenticator code, or a recovery code.
          this.mfaToken = d.data?.mfa_token || null;
          this.maskedEmail = d.data?.email || this.maskEmail(this.email);
          this.useRecovery = false;
          this.recoveryCode = '';
          this.step = 'mfa';
          this.$nextTick(() => { const f = this.$refs.codeInputs; if (f && f[0]) f[0].focus(); });
        } else if (d.requires_verification) {
          this.challengeToken = d.data?.challenge_token || null;
          this.maskedEmail = d.data?.email || this.maskEmail(this.email);
          this.step = 'verify';
          this.$nextTick(() => { const f = this.$refs.codeInputs; if (f && f[0]) f[0].focus(); });
        } else if (res.status === 401) {
          this.error = 'Invalid email or password.';
        } else {
          this.error = d.message || 'We could not sign you in. Please try again.';
        }
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async submitCode() {
      if (this.loading || this.code.length !== 6) return;
      this.loading = true;
      this.error = '';
      try {
        const res = await apiPost('/api/auth/verify-code', {
          email: this.email.trim(),
          code: this.code,
          challenge_token: this.challengeToken,
          type: 'login',
        });
        const d = res.data || {};
        if (d.data && (d.data.access_token || d.data.token)) {
          this.enterApp(d.data);
        } else {
          this.error = d.message || 'That code was not valid. Please try again.';
        }
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async submitMfa() {
      if (this.loading || !this.mfaReady) return;
      this.loading = true;
      this.error = '';
      try {
        const res = this.useRecovery
          ? await apiPost('/api/auth/mfa/recovery', { recovery_code: this.recoveryCode.trim(), mfa_token: this.mfaToken })
          : await apiPost('/api/auth/mfa/verify', { code: this.code, mfa_token: this.mfaToken });
        const d = res.data || {};
        if (d.data && (d.data.access_token || d.data.token)) {
          this.enterApp(d.data);
          return;
        }
        // The challenge token is consumed by the first attempt whether or
        // not the code was right (MFAController::validateChallengeToken), so
        // a wrong code cannot simply be retried: sign in again for a new one.
        const message = d.message || 'That code was not valid.';
        this.backToCredentials();
        this.error = `${message} Please sign in again to get a new code.`;
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    toggleRecovery() {
      this.useRecovery = !this.useRecovery;
      this.error = '';
      this.digits = ['', '', '', '', '', ''];
      this.recoveryCode = '';
    },
    async submitRestore() {
      if (this.loading || !this.restore.token) return;
      if (this.restore.mfaRequired && !this.restore.mfaCode.trim()) return;
      this.loading = true;
      this.error = '';
      try {
        const body = { restoration_token: this.restore.token };
        if (this.restore.mfaRequired) body.mfa_code = this.restore.mfaCode.trim();
        const res = await apiPost('/api/auth/restore', body);
        const d = res.data || {};
        if (res.ok && d.token) {
          store.setToken(d.token);
          store.user = d.user || null;
          // redirect_to is a web path; carry only the campaign it names so the
          // /m dashboard re-enters the walk the same way (?from=<campaign>).
          const from = (String(d.redirect_to || '').match(/[?&]from=([^&]+)/) || [])[1];
          this.$router.push(from ? { path: '/dashboard', query: { from } } : '/dashboard');
          return;
        }
        if (res.status === 422 && d.requires_mfa) {
          // A two-factor account: the restore call is the first to say a
          // code is needed (the same gap web had, MB-20).
          this.restore.mfaRequired = true;
          return;
        }
        this.error = d.message || 'We could not restore your account. Please try again.';
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async resend() {
      if (this.resending) return;
      this.resending = true;
      this.error = '';
      try {
        await apiPost('/api/auth/login', { email: this.email.trim(), password: this.password });
      } catch { /* best-effort */ }
      this.resending = false;
    },
    backToCredentials() {
      this.step = 'credentials';
      this.digits = ['', '', '', '', '', ''];
      this.mfaToken = null;
      this.useRecovery = false;
      this.recoveryCode = '';
      this.restore = { token: null, firstName: '', deletedDate: '', mfaRequired: false, mfaCode: '' };
      this.error = '';
    },
    onDigit(i, e) {
      const v = (e.target.value || '').replace(/\D/g, '');
      this.digits[i] = v.slice(-1);
      const f = this.$refs.codeInputs;
      if (this.digits[i] && f && f[i + 1]) f[i + 1].focus();
    },
    onDelete(i, e) {
      if (!this.digits[i] && i > 0) {
        const f = this.$refs.codeInputs;
        if (f && f[i - 1]) { f[i - 1].focus(); this.digits[i - 1] = ''; e.preventDefault(); }
      }
    },
    onPaste(e) {
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      if (!text) return;
      e.preventDefault();
      for (let i = 0; i < 6; i++) this.digits[i] = text[i] || '';
      const f = this.$refs.codeInputs;
      const next = Math.min(text.length, 5);
      if (f && f[next]) f[next].focus();
    },
    maskEmail(email) {
      const [u, d] = String(email).split('@');
      if (!d) return email;
      const shown = u.length <= 2 ? u[0] : u[0] + '***' + u[u.length - 1];
      return `${shown}@${d}`;
    },
  },
  mounted() {
    // Already signed in → straight to the dashboard.
    if (store.token) this.$router.replace('/dashboard');
  },
};
</script>

<style scoped>
.ml-login {
  min-height: 100vh; min-height: 100svh;
  background: linear-gradient(160deg, var(--horizon-500) 0%, var(--horizon-500) 55%, var(--raspberry-500) 130%);
  display: flex; flex-direction: column; align-items: center;
  padding: 2.5rem 1.25rem calc(2rem + env(safe-area-inset-bottom, 0px));
  font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}
.ml-login__brand { margin: 1.5rem 0 2rem; }
.ml-login__logo-img { height: 2.5rem; width: auto; display: block; }

.ml-card {
  width: 100%; max-width: 26rem; background: var(--white);
  border-radius: 1.25rem; padding: 1.75rem 1.5rem 1.5rem;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.32);
}
.ml-form { display: flex; flex-direction: column; gap: 1rem; }
.ml-card__title { font-size: 1.5rem; font-weight: 900; color: var(--horizon-600); margin: 0; line-height: 1.15; }
.ml-card__sub { font-size: 0.9375rem; color: var(--neutral-500); margin: -0.5rem 0 0.25rem; line-height: 1.45; }

.ml-field { display: flex; flex-direction: column; gap: 0.375rem; }
.ml-field__label { font-size: 0.8125rem; font-weight: 600; color: var(--horizon-500); }
.ml-field__input {
  width: 100%; min-height: 3rem; padding: 0.75rem 1rem;
  font-size: 1rem; font-family: inherit; color: var(--horizon-600);
  border: 1px solid var(--horizon-200); border-radius: 0.75rem; background: var(--white);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.ml-field__input::placeholder { color: var(--horizon-400); }
.ml-field__input:focus { outline: none; border-color: var(--raspberry-500); box-shadow: 0 0 0 3px rgba(232, 62, 109, 0.18); }

.ml-btn {
  width: 100%; min-height: 3rem; margin-top: 0.25rem;
  background: var(--raspberry-500); color: var(--white); font-size: 1rem; font-weight: 700;
  font-family: inherit; border: none; border-radius: 0.75rem; cursor: pointer;
  transition: background 0.15s ease, box-shadow 0.15s ease;
}
.ml-btn:hover:not(:disabled) { background: var(--raspberry-600); box-shadow: 0 8px 20px rgba(232, 62, 109, 0.28); }
.ml-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.ml-error {
  font-size: 0.875rem; color: var(--raspberry-600); margin: 0;
  background: var(--raspberry-50); border: 1px solid var(--light-pink-200); border-radius: 0.625rem; padding: 0.625rem 0.75rem;
}

.ml-foot { font-size: 0.8125rem; color: var(--neutral-500); margin: 0.5rem 0 0; text-align: center; }
.ml-link { color: var(--raspberry-500); font-weight: 600; text-decoration: none; }
.ml-link:hover { text-decoration: underline; }
.ml-link--btn { background: none; border: none; font-family: inherit; font-size: 0.8125rem; cursor: pointer; padding: 0; }
.ml-link--btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Verification message — styled info banner */
.ml-verify-msg {
  display: flex; align-items: flex-start; gap: 0.75rem;
  background: var(--raspberry-50); border: 1px solid var(--light-pink-100); border-radius: 0.875rem; padding: 0.875rem 1rem;
}
.ml-verify-msg__icon {
  flex-shrink: 0; width: 2.25rem; height: 2.25rem; border-radius: 9999px;
  background: var(--white); color: var(--raspberry-500); display: flex; align-items: center; justify-content: center;
}
.ml-verify-msg__text { font-size: 0.875rem; color: var(--neutral-600); margin: 0; line-height: 1.45; }
.ml-verify-msg__text strong { color: var(--horizon-600); font-weight: 700; }

/* Code boxes */
.ml-code { display: flex; gap: 0.5rem; justify-content: space-between; }
.ml-code__box {
  flex: 1; min-width: 0; aspect-ratio: 1 / 1; max-width: 3.25rem;
  text-align: center; font-size: 1.5rem; font-weight: 800; color: var(--horizon-600);
  border: 1px solid var(--horizon-200); border-radius: 0.75rem; background: var(--eggshell-500);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.ml-code__box:focus { outline: none; border-color: var(--raspberry-500); background: var(--white); box-shadow: 0 0 0 3px rgba(232, 62, 109, 0.18); }

.ml-verify-actions { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.25rem; }
</style>
