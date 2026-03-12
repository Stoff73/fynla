<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-10">
          <!-- Left: Fyn character placeholder -->
          <div class="hidden lg:flex lg:w-48 lg:flex-shrink-0 items-start justify-center pt-2">
            <div class="w-44 h-56 rounded-lg border-2 border-dashed border-horizon-200 flex items-center justify-center">
              <span class="text-neutral-500 italic text-sm">Fyn</span>
            </div>
          </div>

          <!-- Right: Content -->
          <div class="flex-1">
            <h1 class="text-5xl md:text-6xl font-black text-horizon-500 leading-none mb-2">Meet Fyn</h1>
            <p class="text-lg md:text-xl font-semibold text-horizon-500 mb-3">
              Your financial companion for life
            </p>
            <p class="text-sm text-neutral-500 mb-6 max-w-xl leading-relaxed">
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
              <button type="button" @click="handleAskFyn" class="btn-primary whitespace-nowrap px-8">
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
            <p class="text-neutral-500 mb-8 max-w-lg leading-relaxed">
              We simplify your path to financial freedom by creating clarity through our proprietary Fynla Brain&reg;, which will leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
            </p>
            <div class="flex flex-col items-start gap-4">
              <router-link to="/register" class="btn-primary px-8">Sign up</router-link>
              <p class="text-sm text-neutral-500">
                Not ready yet, check out
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
    <div id="features" class="bg-horizon-500 py-16 lg:py-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative mb-12">
          <h2 class="text-white text-center">How Fyn can help you</h2>
          <button type="button" @click="handleAskFyn" class="btn-secondary hidden md:inline-flex absolute right-0 top-1/2 -translate-y-1/2">
            Ask Fyn
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="card">
            <h3 class="mb-2">Protection</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.</p>
          </div>
          <div class="card">
            <h3 class="mb-2">Savings</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.</p>
          </div>
          <div class="card">
            <h3 class="mb-2">Investment</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.</p>
          </div>
          <div class="card">
            <h3 class="mb-2">Retirement</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.</p>
          </div>
          <div class="card">
            <h3 class="mb-2">Estate</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.</p>
          </div>
          <div class="card">
            <h3 class="mb-2">Net Worth</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.</p>
          </div>
        </div>

        <div class="text-center mt-10">
          <a href="/?demo=true" class="text-white font-medium hover:text-spring-300 transition-colors" @click.prevent="enterPreviewMode">
            View demo &gt;
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
        <div class="flex items-center justify-between mb-12">
          <h2>Solutions built just for you</h2>
          <button type="button" @click="enterPreviewMode" :disabled="enteringPreview" class="btn-primary hidden md:inline-flex">
            View demo
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="card-hover" @click="enterPreviewMode">
            <h3 class="mb-2">Individuals &gt;</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Comprehensive financial planning tools for individuals looking to take control of their financial future.</p>
          </div>
          <div class="card-hover" @click="enterPreviewMode">
            <h3 class="mb-2">Families &gt;</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Joint planning tools for couples and families, with spousal optimisations and household net worth tracking.</p>
          </div>
          <div class="card-hover" @click="enterPreviewMode">
            <h3 class="mb-2">Young Savers &gt;</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Get started early with budgeting, emergency funds, and ISA tracking designed for those beginning their financial journey.</p>
          </div>
          <div class="card-hover" @click="enterPreviewMode">
            <h3 class="mb-2">Retirees &gt;</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Decumulation planning, pension drawdown strategies, and estate planning for those in or approaching retirement.</p>
          </div>
          <div class="card-hover" @click="enterPreviewMode">
            <h3 class="mb-2">Business Owners &gt;</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Business interest valuations, SIPP planning, and tax-efficient strategies for entrepreneurs and directors.</p>
          </div>
          <div class="card-hover" @click="enterPreviewMode">
            <h3 class="mb-2">Estate Planners &gt;</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">Inheritance Tax calculations, trust management, and gifting strategies to protect your legacy.</p>
          </div>
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
