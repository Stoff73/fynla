<template>
    <div class="relative" ref="selectorRef">
        <!-- Trigger button -->
        <button
            @click="toggleDropdown"
            class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
            :class="buttonClasses"
        >
            <!-- User icon -->
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>

            <span class="font-medium">{{ currentPersonaName }}</span>

            <!-- Chevron -->
            <svg
                class="h-4 w-4 transition-transform"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden"
            >
                <!-- Header -->
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-900">Select a Financial Scenario</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Explore different life stages and situations</p>
                </div>

                <!-- Persona options -->
                <div class="p-2 max-h-96 overflow-y-auto">
                    <button
                        v-for="persona in availablePersonas"
                        :key="persona.id"
                        @click="selectPersona(persona)"
                        class="w-full p-3 rounded-lg text-left transition-colors mb-1 last:mb-0"
                        :class="personaButtonClasses(persona)"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Avatar/Icon based on persona -->
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                :class="avatarClasses(persona)"
                            >
                                <span class="text-lg">{{ getPersonaEmoji(persona.id) }}</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ persona.name }}</span>
                                    <span
                                        v-if="persona.id === basePersonaId"
                                        class="text-xs bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded"
                                    >
                                        Current
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-0.5">{{ persona.tagline }}</p>
                                <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                    <span>{{ persona.netWorthRange }}</span>
                                    <span class="text-gray-300">|</span>
                                    <span>{{ persona.focus }}</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Footer with edit indicator -->
                <div v-if="hasEdits" class="px-4 py-3 bg-blue-50 border-t border-blue-200">
                    <div class="flex items-center gap-2 text-sm text-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>You have {{ editCount }} unsaved change{{ editCount === 1 ? '' : 's' }}. Switching personas will discard them.</span>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Confirm switch modal (when user has edits) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50" @click="cancelSwitch" />
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.stop>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Switch Personas?</h3>
                            <p class="text-gray-600 mb-4">
                                You have {{ editCount }} unsaved change{{ editCount === 1 ? '' : 's' }}.
                                Switching to <strong>{{ pendingPersona?.name }}</strong> will discard them.
                            </p>
                            <div class="flex gap-3">
                                <button
                                    @click="confirmSwitch"
                                    class="flex-1 bg-primary-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-700"
                                >
                                    Switch Anyway
                                </button>
                                <button
                                    @click="cancelSwitch"
                                    class="flex-1 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-200"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';

export default {
    name: 'PersonaSelector',
    emits: ['persona-selected'],

    props: {
        variant: {
            type: String,
            default: 'light', // 'light' or 'dark'
        },
        size: {
            type: String,
            default: 'default', // 'small' or 'default'
        },
    },

    data() {
        return {
            isOpen: false,
            showConfirmModal: false,
            pendingPersona: null,
        };
    },

    computed: {
        ...mapGetters('preview', [
            'currentPersona',
            'currentPersonaId',
            'basePersonaId',
            'availablePersonas',
            'hasEdits',
            'editCount',
        ]),

        /**
         * Get the base persona for display (handles spouse view)
         * When viewing as spouse, we still want to show the family name
         */
        basePersona() {
            return this.availablePersonas.find(p => p.id === this.basePersonaId);
        },

        currentPersonaName() {
            // Always show the family/couple name, not individual spouse name
            return this.basePersona?.name || this.currentPersona?.name || 'Select Persona';
        },

        buttonClasses() {
            let base = '';
            if (this.variant === 'dark') {
                // Use persona-specific darker shade for the selector button
                // Use basePersonaId to maintain consistent color when viewing as spouse
                const darkColors = {
                    young_family: 'bg-primary-600 hover:bg-primary-700 text-white',
                    peak_earners: 'bg-green-700 hover:bg-green-800 text-white',
                    widow: 'bg-purple-600 hover:bg-purple-700 text-white',
                    entrepreneur: 'bg-fuchsia-600 hover:bg-fuchsia-700 text-white',
                    young_saver: 'bg-cyan-600 hover:bg-cyan-700 text-white',
                    retired_couple: 'bg-rose-600 hover:bg-rose-700 text-white',
                };
                base = darkColors[this.basePersonaId] || 'bg-fuchsia-600 hover:bg-fuchsia-700 text-white';
            } else {
                base = 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-200';
            }

            if (this.size === 'small') {
                return `${base} text-xs px-2 py-1`;
            }
            return base;
        },
    },

    methods: {
        ...mapActions('preview', ['switchPersona', 'clearEdits']),

        toggleDropdown() {
            this.isOpen = !this.isOpen;
        },

        async selectPersona(persona) {
            // Use basePersonaId to handle spouse view - clicking the same family shouldn't switch
            if (persona.id === this.basePersonaId) {
                this.isOpen = false;
                return;
            }

            if (this.hasEdits) {
                this.pendingPersona = persona;
                this.showConfirmModal = true;
                this.isOpen = false;
                return;
            }

            await this.doSwitch(persona);
        },

        async confirmSwitch() {
            if (this.pendingPersona) {
                await this.doSwitch(this.pendingPersona);
            }
            this.showConfirmModal = false;
            this.pendingPersona = null;
        },

        cancelSwitch() {
            this.showConfirmModal = false;
            this.pendingPersona = null;
        },

        doSwitch(persona) {
            this.isOpen = false;

            // Emit event for parent (PreviewBanner) to show intro modal
            // The actual switchPersona() call happens in PreviewBanner.confirmPersonaSwitch()
            // when the user clicks "Explore Dashboard" button in the modal
            this.$emit('persona-selected', persona);
        },

        personaButtonClasses(persona) {
            // Use basePersonaId to maintain highlighting when viewing as spouse
            if (persona.id === this.basePersonaId) {
                return 'bg-primary-50 border border-primary-200';
            }
            return 'hover:bg-gray-50';
        },

        avatarClasses(persona) {
            const colors = {
                young_family: 'bg-blue-100',
                peak_earners: 'bg-green-100',
                widow: 'bg-purple-100',
                entrepreneur: 'bg-fuchsia-100',
                young_saver: 'bg-cyan-100',
                retired_couple: 'bg-rose-100',
            };
            return colors[persona.id] || 'bg-gray-100';
        },

        getPersonaEmoji(personaId) {
            const emojis = {
                young_family: '👨‍👩‍👧‍👦',
                peak_earners: '💼',
                widow: '👵',
                entrepreneur: '🚀',
                young_saver: '🎓',
                retired_couple: '👴👵',
            };
            return emojis[personaId] || '👤';
        },

        handleClickOutside(event) {
            if (this.$refs.selectorRef && !this.$refs.selectorRef.contains(event.target)) {
                this.isOpen = false;
            }
        },
    },

    mounted() {
        document.addEventListener('click', this.handleClickOutside);
    },

    beforeUnmount() {
        document.removeEventListener('click', this.handleClickOutside);
    },
};
</script>
