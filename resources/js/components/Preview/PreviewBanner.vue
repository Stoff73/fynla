<template>
    <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white px-3 sm:px-4 py-2 shadow-md">
        <div class="max-w-7xl mx-auto">
            <!-- Mobile layout -->
            <div class="flex flex-col sm:hidden space-y-2">
                <!-- Top row: Preview badge + Persona selector -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="font-semibold text-sm">Preview</span>
                        <span v-if="hasEdits" class="text-amber-100 text-xs">{{ editCount }} edit{{ editCount === 1 ? '' : 's' }}</span>
                    </div>
                    <PersonaSelector variant="dark" size="small" @persona-selected="handlePersonaSelected" />
                </div>

                <!-- Bottom row: Actions -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <button v-if="hasEdits" @click="handleClearEdits" class="text-amber-100 hover:text-white text-xs font-medium">
                            Reset
                        </button>
                        <button @click="exitPreviewMode" class="text-amber-100 hover:text-white text-xs font-medium">
                            Exit
                        </button>
                    </div>
                    <router-link to="/register" class="bg-white text-amber-600 px-3 py-1 rounded-md font-medium text-xs hover:bg-amber-50 transition-colors shadow-sm">
                        Register
                    </router-link>
                </div>

                <!-- Loading indicator -->
                <div v-if="switching" class="text-amber-100 text-xs flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Loading persona...</span>
                </div>
            </div>

            <!-- Desktop layout -->
            <div class="hidden sm:flex items-center justify-between">
                <!-- Left side: Preview indicator -->
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>
                        <span class="font-semibold">Preview Mode</span>
                    </div>

                    <!-- Persona Selector Component -->
                    <PersonaSelector
                        variant="dark"
                        @persona-selected="handlePersonaSelected"
                    />

                    <!-- Edit count indicator -->
                    <span v-if="hasEdits" class="text-amber-100 text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        {{ editCount }} edit{{ editCount === 1 ? '' : 's' }}
                    </span>

                    <span v-if="switching" class="text-amber-100 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>
                </div>

                <!-- Right side: Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Clear edits button (only show if has edits) -->
                    <button
                        v-if="hasEdits"
                        @click="handleClearEdits"
                        class="text-amber-100 hover:text-white text-sm font-medium transition-colors flex items-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset
                    </button>

                    <!-- Exit Preview -->
                    <button
                        @click="exitPreviewMode"
                        class="text-amber-100 hover:text-white text-sm font-medium transition-colors"
                    >
                        Exit Demo
                    </button>

                    <!-- Register CTA -->
                    <router-link
                        to="/register"
                        class="bg-white text-amber-600 px-4 py-1.5 rounded-md font-medium text-sm hover:bg-amber-50 transition-colors shadow-sm"
                    >
                        Register to Save Your Data
                    </router-link>
                </div>
            </div>
        </div>
    </div>

    <!-- Persona Intro Modal -->
    <PersonaIntroModal
        :is-open="showIntroModal"
        :persona="selectedPersona"
        @close="cancelPersonaSwitch"
        @explore="confirmPersonaSwitch"
    />
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import PersonaSelector from './PersonaSelector.vue';
import PersonaIntroModal from './PersonaIntroModal.vue';

export default {
    name: 'PreviewBanner',

    components: {
        PersonaSelector,
        PersonaIntroModal,
    },

    data() {
        return {
            switching: false,
            showIntroModal: false,
            selectedPersona: null,
        };
    },

    computed: {
        ...mapGetters('preview', [
            'currentPersona',
            'currentPersonaId',
            'hasEdits',
            'editCount',
        ]),

        currentPersonaName() {
            return this.currentPersona?.name || 'Demo User';
        },
    },

    methods: {
        ...mapActions('preview', ['exitPreview', 'switchPersona', 'clearEdits']),

        async exitPreviewMode() {
            await this.exitPreview();
            this.$router.push('/');
        },

        handlePersonaSelected(persona) {
            // Show intro modal for the selected persona
            this.selectedPersona = persona;
            this.showIntroModal = true;
        },

        cancelPersonaSwitch() {
            this.showIntroModal = false;
            this.selectedPersona = null;
        },

        async confirmPersonaSwitch() {
            if (!this.selectedPersona) return;

            this.showIntroModal = false;
            this.switching = true;

            try {
                await this.switchPersona(this.selectedPersona.id);
                // Reload the page to refresh all components with new persona data
                // Preview mode persists to sessionStorage, so it will be restored on reload
                this.$router.go(0);
            } catch (error) {
                console.error('Failed to switch persona:', error);
                this.switching = false;
            }
            // Note: don't reset switching in finally because page will reload
        },

        async handleClearEdits() {
            if (confirm('Reset all changes and restore original persona data?')) {
                await this.clearEdits();
            }
        },
    },
};
</script>
