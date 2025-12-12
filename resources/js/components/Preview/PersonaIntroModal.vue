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
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('close')" />

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
                            v-if="isOpen && persona"
                            class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden"
                            @click.stop
                        >
                            <!-- Header with gradient -->
                            <div :class="headerClasses">
                                <div class="p-6">
                                    <!-- Avatar -->
                                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mb-4">
                                        <span class="text-4xl">{{ personaEmoji }}</span>
                                    </div>

                                    <!-- Name and tagline -->
                                    <h2 class="text-2xl font-bold text-white mb-1">{{ persona.name }}</h2>
                                    <p class="text-white/80">{{ persona.tagline }}</p>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-6">
                                <!-- Description -->
                                <p class="text-gray-600 mb-6">{{ persona.description }}</p>

                                <!-- Key stats -->
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Net Worth</div>
                                        <div class="font-semibold text-gray-900">{{ persona.netWorthRange }}</div>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Key Focus</div>
                                        <div class="font-semibold text-gray-900">{{ persona.focus }}</div>
                                    </div>
                                </div>

                                <!-- Key concerns / highlights -->
                                <div v-if="keyConcerns.length > 0" class="mb-6">
                                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Key Financial Questions</h3>
                                    <ul class="space-y-2">
                                        <li
                                            v-for="(concern, index) in keyConcerns"
                                            :key="index"
                                            class="flex items-start gap-2 text-sm text-gray-600"
                                        >
                                            <svg class="w-5 h-5 text-primary-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>{{ concern }}</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-3">
                                    <button
                                        @click="$emit('explore')"
                                        class="flex-1 bg-primary-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors flex items-center justify-center gap-2"
                                    >
                                        <span>Explore Dashboard</span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="$emit('close')"
                                        class="px-4 py-3 rounded-lg font-medium text-gray-600 hover:bg-gray-100 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
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
    name: 'PersonaIntroModal',

    props: {
        isOpen: {
            type: Boolean,
            default: false,
        },
        persona: {
            type: Object,
            default: null,
        },
    },

    emits: ['close', 'explore'],

    computed: {
        personaEmoji() {
            if (!this.persona) return '👤';

            const emojis = {
                young_family: '👨‍👩‍👧‍👦',
                peak_earners: '💼',
                widow: '👵',
                entrepreneur: '🚀',
            };
            return emojis[this.persona.id] || '👤';
        },

        headerClasses() {
            if (!this.persona) return 'bg-gradient-to-br from-primary-500 to-primary-700';

            const gradients = {
                young_family: 'bg-gradient-to-br from-blue-500 to-blue-700',
                peak_earners: 'bg-gradient-to-br from-green-500 to-green-700',
                widow: 'bg-gradient-to-br from-purple-500 to-purple-700',
                entrepreneur: 'bg-gradient-to-br from-orange-500 to-orange-700',
            };
            return gradients[this.persona.id] || 'bg-gradient-to-br from-primary-500 to-primary-700';
        },

        keyConcerns() {
            if (!this.persona) return [];

            // Default concerns based on persona type
            const concerns = {
                young_family: [
                    'Do we have enough life cover if something happens?',
                    'How many months of expenses do we have in savings?',
                    'Are we contributing enough to our pensions?',
                ],
                peak_earners: [
                    'Are we maximising our pension tax relief?',
                    'How much inheritance tax will our estate face?',
                    'Is our investment portfolio properly diversified?',
                ],
                widow: [
                    'How can I reduce the inheritance tax on my estate?',
                    'Should I be making gifts to reduce my estate?',
                    'Do I have enough income for retirement?',
                ],
                entrepreneur: [
                    'Is my business adequately protected?',
                    'Am I saving enough for retirement?',
                    'How do I balance business growth with personal security?',
                ],
            };

            return concerns[this.persona.id] || [];
        },
    },
};
</script>
