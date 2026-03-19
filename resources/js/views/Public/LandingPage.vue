<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">

        <!-- Heading -->
        <h2 class="text-4xl sm:text-5xl lg:text-8xl leading-tight mb-3 text-white">
          Create your own<br />
          <span class="text-raspberry-300">financial freedom</span>
        </h2>

        <!-- Body paragraph -->
        <p class="text-white/80 mb-4 max-w-2xl leading-relaxed">
          We help create your path to financial freedom<br />
          with clear recommendations from our proprietary Fynla Brain&reg;
        </p>

        <!-- CTA -->
        <div class="flex flex-col items-start gap-3 mb-8">
          <router-link to="/register" class="px-12 py-2.5 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-all">Sign up</router-link>
          <p class="text-sm text-white/70 flex flex-wrap items-center gap-2">
            <a href="#dashboard" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="scrollToDashboard">View the video</a>
            <span class="text-white/40">|</span>
            <a href="/?demo=true" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="enterPreviewMode">See our demo</a>
            <span class="text-white/40">|</span>
            <a href="#meet-fyn" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="scrollToMeetFyn">Ask Fyn</a>
          </p>
        </div>

        <!-- Mobile: single card (shown below lg, no overlap on mobile) -->
        <div class="flex justify-center lg:hidden">
          <img
            src="/images/Website/Homepage-Header-Mobile.png"
            alt="Fynla Brain — your financial planning intelligence"
            class="w-full max-w-sm"
          />
        </div>

        <!-- Desktop: three-panel composite as background with caption cards on top -->
        <div
          class="relative hidden lg:block bg-cover bg-top bg-no-repeat overflow-hidden"
          :style="{ backgroundImage: 'url(/images/Website/Homepage-Header-Desktop.png)' }"
          style="aspect-ratio: 1315 / 340;"
        >
          <!-- Caption cards — positioned at top of the image -->
          <div class="absolute inset-x-0 top-0 grid grid-cols-3 gap-6 px-4 pt-4">
            <div class="bg-white/95 rounded-lg px-4 py-3 shadow-sm">
              <p class="text-sm font-bold text-horizon-500 mb-1">One financial view</p>
              <p class="text-xs text-neutral-500 leading-relaxed">Use Fynla to securely centralise and view all your financial data</p>
            </div>
            <div class="bg-white/95 rounded-lg px-4 py-3 shadow-sm text-center">
              <p class="text-sm font-bold text-horizon-500 mb-1">One financial brain.</p>
              <p class="text-xs text-neutral-500 leading-relaxed">Our proprietary brain does the calculations so you don't have to</p>
            </div>
            <div class="bg-white/95 rounded-lg px-4 py-3 shadow-sm text-right">
              <p class="text-sm font-bold text-horizon-500 mb-1">One financial voice</p>
              <p class="text-xs text-neutral-500 leading-relaxed">We will give you clear and simple advice for financial freedom</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Meet Fyn Section -->
    <div id="meet-fyn" class="bg-light-pink-100 pt-6 pb-8 lg:-mt-2 lg:pt-0 lg:pb-0">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">

          <!-- Left: Meet Fyn text + Ask Fyn input -->
          <div class="flex-1 self-center">
            <h1 class="text-6xl lg:text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-4">Meet Fyn</h1>
            <p class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">
              Your financial companion for life
            </p>
            <p class="text-sm text-neutral-500 mb-4 max-w-2xl leading-relaxed">
              Need help? Fyn is the face of our Fynla brain and will help you set and meet your financial goals by giving you clarity of your finances. Fyn will help with everything from planning to saving and investments, through to your net worth and real estate. Tell Fyn what you want and he'll help you get on your way.
            </p>

            <!-- Ask Fyn input -->
            <div class="flex flex-col sm:flex-row gap-3 max-w-2xl">
              <input
                v-model="chatInput"
                type="text"
                placeholder="Enter your text here"
                class="input-field flex-1 !py-3"
                @keyup.enter="handleAskFyn"
              />
              <button type="button" @click="handleAskFyn" class="px-8 py-3 bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-colors whitespace-nowrap">
                Ask Fyn
              </button>
            </div>
          </div>

          <!-- Right: Fyn character -->
          <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-end">
            <img src="/images/Fyn/Design Character 001a.png" alt="Fyn" class="h-[427px] w-auto lg:-mb-[3em]" />
          </div>

        </div>
      </div>
    </div>

    <!-- How Fyn Can Help You -->
    <div id="features" class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-10 lg:py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center mb-4 text-white">How Fyn can help you</h2>
        <p class="text-center text-white/70 text-sm max-w-2xl mx-auto mb-10 leading-relaxed">
          We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Protection -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Protection</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Life Cover</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Income Protection</span>
            </div>
          </div>

          <!-- Savings -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-spring-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Savings</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Emergency Fund</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">ISA</span>
            </div>
          </div>

          <!-- Investment -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Investment</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Portfolio</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Risk Profile</span>
            </div>
          </div>

          <!-- Retirement -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Retirement</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Pension</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">State Pension</span>
            </div>
          </div>

          <!-- Estate -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-savannah-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Estate</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Inheritance Tax</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Trusts</span>
            </div>
          </div>

          <!-- Net Worth -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-horizon-400 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Net Worth</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Assets</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Liabilities</span>
            </div>
          </div>
        </div>

        <div class="text-center mt-10">
          <a href="/?demo=true" class="text-white font-medium hover:text-white/70 transition-colors" @click.prevent="enterPreviewMode">
            View demos &gt;
          </a>
        </div>
      </div>
    </div>

    <!-- Your Fynla Dashboard -->
    <div id="dashboard" class="bg-eggshell-500 py-10 lg:py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center mb-12">Your Fynla dashboard</h2>
        <div class="card-lg overflow-hidden p-0">
          <div class="flex items-center gap-2 px-4 py-2.5 bg-savannah-100 border-b border-light-gray">
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

    <!-- Solutions Built Just For You -->
    <div id="solutions" class="bg-light-pink-100 pt-10 lg:pt-12 pb-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center mb-12">Solutions</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
          <!-- Fynla Investor -->
          <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
            <div class="w-16 h-16 rounded-lg bg-horizon-500 flex items-center justify-center mb-4">
              <span class="w-8 h-8 rounded bg-white/20 block"></span>
            </div>
            <p class="text-sm font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
            <p class="text-base font-bold text-white mb-2">INVESTOR</p>
            <p class="text-xs text-white/70 leading-relaxed">Portfolio analysis, risk profiling, and investment strategy tools.</p>
          </div>

          <!-- Fynla Life -->
          <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
            <div class="w-16 h-16 rounded-lg bg-raspberry-500 flex items-center justify-center mb-4">
              <span class="w-8 h-8 rounded bg-white/20 block"></span>
            </div>
            <p class="text-sm font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
            <p class="text-base font-bold text-white mb-2">LIFE</p>
            <p class="text-xs text-white/70 leading-relaxed">Protection, critical illness, and income cover analysis for your family.</p>
          </div>

          <!-- Fynla Manager -->
          <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
            <div class="w-16 h-16 rounded-lg bg-spring-500 flex items-center justify-center mb-4">
              <span class="w-8 h-8 rounded bg-white/20 block"></span>
            </div>
            <p class="text-sm font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
            <p class="text-base font-bold text-white mb-2">MANAGER</p>
            <p class="text-xs text-white/70 leading-relaxed">Net worth tracking, savings goals, and financial oversight tools.</p>
          </div>

          <!-- Fynla Planner -->
          <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
            <div class="w-16 h-16 rounded-lg bg-violet-500 flex items-center justify-center mb-4">
              <span class="w-8 h-8 rounded bg-white/20 block"></span>
            </div>
            <p class="text-sm font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
            <p class="text-base font-bold text-white mb-2">PLANNER</p>
            <p class="text-xs text-white/70 leading-relaxed">Retirement projections, pension tracking, and estate planning.</p>
          </div>

          <!-- Fynla Saver -->
          <div class="bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-6 flex flex-col items-start cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200" @click="enterPreviewMode">
            <div class="w-16 h-16 rounded-lg bg-savannah-500 flex items-center justify-center mb-4">
              <span class="w-8 h-8 rounded bg-white/20 block"></span>
            </div>
            <p class="text-sm font-bold text-white/60 tracking-wider mb-0.5">FYNLA</p>
            <p class="text-base font-bold text-white mb-2">SAVER</p>
            <p class="text-xs text-white/70 leading-relaxed">Emergency funds, ISA allowances, and savings goal tracking.</p>
          </div>
        </div>

        <div class="text-center">
          <button type="button" @click="enterPreviewMode" :disabled="enteringPreview" class="px-8 py-2 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 active:bg-spring-700 transition-all duration-150 shadow-sm hover:shadow-md">
            View demo
          </button>
        </div>

      </div>
    </div>

    <!-- Stats Bar - Straddles solutions section and footer -->
    <div class="relative z-10 -mt-14 -mb-20">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="card-lg flex flex-col sm:flex-row items-center justify-around gap-6">
          <div class="text-center">
            <div class="text-4xl font-bold text-horizon-500">123</div>
            <div class="text-sm font-semibold text-neutral-500 mt-1">Regulations</div>
          </div>
          <div class="hidden sm:block w-px h-12 bg-light-gray"></div>
          <div class="text-center">
            <div class="text-4xl font-bold text-horizon-500">16</div>
            <div class="text-sm font-semibold text-neutral-500 mt-1">Financial Tools</div>
          </div>
          <div class="hidden sm:block w-px h-12 bg-light-gray"></div>
          <div class="text-center">
            <div class="text-4xl font-bold text-horizon-500">12</div>
            <div class="text-sm font-semibold text-neutral-500 mt-1">Accreditations</div>
          </div>
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

    handleAskFyn() {
      this.enterPreviewMode();
    },

    scrollToMeetFyn() {
      document.getElementById('meet-fyn').scrollIntoView({ behavior: 'smooth' });
    },

    scrollToDashboard() {
      document.getElementById('dashboard').scrollIntoView({ behavior: 'smooth' });
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
