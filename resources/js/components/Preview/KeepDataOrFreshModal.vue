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
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />

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
                            class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden"
                            @click.stop
                        >
                            <!-- Header -->
                            <div class="bg-gradient-to-br from-raspberry-500 to-raspberry-700 p-6 text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold">Welcome to Fynla!</h2>
                                        <p class="text-white/80">Let's set up your account</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-6">
                                <p class="text-neutral-500 mb-6">
                                    You were exploring <strong class="text-horizon-500">{{ personaName }}</strong>'s example data.
                                    Would you like to keep it as a starting point?
                                </p>

                                <div class="space-y-3">
                                    <!-- Keep Data Option -->
                                    <button
                                        @click="selected = 'keep'"
                                        class="w-full p-4 border-2 rounded-xl text-left transition-all"
                                        :class="selected === 'keep'
                                            ? 'border-raspberry-500 bg-raspberry-50 ring-2 ring-raspberry-200'
                                            : 'border-light-gray hover:border-horizon-300 hover:bg-savannah-100'"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                                 :class="selected === 'keep' ? 'bg-raspberry-100' : 'bg-savannah-100'">
                                                <svg class="h-5 w-5" :class="selected === 'keep' ? 'text-raspberry-600' : 'text-neutral-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-horizon-500">Keep {{ personaFirstName }}'s data</div>
                                                <div class="text-sm text-neutral-500 mt-0.5">
                                                    Start with example data and modify to match your situation.
                                                </div>
                                                <div v-if="dataSummary" class="text-xs text-horizon-400 mt-2 flex flex-wrap gap-2">
                                                    <span v-for="item in dataSummary" :key="item" class="bg-savannah-100 px-2 py-0.5 rounded">
                                                        {{ item }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div v-if="selected === 'keep'" class="flex-shrink-0">
                                                <svg class="w-5 h-5 text-raspberry-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </button>

                                    <!-- Start Fresh Option -->
                                    <button
                                        @click="selected = 'fresh'"
                                        class="w-full p-4 border-2 rounded-xl text-left transition-all"
                                        :class="selected === 'fresh'
                                            ? 'border-raspberry-500 bg-raspberry-50 ring-2 ring-raspberry-200'
                                            : 'border-light-gray hover:border-horizon-300 hover:bg-savannah-100'"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                                 :class="selected === 'fresh' ? 'bg-raspberry-100' : 'bg-savannah-100'">
                                                <svg class="h-5 w-5" :class="selected === 'fresh' ? 'text-raspberry-600' : 'text-neutral-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-horizon-500">Start fresh</div>
                                                <div class="text-sm text-neutral-500 mt-0.5">
                                                    Begin with a clean slate and enter your own data.
                                                </div>
                                            </div>
                                            <div v-if="selected === 'fresh'" class="flex-shrink-0">
                                                <svg class="w-5 h-5 text-raspberry-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    </button>

                                    <!-- Spouse option for married personas -->
                                    <Transition
                                        enter-active-class="transition ease-out duration-200"
                                        enter-from-class="opacity-0 -translate-y-2"
                                        enter-to-class="opacity-100 translate-y-0"
                                        leave-active-class="transition ease-in duration-150"
                                        leave-from-class="opacity-100 translate-y-0"
                                        leave-to-class="opacity-0 -translate-y-2"
                                    >
                                        <div v-if="personaIsMarried && selected === 'keep'" class="ml-4 p-3 bg-violet-50 rounded-lg border border-violet-200">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    v-model="createSpouseAccount"
                                                    class="w-4 h-4 rounded text-raspberry-600 focus:ring-violet-500"
                                                />
                                                <div>
                                                    <span class="text-sm font-medium text-horizon-500">Also create account for {{ spouseName }}</span>
                                                    <p class="text-xs text-neutral-500 mt-0.5">They'll receive an email invitation to join</p>
                                                </div>
                                            </label>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- Actions -->
                                <div class="mt-6 flex gap-3">
                                    <button
                                        @click="handleContinue"
                                        :disabled="!selected || loading"
                                        class="flex-1 bg-raspberry-600 text-white px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2"
                                        :class="!selected || loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-raspberry-700'"
                                    >
                                        <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ loading ? 'Setting up...' : 'Continue' }}</span>
                                        <svg v-if="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Info note -->
                                <p class="text-xs text-horizon-400 text-center mt-4">
                                    You can always add, edit, or remove data later from your dashboard.
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
import { mapGetters } from 'vuex';

export default {
    name: 'KeepDataOrFreshModal',

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

    emits: ['choice', 'close'],

    data() {
        return {
            selected: null,
            createSpouseAccount: false,
            loading: false,
        };
    },

    computed: {
        ...mapGetters('preview', ['currentPersona', 'effectivePersonaData']),

        activePersona() {
            // Use prop first, then full persona data from store, then basic metadata
            return this.persona || this.effectivePersonaData || this.currentPersona;
        },

        personaName() {
            if (!this.activePersona) return 'Demo User';
            return this.activePersona.name || 'Demo User';
        },

        personaFirstName() {
            if (!this.personaName) return 'Demo';
            // Handle names like "James & Emily Wilson" -> "the Wilsons"
            if (this.personaName.includes('&')) {
                const lastName = this.personaName.split(' ').pop();
                return `the ${lastName}s`;
            }
            // Handle single names like "Margaret Thompson" -> "Margaret"
            return this.personaName.split(' ')[0];
        },

        personaIsMarried() {
            if (!this.activePersona) return false;
            return this.activePersona?.user?.marital_status === 'married' || !!this.activePersona?.spouse;
        },

        spouseName() {
            if (!this.activePersona) return '';
            return this.activePersona?.spouse?.name || 'your spouse';
        },

        dataSummary() {
            if (!this.activePersona) return [];
            const data = this.activePersona;
            const summary = [];

            const propertyCount = data?.properties?.length || 0;
            if (propertyCount > 0) {
                summary.push(`${propertyCount} ${propertyCount === 1 ? 'property' : 'properties'}`);
            }

            const savingsCount = data?.savings_accounts?.length || 0;
            if (savingsCount > 0) {
                summary.push(`${savingsCount} savings ${savingsCount === 1 ? 'account' : 'accounts'}`);
            }

            const investmentCount = data?.investment_accounts?.length || 0;
            if (investmentCount > 0) {
                summary.push(`${investmentCount} ${investmentCount === 1 ? 'investment' : 'investments'}`);
            }

            const pensionCount = (data?.dc_pensions?.length || 0) + (data?.db_pensions?.length || 0);
            if (pensionCount > 0) {
                summary.push(`${pensionCount} ${pensionCount === 1 ? 'pension' : 'pensions'}`);
            }

            const policyCount = (data?.life_insurance_policies?.length || 0) +
                               (data?.critical_illness_policies?.length || 0) +
                               (data?.income_protection_policies?.length || 0);
            if (policyCount > 0) {
                summary.push(`${policyCount} ${policyCount === 1 ? 'policy' : 'policies'}`);
            }

            return summary;
        },
    },

    watch: {
        isOpen(newVal) {
            if (newVal) {
                // Reset state when modal opens
                this.selected = null;
                this.createSpouseAccount = false;
                this.loading = false;
            }
        },
    },

    methods: {
        async handleContinue() {
            if (!this.selected) return;

            this.loading = true;

            this.$emit('choice', {
                choice: this.selected,
                createSpouseAccount: this.selected === 'keep' && this.personaIsMarried && this.createSpouseAccount,
                personaId: this.activePersona?.id,
            });
        },

        // Called by parent to reset loading state if needed
        resetLoading() {
            this.loading = false;
        },
    },
};
</script>
