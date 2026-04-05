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
                class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-light-gray z-50 overflow-hidden"
            >
                <!-- Header -->
                <div class="px-4 py-3 bg-savannah-100 border-b border-light-gray">
                    <h3 class="font-semibold text-horizon-500">Select a Financial Scenario</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Explore different life stages and situations</p>
                </div>

                <!-- Persona options grouped by stage -->
                <div class="p-2 max-h-96 overflow-y-auto">
                    <div
                        v-for="group in personasByStage"
                        :key="group.stageId"
                        class="mb-3 last:mb-0"
                    >
                        <!-- Stage group header -->
                        <div class="flex items-center gap-2 px-3 py-1.5 mb-1">
                            <span
                                class="text-xs font-bold uppercase tracking-wide"
                                :class="stageHeaderColourClass(group.stageColour)"
                            >
                                {{ group.stageLabel }}
                            </span>
                            <div class="flex-1 h-px ml-1" :class="stageDividerClass(group.stageColour)"></div>
                        </div>

                        <!-- Persona cards within this stage -->
                        <button
                            v-for="persona in group.personas"
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
                                        <span class="font-medium text-horizon-500">{{ persona.name }}</span>
                                        <span
                                            v-if="persona.id === basePersonaId"
                                            class="text-xs bg-raspberry-100 text-raspberry-700 px-1.5 py-0.5 rounded"
                                        >
                                            Current
                                        </span>
                                    </div>
                                    <p class="text-sm text-neutral-500 mt-0.5">{{ persona.tagline }}</p>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-horizon-400">
                                        <span>{{ persona.netWorthRange }}</span>
                                        <span class="text-horizon-300">|</span>
                                        <span>{{ persona.focus }}</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

            </div>
        </Transition>
    </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';

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
        };
    },

    computed: {
        ...mapGetters('preview', [
            'currentPersona',
            'currentPersonaId',
            'basePersonaId',
            'availablePersonas',
        ]),

        /**
         * Group available personas by their life stage.
         * Returns an array of { stageId, stageLabel, stageColour, personas: [] }
         */
        personasByStage() {
            const groups = [];
            const personaMap = {};
            const unmapped = [];

            // Build a map of stageId -> personas
            this.availablePersonas.forEach(persona => {
                const stageId = PERSONA_TO_STAGE[persona.id];
                if (!stageId) {
                    unmapped.push(persona);
                    return;
                }
                if (!personaMap[stageId]) {
                    personaMap[stageId] = [];
                }
                personaMap[stageId].push(persona);
            });

            // Build ordered groups
            STAGE_ORDER.forEach(stageId => {
                if (personaMap[stageId] && personaMap[stageId].length > 0) {
                    const stageConfig = LIFE_STAGES[stageId];
                    groups.push({
                        stageId,
                        stageLabel: stageConfig.label,
                        stageColour: stageConfig.colour,
                        personas: personaMap[stageId],
                    });
                }
            });

            // Include any personas without a stage mapping at the end
            if (unmapped.length > 0) {
                groups.push({
                    stageId: 'other',
                    stageLabel: 'Other',
                    stageColour: 'neutral',
                    personas: unmapped,
                });
            }

            return groups;
        },

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
                    young_family: 'bg-blue-600 hover:bg-blue-700 text-white',
                    peak_earners: 'bg-green-600 hover:bg-green-700 text-white',
                    entrepreneur: 'bg-fuchsia-600 hover:bg-fuchsia-700 text-white',
                    young_saver: 'bg-cyan-600 hover:bg-cyan-700 text-white',
                    student: 'bg-teal-600 hover:bg-teal-700 text-white',
                    retired_couple: 'bg-rose-600 hover:bg-rose-700 text-white',
                };
                base = darkColors[this.basePersonaId] || 'bg-fuchsia-600 hover:bg-fuchsia-700 text-white';
            } else {
                base = 'bg-white hover:bg-savannah-100 text-neutral-500 border border-light-gray';
            }

            if (this.size === 'small') {
                return `${base} text-xs px-2 py-1`;
            }
            return base;
        },
    },

    methods: {
        ...mapActions('preview', ['switchPersona']),

        toggleDropdown() {
            this.isOpen = !this.isOpen;
        },

        async selectPersona(persona) {
            // Use basePersonaId to handle spouse view - clicking the same family shouldn't switch
            if (persona.id === this.basePersonaId) {
                this.isOpen = false;
                return;
            }

            await this.doSwitch(persona);
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
                return 'bg-raspberry-50 border border-raspberry-200';
            }
            return 'hover:bg-savannah-100';
        },

        avatarClasses(persona) {
            const colors = {
                young_family: 'bg-violet-100',
                peak_earners: 'bg-spring-100',
                widow: 'bg-purple-100',
                entrepreneur: 'bg-fuchsia-100',
                young_saver: 'bg-cyan-100',
                student: 'bg-teal-100',
                retired_couple: 'bg-rose-100',
            };
            return colors[persona.id] || 'bg-savannah-100';
        },

        getPersonaEmoji(personaId) {
            const emojis = {
                young_family: '👨‍👩‍👧‍👦',
                peak_earners: '💼',
                widow: '👵',
                entrepreneur: '🚀',
                young_saver: '🎓',
                student: '📚',
                retired_couple: '👴👵',
            };
            return emojis[personaId] || '👤';
        },

        stageHeaderColourClass(colour) {
            const map = {
                violet: 'text-violet-500',
                spring: 'text-spring-600',
                raspberry: 'text-raspberry-500',
                'light-blue': 'text-light-blue-500',
                horizon: 'text-horizon-500',
                neutral: 'text-neutral-500',
            };
            return map[colour] || 'text-neutral-500';
        },

        stageDividerClass(colour) {
            const map = {
                violet: 'bg-violet-200',
                spring: 'bg-spring-200',
                raspberry: 'bg-raspberry-200',
                'light-blue': 'bg-light-blue-100',
                horizon: 'bg-horizon-200',
                neutral: 'bg-light-gray',
            };
            return map[colour] || 'bg-light-gray';
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
