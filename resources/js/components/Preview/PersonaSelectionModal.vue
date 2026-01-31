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

                            <!-- Persona Grid -->
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <button
                                        v-for="persona in personas"
                                        :key="persona.id"
                                        @click="selectPersona(persona)"
                                        :disabled="loadingPersonaId !== null"
                                        class="group relative text-left rounded-xl border-2 overflow-hidden transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                        :class="[
                                            loadingPersonaId === persona.id
                                                ? 'border-primary-500 ring-2 ring-primary-500'
                                                : 'border-gray-200 hover:border-gray-300 hover:shadow-lg hover:-translate-y-0.5',
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
                                            <p class="text-gray-600 text-sm mb-3 line-clamp-2">{{ persona.description }}</p>

                                            <!-- Stats -->
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="inline-flex items-center px-2 py-1 rounded-md bg-gray-100 text-xs font-medium text-gray-700">
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

                                <!-- Footer hint -->
                                <p class="text-center text-gray-500 text-sm mt-5">
                                    Click a scenario to explore the demo dashboard
                                </p>

                                <!-- Register Section -->
                                <div class="mt-6 pt-5 border-t border-gray-200">
                                    <p class="text-center text-gray-600 text-sm mb-3">
                                        We strongly encourage you to explore the personas above first to see what Fynla can do.
                                    </p>
                                    <div class="flex justify-center">
                                        <router-link
                                            to="/register"
                                            class="inline-flex items-center px-5 py-2.5 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
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
    },

    emits: ['close', 'select'],

    data() {
        return {
            loadingPersonaId: null,
        };
    },

    watch: {
        isOpen(newVal) {
            if (!newVal) {
                // Reset loading state when modal closes
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
                retired_couple: '👴👵',
            };
            return emojis[personaId] || '👤';
        },

        getHeaderClasses(personaId) {
            const gradients = {
                young_family: 'bg-gradient-to-br from-blue-500 to-blue-700',
                peak_earners: 'bg-gradient-to-br from-green-500 to-green-700',
                widow: 'bg-gradient-to-br from-purple-500 to-purple-700',
                entrepreneur: 'bg-gradient-to-br from-fuchsia-500 to-fuchsia-700',
                young_saver: 'bg-gradient-to-br from-cyan-500 to-cyan-700',
                retired_couple: 'bg-gradient-to-br from-rose-500 to-rose-700',
            };
            return gradients[personaId] || 'bg-gradient-to-br from-primary-500 to-primary-700';
        },

        getFocusBadgeClasses(personaId) {
            const classes = {
                young_family: 'bg-blue-100 text-blue-700',
                peak_earners: 'bg-green-100 text-green-700',
                widow: 'bg-purple-100 text-purple-700',
                entrepreneur: 'bg-fuchsia-100 text-fuchsia-700',
                young_saver: 'bg-cyan-100 text-cyan-700',
                retired_couple: 'bg-rose-100 text-rose-700',
            };
            return classes[personaId] || 'bg-gray-100 text-gray-700';
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
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
