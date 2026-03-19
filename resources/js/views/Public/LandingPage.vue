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

    <!-- Find Your Stage Section -->
    <div class="bg-white py-12 lg:py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="leading-tight mb-3">
            Where are you in your<br />
            <span class="text-raspberry-500">financial journey?</span>
          </h2>
          <p class="text-neutral-500 max-w-2xl mx-auto leading-relaxed">
            Choose the stage that best describes where you are right now, and explore a personalised demo experience tailored to your situation.
          </p>
        </div>

        <!-- Life Stage Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-10">
          <button
            v-for="stage in stages"
            :key="stage.id"
            type="button"
            class="card group text-left transition-all duration-200 hover:shadow-md hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 cursor-pointer"
            :class="stageCardBorderClass(stage)"
            @click="handleStageCardClick(stage)"
          >
            <!-- Stage Label -->
            <h3 class="text-base font-bold text-horizon-500 text-center mb-1 mt-2">
              {{ stage.label }}
            </h3>

            <!-- Tagline -->
            <p class="text-xs text-neutral-500 text-center leading-relaxed">
              {{ stage.tagline }}
            </p>

            <!-- See Demo Hint -->
            <div class="mt-3 text-center">
              <span class="inline-flex items-center text-xs font-semibold transition-colors" :class="stageTextColourClass(stage)">
                See it in action
                <svg class="w-3 h-3 ml-1 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </span>
            </div>
          </button>
        </div>

        <!-- Divider -->
        <div class="relative mb-8">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-light-gray"></div>
          </div>
          <div class="relative flex justify-center">
            <span class="bg-white px-4 text-sm text-neutral-500">or get started</span>
          </div>
        </div>

        <div class="flex flex-col items-center gap-4">
          <router-link to="/register" class="px-8 py-2.5 bg-raspberry-500 text-white rounded-button font-medium hover:bg-raspberry-600 transition-colors">
            Create Your Account
          </router-link>
          <p class="text-sm text-neutral-500">
            Not convinced yet?
            <a href="/?demo=true" class="text-horizon-500 underline hover:text-raspberry-500 transition-colors" @click.prevent="enterPreviewMode">Browse all demos</a>
          </p>
        </div>
      </div>
    </div>

    <!-- Map Your Path to Financial Freedom -->
    <div class="bg-savannah-100 py-12 lg:py-16">
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
import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';

export default {
  name: 'LandingPage',

  components: {
    PublicLayout,
    PersonaSelectionModal,
    // Inline icon components (same as FocusAreaSelection.vue)
    IconGraduationCap: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
      </svg>`,
    },
    IconBriefcase: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
      </svg>`,
    },
    IconShield: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>`,
    },
    IconChartLine: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
      </svg>`,
    },
    IconSun: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
      </svg>`,
    },
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

    stages() {
      return STAGE_ORDER.map(id => LIFE_STAGES[id]);
    },
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

    /**
     * Handle clicking a life stage card on the landing page.
     * Finds the persona mapped to that stage and enters preview mode directly.
     */
    async handleStageCardClick(stage) {
      if (this.enteringPreview) return;

      // Find the persona ID for this stage
      const personaId = stage.persona;
      if (!personaId) {
        // Fallback: open the persona selection modal
        this.enterPreviewMode();
        return;
      }

      this.enteringPreview = true;
      this.previewError = '';

      try {
        await this.$store.dispatch('preview/loadPersona', personaId);
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        console.error('Failed to enter preview mode for stage:', stage.id, error);
        this.previewError = 'Unable to load demo. Please try again or check your connection.';
        this.enteringPreview = false;
        // Show the modal so user can retry or pick manually
        this.showSelectionModal = true;
      }
    },

    stageCardBorderClass(stage) {
      const map = {
        violet: 'hover:border-violet-400',
        spring: 'hover:border-spring-400',
        raspberry: 'hover:border-raspberry-400',
        'light-blue': 'hover:border-light-blue-500',
        horizon: 'hover:border-horizon-400',
      };
      return map[stage.colour] || 'hover:border-violet-400';
    },

    stageIconBgClass(stage) {
      const map = {
        violet: 'bg-gradient-to-br from-violet-400 to-violet-600',
        spring: 'bg-gradient-to-br from-spring-400 to-spring-600',
        raspberry: 'bg-gradient-to-br from-raspberry-400 to-raspberry-600',
        'light-blue': 'bg-gradient-to-br from-light-blue-500 to-horizon-400',
        horizon: 'bg-gradient-to-br from-horizon-400 to-horizon-600',
      };
      return map[stage.colour] || 'bg-gradient-to-br from-violet-400 to-violet-600';
    },

    stageTextColourClass(stage) {
      const map = {
        violet: 'text-violet-500',
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[stage.colour] || 'text-violet-500';
    },

    stageIconComponent(stage) {
      const icons = {
        'graduation-cap': 'IconGraduationCap',
        'briefcase': 'IconBriefcase',
        'shield': 'IconShield',
        'chart-line': 'IconChartLine',
        'sun': 'IconSun',
      };
      return icons[stage.icon] || 'IconGraduationCap';
    },
  },
};
</script>
