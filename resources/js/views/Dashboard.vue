<template>
  <AppLayout>
    <!-- Journey blur overlay (desktop only, from Quick Start with Fyn registration) -->
    <div
      v-if="journeyBlurActive"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30 hidden lg:block"
    ></div>

    <div class="py-2 sm:py-3">
      <!-- 2FA Security Reminder Notification -->
      <div
        v-if="showMFABanner"
        class="mb-6 bg-spring-100 border border-spring-200 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-spring-200 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-spring-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-spring-800">Secure your account with two-factor authentication</h3>
            <p class="mt-1 text-sm text-spring-700">
              Protect your financial data by adding an extra layer of security using an authenticator app on your phone.
            </p>
            <div class="mt-3">
              <router-link
                to="/settings/security"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-button border border-spring-300 text-spring-800 bg-white hover:bg-spring-50 transition-colors"
              >
                Enable Two-Factor Authentication →
              </router-link>
            </div>
          </div>
          <button
            @click="dismissMFABanner"
            class="flex-shrink-0 text-spring-400 hover:text-spring-600"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Investment Knowledge Nudge -->
      <div
        v-if="showKnowledgeNudge"
        class="mb-6 bg-light-pink-50 border border-light-pink-200 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-light-pink-100 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-raspberry-800">How would you describe your investment knowledge?</h3>
            <p class="mt-1 text-sm text-raspberry-700">
              This helps us tailor investment recommendations to your experience level.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button
                @click="setKnowledgeLevel('novice')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-light-pink-300 text-raspberry-700 bg-white hover:bg-light-pink-100 transition-colors"
              >
                Beginner — I'm new to investing
              </button>
              <button
                @click="setKnowledgeLevel('intermediate')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-light-pink-300 text-raspberry-700 bg-white hover:bg-light-pink-100 transition-colors"
              >
                Intermediate — I understand the basics
              </button>
              <button
                @click="setKnowledgeLevel('experienced')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-light-pink-300 text-raspberry-700 bg-white hover:bg-light-pink-100 transition-colors"
              >
                Experienced — I'm confident with investments
              </button>
            </div>
          </div>
          <button
            @click="dismissKnowledgeNudge"
            class="flex-shrink-0 text-raspberry-400 hover:text-raspberry-600"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Journey Progress Hero (shown when life stage is active, even if no financial data yet) -->
      <JourneyProgressHero
        v-if="currentStage"
        class="mb-3"
        @toggle-chat="toggleFynChat"
      />

      <!-- Empty Dashboard (no financial data) -->
      <template v-if="showEmptyDashboard">
        <div class="grid grid-cols-1 gap-3">
          <EmptyDashboard />
        </div>
      </template>

      <!-- Three-column dashboard grid -->
      <div v-else class="dashboard-grid grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <!-- Areas to Complete Card (shown first when user has skipped steps) -->
        <div v-if="hasAreasToComplete" class="bg-white rounded-lg border border-light-gray p-6">
          <AreasToCompleteCard />
        </div>

        <!-- Profile Completion Cards (shown for quick onboarding users) -->
        <ProfileCompletionCards v-if="isQuickOnboardingUser" />

        <!-- Student: Recent Activity Card (replaces Net Worth) — maps to 'budget-tracker' -->
        <DashboardCard
          v-if="isStudentPersona && isCardVisible('budget-tracker')"
          title="Recent Activity"
          :loading="false"
          @click="navigateTo('/net-worth/cash')"
        >
          <div v-if="recentTransactions.length" class="space-y-0">
            <div class="max-h-[340px] overflow-y-auto -mx-1 px-1">
              <div
                v-for="(tx, idx) in recentTransactions"
                :key="idx"
                class="flex items-center justify-between py-2.5"
                :class="{ 'border-b border-light-gray': idx < recentTransactions.length - 1 }"
              >
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-horizon-500 truncate">{{ tx.description }}</div>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-xs text-neutral-500">{{ tx.relativeDate }}</span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-savannah-100 text-neutral-500">{{ tx.account }}</span>
                  </div>
                </div>
                <div
                  class="text-sm font-semibold ml-3 whitespace-nowrap"
                  :class="tx.type === 'credit' ? 'text-spring-600' : 'text-raspberry-600'"
                >
                  {{ tx.type === 'credit' ? '+' : '' }}{{ formatCurrency(tx.amount) }}
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-4">
            <p class="text-sm text-neutral-500">No recent transactions</p>
          </div>
        </DashboardCard>

        <!-- Student: Student Debt Card — maps to 'student-loan' -->
        <DashboardCard
          v-if="isStudentPersona && isCardVisible('student-loan')"
          title="Student Debt"
          :loading="loading.netWorth"
          @click="navigateTo('/net-worth/liabilities')"
        >
          <div v-if="studentLiability" class="space-y-4">
            <div class="border-b border-light-gray pb-4">
              <span class="text-sm text-neutral-500">Outstanding Balance</span>
              <div class="mt-1">
                <span class="text-xl font-bold text-raspberry-600">
                  {{ formatCurrency(studentLiability.balance) }}
                </span>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Plan Type</span>
                <span class="font-medium text-horizon-500">{{ studentLiability.planType }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Interest Rate</span>
                <span class="font-medium text-horizon-500">{{ studentLiability.interestRate }}%</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Repayment Threshold</span>
                <span class="font-medium text-horizon-500">{{ formatCurrency(studentLoanDetails.threshold) }}/yr</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Monthly Repayments</span>
                <span class="font-medium text-spring-600">None (studying)</span>
              </div>
            </div>

            <div class="bg-light-pink-50 border border-light-pink-200 rounded-lg p-3 mt-3">
              <p class="text-xs text-raspberry-700">
                Repayments begin the April after you graduate or leave your course, but only if you earn above {{ formatCurrency(studentLoanDetails.threshold) }} per year. Your loan is written off after {{ studentLoanDetails.writeOff }} years.
              </p>
            </div>
          </div>
          <div v-else class="text-center py-4">
            <p class="text-sm text-neutral-500">No student loan data available.</p>
          </div>
        </DashboardCard>

        <!-- Net Worth Card (hidden for student persona) — maps to 'net-worth' -->
        <DashboardCard
          v-if="!isStudentPersona && isCardVisible('net-worth')"
          title="Net Worth"
          :loading="loading.netWorth"
          :empty="!hasNetWorthData"
          @click="navigateTo('/net-worth/wealth-summary')"
        >
          <div v-if="hasNetWorthData">
            <!-- Mobile: Bar chart (assets vs liabilities) -->
            <template v-if="isMobile">
              <apexchart
                :key="'nw-bar-' + netWorthChartKey"
                type="bar"
                :options="netWorthBarChartOptions"
                :series="netWorthBarChartSeries"
                height="280"
              />
              <div class="text-center text-sm mt-1">
                <span class="font-semibold" :class="netWorthData.netWorth >= 0 ? 'text-spring-600' : 'text-raspberry-600'">
                  Net Worth: {{ formatCurrency(netWorthData.netWorth) }}
                </span>
              </div>
            </template>

            <!-- Desktop: Custom SVG donut ring (V6 design — 40px stroke, gradients, rounded caps) -->
            <template v-else>
              <div class="flex justify-center" style="overflow: visible; position: relative; z-index: 1;">
                <div class="relative" style="width: 240px; height: 240px; overflow: visible;">
                  <svg viewBox="0 0 220 220" width="240" height="240" overflow="visible" @click.stop>
                    <defs>
                      <linearGradient
                        v-for="(seg, idx) in netWorthDonutSegments"
                        :key="'grad-' + idx"
                        :id="'nw-grad-' + idx"
                        x1="0%" y1="0%" x2="100%" y2="0%"
                      >
                        <stop offset="0%" :stop-color="seg.color" />
                        <stop offset="100%" :stop-color="seg.colorLight" />
                      </linearGradient>
                    </defs>
                    <circle
                      v-for="(seg, idx) in netWorthDonutSegments"
                      :key="'seg-' + idx"
                      cx="110" cy="110" r="75"
                      fill="none"
                      :stroke="'url(#nw-grad-' + idx + ')'"
                      stroke-width="40"
                      stroke-linecap="round"
                      :stroke-dasharray="seg.arcLength + ' ' + 471.2"
                      :stroke-dashoffset="-seg.offset"
                      transform="rotate(-90 110 110)"
                      class="cursor-pointer"
                      @mouseenter="nwHoveredIndex = idx; nwMouseX = $event.clientX; nwMouseY = $event.clientY"
                      @mousemove="nwMouseX = $event.clientX; nwMouseY = $event.clientY"
                      @mouseleave="nwHoveredIndex = null"
                      @click="seg.route && $router.push(seg.route)"
                    />
                  </svg>
                  <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <span
                      class="text-xl font-black"
                      :class="netWorthData.netWorth >= 0 ? 'text-spring-600' : 'text-raspberry-600'"
                    >{{ formatCurrency(netWorthData.netWorth) }}</span>
                  </div>
                </div>
              </div>
              <div class="flex justify-between text-sm mt-2">
                <div>
                  <span class="text-neutral-500">Assets</span>
                  <div class="font-semibold text-violet-600">{{ formatCurrency(netWorthData.totalAssets) }}</div>
                </div>
                <div class="text-right">
                  <span class="text-neutral-500">Liabilities</span>
                  <div class="font-semibold text-raspberry-600">{{ formatCurrency(netWorthData.totalLiabilities) }}</div>
                </div>
              </div>
            </template>
          </div>

          <!-- Empty state when no assets or liabilities -->
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No assets or liabilities added yet.</p>
            <router-link to="/net-worth/wealth-summary" class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors" @click.stop>
              Add Assets &amp; Liabilities
            </router-link>
          </div>
        </DashboardCard>

        <!-- Protection Card — maps to 'protection' -->
        <DashboardCard
          v-if="isCardVisible('protection')"
          title="Protection"
          :loading="loading.protection"
          :empty="!hasProtectionData"
          @click="navigateTo('/protection')"
        >
          <div v-if="hasProtectionData" class="space-y-4">
            <div class="border-b border-light-gray pb-4">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-light-blue-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-8 h-8 text-horizon-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                  </svg>
                </div>
                <div>
                  <span class="text-sm text-neutral-500">Total Coverage</span>
                  <div class="mt-0.5">
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-horizon-500">{{ formatCurrency(protectionData.totalCoverage) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-neutral-500">Monthly Premiums</span>
              <span class="font-medium text-horizon-500">{{ formatCurrency(protectionData.premiumTotal) }}/mo</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="font-semibold text-horizon-500">Policies</span>
              <span class="font-medium text-horizon-500">{{ protectionData.policyCount }}</span>
            </div>
            <!-- Policy list -->
            <div class="pt-3 border-t border-light-gray space-y-2">
              <div v-for="policy in protectionPolicyList" :key="policy.name" class="flex justify-between text-sm">
                <span class="text-neutral-500">{{ policy.name }}</span>
                <span class="font-medium text-horizon-500">{{ formatCurrency(policy.cover) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No protection policies added yet.</p>
            <router-link to="/protection" class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors" @click.stop>
              Add Protection Policy
            </router-link>
          </div>
        </DashboardCard>

        <!-- Cash & Savings Card — maps to 'cash-savings' / 'savings' -->
        <DashboardCard
          v-if="isCardVisible('cash-savings') || isCardVisible('savings')"
          title="Cash & Savings"
          :loading="loading.taxAllowances"
          :empty="!hasSavingsData"
          @click="navigateTo('/net-worth/cash')"
        >
          <div v-if="hasSavingsData" class="space-y-3">
            <!-- Hero metric -->
            <div class="border-b border-light-gray pb-3">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-spring-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                  </svg>
                </div>
                <div>
                  <span class="text-sm text-neutral-500">Total Savings</span>
                  <div class="mt-0.5">
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-spring-600">{{ formatCurrency(savingsTotalBalance) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Collapsible accounts -->
            <div class="border-t border-light-gray pt-2">
              <button
                class="w-full flex justify-between items-center py-1.5 text-sm"
                :aria-expanded="savingsAccountsExpanded"
                aria-controls="savings-account-list"
                @click.stop="savingsAccountsExpanded = !savingsAccountsExpanded"
              >
                <span class="font-semibold text-horizon-500">Accounts ({{ savingsAccountCount }})</span>
                <svg
                  class="w-4 h-4 text-neutral-400 transition-transform duration-200"
                  :class="{ 'rotate-180': savingsAccountsExpanded }"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="savingsAccountsExpanded"
                id="savings-account-list"
                class="space-y-2 pt-2"
              >
                <div v-for="acc in visibleSavingsAccounts" :key="acc.id" class="flex justify-between text-sm">
                  <span class="text-neutral-500 truncate mr-2">{{ acc.account_name || acc.provider }}</span>
                  <span class="font-medium text-horizon-500 whitespace-nowrap">{{ formatCurrency(acc.current_balance) }}</span>
                </div>
                <div v-if="savingsAccountCount > 5" class="text-center pt-1">
                  <router-link
                    to="/net-worth/cash"
                    class="text-xs font-semibold text-horizon-500 hover:text-horizon-600"
                    @click.stop
                  >
                    View all {{ savingsAccountCount }} accounts &rarr;
                  </router-link>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No savings accounts added yet.</p>
            <router-link to="/net-worth/cash" class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors" @click.stop>
              Add Savings Account
            </router-link>
          </div>
        </DashboardCard>

        <!-- Investment Card — maps to 'investments' -->
        <DashboardCard
          v-if="isCardVisible('investments')"
          title="Investments"
          :loading="loading.investment"
          :empty="!hasInvestmentData"
          @click="navigateTo('/net-worth/investments')"
        >
          <div v-if="hasInvestmentData" class="space-y-3">
            <!-- Hero metric -->
            <div class="border-b border-light-gray pb-3">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                </div>
                <div>
                  <span class="text-sm text-neutral-500">Portfolio Value</span>
                  <div class="mt-0.5">
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-horizon-500">{{ formatCurrency(investmentPortfolioValue) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Investment accounts bar chart -->
            <div v-if="investmentBarData.length > 0" class="investment-bar-chart">
              <apexchart
                type="bar"
                :options="investmentBarOptions"
                :series="investmentBarSeries"
                :height="120"
              />
            </div>

            <!-- Collapsible accounts -->
            <div class="border-t border-light-gray pt-2">
              <button
                class="w-full flex justify-between items-center py-1.5 text-sm"
                :aria-expanded="investmentAccountsExpanded"
                aria-controls="investment-account-list"
                @click.stop="investmentAccountsExpanded = !investmentAccountsExpanded"
              >
                <span class="font-semibold text-horizon-500">Accounts ({{ investmentAccountCount }})</span>
                <svg
                  class="w-4 h-4 text-neutral-400 transition-transform duration-200"
                  :class="{ 'rotate-180': investmentAccountsExpanded }"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="investmentAccountsExpanded"
                id="investment-account-list"
                class="space-y-2 pt-2"
              >
                <div v-for="acc in visibleInvestmentAccounts" :key="acc.id" class="flex justify-between text-sm">
                  <span class="text-neutral-500 truncate mr-2">{{ acc.account_name || acc.provider }}</span>
                  <span class="font-medium text-horizon-500 whitespace-nowrap">{{ formatCurrency(acc.current_value || acc.total_value || 0) }}</span>
                </div>
                <div v-if="investmentAccountCount > 3" class="text-center pt-1">
                  <router-link
                    to="/net-worth/investments"
                    class="text-xs font-semibold text-horizon-500 hover:text-horizon-600"
                    @click.stop
                  >
                    View all {{ investmentAccountCount }} accounts &rarr;
                  </router-link>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No investment accounts added yet.</p>
            <router-link to="/net-worth/investments" class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors" @click.stop>
              Add Investment Account
            </router-link>
          </div>
        </DashboardCard>

        <!-- Estate Planning Card — only shown when estate data exists -->
        <DashboardCard
          v-if="isCardVisible('estate') && hasEstateData"
          title="Estate Planning"
          :loading="loading.estate"
          @click="navigateTo('/estate')"
        >
          <div class="space-y-4">
            <!-- Taxable Estate Now -->
            <div class="border-b border-light-gray pb-4">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-raspberry-50 flex items-center justify-center flex-shrink-0">
                  <svg class="w-8 h-8 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                </div>
                <div>
                  <span class="text-sm text-neutral-500">Taxable Estate on Joint Death</span>
                  <div class="mt-0.5">
                    <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-raspberry-500">
                      {{ formatCurrency(estateData.taxableEstate) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Current IHT Liability -->
            <div class="border-b border-light-gray pb-4">
              <div class="text-sm font-semibold text-horizon-500 mb-2">Current Inheritance Tax Liability</div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Amount Due</span>
                <span class="font-medium text-horizon-500">{{ formatCurrency(estateData.ihtLiability) }}</span>
              </div>
            </div>

          </div>

          <!-- Will question when not yet answered -->
          <div v-if="!willAnswered" class="mt-4 pt-4 border-t border-light-gray" @click.stop>
            <div class="text-sm font-semibold text-horizon-500 mb-2">Do you currently have a valid will?</div>
            <div class="flex gap-3">
              <button
                type="button"
                class="flex-1 py-2 px-3 text-sm font-medium rounded-button border transition-colors"
                :class="willSelection === true ? 'bg-raspberry-500 text-white border-raspberry-500' : 'bg-white text-neutral-500 border-horizon-300 hover:bg-savannah-100'"
                @click="selectWill(true)"
              >
                Yes
              </button>
              <button
                type="button"
                class="flex-1 py-2 px-3 text-sm font-medium rounded-button border transition-colors"
                :class="willSelection === false ? 'bg-raspberry-500 text-white border-raspberry-500' : 'bg-white text-neutral-500 border-horizon-300 hover:bg-savannah-100'"
                @click="selectWill(false)"
              >
                No
              </button>
            </div>
            <p v-if="willSelection === false" class="mt-2 text-xs text-neutral-500">
              A valid will ensures your estate is distributed according to your wishes.
            </p>
          </div>
        </DashboardCard>


        <!-- Retirement Card — maps to 'retirement' / 'retirement-income' -->
        <DashboardCard
          v-if="isCardVisible('retirement') || isCardVisible('retirement-income')"
          :title="retirementCardTitle"
          :loading="loading.retirement"
          :empty="!hasRetirementData"
          @click="navigateTo('/net-worth/retirement')"
        >
          <template v-if="hasRetirementData">
          <!-- RETIRED USER: Show income breakdown -->
          <div v-if="isRetired" class="space-y-3">
            <!-- Income Sources Breakdown -->
            <div v-if="retiredIncomeData.pensionDrawdown > 0" class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Pension Drawdown</span>
              <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(retiredIncomeData.pensionDrawdown) }}/yr</span>
            </div>
            <div v-if="retiredIncomeData.dbPensionIncome > 0" class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Defined Benefit Pension</span>
              <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(retiredIncomeData.dbPensionIncome) }}/yr</span>
            </div>
            <div v-if="retiredIncomeData.statePensionIncome > 0" class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">State Pension</span>
              <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(retiredIncomeData.statePensionIncome) }}/yr</span>
            </div>

            <!-- Total Income -->
            <div class="flex justify-between items-center pt-3 border-t border-light-gray">
              <span class="text-sm font-medium text-horizon-500">Total Income</span>
              <span class="text-sm font-bold text-spring-600">{{ formatCurrency(retiredIncomeData.totalIncome) }}/yr</span>
            </div>

            <!-- Income Need -->
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">Income Need</span>
              <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(retirementData.targetIncome) }}/yr</span>
            </div>

            <!-- Surplus/Shortfall aligned right -->
            <div class="flex justify-between items-center">
              <span class="text-sm text-neutral-500">{{ retiredIncomeData.totalIncome >= retirementData.targetIncome ? 'Surplus' : 'Shortfall' }}</span>
              <span
                class="text-sm font-semibold"
                :class="retiredIncomeData.totalIncome >= retirementData.targetIncome ? 'text-spring-600' : 'text-violet-600'"
              >
                {{ formatCurrency(Math.abs(retiredIncomeData.totalIncome - retirementData.targetIncome)) }}/yr
              </span>
            </div>

          </div>

          <!-- NON-RETIRED USER: Show projections with progress bars -->
          <div v-else class="space-y-4">
            <!-- Income progress bar -->
            <div>
              <div class="flex justify-between items-baseline mb-2">
                <span class="text-sm font-bold text-horizon-500">Income</span>
                <div class="flex items-baseline gap-1.5">
                  <span class="text-sm font-extrabold text-spring-600">{{ formatCurrency(retirementData.projectedIncome) }}</span>
                  <span class="text-xs text-neutral-500">of {{ formatCurrency(retirementData.targetIncome) }}/yr</span>
                </div>
              </div>
              <div class="w-full rounded-full h-12 overflow-hidden" :class="retirementIncomePercent >= 100 ? 'bg-spring-100' : 'bg-light-blue-100'">
                <div
                  class="h-12 rounded-full transition-all duration-500 flex items-center justify-center px-4"
                  :class="retirementIncomePercent === 0 ? '' : (retirementIncomePercent >= 100 ? 'bg-gradient-to-r from-spring-500 to-spring-400' : 'bg-gradient-to-r from-horizon-400 to-horizon-500')"
                  :style="{ width: retirementIncomePercent > 0 ? Math.min(retirementIncomePercent, 100) + '%' : '100%' }"
                >
                  <span class="text-xs font-bold" :class="retirementIncomePercent === 0 ? 'text-horizon-500' : 'text-white'">{{ retirementIncomePercent }}%</span>
                </div>
              </div>
            </div>

            <!-- Capital progress bar -->
            <div>
              <div class="flex justify-between items-baseline mb-2">
                <span class="text-sm font-bold text-horizon-500">Capital</span>
                <div class="flex items-baseline gap-1.5">
                  <span class="text-sm font-extrabold text-spring-600">{{ formatCurrency(retirementData.projectedCapital) }}</span>
                  <span class="text-xs text-neutral-500">of {{ formatCurrency(retirementData.capitalRequired) }}</span>
                </div>
              </div>
              <div class="w-full rounded-full h-12 overflow-hidden" :class="retirementCapitalPercent >= 100 ? 'bg-spring-100' : 'bg-light-blue-100'">
                <div
                  class="h-12 rounded-full transition-all duration-500 flex items-center justify-center px-4"
                  :class="retirementCapitalPercent === 0 ? '' : (retirementCapitalPercent >= 100 ? 'bg-gradient-to-r from-spring-500 to-spring-400' : 'bg-gradient-to-r from-horizon-400 to-horizon-500')"
                  :style="{ width: retirementCapitalPercent > 0 ? Math.min(retirementCapitalPercent, 100) + '%' : '100%' }"
                >
                  <span class="text-xs font-bold" :class="retirementCapitalPercent === 0 ? 'text-horizon-500' : 'text-white'">{{ retirementCapitalPercent }}%</span>
                </div>
              </div>
            </div>

            <!-- Retirement age and years -->
            <div class="flex justify-between">
              <div v-if="retirementData.retirementAge" class="text-center">
                <span class="text-sm text-neutral-500">Retirement Age</span>
                <div class="text-base font-semibold text-horizon-500">{{ retirementData.retirementAge }}</div>
              </div>
              <div v-if="retirementData.yearsToRetirement !== null" class="text-center">
                <span class="text-sm text-neutral-500">Years to Retirement</span>
                <div class="text-base font-semibold text-horizon-500">{{ retirementData.yearsToRetirement }} years</div>
              </div>
            </div>

          </div>
          </template>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No pension data added yet.</p>
            <router-link to="/net-worth/retirement" class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white text-sm font-medium rounded-button hover:bg-horizon-600 transition-colors" @click.stop>
              Add Pension
            </router-link>
          </div>
        </DashboardCard>

        <!-- Allowances Card — maps to 'tax-allowances' -->
        <DashboardCard
          v-if="isCardVisible('tax-allowances')"
          title="Allowances"
          :subtitle="'Tax year ' + currentTaxYear"
          :loading="loading.taxAllowances"
          :clickable="false"
        >
          <div v-if="hasAllowancesData" class="space-y-4">
            <!-- Lifetime ISA Allowance (eligible users only) -->
            <template v-if="lisaAllowanceData">
              <div class="cursor-pointer hover:opacity-80 transition-opacity" @click.stop="navigateTo('/net-worth/cash')">
                <div class="flex justify-between items-baseline mb-0.5 gap-2">
                  <span class="text-sm font-bold text-horizon-500">Lifetime ISA</span>
                  <div>
                    <span class="text-sm font-bold text-spring-600">{{ formatCurrency(lisaAllowanceData.used) }}</span>
                    <span class="text-xs text-neutral-500 ml-1">of {{ formatCurrency(lisaAllowanceData.limit || 4000) }}</span>
                  </div>
                </div>
                <div class="w-full bg-light-blue-100 rounded-full h-12 overflow-hidden">
                  <div
                    class="h-12 rounded-full transition-all flex items-center justify-center px-4"
                    :class="lisaAllowanceData.percentUsed === 0 ? '' : allowanceBarClass(lisaAllowanceData.percentUsed, false)"
                    :style="{ width: lisaAllowanceData.percentUsed > 0 ? Math.min(lisaAllowanceData.percentUsed, 100) + '%' : '100%' }"
                  >
                    <span class="text-xs font-bold" :class="lisaAllowanceData.percentUsed === 0 ? 'text-horizon-500' : 'text-white'">{{ Math.round(lisaAllowanceData.percentUsed) }}%</span>
                  </div>
                </div>
                <div class="flex justify-between text-sm mt-1">
                  <span class="text-xs text-neutral-500">25% bonus: {{ formatCurrency(lisaAllowanceData.bonusEarned) }} earned</span>
                  <span class="text-spring-600 font-semibold text-sm">{{ formatCurrency(lisaAllowanceData.remaining) }} remaining</span>
                </div>
              </div>
              <div class="border-t border-light-gray"></div>
            </template>

            <!-- ISA Allowance -->
            <div v-if="isaAllowanceData" class="cursor-pointer hover:opacity-80 transition-opacity" @click.stop="navigateTo('/net-worth/cash')">
              <div class="flex justify-between items-baseline mb-0.5 gap-2">
                <span class="text-sm font-bold text-horizon-500">{{ lisaAllowanceData ? 'ISA Allowance (excl. Lifetime ISA)' : 'ISA Allowance' }}</span>
                <div>
                  <span class="text-sm font-bold text-spring-600">{{ formatCurrency(isaAllowanceData.totalUsed) }}</span>
                  <span class="text-xs text-neutral-500 ml-1">of {{ formatCurrency(isaAllowance?.total_allowance || ISA_ANNUAL_ALLOWANCE) }}</span>
                </div>
              </div>
              <div class="w-full bg-light-blue-100 rounded-full h-12 overflow-hidden">
                <div
                  class="h-12 rounded-full transition-all flex items-center justify-center px-4"
                  :class="isaAllowanceData.percentUsed === 0 ? '' : allowanceBarClass(isaAllowanceData.percentUsed, false)"
                  :style="{ width: isaAllowanceData.percentUsed > 0 ? Math.min(isaAllowanceData.percentUsed, 100) + '%' : '100%' }"
                >
                  <span class="text-xs font-bold" :class="isaAllowanceData.percentUsed === 0 ? 'text-horizon-500' : 'text-white'">{{ Math.round(isaAllowanceData.percentUsed) }}%</span>
                </div>
              </div>
              <div class="flex justify-between text-sm mt-1">
                <div v-if="isaAllowanceData.cashUsed > 0 || isaAllowanceData.ssUsed > 0" class="flex gap-3 text-xs text-neutral-500">
                  <span v-if="isaAllowanceData.cashUsed > 0">Cash ISA: {{ formatCurrency(isaAllowanceData.cashUsed) }}</span>
                  <span v-if="isaAllowanceData.ssUsed > 0">Stocks &amp; Shares ISA: {{ formatCurrency(isaAllowanceData.ssUsed) }}</span>
                </div>
                <span v-else></span>
                <span :class="isaAllowanceData.remaining >= 0 ? 'text-spring-600' : 'text-raspberry-600'" class="font-semibold text-sm">
                  {{ formatCurrency(isaAllowanceData.remaining) }} remaining
                </span>
              </div>
            </div>

            <!-- Divider -->
            <div v-if="isaAllowanceData && pensionAllowanceData" class="border-t border-light-gray"></div>

            <!-- Pension Annual Allowance -->
            <div v-if="pensionAllowanceData" class="cursor-pointer hover:opacity-80 transition-opacity" @click.stop="navigateTo('/net-worth/retirement')">
              <div class="flex justify-between items-baseline mb-0.5 gap-2">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-horizon-500">Pension Annual Allowance</span>
                  <span
                    v-if="pensionAllowanceData.isTapered"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 cursor-help"
                    :title="taperedTooltip"
                  >Tapered</span>
                  <span
                    v-if="pensionAllowanceData.mpaaTriggered"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-raspberry-100 text-raspberry-700"
                  >Money Purchase Annual Allowance</span>
                </div>
                <div>
                  <span class="text-sm font-bold text-spring-600">{{ formatCurrency(pensionStandardUsed) }}</span>
                  <span class="text-xs text-neutral-500 ml-1">of {{ formatCurrency(pensionAllowanceData.availableAllowance) }}</span>
                </div>
              </div>
              <div class="w-full bg-light-blue-100 rounded-full h-12 overflow-hidden">
                <div
                  class="h-12 rounded-full transition-all flex items-center justify-center px-4"
                  :class="pensionStandardPercent === 0 ? '' : allowanceBarClass(pensionStandardPercent, false)"
                  :style="{ width: pensionStandardPercent > 0 ? Math.min(pensionStandardPercent, 100) + '%' : '100%' }"
                >
                  <span class="text-xs font-bold" :class="pensionStandardPercent === 0 ? 'text-horizon-500' : 'text-white'">{{ Math.round(pensionStandardPercent) }}%</span>
                </div>
              </div>
              <div class="flex justify-between text-sm mt-1">
                <span class="text-xs text-neutral-500"></span>
                <span class="text-spring-600 font-semibold text-sm">
                  {{ formatCurrency(pensionStandardRemaining) }} remaining
                </span>
              </div>
            </div>

            <!-- Carry Forward (only when contributions exceed standard allowance) -->
            <template v-if="pensionAllowanceData && carryForwardData">
              <div class="border-t border-light-gray"></div>
              <div class="cursor-pointer hover:opacity-80 transition-opacity" @click.stop="navigateTo('/net-worth/retirement')">
                <div class="flex justify-between items-baseline mb-0.5 gap-2">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-horizon-500">Carry Forward</span>
                  </div>
                  <div>
                    <span class="text-sm font-bold text-spring-600">{{ formatCurrency(carryForwardData.used) }}</span>
                    <span class="text-xs text-neutral-500 ml-1">of {{ formatCurrency(pensionAllowanceData.availableAllowance) }}</span>
                  </div>
                </div>
                <div class="w-full bg-light-blue-100 rounded-full h-12 overflow-hidden">
                  <div
                    class="h-12 rounded-full transition-all flex items-center justify-center px-4"
                    :class="carryForwardData.percentUsed === 0 ? '' : allowanceBarClass(carryForwardData.percentUsed, false)"
                    :style="{ width: carryForwardData.percentUsed > 0 ? Math.min(carryForwardData.percentUsed, 100) + '%' : '100%' }"
                  >
                    <span class="text-xs font-bold" :class="carryForwardData.percentUsed === 0 ? 'text-horizon-500' : 'text-white'">{{ Math.round(carryForwardData.percentUsed) }}%</span>
                  </div>
                </div>
                <div class="flex justify-between text-sm mt-1">
                  <span class="text-xs text-neutral-500">{{ carryForwardTaxYear }}</span>
                  <span class="text-spring-600 font-semibold text-sm">
                    {{ formatCurrency(carryForwardData.remaining) }} remaining
                  </span>
                </div>
              </div>
            </template>
          </div>

          <!-- Empty state -->
          <div v-else class="text-center py-4">
            <div class="mx-auto w-12 h-12 rounded-full bg-raspberry-100 flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-raspberry-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-sm font-semibold text-horizon-500 mb-1">No Allowance Data</h3>
            <p class="text-xs text-neutral-500">
              Add savings or pension accounts to track your tax allowances.
            </p>
          </div>
        </DashboardCard>

        <!-- Goals & Events Card (spans full width on larger screens) — legacy, hidden when stage-curated GoalsCard is shown -->
        <DashboardCard
          v-if="hasGoalsData && !currentStage"
          title="Goals & Life Events"
          :loading="loading.goals"
          class="xl:col-span-3"
          @click="navigateTo('/goals')"
        >
          <!-- Bar chart with event icons - simplified for dashboard -->
          <div v-if="goalsData.hasProjection || goalsData.hasGoals" class="cursor-pointer">
            <GoalsProjectionChartDashboard />
          </div>

          <!-- Empty state for goals -->
          <div v-else class="text-center py-4">
            <div class="mx-auto w-12 h-12 rounded-full bg-raspberry-100 flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-raspberry-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-sm font-semibold text-horizon-500 mb-1">Set Your First Goal</h3>
            <p class="text-xs text-neutral-500">
              Track your financial goals and life events
            </p>
          </div>
        </DashboardCard>

        <!-- Goals projection chart (spans full width) -->
        <DashboardCard
          v-if="currentStage && isCardVisible('goals')"
          title="Goals & Life Events"
          :loading="loading.goals"
          class="xl:col-span-3"
          @click="navigateTo('/goals')"
        >
          <div v-if="goalsData.hasProjection || goalsData.hasGoals" class="cursor-pointer">
            <GoalsProjectionChartDashboard />
          </div>
          <div v-else class="text-center py-4">
            <h3 class="text-sm font-semibold text-horizon-500 mb-1">Set Your First Goal</h3>
            <p class="text-xs text-neutral-500">Track your financial goals and life events</p>
          </div>
        </DashboardCard>


        <!-- Stage-curated: Life Timeline Card (horizontal, spans 3 columns) -->
        <LifeTimelineCard
          v-if="currentStage && isCardVisible('life-timeline')"
          class="xl:col-span-3"
          :horizontal="true"
        />

        <!-- Cross-Module Insights removed from dashboard -->
      </div>
    </div>
  </AppLayout>

  <!-- Net Worth donut hover tooltip (teleported to body to avoid overflow:hidden clipping) -->
  <Teleport to="body">
    <div
      v-if="nwHoveredIndex !== null && netWorthDonutSegments[nwHoveredIndex]"
      class="nw-donut-tooltip"
      :style="{ left: nwMouseX + 12 + 'px', top: nwMouseY - 40 + 'px', background: netWorthDonutSegments[nwHoveredIndex].color }"
    >
      <span class="font-semibold">{{ netWorthDonutSegments[nwHoveredIndex].label }}</span>
      <span>{{ formatCurrency(netWorthDonutSegments[nwHoveredIndex].value) }}</span>
      <span class="text-white/70">{{ netWorthDonutSegments[nwHoveredIndex].percent }}%</span>
    </div>
  </Teleport>
</template>

<script>
import { mapGetters, mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardCard from '@/components/Dashboard/DashboardCard.vue';
import GoalsProjectionChartDashboard from '@/components/Dashboard/GoalsProjectionChartDashboard.vue';
import AreasToCompleteCard from '@/components/Dashboard/AreasToCompleteCard.vue';
import ProfileCompletionCards from '@/components/Dashboard/ProfileCompletionCards.vue';
// CrossModuleInsights removed from dashboard
import EmptyDashboard from '@/components/Dashboard/EmptyDashboard.vue';
import DashboardSparkline from '@/components/Dashboard/DashboardSparkline.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, TEXT_COLORS, BORDER_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';
import storage from '@/utils/storage';
import userProfileService from '@/services/userProfileService';
import { getRelativeTime, getCurrentTaxYear } from '@/utils/dateFormatter';
import { ANNUAL_ALLOWANCE, ISA_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

// Life stage journey components
import JourneyProgressHero from '@/components/Journey/JourneyProgressHero.vue';
import LifeTimelineCard from '@/components/Dashboard/LifeTimelineCard.vue';

import logger from '@/utils/logger';
export default {
  name: 'DashboardView',

  components: {
    AppLayout,
    DashboardCard,
    GoalsProjectionChartDashboard,
    AreasToCompleteCard,
    ProfileCompletionCards,
    EmptyDashboard,
    JourneyProgressHero,
    LifeTimelineCard,
    DashboardSparkline,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: {
        netWorth: true,
        retirement: true,
        estate: true,
        investment: true,
        protection: true,
        goals: true,
        taxAllowances: true,
        plans: false,
      },
      errors: {
        protection: null,
        estate: null,
      },
      dataLoaded: false,
      mfaBannerDismissed: sessionStorage.getItem('mfaBannerDismissed') === 'true',
      knowledgeNudgeDismissed: storage.get('knowledgeNudgeDismissed') === 'true',
      savingKnowledgeLevel: false,
      savingsAccountsExpanded: true,
      investmentAccountsExpanded: false,
      financialCommitmentsData: null,
      willSelection: null,
      isMobile: window.innerWidth < 768,
      nwHoveredIndex: null,
      nwMouseX: 0,
      nwMouseY: 0,
      journeyBlurActive: false,
      ISA_ANNUAL_ALLOWANCE,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin', 'currentUser']),
    ...mapGetters('preview', ['effectivePersonaData']),
    ...mapGetters('plans', { getPlan: 'getPlan' }),
    ...mapGetters('lifeStage', {
      currentStage: 'currentStage',
      stageDashboardCards: 'dashboardCards',
    }),

    isStudentPersona() {
      return this.currentUser?.preview_persona_id === 'student'
        && this.currentUser?.is_preview_user === true;
    },

    recentTransactions() {
      if (!this.isStudentPersona) return [];
      const transactions = this.effectivePersonaData?.recent_transactions || [];
      return transactions.map(t => ({
        ...t,
        relativeDate: getRelativeTime(t.date),
      }));
    },

    studentLiability() {
      if (!this.isStudentPersona) return null;

      const extractPlanType = (name) => {
        const match = (name || '').match(/Plan\s*(\d)/i);
        return match ? `Plan ${match[1]}` : null;
      };

      const buildResult = (loan) => {
        const name = loan.liability_name || loan.name || 'Student Loan';
        const planType = extractPlanType(name) || 'Plan 2';
        return {
          balance: parseFloat(loan.current_balance || 0),
          name,
          planType,
          interestRate: parseFloat(loan.interest_rate || 0),
          notes: loan.notes || '',
        };
      };

      // Check estate store liabilities first (real user data)
      const estateLiabilities = this.$store.state.estate?.liabilities || [];
      const storeLoan = estateLiabilities.find(l => (l.liability_type || '').includes('student'));
      if (storeLoan) return buildResult(storeLoan);

      // Fallback: net worth overview liabilities
      const overview = this.netWorthOverview;
      const liabilities = overview?.liabilities || [];
      const loan = liabilities.find(l => (l.liability_type || '').includes('student'));
      if (loan) return buildResult(loan);

      // Fallback: persona JSON data
      const personaLiabilities = this.effectivePersonaData?.liabilities || [];
      const personaLoan = personaLiabilities.find(l => (l.liability_type || '').includes('student'));
      if (personaLoan) return buildResult(personaLoan);

      return null;
    },

    studentLoanDetails() {
      const planType = this.studentLiability?.planType || 'Plan 2';
      const details = {
        'Plan 1': { threshold: 24990, writeOff: 25 },
        'Plan 2': { threshold: 27295, writeOff: 30 },
        'Plan 4': { threshold: 31395, writeOff: 30 },
        'Plan 5': { threshold: 25000, writeOff: 40 },
      };
      return details[planType] || details['Plan 2'];
    },

    hasAreasToComplete() {
      const skippedSteps = this.currentUser?.onboarding_skipped_steps || [];
      return skippedSteps.length > 0;
    },

    isQuickOnboardingUser() {
      return this.currentUser?.onboarding_mode === 'quick';
    },

    showEmptyDashboard() {
      return !this.hasAnyFinancialData;
    },

    hasAnyFinancialData() {
      return this.hasNetWorthData || this.hasProtectionData || this.hasInvestmentData || this.hasRetirementData || this.hasSavingsData || this.hasActualGoals;
    },

    // Check if the user is currently retired
    isRetired() {
      return this.currentUser?.employment_status === 'retired';
    },

    userAge() {
      const dob = this.currentUser?.date_of_birth;
      if (!dob) return null;
      const birth = new Date(dob);
      const now = new Date();
      let age = now.getFullYear() - birth.getFullYear();
      const monthDiff = now.getMonth() - birth.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
        age--;
      }
      return age;
    },

    // Dynamic title for retirement card
    retirementCardTitle() {
      return this.isRetired ? 'Retirement Income' : 'Retirement';
    },

    showMFABanner() {
      const user = this.currentUser;
      if (!user) return false;
      if (user.is_preview_user) return false;
      if (this.mfaBannerDismissed) return false;
      return user.mfa_enabled !== true;
    },

    showKnowledgeNudge() {
      const user = this.currentUser;
      if (!user) return false;
      if (user.is_preview_user) return false;
      // Already answered — never ask again (check actual data, not just localStorage)
      const riskProfile = this.$store.state.investment?.riskProfile;
      if (riskProfile?.knowledge_level) return false;
      // Dismissed this session — don't pester
      if (this.knowledgeNudgeDismissed) return false;
      // Only show if user has investment or pension accounts
      const hasInvestments = this.$store.getters['completeness/hasModuleData']?.('investment') || this.$store.getters['completeness/hasModuleData']?.('retirement');
      return hasInvestments;
    },

    // Net Worth data
    ...mapGetters('netWorth', {
      netWorthValue: 'netWorth',
      netWorthAssets: 'totalAssets',
      netWorthLiabilities: 'totalLiabilities',
      netWorthOverview: 'overview',
    }),

    netWorthData() {
      return {
        netWorth: this.netWorthValue || 0,
        totalAssets: this.netWorthAssets || 0,
        totalLiabilities: this.netWorthLiabilities || 0,
      };
    },

    // Breakdown of assets and liabilities by category
    netWorthBreakdown() {
      const overview = this.netWorthOverview;
      // Filter out categories with zero values
      const assets = {};
      const liabilities = {};

      if (overview.breakdown) {
        Object.entries(overview.breakdown).forEach(([key, value]) => {
          if (value > 0) {
            assets[key] = value;
          }
        });
      }

      if (overview.liabilitiesBreakdown) {
        Object.entries(overview.liabilitiesBreakdown).forEach(([key, value]) => {
          if (value > 0) {
            liabilities[key] = value;
          }
        });
      }

      return { assets, liabilities };
    },

    hasNetWorthData() {
      return this.netWorthData.totalAssets > 0 || this.netWorthData.totalLiabilities > 0;
    },

    // Net Worth donut chart data
    netWorthChartCategories() {
      const LIABILITY_COLORS = {
        mortgages: '#E83E6D',
        loans: '#C62D57',
        credit_cards: '#A82248',
        other: '#8A1A39',
      };

      const categories = [];

      const ASSET_ROUTES = {
        property: '/net-worth/property',
        investments: '/net-worth/investments',
        pensions: '/net-worth/retirement',
        savings: '/net-worth/cash',
        business: '/net-worth/business',
        chattels: '/net-worth/chattels',
      };

      const LIABILITY_ROUTES = {
        mortgages: '/net-worth/liabilities',
        loans: '/net-worth/liabilities',
        credit_cards: '/net-worth/liabilities',
        other: '/net-worth/liabilities',
      };

      // Asset categories
      Object.entries(this.netWorthBreakdown.assets).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatAssetCategory(key),
            value,
            color: ASSET_COLORS[key] || '#717171',
            route: ASSET_ROUTES[key] || '/net-worth/wealth-summary',
          });
        }
      });

      // Liability categories
      Object.entries(this.netWorthBreakdown.liabilities).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatLiabilityCategory(key),
            value,
            color: LIABILITY_COLORS[key] || '#E83E6D',
            route: LIABILITY_ROUTES[key] || '/net-worth/liabilities',
          });
        }
      });

      return categories;
    },

    netWorthChartSeries() {
      return this.netWorthChartCategories.map(c => c.value);
    },

    netWorthChartLabels() {
      return this.netWorthChartCategories.map(c => c.label);
    },

    netWorthChartColors() {
      // Fixed rotating palette: dark blue, pink, mid blue, green, light blue
      const palette = [
        '#1F2A44', // Horizon 500 — dark blue
        '#E83E6D', // Raspberry 500 — pink
        '#5854E6', // Violet 500 — mid blue
        '#20B486', // Spring 500 — green
        '#6C83BC', // Light Blue 500 — light blue
      ];
      return this.netWorthChartCategories.map((_, idx) => palette[idx % palette.length]);
    },

    // Custom SVG donut segments for V6 design (40px stroke, gradients, rounded caps)
    netWorthDonutSegments() {
      const categories = this.netWorthChartCategories;
      const total = categories.reduce((sum, c) => sum + c.value, 0);
      if (total === 0) return [];

      const circumference = 471.2; // 2 * PI * 75
      const gap = 3; // small gap between segments
      let offset = 0;
      return categories.map(cat => {
        const proportion = cat.value / total;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const seg = {
          label: cat.label,
          value: cat.value,
          percent: (proportion * 100).toFixed(1),
          color: cat.color,
          colorLight: this.lightenColor(cat.color, 0.35),
          route: cat.route,
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
    },

    netWorthChartKey() {
      const total = this.netWorthChartSeries.reduce((a, b) => a + b, 0);
      return `nw-donut-${this.netWorthChartSeries.length}-${Math.round(total)}`;
    },

    netWorthChartOptions() {
      const netWorth = this.netWorthData.netWorth;
      const vm = this;

      const colors = this.netWorthChartColors;
      // Build per-segment gradient shades (lighter version of each color)
      const gradientToColors = colors.map(hex => {
        // Lighten each color by blending with white
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * 0.4));
        return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
      });

      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'donut',
        },
        labels: this.netWorthChartLabels,
        colors,
        legend: { show: false },
        dataLabels: { enabled: false },
        stroke: {
          width: 0,
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'dark',
            type: 'diagonal1',
            gradientToColors,
            stops: [0, 100],
          },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '55%',
              labels: {
                show: true,
                name: {
                  show: false,
                },
                value: {
                  show: true,
                  fontSize: '22px',
                  fontWeight: 900,
                  color: netWorth >= 0 ? '#16A34A' : '#A82248',
                  offsetY: 24,
                  formatter: () => vm.formatCurrency(netWorth),
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: '',
                  fontSize: '22px',
                  fontWeight: 900,
                  color: netWorth >= 0 ? '#16A34A' : '#A82248',
                  formatter: () => vm.formatCurrency(netWorth),
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              const total = vm.netWorthChartSeries.reduce((a, b) => a + b, 0);
              const percent = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
              return `${vm.formatCurrency(val)} (${percent}%)`;
            },
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: { height: 240 },
            },
          },
        ],
      };
    },

    // Mobile bar chart for net worth (each asset/liability category as a bar)
    netWorthBarChartSeries() {
      return [{
        name: 'Value',
        data: this.netWorthChartCategories.map(c => c.value),
      }];
    },

    netWorthBarChartOptions() {
      const vm = this;
      const categories = this.netWorthChartCategories;
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
          offsetY: 0,
        },
        plotOptions: {
          bar: {
            borderRadius: 4,
            columnWidth: '60%',
            distributed: true,
          },
        },
        colors: categories.map(c => c.color),
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
          categories: categories.map(c => c.label),
          labels: {
            style: {
              fontSize: '11px',
              fontWeight: 500,
              colors: categories.map(c => c.color),
            },
            rotate: -45,
            rotateAlways: categories.length > 4,
            trim: false,
            maxHeight: 80,
          },
        },
        yaxis: {
          labels: {
            formatter: (val) => vm.formatCurrency(val),
            style: { fontSize: '10px' },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => vm.formatCurrency(val),
          },
        },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 4,
        },
      };
    },

    // Retirement data - using real API data
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'requiredCapital', 'analysis', 'annualAllowance', 'projections']),
    ...mapGetters('retirement', ['totalPensionWealth', 'yearsToRetirement', 'projectedIncome']),

    retirementData() {
      // Use the SAME data sources as the pension tab (projections from Monte Carlo)
      const requiredCapital = this.requiredCapital || {};
      const potProjection = this.projections?.pension_pot_projection;
      const incomeDrawdown = this.projections?.income_drawdown;

      // Gross income = DC withdrawals + DB pension + State Pension (first year)
      const firstYear = incomeDrawdown?.yearly_income?.[0];
      const grossIncome = firstYear
        ? (firstYear.total_income || 0) + (firstYear.state_pension || 0) + (firstYear.db_pension || 0)
        : 0;

      return {
        projectedIncome: grossIncome || this.projectedIncome || 0,
        targetIncome: requiredCapital.required_income || incomeDrawdown?.target_income || 0,
        projectedCapital: potProjection?.percentile_20_at_retirement || this.totalPensionWealth || 0,
        capitalRequired: requiredCapital.required_capital_at_retirement || 0,
        retirementAge: this.profile?.target_retirement_age || null,
        yearsToRetirement: this.yearsToRetirement || null,
      };
    },

    hasRetirementData() {
      return (this.dcPensions && this.dcPensions.length > 0) ||
             (this.dbPensions && this.dbPensions.length > 0) ||
             !!this.statePension;
    },

    retirementIncomePercent() {
      if (!this.retirementData?.targetIncome || this.retirementData.targetIncome === 0) return 0;
      return Math.round((this.retirementData.projectedIncome / this.retirementData.targetIncome) * 100);
    },

    retirementCapitalPercent() {
      if (!this.retirementData?.capitalRequired || this.retirementData.capitalRequired === 0) return 0;
      return Math.round((this.retirementData.projectedCapital / this.retirementData.capitalRequired) * 100);
    },

    // Retired user income breakdown - uses backend projection data for consistency
    retiredIncomeData() {
      const incomeDrawdown = this.projections?.income_drawdown;
      const firstYear = incomeDrawdown?.yearly_income?.[0];

      const pensionDrawdown = firstYear?.total_income || 0;
      const dbPensionIncome = firstYear?.db_pension || 0;
      const statePensionIncome = firstYear?.state_pension || 0;
      const totalIncome = pensionDrawdown + dbPensionIncome + statePensionIncome;

      return {
        pensionDrawdown,
        dbPensionIncome,
        statePensionIncome,
        totalIncome,
      };
    },

    // Investment & Savings data
    ...mapState('investment', { investmentAccounts: 'accounts', investmentAnalysis: 'analysis' }),
    ...mapGetters('investment', ['totalPortfolioValue']),
    ...mapState('savings', ['expenditureProfile', 'accounts', 'isaAllowance']),

    // Investment accounts list (for line items)
    investmentAccountsList() {
      return this.investmentAccounts || [];
    },

    // Cash/savings accounts list (for line items)
    cashAccountsList() {
      return this.accounts || [];
    },

    investmentData() {
      // Total investments from investment accounts (adjusted for ownership)
      const totalInvestments = this.investmentAccountsList.reduce((sum, account) => {
        return sum + this.ownershipValue(account, 'current_value');
      }, 0);

      // Total cash from savings accounts (adjusted for ownership)
      const totalCash = this.cashAccountsList.reduce((sum, account) => {
        return sum + this.ownershipValue(account, 'current_balance');
      }, 0);

      return {
        totalInvestments,
        totalCash,
        totalValue: totalInvestments + totalCash,
        accountsCount: (this.investmentAccountsList?.length || 0) + (this.cashAccountsList?.length || 0),
      };
    },

    hasInvestmentData() {
      return (this.investmentAccountsList && this.investmentAccountsList.length > 0) ||
             (this.cashAccountsList && this.cashAccountsList.length > 0);
    },

    // Protection data
    ...mapGetters('protection', {
      protectionTotalCoverage: 'totalCoverage',
      protectionTotalPremium: 'totalPremium',
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
      protectionDisabilityPolicies: 'disabilityPolicies',
      protectionSicknessIllnessPolicies: 'sicknessIllnessPolicies',
    }),

    protectionData() {
      return {
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: this.protectionTotalPremium || 0, // Already monthly from store getter
        policyCount: (this.protectionLifePolicies?.length || 0) +
          (this.protectionCriticalIllnessPolicies?.length || 0) +
          (this.protectionIncomeProtectionPolicies?.length || 0) +
          (this.protectionDisabilityPolicies?.length || 0) +
          (this.protectionSicknessIllnessPolicies?.length || 0),
      };
    },

    hasProtectionData() {
      return (this.protectionLifePolicies?.length || 0) +
             (this.protectionCriticalIllnessPolicies?.length || 0) +
             (this.protectionIncomeProtectionPolicies?.length || 0) +
             (this.protectionDisabilityPolicies?.length || 0) +
             (this.protectionSicknessIllnessPolicies?.length || 0) > 0;
    },

    // Goals data
    ...mapState('goals', ['dashboardOverview', 'projectionData']),
    ...mapGetters('goals', ['dashboardData']),

    // Estate data
    ...mapGetters('estate', ['ihtLiability', 'taxableEstate', 'grossEstate']),
    ...mapState('estate', { willInfo: 'willInfo' }),
    ...mapState('trusts', { trusts: 'trusts' }),

    estateData() {
      return {
        taxableEstate: this.taxableEstate || 0,
        ihtLiability: this.ihtLiability || 0,
      };
    },

    trustsList() {
      return this.trusts || [];
    },

    hasEstateData() {
      return this.grossEstate > 0 || this.taxableEstate > 0 || this.ihtLiability > 0;
    },

    willAnswered() {
      return this.willInfo?.will_answered === true;
    },

    goalsData() {
      const data = this.dashboardData || {};
      return {
        hasGoals: data.has_goals || false,
        totalGoals: data.total_goals || 0,
        onTrackCount: data.on_track_count || 0,
        totalTarget: data.total_target || 0,
        totalCurrent: data.total_current || 0,
        overallProgress: Math.round(data.overall_progress || 0),
        lifeEventsCount: data.life_events_count || 0,
        hasProjection: !!this.projectionData,
      };
    },

    hasGoalsData() {
      // Always show goals card - it has empty state
      return true;
    },

    hasActualGoals() {
      const goals = this.$store.state.goals?.goals || [];
      return goals.length > 0;
    },

    // ISA Allowance computed — uses server-calculated tracking data
    // Lifetime ISA eligibility: under 40 and no main residence property
    lisaEligible() {
      if (this.userAge === null) return false;
      if (this.userAge >= 40) return false;
      // No property = likely first-time buyer
      return !this.netWorthBreakdown.assets.property;
    },

    lisaAllowanceData() {
      if (!this.lisaEligible) return null;

      const lisaLimit = 4000;
      const maxBonus = 1000; // 25% of £4,000

      // Find LISA contributions from investment accounts
      const lisaAccounts = (this.investmentAccounts || []).filter(a => {
        const type = (a.account_type || '').toLowerCase();
        return type === 'lisa' || type === 'lifetime_isa';
      });

      const used = lisaAccounts.reduce((sum, a) => {
        return sum + parseFloat(a.isa_subscription_current_year || a.annual_contribution || 0);
      }, 0);

      const capped = Math.min(used, lisaLimit);
      const remaining = lisaLimit - capped;
      const percentUsed = (capped / lisaLimit) * 100;
      const bonusEarned = capped * 0.25;

      return { used: capped, remaining, percentUsed, bonusEarned, maxBonus };
    },

    isaAllowanceData() {
      if (!this.isaAllowance) return null;
      const fullAllowance = this.isaAllowance.total_allowance || 20000;
      const totalAllowance = this.lisaAllowanceData ? fullAllowance - 4000 : fullAllowance;
      const cashUsed = parseFloat(this.isaAllowance.cash_isa_used || 0);
      const ssUsed = parseFloat(this.isaAllowance.stocks_shares_isa_used || 0);
      const totalUsed = parseFloat(this.isaAllowance.total_used || 0) || (cashUsed + ssUsed);
      const remaining = totalAllowance - totalUsed;
      const percentUsed = this.isaAllowance.percentage_used || (totalAllowance > 0 ? (totalUsed / totalAllowance) * 100 : 0);

      return {
        totalAllowance,
        cashUsed,
        ssUsed,
        totalUsed,
        remaining,
        percentUsed,
      };
    },

    // Pension Annual Allowance computed
    pensionAllowanceData() {
      if (this.annualAllowance) {
        const available = this.annualAllowance.available_allowance || ANNUAL_ALLOWANCE;
        const contributions = this.annualAllowance.total_contributions || 0;
        const remaining = this.annualAllowance.remaining_allowance || (available - contributions);
        const percentUsed = available > 0 ? (contributions / available) * 100 : 0;

        return {
          availableAllowance: available,
          totalContributions: contributions,
          remaining,
          percentUsed,
          isTapered: this.annualAllowance.is_tapered || false,
          mpaaTriggered: false,
          hasExcess: this.annualAllowance.has_excess || false,
        };
      }

      // Fallback: calculate from DC pensions if annual allowance API didn't return data
      if (this.dcPensions && this.dcPensions.length > 0) {
        const totalContributions = this.dcPensions.reduce((sum, p) => {
          const employee = parseFloat(p.employee_contribution_amount || 0);
          const employer = parseFloat(p.employer_contribution_amount || 0);
          // Annualise monthly contributions
          const freq = (p.contribution_frequency || 'monthly').toLowerCase();
          const multiplier = freq === 'annual' || freq === 'annually' ? 1 : 12;
          return sum + (employee + employer) * multiplier;
        }, 0);

        if (totalContributions > 0) {
          const available = ANNUAL_ALLOWANCE;
          const remaining = available - totalContributions;
          const percentUsed = (totalContributions / available) * 100;

          return {
            availableAllowance: available,
            totalContributions,
            remaining,
            percentUsed,
            isTapered: false,
            mpaaTriggered: false,
            hasExcess: totalContributions > available,
          };
        }
      }

      return null;
    },

    // Pension standard used (capped at available allowance)
    pensionStandardUsed() {
      if (!this.pensionAllowanceData) return 0;
      return Math.min(this.pensionAllowanceData.totalContributions, this.pensionAllowanceData.availableAllowance);
    },

    pensionStandardPercent() {
      if (!this.pensionAllowanceData) return 0;
      return (this.pensionStandardUsed / this.pensionAllowanceData.availableAllowance) * 100;
    },

    pensionStandardRemaining() {
      if (!this.pensionAllowanceData) return 0;
      return Math.max(0, this.pensionAllowanceData.availableAllowance - this.pensionStandardUsed);
    },

    taperedTooltip() {
      if (!this.pensionAllowanceData?.isTapered) return '';
      const details = this.annualAllowance?.tapering_details;
      if (details) {
        return `Your adjusted income of ${this.formatCurrency(details.adjusted_income)} exceeds the threshold, reducing your Annual Allowance by ${this.formatCurrency(details.reduction)} from £60,000 to ${this.formatCurrency(this.pensionAllowanceData.availableAllowance)}`;
      }
      return `Your income exceeds the adjusted income threshold, so your Annual Allowance is reduced to ${this.formatCurrency(this.pensionAllowanceData.availableAllowance)}`;
    },

    // Carry forward data (only shown when contributions exceed standard allowance)
    carryForwardData() {
      if (!this.pensionAllowanceData) return null;
      const excess = this.pensionAllowanceData.totalContributions - this.pensionAllowanceData.availableAllowance;
      if (excess <= 0) return null;

      const carryForwardAvailable = this.annualAllowance?.carry_forward_available || this.annualAllowance?.carry_forward || 0;
      if (carryForwardAvailable <= 0) return null;

      const used = Math.min(carryForwardAvailable, excess);
      const remaining = this.pensionAllowanceData.availableAllowance - used;
      const percentUsed = (used / this.pensionAllowanceData.availableAllowance) * 100;

      return { used, remaining, percentUsed };
    },

    currentTaxYear() {
      return getCurrentTaxYear();
    },

    carryForwardTaxYear() {
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const day = now.getDate();
      const taxYearStart = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;
      const cfStart = taxYearStart - 3;
      return `${cfStart}/${String(cfStart + 1).slice(-2)}`;
    },

    hasAllowancesData() {
      return !!this.lisaAllowanceData || !!this.isaAllowanceData || !!this.pensionAllowanceData;
    },

    estateActions() {
      const plan = this.getPlan('estate');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    protectionActions() {
      const plan = this.getPlan('protection');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    savingsActions() {
      const plan = this.getPlan('savings');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    investmentActions() {
      const plan = this.getPlan('investment');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },
    retirementActions() {
      const plan = this.getPlan('retirement');
      return (plan?.actions || []).filter(a => a.enabled).slice(0, 2);
    },

    hasSavingsData() {
      const accounts = this.$store.state.savings.accounts || [];
      return accounts.length > 0;
    },

    savingsTotalBalance() {
      const accounts = this.$store.state.savings.accounts || [];
      return accounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance || 0), 0);
    },

    savingsAccountCount() {
      return (this.$store.state.savings.accounts || []).length;
    },

    investmentPortfolioValue() {
      return this.$store.getters['investment/totalPortfolioValue'] || 0;
    },

    investmentAccountCount() {
      return (this.$store.state.investment.accounts || []).length;
    },

    protectionPolicyList() {
      const list = [];
      (this.protectionLifePolicies || []).forEach(p => list.push({ name: p.policy_name || 'Life Insurance', cover: p.sum_assured || 0 }));
      (this.protectionCriticalIllnessPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Critical Illness', cover: p.sum_assured || 0 }));
      (this.protectionIncomeProtectionPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Income Protection', cover: p.monthly_benefit || 0 }));
      (this.protectionDisabilityPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Disability', cover: p.benefit_amount || 0 }));
      (this.protectionSicknessIllnessPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Sickness & Illness', cover: p.benefit_amount || 0 }));
      return list.slice(0, 5);
    },

    savingsSparklineData() {
      const total = this.savingsTotalBalance || 0;
      const now = new Date();
      const labels = [];
      for (let i = 5; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(d.toLocaleString('en-GB', { month: 'short' }));
      }
      // Realistic savings growth: small steady increases (~0.3-0.5% per month from interest + contributions)
      const factors = [0.985, 0.988, 0.991, 0.994, 0.997, 1.0];
      return labels.map((label, i) => ({
        label,
        value: Math.round(total * factors[i]),
      }));
    },

    visibleSavingsAccounts() {
      const sorted = [...(this.savingsAccountList || [])].sort((a, b) =>
        (b.current_balance || 0) - (a.current_balance || 0) || (a.account_name || a.provider || '').localeCompare(b.account_name || b.provider || '')
      );
      if (!this.savingsAccountsExpanded) return [];
      return sorted.slice(0, 5);
    },

    investmentBarData() {
      return (this.$store.state.investment.accounts || [])
        .map(acc => ({
          name: acc.account_name || acc.provider || 'Account',
          value: acc.current_value || acc.total_value || 0,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 6);
    },

    investmentBarSeries() {
      return [{ name: 'Value', data: this.investmentBarData.map(d => d.value) }];
    },

    investmentBarOptions() {
      return {
        chart: { toolbar: { show: false }, parentHeightOffset: 0 },
        plotOptions: { bar: { horizontal: false, columnWidth: this.investmentBarData.length === 1 ? '35%' : '55%', borderRadius: 4, distributed: true } },
        colors: ['#5854E6', '#6C83BC', '#20B486', '#E83E6D', '#E6C9A8', '#1F2A44'],
        xaxis: {
          categories: this.investmentBarData.map(d => d.name.length > 12 ? d.name.substring(0, 12) + '…' : d.name),
          labels: { style: { fontSize: '10px', colors: '#6B7280' }, trim: false },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          show: true,
          labels: {
            style: { fontSize: '9px', colors: ['#6B7280'] },
            formatter: (val) => val >= 1000 ? '£' + Math.round(val / 1000) + 'k' : '£' + val,
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        legend: { show: false },
        grid: { show: false, padding: { left: 2, right: 2, top: -15, bottom: 0 } },
        dataLabels: { enabled: false },
        tooltip: {
          y: { formatter: (val) => this.formatCurrency(val) },
        },
      };
    },

    visibleInvestmentAccounts() {
      const sorted = [...(this.investmentAccountList || [])].sort((a, b) =>
        ((b.current_value || b.total_value || 0) - (a.current_value || a.total_value || 0)) || (a.account_name || a.provider || '').localeCompare(b.account_name || b.provider || '')
      );
      if (!this.investmentAccountsExpanded) return [];
      return sorted.slice(0, 3);
    },

    savingsAccountList() {
      return (this.$store.state.savings.accounts || []).slice(0, 4);
    },

    investmentAccountList() {
      return (this.$store.state.investment.accounts || []).slice(0, 4);
    },
  },

  methods: {
    ...mapActions('goals', ['fetchDashboardOverview', 'fetchProjection']),
    ...mapActions('retirement', ['fetchRequiredCapital']),

    /**
     * Card visibility — always show cards that have data.
     * Life stage only affects the onboarding wizard steps, not what's
     * displayed on the dashboard. If the user has data, show the card.
     */
    isCardVisible() {
      return true;
    },

    // Format asset category names for display
    formatAssetCategory(category) {
      const categoryLabels = {
        pensions: 'Pensions',
        property: 'Property',
        investments: 'Investments',
        cash: 'Cash & Savings',
        business: 'Business Interests',
        chattels: 'Personal Valuables',
      };
      return categoryLabels[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    },

    // Format liability category names for display
    formatLiabilityCategory(category) {
      const categoryLabels = {
        mortgages: 'Mortgages',
        loans: 'Loans',
        credit_cards: 'Credit Cards',
        other: 'Other Liabilities',
      };
      return categoryLabels[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    },

    // Format cash account name from institution and account type
    ownershipValue(account, valueField) {
      const value = parseFloat(account[valueField] || 0);
      if (account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common') {
        const percentage = account.ownership_percentage ?? 50;
        return value * (percentage / 100);
      }
      return value;
    },

    formatCashAccountName(account) {
      const isJoint = account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
      const jointLabel = isJoint ? ' (Joint)' : '';
      const name = account.account_name || '';
      const provider = account.institution || account.provider_name || '';

      if (name) return name + jointLabel;
      if (provider) return provider + jointLabel;
      return 'Cash Account' + jointLabel;
    },

    // Format protection policy name from provider and policy type
    formatPolicyName(policy, fallbackType) {
      const provider = policy.provider || policy.provider_name || '';
      const policyType = policy.policy_type || '';

      // Format policy type for display
      const typeLabels = {
        level_term: 'Level Term',
        decreasing_term: 'Decreasing Term',
        whole_of_life: 'Whole of Life',
        family_income_benefit: 'Family Income Benefit',
      };
      const formattedType = typeLabels[policyType] || policyType.replace(/_/g, ' ');

      if (provider && formattedType) {
        return `${provider} ${formattedType}`;
      }
      return provider || formattedType || fallbackType;
    },

    lightenColor(hex, amount) {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    },

    allowanceBarClass(percentUsed, isOverLimit) {
      if (isOverLimit || percentUsed >= 95) return 'bg-gradient-to-r from-raspberry-400 to-raspberry-600';
      if (percentUsed >= 75) return 'bg-gradient-to-r from-violet-400 to-violet-600';
      return 'bg-gradient-to-r from-horizon-400 to-horizon-500';
    },

    dismissMFABanner() {
      this.mfaBannerDismissed = true;
      sessionStorage.setItem('mfaBannerDismissed', 'true');
    },

    toggleFynChat() {
      window.dispatchEvent(new CustomEvent('fyn-toggle-chat'));
    },

    dismissKnowledgeNudge() {
      this.knowledgeNudgeDismissed = true;
      storage.set('knowledgeNudgeDismissed', 'true');
    },

    async setKnowledgeLevel(level) {
      this.savingKnowledgeLevel = true;
      try {
        await this.$store.dispatch('investment/updateKnowledgeLevel', level);
        this.knowledgeNudgeDismissed = true;
        storage.set('knowledgeNudgeDismissed', 'true');
      } catch (error) {
        logger.error('Failed to save investment knowledge level:', error);
      } finally {
        this.savingKnowledgeLevel = false;
      }
    },

    navigateTo(path) {
      if (path) {
        this.$router.push(path);
      }
    },

    async selectWill(hasWill) {
      this.willSelection = hasWill;
      try {
        await this.$store.dispatch('estate/saveWill', { has_will: hasWill });
      } catch (error) {
        logger.error('Failed to save will information:', error);
      }
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    async loadAllData() {
      const user = this.currentUser;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateIHTPlanning'
        : 'estate/calculateIHT';

      // Student persona: only load modules they actually use
      const moduleLoaders = this.isStudentPersona ? [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'userProfile/fetchProfile' },
        { name: 'goals', action: 'goals/fetchDashboardOverview' },
        { name: 'goals', action: 'goals/fetchGoals' },
        { name: 'goals', action: 'goals/fetchLifeEvents', payload: {} },
      ] : [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'protection', action: 'protection/fetchProtectionData' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'estate', action: estateCalculationAction, payload: {} },
        { name: 'retirement', action: 'trusts/fetchTrusts' },
        { name: 'investment', action: 'userProfile/fetchProfile' },
        { name: 'retirement', action: 'retirement/fetchRetirementData' },
        { name: 'retirement', action: 'retirement/fetchRequiredCapital' },
        { name: 'retirement', action: 'retirement/analyseRetirement' },
        { name: 'retirement', action: 'retirement/fetchProjections' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'investment/analyseInvestment' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
        { name: 'taxAllowances', action: 'retirement/fetchAnnualAllowance', payload: getCurrentTaxYear() },
        { name: 'goals', action: 'goals/fetchDashboardOverview' },
        { name: 'goals', action: 'goals/fetchGoals' },
        { name: 'goals', action: 'goals/fetchLifeEvents', payload: {} },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'estate' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'protection' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'savings' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'investment' },
        { name: 'plans', action: 'plans/fetchPlan', payload: 'retirement' },
      ];

      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

      // Load financial commitments and module completeness
      this.loadFinancialCommitments();
      this.$store.dispatch('completeness/fetchCompleteness');

      const moduleActionCounts = {};
      moduleLoaders.forEach(loader => {
        moduleActionCounts[loader.name] = (moduleActionCounts[loader.name] || 0) + 1;
      });

      const moduleCompletedCounts = {};

      const promises = moduleLoaders.map(loader =>
        this.$store.dispatch(loader.action, loader.payload)
          .then(() => ({ module: loader.name, success: true }))
          .catch(error => ({
            module: loader.name,
            success: false,
            error: error.response?.data?.message || error.message || 'Unknown error'
          }))
      );

      const results = await Promise.allSettled(promises);

      results.forEach(result => {
        if (result.status === 'fulfilled') {
          const { module, success, error } = result.value;
          moduleCompletedCounts[module] = (moduleCompletedCounts[module] || 0) + 1;

          if (!success && this.loading.hasOwnProperty(module)) {
            this.errors[module] = error;
          }

          if (this.loading.hasOwnProperty(module) &&
              moduleCompletedCounts[module] >= moduleActionCounts[module]) {
            this.loading[module] = false;
          }
        } else {
          logger.error('Failed to load module:', result.reason);
        }
      });

      // Also try to fetch projection data for goals chart
      try {
        await this.fetchProjection();
      } catch (e) {
        // Projection is optional, don't block
      }
    },
  },

  mounted() {
    this._handleResize = () => {
      this.isMobile = window.innerWidth < 768;
    };
    window.addEventListener('resize', this._handleResize);

    // Always refresh journey progress when returning to dashboard
    // (e.g. after onboarding, adding data via Fyn, etc.)
    this.$store.dispatch('lifeStage/refreshCompleteness').catch(() => {});

    // Meta Pixel: StartTrial — first dashboard visit after registration
    // Skip for preview/admin/test users
    if (this.$route.query.newUser === '1') {
      const user = this.$store.state.auth?.user;
      if (!user?.is_preview_user && !user?.is_admin && typeof fbq === 'function') {
        fbq('track', 'StartTrial', { currency: 'GBP', value: 0 });
      }
      // Clean the newUser param
      const cleanQuery = { ...this.$route.query };
      delete cleanQuery.newUser;
      this.$router.replace({ query: cleanQuery });
    }

    // Handle openFyn=journey from "Get started with Fyn" registration
    if (this.$route.query.openFyn === 'journey') {
      // Set flag so AiChatPanel adds the journey message after conversation is created
      this.$store.commit('aiChat/SET_PENDING_JOURNEY_PROMPT', true);
      window.dispatchEvent(new Event('fyn-open-chat'));

      // Blur dashboard background on desktop until user interacts with chat
      if (window.innerWidth >= 1024) {
        this.journeyBlurActive = true;
        this._clearBlur = () => { this.journeyBlurActive = false; };
        window.addEventListener('fyn-chat-interaction', this._clearBlur, { once: true });
      }

      // Clean the query param so it doesn't trigger again on refresh
      this.$router.replace({ query: {} });
    }
  },

  beforeUnmount() {
    if (this._handleResize) {
      window.removeEventListener('resize', this._handleResize);
    }
    if (this._clearBlur) {
      window.removeEventListener('fyn-chat-interaction', this._clearBlur);
    }
  },

  watch: {
    currentUser: {
      immediate: true,
      handler(user, oldUser) {
        if (user && !this.dataLoaded) {
          this.dataLoaded = true;
          this.$store.dispatch('lifeStage/fetchStage').catch(() => {});
          this.loadAllData();
        } else if (user && oldUser && user.id !== oldUser.id) {
          // User changed (e.g. preview persona switch) — reload all data
          this.$store.dispatch('lifeStage/fetchStage').catch(() => {});
          this.loadAllData();
        }
      }
    }
  },
};
</script>

<style scoped>
/* Empty cards use order-last to sink below populated cards in the grid */

.dashboard-grid {
  align-items: stretch;
}

.dashboard-grid > * {
  min-width: 0;
  height: 100%;
}

</style>

<style>
.nw-donut-tooltip {
  position: fixed;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 12px;
  border-radius: 8px;
  color: white;
  font-size: 12px;
  line-height: 1.3;
  white-space: nowrap;
  pointer-events: none;
  z-index: 10000;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
</style>
