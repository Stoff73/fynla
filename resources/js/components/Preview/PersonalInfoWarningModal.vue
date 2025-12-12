<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/30 backdrop-blur-sm"
                    @click="$emit('close')"
                />

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isOpen"
                            class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6"
                            @click.stop
                        >
                            <!-- Header with icon -->
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                    <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Personal Information
                                </h3>
                            </div>

                            <!-- Content -->
                            <p class="text-gray-600 mb-6">
                                Personal details like names, dates of birth, and addresses are
                                <strong class="text-gray-900">not saved</strong> in preview mode.
                                Register now to create your own personalised financial plan with your real information.
                            </p>

                            <!-- Info box -->
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6">
                                <div class="flex items-start gap-2">
                                    <svg class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-sm text-amber-800">
                                        Any changes you make will be lost when you leave or refresh the page.
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="space-y-3">
                                <button
                                    @click="$emit('register')"
                                    class="w-full bg-primary-600 text-white px-4 py-2.5 rounded-lg font-medium hover:bg-primary-700 transition-colors flex items-center justify-center gap-2"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    Register Now
                                </button>

                                <button
                                    @click="$emit('continue')"
                                    class="w-full bg-gray-100 text-gray-700 px-4 py-2.5 rounded-lg font-medium hover:bg-gray-200 transition-colors"
                                >
                                    Continue Without Saving
                                </button>

                                <button
                                    @click="$emit('close')"
                                    class="w-full text-gray-500 text-sm hover:text-gray-700 transition-colors py-2"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
export default {
    name: 'PersonalInfoWarningModal',

    props: {
        isOpen: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['close', 'register', 'continue'],
};
</script>
