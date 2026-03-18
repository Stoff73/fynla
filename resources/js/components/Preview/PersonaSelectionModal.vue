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
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')" />

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
                            class="relative bg-white rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden"
                            @click.stop
                        >
                            <!-- Header -->
                            <div class="bg-gradient-to-br from-slate-800 to-slate-900 px-6 py-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-xl font-bold text-white">Choose Your Scenario</h2>
                                        <p class="text-slate-300 text-sm mt-1">Explore different financial situations</p>
                                    </div>
                                    <button
                                        @click="$emit('close')"
                                        class="text-slate-400 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10"
                                        aria-label="Close modal"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Persona Grid grouped by Stage -->
                            <div class="p-6">
                                <!-- Error Banner -->
                                <div v-if="error" class="mb-4 rounded-lg bg-raspberry-50 border border-raspberry-200 px-4 py-3 flex items-start gap-3">
                                    <svg class="w-5 h-5 text-raspberry-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                    <p class="text-sm text-raspberry-700">{{ error }}</p>
                                </div>

                                <div
                                    v-for="group in personasByStage"
                                    :key="group.stageId"
                                    class="mb-5 last:mb-0"
                                >
                                    <!-- Stage group header -->
                                    <div class="flex items-center gap-2 mb-3">
                                        <span
                                            class="text-sm font-bold"
                                            :class="getStageHeaderColour(group.stageColour)"
                                        >
                                            {{ group.stageLabel }}
                                        </span>
                                        <span class="text-xs font-medium" :class="getStageHeaderColour(group.stageColour)">
                                            Ages {{ group.ageRange }}
                                        </span>
                                        <div class="flex-1 h-px" :class="getStageDividerColour(group.stageColour)"></div>
                                    </div>

                                    <!-- Persona cards for this stage -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <button
                                            v-for="persona in group.personas"
                                            :key="persona.id"
                                            @click="selectPersona(persona)"
                                            :disabled="loadingPersonaId !== null"
                                            class="group relative text-left rounded-xl border-2 overflow-hidden transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
                                            :class="[
                                                loadingPersonaId === persona.id
                                                    ? 'border-raspberry-500 ring-2 ring-raspberry-500'
                                                    : 'border-light-gray hover:border-horizon-300 hover:shadow-lg hover:-translate-y-0.5',
                                                loadingPersonaId !== null && loadingPersonaId !== persona.id
                                                    ? 'opacity-50 cursor-not-allowed'
                                                    : ''
                                            ]"
                                        >
                                            <!-- Card Header with Gradient -->
                                            <div :class="getHeaderClasses(persona.id)" class="px-4 py-4">
                                                <div class="flex items-center gap-3">
                                                    <!-- Avatar -->
                                                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                                                        <span v-if="loadingPersonaId === persona.id" class="animate-spin">
                                                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                        </span>
                                                        <span v-else class="text-2xl">{{ getPersonaEmoji(persona.id) }}</span>
                                                    </div>
                                                    <!-- Name & Tagline -->
                                                    <div class="min-w-0">
                                                        <h3 class="font-bold text-white text-lg leading-tight">{{ persona.name }}</h3>
                                                        <p class="text-white/80 text-sm">{{ persona.tagline }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Content -->
                                            <div class="px-4 py-3 bg-white">
                                                <!-- Description -->
                                                <p class="text-neutral-500 text-sm mb-3 line-clamp-2">{{ persona.description }}</p>

                                                <!-- Stats -->
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md bg-savannah-100 text-xs font-medium text-neutral-500">
                                                        {{ persona.netWorthRange }}
                                                    </span>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium" :class="getFocusBadgeClasses(persona.id)">
                                                        {{ persona.focus }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Hover Arrow -->
                                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity" :class="{ 'opacity-100': loadingPersonaId === persona.id }">
                                                <svg v-if="loadingPersonaId !== persona.id" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                </svg>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <!-- Footer hint -->
                                <p class="text-center text-neutral-500 text-sm mt-5">
                                    Click a scenario to explore the demo dashboard
                                </p>

                                <!-- Register Section -->
                                <div class="mt-6 pt-5 border-t border-light-gray">
                                    <p class="text-center text-neutral-500 text-sm mb-3">
                                        We strongly recommend looking through a persona to see the full power of the platform.
                                    </p>
                                    <div class="flex justify-center">
                                        <router-link
                                            to="/register"
                                            class="inline-flex items-center px-5 py-2.5 bg-raspberry-600 text-white font-medium rounded-button hover:bg-raspberry-700 transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
                                            @click="$emit('close')"
                                        >
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                            </svg>
                                            Create Your Account
                                        </router-link>
                                    </div>
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
import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';

export default {
    name: 'PersonaSelectionModal',

    props: {
        isOpen: {
            type: Boolean,
            default: false,
        },
        personas: {
            type: Array,
            default: () => [],
        },
        error: {
            type: String,
            default: '',
        },
    },

    emits: ['close', 'select'],

    data() {
        return {
            loadingPersonaId: null,
        };
    },

    computed: {
        /**
         * Group personas by their life stage for display.
         * Returns an array of { stageId, stageLabel, stageColour, ageRange, personas: [] }
         */
        personasByStage() {
            const groups = [];
            const personaMap = {};

            // Build a map of stageId -> personas
            (this.personas || []).forEach(persona => {
                const stageId = PERSONA_TO_STAGE[persona.id];
                if (!stageId) return;
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
                        ageRange: stageConfig.ageRange,
                        personas: personaMap[stageId],
                    });
                }
            });

            // Include any personas without a stage mapping at the end
            const unmapped = (this.personas || []).filter(p => !PERSONA_TO_STAGE[p.id]);
            if (unmapped.length > 0) {
                groups.push({
                    stageId: 'other',
                    stageLabel: 'Other Scenarios',
                    stageColour: 'neutral',
                    ageRange: '',
                    personas: unmapped,
                });
            }

            return groups;
        },
    },

    watch: {
        isOpen(newVal) {
            if (!newVal) {
                // Reset loading state when modal closes
                this.loadingPersonaId = null;
            }
        },
        error(newVal) {
            if (newVal) {
                // Reset loading state so user can retry
                this.loadingPersonaId = null;
            }
        },
    },

    methods: {
        selectPersona(persona) {
            if (this.loadingPersonaId !== null) return;
            this.loadingPersonaId = persona.id;
            this.$emit('select', persona);
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

        getHeaderClasses(personaId) {
            // Map persona IDs to stage colours for the card header gradient
            const stageId = PERSONA_TO_STAGE[personaId];
            const colour = stageId ? LIFE_STAGES[stageId]?.colour : null;
            const gradients = {
                violet: 'bg-gradient-to-br from-violet-400 to-violet-600',
                spring: 'bg-gradient-to-br from-spring-400 to-spring-600',
                raspberry: 'bg-gradient-to-br from-raspberry-400 to-raspberry-600',
                'light-blue': 'bg-gradient-to-br from-light-blue-500 to-horizon-400',
                horizon: 'bg-gradient-to-br from-horizon-400 to-horizon-600',
            };
            return gradients[colour] || 'bg-gradient-to-br from-raspberry-500 to-raspberry-700';
        },

        getFocusBadgeClasses(personaId) {
            const stageId = PERSONA_TO_STAGE[personaId];
            const colour = stageId ? LIFE_STAGES[stageId]?.colour : null;
            const classes = {
                violet: 'bg-violet-100 text-violet-700',
                spring: 'bg-spring-100 text-spring-700',
                raspberry: 'bg-raspberry-100 text-raspberry-700',
                'light-blue': 'bg-light-blue-100 text-light-blue-500',
                horizon: 'bg-horizon-100 text-horizon-500',
            };
            return classes[colour] || 'bg-savannah-100 text-neutral-500';
        },

        getStageHeaderColour(colour) {
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

        getStageDividerColour(colour) {
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
    },

    mounted() {
        // Handle escape key to close modal
        const handleEscape = (e) => {
            if (e.key === 'Escape' && this.isOpen && this.loadingPersonaId === null) {
                this.$emit('close');
            }
        };
        document.addEventListener('keydown', handleEscape);
        this.$options.handleEscape = handleEscape;
    },

    beforeUnmount() {
        if (this.$options.handleEscape) {
            document.removeEventListener('keydown', this.$options.handleEscape);
        }
    },
};
</script>

