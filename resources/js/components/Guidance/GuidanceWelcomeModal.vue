<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="handleDismiss" />

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition
                        enter-active-class="transition ease-out duration-300"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-200"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isOpen"
                            class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden"
                            @click.stop
                        >
                            <!-- Header with icon -->
                            <div class="pt-8 pb-4 px-6 text-center">
                                <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="h-8 w-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>

                                <h2 class="text-xl font-semibold text-gray-900 mb-2">
                                    Welcome to Fynla!
                                </h2>

                                <p class="text-gray-600">
                                    Let's personalise your financial plan. We'll guide you through the key sections step by step.
                                </p>
                            </div>

                            <!-- Steps preview -->
                            <div class="px-6 pb-4">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h3 class="text-sm font-medium text-gray-700 mb-3">What we'll cover:</h3>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            v-for="step in previewSteps"
                                            :key="step.id"
                                            class="flex items-center gap-2 text-sm text-gray-600"
                                        >
                                            <svg class="w-4 h-4 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>{{ step.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Time estimate -->
                            <div class="px-6 pb-4">
                                <div class="flex items-center justify-center gap-2 text-sm text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Takes about 5-10 minutes</span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="px-6 pb-6 space-y-3">
                                <button
                                    @click="handleStart"
                                    class="w-full bg-primary-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors flex items-center justify-center gap-2"
                                >
                                    <span>Let's Start</span>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </button>

                                <button
                                    @click="handleDismiss"
                                    class="w-full text-gray-500 hover:text-gray-700 py-2 text-sm font-medium transition-colors"
                                >
                                    I'll explore on my own
                                </button>
                            </div>

                            <!-- Can restart later note -->
                            <div class="px-6 pb-6">
                                <p class="text-xs text-gray-400 text-center">
                                    You can restart the setup guide anytime from Settings
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { GUIDANCE_STEPS } from '@/store/modules/guidance';

export default {
    name: 'GuidanceWelcomeModal',

    props: {
        isOpen: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['start', 'dismiss'],

    computed: {
        ...mapGetters('guidance', ['allSteps']),

        previewSteps() {
            // Show first 8 steps in preview
            return GUIDANCE_STEPS.slice(0, 8);
        },
    },

    methods: {
        ...mapActions('guidance', ['startGuidance', 'dismissGuidance', 'hideWelcomeModal']),

        async handleStart() {
            await this.startGuidance();
            this.$emit('start');
        },

        async handleDismiss() {
            await this.dismissGuidance();
            this.$emit('dismiss');
        },
    },
};
</script>
