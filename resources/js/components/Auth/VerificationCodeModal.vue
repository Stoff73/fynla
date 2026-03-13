<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop (no click handler - modal stays open) -->
        <div
            class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
        ></div>

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
                    title="Cancel verification"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="mx-auto w-16 h-16 bg-raspberry-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-horizon-500">Enter Verification Code</h3>
                    <p class="mt-2 text-sm text-neutral-500">
                        We sent a code to <span class="font-medium">{{ userEmail }}</span>
                    </p>
                </div>

                <!-- Code Input -->
                <div class="flex justify-center gap-2 mb-6">
                    <input
                        v-for="(digit, index) in 6"
                        :key="index"
                        :ref="el => { if (el) inputRefs[index] = el }"
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
                        @input="handleInput($event, index)"
                        @keydown="handleKeydown($event, index)"
                        @paste="handlePaste"
                        :disabled="verifying"
                    />
                </div>

                <!-- Error Message -->
                <div v-if="error" class="mb-4 p-3 bg-raspberry-50 border border-raspberry-200 rounded-lg">
                    <p class="text-sm text-raspberry-500 text-center">{{ error }}</p>
                </div>

                <!-- Resend Section -->
                <div class="text-center mb-6">
                    <button
                        @click="handleResend"
                        :disabled="resending"
                        class="text-sm text-raspberry-500 hover:text-raspberry-700 font-medium disabled:text-horizon-400 disabled:cursor-not-allowed transition-colors"
                    >
                        <span v-if="resending">Sending...</span>
                        <span v-else>Resend Code</span>
                    </button>
                </div>

                <!-- Help Text -->
                <div class="text-center text-xs text-neutral-500">
                    <p>Didn't receive the email? Check your spam folder.</p>
                </div>

                <!-- Loading Overlay -->
                <div v-if="verifying" class="absolute inset-0 bg-white/80 rounded-2xl flex items-center justify-center">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 border-4 border-violet-200 border-t-raspberry-500 rounded-full animate-spin"></div>
                        <p class="mt-3 text-sm text-neutral-500">Verifying...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import api from '../../services/api';

export default {
    name: 'VerificationCodeModal',
    props: {
        isOpen: {
            type: Boolean,
            required: true
        },
        userEmail: {
            type: String,
            required: true
        },
        // For login verification (challenge token from login response)
        challengeToken: {
            type: String,
            default: null
        },
        // For registration verification
        pendingId: {
            type: Number,
            default: null
        },
        type: {
            type: String,
            required: true,
            validator: (value) => ['login', 'registration'].includes(value)
        }
    },
    emits: ['verified', 'close'],
    setup(props, { emit }) {
        const codeDigits = ref(['', '', '', '', '', '']);
        const inputRefs = ref([]);
        const error = ref(null);
        const verifying = ref(false);
        const resending = ref(false);

        const fullCode = computed(() => codeDigits.value.join(''));

        const handleInput = (event, index) => {
            const value = event.target.value.replace(/[^0-9]/g, '');
            codeDigits.value[index] = value;
            error.value = null;

            // Move to next input
            if (value && index < 5) {
                nextTick(() => {
                    inputRefs.value[index + 1]?.focus();
                });
            }

            // Auto-submit when all 6 digits entered
            if (fullCode.value.length === 6) {
                verifyCode();
            }
        };

        const handleKeydown = (event, index) => {
            // Handle backspace
            if (event.key === 'Backspace' && !codeDigits.value[index] && index > 0) {
                nextTick(() => {
                    inputRefs.value[index - 1]?.focus();
                });
            }
            // Handle arrow keys
            if (event.key === 'ArrowLeft' && index > 0) {
                inputRefs.value[index - 1]?.focus();
            }
            if (event.key === 'ArrowRight' && index < 5) {
                inputRefs.value[index + 1]?.focus();
            }
        };

        const handlePaste = (event) => {
            event.preventDefault();
            const pastedData = event.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            if (pastedData) {
                pastedData.split('').forEach((digit, i) => {
                    if (i < 6) codeDigits.value[i] = digit;
                });
                error.value = null;
                // Focus the appropriate input
                const nextEmpty = Math.min(pastedData.length, 5);
                nextTick(() => {
                    inputRefs.value[nextEmpty]?.focus();
                });
                // Auto-submit if complete
                if (pastedData.length === 6) {
                    verifyCode();
                }
            }
        };

        const verifyCode = async () => {
            if (fullCode.value.length !== 6) return;

            verifying.value = true;
            error.value = null;

            try {
                // Build request based on type
                const requestData = {
                    code: fullCode.value,
                    type: props.type
                };

                if (props.type === 'registration') {
                    requestData.pending_id = props.pendingId;
                } else {
                    requestData.challenge_token = props.challengeToken;
                }

                const response = await api.post('/auth/verify-code', requestData);

                if (response.data.success) {
                    emit('verified', response.data.data);
                } else {
                    error.value = response.data.message || 'Verification failed';
                    clearCode();
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Invalid verification code';
                clearCode();
            } finally {
                verifying.value = false;
            }
        };

        const handleResend = async () => {
            resending.value = true;
            error.value = null;

            try {
                // Build request based on type
                const requestData = { type: props.type };

                if (props.type === 'registration') {
                    requestData.pending_id = props.pendingId;
                } else {
                    requestData.challenge_token = props.challengeToken;
                }

                const response = await api.post('/auth/resend-code', requestData);

                if (response.data.success) {
                    clearCode();
                    error.value = null;
                } else {
                    error.value = response.data.message || 'Failed to resend code';
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to resend code';
            } finally {
                resending.value = false;
            }
        };

        const clearCode = () => {
            codeDigits.value = ['', '', '', '', '', ''];
            nextTick(() => {
                inputRefs.value[0]?.focus();
            });
        };

        const handleClose = () => {
            emit('close');
        };

        watch(() => props.isOpen, (newVal) => {
            if (newVal) {
                clearCode();
                error.value = null;
                nextTick(() => {
                    inputRefs.value[0]?.focus();
                });
            }
        });

        onMounted(() => {
            if (props.isOpen) {
                nextTick(() => {
                    inputRefs.value[0]?.focus();
                });
            }
        });

        return {
            codeDigits,
            inputRefs,
            error,
            verifying,
            resending,
            handleInput,
            handleKeydown,
            handlePaste,
            handleResend,
            handleClose
        };
    }
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
