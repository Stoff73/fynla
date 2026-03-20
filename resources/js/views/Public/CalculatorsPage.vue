<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-horizon-600/80 to-slate-900/90"></div>
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-violet-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
      </div>

      <!-- Grid Pattern Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-30"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
        <!-- Badge -->
        <div class="inline-flex items-center px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-violet-200 text-sm font-medium mb-4">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
          Free Financial Tools
        </div>

        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
          Financial
          <span class="text-raspberry-400">Calculators</span>
        </h1>
        <p class="text-lg text-slate-300 max-w-2xl mx-auto mb-8">
          Free tools to help you understand your finances better.
        </p>

        <!-- Calculator Selection Cards - Now in hero -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 max-w-4xl mx-auto">
          <button
            v-for="calc in calculators"
            :key="calc.id"
            @click="activeCalculator = calc.id"
            :class="[
              'group relative p-4 rounded-xl transition-all duration-300 text-center',
              activeCalculator === calc.id
                ? 'bg-raspberry-500 text-white shadow-lg shadow-raspberry-500/25'
                : 'bg-white/10 backdrop-blur-md text-white hover:bg-white/20 border border-white/20'
            ]"
          >
            <div :class="[
              'w-10 h-10 mx-auto mb-2 rounded-lg flex items-center justify-center transition-colors',
              activeCalculator === calc.id ? 'bg-white/20' : 'bg-white/10'
            ]">
              <!-- Income Tax Icon - Blue -->
              <svg v-if="calc.id === 'income-tax'" class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              <!-- Mortgage Icon - Indigo -->
              <svg v-else-if="calc.id === 'mortgage'" class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              <!-- Loan Icon - Blue -->
              <svg v-else-if="calc.id === 'loan'" class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <!-- Emergency Fund Icon - Emerald -->
              <svg v-else-if="calc.id === 'emergency-fund'" class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              <!-- Pension Icon - Purple -->
              <svg v-else-if="calc.id === 'pension'" class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <span class="font-medium text-sm">{{ calc.name }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Calculator Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <!-- Income Tax Calculator -->
      <div v-if="activeCalculator === 'income-tax'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-fade-in-slide" :key="'income-tax'">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
          <h2 class="text-2xl font-bold text-white">Income Tax Calculator</h2>
          <p class="text-violet-100 mt-1">Calculate your UK income tax and National Insurance contributions for 2025/26</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-6">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Annual Gross Income</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="incomeTax.income"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                    placeholder="50,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Pension Contributions</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="incomeTax.pension"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                    placeholder="0"
                  />
                </div>
                <p class="text-xs text-slate-500 mt-1">Pension contributions reduce your taxable income</p>
              </div>

              <button
                @click="calculateIncomeTax"
                class="w-full px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40"
              >
                Calculate Tax
              </button>
            </div>

            <div v-if="incomeTax.result" class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200">
              <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Gross Income</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(incomeTax.result.gross) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Pension Contributions</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(incomeTax.result.pension) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Taxable Income</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(incomeTax.result.taxable) }}</span>
                </div>
                <div class="border-t border-slate-300 my-2"></div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Income Tax</span>
                  <span class="font-semibold text-raspberry-600">-{{ formatCurrency(incomeTax.result.tax) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">National Insurance</span>
                  <span class="font-semibold text-raspberry-600">-{{ formatCurrency(incomeTax.result.ni) }}</span>
                </div>
                <div class="border-t border-slate-300 my-2"></div>
                <div class="flex justify-between items-center py-3 bg-spring-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-slate-900">Net Income</span>
                  <span class="font-bold text-2xl text-spring-600">{{ formatCurrency(incomeTax.result.net) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Effective Tax Rate</span>
                  <span class="font-semibold text-slate-900">{{ incomeTax.result.effectiveRate }}%</span>
                </div>
              </div>
            </div>

            <div v-else class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200 flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <p class="text-slate-500">Enter your income and click Calculate to see results</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mortgage Calculator -->
      <div v-if="activeCalculator === 'mortgage'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-fade-in-slide" :key="'mortgage'">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-8 py-6">
          <h2 class="text-2xl font-bold text-white">Mortgage Affordability Calculator</h2>
          <p class="text-indigo-100 mt-1">Calculate how much you can afford to borrow and your monthly repayments</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-5">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Annual Income</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="mortgage.income"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="50,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Property Value</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="mortgage.propertyValue"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="250,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Deposit</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="mortgage.deposit"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="25,000"
                  />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">Interest Rate (%)</label>
                  <input
                    v-model.number="mortgage.interestRate"
                    type="number"
                    step="0.1"
                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="5.5"
                  />
                </div>

                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">Term (years)</label>
                  <input
                    v-model.number="mortgage.term"
                    type="number"
                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="25"
                  />
                </div>
              </div>

              <button
                @click="calculateMortgage"
                class="w-full px-6 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl font-semibold hover:from-indigo-700 hover:to-indigo-800 transition-all shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40"
              >
                Calculate Mortgage
              </button>
            </div>

            <div v-if="mortgage.result" class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200">
              <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Property Value</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(mortgage.result.propertyValue) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Deposit ({{ mortgage.result.ltv }}% LTV)</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(mortgage.result.deposit) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Loan Amount</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(mortgage.result.loanAmount) }}</span>
                </div>
                <div class="border-t border-slate-300 my-2"></div>
                <div class="flex justify-between items-center py-3 bg-indigo-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-slate-900">Monthly Payment</span>
                  <span class="font-bold text-2xl text-indigo-600">{{ formatCurrency(mortgage.result.monthlyPayment) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Total Interest</span>
                  <span class="font-semibold text-raspberry-600">{{ formatCurrency(mortgage.result.totalInterest) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Total Repayment</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(mortgage.result.totalRepayment) }}</span>
                </div>
                <div class="mt-4 bg-violet-50 border border-violet-200 rounded-xl p-4">
                  <p class="text-sm text-violet-900">
                    <strong>Maximum Affordable (4.5x income):</strong><br />
                    {{ formatCurrency(mortgage.result.maxAffordable) }}
                  </p>
                </div>
              </div>
            </div>

            <div v-else class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200 flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <p class="text-slate-500">Enter your details and click Calculate to see results</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loan Repayment Calculator -->
      <div v-if="activeCalculator === 'loan'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-fade-in-slide" :key="'loan'">
        <div class="bg-gradient-to-r from-slate-600 to-slate-700 px-8 py-6">
          <h2 class="text-2xl font-bold text-white">Loan Repayment Calculator</h2>
          <p class="text-slate-200 mt-1">Calculate monthly payments and total interest on personal loans</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-6">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Loan Amount</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="loan.amount"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                    placeholder="10,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Interest Rate (% APR)</label>
                <input
                  v-model.number="loan.rate"
                  type="number"
                  step="0.1"
                  class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                  placeholder="8.9"
                />
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Loan Term (months)</label>
                <input
                  v-model.number="loan.term"
                  type="number"
                  class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                  placeholder="36"
                />
              </div>

              <button
                @click="calculateLoan"
                class="w-full px-6 py-4 bg-gradient-to-r from-slate-600 to-slate-700 text-white rounded-xl font-semibold hover:from-slate-700 hover:to-slate-800 transition-all shadow-lg shadow-slate-500/25 hover:shadow-slate-500/40"
              >
                Calculate Loan
              </button>
            </div>

            <div v-if="loan.result" class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200">
              <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Loan Amount</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(loan.result.amount) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Interest Rate</span>
                  <span class="font-semibold text-slate-900">{{ loan.result.rate }}% APR</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Term</span>
                  <span class="font-semibold text-slate-900">{{ loan.result.term }} months</span>
                </div>
                <div class="border-t border-slate-300 my-2"></div>
                <div class="flex justify-between items-center py-3 bg-violet-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-slate-900">Monthly Payment</span>
                  <span class="font-bold text-2xl text-violet-600">{{ formatCurrency(loan.result.monthlyPayment) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Total Interest</span>
                  <span class="font-semibold text-raspberry-600">{{ formatCurrency(loan.result.totalInterest) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Total Repayment</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(loan.result.totalRepayment) }}</span>
                </div>
              </div>
            </div>

            <div v-else class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200 flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-slate-500">Enter your loan details and click Calculate to see results</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Emergency Fund Calculator -->
      <div v-if="activeCalculator === 'emergency-fund'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-fade-in-slide" :key="'emergency-fund'">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-8 py-6">
          <h2 class="text-2xl font-bold text-white">Emergency Fund Calculator</h2>
          <p class="text-spring-100 mt-1">Calculate how much you should save for emergencies (3-6 months of expenses)</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-6">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Monthly Expenses</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="emergencyFund.monthlyExpenses"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                    placeholder="2,500"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Current Savings</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="emergencyFund.currentSavings"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                    placeholder="5,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Target Months</label>
                <select
                  v-model.number="emergencyFund.targetMonths"
                  class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                >
                  <option :value="3">3 months (minimum recommended)</option>
                  <option :value="6">6 months (standard recommendation)</option>
                  <option :value="9">9 months (conservative)</option>
                  <option :value="12">12 months (very conservative)</option>
                </select>
              </div>

              <button
                @click="calculateEmergencyFund"
                class="w-full px-6 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:from-green-700 hover:to-green-800 transition-all shadow-lg shadow-green-500/25 hover:shadow-green-500/40"
              >
                Calculate Fund
              </button>
            </div>

            <div v-if="emergencyFund.result" class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200">
              <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Monthly Expenses</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(emergencyFund.result.monthlyExpenses) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Target Coverage</span>
                  <span class="font-semibold text-slate-900">{{ emergencyFund.result.targetMonths }} months</span>
                </div>
                <div class="border-t border-slate-300 my-2"></div>
                <div class="flex justify-between items-center py-3 bg-spring-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-slate-900">Target Fund</span>
                  <span class="font-bold text-2xl text-spring-600">{{ formatCurrency(emergencyFund.result.targetAmount) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Current Savings</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(emergencyFund.result.currentSavings) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Shortfall</span>
                  <span :class="emergencyFund.result.shortfall > 0 ? 'font-semibold text-raspberry-600' : 'font-semibold text-spring-600'">
                    {{ emergencyFund.result.shortfall > 0 ? formatCurrency(emergencyFund.result.shortfall) : 'Fully funded!' }}
                  </span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Current Runway</span>
                  <span class="font-semibold text-slate-900">{{ emergencyFund.result.currentRunway.toFixed(1) }} months</span>
                </div>
                <div class="mt-4" :class="[
                  'rounded-xl p-4',
                  emergencyFund.result.adequacy === 'Good' ? 'bg-spring-50 border border-spring-200' :
                  emergencyFund.result.adequacy === 'Adequate' ? 'bg-violet-50 border border-violet-200' :
                  'bg-raspberry-50 border border-raspberry-200'
                ]">
                  <p :class="[
                    'text-sm font-semibold',
                    emergencyFund.result.adequacy === 'Good' ? 'text-spring-900' :
                    emergencyFund.result.adequacy === 'Adequate' ? 'text-violet-900' :
                    'text-raspberry-900'
                  ]">
                    Status: {{ emergencyFund.result.adequacy }}
                  </p>
                  <p :class="[
                    'text-xs mt-1',
                    emergencyFund.result.adequacy === 'Good' ? 'text-spring-700' :
                    emergencyFund.result.adequacy === 'Adequate' ? 'text-violet-700' :
                    'text-raspberry-700'
                  ]">
                    {{ emergencyFund.result.message }}
                  </p>
                </div>
              </div>
            </div>

            <div v-else class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200 flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <p class="text-slate-500">Enter your expenses and savings to see your emergency fund status</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pension Growth Calculator -->
      <div v-if="activeCalculator === 'pension'" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-fade-in-slide" :key="'pension'">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-8 py-6">
          <h2 class="text-2xl font-bold text-white">Pension Growth Calculator</h2>
          <p class="text-purple-100 mt-1">Project your pension pot at retirement with regular contributions</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="space-y-5">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Current Pension Value</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="pension.currentValue"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="50,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Monthly Contribution</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-slate-400 font-medium">£</span>
                  <input
                    v-model.number="pension.monthlyContribution"
                    type="number"
                    class="w-full pl-10 pr-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="500"
                  />
                </div>
                <p class="text-xs text-slate-500 mt-1">Including employer contributions and tax relief</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">Current Age</label>
                  <input
                    v-model.number="pension.currentAge"
                    type="number"
                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="35"
                  />
                </div>

                <div>
                  <label class="block text-sm font-semibold text-slate-700 mb-2">Retirement Age</label>
                  <input
                    v-model.number="pension.retirementAge"
                    type="number"
                    class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="65"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Expected Growth Rate (%)</label>
                <input
                  v-model.number="pension.growthRate"
                  type="number"
                  step="0.1"
                  class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                  placeholder="5.0"
                />
                <p class="text-xs text-slate-500 mt-1">Typical range: 4-7% for balanced portfolios</p>
              </div>

              <button
                @click="calculatePension"
                class="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-purple-800 transition-all shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40"
              >
                Calculate Projection
              </button>
            </div>

            <div v-if="pension.result" class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200">
              <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Projection
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Years to Retirement</span>
                  <span class="font-semibold text-slate-900">{{ pension.result.yearsToRetirement }} years</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Current Value</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(pension.result.currentValue) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Total Contributions</span>
                  <span class="font-semibold text-slate-900">{{ formatCurrency(pension.result.totalContributions) }}</span>
                </div>
                <div class="border-t border-slate-300 my-2"></div>
                <div class="flex justify-between items-center py-3 bg-purple-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-slate-900">Projected Pot at {{ pension.retirementAge }}</span>
                  <span class="font-bold text-2xl text-purple-600">{{ formatCurrency(pension.result.projectedValue) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-slate-600">Investment Growth</span>
                  <span class="font-semibold text-spring-600">{{ formatCurrency(pension.result.investmentGrowth) }}</span>
                </div>
                <div class="mt-4 bg-violet-50 border border-violet-200 rounded-xl p-4">
                  <p class="text-sm text-violet-900 font-semibold mb-2">At 4% withdrawal rate:</p>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <p class="text-xs text-violet-700">Annual Income</p>
                      <p class="font-bold text-violet-900">{{ formatCurrency(pension.result.annualIncome) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-violet-700">Monthly Income</p>
                      <p class="font-bold text-violet-900">{{ formatCurrency(pension.result.monthlyIncome) }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border border-slate-200 flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <p class="text-slate-500">Enter your pension details to see your retirement projection</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 py-16 overflow-hidden">
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-horizon-600/50 to-slate-900/80"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-violet-500/10 rounded-full blur-3xl"></div>
      </div>
      <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="bg-white/5 backdrop-blur-md rounded-3xl p-10 border border-white/10">
          <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
            Ready for Comprehensive Financial Planning?
          </h2>
          <p class="text-lg text-slate-300 mb-8 max-w-2xl mx-auto">
            Create a free account to access all planning tools and get a complete view of your finances.
          </p>
          <div class="flex flex-col sm:flex-row justify-center gap-4">
            <router-link
              to="/register"
              class="group px-8 py-4 bg-white text-slate-900 rounded-xl font-semibold text-lg hover:bg-violet-50 transition-all shadow-lg hover:shadow-xl"
            >
              <span class="flex items-center justify-center">
                Get Started Free
                <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </span>
            </router-link>
            <router-link
              to="/login"
              class="px-8 py-4 bg-white/10 backdrop-blur-md text-white rounded-xl font-semibold text-lg hover:bg-white/20 transition-all border border-white/20"
            >
              Sign In
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'CalculatorsPage',
  mixins: [currencyMixin],

  components: {
    PublicLayout,
  },

  data() {
    return {
      activeCalculator: 'income-tax',
      calculators: [
        { id: 'income-tax', name: 'Income Tax', icon: 'IconTax' },
        { id: 'mortgage', name: 'Mortgage', icon: 'IconHome' },
        { id: 'loan', name: 'Loan', icon: 'IconLoan' },
        { id: 'emergency-fund', name: 'Emergency Fund', icon: 'IconShield' },
        { id: 'pension', name: 'Pension', icon: 'IconChart' },
      ],
      incomeTax: {
        income: null,
        pension: 0,
        result: null,
      },
      mortgage: {
        income: null,
        propertyValue: null,
        deposit: null,
        interestRate: 5.5,
        term: 25,
        result: null,
      },
      loan: {
        amount: null,
        rate: null,
        term: null,
        result: null,
      },
      emergencyFund: {
        monthlyExpenses: null,
        currentSavings: null,
        targetMonths: 6,
        result: null,
      },
      pension: {
        currentValue: null,
        monthlyContribution: null,
        currentAge: null,
        retirementAge: 65,
        growthRate: 5.0,
        result: null,
      },
    };
  },

  methods: {
    calculateIncomeTax() {
      const income = this.incomeTax.income || 0;
      const pension = this.incomeTax.pension || 0;
      const taxable = income - pension;

      // UK Tax Bands 2025/26
      const personalAllowance = 12570;
      const basicRateLimit = 50270;
      const higherRateLimit = 125140;

      let tax = 0;
      let remaining = taxable;

      // Personal allowance
      if (remaining > personalAllowance) {
        remaining -= personalAllowance;
      } else {
        remaining = 0;
      }

      // Basic rate (20%)
      if (remaining > 0) {
        const basicRateBand = Math.min(remaining, basicRateLimit - personalAllowance);
        tax += basicRateBand * 0.20;
        remaining -= basicRateBand;
      }

      // Higher rate (40%)
      if (remaining > 0) {
        const higherRateBand = Math.min(remaining, higherRateLimit - basicRateLimit);
        tax += higherRateBand * 0.40;
        remaining -= higherRateBand;
      }

      // Additional rate (45%)
      if (remaining > 0) {
        tax += remaining * 0.45;
      }

      // National Insurance (simplified)
      const niThreshold = 12570;
      const niUpperLimit = 50270;
      let ni = 0;
      if (income > niThreshold) {
        const niableIncome = Math.min(income - niThreshold, niUpperLimit - niThreshold);
        ni = niableIncome * 0.12;
        if (income > niUpperLimit) {
          ni += (income - niUpperLimit) * 0.02;
        }
      }

      const net = income - tax - ni;
      const effectiveRate = income > 0 ? ((tax + ni) / income * 100).toFixed(1) : '0.0';

      this.incomeTax.result = {
        gross: income,
        pension: pension,
        taxable: taxable,
        tax: Math.round(tax),
        ni: Math.round(ni),
        net: Math.round(net),
        effectiveRate: effectiveRate,
      };
    },

    calculateMortgage() {
      const propertyValue = this.mortgage.propertyValue || 0;
      const deposit = this.mortgage.deposit || 0;
      const loanAmount = propertyValue - deposit;
      const monthlyRate = (this.mortgage.interestRate / 100) / 12;
      const numberOfPayments = this.mortgage.term * 12;

      // Monthly payment formula
      const monthlyPayment = loanAmount *
        (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) /
        (Math.pow(1 + monthlyRate, numberOfPayments) - 1);

      const totalRepayment = monthlyPayment * numberOfPayments;
      const totalInterest = totalRepayment - loanAmount;
      const ltv = ((loanAmount / propertyValue) * 100).toFixed(0);
      const maxAffordable = (this.mortgage.income || 0) * 4.5;

      this.mortgage.result = {
        propertyValue: propertyValue,
        deposit: deposit,
        loanAmount: loanAmount,
        ltv: ltv,
        monthlyPayment: Math.round(monthlyPayment),
        totalInterest: Math.round(totalInterest),
        totalRepayment: Math.round(totalRepayment),
        maxAffordable: maxAffordable,
      };
    },

    calculateLoan() {
      const amount = this.loan.amount || 0;
      const monthlyRate = (this.loan.rate / 100) / 12;
      const term = this.loan.term || 1;

      const monthlyPayment = amount *
        (monthlyRate * Math.pow(1 + monthlyRate, term)) /
        (Math.pow(1 + monthlyRate, term) - 1);

      const totalRepayment = monthlyPayment * term;
      const totalInterest = totalRepayment - amount;

      this.loan.result = {
        amount: amount,
        rate: this.loan.rate,
        term: term,
        monthlyPayment: Math.round(monthlyPayment),
        totalInterest: Math.round(totalInterest),
        totalRepayment: Math.round(totalRepayment),
      };
    },

    calculateEmergencyFund() {
      const monthlyExpenses = this.emergencyFund.monthlyExpenses || 0;
      const currentSavings = this.emergencyFund.currentSavings || 0;
      const targetMonths = this.emergencyFund.targetMonths;
      const targetAmount = monthlyExpenses * targetMonths;
      const shortfall = Math.max(0, targetAmount - currentSavings);
      const currentRunway = monthlyExpenses > 0 ? currentSavings / monthlyExpenses : 0;

      let adequacy = 'Low';
      let message = 'Build your emergency fund to cover unexpected expenses.';

      if (currentRunway >= 6) {
        adequacy = 'Good';
        message = 'Excellent! You have a strong emergency fund.';
      } else if (currentRunway >= 3) {
        adequacy = 'Adequate';
        message = 'Good progress. Consider building to 6 months for better security.';
      }

      this.emergencyFund.result = {
        monthlyExpenses: monthlyExpenses,
        targetMonths: targetMonths,
        targetAmount: targetAmount,
        currentSavings: currentSavings,
        shortfall: shortfall,
        currentRunway: currentRunway,
        adequacy: adequacy,
        message: message,
      };
    },

    calculatePension() {
      const currentValue = this.pension.currentValue || 0;
      const monthlyContribution = this.pension.monthlyContribution || 0;
      const currentAge = this.pension.currentAge || 0;
      const retirementAge = this.pension.retirementAge || 65;
      const yearsToRetirement = retirementAge - currentAge;
      const growthRate = (this.pension.growthRate / 100) / 12;
      const months = yearsToRetirement * 12;

      // Future value of current pot
      const futureValueOfCurrent = currentValue * Math.pow(1 + growthRate, months);

      // Future value of contributions
      const futureValueOfContributions = monthlyContribution *
        ((Math.pow(1 + growthRate, months) - 1) / growthRate);

      const projectedValue = futureValueOfCurrent + futureValueOfContributions;
      const totalContributions = monthlyContribution * months;
      const investmentGrowth = projectedValue - currentValue - totalContributions;
      const annualIncome = projectedValue * 0.04;
      const monthlyIncome = annualIncome / 12;

      this.pension.result = {
        currentValue: currentValue,
        yearsToRetirement: yearsToRetirement,
        totalContributions: totalContributions,
        projectedValue: Math.round(projectedValue),
        investmentGrowth: Math.round(investmentGrowth),
        annualIncome: Math.round(annualIncome),
        monthlyIncome: Math.round(monthlyIncome),
      };
    },

    // formatCurrency provided by currencyMixin
  },
};
</script>


