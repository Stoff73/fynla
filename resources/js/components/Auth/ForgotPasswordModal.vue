<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"></div>

    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
      <div
        class="relative w-full max-w-md transform rounded-2xl bg-white p-6 shadow-2xl transition-all"
        @click.stop
      >
        <!-- Close button -->
        <button
          @click="handleClose"
          class="absolute top-4 right-4 text-horizon-400 hover:text-neutral-500 transition-colors"
          title="Close"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Step 1: Email Entry -->
        <div v-if="step === 'email'">
          <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-raspberry-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-horizon-500">Reset Your Password</h3>
            <p class="mt-2 text-sm text-neutral-500">
              Enter your email address and we'll send you a verification code.
            </p>
          </div>

          <form @submit.prevent="requestReset">
            <div class="mb-4">
              <label for="reset-email" class="block text-sm font-medium text-horizon-500 mb-1">
                Email address
              </label>
              <input
                id="reset-email"
                v-model="email"
                type="email"
                required
                class="w-full px-4 py-3 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none transition-all"
                placeholder="you@example.com"
                :disabled="loading"
                ref="emailInput"
              >
            </div>

            <div v-if="error" class="mb-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
              <p class="text-sm text-raspberry-500 text-center">{{ error }}</p>
            </div>

            <button
              type="submit"
              :disabled="!email || loading"
              class="w-full py-3 px-4 bg-raspberry-500 text-white font-medium rounded-lg hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <span v-if="loading">Sending...</span>
              <span v-else>Send Verification Code</span>
            </button>
          </form>
        </div>

        <!-- Step 2: Email Code Verification -->
        <div v-else-if="step === 'verify-email'">
          <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-raspberry-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-horizon-500">Enter Verification Code</h3>
            <p class="mt-2 text-sm text-neutral-500">
              We sent a code to <span class="font-medium">{{ email }}</span>
            </p>
          </div>

          <!-- 6-digit Code Input -->
          <div class="flex justify-center gap-2 mb-6">
            <input
              v-for="(digit, index) in 6"
              :key="index"
              :ref="el => { if (el) codeInputRefs[index] = el }"
              type="text"
              maxlength="1"
              inputmode="numeric"
              pattern="[0-9]*"
              class="w-12 h-14 text-center text-2xl font-bold border-2 border-horizon-300 rounded-lg focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none transition-all"
              :class="{
                'border-raspberry-500 bg-raspberry-50': error,
                'border-violet-500': codeDigits[index] && !error
              }"
              :value="codeDigits[index]"
              @input="handleCodeInput($event, index)"
              @keydown="handleCodeKeydown($event, index)"
              @paste="handleCodePaste"
              :disabled="loading"
            />
          </div>

          <div v-if="error" class="mb-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
            <p class="text-sm text-raspberry-500 text-center">{{ error }}</p>
          </div>

          <!-- Resend Section -->
          <div class="text-center mb-4">
            <button
              @click="handleResendCode"
              :disabled="resending || remainingResends === 0"
              class="text-sm text-raspberry-500 hover:text-raspberry-700 font-medium disabled:text-horizon-400 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="resending">Sending...</span>
              <span v-else-if="remainingResends === 0">No resends remaining</span>
              <span v-else>Resend Code ({{ remainingResends }} left)</span>
            </button>
          </div>

          <div class="text-center">
            <button
              @click="goToStep('email')"
              class="text-sm text-neutral-500 hover:text-horizon-500"
            >
              Use a different email
            </button>
          </div>
        </div>

        <!-- Step 3: MFA Verification (if required) -->
        <div v-else-if="step === 'verify-mfa'">
          <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-raspberry-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-horizon-500">Two-Factor Authentication</h3>
            <p class="mt-2 text-sm text-neutral-500">
              Enter the 6-digit code from your authenticator app.
            </p>
          </div>

          <!-- MFA Code Input (not using recovery) -->
          <div v-if="!showRecoveryInput">
            <div class="flex justify-center gap-2 mb-6">
              <input
                v-for="(digit, index) in 6"
                :key="'mfa-' + index"
                :ref="el => { if (el) mfaInputRefs[index] = el }"
                type="text"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]*"
                class="w-12 h-14 text-center text-2xl font-bold border-2 border-horizon-300 rounded-lg focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none transition-all"
                :class="{
                  'border-raspberry-500 bg-raspberry-50': error,
                  'border-violet-500': mfaDigits[index] && !error
                }"
                :value="mfaDigits[index]"
                @input="handleMfaInput($event, index)"
                @keydown="handleMfaKeydown($event, index)"
                @paste="handleMfaPaste"
                :disabled="loading"
              />
            </div>

            <div class="text-center mt-4">
              <button
                @click="showRecoveryInput = true"
                class="text-sm text-raspberry-500 hover:text-raspberry-700"
              >
                Lost access to authenticator? Use a recovery code
              </button>
            </div>
          </div>

          <!-- Recovery Code Input -->
          <div v-else>
            <div class="mb-4">
              <input
                v-model="recoveryCode"
                type="text"
                class="w-full px-4 py-3 font-mono text-center text-lg tracking-wider border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none"
                placeholder="XXXX-XXXX-XXXX"
                :disabled="loading"
                @keyup.enter="verifyRecoveryCode"
              >
            </div>

            <button
              @click="verifyRecoveryCode"
              :disabled="!recoveryCode.trim() || loading"
              class="w-full py-3 px-4 bg-raspberry-500 text-white font-medium rounded-lg hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <span v-if="loading">Verifying...</span>
              <span v-else>Use Recovery Code</span>
            </button>

            <div class="text-center mt-4">
              <button
                @click="showRecoveryInput = false"
                class="text-sm text-neutral-500 hover:text-horizon-500"
              >
                Back to authenticator code
              </button>
            </div>
          </div>

          <div v-if="error" class="mt-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
            <p class="text-sm text-raspberry-500 text-center">{{ error }}</p>
          </div>
        </div>

        <!-- Step 4: New Password Entry -->
        <div v-else-if="step === 'new-password'">
          <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-spring-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-horizon-500">Create New Password</h3>
            <p class="mt-2 text-sm text-neutral-500">
              Choose a strong password for your account.
            </p>
          </div>

          <form @submit.prevent="resetPassword">
            <div class="mb-4">
              <label for="new-password" class="block text-sm font-medium text-horizon-500 mb-1">
                New Password
              </label>
              <input
                id="new-password"
                v-model="newPassword"
                type="password"
                required
                minlength="8"
                class="w-full px-4 py-3 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none transition-all"
                placeholder="Enter new password"
                :disabled="loading"
                ref="passwordInput"
              >
            </div>

            <div class="mb-4">
              <label for="confirm-password" class="block text-sm font-medium text-horizon-500 mb-1">
                Confirm Password
              </label>
              <input
                id="confirm-password"
                v-model="confirmPassword"
                type="password"
                required
                minlength="8"
                class="w-full px-4 py-3 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none transition-all"
                placeholder="Confirm new password"
                :disabled="loading"
              >
            </div>

            <div v-if="passwordError" class="mb-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
              <p class="text-sm text-raspberry-500 text-center">{{ passwordError }}</p>
            </div>

            <div v-if="error" class="mb-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
              <p class="text-sm text-raspberry-500 text-center">{{ error }}</p>
            </div>

            <button
              type="submit"
              :disabled="!canSubmitPassword || loading"
              class="w-full py-3 px-4 bg-raspberry-500 text-white font-medium rounded-lg hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <span v-if="loading">Resetting...</span>
              <span v-else>Reset Password</span>
            </button>
          </form>

          <p class="mt-4 text-xs text-neutral-500 text-center">
            Password must be at least 8 characters long.
          </p>
        </div>

        <!-- Step 5: Success -->
        <div v-else-if="step === 'success'">
          <div class="text-center">
            <div class="mx-auto w-16 h-16 bg-spring-100 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-horizon-500">Password Reset Complete</h3>
            <p class="mt-2 text-sm text-neutral-500">
              Your password has been successfully reset. You can now sign in with your new password.
            </p>

            <button
              @click="handleClose"
              class="mt-6 w-full py-3 px-4 bg-raspberry-500 text-white font-medium rounded-lg hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 transition-all"
            >
              Sign In
            </button>
          </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="loading && step !== 'success'" class="absolute inset-0 bg-white/80 rounded-2xl flex items-center justify-center">
          <div class="flex flex-col items-center">
            <div class="w-10 h-10 border-4 border-violet-200 border-t-raspberry-500 rounded-full animate-spin"></div>
            <p class="mt-3 text-sm text-neutral-500">{{ loadingMessage }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, nextTick } from 'vue';
import authService from '../../services/authService';

export default {
  name: 'ForgotPasswordModal',
  props: {
    isOpen: {
      type: Boolean,
      required: true,
    },
  },
  emits: ['close', 'success'],
  setup(props, { emit }) {
    // State
    const step = ref('email');
    const email = ref('');
    const resetToken = ref('');
    const codeDigits = ref(['', '', '', '', '', '']);
    const mfaDigits = ref(['', '', '', '', '', '']);
    const recoveryCode = ref('');
    const showRecoveryInput = ref(false);
    const newPassword = ref('');
    const confirmPassword = ref('');
    const loading = ref(false);
    const resending = ref(false);
    const error = ref('');
    const remainingResends = ref(2);

    // Refs
    const emailInput = ref(null);
    const passwordInput = ref(null);
    const codeInputRefs = ref([]);
    const mfaInputRefs = ref([]);

    // Computed
    const fullCode = computed(() => codeDigits.value.join(''));
    const fullMfaCode = computed(() => mfaDigits.value.join(''));

    const passwordError = computed(() => {
      if (!newPassword.value || !confirmPassword.value) return '';
      if (newPassword.value.length < 8) return 'Password must be at least 8 characters';
      if (newPassword.value !== confirmPassword.value) return 'Passwords do not match';
      return '';
    });

    const canSubmitPassword = computed(() => {
      return newPassword.value.length >= 8 &&
             confirmPassword.value.length >= 8 &&
             newPassword.value === confirmPassword.value;
    });

    const loadingMessage = computed(() => {
      switch (step.value) {
        case 'email': return 'Sending verification code...';
        case 'verify-email': return 'Verifying code...';
        case 'verify-mfa': return 'Verifying MFA...';
        case 'new-password': return 'Resetting password...';
        default: return 'Please wait...';
      }
    });

    // Methods
    const goToStep = (newStep) => {
      step.value = newStep;
      error.value = '';
      if (newStep === 'email') {
        resetToken.value = '';
        clearCode();
      }
    };

    const clearCode = () => {
      codeDigits.value = ['', '', '', '', '', ''];
      nextTick(() => {
        codeInputRefs.value[0]?.focus();
      });
    };

    const clearMfaCode = () => {
      mfaDigits.value = ['', '', '', '', '', ''];
      nextTick(() => {
        mfaInputRefs.value[0]?.focus();
      });
    };

    const requestReset = async () => {
      if (!email.value) return;

      loading.value = true;
      error.value = '';

      try {
        const response = await authService.requestPasswordReset(email.value);

        if (response.success && response.data?.reset_token) {
          resetToken.value = response.data.reset_token;
          remainingResends.value = 2;
          step.value = 'verify-email';
          nextTick(() => {
            codeInputRefs.value[0]?.focus();
          });
        } else {
          // Still show success message (to prevent account enumeration)
          // but don't proceed (no token returned means user not found)
          error.value = 'If an account exists with this email, you will receive a verification code.';
        }
      } catch (err) {
        // Don't reveal if account exists or not
        error.value = err.response?.data?.message || 'Unable to process request. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleCodeInput = (event, index) => {
      const value = event.target.value.replace(/[^0-9]/g, '');
      codeDigits.value[index] = value;
      error.value = '';

      if (value && index < 5) {
        nextTick(() => {
          codeInputRefs.value[index + 1]?.focus();
        });
      }

      if (fullCode.value.length === 6) {
        verifyEmailCode();
      }
    };

    const handleCodeKeydown = (event, index) => {
      if (event.key === 'Backspace' && !codeDigits.value[index] && index > 0) {
        nextTick(() => {
          codeInputRefs.value[index - 1]?.focus();
        });
      }
      if (event.key === 'ArrowLeft' && index > 0) {
        codeInputRefs.value[index - 1]?.focus();
      }
      if (event.key === 'ArrowRight' && index < 5) {
        codeInputRefs.value[index + 1]?.focus();
      }
    };

    const handleCodePaste = (event) => {
      event.preventDefault();
      const pastedData = event.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
      if (pastedData) {
        pastedData.split('').forEach((digit, i) => {
          if (i < 6) codeDigits.value[i] = digit;
        });
        error.value = '';
        const nextEmpty = Math.min(pastedData.length, 5);
        nextTick(() => {
          codeInputRefs.value[nextEmpty]?.focus();
        });
        if (pastedData.length === 6) {
          verifyEmailCode();
        }
      }
    };

    const verifyEmailCode = async () => {
      if (fullCode.value.length !== 6) return;

      loading.value = true;
      error.value = '';

      try {
        const response = await authService.verifyPasswordResetEmail(resetToken.value, fullCode.value);

        if (response.success) {
          if (response.data?.requires_mfa) {
            step.value = 'verify-mfa';
            nextTick(() => {
              mfaInputRefs.value[0]?.focus();
            });
          } else {
            step.value = 'new-password';
            nextTick(() => {
              passwordInput.value?.focus();
            });
          }
        } else {
          error.value = response.message || 'Invalid verification code';
          clearCode();
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Invalid verification code';
        clearCode();
      } finally {
        loading.value = false;
      }
    };

    const handleResendCode = async () => {
      if (remainingResends.value === 0) return;

      resending.value = true;
      error.value = '';

      try {
        const response = await authService.resendPasswordResetCode(resetToken.value);

        if (response.success) {
          remainingResends.value = response.data?.remaining_resends ?? remainingResends.value - 1;
          clearCode();
        } else {
          error.value = response.message || 'Failed to resend code';
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to resend code';
      } finally {
        resending.value = false;
      }
    };

    // MFA handlers
    const handleMfaInput = (event, index) => {
      const value = event.target.value.replace(/[^0-9]/g, '');
      mfaDigits.value[index] = value;
      error.value = '';

      if (value && index < 5) {
        nextTick(() => {
          mfaInputRefs.value[index + 1]?.focus();
        });
      }

      if (fullMfaCode.value.length === 6) {
        verifyMfaCode();
      }
    };

    const handleMfaKeydown = (event, index) => {
      if (event.key === 'Backspace' && !mfaDigits.value[index] && index > 0) {
        nextTick(() => {
          mfaInputRefs.value[index - 1]?.focus();
        });
      }
      if (event.key === 'ArrowLeft' && index > 0) {
        mfaInputRefs.value[index - 1]?.focus();
      }
      if (event.key === 'ArrowRight' && index < 5) {
        mfaInputRefs.value[index + 1]?.focus();
      }
    };

    const handleMfaPaste = (event) => {
      event.preventDefault();
      const pastedData = event.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
      if (pastedData) {
        pastedData.split('').forEach((digit, i) => {
          if (i < 6) mfaDigits.value[i] = digit;
        });
        error.value = '';
        const nextEmpty = Math.min(pastedData.length, 5);
        nextTick(() => {
          mfaInputRefs.value[nextEmpty]?.focus();
        });
        if (pastedData.length === 6) {
          verifyMfaCode();
        }
      }
    };

    const verifyMfaCode = async () => {
      if (fullMfaCode.value.length !== 6) return;

      loading.value = true;
      error.value = '';

      try {
        const response = await authService.verifyPasswordResetMfa(resetToken.value, fullMfaCode.value);

        if (response.success) {
          step.value = 'new-password';
          nextTick(() => {
            passwordInput.value?.focus();
          });
        } else {
          error.value = response.message || 'Invalid MFA code';
          clearMfaCode();
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Invalid MFA code';
        clearMfaCode();
      } finally {
        loading.value = false;
      }
    };

    const verifyRecoveryCode = async () => {
      if (!recoveryCode.value.trim()) return;

      loading.value = true;
      error.value = '';

      try {
        const response = await authService.usePasswordResetRecoveryCode(resetToken.value, recoveryCode.value.trim());

        if (response.success) {
          step.value = 'new-password';
          nextTick(() => {
            passwordInput.value?.focus();
          });
        } else {
          error.value = response.message || 'Invalid recovery code';
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Invalid recovery code';
      } finally {
        loading.value = false;
      }
    };

    const resetPassword = async () => {
      if (!canSubmitPassword.value) return;

      loading.value = true;
      error.value = '';

      try {
        const response = await authService.resetPassword(
          resetToken.value,
          newPassword.value,
          confirmPassword.value
        );

        if (response.success) {
          step.value = 'success';
          emit('success');
        } else {
          error.value = response.message || 'Failed to reset password';
        }
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to reset password';
      } finally {
        loading.value = false;
      }
    };

    const resetModal = () => {
      step.value = 'email';
      email.value = '';
      resetToken.value = '';
      codeDigits.value = ['', '', '', '', '', ''];
      mfaDigits.value = ['', '', '', '', '', ''];
      recoveryCode.value = '';
      showRecoveryInput.value = false;
      newPassword.value = '';
      confirmPassword.value = '';
      error.value = '';
      remainingResends.value = 2;
    };

    const handleClose = () => {
      resetModal();
      emit('close');
    };

    // Watchers
    watch(() => props.isOpen, (newVal) => {
      if (newVal) {
        resetModal();
        nextTick(() => {
          emailInput.value?.focus();
        });
      }
    });

    return {
      step,
      email,
      codeDigits,
      mfaDigits,
      recoveryCode,
      showRecoveryInput,
      newPassword,
      confirmPassword,
      loading,
      resending,
      error,
      remainingResends,
      passwordError,
      canSubmitPassword,
      loadingMessage,
      emailInput,
      passwordInput,
      codeInputRefs,
      mfaInputRefs,
      goToStep,
      requestReset,
      handleCodeInput,
      handleCodeKeydown,
      handleCodePaste,
      handleResendCode,
      handleMfaInput,
      handleMfaKeydown,
      handleMfaPaste,
      verifyRecoveryCode,
      resetPassword,
      handleClose,
    };
  },
};
</script>

<style scoped>
/* Hide number input spinners */
input[type="text"]::-webkit-outer-spin-button,
input[type="text"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
