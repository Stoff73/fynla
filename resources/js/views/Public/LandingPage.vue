<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-10">
          <!-- Left: Fyn character placeholder -->
          <div class="hidden lg:flex lg:w-48 lg:flex-shrink-0 items-start justify-center pt-2">
            <div class="w-44 h-56 rounded-lg border-2 border-dashed border-white/30 flex items-center justify-center">
              <span class="text-white/60 italic text-sm">Fyn</span>
            </div>
          </div>

          <!-- Right: Content -->
          <div class="flex-1">
            <h1 class="text-5xl md:text-6xl font-black text-white leading-none mb-2">Meet Fyn</h1>
            <p class="text-lg md:text-xl font-semibold text-white/90 italic mb-3">
              Your financial companion for life
            </p>
            <p class="text-sm text-white/70 mb-6 max-w-xl leading-relaxed">
              Fyn will help you meet your financial goals by giving you clarity of your finances from planning to saving and investments, through to your net worth and real estate
            </p>

            <!-- Ask Fyn input -->
            <div class="flex flex-col sm:flex-row gap-3 max-w-xl">
              <input
                v-model="chatInput"
                type="text"
                placeholder="Enter your text here"
                class="input-field flex-1"
                @keyup.enter="handleAskFyn"
              />
              <button type="button" @click="handleAskFyn" class="px-8 py-2 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap">
                Ask Fyn
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Map Your Path to Financial Freedom -->
    <div class="bg-white py-12 lg:py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-16">
          <div class="lg:flex-1">
            <h2 class="leading-tight mb-6">
              Map your path to<br />
              <span class="text-raspberry-500">financial freedom</span>
            </h2>
            <p class="text-neutral-500 mb-6 max-w-lg leading-relaxed">
              We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;, which will leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
            </p>

            <!-- Feature indicators -->
            <div class="flex flex-wrap gap-3 mb-8">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <span class="w-2.5 h-2.5 rounded-full bg-raspberry-500"></span> Protection
              </span>
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <span class="w-2.5 h-2.5 rounded-full bg-spring-500"></span> Savings
              </span>
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <span class="w-2.5 h-2.5 rounded-full bg-violet-500"></span> Investment
              </span>
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <span class="w-2.5 h-2.5 rounded-full bg-light-blue-500"></span> Retirement
              </span>
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <span class="w-2.5 h-2.5 rounded-full bg-savannah-500"></span> Estate
              </span>
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <span class="w-2.5 h-2.5 rounded-full bg-horizon-500"></span> Net Worth
              </span>
            </div>

            <div class="flex flex-col items-start gap-4">
              <router-link to="/register" class="px-8 py-2.5 bg-horizon-500 text-white rounded-button font-medium hover:bg-horizon-600 transition-colors">Sign up</router-link>
              <p class="text-sm text-neutral-500">
                Not convinced yet, check out
                <a href="/?demo=true" class="text-horizon-500 underline hover:text-raspberry-500 transition-colors" @click.prevent="enterPreviewMode">our demo</a>
              </p>
            </div>
          </div>

          <div class="mt-10 lg:mt-0 lg:w-[28rem] flex items-center justify-center">
            <img
              src="/images/financial-freedom-wheel.png"
              alt="Financial Freedom - Optimise your Tax, Invest Wisely, Plan for Retirement, Establish Passive Income, Reduce Debt, Build Savings"
              class="w-full max-w-md opacity-60"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- How Fyn Can Help You -->
    <div id="features" class="bg-eggshell-500 py-16 lg:py-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center mb-12">How Fyn can help you</h2>

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
          <a href="/?demo=true" class="text-horizon-500 font-medium hover:text-raspberry-500 transition-colors" @click.prevent="enterPreviewMode">
            View demos &gt;
          </a>
        </div>
      </div>
    </div>

    <!-- Your Fynla Dashboard -->
    <div class="bg-white py-16 lg:py-20">
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
    <div id="solutions" class="bg-eggshell-500 pt-16 lg:pt-20 pb-24 lg:pb-28">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center mb-12">Solutions built just for you</h2>

        <div class="flex flex-wrap justify-center gap-8 lg:gap-10 mb-10">
          <div class="flex flex-col items-center text-center max-w-[140px] cursor-pointer group" @click="enterPreviewMode">
            <div class="w-14 h-14 rounded-lg bg-horizon-500 flex items-center justify-center mb-3 group-hover:bg-horizon-600 transition-colors">
              <span class="text-white text-xs font-bold leading-tight">F</span>
            </div>
            <p class="text-xs font-bold text-horizon-500 tracking-wider">FYNLA</p>
            <p class="text-sm font-bold text-horizon-500 mb-1">INVESTOR</p>
            <p class="text-xs text-neutral-500 leading-relaxed">Portfolio analysis, risk profiling, and investment strategy tools.</p>
          </div>
          <div class="flex flex-col items-center text-center max-w-[140px] cursor-pointer group" @click="enterPreviewMode">
            <div class="w-14 h-14 rounded-lg bg-horizon-500 flex items-center justify-center mb-3 group-hover:bg-horizon-600 transition-colors">
              <span class="text-white text-xs font-bold leading-tight">F</span>
            </div>
            <p class="text-xs font-bold text-horizon-500 tracking-wider">FYNLA</p>
            <p class="text-sm font-bold text-horizon-500 mb-1">LIFE</p>
            <p class="text-xs text-neutral-500 leading-relaxed">Protection, critical illness, and income cover analysis for your family.</p>
          </div>
          <div class="flex flex-col items-center text-center max-w-[140px] cursor-pointer group" @click="enterPreviewMode">
            <div class="w-14 h-14 rounded-lg bg-horizon-500 flex items-center justify-center mb-3 group-hover:bg-horizon-600 transition-colors">
              <span class="text-white text-xs font-bold leading-tight">F</span>
            </div>
            <p class="text-xs font-bold text-horizon-500 tracking-wider">FYNLA</p>
            <p class="text-sm font-bold text-horizon-500 mb-1">MANAGER</p>
            <p class="text-xs text-neutral-500 leading-relaxed">Net worth tracking, savings goals, and financial oversight tools.</p>
          </div>
          <div class="flex flex-col items-center text-center max-w-[140px] cursor-pointer group" @click="enterPreviewMode">
            <div class="w-14 h-14 rounded-lg bg-horizon-500 flex items-center justify-center mb-3 group-hover:bg-horizon-600 transition-colors">
              <span class="text-white text-xs font-bold leading-tight">F</span>
            </div>
            <p class="text-xs font-bold text-horizon-500 tracking-wider">FYNLA</p>
            <p class="text-sm font-bold text-horizon-500 mb-1">PLANNER</p>
            <p class="text-xs text-neutral-500 leading-relaxed">Retirement projections, pension tracking, and estate planning.</p>
          </div>
          <div class="flex flex-col items-center text-center max-w-[140px] cursor-pointer group" @click="enterPreviewMode">
            <div class="w-14 h-14 rounded-lg bg-horizon-500 flex items-center justify-center mb-3 group-hover:bg-horizon-600 transition-colors">
              <span class="text-white text-xs font-bold leading-tight">F</span>
            </div>
            <p class="text-xs font-bold text-horizon-500 tracking-wider">FYNLA</p>
            <p class="text-sm font-bold text-horizon-500 mb-1">SAVER</p>
            <p class="text-xs text-neutral-500 leading-relaxed">Emergency funds, ISA allowances, and savings goal tracking.</p>
          </div>
        </div>

        <div class="text-center">
          <button type="button" @click="enterPreviewMode" :disabled="enteringPreview" class="btn-primary px-8">
            View demo
          </button>
        </div>
      </div>
    </div>

    <!-- Stats Bar - Straddles solutions section and footer -->
    <div class="relative z-10 -mt-14 -mb-12">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="card-lg flex flex-col sm:flex-row items-center justify-around gap-6">
          <div class="text-center">
            <div class="text-4xl font-black text-horizon-500">123</div>
            <div class="text-sm font-semibold text-neutral-500 mt-1">Regulations</div>
          </div>
          <div class="hidden sm:block w-px h-12 bg-light-gray"></div>
          <div class="text-center">
            <div class="text-4xl font-black text-horizon-500">16</div>
            <div class="text-sm font-semibold text-neutral-500 mt-1">Financial Tools</div>
          </div>
          <div class="hidden sm:block w-px h-12 bg-light-gray"></div>
          <div class="text-center">
            <div class="text-4xl font-black text-horizon-500">12</div>
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
