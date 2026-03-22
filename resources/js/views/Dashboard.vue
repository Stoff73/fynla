<template>
  <AppLayout>
    <div class="py-2 sm:py-3">
      <!-- 2FA Security Reminder Banner -->
      <div
        v-if="showMFABanner"
        class="mb-6 bg-spring-100 border border-spring-300 rounded-lg p-4 shadow-sm"
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
            <h3 class="text-sm font-semibold text-spring-800">Secure Your Account with Two-Factor Authentication</h3>
            <p class="mt-1 text-sm text-spring-700">
              Protect your financial data by enabling two-factor authentication. It adds an extra layer of security using an authenticator app on your phone.
            </p>
            <div class="mt-3 flex items-center gap-3">
              <router-link
                to="/settings/security"
                class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Enable Two-Factor Authentication
              </router-link>
              <button
                @click="dismissMFABanner"
                class="text-sm text-spring-700 hover:text-spring-900 underline"
              >
                Remind me later
              </button>
            </div>
          </div>
          <button
            @click="dismissMFABanner"
            class="flex-shrink-0 text-spring-500 hover:text-spring-700"
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
        class="mb-6 bg-violet-50 border border-violet-200 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-violet-100 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-violet-800">How would you describe your investment knowledge?</h3>
            <p class="mt-1 text-sm text-violet-700">
              This helps us tailor investment recommendations to your experience level.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button
                @click="setKnowledgeLevel('novice')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-violet-300 text-violet-700 bg-white hover:bg-violet-100 transition-colors"
              >
                Beginner — I'm new to investing
              </button>
              <button
                @click="setKnowledgeLevel('intermediate')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-violet-300 text-violet-700 bg-white hover:bg-violet-100 transition-colors"
              >
                Intermediate — I understand the basics
              </button>
              <button
                @click="setKnowledgeLevel('experienced')"
                :disabled="savingKnowledgeLevel"
                class="px-4 py-2 text-sm font-medium rounded-button border border-violet-300 text-violet-700 bg-white hover:bg-violet-100 transition-colors"
              >
                Experienced — I'm confident with investments
              </button>
            </div>
          </div>
          <button
            @click="dismissKnowledgeNudge"
            class="flex-shrink-0 text-violet-400 hover:text-violet-600"
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
        :suggested-goals="stageSuggestedGoals"
        @suggested-goal="handleSuggestedGoal"
      />

      <!-- Empty Dashboard (no financial data) -->
      <template v-if="showEmptyDashboard">
        <div class="grid grid-cols-1 gap-3">
          <EmptyDashboard />
        </div>
      </template>

      <!-- Three-column dashboard grid -->
      <div v-else class="dashboard-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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
                <span class="font-medium text-horizon-500">Plan 5</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Interest Rate</span>
                <span class="font-medium text-horizon-500">{{ studentLiability.interestRate }}%</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Repayment Threshold</span>
                <span class="font-medium text-horizon-500">{{ formatCurrency(25000) }}/yr</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Monthly Repayments</span>
                <span class="font-medium text-spring-600">None (studying)</span>
              </div>
            </div>

            <div class="bg-violet-50 border border-violet-200 rounded-lg p-3 mt-3">
              <p class="text-xs text-violet-700">
                Repayments begin the April after you graduate or leave your course, but only if you earn above {{ formatCurrency(25000) }} per year. Your loan is written off after 40 years.
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

            <!-- Desktop: Donut chart with category breakdown -->
            <template v-else>
              <apexchart
                :key="netWorthChartKey"
                type="donut"
                :options="netWorthChartOptions"
                :series="netWorthChartSeries"
                height="260"
              />
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
            <router-link to="/net-worth/wealth-summary" class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors" @click.stop>
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
            <!-- Actions -->
            <div v-if="protectionActions.length > 0" class="pt-3 border-t border-light-gray space-y-2">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
              <div
                v-for="action in protectionActions"
                :key="action.id"
                class="flex items-start gap-2 p-2 rounded-lg hover:bg-savannah-100 cursor-pointer transition-colors"
                @click.stop="navigateTo('/actions/' + (action.plan_type || 'protection') + '/' + action.id)"
              >
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5" :class="action.impact === 'High' || action.impact === 'Critical' ? 'bg-raspberry-100' : 'bg-violet-100'">
                  <svg class="w-3 h-3" :class="action.impact === 'High' || action.impact === 'Critical' ? 'text-raspberry-500' : 'text-violet-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-horizon-500 leading-tight">{{ action.title }}</p>
                  <p v-if="action.description" class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ action.description }}</p>
                </div>
              </div>
            </div>
            <!-- Fallback: policy list when no actions -->
            <div v-else class="pt-3 border-t border-light-gray space-y-2">
              <div v-for="policy in protectionPolicyList" :key="policy.name" class="flex justify-between text-sm">
                <span class="text-neutral-500">{{ policy.name }}</span>
                <span class="font-medium text-horizon-500">{{ formatCurrency(policy.cover) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No protection policies added yet.</p>
            <router-link to="/protection" class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors" @click.stop>
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
          <div v-if="hasSavingsData" class="space-y-4">
            <div class="border-b border-light-gray pb-4">
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
            <div class="flex justify-between text-sm">
              <span class="font-semibold text-horizon-500">Accounts</span>
              <span class="font-medium text-horizon-500">{{ savingsAccountCount }}</span>
            </div>
            <!-- Actions -->
            <div v-if="savingsActions.length > 0" class="pt-3 border-t border-light-gray space-y-2">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
              <div
                v-for="action in savingsActions"
                :key="action.id"
                class="flex items-start gap-2 p-2 rounded-lg hover:bg-savannah-100 cursor-pointer transition-colors"
                @click.stop="navigateTo('/actions/' + (action.plan_type || 'savings') + '/' + action.id)"
              >
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5" :class="action.impact === 'High' || action.impact === 'Critical' ? 'bg-raspberry-100' : 'bg-violet-100'">
                  <svg class="w-3 h-3" :class="action.impact === 'High' || action.impact === 'Critical' ? 'text-raspberry-500' : 'text-violet-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-horizon-500 leading-tight">{{ action.title }}</p>
                  <p v-if="action.description" class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ action.description }}</p>
                </div>
              </div>
            </div>
            <!-- Fallback: account list when no actions -->
            <div v-else class="pt-3 border-t border-light-gray space-y-2">
              <div v-for="acc in savingsAccountList" :key="acc.id" class="flex justify-between text-sm">
                <span class="text-neutral-500 truncate mr-2">{{ acc.account_name || acc.provider }}</span>
                <span class="font-medium text-horizon-500 whitespace-nowrap">{{ formatCurrency(acc.current_balance) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No savings accounts added yet.</p>
            <router-link to="/net-worth/cash" class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors" @click.stop>
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
          <div v-if="hasInvestmentData" class="space-y-4">
            <div class="border-b border-light-gray pb-4">
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
            <div class="flex justify-between text-sm">
              <span class="font-semibold text-horizon-500">Accounts</span>
              <span class="font-medium text-horizon-500">{{ investmentAccountCount }}</span>
            </div>
            <!-- Actions -->
            <div v-if="investmentActions.length > 0" class="pt-3 border-t border-light-gray space-y-2">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
              <div
                v-for="action in investmentActions"
                :key="action.id"
                class="flex items-start gap-2 p-2 rounded-lg hover:bg-savannah-100 cursor-pointer transition-colors"
                @click.stop="navigateTo('/actions/' + (action.plan_type || 'investment') + '/' + action.id)"
              >
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5" :class="action.impact === 'High' || action.impact === 'Critical' ? 'bg-raspberry-100' : 'bg-violet-100'">
                  <svg class="w-3 h-3" :class="action.impact === 'High' || action.impact === 'Critical' ? 'text-raspberry-500' : 'text-violet-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-horizon-500 leading-tight">{{ action.title }}</p>
                  <p v-if="action.description" class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ action.description }}</p>
                </div>
              </div>
            </div>
            <!-- Fallback: account list when no actions -->
            <div v-else class="pt-3 border-t border-light-gray space-y-2">
              <div v-for="acc in investmentAccountList" :key="acc.id" class="flex justify-between text-sm">
                <span class="text-neutral-500 truncate mr-2">{{ acc.account_name || acc.provider }}</span>
                <span class="font-medium text-horizon-500 whitespace-nowrap">{{ formatCurrency(acc.current_value || acc.total_value || 0) }}</span>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No investment accounts added yet.</p>
            <router-link to="/net-worth/investments" class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors" @click.stop>
              Add Investment Account
            </router-link>
          </div>
        </DashboardCard>

        <!-- Estate Planning Card — maps to 'estate' -->
        <DashboardCard
          v-if="isCardVisible('estate')"
          title="Estate Planning"
          :loading="loading.estate"
          :empty="!hasEstateData"
          @click="navigateTo('/estate')"
        >
          <div v-if="hasEstateData" class="space-y-4">
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

            <!-- Actions -->
            <div v-if="estateActions.length > 0" class="space-y-2">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
              <div
                v-for="action in estateActions"
                :key="action.id"
                class="flex items-start gap-2 p-2 rounded-lg hover:bg-savannah-100 cursor-pointer transition-colors"
                @click.stop="navigateTo('/actions/' + (action.plan_type || 'estate') + '/' + action.id)"
              >
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5" :class="action.impact === 'High' || action.impact === 'Critical' ? 'bg-raspberry-100' : 'bg-violet-100'">
                  <svg class="w-3 h-3" :class="action.impact === 'High' || action.impact === 'Critical' ? 'text-raspberry-500' : 'text-violet-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-horizon-500 leading-tight">{{ action.title }}</p>
                  <p v-if="action.description" class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ action.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty state when no estate data -->
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">
              No estate details added yet.
            </p>
            <router-link to="/estate" class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors" @click.stop>
              Add Estate Details
            </router-link>
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


        <!-- Retirement Card (hidden for users under 35) — maps to 'retirement' / 'retirement-income' -->
        <DashboardCard
          v-if="(userAge === null || userAge >= 35) && (isCardVisible('retirement') || isCardVisible('retirement-income'))"
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

            <!-- Retirement Actions (retired) -->
            <div v-if="retirementActions.length > 0" class="pt-3 border-t border-light-gray space-y-2">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
              <div
                v-for="action in retirementActions"
                :key="action.id"
                class="flex items-start gap-2 p-2 rounded-lg hover:bg-savannah-100 cursor-pointer transition-colors"
                @click.stop="navigateTo('/actions/' + (action.plan_type || 'retirement') + '/' + action.id)"
              >
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5" :class="action.impact === 'High' || action.impact === 'Critical' ? 'bg-raspberry-100' : 'bg-violet-100'">
                  <svg class="w-3 h-3" :class="action.impact === 'High' || action.impact === 'Critical' ? 'text-raspberry-500' : 'text-violet-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-horizon-500 leading-tight">{{ action.title }}</p>
                  <p v-if="action.description" class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ action.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- NON-RETIRED USER: Show projections with progress bars -->
          <div v-else class="space-y-4">
            <!-- Income progress bar -->
            <div>
              <div class="flex justify-between items-baseline mb-1.5">
                <span class="text-sm font-medium text-horizon-500">Income</span>
                <div class="flex items-baseline gap-1.5">
                  <span class="text-sm font-bold text-spring-600">{{ formatCurrency(retirementData.projectedIncome) }}</span>
                  <span class="text-xs text-neutral-500">of {{ formatCurrency(retirementData.targetIncome) }}/yr</span>
                </div>
              </div>
              <div class="w-full bg-neutral-100 rounded-full h-3">
                <div
                  class="h-3 rounded-full transition-all duration-500"
                  :class="retirementIncomePercent >= 100 ? 'bg-spring-500' : 'bg-violet-500'"
                  :style="{ width: Math.min(retirementIncomePercent, 100) + '%' }"
                ></div>
              </div>
            </div>

            <!-- Capital progress bar -->
            <div>
              <div class="flex justify-between items-baseline mb-1.5">
                <span class="text-sm font-medium text-horizon-500">Capital</span>
                <div class="flex items-baseline gap-1.5">
                  <span class="text-sm font-bold text-spring-600">{{ formatCurrency(retirementData.projectedCapital) }}</span>
                  <span class="text-xs text-neutral-500">of {{ formatCurrency(retirementData.capitalRequired) }}</span>
                </div>
              </div>
              <div class="w-full bg-neutral-100 rounded-full h-3">
                <div
                  class="h-3 rounded-full transition-all duration-500"
                  :class="retirementCapitalPercent >= 100 ? 'bg-spring-500' : 'bg-violet-500'"
                  :style="{ width: Math.min(retirementCapitalPercent, 100) + '%' }"
                ></div>
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

            <!-- Retirement Actions (non-retired) -->
            <div v-if="retirementActions.length > 0" class="pt-3 border-t border-light-gray space-y-2">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
              <div
                v-for="action in retirementActions"
                :key="action.id"
                class="flex items-start gap-2 p-2 rounded-lg hover:bg-savannah-100 cursor-pointer transition-colors"
                @click.stop="navigateTo('/actions/' + (action.plan_type || 'retirement') + '/' + action.id)"
              >
                <div class="w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center mt-0.5" :class="action.impact === 'High' || action.impact === 'Critical' ? 'bg-raspberry-100' : 'bg-violet-100'">
                  <svg class="w-3 h-3" :class="action.impact === 'High' || action.impact === 'Critical' ? 'text-raspberry-500' : 'text-violet-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-horizon-500 leading-tight">{{ action.title }}</p>
                  <p v-if="action.description" class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ action.description }}</p>
                </div>
              </div>
            </div>
          </div>
          </template>
          <div v-else class="text-center py-6">
            <p class="text-sm text-neutral-500 mb-4">No pension data added yet.</p>
            <router-link to="/net-worth/retirement" class="inline-flex items-center px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors" @click.stop>
              Add Pension
            </router-link>
          </div>
        </DashboardCard>

        <!-- Allowances Card — maps to 'tax-allowances' -->
        <DashboardCard
          v-if="isCardVisible('tax-allowances')"
          title="Allowances"
          :loading="loading.taxAllowances"
          :clickable="false"
        >
          <div v-if="hasAllowancesData" class="space-y-4">
            <!-- Lifetime ISA Allowance (eligible users only) -->
            <template v-if="lisaAllowanceData">
              <div>
                <div class="flex justify-between items-baseline mb-1">
                  <span class="text-sm font-semibold text-horizon-500">Lifetime ISA</span>
                  <span class="text-xs text-neutral-500">{{ currentTaxYear }}</span>
                </div>
                <div class="w-full bg-savannah-100 rounded-full h-2 mb-2">
                  <div
                    class="h-2 rounded-full transition-all"
                    :class="allowanceBarClass(lisaAllowanceData.percentUsed, false)"
                    :style="{ width: Math.min(lisaAllowanceData.percentUsed, 100) + '%' }"
                  ></div>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-neutral-500">{{ formatCurrency(lisaAllowanceData.used) }} used</span>
                  <span class="text-spring-600 font-medium">
                    {{ formatCurrency(lisaAllowanceData.remaining) }} remaining
                  </span>
                </div>
                <div class="text-xs text-neutral-500 mt-1">
                  25% bonus: {{ formatCurrency(lisaAllowanceData.bonusEarned) }} earned of {{ formatCurrency(lisaAllowanceData.maxBonus) }} max
                </div>
              </div>
              <div class="border-t border-light-gray"></div>
            </template>

            <!-- ISA Allowance -->
            <div v-if="isaAllowanceData">
              <div class="flex justify-between items-baseline mb-1">
                <span class="text-sm font-semibold text-horizon-500">{{ lisaAllowanceData ? 'ISA Allowance (excl. Lifetime ISA)' : 'ISA Allowance' }}</span>
                <span class="text-xs text-neutral-500">2025/26</span>
              </div>
              <!-- Progress bar -->
              <div class="w-full bg-savannah-100 rounded-full h-2 mb-2">
                <div
                  class="h-2 rounded-full transition-all"
                  :class="allowanceBarClass(isaAllowanceData.percentUsed, false)"
                  :style="{ width: Math.min(isaAllowanceData.percentUsed, 100) + '%' }"
                ></div>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">{{ formatCurrency(isaAllowanceData.totalUsed) }} used</span>
                <span :class="isaAllowanceData.remaining >= 0 ? 'text-spring-600' : 'text-raspberry-600'" class="font-medium">
                  {{ formatCurrency(isaAllowanceData.remaining) }} remaining
                </span>
              </div>
              <!-- Cash / Stocks & Shares breakdown -->
              <div v-if="isaAllowanceData.cashUsed > 0 || isaAllowanceData.ssUsed > 0" class="flex gap-4 mt-1 text-xs text-neutral-500">
                <span v-if="isaAllowanceData.cashUsed > 0">Cash ISA: {{ formatCurrency(isaAllowanceData.cashUsed) }}</span>
                <span v-if="isaAllowanceData.ssUsed > 0">Stocks &amp; Shares ISA: {{ formatCurrency(isaAllowanceData.ssUsed) }}</span>
              </div>
            </div>

            <!-- Divider -->
            <div v-if="isaAllowanceData && pensionAllowanceData" class="border-t border-light-gray"></div>

            <!-- Pension Annual Allowance -->
            <div v-if="pensionAllowanceData">
              <div class="flex justify-between items-baseline mb-1">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-semibold text-horizon-500">Pension Annual Allowance</span>
                  <span
                    v-if="pensionAllowanceData.isTapered"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700"
                  >Tapered</span>
                  <span
                    v-if="pensionAllowanceData.mpaaTriggered"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-raspberry-100 text-raspberry-700"
                  >Money Purchase Annual Allowance</span>
                </div>
                <span class="text-xs text-neutral-500">{{ currentTaxYear }}</span>
              </div>
              <!-- Progress bar -->
              <div class="w-full bg-savannah-100 rounded-full h-2 mb-2">
                <div
                  class="h-2 rounded-full transition-all"
                  :class="allowanceBarClass(pensionStandardPercent, false)"
                  :style="{ width: Math.min(pensionStandardPercent, 100) + '%' }"
                ></div>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">{{ formatCurrency(pensionStandardUsed) }} used</span>
                <span class="text-spring-600 font-medium">
                  {{ formatCurrency(pensionStandardRemaining) }} remaining
                </span>
              </div>
              <div class="text-xs text-neutral-500 mt-1">
                of {{ formatCurrency(pensionAllowanceData.availableAllowance) }} allowance
              </div>
            </div>

            <!-- Carry Forward (only when contributions exceed standard allowance) -->
            <template v-if="pensionAllowanceData && carryForwardData">
              <div class="border-t border-light-gray"></div>
              <div>
                <div class="flex justify-between items-baseline mb-1">
                  <span class="text-sm font-semibold text-horizon-500">Carry Forward</span>
                  <span class="text-xs text-neutral-500">{{ carryForwardTaxYear }}</span>
                </div>
                <div class="w-full bg-savannah-100 rounded-full h-2 mb-2">
                  <div
                    class="h-2 rounded-full transition-all"
                    :class="allowanceBarClass(carryForwardData.percentUsed, false)"
                    :style="{ width: Math.min(carryForwardData.percentUsed, 100) + '%' }"
                  ></div>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-neutral-500">{{ formatCurrency(carryForwardData.used) }} used</span>
                  <span class="text-spring-600 font-medium">
                    {{ formatCurrency(carryForwardData.remaining) }} remaining
                  </span>
                </div>
                <div class="text-xs text-neutral-500 mt-1">
                  of {{ formatCurrency(pensionAllowanceData.availableAllowance) }} allowance
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

        <!-- Goals & Events Card (spans 2 columns on larger screens) — legacy, hidden when stage-curated GoalsCard is shown -->
        <DashboardCard
          v-if="hasGoalsData && !currentStage"
          title="Goals & Life Events"
          :loading="loading.goals"
          class="lg:col-span-2"
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

        <!-- UK Taxes card removed — accessible via /uk-taxes route and admin panel -->

        <!-- Stage-curated: Goals projection chart (spans 2 columns) + Suggested goals card (1 column) -->
        <DashboardCard
          v-if="currentStage && isCardVisible('goals')"
          title="Goals & Life Events"
          :loading="loading.goals"
          class="lg:col-span-2"
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
          class="lg:col-span-3"
          :horizontal="true"
        />

        <!-- Cross-Module Insights removed from dashboard -->
      </div>
    </div>
  </AppLayout>
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
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, TEXT_COLORS } from '@/constants/designSystem';
import storage from '@/utils/storage';
import userProfileService from '@/services/userProfileService';
import { getRelativeTime } from '@/utils/dateFormatter';

// Life stage journey components
import JourneyProgressHero from '@/components/Journey/JourneyProgressHero.vue';
import GoalsCard from '@/components/Dashboard/GoalsCard.vue';
import LifeTimelineCard from '@/components/Dashboard/LifeTimelineCard.vue';

export default {
  name: 'Dashboard',

  components: {
    AppLayout,
    DashboardCard,
    GoalsProjectionChartDashboard,
    AreasToCompleteCard,
    ProfileCompletionCards,
    EmptyDashboard,
    JourneyProgressHero,
    GoalsCard,
    LifeTimelineCard,
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
      mfaBannerDismissed: storage.get('mfaBannerDismissed') === 'true',
      knowledgeNudgeDismissed: storage.get('knowledgeNudgeDismissed') === 'true',
      savingKnowledgeLevel: false,
      financialCommitmentsData: null,
      willSelection: null,
      isMobile: window.innerWidth < 768,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin', 'currentUser']),
    ...mapGetters('preview', ['effectivePersonaData']),
    ...mapGetters('plans', { getPlan: 'getPlan' }),
    ...mapGetters('lifeStage', {
      currentStage: 'currentStage',
      stageDashboardCards: 'dashboardCards',
      stageSuggestedGoals: 'suggestedGoals',
    }),

    isStudentPersona() {
      return this.currentUser?.preview_persona_id === 'student'
        || this.currentUser?.life_stage === 'university';
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
      // Check estate store liabilities first (real user data)
      const estateLiabilities = this.$store.state.estate?.liabilities || [];
      const storeLoan = estateLiabilities.find(l => (l.liability_type || '').includes('student'));
      if (storeLoan) {
        return {
          balance: parseFloat(storeLoan.current_balance || 0),
          name: storeLoan.liability_name || 'Student Loan',
          interestRate: parseFloat(storeLoan.interest_rate || 0),
          notes: storeLoan.notes || '',
        };
      }
      // Fallback: net worth overview liabilities
      const overview = this.netWorthOverview;
      const liabilities = overview?.liabilities || [];
      const loan = liabilities.find(l => (l.liability_type || '').includes('student'));
      if (loan) {
        return {
          balance: parseFloat(loan.current_balance || 0),
          name: loan.liability_name || 'Student Loan',
          interestRate: parseFloat(loan.interest_rate || 0),
          notes: loan.notes || '',
        };
      }
      // Fallback: persona JSON data
      const personaLiabilities = this.effectivePersonaData?.liabilities || [];
      const personaLoan = personaLiabilities.find(l => (l.liability_type || '').includes('student'));
      if (personaLoan) {
        return {
          balance: parseFloat(personaLoan.current_balance || 0),
          name: personaLoan.liability_name || 'Student Loan',
          interestRate: parseFloat(personaLoan.interest_rate || 0),
          notes: personaLoan.notes || '',
        };
      }
      return null;
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
        mortgages: '#B91C1C',
        loans: '#991B1B',
        credit_cards: '#DC2626',
        other: '#7F1D1D',
      };

      const categories = [];

      // Asset categories
      Object.entries(this.netWorthBreakdown.assets).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatAssetCategory(key),
            value,
            color: ASSET_COLORS[key] || '#94A3B8',
          });
        }
      });

      // Liability categories
      Object.entries(this.netWorthBreakdown.liabilities).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatLiabilityCategory(key),
            value,
            color: LIABILITY_COLORS[key] || '#B91C1C',
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
      return this.netWorthChartCategories.map(c => c.color);
    },

    netWorthChartKey() {
      const total = this.netWorthChartSeries.reduce((a, b) => a + b, 0);
      return `nw-donut-${this.netWorthChartSeries.length}-${Math.round(total)}`;
    },

    netWorthChartOptions() {
      const netWorth = this.netWorthData.netWorth;
      const vm = this;

      return {
        chart: {
          type: 'donut',
          fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
        },
        labels: this.netWorthChartLabels,
        colors: this.netWorthChartColors,
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: false,
                },
                value: {
                  show: true,
                  fontSize: '22px',
                  fontWeight: 900,
                  color: netWorth >= 0 ? '#16A34A' : '#DC2626',
                  offsetY: 24,
                  formatter: () => vm.formatCurrency(netWorth),
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: '',
                  fontSize: '22px',
                  fontWeight: 900,
                  color: netWorth >= 0 ? '#16A34A' : '#DC2626',
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
          type: 'bar',
          fontFamily: 'Segoe UI, Inter, system-ui, sans-serif',
          toolbar: { show: false },
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
          borderColor: '#E2E8F0',
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

      return {
        projectedIncome: incomeDrawdown?.yearly_income?.[0]?.total_income || this.projectedIncome || 0,
        targetIncome: incomeDrawdown?.target_income || requiredCapital.required_income || 0,
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

    // Retired user income breakdown - calculates actual income from pension sources
    retiredIncomeData() {
      // Pension Drawdown: DC pension wealth × 4% safe withdrawal rate
      const dcWealth = this.totalPensionWealth || 0;
      const pensionDrawdown = dcWealth * 0.04;

      // DB Pension: Sum of accrued annual pension from all DB schemes
      const dbPensionIncome = (this.dbPensions || []).reduce((sum, pension) => {
        return sum + parseFloat(pension.accrued_annual_pension || 0);
      }, 0);

      // State Pension: Use configured amount or UK default (£11,502 for 2024/25)
      const statePensionIncome = parseFloat(this.statePension?.annual_amount || 0) || 11502;

      // Total retirement income
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
    ...mapGetters('estate', ['ihtLiability', 'taxableEstate']),
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
      if (this.userAge !== null && this.userAge <= 35) return false;
      return this.taxableEstate > 0 || this.ihtLiability > 0;
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
        const available = this.annualAllowance.available_allowance || 60000;
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
          const available = 60000;
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
      const now = new Date();
      const year = now.getFullYear();
      const month = now.getMonth();
      const day = now.getDate();
      const taxYearStart = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;
      return `${taxYearStart}/${String(taxYearStart + 1).slice(-2)}`;
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
      (this.protectionLifePolicies || []).forEach(p => list.push({ name: p.policy_name || 'Life Insurance', cover: p.cover_amount || 0 }));
      (this.protectionCriticalIllnessPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Critical Illness', cover: p.cover_amount || 0 }));
      (this.protectionIncomeProtectionPolicies || []).forEach(p => list.push({ name: p.policy_name || 'Income Protection', cover: p.monthly_benefit || 0 }));
      return list.slice(0, 4);
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
     * Stage-curated card visibility.
     * When a life stage is active, only cards listed in the stage's dashboard.cards
     * config are shown. When no stage is set, all cards fall back to their original
     * visibility logic (the existing v-if conditions handle that).
     */
    isCardVisible(cardId) {
      // No stage set — fall back to showing all cards (existing behaviour)
      if (!this.currentStage) return true;
      // Stage is active — only show cards in the curated list
      return this.stageDashboardCards.includes(cardId);
    },

    handleSuggestedGoal(goalData) {
      // Navigate to goals page with the suggested goal pre-filled
      this.$router.push({
        path: '/goals',
        query: { addGoal: 'true', suggested: goalData.id },
      });
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

    allowanceBarClass(percentUsed, isOverLimit) {
      if (isOverLimit || percentUsed >= 95) return 'bg-raspberry-500';
      if (percentUsed >= 75) return 'bg-violet-500';
      return 'bg-spring-500';
    },

    dismissMFABanner() {
      this.mfaBannerDismissed = true;
      storage.set('mfaBannerDismissed', 'true');
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
        console.error('Failed to save investment knowledge level:', error);
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
        console.error('Failed to save will information:', error);
      }
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        console.error('Failed to load financial commitments:', error);
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
        { name: 'taxAllowances', action: 'retirement/fetchAnnualAllowance', payload: '2025/26' },
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
          console.error('Failed to load module:', result.reason);
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

  },

  beforeUnmount() {
    if (this._handleResize) {
      window.removeEventListener('resize', this._handleResize);
    }
  },

  watch: {
    currentUser: {
      immediate: true,
      handler(user) {
        if (user && !this.dataLoaded) {
          this.dataLoaded = true;
          // Fetch life stage data (for stage-curated dashboard)
          this.$store.dispatch('lifeStage/fetchStage').catch(() => {});
          this.loadAllData();
        }
      }
    }
  },
};
</script>

<style scoped>
/* Dashboard grid gap fix: last card(s) expand to fill remaining row space.
   Uses :not([class*="col-span"]) to exclude cards that already span multiple columns. */
@media (min-width: 1024px) {
  .dashboard-grid > .dashboard-card:last-of-type:nth-of-type(3n+1) {
    grid-column: span 3;
  }
  .dashboard-grid > .dashboard-card:last-of-type:nth-of-type(3n+2) {
    grid-column: span 2;
  }
}
@media (min-width: 768px) and (max-width: 1023px) {
  .dashboard-grid > .dashboard-card:last-of-type:nth-of-type(2n+1) {
    grid-column: span 2;
  }
}
</style>
