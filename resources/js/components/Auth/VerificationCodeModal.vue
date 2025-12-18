<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
            @click="handleClose"
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
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="mx-auto w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Enter Verification Code</h3>
                    <p class="mt-2 text-sm text-gray-600">
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
                        class="w-12 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all"
                        :class="{
                            'border-red-500 bg-red-50': error,
                            'border-indigo-500': codeDigits[index] && !error
                        }"
                        :value="codeDigits[index]"
                        @input="handleInput($event, index)"
                        @keydown="handleKeydown($event, index)"
                        @paste="handlePaste"
                        :disabled="verifying"
                    />
                </div>

                <!-- Error Message -->
                <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600 text-center">{{ error }}</p>
                </div>

                <!-- Timer -->
                <div class="text-center mb-4">
                    <div v-if="timeRemaining > 0" class="flex items-center justify-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 animate-pulse text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                        <span>Code expires in <span class="font-semibold text-amber-600">{{ formattedTime }}</span></span>
                    </div>
                    <div v-else class="text-sm text-red-600">
                        Code expired
                    </div>
                </div>

                <!-- Resend Section -->
                <div class="text-center mb-6">
                    <button
                        v-if="canResend"
                        @click="handleResend"
                        :disabled="resending || timeRemaining > 0"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium disabled:text-gray-400 disabled:cursor-not-allowed transition-colors"
                    >
                        <span v-if="resending">Sending...</span>
                        <span v-else-if="timeRemaining > 0">Resend available after timer</span>
                        <span v-else>Resend Code</span>
                    </button>
                    <p v-if="remainingResends !== null" class="mt-1 text-xs text-gray-500">
                        {{ remainingResends }} resend{{ remainingResends !== 1 ? 's' : '' }} remaining
                    </p>
                    <p v-if="!canResend && remainingResends === 0" class="mt-2 text-sm text-red-600">
                        Maximum resends reached. Please refresh and try again.
                    </p>
                </div>

                <!-- Help Text -->
                <div class="text-center text-xs text-gray-500">
                    <p>Didn't receive the email? Check your spam folder.</p>
                </div>

                <!-- Loading Overlay -->
                <div v-if="verifying" class="absolute inset-0 bg-white/80 rounded-2xl flex items-center justify-center">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                        <p class="mt-3 text-sm text-gray-600">Verifying...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
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
        userId: {
            type: Number,
            required: true
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
        const timeRemaining = ref(60);
        const remainingResends = ref(2);
        const canResend = ref(true);
        let timerInterval = null;

        const formattedTime = computed(() => {
            const minutes = Math.floor(timeRemaining.value / 60);
            const seconds = timeRemaining.value % 60;
            return `${minutes}:${String(seconds).padStart(2, '0')}`;
        });

        const fullCode = computed(() => codeDigits.value.join(''));

        const startTimer = () => {
            timeRemaining.value = 60;
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (timeRemaining.value > 0) {
                    timeRemaining.value--;
                } else {
                    clearInterval(timerInterval);
                    // Auto-resend if allowed
                    if (canResend.value && remainingResends.value > 0) {
                        handleResend();
                    }
                }
            }, 1000);
        };

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
                const response = await api.post('/auth/verify-code', {
                    user_id: props.userId,
                    code: fullCode.value,
                    type: props.type
                });

                if (response.data.success) {
                    emit('verified', response.data.data);
                } else {
                    error.value = response.data.message || 'Verification failed';
                    clearCode();
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Invalid or expired verification code';
                clearCode();
            } finally {
                verifying.value = false;
            }
        };

        const handleResend = async () => {
            if (!canResend.value || remainingResends.value <= 0) return;

            resending.value = true;
            error.value = null;

            try {
                const response = await api.post('/auth/resend-code', {
                    user_id: props.userId,
                    type: props.type
                });

                if (response.data.success) {
                    remainingResends.value = response.data.data.remaining_resends;
                    canResend.value = response.data.data.can_resend;
                    startTimer();
                    clearCode();
                } else {
                    error.value = response.data.message || 'Failed to resend code';
                }
            } catch (err) {
                if (err.response?.status === 429) {
                    canResend.value = false;
                    remainingResends.value = 0;
                    error.value = 'Maximum resend limit reached. Please refresh and try again.';
                } else {
                    error.value = err.response?.data?.message || 'Failed to resend code';
                }
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

        // Handle escape key
        const handleEscape = (event) => {
            if (event.key === 'Escape' && props.isOpen) {
                handleClose();
            }
        };

        watch(() => props.isOpen, (newVal) => {
            if (newVal) {
                startTimer();
                clearCode();
                error.value = null;
                remainingResends.value = 2;
                canResend.value = true;
                nextTick(() => {
                    inputRefs.value[0]?.focus();
                });
            } else {
                if (timerInterval) clearInterval(timerInterval);
            }
        });

        onMounted(() => {
            document.addEventListener('keydown', handleEscape);
            if (props.isOpen) {
                startTimer();
                nextTick(() => {
                    inputRefs.value[0]?.focus();
                });
            }
        });

        onUnmounted(() => {
            document.removeEventListener('keydown', handleEscape);
            if (timerInterval) clearInterval(timerInterval);
        });

        return {
            codeDigits,
            inputRefs,
            error,
            verifying,
            resending,
            timeRemaining,
            formattedTime,
            remainingResends,
            canResend,
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
