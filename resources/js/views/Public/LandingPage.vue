<template>
  <PublicLayout>
    <!-- Hero Section - Horizon-to-Raspberry Gradient with Meet Fyn -->
    <div class="relative overflow-hidden hero-gradient">
      <!-- Decorative orbs for depth -->
      <div class="absolute top-20 right-20 w-72 h-72 bg-violet-500/10 rounded-full blur-3xl"></div>
      <div class="absolute bottom-10 left-10 w-96 h-96 bg-horizon-500/10 rounded-full blur-3xl"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-8">
          <!-- Left: Springbok placeholder area (empty for now) -->
          <div class="hidden lg:block lg:w-64 lg:flex-shrink-0">
            <!-- Springbok image will go here when available -->
          </div>

          <!-- Centre: Meet Fyn Text -->
          <div class="flex-1 text-left pl-24">
            <h1 class="hero-title text-white leading-none">
              Meet<br />Fyn
            </h1>
          </div>

          <!-- Right: Chat Card -->
          <div class="mt-10 lg:mt-0 lg:w-80 xl:w-96 lg:flex-shrink-0">
            <div class="bg-white rounded-2xl p-5 shadow-2xl">
              <p class="text-horizon-500 font-medium text-sm mb-3 leading-relaxed">
                Tell Fyn about your financial goals or simply get started for free!
              </p>
              <input
                v-model="chatInput"
                type="text"
                placeholder="Enter your text here"
                class="w-full px-4 py-2.5 border border-light-gray rounded-lg text-sm text-horizon-500 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent mb-3"
                @keyup.enter="handleAskFyn"
              />
              <div class="flex gap-3">
                <button
                  type="button"
                  @click="handleAskFyn"
                  class="min-w-[120px] px-5 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors text-center"
                >
                  Ask Fyn
                </button>
                <button
                  type="button"
                  @click="enterPreviewMode"
                  :disabled="enteringPreview"
                  class="min-w-[120px] px-5 py-2.5 bg-savannah-500 text-white text-sm font-semibold rounded-lg hover:bg-savannah-600 transition-colors disabled:opacity-50 text-center"
                >
                  Get started
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Subtitle spanning full width below -->
        <p class="hero-subtitle text-white/90 mt-6 text-center whitespace-nowrap">
          Your financial companion for life
        </p>
      </div>
    </div>

    <!-- Map Your Path to Financial Freedom Section -->
    <div class="bg-white py-10 lg:py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-16">
          <!-- Left: Text Content -->
          <div class="lg:flex-1">
            <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-horizon-500 leading-tight">
              Map your path to<br />
              <span class="text-raspberry-500">financial freedom</span>
            </h2>
            <p class="text-lg text-neutral-500 mt-6 mb-8 max-w-lg leading-relaxed">
              Comprehensive tools designed for UK individuals and families to plan protection, savings, investments, retirement, and estate with confidence.
            </p>
            <div class="flex flex-wrap gap-4">
              <button
                type="button"
                @click="enterPreviewMode"
                :disabled="enteringPreview"
                class="min-w-[120px] px-5 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors disabled:opacity-50 text-center"
              >
                Try the demo
              </button>
              <router-link
                to="/login"
                class="inline-flex items-center justify-center min-w-[120px] px-5 py-2.5 bg-savannah-500 text-white text-sm font-semibold rounded-lg hover:bg-savannah-600 transition-colors"
              >
                Sign in
              </router-link>
            </div>
          </div>

          <!-- Right: Financial Freedom Wheel -->
          <div class="mt-10 lg:mt-0 lg:w-[28rem] flex items-center justify-center">
            <img
              :src="'/images/financial-freedom-wheel.png'"
              alt="Financial Freedom - Optimise your Tax, Invest Wisely, Plan for Retirement, Establish Passive Income, Reduce Debt, Build Savings"
              class="w-full max-w-md"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- How Fyn Can Help You Section -->
    <div id="features" class="bg-horizon-500 py-16 lg:py-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white text-center mb-16">
          How Fyn can help you
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Protection Module -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/10 hover:bg-white/15 transition-colors">
            <div class="w-12 h-12 bg-spring-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-spring-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Protection</h3>
            <p class="text-sm text-white/70 mb-4">Analyse life insurance, critical illness, and income protection coverage gaps.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Life Cover</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Income Protection</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Adequacy Scoring</span>
            </div>
          </div>

          <!-- Savings Module -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/10 hover:bg-white/15 transition-colors">
            <div class="w-12 h-12 bg-light-blue-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-light-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Savings</h3>
            <p class="text-sm text-white/70 mb-4">Track emergency funds, ISA allowances, and savings goals across all accounts.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Emergency Fund</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">ISA Tracking</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Goal Planning</span>
            </div>
          </div>

          <!-- Investment Module -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/10 hover:bg-white/15 transition-colors">
            <div class="w-12 h-12 bg-violet-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Investment</h3>
            <p class="text-sm text-white/70 mb-4">Portfolio analysis, risk profiling, and Monte Carlo retirement projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Risk Profile</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Portfolio View</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Monte Carlo</span>
            </div>
          </div>

          <!-- Retirement Module -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/10 hover:bg-white/15 transition-colors">
            <div class="w-12 h-12 bg-raspberry-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-raspberry-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Retirement</h3>
            <p class="text-sm text-white/70 mb-4">Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Defined Contribution Pensions</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Defined Benefit Pensions</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">State Pension</span>
            </div>
          </div>

          <!-- Estate Module -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/10 hover:bg-white/15 transition-colors">
            <div class="w-12 h-12 bg-raspberry-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-raspberry-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Estate</h3>
            <p class="text-sm text-white/70 mb-4">Inheritance Tax calculations, gifting strategies, and estate value projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Inheritance Tax Calculator</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Gifting</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Nil Rate Band / Residence Nil Rate Band</span>
            </div>
          </div>

          <!-- Net Worth Module -->
          <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/10 hover:bg-white/15 transition-colors">
            <div class="w-12 h-12 bg-violet-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Net Worth</h3>
            <p class="text-sm text-white/70 mb-4">Complete balance sheet with properties, assets, and liabilities tracking.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Properties</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Liabilities</span>
              <span class="px-3 py-1 bg-white/10 text-white/80 text-xs font-medium rounded-full">Balance Sheet</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Your Fynla Dashboard Section -->
    <div class="bg-savannah-500 py-16 lg:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white text-center mb-12">
          Your Fynla dashboard
        </h2>
        <div class="rounded-2xl overflow-hidden border border-neutral-200 shadow-xl bg-white">
          <div class="flex items-center gap-2 px-4 py-2.5 bg-neutral-100 border-b border-neutral-200">
            <span class="w-3 h-3 rounded-full bg-raspberry-500"></span>
            <span class="w-3 h-3 rounded-full bg-violet-500"></span>
            <span class="w-3 h-3 rounded-full bg-spring-500"></span>
            <span class="ml-3 text-xs text-neutral-500 font-mono">fynla.org</span>
          </div>
          <img
            :src="dashboardGifUrl"
            alt="Fynla dashboard walkthrough showing net worth, pensions, investments, protection, and estate planning modules"
            class="w-full h-auto block"
          />
        </div>
      </div>
    </div>

    <!-- Persona Selection Modal -->
    <PersonaSelectionModal
      :is-open="showSelectionModal"
      :personas="availablePersonas"
      :error="previewError"
      @close="cancelPreview"
      @select="handlePersonaSelect"
    />
  </PublicLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import { nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PersonaSelectionModal from '@/components/Preview/PersonaSelectionModal.vue';

export default {
  name: 'LandingPage',

  components: {
    PublicLayout,
    PersonaSelectionModal,
  },

  data() {
    return {
      enteringPreview: false,
      showSelectionModal: false,
      previewError: '',
      chatInput: '',
      dashboardGifUrl: '/images/fynla-dashboard-walkthrough.gif',
    };
  },

  computed: {
    ...mapGetters('preview', ['availablePersonas']),
  },

  mounted() {
    this.checkDemoParam();
  },

  watch: {
    '$route.query.demo': {
      handler(newVal) {
        if (newVal === 'true') {
          this.checkDemoParam();
        }
      },
    },
  },

  methods: {
    ...mapActions('preview', ['loadPersona']),

    checkDemoParam() {
      if (this.$route.query.demo === 'true') {
        this.showSelectionModal = true;
        this.$router.replace({ path: '/', query: {} });
      }
    },

    scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    },

    handleAskFyn() {
      // For now, trigger demo mode - future: AI chatbot integration
      this.enterPreviewMode();
    },

    enterPreviewMode() {
      this.previewError = '';
      this.showSelectionModal = true;
    },

    cancelPreview() {
      this.showSelectionModal = false;
      this.previewError = '';
    },

    async handlePersonaSelect(persona) {
      if (this.enteringPreview) return;
      this.enteringPreview = true;
      this.previewError = '';

      try {
        await this.$store.dispatch('preview/loadPersona', persona.id);
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        console.error('Failed to enter preview mode:', error);
        this.previewError = 'Unable to load demo. Please try again or check your connection.';
        this.enteringPreview = false;
      }
    },
  },
};
</script>

<style scoped>
/* Hero gradient: dark horizon blue on left to raspberry pink on right */
.hero-gradient {
  background: linear-gradient(to right, #1F2A44 0%, #1F2A44 25%, #E83E6D 100%);
}

/* Large decorative display font for "Meet Fyn" */
.hero-title {
  font-family: 'Segoe UI', 'Inter', sans-serif;
  font-size: 5rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  line-height: 1;
}

@media (min-width: 768px) {
  .hero-title {
    font-size: 7rem;
  }
}

@media (min-width: 1024px) {
  .hero-title {
    font-size: 8rem;
  }
}

/* Flowing italic subtitle */
.hero-subtitle {
  font-family: 'Segoe UI', 'Inter', sans-serif;
  font-size: 1.5rem;
  font-style: italic;
  font-weight: 300;
}

@media (min-width: 768px) {
  .hero-subtitle {
    font-size: 1.875rem;
  }
}
</style>
