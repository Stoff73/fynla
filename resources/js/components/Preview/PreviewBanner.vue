<template>
    <div class="text-white py-2 shadow-md" :class="bannerColorClass">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Mobile layout -->
            <div class="flex flex-col sm:hidden space-y-2">
                <!-- Top row: Preview badge + Persona selector -->
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="font-semibold text-sm">Preview</span>
                        <span v-if="hasSpouse && currentViewerName" class="text-xs opacity-90">({{ currentViewerName }})</span>
                    </div>
                    <PersonaSelector variant="dark" size="small" @persona-selected="handlePersonaSelected" />
                </div>

                <!-- Middle row: Spouse toggle (only for personas with spouses) -->
                <div v-if="hasSpouse && !switching" class="flex justify-center">
                    <button
                        @click="handleSpouseToggle"
                        :class="[spouseToggleClass, 'flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-medium transition-all duration-200']"
                        :disabled="switchingSpouse"
                    >
                        <svg v-if="switchingSpouse" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>View as {{ toggleTargetName }}</span>
                    </button>
                </div>

                <!-- Bottom row: Actions -->
                <div class="flex items-center space-x-3">
                    <button @click="exitPreviewMode" :class="[buttonColorClass, 'text-xs font-medium transition-colors']">
                        Exit
                    </button>
                    <router-link to="/register" :class="[registerButtonClass, 'px-3 py-1 rounded-md font-medium text-xs transition-colors shadow-sm']">
                        Register
                    </router-link>
                </div>

                <!-- Loading indicator -->
                <div v-if="switching" :class="[loadingTextClass, 'text-xs flex items-center justify-center gap-2']">
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
                        <span v-if="hasSpouse && currentViewerName" class="text-sm opacity-90">({{ currentViewerName }}'s view)</span>
                    </div>

                    <!-- Persona Selector Component -->
                    <PersonaSelector
                        variant="dark"
                        @persona-selected="handlePersonaSelected"
                    />

                    <span v-if="switching" :class="[loadingTextClass, 'text-sm flex items-center gap-2']">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading...
                    </span>

                    <!-- Spouse View Toggle -->
                    <button
                        v-if="hasSpouse && !switching"
                        @click="handleSpouseToggle"
                        :class="[spouseToggleClass, 'flex items-center gap-1.5 px-3 py-1 rounded-md text-sm font-medium transition-all duration-200 hover:scale-105']"
                        :disabled="switchingSpouse"
                    >
                        <svg v-if="switchingSpouse" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>View as {{ toggleTargetName }}</span>
                    </button>
                </div>

                <!-- Right side: Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Exit Preview -->
                    <button
                        @click="exitPreviewMode"
                        :class="[buttonColorClass, 'text-sm font-medium transition-colors']"
                    >
                        Exit Demo
                    </button>

                    <!-- Register CTA -->
                    <router-link
                        to="/register"
                        :class="[registerButtonClass, 'px-4 py-1.5 rounded-md font-medium text-sm transition-colors shadow-sm']"
                    >
                        Signup Now
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

import logger from '@/utils/logger';
export default {
    name: 'PreviewBanner',

    components: {
        PersonaSelector,
        PersonaIntroModal,
    },

    data() {
        return {
            switching: false,
            switchingSpouse: false,
            showIntroModal: false,
            selectedPersona: null,
        };
    },

    computed: {
        ...mapGetters('preview', [
            'currentPersona',
            'currentPersonaId',
            'hasSpouse',
            'isViewingAsSpouse',
            'basePersonaId',
            'toggleTargetName',
            'currentViewerName',
        ]),

        currentPersonaName() {
            return this.currentPersona?.name || 'Demo User';
        },

        bannerColorClass() {
            const colors = {
                young_family: 'bg-gradient-to-r from-blue-500 to-blue-600',
                peak_earners: 'bg-gradient-to-r from-green-500 to-green-600',
                entrepreneur: 'bg-gradient-to-r from-fuchsia-500 to-fuchsia-600',
                young_saver: 'bg-gradient-to-r from-cyan-500 to-cyan-600',
                student: 'bg-gradient-to-r from-teal-500 to-teal-600',
                retired_couple: 'bg-gradient-to-r from-rose-500 to-rose-600',
            };
            // Use basePersonaId to get consistent colors for both primary and spouse views
            return colors[this.basePersonaId] || 'bg-gradient-to-r from-neutral-500 to-neutral-600';
        },

        buttonColorClass() {
            return 'bg-white text-horizon-500 hover:bg-white/90 px-4 py-1.5 rounded-md';
        },

        registerButtonClass() {
            return 'bg-raspberry-500 text-white hover:bg-raspberry-600';
        },

        loadingTextClass() {
            const colors = {
                young_family: 'text-blue-100',
                peak_earners: 'text-green-100',
                entrepreneur: 'text-fuchsia-100',
                young_saver: 'text-cyan-100',
                student: 'text-teal-100',
                retired_couple: 'text-rose-100',
            };
            return colors[this.basePersonaId] || 'text-savannah-100';
        },

        /**
         * Spouse toggle button styling - uses a semi-transparent white button
         * that works well on all persona gradient backgrounds
         */
        spouseToggleClass() {
            // Shade darker than the banner gradient for each persona
            const colors = {
                young_family: 'bg-blue-700 hover:bg-blue-800 text-white border border-blue-400/30',
                peak_earners: 'bg-green-700 hover:bg-green-800 text-white border border-green-400/30',
                entrepreneur: 'bg-fuchsia-700 hover:bg-fuchsia-800 text-white border border-fuchsia-400/30',
                young_saver: 'bg-cyan-700 hover:bg-cyan-800 text-white border border-cyan-400/30',
                student: 'bg-teal-700 hover:bg-teal-800 text-white border border-teal-400/30',
                retired_couple: 'bg-rose-700 hover:bg-rose-800 text-white border border-rose-400/30',
            };
            return colors[this.basePersonaId] || 'bg-neutral-700 hover:bg-neutral-800 text-white border border-neutral-400/30';
        },
    },

    methods: {
        ...mapActions('preview', ['exitPreview', 'switchPersona', 'toggleSpouseView']),

        async exitPreviewMode() {
            await this.exitPreview();
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
                // switchPersona will reload the page
            } catch (error) {
                logger.error('Failed to switch persona:', error);
            } finally {
                this.switching = false;
            }
        },

        /**
         * Handle clicking the spouse view toggle button
         */
        async handleSpouseToggle() {
            if (this.switchingSpouse) return;

            this.switchingSpouse = true;

            try {
                await this.toggleSpouseView();
                // toggleSpouseView will reload the page via switchPersona
            } catch (error) {
                logger.error('Failed to toggle spouse view:', error);
            } finally {
                this.switchingSpouse = false;
            }
        },
    },
};
</script>
