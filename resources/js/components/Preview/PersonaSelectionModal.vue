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
                            class="relative bg-white rounded-2xl shadow-2xl max-w-5xl w-full overflow-hidden"
                            @click.stop
                        >
                            <!-- Header -->
                            <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-black text-white">Choose your journey to see it in action</h2>
                                        <p class="text-white/70 text-sm mt-1">Explore realistic scenarios with sample data — no sign-up required</p>
                                    </div>
                                    <button
                                        @click="$emit('close')"
                                        class="text-white/60 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10"
                                        aria-label="Close modal"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Persona Grid — two rows with separated categories -->
                            <div class="p-5">
                                <!-- Error Banner -->
                                <div v-if="error" class="mb-4 rounded-lg bg-raspberry-50 border border-raspberry-200 px-4 py-3 flex items-start gap-3">
                                    <svg class="w-5 h-5 text-raspberry-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                    <p class="text-sm text-raspberry-700">{{ error }}</p>
                                </div>

                                <!-- Row 1: Starting Out (1) + Protecting (2) -->
                                <div class="flex flex-col sm:flex-row gap-3 mb-3 items-stretch">
                                    <!-- Starting Out -->
                                    <div class="flex-1 flex flex-col">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-1.5 mb-2">
                                            <svg class="w-6 h-6 sm:w-4 sm:h-4 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            <div class="flex items-center gap-1.5 flex-1">
                                                <span class="text-[0.7rem] font-bold text-horizon-500 uppercase tracking-wider">Starting Out</span>
                                                <div class="flex-1 h-px bg-light-gray ml-1"></div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2.5 flex-1">
                                            <button
                                                v-for="persona in getPersonasForStage('university')"
                                                :key="persona.id"
                                                @click="selectPersona(persona)"
                                                :disabled="loadingPersonaId !== null"
                                                class="flex-1 text-left rounded-xl p-3 sm:p-3.5 transition-all duration-200 focus:outline-none persona-card-starting"
                                                :class="[
                                                    loadingPersonaId === persona.id ? 'ring-2 ring-raspberry-500' : 'hover:-translate-y-0.5 hover:shadow-md',
                                                    loadingPersonaId !== null && loadingPersonaId !== persona.id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                                ]"
                                            >
                                                <div class="flex items-center gap-2 mb-1.5">
                                                    <svg v-if="loadingPersonaId !== persona.id" class="w-5 h-5 flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getPersonaIcon(persona.id)"/></svg>
                                                    <span class="text-sm font-bold leading-tight">{{ persona.name }}</span>
                                                </div>
                                                <p class="text-[0.7rem] opacity-70 leading-relaxed mb-2">{{ persona.tagline }}</p>
                                                <div class="hidden sm:flex flex-col gap-1 mb-2">
                                                    <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.netWorthRange }}</span>
                                                    <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.focus }}</span>
                                                </div>
                                                <span class="text-xs font-bold">View demo &rarr;</span>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Protecting and Growing -->
                                    <div class="flex-1 sm:flex-[2] flex flex-col">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-1.5 mb-2">
                                            <svg class="w-6 h-6 sm:w-4 sm:h-4 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            <div class="flex items-center gap-1.5 flex-1">
                                                <span class="text-[0.7rem] font-bold text-horizon-500 uppercase tracking-wider">Protecting and Growing</span>
                                                <div class="flex-1 h-px bg-light-gray ml-1"></div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2.5 flex-1">
                                            <button
                                                v-for="persona in getPersonasForStage('mid_career')"
                                                :key="persona.id"
                                                @click="selectPersona(persona)"
                                                :disabled="loadingPersonaId !== null"
                                                class="flex-1 text-left rounded-xl p-3 sm:p-3.5 transition-all duration-200 focus:outline-none persona-card-protecting"
                                                :class="[
                                                    loadingPersonaId === persona.id ? 'ring-2 ring-raspberry-500' : 'hover:-translate-y-0.5 hover:shadow-md',
                                                    loadingPersonaId !== null && loadingPersonaId !== persona.id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                                ]"
                                            >
                                                <div class="flex items-center gap-2 mb-1.5">
                                                    <svg v-if="loadingPersonaId !== persona.id" class="w-5 h-5 flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getPersonaIcon(persona.id)"/></svg>
                                                    <span class="text-sm font-bold leading-tight">{{ persona.name }}</span>
                                                </div>
                                                <p class="text-[0.7rem] opacity-70 leading-relaxed mb-2">{{ persona.tagline }}</p>
                                                <div class="hidden sm:flex flex-col gap-1 mb-2">
                                                    <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.netWorthRange }}</span>
                                                    <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.focus }}</span>
                                                </div>
                                                <span class="text-xs font-bold">View demo &rarr;</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 2: Building Foundations (1) + Planning (1) + Enjoying (1) -->
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <!-- Building Foundations -->
                                    <div class="flex-1">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-1.5 mb-2">
                                            <svg class="w-6 h-6 sm:w-4 sm:h-4 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                            <div class="flex items-center gap-1.5 flex-1">
                                                <span class="text-[0.7rem] font-bold text-horizon-500 uppercase tracking-wider">Building Foundations</span>
                                                <div class="flex-1 h-px bg-light-gray ml-1"></div>
                                            </div>
                                        </div>
                                        <button
                                            v-for="persona in getPersonasForStage('early_career')"
                                            :key="persona.id"
                                            @click="selectPersona(persona)"
                                            :disabled="loadingPersonaId !== null"
                                            class="w-full text-left rounded-xl p-3 sm:p-3.5 transition-all duration-200 focus:outline-none persona-card-building"
                                            :class="[
                                                loadingPersonaId === persona.id ? 'ring-2 ring-raspberry-500' : 'hover:-translate-y-0.5 hover:shadow-md',
                                                loadingPersonaId !== null && loadingPersonaId !== persona.id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                            ]"
                                        >
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <svg v-if="loadingPersonaId !== persona.id" class="w-5 h-5 flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getPersonaIcon(persona.id)"/></svg>
                                                <span class="text-sm font-bold leading-tight">{{ persona.name }}</span>
                                            </div>
                                            <p class="text-[0.7rem] opacity-70 leading-relaxed mb-2">{{ persona.tagline }}</p>
                                            <div class="hidden sm:flex flex-col gap-1 mb-2">
                                                <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.netWorthRange }}</span>
                                                <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.focus }}</span>
                                            </div>
                                            <span class="text-xs font-bold">View demo &rarr;</span>
                                        </button>
                                    </div>
                                    <!-- Planning Your Future -->
                                    <div class="flex-1">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-1.5 mb-2">
                                            <svg class="w-6 h-6 sm:w-4 sm:h-4 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <div class="flex items-center gap-1.5 flex-1">
                                                <span class="text-[0.7rem] font-bold text-horizon-500 uppercase tracking-wider">Planning Your Future</span>
                                                <div class="flex-1 h-px bg-light-gray ml-1"></div>
                                            </div>
                                        </div>
                                        <button
                                            v-for="persona in getPersonasForStage('peak')"
                                            :key="persona.id"
                                            @click="selectPersona(persona)"
                                            :disabled="loadingPersonaId !== null"
                                            class="w-full text-left rounded-xl p-3 sm:p-3.5 transition-all duration-200 focus:outline-none persona-card-planning"
                                            :class="[
                                                loadingPersonaId === persona.id ? 'ring-2 ring-raspberry-500' : 'hover:-translate-y-0.5 hover:shadow-md',
                                                loadingPersonaId !== null && loadingPersonaId !== persona.id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                            ]"
                                        >
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <svg v-if="loadingPersonaId !== persona.id" class="w-5 h-5 flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getPersonaIcon(persona.id)"/></svg>
                                                <span class="text-sm font-bold leading-tight">{{ persona.name }}</span>
                                            </div>
                                            <p class="text-[0.7rem] opacity-70 leading-relaxed mb-2">{{ persona.tagline }}</p>
                                            <div class="hidden sm:flex flex-col gap-1 mb-2">
                                                <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.netWorthRange }}</span>
                                                <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.focus }}</span>
                                            </div>
                                            <span class="text-xs font-bold">View demo &rarr;</span>
                                        </button>
                                    </div>
                                    <!-- Enjoying Your Wealth -->
                                    <div class="flex-1">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-1.5 mb-2">
                                            <svg class="w-6 h-6 sm:w-4 sm:h-4 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7"/></svg>
                                            <div class="flex items-center gap-1.5 flex-1">
                                                <span class="text-[0.7rem] font-bold text-horizon-500 uppercase tracking-wider">Enjoying Your Wealth</span>
                                                <div class="flex-1 h-px bg-light-gray ml-1"></div>
                                            </div>
                                        </div>
                                        <button
                                            v-for="persona in getPersonasForStage('retirement')"
                                            :key="persona.id"
                                            @click="selectPersona(persona)"
                                            :disabled="loadingPersonaId !== null"
                                            class="w-full text-left rounded-xl p-3 sm:p-3.5 mb-2.5 last:mb-0 transition-all duration-200 focus:outline-none persona-card-enjoying"
                                            :class="[
                                                loadingPersonaId === persona.id ? 'ring-2 ring-raspberry-500' : 'hover:-translate-y-0.5 hover:shadow-md',
                                                loadingPersonaId !== null && loadingPersonaId !== persona.id ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                            ]"
                                        >
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <svg v-if="loadingPersonaId !== persona.id" class="w-5 h-5 flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getPersonaIcon(persona.id)"/></svg>
                                                <span class="text-sm font-bold leading-tight">{{ persona.name }}</span>
                                            </div>
                                            <p class="text-[0.7rem] opacity-70 leading-relaxed mb-2">{{ persona.tagline }}</p>
                                            <div class="hidden sm:flex flex-col gap-1 mb-2">
                                                <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.netWorthRange }}</span>
                                                <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md bg-black/[0.08] self-start">{{ persona.focus }}</span>
                                            </div>
                                            <span class="text-xs font-bold">View demo &rarr;</span>
                                        </button>
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
         * Arrange persona groups into two rows for the modal layout.
         * Row 1: Starting Out + Protecting and Growing
         * Row 2: Planning Your Future + Enjoying Your Wealth
         */
        personaRows() {
            const groups = this.personasByStage;
            if (groups.length <= 2) return [groups];
            const mid = Math.ceil(groups.length / 2);
            return [groups.slice(0, mid), groups.slice(mid)];
        },

        /**
         * Group personas by their life stage for display.
         * Returns an array of { stageId, stageLabel, stageColour, personas: [] }
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

        getCardBgClass(colour) {
            const map = {
                violet: 'persona-card-planning',
                spring: 'persona-card-starting',
                raspberry: 'persona-card-protecting',
                'light-blue': 'persona-card-protecting',
                horizon: 'persona-card-protecting',
                neutral: 'persona-card-starting',
            };
            return map[colour] || 'persona-card-starting';
        },

        getCardBgByStage(stageId) {
            const map = {
                university: 'persona-card-starting',
                early_career: 'persona-card-building',
                mid_career: 'persona-card-protecting',
                peak: 'persona-card-planning',
                retirement: 'persona-card-enjoying',
            };
            return map[stageId] || 'persona-card-starting';
        },

        getPersonasForStage(stageId) {
            const group = this.personasByStage.find(g => g.stageId === stageId);
            return group ? group.personas : [];
        },

        getStageIcon(stageId) {
            const icons = {
                university: 'M13 10V3L4 14h7v7l9-11h-7z',
                early_career: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                mid_career: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                peak: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                retirement: 'M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7',
            };
            return icons[stageId] || 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z';
        },

        getPersonaIcon(personaId) {
            const icons = {
                student: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
                young_saver: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                young_family: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                entrepreneur: 'M13 10V3L4 14h7v7l9-11h-7z',
                peak_earners: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                widow: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                retired_couple: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
            };
            return icons[personaId] || 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z';
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

<style scoped>
.persona-card-starting { @apply bg-spring-100 text-spring-900; }
.persona-card-protecting { @apply bg-horizon-100 text-horizon-900; }
.persona-card-planning { @apply bg-violet-100 text-violet-900; }
.persona-card-building { @apply bg-spring-100 text-spring-900; }
.persona-card-enjoying { @apply bg-raspberry-100 text-raspberry-900; }
</style>

