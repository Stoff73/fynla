<template>
  <PublicLayout>
    <!-- Hero Section — matches pricing page -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Free Financial
          <span class="text-raspberry-300">Calculators</span>
        </h1>
        <p class="text-lg text-white/70">
          Free tools to help you understand your finances. Planning tools require a free Fynla account.
        </p>
      </div>
    </div>

    <!-- Two-column layout: sidebar + calculator -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col lg:flex-row gap-8 items-start">

      <!-- Mobile: horizontal pill navigation (shown below lg) -->
      <div class="w-full lg:hidden">
        <!-- Category pills — horizontal scroll -->
        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
          <button
            v-for="stage in calculatorStages"
            :key="'pill-'+stage.name"
            type="button"
            class="flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-semibold whitespace-nowrap flex-shrink-0 transition-all"
            :class="activeStage === stage.name ? 'bg-horizon-500 text-white' : 'bg-white border border-light-gray text-horizon-500'"
            @click="activeStage = stage.name"
          >
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stage.icon" />
            </svg>
            {{ stage.name }}
          </button>
        </div>
        <!-- Calculator items for selected category -->
        <div class="flex gap-1.5 overflow-x-auto pb-3 pt-1.5 scrollbar-hide">
          <button
            v-for="item in activeStageItems"
            :key="'mpill-'+item.id"
            type="button"
            class="px-3 py-1.5 rounded-full text-xs whitespace-nowrap flex-shrink-0 transition-all"
            :class="activeCalculator === item.id ? 'bg-light-blue-100 text-horizon-500 font-semibold' : 'bg-eggshell-500 text-neutral-500'"
            @click="item.type === 'free' ? selectCalculator(item.id) : null"
          >
            {{ item.name }}
          </button>
        </div>
      </div>

      <!-- Desktop: collapsible sidebar (hidden below lg) -->
      <div class="hidden lg:block w-[280px] flex-shrink-0 sticky top-20">
        <div v-for="stage in calculatorStages" :key="stage.name" class="mb-1">
          <!-- Stage header (collapsible) -->
          <button
            type="button"
            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg hover:bg-horizon-500/[0.05] transition-colors"
            @click="toggleStage(stage.name)"
          >
            <div class="flex items-center gap-2.5">
              <svg class="w-5 h-5 text-horizon-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stage.icon" />
              </svg>
              <span class="text-xs font-bold text-horizon-500 uppercase tracking-wider">{{ stage.name }}</span>
            </div>
            <svg
              class="w-4 h-4 text-neutral-400 transition-transform duration-200"
              :class="{ 'rotate-180': expandedStages[stage.name] }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <!-- Calculator items -->
          <div v-if="expandedStages[stage.name]" class="pb-1">
            <button
              v-for="item in stage.items"
              :key="item.id"
              type="button"
              class="w-full flex items-center gap-2.5 px-3.5 py-2 pl-11 rounded-lg text-sm transition-all"
              :class="activeCalculator === item.id ? 'bg-light-blue-100 text-horizon-500 font-semibold' : 'text-neutral-500 hover:bg-light-pink-100 hover:text-horizon-500'"
              @click="item.type === 'free' ? selectCalculator(item.id) : null"
            >
              <svg class="w-4 h-4 text-neutral-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.menuIcon || 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 7h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8a1 1 0 011-1z'" />
              </svg>
              <span class="truncate">{{ item.name }}</span>
              <span v-if="item.type !== 'free'" class="ml-auto text-[0.65rem] font-semibold px-1.5 py-0.5 rounded-md bg-light-pink-100 text-raspberry-500">Free trial</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Calculator panel -->
      <div class="flex-1 min-w-0" id="calculator-panel">
      <!-- Income Tax Calculator -->
      <div v-if="activeCalculator === 'income-tax'" class="animate-fade-in-slide" :key="'income-tax'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Income Tax Calculator</h2>
          <p class="text-white/60 mt-1">Calculate your UK income tax and National Insurance contributions for {{ currentTaxYear }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Annual Gross Income</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(incomeTax.income)"
                    @input="parseInputValue($event, incomeTax, 'income')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                    placeholder="50,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Pension Contributions</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(incomeTax.pension)"
                    @input="parseInputValue($event, incomeTax, 'pension')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                    placeholder="0"
                  />
                </div>
                <p class="text-xs text-neutral-500 mt-1">Pension contributions reduce your taxable income</p>
              </div>

              <button
                @click="calculateIncomeTax"
                class="w-full px-6 py-4 bg-raspberry-500 text-white rounded-xl font-semibold hover:bg-raspberry-600 transition-all"
              >
                Calculate Tax
              </button>
            </div>

            <div v-if="incomeTax.result" class="bg-eggshell-500 rounded-xl p-6 border border-light-gray">
              <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Gross Income</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(incomeTax.result.gross) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Pension Contributions</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(incomeTax.result.pension) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Taxable Income</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(incomeTax.result.taxable) }}</span>
                </div>
                <div class="border-t border-light-gray my-2"></div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Income Tax</span>
                  <span class="font-semibold text-raspberry-600">-{{ formatCurrency(incomeTax.result.tax) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">National Insurance</span>
                  <span class="font-semibold text-raspberry-600">-{{ formatCurrency(incomeTax.result.ni) }}</span>
                </div>
                <div class="border-t border-light-gray my-2"></div>
                <div class="flex justify-between items-center py-3 bg-spring-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-horizon-500">Net Income</span>
                  <span class="font-bold text-2xl text-spring-600">{{ formatCurrency(incomeTax.result.net) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Effective Tax Rate</span>
                  <span class="font-semibold text-horizon-500">{{ incomeTax.result.effectiveRate }}%</span>
                </div>
              </div>
            </div>

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <p class="text-neutral-500">Enter your income and click Calculate to see results</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mortgage Repayment Calculator -->
      <div v-if="activeCalculator === 'mortgage'" class="animate-fade-in-slide" :key="'mortgage'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Building Foundations</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Mortgage Repayment Calculator</h2>
          <p class="text-white/60 mt-1">Calculate your monthly payments, total cost, and see how your mortgage is paid off over time</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Property Price</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(mortgage.propertyValue)"
                    @input="parseInputValue($event, mortgage, 'propertyValue')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="300,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Deposit</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(mortgage.deposit)"
                    @input="parseInputValue($event, mortgage, 'deposit')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    placeholder="30,000"
                  />
                </div>
                <div v-if="mortgage.propertyValue > 0 && mortgage.deposit > 0" class="mt-2 flex gap-4 text-xs text-neutral-500">
                  <span>Deposit: {{ mortgageDepositPercent }}%</span>
                  <span>Loan to Value: {{ mortgageLTV }}%</span>
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">
                  Mortgage Term: {{ mortgage.term }} years
                </label>
                <input
                  v-model.number="mortgage.term"
                  type="range"
                  min="5"
                  max="40"
                  step="1"
                  class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                />
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>5 yrs</span>
                  <span>40 yrs</span>
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Interest Rate (%)</label>
                <input
                  v-model.number="mortgage.interestRate"
                  type="number"
                  step="0.1"
                  class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                  placeholder="4.5"
                />
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Repayment Type</label>
                <div class="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    @click="mortgage.repaymentType = 'repayment'"
                    class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors"
                    :class="mortgage.repaymentType === 'repayment' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'"
                  >
                    Repayment
                  </button>
                  <button
                    type="button"
                    @click="mortgage.repaymentType = 'interest_only'"
                    class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors"
                    :class="mortgage.repaymentType === 'interest_only' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'"
                  >
                    Interest Only
                  </button>
                </div>
              </div>

              <button
                @click="calculateMortgage"
                class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors"
              >
                Calculate
              </button>
            </div>

            <!-- Results -->
            <div v-if="mortgage.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-500 font-semibold uppercase tracking-wider mb-1">Monthly Payment</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(mortgage.result.monthlyPayment) }}</p>
                <p class="text-xs text-neutral-500 mt-1">{{ mortgage.repaymentType === 'interest_only' ? 'Interest only — capital not repaid' : 'Capital and interest' }}</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Loan Amount</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(mortgage.result.loanAmount) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Loan to Value</p>
                  <p class="text-lg font-bold text-horizon-500">{{ mortgage.result.ltv }}%</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Repaid</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(mortgage.result.totalRepayment) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Interest</p>
                  <p class="text-lg font-bold text-raspberry-600">{{ formatCurrency(mortgage.result.totalInterest) }}</p>
                </div>
              </div>

              <!-- LTV warning -->
              <div v-if="mortgage.result.ltv > 90" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p class="text-sm text-violet-700">
                    Your Loan to Value is above 90%. Fewer mortgage deals are available at this level and rates tend to be higher. A larger deposit would improve your options.
                  </p>
                </div>
              </div>

              <!-- Interest only warning -->
              <div v-if="mortgage.repaymentType === 'interest_only'" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p class="text-sm text-violet-700">
                    With interest-only, you still owe the full {{ formatCurrency(mortgage.result.loanAmount) }} at the end of the term. You'll need a repayment strategy.
                  </p>
                </div>
              </div>

              <!-- Amortisation chart -->
              <div v-if="mortgage.repaymentType === 'repayment'" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-xs text-neutral-500 font-semibold mb-3">Capital vs Interest Over Term</p>
                <apexchart
                  type="bar"
                  height="220"
                  :options="mortgageChartOptions"
                  :series="mortgageChartSeries"
                />
              </div>

              <!-- CTA -->
              <div class="mt-4">
                <router-link to="/stage/building-foundations" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>Model your mortgage alongside your full financial picture in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <p class="text-neutral-500">Enter your mortgage details to see your repayment breakdown</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loan Repayment Calculator -->
      <div v-if="activeCalculator === 'loan'" class="animate-fade-in-slide" :key="'loan'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Loan Repayment Calculator</h2>
          <p class="text-white/60 mt-1">Calculate monthly payments and total interest on personal loans</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Loan Amount</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(loan.amount)"
                    @input="parseInputValue($event, loan, 'amount')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                    placeholder="10,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Interest Rate (% APR)</label>
                <input
                  v-model.number="loan.rate"
                  type="number"
                  step="0.1"
                  class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                  placeholder="8.9"
                />
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Loan Term (months)</label>
                <input
                  v-model.number="loan.term"
                  type="number"
                  class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
                  placeholder="36"
                />
              </div>

              <button
                @click="calculateLoan"
                class="w-full px-6 py-4 bg-raspberry-500 text-white rounded-xl font-semibold hover:bg-raspberry-600 transition-all"
              >
                Calculate Loan
              </button>
            </div>

            <div v-if="loan.result" class="bg-eggshell-500 rounded-xl p-6 border border-light-gray">
              <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Loan Amount</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(loan.result.amount) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Interest Rate</span>
                  <span class="font-semibold text-horizon-500">{{ loan.result.rate }}% APR</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Term</span>
                  <span class="font-semibold text-horizon-500">{{ loan.result.term }} months</span>
                </div>
                <div class="border-t border-light-gray my-2"></div>
                <div class="flex justify-between items-center py-3 bg-violet-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-horizon-500">Monthly Payment</span>
                  <span class="font-bold text-2xl text-violet-600">{{ formatCurrency(loan.result.monthlyPayment) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Total Interest</span>
                  <span class="font-semibold text-raspberry-600">{{ formatCurrency(loan.result.totalInterest) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Total Repayment</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(loan.result.totalRepayment) }}</span>
                </div>
              </div>
            </div>

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-neutral-500">Enter your loan details and click Calculate to see results</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Emergency Fund Calculator -->
      <div v-if="activeCalculator === 'emergency-fund'" class="animate-fade-in-slide" :key="'emergency-fund'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Emergency Fund Calculator</h2>
          <p class="text-white/60 mt-1">Calculate how much you should save for emergencies (3-6 months of expenses)</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Monthly Expenses</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(emergencyFund.monthlyExpenses)"
                    @input="parseInputValue($event, emergencyFund, 'monthlyExpenses')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                    placeholder="2,500"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Current Savings</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(emergencyFund.currentSavings)"
                    @input="parseInputValue($event, emergencyFund, 'currentSavings')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                    placeholder="5,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Target Months</label>
                <select
                  v-model.number="emergencyFund.targetMonths"
                  class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                >
                  <option :value="3">3 months (minimum recommended)</option>
                  <option :value="6">6 months (standard recommendation)</option>
                  <option :value="9">9 months (conservative)</option>
                  <option :value="12">12 months (very conservative)</option>
                </select>
              </div>

              <button
                @click="calculateEmergencyFund"
                class="w-full px-6 py-4 bg-raspberry-500 text-white rounded-xl font-semibold hover:bg-raspberry-600 transition-all"
              >
                Calculate Fund
              </button>
            </div>

            <div v-if="emergencyFund.result" class="bg-eggshell-500 rounded-xl p-6 border border-light-gray">
              <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Results
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Monthly Expenses</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(emergencyFund.result.monthlyExpenses) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Target Coverage</span>
                  <span class="font-semibold text-horizon-500">{{ emergencyFund.result.targetMonths }} months</span>
                </div>
                <div class="border-t border-light-gray my-2"></div>
                <div class="flex justify-between items-center py-3 bg-spring-50 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-horizon-500">Target Fund</span>
                  <span class="font-bold text-2xl text-spring-600">{{ formatCurrency(emergencyFund.result.targetAmount) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Current Savings</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(emergencyFund.result.currentSavings) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Shortfall</span>
                  <span :class="emergencyFund.result.shortfall > 0 ? 'font-semibold text-raspberry-600' : 'font-semibold text-spring-600'">
                    {{ emergencyFund.result.shortfall > 0 ? formatCurrency(emergencyFund.result.shortfall) : 'Fully funded!' }}
                  </span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Current Runway</span>
                  <span class="font-semibold text-horizon-500">{{ emergencyFund.result.currentRunway.toFixed(1) }} months</span>
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

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <p class="text-neutral-500">Enter your expenses and savings to see your emergency fund status</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pension Growth Calculator -->
      <div v-if="activeCalculator === 'pension'" class="animate-fade-in-slide" :key="'pension'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Pension Growth Calculator</h2>
          <p class="text-white/60 mt-1">Project your pension pot at retirement with regular contributions</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Current Pension Value</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(pension.currentValue)"
                    @input="parseInputValue($event, pension, 'currentValue')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="50,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Monthly Contribution</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">£</span>
                  <input
                    :value="formatInputDisplay(pension.monthlyContribution)"
                    @input="parseInputValue($event, pension, 'monthlyContribution')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="500"
                  />
                </div>
                <p class="text-xs text-neutral-500 mt-1">Including employer contributions and tax relief</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-horizon-500 mb-2">Current Age</label>
                  <input
                    v-model.number="pension.currentAge"
                    type="number"
                    class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="35"
                  />
                </div>

                <div>
                  <label class="block text-sm font-semibold text-horizon-500 mb-2">Retirement Age</label>
                  <input
                    v-model.number="pension.retirementAge"
                    type="number"
                    class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                    placeholder="65"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Expected Growth Rate (%)</label>
                <input
                  v-model.number="pension.growthRate"
                  type="number"
                  step="0.1"
                  class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                  placeholder="5.0"
                />
                <p class="text-xs text-neutral-500 mt-1">Typical range: 4-7% for balanced portfolios</p>
              </div>

              <button
                @click="calculatePension"
                class="w-full px-6 py-4 bg-raspberry-500 text-white rounded-xl font-semibold hover:bg-raspberry-600 transition-all"
              >
                Calculate Projection
              </button>
            </div>

            <div v-if="pension.result" class="bg-eggshell-500 rounded-xl p-6 border border-light-gray">
              <h3 class="text-lg font-bold text-horizon-500 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Projection
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Years to Retirement</span>
                  <span class="font-semibold text-horizon-500">{{ pension.result.yearsToRetirement }} years</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Current Value</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(pension.result.currentValue) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Total Contributions</span>
                  <span class="font-semibold text-horizon-500">{{ formatCurrency(pension.result.totalContributions) }}</span>
                </div>
                <div class="border-t border-light-gray my-2"></div>
                <div class="flex justify-between items-center py-3 bg-light-pink-100 -mx-6 px-6 rounded-lg">
                  <span class="font-bold text-horizon-500">Projected Pot at {{ pension.retirementAge }}</span>
                  <span class="font-bold text-2xl text-purple-600">{{ formatCurrency(pension.result.projectedValue) }}</span>
                </div>
                <div class="flex justify-between items-center py-2">
                  <span class="text-neutral-500">Investment Growth</span>
                  <span class="font-semibold text-spring-600">{{ formatCurrency(pension.result.investmentGrowth) }}</span>
                </div>
                <div class="mt-4 bg-eggshell-500 rounded-xl p-4 border border-light-gray">
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

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <p class="text-neutral-500">Enter your pension details to see your retirement projection</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Student Loan Repayment Calculator -->
      <div v-if="activeCalculator === 'student-loan'" class="animate-fade-in-slide" :key="'student-loan'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Starting Out</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Student Loan Repayment Calculator</h2>
          <p class="text-white/60 mt-1">Estimate your monthly repayments, total cost, and when your loan will be cleared</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Inputs -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Loan Plan</label>
                <select
                  v-model="studentLoan.plan"
                  class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                >
                  <option v-for="(plan, key) in studentLoan.plans" :key="key" :value="key">{{ plan.label }}</option>
                </select>
                <div class="mt-2 flex gap-4 text-xs text-neutral-500">
                  <span>Threshold: {{ formatCurrency(studentLoan.plans[studentLoan.plan].threshold) }}/yr</span>
                  <span>Interest: {{ studentLoan.plans[studentLoan.plan].rate }}%</span>
                  <span>Written off after: {{ studentLoan.plans[studentLoan.plan].writeOff }} years</span>
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Current Loan Balance</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(studentLoan.balance)"
                    @input="parseInputValue($event, studentLoan, 'balance')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                    placeholder="45,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Current Annual Salary</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(studentLoan.salary)"
                    @input="parseInputValue($event, studentLoan, 'salary')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all"
                    placeholder="30,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">
                  Expected Salary Growth: {{ studentLoan.salaryGrowth }}% per year
                </label>
                <input
                  v-model.number="studentLoan.salaryGrowth"
                  type="range"
                  min="0"
                  max="10"
                  step="0.5"
                  class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-spring-500"
                />
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>0%</span>
                  <span>10%</span>
                </div>
              </div>

              <button
                @click="calculateStudentLoan"
                class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors"
              >
                Calculate Repayments
              </button>
            </div>

            <!-- Results -->
            <div v-if="studentLoan.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-500 font-semibold uppercase tracking-wider mb-1">Monthly Repayment</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(studentLoan.result.monthlyRepayment) }}</p>
                <p class="text-xs text-neutral-500 mt-1">at your current salary</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Time to Repay</p>
                  <p class="text-lg font-bold text-horizon-500">{{ studentLoan.result.yearsToRepay }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Repaid</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(studentLoan.result.totalRepaid) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Interest</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(studentLoan.result.totalInterest) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Write-off Date</p>
                  <p class="text-lg font-bold text-horizon-500">{{ studentLoan.result.writeOffYear }}</p>
                </div>
              </div>

              <!-- Write-off warning -->
              <div v-if="studentLoan.result.writtenOff" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-sm font-semibold text-violet-700">Loan likely to be written off</p>
                    <p class="text-xs text-neutral-500 mt-1">
                      Based on your salary and growth projections, your loan balance of {{ formatCurrency(studentLoan.result.remainingAtWriteOff) }} would be written off after {{ studentLoan.plans[studentLoan.plan].writeOff }} years. You would repay {{ formatCurrency(studentLoan.result.totalRepaid) }} of the original {{ formatCurrency(studentLoan.balance) }} balance.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Fully repaid message -->
              <div v-else class="bg-spring-50 border border-spring-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-sm font-semibold text-spring-700">Loan fully repaid</p>
                    <p class="text-xs text-neutral-500 mt-1">
                      You would clear your loan in {{ studentLoan.result.yearsToRepay }} before the {{ studentLoan.plans[studentLoan.plan].writeOff }}-year write-off period.
                    </p>
                  </div>
                </div>
              </div>

              <!-- CTA -->
              <div class="mt-4">
                <router-link to="/stage/starting-out" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>Factor your student loan into your full financial plan in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                </svg>
                <p class="text-neutral-500">Enter your loan details to see your repayment projection</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Savings Goal Calculator -->
      <div v-if="activeCalculator === 'savings-goal'" class="animate-fade-in-slide" :key="'savings-goal'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Starting Out</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Savings Goal Calculator</h2>
          <p class="text-white/60 mt-1">See how long it takes to reach your savings target with compound interest</p>
        </div>

        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Inputs -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Target Amount</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(savingsGoal.target)"
                    @input="parseInputValue($event, savingsGoal, 'target')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500 transition-all"
                    placeholder="10,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Current Savings</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(savingsGoal.current)"
                    @input="parseInputValue($event, savingsGoal, 'current')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500 transition-all"
                    placeholder="1,000"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Monthly Contribution</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input
                    :value="formatInputDisplay(savingsGoal.monthly)"
                    @input="parseInputValue($event, savingsGoal, 'monthly')"
                    type="text"
                    inputmode="numeric"
                    class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-raspberry-500 focus:border-raspberry-500 transition-all"
                    placeholder="200"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">
                  Interest Rate / Expected Return: {{ savingsGoal.rate }}% per year
                </label>
                <input
                  v-model.number="savingsGoal.rate"
                  type="range"
                  min="0"
                  max="8"
                  step="0.25"
                  class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-raspberry-500"
                />
                <div class="flex justify-between text-xs text-neutral-500 mt-1">
                  <span>0%</span>
                  <span>8%</span>
                </div>
              </div>

              <button
                @click="calculateSavingsGoal"
                class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors"
              >
                Calculate
              </button>
            </div>

            <!-- Results -->
            <div v-if="savingsGoal.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-600 font-semibold uppercase tracking-wider mb-1">Time to Reach Goal</p>
                <p class="text-2xl font-bold text-horizon-500">{{ savingsGoal.result.yearsText }}</p>
                <p class="text-xs text-raspberry-600 mt-1">to save {{ formatCurrency(savingsGoal.target) }}</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Contributions</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(savingsGoal.result.totalContributions) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Interest Earned</p>
                  <p class="text-lg font-bold text-spring-600">{{ formatCurrency(savingsGoal.result.totalInterest) }}</p>
                </div>
              </div>

              <!-- Growth chart -->
              <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-xs text-neutral-500 font-semibold mb-3">Savings Growth Over Time</p>
                <apexchart
                  type="area"
                  height="200"
                  :options="savingsGoalChartOptions"
                  :series="savingsGoalChartSeries"
                />
              </div>

              <!-- CTA -->
              <div class="mt-4">
                <router-link to="/stage/starting-out" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>Set and track your savings goals in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>

            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                <p class="text-neutral-500">Enter your savings details to see your projection</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Mortgage Affordability Calculator -->
      <div v-if="activeCalculator === 'mortgage-afford'" class="animate-fade-in-slide" :key="'mortgage-afford'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Building Foundations</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Mortgage Affordability Calculator</h2>
          <p class="text-white/60 mt-1">Estimate how much you could borrow based on your income</p>
        </div>
        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Annual Salary (Applicant 1)</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(mortgageAfford.salary1)" @input="parseInputValue($event, mortgageAfford, 'salary1')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" placeholder="45,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Annual Salary (Applicant 2, optional)</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(mortgageAfford.salary2)" @input="parseInputValue($event, mortgageAfford, 'salary2')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" placeholder="0" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Other Monthly Debt Commitments</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(mortgageAfford.monthlyDebts)" @input="parseInputValue($event, mortgageAfford, 'monthlyDebts')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" placeholder="200" />
                </div>
                <p class="text-xs text-neutral-500 mt-1">Loans, car finance, credit card minimums, etc.</p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Deposit Available</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(mortgageAfford.deposit)" @input="parseInputValue($event, mortgageAfford, 'deposit')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" placeholder="25,000" />
                </div>
              </div>
              <button @click="calculateMortgageAfford" class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors">Calculate Affordability</button>
            </div>
            <div v-if="mortgageAfford.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-500 font-semibold uppercase tracking-wider mb-1">Estimated Maximum Borrowing</p>
                <div class="flex items-baseline gap-4">
                  <div>
                    <p class="text-3xl font-bold text-indigo-700">{{ formatCurrency(mortgageAfford.result.max4x) }}</p>
                    <p class="text-xs text-indigo-500">at 4x income</p>
                  </div>
                  <div>
                    <p class="text-3xl font-bold text-indigo-500">{{ formatCurrency(mortgageAfford.result.max45x) }}</p>
                    <p class="text-xs text-indigo-500">at 4.5x income</p>
                  </div>
                </div>
              </div>
              <div>
                <p class="text-xs text-neutral-500 font-semibold mb-2">Maximum Property Price by Deposit</p>
                <div class="grid grid-cols-3 gap-3">
                  <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray text-center">
                    <p class="text-xs text-neutral-500">10% deposit</p>
                    <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(mortgageAfford.result.property10) }}</p>
                  </div>
                  <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray text-center">
                    <p class="text-xs text-neutral-500">15% deposit</p>
                    <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(mortgageAfford.result.property15) }}</p>
                  </div>
                  <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray text-center">
                    <p class="text-xs text-neutral-500">20% deposit</p>
                    <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(mortgageAfford.result.property20) }}</p>
                  </div>
                </div>
              </div>
              <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-sm text-violet-700">Lenders assess affordability differently — this is a guide only. Your actual borrowing will depend on credit history, outgoings, and lender criteria.</p>
              </div>
              <div class="mt-4">
                <router-link to="/stage/building-foundations" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>See how a mortgage fits into your full financial plan in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>
            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                <p class="text-neutral-500">Enter your income details to see what you could borrow</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stamp Duty Calculator -->
      <div v-if="activeCalculator === 'stamp-duty'" class="animate-fade-in-slide" :key="'stamp-duty'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Building Foundations</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Stamp Duty Calculator</h2>
          <p class="text-white/80 mt-1">Calculate Stamp Duty Land Tax, Land and Buildings Transaction Tax, or Land Transaction Tax</p>
        </div>
        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Property Price</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(stampDuty.price)" @input="parseInputValue($event, stampDuty, 'price')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-horizon-500 focus:border-horizon-500 transition-all" placeholder="300,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Buyer Type</label>
                <select v-model="stampDuty.buyerType" class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-horizon-500 focus:border-horizon-500 transition-all">
                  <option value="first-time">First-time buyer</option>
                  <option value="home-mover">Home mover</option>
                  <option value="additional">Additional property</option>
                  <option value="non-uk">Non-UK resident</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Country</label>
                <select v-model="stampDuty.country" class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-horizon-500 focus:border-horizon-500 transition-all">
                  <option value="england">England &amp; Northern Ireland (SDLT)</option>
                  <option value="scotland">Scotland (LBTT)</option>
                  <option value="wales">Wales (LTT)</option>
                </select>
              </div>
              <button @click="calculateStampDuty" class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors">Calculate Stamp Duty</button>
            </div>
            <div v-if="stampDuty.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-horizon-400 font-semibold uppercase tracking-wider mb-1">{{ stampDuty.result.taxName }}</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(stampDuty.result.totalTax) }}</p>
                <p class="text-xs text-horizon-400 mt-1">Effective rate: {{ stampDuty.result.effectiveRate }}%</p>
              </div>
              <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-xs text-neutral-500 font-semibold mb-2">Breakdown by Band</p>
                <div class="space-y-1.5">
                  <div v-for="(band, i) in stampDuty.result.bands" :key="i" class="flex justify-between text-xs">
                    <span class="text-neutral-500">{{ band.label }}</span>
                    <span class="font-medium text-horizon-500">{{ formatCurrency(band.tax) }}</span>
                  </div>
                </div>
              </div>
              <div v-if="stampDuty.result.saving > 0" class="bg-spring-50 border border-spring-200 rounded-xl p-4">
                <p class="text-sm text-spring-700">First-time buyer relief saves you {{ formatCurrency(stampDuty.result.saving) }} compared to standard rates.</p>
              </div>
              <div v-if="stampDuty.buyerType === 'additional'" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-sm text-violet-700">Additional property surcharge of 5% has been applied to all bands.</p>
              </div>
              <div v-if="stampDuty.buyerType === 'non-uk'" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-sm text-violet-700">Non-UK resident surcharge of 2% has been applied to all bands.</p>
              </div>
              <div class="mt-4">
                <router-link to="/stage/building-foundations" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>Factor stamp duty into your home buying plan in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>
            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
                <p class="text-neutral-500">Enter a property price to see the stamp duty breakdown</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Compound Interest Calculator -->
      <div v-if="activeCalculator === 'compound-interest'" class="animate-fade-in-slide" :key="'compound-interest'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Building Foundations</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Compound Interest Calculator</h2>
          <p class="text-white/60 mt-1">See how your money grows over time with the power of compound interest</p>
        </div>
        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Initial Lump Sum</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(compoundInterest.lumpSum)" @input="parseInputValue($event, compoundInterest, 'lumpSum')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all" placeholder="5,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Monthly Contribution</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(compoundInterest.monthly)" @input="parseInputValue($event, compoundInterest, 'monthly')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-spring-500 focus:border-spring-500 transition-all" placeholder="200" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Annual Return: {{ compoundInterest.rate }}%</label>
                <input v-model.number="compoundInterest.rate" type="range" min="0" max="12" step="0.5" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-spring-500" />
                <div class="flex justify-between text-xs text-neutral-500 mt-1"><span>0%</span><span>12%</span></div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Investment Term: {{ compoundInterest.term }} years</label>
                <input v-model.number="compoundInterest.term" type="range" min="1" max="40" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-spring-500" />
                <div class="flex justify-between text-xs text-neutral-500 mt-1"><span>1 yr</span><span>40 yrs</span></div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Compound Frequency</label>
                <div class="grid grid-cols-2 gap-3">
                  <button type="button" @click="compoundInterest.frequency = 'monthly'" class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors" :class="compoundInterest.frequency === 'monthly' ? 'bg-spring-600 text-white border-spring-600' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Monthly</button>
                  <button type="button" @click="compoundInterest.frequency = 'annually'" class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors" :class="compoundInterest.frequency === 'annually' ? 'bg-spring-600 text-white border-spring-600' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Annually</button>
                </div>
              </div>
              <button @click="calculateCompoundInterest" class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors">Calculate Growth</button>
            </div>
            <div v-if="compoundInterest.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-500 font-semibold uppercase tracking-wider mb-1">Final Value</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(compoundInterest.result.finalValue) }}</p>
                <p class="text-xs text-neutral-500 mt-1">after {{ compoundInterest.term }} years</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Contributions</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(compoundInterest.result.totalContributions) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Growth Earned</p>
                  <p class="text-lg font-bold text-spring-600">{{ formatCurrency(compoundInterest.result.totalGrowth) }}</p>
                </div>
              </div>
              <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-xs text-neutral-500 font-semibold mb-3">Growth Over Time</p>
                <apexchart type="area" height="220" :options="compoundChartOptions" :series="compoundChartSeries" />
              </div>
              <div class="mt-4">
                <router-link to="/stage/building-foundations" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>See your investments in the context of your full financial picture in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>
            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                <p class="text-neutral-500">Enter your investment details to see projected growth</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Personal Loan Calculator -->
      <div v-if="activeCalculator === 'personal-loan'" class="animate-fade-in-slide" :key="'personal-loan'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Building Foundations</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Personal Loan Calculator</h2>
          <p class="text-white/60 mt-1">Calculate your monthly repayments and total cost of borrowing</p>
        </div>
        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Loan Amount</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(personalLoan.amount)" @input="parseInputValue($event, personalLoan, 'amount')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all" placeholder="10,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Annual Interest Rate (%)</label>
                <input v-model.number="personalLoan.rate" type="number" step="0.1" class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all" placeholder="6.9" />
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Loan Term: {{ personalLoan.term }} years</label>
                <input v-model.number="personalLoan.term" type="range" min="1" max="10" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-violet-500" />
                <div class="flex justify-between text-xs text-neutral-500 mt-1"><span>1 yr</span><span>10 yrs</span></div>
              </div>
              <button @click="calculatePersonalLoan" class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors">Calculate Repayments</button>
            </div>
            <div v-if="personalLoan.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-500 font-semibold uppercase tracking-wider mb-1">Monthly Repayment</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(personalLoan.result.monthlyPayment) }}</p>
                <p class="text-xs text-neutral-500 mt-1">over {{ personalLoan.term }} years</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Repaid</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(personalLoan.result.totalRepaid) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Total Interest</p>
                  <p class="text-lg font-bold text-raspberry-600">{{ formatCurrency(personalLoan.result.totalInterest) }}</p>
                </div>
              </div>
              <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <p class="text-sm text-violet-700">The APR (Annual Percentage Rate) may differ from the interest rate as it includes any fees. Always check the APR when comparing loan offers.</p>
              </div>
              <div class="mt-4">
                <router-link to="/stage/building-foundations" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>See how debt fits into your financial picture in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>
            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                <p class="text-neutral-500">Enter your loan details to see your repayment breakdown</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Pension Withdrawal Tax Calculator -->
      <div v-if="activeCalculator === 'pension-withdrawal'" class="animate-fade-in-slide" :key="'pension-withdrawal'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Enjoying Your Wealth</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Pension Withdrawal Tax Calculator</h2>
          <p class="text-white/80 mt-1">See how much tax you'll pay when withdrawing from your pension</p>
        </div>
        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Total Pension Pot</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(pensionWithdrawal.pot)" @input="parseInputValue($event, pensionWithdrawal, 'pot')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-horizon-500 focus:border-horizon-500 transition-all" placeholder="250,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Withdrawal Amount</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(pensionWithdrawal.withdrawal)" @input="parseInputValue($event, pensionWithdrawal, 'withdrawal')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-horizon-500 focus:border-horizon-500 transition-all" placeholder="50,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Withdrawal Type</label>
                <div class="grid grid-cols-2 gap-3">
                  <button type="button" @click="pensionWithdrawal.withdrawalType = 'lump'" class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors" :class="pensionWithdrawal.withdrawalType === 'lump' ? 'bg-horizon-500 text-white border-horizon-500' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Lump Sum</button>
                  <button type="button" @click="pensionWithdrawal.withdrawalType = 'annual'" class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors" :class="pensionWithdrawal.withdrawalType === 'annual' ? 'bg-horizon-500 text-white border-horizon-500' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Annual Income</button>
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Other Annual Income</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(pensionWithdrawal.otherIncome)" @input="parseInputValue($event, pensionWithdrawal, 'otherIncome')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-horizon-500 focus:border-horizon-500 transition-all" placeholder="12,570" />
                </div>
                <p class="text-xs text-neutral-500 mt-1">State pension, employment, rental income, etc.</p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Tax-Free Cash Already Taken?</label>
                <div class="grid grid-cols-2 gap-3">
                  <button type="button" @click="pensionWithdrawal.taxFreeTaken = false" class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors" :class="!pensionWithdrawal.taxFreeTaken ? 'bg-horizon-500 text-white border-horizon-500' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">No</button>
                  <button type="button" @click="pensionWithdrawal.taxFreeTaken = true" class="py-2.5 px-4 text-sm font-medium rounded-xl border transition-colors" :class="pensionWithdrawal.taxFreeTaken ? 'bg-horizon-500 text-white border-horizon-500' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Yes</button>
                </div>
              </div>
              <button @click="calculatePensionWithdrawal" class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors">Calculate Tax</button>
            </div>
            <div v-if="pensionWithdrawal.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-horizon-400 font-semibold uppercase tracking-wider mb-1">Net Amount Received</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(pensionWithdrawal.result.netReceived) }}</p>
                <p class="text-xs text-horizon-400 mt-1">from a {{ formatCurrency(pensionWithdrawal.withdrawal) }} withdrawal</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Tax-Free Portion</p>
                  <p class="text-lg font-bold text-spring-600">{{ formatCurrency(pensionWithdrawal.result.taxFree) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Taxable Portion</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(pensionWithdrawal.result.taxable) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Income Tax Due</p>
                  <p class="text-lg font-bold text-raspberry-600">{{ formatCurrency(pensionWithdrawal.result.taxDue) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Effective Tax Rate</p>
                  <p class="text-lg font-bold text-horizon-500">{{ pensionWithdrawal.result.effectiveRate }}%</p>
                </div>
              </div>
              <div v-if="pensionWithdrawal.result.taxable > higherRateThreshold - (pensionWithdrawal.otherIncome || 0)" class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <p class="text-sm text-violet-700">Taking large lump sums can push you into higher tax bands. Consider spreading withdrawals across tax years to reduce the overall tax burden.</p>
                </div>
              </div>
              <div class="mt-4">
                <router-link to="/stage/enjoying-your-wealth" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>Plan your pension withdrawals in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>
            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p class="text-neutral-500">Enter your pension withdrawal details to see the tax impact</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pension Tax Relief Calculator -->
      <div v-if="activeCalculator === 'pension-relief'" class="animate-fade-in-slide" :key="'pension-relief'">
        <div class="bg-horizon-500 rounded-2xl px-7 py-5 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-medium text-white">Planning Your Future</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white">Pension Tax Relief Calculator</h2>
          <p class="text-white/60 mt-1">See how much tax relief you get on your pension contributions</p>
        </div>
        <div class="bg-white rounded-2xl border border-light-gray p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Annual Pension Contribution</label>
                <div class="relative">
                  <span class="absolute left-4 top-3 text-neutral-500 font-medium">&pound;</span>
                  <input :value="formatInputDisplay(pensionRelief.contribution)" @input="parseInputValue($event, pensionRelief, 'contribution')" type="text" inputmode="numeric" class="w-full pl-10 pr-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all" placeholder="5,000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Tax Band</label>
                <select v-model="pensionRelief.taxBand" class="w-full px-4 py-3 border border-light-gray rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all">
                  <option value="basic">Basic rate (20%)</option>
                  <option value="higher">Higher rate (40%)</option>
                  <option value="additional">Additional rate (45%)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-horizon-500 mb-2">Pension Type</label>
                <div class="grid grid-cols-2 gap-3">
                  <button type="button" @click="pensionRelief.pensionType = 'relief-at-source'" class="py-2.5 px-3 text-sm font-medium rounded-xl border transition-colors" :class="pensionRelief.pensionType === 'relief-at-source' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Relief at Source</button>
                  <button type="button" @click="pensionRelief.pensionType = 'net-pay'" class="py-2.5 px-3 text-sm font-medium rounded-xl border transition-colors" :class="pensionRelief.pensionType === 'net-pay' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-horizon-500 border-light-gray hover:bg-eggshell-500'">Net Pay</button>
                </div>
                <p class="text-xs text-neutral-500 mt-2">
                  <span v-if="pensionRelief.pensionType === 'relief-at-source'">Relief at source: you pay from net pay, provider claims 20% from HMRC. Higher/additional rate taxpayers claim the rest via self-assessment.</span>
                  <span v-else>Net pay: contributions taken before tax from your gross salary. Tax relief is automatic — nothing to claim.</span>
                </p>
              </div>
              <button @click="calculatePensionRelief" class="w-full py-3 bg-raspberry-500 text-white font-semibold rounded-xl hover:bg-raspberry-600 transition-colors">Calculate Relief</button>
            </div>
            <div v-if="pensionRelief.result" class="space-y-4">
              <div class="bg-light-pink-100 rounded-xl p-6">
                <p class="text-xs text-raspberry-500 font-semibold uppercase tracking-wider mb-1">Total Pension Contribution (Gross)</p>
                <p class="text-2xl font-bold text-horizon-500">{{ formatCurrency(pensionRelief.result.gross) }}</p>
                <p class="text-xs text-neutral-500 mt-1">from {{ formatCurrency(pensionRelief.result.effectiveCost) }} effective cost to you</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Your Contribution (Net)</p>
                  <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(pensionRelief.result.netContribution) }}</p>
                </div>
                <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                  <p class="text-xs text-neutral-500">Government Top-Up (20%)</p>
                  <p class="text-lg font-bold text-spring-600">{{ formatCurrency(pensionRelief.result.basicRelief) }}</p>
                </div>
              </div>
              <div v-if="pensionRelief.result.additionalRelief > 0" class="bg-spring-50 border border-spring-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <div>
                    <p class="text-sm font-semibold text-spring-700">Additional relief: {{ formatCurrency(pensionRelief.result.additionalRelief) }}</p>
                    <p class="text-xs text-neutral-500 mt-1" v-if="pensionRelief.pensionType === 'relief-at-source'">As a {{ pensionRelief.taxBand === 'higher' ? 'higher' : 'additional' }} rate taxpayer with a relief at source pension, you need to claim this {{ formatCurrency(pensionRelief.result.additionalRelief) }} via your self-assessment tax return.</p>
                    <p class="text-xs text-neutral-500 mt-1" v-else>With net pay, your full relief is applied automatically through your payslip. No self-assessment claim needed.</p>
                  </div>
                </div>
              </div>
              <div class="bg-eggshell-500 rounded-xl p-4 border border-light-gray">
                <div class="flex justify-between text-sm mb-2">
                  <span class="text-neutral-500">Effective cost to you</span>
                  <span class="font-bold text-horizon-500">{{ formatCurrency(pensionRelief.result.effectiveCost) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-neutral-500">Total relief received</span>
                  <span class="font-bold text-spring-600">{{ formatCurrency(pensionRelief.result.totalRelief) }}</span>
                </div>
              </div>
              <div class="mt-4">
                <router-link to="/stage/planning-your-future" class="block bg-light-blue-500 rounded-xl px-5 py-4 text-white text-sm font-semibold hover:bg-light-blue-600 transition-all">
                  <span class="flex items-center justify-between">
                    <span>Track your pension contributions in Fynla</span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                  </span>
                </router-link>
              </div>
            </div>
            <div v-else class="bg-eggshell-500 rounded-xl p-6 border border-light-gray flex items-center justify-center">
              <div class="text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
                <p class="text-neutral-500">Enter your contribution details to see your tax relief</p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- close calculator-panel -->
    </div><!-- close flex wrapper -->

    <!-- CTA Section -->
    <div class="bg-light-pink-100 py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-horizon-500 mb-4">Ready for comprehensive financial planning?</h2>
        <p class="text-neutral-500 mb-8">Create a free account to access all planning tools and get a complete view of your finances.</p>
        <router-link
          to="/register"
          class="inline-flex items-center px-8 py-4 bg-raspberry-500 text-white rounded-xl font-semibold text-lg hover:bg-raspberry-600 transition-all shadow-lg hover:shadow-xl"
        >
          Get started free
          <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
        </router-link>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { getCurrentTaxYear } from '@/utils/dateFormatter';
import {
  PERSONAL_ALLOWANCE, PERSONAL_ALLOWANCE_TAPER_THRESHOLD, HIGHER_RATE_THRESHOLD, ADDITIONAL_RATE_THRESHOLD,
  BASIC_RATE, HIGHER_RATE, ADDITIONAL_RATE,
  NI_PRIMARY_THRESHOLD, NI_UPPER_EARNINGS_LIMIT, NI_BASIC_RATE, NI_ADDITIONAL_RATE,
  SDLT_STANDARD_BANDS, SDLT_FTB_BANDS, SDLT_FTB_MAX_PRICE, SDLT_ADDITIONAL_SURCHARGE, SDLT_NON_UK_SURCHARGE,
  LBTT_BANDS, LBTT_ADDITIONAL_SURCHARGE, LBTT_NON_UK_SURCHARGE,
  LTT_BANDS, LTT_ADDITIONAL_SURCHARGE, LTT_NON_UK_SURCHARGE,
  PENSION_TAX_FREE_RATE, PENSION_TAX_FREE_LUMP_SUM_LIMIT,
  STUDENT_LOAN_REPAYMENT_RATE,
} from '@/constants/taxConfig';

export default {
  name: 'CalculatorsPage',
  mixins: [currencyMixin],

  components: {
    PublicLayout,
  },

  data() {
    return {
      activeCalculator: 'income-tax',
      activeStage: 'Planning Your Future',
      expandedStages: { 'Starting Out': true, 'Building Foundations': true, 'Protecting and Growing': false, 'Planning Your Future': true, 'Enjoying Your Wealth': false },
      calculatorStages: [
        {
          name: 'Starting Out', colour: '#1D9E75', icon: 'M13 10V3L4 14h7v7l9-11h-7z',
          items: [
            { id: 'student-loan', name: 'Student Loan Repayment', description: 'Estimate repayments, write-off date, and total cost by plan type', icon: '🎓', type: 'free' },
            { id: 'savings-goal', name: 'Savings Goal', description: 'See how long it takes to reach your savings target with compound interest', icon: '✨', type: 'free' },
            { id: 'emergency-fund', name: 'Emergency Fund', description: 'Calculate how many months of expenses you have covered', icon: '🛡', type: 'free' },
          ],
        },
        {
          name: 'Building Foundations', colour: '#5DCAA5', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
          items: [
            { id: 'mortgage', name: 'Mortgage Repayment', description: 'Monthly payments, total interest, and amortisation breakdown', icon: '🏠', type: 'free' },
            { id: 'mortgage-afford', name: 'Mortgage Affordability', description: 'How much could you borrow based on your income?', icon: '🔑', type: 'free' },
            { id: 'stamp-duty', name: 'Stamp Duty', description: 'SDLT, LBTT, or LTT breakdown for England, Scotland, or Wales', icon: '📜', type: 'free' },
            { id: 'personal-loan', name: 'Personal Loan', description: 'Monthly repayments and total cost of borrowing', icon: '💷', type: 'free' },
            { id: 'compound-interest', name: 'Compound Interest', description: 'See how your money grows over time with compound returns', icon: '📈', type: 'free' },
          ],
        },
        {
          name: 'Protecting and Growing', colour: '#378ADD', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
          items: [
            { id: 'life-insurance', name: 'Life Insurance Needs', description: 'How much cover does my family actually need?', icon: '🛡️', type: 'gated-free' },
            { id: 'income-protection', name: 'Income Protection', description: 'What would I need if I couldn\'t work?', icon: '☂️', type: 'gated-free' },
          ],
        },
        {
          name: 'Planning Your Future', colour: '#7F77DD', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
          items: [
            { id: 'income-tax', name: 'Income Tax', description: `Calculate your UK income tax and National Insurance for ${getCurrentTaxYear()}`, icon: '🧮', type: 'free' },
            { id: 'pension', name: 'Pension Growth', description: 'Project your pension pot value at retirement', icon: '📊', type: 'free' },
            { id: 'pension-relief', name: 'Pension Tax Relief', description: 'See how much tax relief you get on pension contributions', icon: '🧾', type: 'free' },
            { id: 'salary-sacrifice', name: 'Salary Sacrifice', description: 'Take-home pay vs pension boost — what\'s the real trade-off?', icon: '⚖️', type: 'gated-free' },
            { id: 'retirement-budget', name: 'Retirement Budget Planner', description: 'Detailed income vs spending plan for retirement', icon: '📋', type: 'gated-paid' },
          ],
        },
        {
          name: 'Enjoying Your Wealth', colour: '#B8956A', icon: 'M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7',
          items: [
            { id: 'pension-withdrawal', name: 'Pension Withdrawal Tax', description: 'See how much tax you\'ll pay when withdrawing from your pension', icon: '💰', type: 'free' },
            { id: 'iht-checker', name: 'Inheritance Tax Exposure Checker', description: 'Estimated inheritance tax liability with full breakdown', icon: '🏛️', type: 'gated-free' },
            { id: 'drawdown-runway', name: 'Pension Drawdown / Runway', description: 'How long will my pension pot last?', icon: '⏳', type: 'gated-free' },
            { id: 'annuity-vs-drawdown', name: 'Annuity vs Drawdown Comparison', description: 'Which gives you more over your lifetime?', icon: '⚖️', type: 'gated-paid' },
          ],
        },
      ],
      incomeTax: {
        income: 50000,
        pension: 0,
        result: null,
      },
      mortgage: {
        income: null,
        propertyValue: 300000,
        deposit: 30000,
        interestRate: 4.5,
        term: 25,
        repaymentType: 'repayment',
        result: null,
      },
      loan: {
        amount: 10000,
        rate: 8.9,
        term: 3,
        result: null,
      },
      emergencyFund: {
        monthlyExpenses: 2500,
        currentSavings: 5000,
        targetMonths: 6,
        result: null,
      },
      pension: {
        currentValue: 50000,
        monthlyContribution: 500,
        currentAge: 35,
        retirementAge: 65,
        growthRate: 5.0,
        result: null,
      },
      savingsGoal: {
        target: 10000,
        current: 1000,
        monthly: 200,
        rate: 4,
        result: null,
      },
      mortgageAfford: {
        salary1: 45000,
        salary2: 0,
        monthlyDebts: 200,
        deposit: 25000,
        result: null,
      },
      stampDuty: {
        price: 300000,
        buyerType: 'home-mover',
        country: 'england',
        result: null,
      },
      compoundInterest: {
        lumpSum: 5000,
        monthly: 200,
        rate: 6,
        term: 20,
        frequency: 'monthly',
        result: null,
      },
      personalLoan: {
        amount: 10000,
        rate: 6.9,
        term: 5,
        result: null,
      },
      pensionWithdrawal: {
        pot: 250000,
        withdrawal: 50000,
        withdrawalType: 'lump',
        otherIncome: 12570,
        taxFreeTaken: false,
        result: null,
      },
      pensionRelief: {
        contribution: 5000,
        taxBand: 'basic',
        pensionType: 'relief-at-source',
        result: null,
      },
      studentLoan: {
        plan: 'plan2',
        balance: 45000,
        salary: 30000,
        salaryGrowth: 3,
        result: null,
        plans: {
          plan1: { label: 'Plan 1', threshold: 24990, rate: 6.25, writeOff: 25 },
          plan2: { label: 'Plan 2', threshold: 27295, rate: 7.3, writeOff: 30 },
          plan4: { label: 'Plan 4 (Scotland)', threshold: 31395, rate: 6.25, writeOff: 30 },
          plan5: { label: 'Plan 5', threshold: 25000, rate: 7.3, writeOff: 40 },
        },
      },
    };
  },

  mounted() {
    document.title = 'Free Financial Calculators — UK Tax, Mortgage, Pension | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Free UK financial calculators for income tax, mortgage repayments, pension growth, stamp duty, student loans, and more. No sign-up required.');

    // Open specific calculator from query param (e.g. ?calc=student-loan)
    const calcParam = this.$route.query.calc;
    if (calcParam) {
      this.activeCalculator = calcParam;
    }
  },

  computed: {
    higherRateThreshold() {
      return HIGHER_RATE_THRESHOLD;
    },
    currentTaxYear() {
      return getCurrentTaxYear();
    },
    activeStageItems() {
      const stage = this.calculatorStages.find(s => s.name === this.activeStage);
      return stage ? stage.items : [];
    },

    mortgageDepositPercent() {
      if (!this.mortgage.propertyValue || !this.mortgage.deposit) return 0;
      return Math.round((this.mortgage.deposit / this.mortgage.propertyValue) * 100);
    },
    mortgageLTV() {
      return 100 - this.mortgageDepositPercent;
    },
    mortgageChartOptions() {
      if (!this.mortgage.result?.amortisation) return {};
      return {
        chart: { type: 'bar', stacked: true, toolbar: { show: false } },
        colors: ['#4f46e5', '#E83E6D'],
        plotOptions: { bar: { columnWidth: '70%', borderRadius: 2 } },
        xaxis: {
          categories: this.mortgage.result.amortisation.map(d => `Yr ${d.year}`),
          labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: 0, hideOverlappingLabels: true },
        },
        yaxis: { labels: { formatter: (v) => '£' + Math.round(v / 1000) + 'k', style: { fontSize: '10px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: (v) => '£' + Math.round(v).toLocaleString() } },
        legend: { position: 'top', fontSize: '11px' },
        dataLabels: { enabled: false },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
      };
    },
    mortgageChartSeries() {
      if (!this.mortgage.result?.amortisation) return [];
      return [
        { name: 'Capital', data: this.mortgage.result.amortisation.map(d => d.capital) },
        { name: 'Interest', data: this.mortgage.result.amortisation.map(d => d.interest) },
      ];
    },
    savingsGoalChartOptions() {
      if (!this.savingsGoal.result?.chartData) return {};
      return {
        chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false } },
        colors: ['#E83E6D', '#94a3b8'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: {
          categories: this.savingsGoal.result.chartData.map(d => {
            const y = Math.floor(d.month / 12);
            const m = d.month % 12;
            return d.month === 0 ? 'Now' : (y > 0 ? `${y}y${m > 0 ? ` ${m}m` : ''}` : `${m}m`);
          }),
          labels: { style: { fontSize: '10px', colors: '#94a3b8' } },
        },
        yaxis: { labels: { formatter: (v) => '£' + Math.round(v / 1000) + 'k', style: { fontSize: '10px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: (v) => '£' + v.toLocaleString() } },
        legend: { position: 'top', fontSize: '11px' },
        dataLabels: { enabled: false },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
      };
    },
    savingsGoalChartSeries() {
      if (!this.savingsGoal.result?.chartData) return [];
      return [
        { name: 'Total Balance', data: this.savingsGoal.result.chartData.map(d => d.balance) },
        { name: 'Contributions Only', data: this.savingsGoal.result.chartData.map(d => d.contributions) },
      ];
    },
    compoundChartOptions() {
      if (!this.compoundInterest.result?.chartData) return {};
      return {
        chart: { type: 'area', toolbar: { show: false } },
        colors: ['#20B486', '#94a3b8'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: {
          categories: this.compoundInterest.result.chartData.map(d => d.year === 0 ? 'Now' : `Yr ${d.year}`),
          labels: { style: { fontSize: '10px', colors: '#94a3b8' } },
        },
        yaxis: { labels: { formatter: (v) => '£' + Math.round(v / 1000) + 'k', style: { fontSize: '10px', colors: '#94a3b8' } } },
        tooltip: { y: { formatter: (v) => '£' + Math.round(v).toLocaleString() } },
        legend: { position: 'top', fontSize: '11px' },
        dataLabels: { enabled: false },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
      };
    },
    compoundChartSeries() {
      if (!this.compoundInterest.result?.chartData) return [];
      return [
        { name: 'With Contributions', data: this.compoundInterest.result.chartData.map(d => d.withContributions) },
        { name: 'Without Contributions', data: this.compoundInterest.result.chartData.map(d => d.withoutContributions) },
      ];
    },
  },

  methods: {
    toggleStage(name) {
      this.expandedStages[name] = !this.expandedStages[name];
    },

    selectCalculator(id) {
      this.activeCalculator = id;
      this.$nextTick(() => {
        const el = document.getElementById('calculator-panel');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    },

    calculateIncomeTax() {
      const income = this.incomeTax.income || 0;
      const pension = this.incomeTax.pension || 0;
      const taxable = income - pension;

      // Personal Allowance with taper above £100k (reduces £1 per £2 over threshold)
      let personalAllowance = PERSONAL_ALLOWANCE;
      if (taxable > PERSONAL_ALLOWANCE_TAPER_THRESHOLD) {
        personalAllowance = Math.max(0, PERSONAL_ALLOWANCE - Math.floor((taxable - PERSONAL_ALLOWANCE_TAPER_THRESHOLD) / 2));
      }

      let tax = 0;
      let remaining = taxable;

      // Personal allowance
      if (remaining > personalAllowance) {
        remaining -= personalAllowance;
      } else {
        remaining = 0;
      }

      // Basic rate
      if (remaining > 0) {
        const basicRateBand = Math.min(remaining, HIGHER_RATE_THRESHOLD - personalAllowance);
        tax += basicRateBand * BASIC_RATE;
        remaining -= basicRateBand;
      }

      // Higher rate
      if (remaining > 0) {
        const higherRateBand = Math.min(remaining, ADDITIONAL_RATE_THRESHOLD - HIGHER_RATE_THRESHOLD);
        tax += higherRateBand * HIGHER_RATE;
        remaining -= higherRateBand;
      }

      // Additional rate
      if (remaining > 0) {
        tax += remaining * ADDITIONAL_RATE;
      }

      // National Insurance
      let ni = 0;
      if (income > NI_PRIMARY_THRESHOLD) {
        const niableIncome = Math.min(income - NI_PRIMARY_THRESHOLD, NI_UPPER_EARNINGS_LIMIT - NI_PRIMARY_THRESHOLD);
        ni = niableIncome * NI_BASIC_RATE;
        if (income > NI_UPPER_EARNINGS_LIMIT) {
          ni += (income - NI_UPPER_EARNINGS_LIMIT) * NI_ADDITIONAL_RATE;
        }
      }

      const net = income - tax - ni;
      const effectiveRate = income > 0 ? ((tax + ni) / income * 100).toFixed(1) : '0.0';

      this.incomeTax.result = {
        gross: income,
        pension: pension,
        taxable: taxable,
        personalAllowance: personalAllowance,
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
      const annualRate = this.mortgage.interestRate / 100;
      const monthlyRate = annualRate / 12;
      const term = this.mortgage.term || 25;
      const numberOfPayments = term * 12;
      const isInterestOnly = this.mortgage.repaymentType === 'interest_only';

      if (loanAmount <= 0 || annualRate <= 0) return;

      let monthlyPayment, totalRepayment, totalInterest;

      if (isInterestOnly) {
        monthlyPayment = loanAmount * monthlyRate;
        totalRepayment = (monthlyPayment * numberOfPayments) + loanAmount;
        totalInterest = monthlyPayment * numberOfPayments;
      } else {
        monthlyPayment = loanAmount *
          (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) /
          (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
        totalRepayment = monthlyPayment * numberOfPayments;
        totalInterest = totalRepayment - loanAmount;
      }

      const ltv = propertyValue > 0 ? Math.round((loanAmount / propertyValue) * 100) : 0;

      // Build amortisation data (yearly) for repayment mortgages
      let amortisation = null;
      if (!isInterestOnly) {
        amortisation = [];
        let balance = loanAmount;
        for (let year = 1; year <= term; year++) {
          let yearCapital = 0;
          let yearInterest = 0;
          for (let m = 0; m < 12; m++) {
            const interestPortion = balance * monthlyRate;
            const capitalPortion = monthlyPayment - interestPortion;
            yearCapital += capitalPortion;
            yearInterest += interestPortion;
            balance -= capitalPortion;
          }
          amortisation.push({
            year,
            capital: Math.round(yearCapital),
            interest: Math.round(yearInterest),
          });
        }
      }

      this.mortgage.result = {
        loanAmount: loanAmount,
        ltv: ltv,
        monthlyPayment: Math.round(monthlyPayment),
        totalInterest: Math.round(totalInterest),
        totalRepayment: Math.round(totalRepayment),
        amortisation: amortisation,
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

    calculateSavingsGoal() {
      const target = this.savingsGoal.target || 0;
      const current = this.savingsGoal.current || 0;
      const monthly = this.savingsGoal.monthly || 0;
      const annualRate = (this.savingsGoal.rate || 0) / 100;
      const monthlyRate = annualRate / 12;

      if (target <= 0 || monthly <= 0) return;
      if (current >= target) {
        this.savingsGoal.result = { months: 0, yearsText: 'Already reached', totalContributions: 0, totalInterest: 0, chartData: [] };
        return;
      }

      let balance = current;
      let months = 0;
      let totalContributions = current;
      const chartData = [{ month: 0, balance: Math.round(current), contributions: Math.round(current) }];

      while (balance < target && months < 600) {
        const interest = balance * monthlyRate;
        balance += interest + monthly;
        totalContributions += monthly;
        months++;
        if (months % 3 === 0 || balance >= target) {
          chartData.push({ month: months, balance: Math.round(Math.min(balance, target)), contributions: Math.round(totalContributions) });
        }
      }

      const years = Math.floor(months / 12);
      const remainingMonths = months % 12;
      let yearsText = '';
      if (years > 0 && remainingMonths > 0) yearsText = `${years} yr ${remainingMonths} mo`;
      else if (years > 0) yearsText = `${years} year${years > 1 ? 's' : ''}`;
      else yearsText = `${remainingMonths} month${remainingMonths > 1 ? 's' : ''}`;

      const totalInterest = Math.round(balance - totalContributions);

      this.savingsGoal.result = {
        months,
        yearsText,
        totalContributions: Math.round(totalContributions - current),
        totalInterest: Math.max(0, totalInterest),
        chartData,
      };
    },

    calculateStudentLoan() {
      const plan = this.studentLoan.plans[this.studentLoan.plan];
      let balance = this.studentLoan.balance || 0;
      let salary = this.studentLoan.salary || 0;
      const growthRate = (this.studentLoan.salaryGrowth || 0) / 100;
      const interestRate = plan.rate / 100;
      const threshold = plan.threshold;
      const writeOffYears = plan.writeOff;

      if (balance <= 0 || salary <= 0) return;

      let totalRepaid = 0;
      let year = 0;
      let repaid = false;

      // Current monthly repayment at starting salary
      const initialAnnualRepayment = salary > threshold ? (salary - threshold) * STUDENT_LOAN_REPAYMENT_RATE : 0;
      const monthlyRepayment = Math.round(initialAnnualRepayment / 12);

      // Simulate year by year
      let runningBalance = balance;
      let currentSalary = salary;

      while (year < writeOffYears && runningBalance > 0) {
        // Interest accrues on balance
        const interest = runningBalance * interestRate;
        runningBalance += interest;

        // Annual repayment based on current salary
        const annualRepayment = currentSalary > threshold ? (currentSalary - threshold) * STUDENT_LOAN_REPAYMENT_RATE : 0;

        if (annualRepayment >= runningBalance) {
          totalRepaid += runningBalance;
          runningBalance = 0;
          repaid = true;
          year++;
          break;
        }

        runningBalance -= annualRepayment;
        totalRepaid += annualRepayment;
        year++;

        // Salary grows
        currentSalary *= (1 + growthRate);
      }

      const writtenOff = !repaid && runningBalance > 0;
      const totalInterest = totalRepaid - balance + (writtenOff ? runningBalance : 0);
      const currentYear = new Date().getFullYear();

      this.studentLoan.result = {
        monthlyRepayment: monthlyRepayment,
        yearsToRepay: repaid ? (year + (year === 1 ? ' year' : ' years')) : ('Written off after ' + writeOffYears + ' years'),
        totalRepaid: Math.round(totalRepaid),
        totalInterest: Math.max(0, Math.round(totalInterest)),
        writeOffYear: String(currentYear + writeOffYears),
        writtenOff: writtenOff,
        remainingAtWriteOff: writtenOff ? Math.round(runningBalance) : 0,
      };
    },

    calculateMortgageAfford() {
      const salary1 = this.mortgageAfford.salary1 || 0;
      const salary2 = this.mortgageAfford.salary2 || 0;
      const monthlyDebts = this.mortgageAfford.monthlyDebts || 0;
      const deposit = this.mortgageAfford.deposit || 0;
      const totalIncome = salary1 + salary2;
      if (totalIncome <= 0) return;
      const debtAdjustment = monthlyDebts * 12 * 4;
      const max4x = Math.max(0, (totalIncome * 4) - debtAdjustment);
      const max45x = Math.max(0, (totalIncome * 4.5) - debtAdjustment);
      this.mortgageAfford.result = {
        totalIncome,
        max4x: Math.round(max4x),
        max45x: Math.round(max45x),
        property10: Math.round(max45x / 0.90),
        property15: Math.round(max45x / 0.85),
        property20: Math.round(max45x / 0.80),
      };
    },

    calculateStampDuty() {
      const price = this.stampDuty.price || 0;
      if (price <= 0) return;
      const buyerType = this.stampDuty.buyerType;
      const country = this.stampDuty.country;
      let bands, taxName, surcharge = 0;

      if (country === 'scotland') {
        taxName = 'Land and Buildings Transaction Tax';
        bands = LBTT_BANDS;
        if (buyerType === 'additional') surcharge = LBTT_ADDITIONAL_SURCHARGE;
        if (buyerType === 'non-uk') surcharge = LBTT_NON_UK_SURCHARGE;
      } else if (country === 'wales') {
        taxName = 'Land Transaction Tax';
        bands = LTT_BANDS;
        if (buyerType === 'additional') surcharge = LTT_ADDITIONAL_SURCHARGE;
        if (buyerType === 'non-uk') surcharge = LTT_NON_UK_SURCHARGE;
      } else {
        taxName = 'Stamp Duty Land Tax';
        if (buyerType === 'first-time' && price <= SDLT_FTB_MAX_PRICE) {
          bands = SDLT_FTB_BANDS;
        } else {
          bands = SDLT_STANDARD_BANDS;
        }
        if (buyerType === 'additional') surcharge = SDLT_ADDITIONAL_SURCHARGE;
        if (buyerType === 'non-uk') surcharge = SDLT_NON_UK_SURCHARGE;
      }

      let totalTax = 0;
      let prev = 0;
      const bandResults = [];
      for (const band of bands) {
        const taxable = Math.min(price, band.threshold) - prev;
        if (taxable <= 0) { prev = band.threshold; continue; }
        const effectiveRate = band.rate + surcharge;
        const tax = taxable * (effectiveRate / 100);
        totalTax += tax;
        bandResults.push({ label: `£${prev.toLocaleString()} - £${Math.min(price, band.threshold).toLocaleString()} at ${effectiveRate}%`, tax: Math.round(tax) });
        prev = band.threshold;
        if (prev >= price) break;
      }

      let saving = 0;
      if (buyerType === 'first-time' && country === 'england' && price <= SDLT_FTB_MAX_PRICE) {
        let standardTax = 0;
        let sPrev = 0;
        for (const b of SDLT_STANDARD_BANDS) {
          const t = Math.min(price, b.threshold) - sPrev;
          if (t > 0) standardTax += t * (b.rate / 100);
          sPrev = b.threshold;
          if (sPrev >= price) break;
        }
        saving = Math.round(standardTax - totalTax);
      }

      this.stampDuty.result = {
        taxName,
        totalTax: Math.round(totalTax),
        effectiveRate: price > 0 ? ((totalTax / price) * 100).toFixed(1) : '0',
        bands: bandResults,
        saving,
      };
    },

    calculateCompoundInterest() {
      const lumpSum = this.compoundInterest.lumpSum || 0;
      const monthly = this.compoundInterest.monthly || 0;
      const annualRate = (this.compoundInterest.rate || 0) / 100;
      const term = this.compoundInterest.term || 1;
      const isMonthly = this.compoundInterest.frequency === 'monthly';
      if (lumpSum <= 0 && monthly <= 0) return;

      const chartData = [{ year: 0, withContributions: lumpSum, withoutContributions: lumpSum }];
      let balanceWith = lumpSum;
      let balanceWithout = lumpSum;
      let totalContributions = lumpSum;

      for (let y = 1; y <= term; y++) {
        if (isMonthly) {
          const mr = annualRate / 12;
          for (let m = 0; m < 12; m++) {
            balanceWith = balanceWith * (1 + mr) + monthly;
            balanceWithout = balanceWithout * (1 + mr);
          }
        } else {
          balanceWith = (balanceWith + monthly * 12) * (1 + annualRate);
          balanceWithout = balanceWithout * (1 + annualRate);
        }
        totalContributions += monthly * 12;
        chartData.push({ year: y, withContributions: Math.round(balanceWith), withoutContributions: Math.round(balanceWithout) });
      }

      this.compoundInterest.result = {
        finalValue: Math.round(balanceWith),
        totalContributions: Math.round(totalContributions),
        totalGrowth: Math.round(balanceWith - totalContributions),
        chartData,
      };
    },

    calculatePersonalLoan() {
      const amount = this.personalLoan.amount || 0;
      const annualRate = (this.personalLoan.rate || 0) / 100;
      const term = this.personalLoan.term || 1;
      if (amount <= 0 || annualRate <= 0) return;
      const monthlyRate = annualRate / 12;
      const months = term * 12;
      const monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
      const totalRepaid = monthlyPayment * months;
      this.personalLoan.result = {
        monthlyPayment: Math.round(monthlyPayment),
        totalRepaid: Math.round(totalRepaid),
        totalInterest: Math.round(totalRepaid - amount),
      };
    },

    calculatePensionWithdrawal() {
      const pot = this.pensionWithdrawal.pot || 0;
      const withdrawal = Math.min(this.pensionWithdrawal.withdrawal || 0, pot);
      const otherIncome = this.pensionWithdrawal.otherIncome || 0;
      const taxFreeTaken = this.pensionWithdrawal.taxFreeTaken;
      if (withdrawal <= 0) return;

      // Tax-free cash: 25% of pot, max lifetime limit
      let taxFree = 0;
      if (!taxFreeTaken) {
        taxFree = Math.min(withdrawal * PENSION_TAX_FREE_RATE, pot * PENSION_TAX_FREE_RATE, PENSION_TAX_FREE_LUMP_SUM_LIMIT);
      }
      const taxable = withdrawal - taxFree;

      // Calculate income tax on taxable portion + other income
      const totalTaxableIncome = otherIncome + taxable;
      const taxOnTotal = this._calculateIncomeTax2526(totalTaxableIncome);
      const taxOnOther = this._calculateIncomeTax2526(otherIncome);
      const taxDue = Math.max(0, Math.round(taxOnTotal - taxOnOther));

      const netReceived = withdrawal - taxDue;
      const effectiveRate = withdrawal > 0 ? ((taxDue / withdrawal) * 100).toFixed(1) : '0';

      this.pensionWithdrawal.result = {
        taxFree: Math.round(taxFree),
        taxable: Math.round(taxable),
        taxDue,
        netReceived: Math.round(netReceived),
        effectiveRate,
      };
    },

    // Helper: calculate income tax on a given amount (uses centralised tax config)
    _calculateIncomeTax2526(income) {
      const basicRateLimit = HIGHER_RATE_THRESHOLD - PERSONAL_ALLOWANCE;
      // Personal allowance taper above threshold
      let adjustedPA = PERSONAL_ALLOWANCE;
      if (income > PERSONAL_ALLOWANCE_TAPER_THRESHOLD) {
        adjustedPA = Math.max(0, PERSONAL_ALLOWANCE - Math.floor((income - PERSONAL_ALLOWANCE_TAPER_THRESHOLD) / 2));
      }
      const taxableIncome = Math.max(0, income - adjustedPA);
      let tax = 0;
      const basicBand = Math.min(taxableIncome, basicRateLimit);
      tax += basicBand * BASIC_RATE;
      const higherBand = Math.min(Math.max(0, taxableIncome - basicRateLimit), ADDITIONAL_RATE_THRESHOLD - HIGHER_RATE_THRESHOLD);
      tax += higherBand * HIGHER_RATE;
      const additionalBand = Math.max(0, taxableIncome - (ADDITIONAL_RATE_THRESHOLD - PERSONAL_ALLOWANCE));
      tax += additionalBand * ADDITIONAL_RATE;
      return tax;
    },

    calculatePensionRelief() {
      const contribution = this.pensionRelief.contribution || 0;
      if (contribution <= 0) return;
      const band = this.pensionRelief.taxBand;
      const isNetPay = this.pensionRelief.pensionType === 'net-pay';

      // For relief at source: you pay net (after 20% relief), provider claims 20%
      // For net pay: contribution comes from gross salary, full relief automatic
      const gross = contribution;
      const basicRelief = Math.round(gross * BASIC_RATE);
      let additionalRelief = 0;
      let effectiveCost = gross;

      if (band === 'higher') {
        additionalRelief = Math.round(gross * (HIGHER_RATE - BASIC_RATE));
        effectiveCost = Math.round(gross * (1 - HIGHER_RATE));
      } else if (band === 'additional') {
        additionalRelief = Math.round(gross * (ADDITIONAL_RATE - BASIC_RATE));
        effectiveCost = Math.round(gross * (1 - ADDITIONAL_RATE));
      } else {
        effectiveCost = Math.round(gross * (1 - BASIC_RATE));
      }

      const totalRelief = basicRelief + additionalRelief;

      // For relief at source, net contribution is what you actually pay out of pocket
      const netContribution = isNetPay ? gross : Math.round(gross * (1 - BASIC_RATE));

      this.pensionRelief.result = {
        gross,
        netContribution,
        basicRelief,
        additionalRelief,
        totalRelief,
        effectiveCost,
      };
    },

    formatInputDisplay(value) {
      if (value === null || value === undefined || value === '') return '';
      return Number(value).toLocaleString('en-GB');
    },

    parseInputValue(event, obj, key) {
      const raw = event.target.value.replace(/[^0-9.]/g, '');
      const num = parseFloat(raw) || 0;
      obj[key] = num;
      event.target.value = num ? num.toLocaleString('en-GB') : '';
    },

    // formatCurrency provided by currencyMixin
  },
};
</script>


