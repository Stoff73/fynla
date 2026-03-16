<template>
  <div v-if="areasToComplete.length > 0">
    <!-- Header -->
    <h3 class="text-lg font-semibold text-horizon-500 mb-1">Areas to Complete</h3>
    <p class="text-xs text-neutral-500 mb-4">These areas were skipped during setup</p>

    <!-- Areas List -->
    <div class="space-y-2">
      <div
        v-for="area in areasToComplete"
        :key="area.id"
        class="flex items-center justify-between py-2 cursor-pointer hover:bg-savannah-100 -mx-2 px-2 rounded transition-colors"
        @click="navigateTo(area.route)"
      >
        <div class="flex items-center gap-3 min-w-0">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
            :class="area.iconBgClass"
          >
            <!-- User Icon -->
            <svg v-if="area.icon === 'user'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <!-- Users Icon -->
            <svg v-else-if="area.icon === 'users'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <!-- Currency Icon -->
            <svg v-else-if="area.icon === 'currency'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Cash Icon -->
            <svg v-else-if="area.icon === 'cash'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <!-- Globe Icon -->
            <svg v-else-if="area.icon === 'globe'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Shield Icon -->
            <svg v-else-if="area.icon === 'shield'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <!-- Home Icon -->
            <svg v-else-if="area.icon === 'home'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <!-- Credit Card Icon -->
            <svg v-else-if="area.icon === 'credit-card'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            <!-- Document Icon -->
            <svg v-else-if="area.icon === 'document'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <!-- Lock Icon -->
            <svg v-else-if="area.icon === 'lock'" class="w-4 h-4" :class="area.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <div class="min-w-0">
            <span class="text-sm font-medium text-horizon-500 block truncate">{{ area.title }}</span>
            <span class="text-xs text-neutral-500 block truncate">{{ area.description }}</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-horizon-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </div>
    </div>

    <!-- Complete Setup link -->
    <div class="mt-4 pt-3 border-t border-light-gray text-center">
      <router-link
        to="/onboarding"
        class="text-sm font-medium text-raspberry-500 hover:text-raspberry-700 transition-colors"
      >
        Complete Setup in Onboarding
      </router-link>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'AreasToCompleteCard',

  computed: {
    ...mapGetters('auth', ['currentUser']),

    skippedSteps() {
      return this.currentUser?.onboarding_skipped_steps || [];
    },

    areasToComplete() {
      if (!this.skippedSteps.length) return [];

      const stepConfig = {
        personal_info: {
          title: 'Personal Information',
          description: 'Date of birth, address, and contact details',
          route: '/valuable-info?section=personal',
          icon: 'user',
          iconBgClass: 'bg-violet-100',
          iconClass: 'text-violet-600',
        },
        family_info: {
          title: 'Family Information',
          description: 'Spouse, dependants, and family members',
          route: '/valuable-info?section=family',
          icon: 'users',
          iconBgClass: 'bg-purple-100',
          iconClass: 'text-purple-600',
        },
        income: {
          title: 'Income Details',
          description: 'Employment, salary, and other income',
          route: '/valuable-info?section=income',
          icon: 'currency',
          iconBgClass: 'bg-teal-100',
          iconClass: 'text-teal-600',
        },
        expenditure: {
          title: 'Expenditure',
          description: 'Monthly outgoings and spending',
          route: '/valuable-info?section=expenditure',
          icon: 'cash',
          iconBgClass: 'bg-sky-100',
          iconClass: 'text-sky-600',
        },
        domicile_info: {
          title: 'Domicile Information',
          description: 'Tax residency and domicile status',
          route: '/valuable-info?section=domicile',
          icon: 'globe',
          iconBgClass: 'bg-indigo-100',
          iconClass: 'text-indigo-600',
        },
        protection_policies: {
          title: 'Protection Policies',
          description: 'Life, critical illness, and income protection',
          route: '/protection',
          icon: 'shield',
          iconBgClass: 'bg-spring-100',
          iconClass: 'text-spring-600',
        },
        assets: {
          title: 'Assets',
          description: 'Properties, investments, and savings',
          route: '/net-worth',
          icon: 'home',
          iconBgClass: 'bg-emerald-100',
          iconClass: 'text-emerald-600',
        },
        liabilities: {
          title: 'Liabilities',
          description: 'Loans, credit cards, and debts',
          route: '/net-worth',
          icon: 'credit-card',
          iconBgClass: 'bg-raspberry-100',
          iconClass: 'text-raspberry-600',
        },
        will_info: {
          title: 'Will Information',
          description: 'Your will and executor details',
          route: '/estate/will-builder',
          icon: 'document',
          iconBgClass: 'bg-savannah-100',
          iconClass: 'text-neutral-500',
        },
        trust_info: {
          title: 'Trust Information',
          description: 'Trusts and their arrangements',
          route: '/estate',
          icon: 'lock',
          iconBgClass: 'bg-slate-100',
          iconClass: 'text-slate-600',
        },
      };

      return this.skippedSteps
        .filter(step => stepConfig[step])
        .map(step => ({
          id: step,
          ...stepConfig[step],
        }));
    },
  },

  methods: {
    navigateTo(route) {
      this.$router.push(route);
    },
  },
};
</script>
