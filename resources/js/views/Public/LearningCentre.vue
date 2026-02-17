<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-900/80 to-slate-900/90"></div>
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
      </div>

      <!-- Grid Pattern Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-30"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
        <!-- Badge -->
        <div class="inline-flex items-center px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-purple-200 text-sm font-medium mb-4">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          Educational Resources
        </div>

        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
          Learning
          <span class="text-purple-400">Centre</span>
        </h1>
        <p class="text-lg text-slate-300 max-w-2xl mx-auto mb-8">
          Educational resources to help you understand financial planning.
        </p>

        <!-- Category Navigation - Now in hero -->
        <div class="flex flex-wrap justify-center gap-3 max-w-4xl mx-auto">
          <button
            v-for="category in categories"
            :key="category.id"
            @click="activeCategory = category.id"
            :class="[
              'px-4 py-2.5 rounded-xl font-medium whitespace-nowrap transition-all duration-300 text-sm',
              activeCategory === category.id
                ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/25'
                : 'bg-white/10 backdrop-blur-md text-white hover:bg-white/20 border border-white/20'
            ]"
          >
            <!-- Category Icons -->
            <span class="inline-flex items-center">
              <svg v-if="category.id === 'basics'" class="w-4 h-4 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              <svg v-else-if="category.id === 'protection'" class="w-4 h-4 mr-2 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              <svg v-else-if="category.id === 'retirement'" class="w-4 h-4 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else-if="category.id === 'estate'" class="w-4 h-4 mr-2 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              <svg v-else-if="category.id === 'investment'" class="w-4 h-4 mr-2 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              <svg v-else-if="category.id === 'tax'" class="w-4 h-4 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              {{ category.name }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <!-- Financial Planning Basics -->
      <div v-if="activeCategory === 'basics'" class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-6">
            <h2 class="text-2xl font-bold text-white">What is Financial Planning?</h2>
            <p class="text-indigo-100 mt-1">Understanding the foundations of managing your financial future</p>
          </div>

          <div class="p-8">
            <div class="prose prose-lg max-w-none text-slate-600 space-y-6">
              <p class="text-lg leading-relaxed">
                Financial planning is the process of taking a comprehensive look at your financial situation and building a specific financial plan to reach your goals. As a result, financial planning often delves into multiple areas of finance, including investing, taxes, savings, retirement, estate planning, insurance, and more.
              </p>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">The Six Key Areas of Financial Planning</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="group bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-6 border border-green-200 hover:shadow-lg transition-all">
                  <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-green-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </div>
                  <h4 class="text-xl font-bold text-green-900 mb-2">1. Protection Planning</h4>
                  <p class="text-green-800">
                    Protecting your income and family against unforeseen circumstances through life insurance, critical illness cover, and income protection.
                  </p>
                </div>

                <div class="group bg-gradient-to-br from-blue-50 to-cyan-50 rounded-2xl p-6 border border-blue-200 hover:shadow-lg transition-all">
                  <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-blue-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                  </div>
                  <h4 class="text-xl font-bold text-blue-900 mb-2">2. Cash Flow & Savings</h4>
                  <p class="text-blue-800">
                    Managing your day-to-day finances, building emergency funds, and working towards short-term savings goals.
                  </p>
                </div>

                <div class="group bg-gradient-to-br from-purple-50 to-violet-50 rounded-2xl p-6 border border-purple-200 hover:shadow-lg transition-all">
                  <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-purple-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                  </div>
                  <h4 class="text-xl font-bold text-purple-900 mb-2">3. Investment Planning</h4>
                  <p class="text-purple-800">
                    Growing your wealth over time through investments in stocks, bonds, funds, and other assets aligned with your risk tolerance.
                  </p>
                </div>

                <div class="group bg-gradient-to-br from-blue-50 to-blue-50 rounded-2xl p-6 border border-blue-200 hover:shadow-lg transition-all">
                  <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-blue-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <h4 class="text-xl font-bold text-blue-900 mb-2">4. Retirement Planning</h4>
                  <p class="text-blue-800">
                    Ensuring you have sufficient income in retirement through pensions, investments, and other retirement savings vehicles.
                  </p>
                </div>

                <div class="group bg-gradient-to-br from-red-50 to-rose-50 rounded-2xl p-6 border border-red-200 hover:shadow-lg transition-all">
                  <div class="w-12 h-12 bg-red-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-red-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                  </div>
                  <h4 class="text-xl font-bold text-red-900 mb-2">5. Estate Planning</h4>
                  <p class="text-red-800">
                    Planning for the efficient transfer of your wealth to beneficiaries while minimizing inheritance tax liabilities.
                  </p>
                </div>

                <div class="group bg-gradient-to-br from-indigo-50 to-blue-50 rounded-2xl p-6 border border-indigo-200 hover:shadow-lg transition-all">
                  <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-indigo-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <h4 class="text-xl font-bold text-indigo-900 mb-2">6. Tax Planning</h4>
                  <p class="text-indigo-800">
                    Structuring your finances in a tax-efficient manner using allowances, reliefs, and legitimate tax planning strategies.
                  </p>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Why Financial Planning Matters</h3>
              <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-2xl p-6 border border-slate-200">
                <ul class="space-y-4">
                  <li class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Provides clarity and direction for your financial future</span>
                  </li>
                  <li class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Helps you make informed decisions about money</span>
                  </li>
                  <li class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Identifies potential risks and opportunities</span>
                  </li>
                  <li class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Ensures you're on track to meet your life goals</span>
                  </li>
                  <li class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Reduces financial stress and anxiety</span>
                  </li>
                  <li class="flex items-start">
                    <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Maximizes the efficiency of your money</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Protection Planning -->
      <div v-if="activeCategory === 'protection'" class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-8 py-6">
            <h2 class="text-2xl font-bold text-white">Understanding Protection Planning</h2>
            <p class="text-green-100 mt-1">Safeguarding your family and lifestyle from the unexpected</p>
          </div>

          <div class="p-8">
            <div class="prose prose-lg max-w-none text-slate-600 space-y-6">
              <p class="text-lg leading-relaxed">
                Protection planning ensures your family and lifestyle are protected if something unexpected happens to you. It's about having the right types and levels of cover in place.
              </p>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Types of Protection</h3>

              <div class="space-y-6">
                <div class="bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-600 rounded-r-2xl p-6">
                  <h4 class="text-xl font-bold text-green-900 mb-3">Life Insurance</h4>
                  <p class="text-green-800 mb-4">
                    Pays out a lump sum if you die during the policy term. Essential for:
                  </p>
                  <ul class="space-y-2 text-green-800">
                    <li class="flex items-center"><span class="w-2 h-2 bg-green-600 rounded-full mr-3"></span>Paying off the mortgage</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-green-600 rounded-full mr-3"></span>Replacing your income for dependants</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-green-600 rounded-full mr-3"></span>Covering funeral costs</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-green-600 rounded-full mr-3"></span>Funding children's education</li>
                  </ul>
                  <div class="mt-4 bg-white/70 rounded-xl p-4">
                    <p class="text-sm font-semibold text-green-900">Rule of Thumb:</p>
                    <p class="text-sm text-green-800 mt-1">Cover should be 10x your annual income, or enough to clear debts plus 5-10 years of income replacement.</p>
                  </div>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6">
                  <h4 class="text-xl font-bold text-blue-900 mb-3">Critical Illness Cover</h4>
                  <p class="text-blue-800 mb-4">
                    Pays out a tax-free lump sum if you're diagnosed with a specified serious illness such as:
                  </p>
                  <ul class="space-y-2 text-blue-800">
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3"></span>Cancer</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3"></span>Heart attack</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3"></span>Stroke</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3"></span>Multiple sclerosis</li>
                  </ul>
                  <div class="mt-4 bg-white/70 rounded-xl p-4">
                    <p class="text-sm font-semibold text-blue-900">Important:</p>
                    <p class="text-sm text-blue-800 mt-1">You're statistically more likely to suffer a critical illness than die during working years. Consider this alongside life insurance.</p>
                  </div>
                </div>

                <div class="bg-gradient-to-r from-purple-50 to-purple-100 border-l-4 border-purple-600 rounded-r-2xl p-6">
                  <h4 class="text-xl font-bold text-purple-900 mb-3">Income Protection</h4>
                  <p class="text-purple-800 mb-4">
                    Replaces a percentage of your income (typically 50-70%) if you can't work due to illness or injury. Key features:
                  </p>
                  <ul class="space-y-2 text-purple-800">
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-3"></span>Pays until you return to work or retirement age</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-3"></span>Deferred period (4-52 weeks) before payments start</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-3"></span>Own occupation or any occupation definitions</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-3"></span>Tax-free benefits</li>
                  </ul>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">How Much Cover Do You Need?</h3>
              <p class="mb-4">The right amount depends on your circumstances. Consider:</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                  <p class="font-semibold text-slate-900">Debts</p>
                  <p class="text-sm text-slate-600">Mortgage, loans, credit cards</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                  <p class="font-semibold text-slate-900">Dependants</p>
                  <p class="text-sm text-slate-600">Number and age of children</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                  <p class="font-semibold text-slate-900">Income</p>
                  <p class="text-sm text-slate-600">Your annual earnings and lifestyle costs</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                  <p class="font-semibold text-slate-900">Existing Cover</p>
                  <p class="text-sm text-slate-600">Employer benefits, savings</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Retirement Planning -->
      <div v-if="activeCategory === 'retirement'" class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
          <div class="bg-gradient-to-r from-blue-600 to-blue-600 px-8 py-6">
            <h2 class="text-2xl font-bold text-white">Retirement Planning in the UK</h2>
            <p class="text-blue-100 mt-1">Building your path to a comfortable retirement</p>
          </div>

          <div class="p-8">
            <div class="prose prose-lg max-w-none text-slate-600 space-y-6">
              <p class="text-lg leading-relaxed">
                Retirement planning involves building up sufficient savings and income sources to maintain your lifestyle when you stop working. With increasing life expectancy, this could be 20-30 years or more.
              </p>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">UK Pension System</h3>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                  <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                  </div>
                  <h4 class="text-lg font-bold text-blue-900 mb-2">State Pension</h4>
                  <p class="text-2xl font-bold text-blue-600 mb-2">£221.20/week</p>
                  <p class="text-blue-800 text-sm">
                    You need 35 qualifying years of National Insurance contributions. Claim age is currently 66.
                  </p>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 border border-purple-200">
                  <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <h4 class="text-lg font-bold text-purple-900 mb-2">Workplace Pension</h4>
                  <p class="text-2xl font-bold text-purple-600 mb-2">8% minimum</p>
                  <p class="text-purple-800 text-sm">
                    Auto-enrollment requires minimum 8% total contribution (5% employee + 3% employer).
                  </p>
                </div>

                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200">
                  <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                  <h4 class="text-lg font-bold text-green-900 mb-2">Personal Pension</h4>
                  <p class="text-2xl font-bold text-green-600 mb-2">20-45% relief</p>
                  <p class="text-green-800 text-sm">
                    Self-Invested Personal Pensions offer more investment control. Get 20-45% tax relief on contributions.
                  </p>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Key Allowances & Limits</h3>

              <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6 my-6">
                <h4 class="text-xl font-bold text-blue-900 mb-4">Annual Allowance: £60,000</h4>
                <p class="text-blue-800 mb-4">
                  The maximum you can contribute to pensions each year with tax relief. Includes employer contributions.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div class="bg-white/70 rounded-xl p-3">
                    <p class="font-semibold text-blue-900 text-sm">Carry Forward</p>
                    <p class="text-xs text-blue-800">Use unused allowance from previous 3 years</p>
                  </div>
                  <div class="bg-white/70 rounded-xl p-3">
                    <p class="font-semibold text-blue-900 text-sm">Tapered</p>
                    <p class="text-xs text-blue-800">Reduced for high earners (income over £260,000)</p>
                  </div>
                  <div class="bg-white/70 rounded-xl p-3">
                    <p class="font-semibold text-blue-900 text-sm">Money Purchase Annual Allowance</p>
                    <p class="text-xs text-blue-800">£10,000 limit if you've accessed pension flexibly</p>
                  </div>
                </div>
              </div>

              <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6 my-6">
                <h4 class="text-xl font-bold text-blue-900 mb-4">Tax-Free Lump Sum: 25%</h4>
                <p class="text-blue-800">
                  You can take 25% of your pension pot as a tax-free lump sum from age 55 (rising to 57 in 2028). The rest is taxed as income when withdrawn.
                </p>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">How Much Do You Need?</h3>
              <p class="mb-4">A comfortable retirement in the UK requires approximately:</p>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-100 rounded-xl p-4 text-center border border-slate-200">
                  <p class="text-sm text-slate-600 mb-1">Moderate</p>
                  <p class="text-2xl font-bold text-slate-900">£25k-£35k</p>
                  <p class="text-xs text-slate-500">per year per person</p>
                </div>
                <div class="bg-blue-100 rounded-xl p-4 text-center border border-blue-200">
                  <p class="text-sm text-blue-600 mb-1">Comfortable</p>
                  <p class="text-2xl font-bold text-blue-900">£35k-£50k</p>
                  <p class="text-xs text-blue-500">per year per person</p>
                </div>
                <div class="bg-purple-100 rounded-xl p-4 text-center border border-purple-200">
                  <p class="text-sm text-purple-600 mb-1">Luxurious</p>
                  <p class="text-2xl font-bold text-purple-900">£50k+</p>
                  <p class="text-xs text-purple-500">per year per person</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Estate Planning -->
      <div v-if="activeCategory === 'estate'" class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
          <div class="bg-gradient-to-r from-red-600 to-rose-600 px-8 py-6">
            <h2 class="text-2xl font-bold text-white">Estate Planning & Inheritance Tax</h2>
            <p class="text-red-100 mt-1">Protecting and transferring your wealth efficiently</p>
          </div>

          <div class="p-8">
            <div class="prose prose-lg max-w-none text-slate-600 space-y-6">
              <p class="text-lg leading-relaxed">
                Estate planning ensures your assets pass to your chosen beneficiaries in a tax-efficient manner. Without proper planning, your estate could face a significant inheritance tax bill and may not be distributed as you wish.
              </p>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Inheritance Tax Basics</h3>

              <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-2xl p-6 border border-red-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <p class="text-sm text-red-700 font-medium mb-1">Inheritance Tax Rate</p>
                    <p class="text-4xl font-bold text-red-600">40%</p>
                    <p class="text-sm text-red-800 mt-2">Charged on estates above the nil rate band. Reduced to 36% if 10%+ left to charity.</p>
                  </div>
                  <div>
                    <p class="text-sm text-red-700 font-medium mb-1">When It's Charged</p>
                    <p class="text-lg font-semibold text-red-900">On death and on gifts made within 7 years of death (Potentially Exempt Transfers).</p>
                  </div>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Tax-Free Allowances</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200 text-center">
                  <h4 class="text-lg font-bold text-blue-900 mb-2">Nil Rate Band</h4>
                  <p class="text-4xl font-bold text-blue-600 mb-2">£325,000</p>
                  <p class="text-blue-800 text-sm">
                    The standard Inheritance Tax-free threshold. Transferable to surviving spouse/civil partner.
                  </p>
                </div>

                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200 text-center">
                  <h4 class="text-lg font-bold text-green-900 mb-2">Residence Nil Rate Band</h4>
                  <p class="text-4xl font-bold text-green-600 mb-2">£175,000</p>
                  <p class="text-green-800 text-sm">
                    Additional allowance when home left to direct descendants. Also transferable to spouse.
                  </p>
                </div>
              </div>

              <div class="bg-gradient-to-r from-indigo-50 to-indigo-100 rounded-2xl p-6 border border-indigo-200 mt-6">
                <h4 class="text-xl font-bold text-indigo-900 mb-3">Married Couples</h4>
                <p class="text-indigo-800 mb-2">
                  Combined allowance: up to <span class="text-3xl font-bold text-indigo-600">£1 million</span>
                </p>
                <p class="text-indigo-800 text-sm">
                  (£325k Nil Rate Band + £175k Residence Nil Rate Band for each spouse). Transfers between spouses are always Inheritance Tax-free. Unused allowances transfer to the surviving spouse.
                </p>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Inheritance Tax Mitigation Strategies</h3>

              <div class="space-y-4">
                <div class="bg-white border-l-4 border-green-600 rounded-r-xl p-5 shadow-sm">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">1. Gifting</h4>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div class="bg-green-50 rounded-lg p-3">
                      <p class="font-semibold text-green-900">Annual Exemption</p>
                      <p class="text-green-700">£3,000 per year</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3">
                      <p class="font-semibold text-green-900">Small Gifts</p>
                      <p class="text-green-700">£250 unlimited people</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3">
                      <p class="font-semibold text-green-900">Wedding (child)</p>
                      <p class="text-green-700">£5,000</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3">
                      <p class="font-semibold text-green-900">Potentially Exempt Transfers</p>
                      <p class="text-green-700">Exempt after 7 years</p>
                    </div>
                  </div>
                </div>

                <div class="bg-white border-l-4 border-blue-600 rounded-r-xl p-5 shadow-sm">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">2. Trusts</h4>
                  <p class="text-slate-700 text-sm">
                    Place assets in trust to remove them from your estate. Various trust types available with different tax treatments. Complex area requiring professional advice.
                  </p>
                </div>

                <div class="bg-white border-l-4 border-purple-600 rounded-r-xl p-5 shadow-sm">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">3. Life Insurance in Trust</h4>
                  <p class="text-slate-700 text-sm">
                    Life insurance proceeds normally form part of your estate. Writing the policy in trust keeps it outside your estate and ensures quick payout to beneficiaries without probate.
                  </p>
                </div>

                <div class="bg-white border-l-4 border-red-600 rounded-r-xl p-5 shadow-sm">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">4. Pension Death Benefits</h4>
                  <p class="text-slate-700 text-sm">
                    Pensions are usually outside your estate for Inheritance Tax. Nominate beneficiaries to ensure proceeds go where you intend. If you die before 75, benefits are usually tax-free.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Investment Planning -->
      <div v-if="activeCategory === 'investment'" class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
          <div class="bg-gradient-to-r from-purple-600 to-violet-600 px-8 py-6">
            <h2 class="text-2xl font-bold text-white">Investment Planning Fundamentals</h2>
            <p class="text-purple-100 mt-1">Growing your wealth through strategic investing</p>
          </div>

          <div class="p-8">
            <div class="prose prose-lg max-w-none text-slate-600 space-y-6">
              <p class="text-lg leading-relaxed">
                Investment planning involves putting your money to work to achieve long-term financial goals. Understanding the basics of risk, return, and asset allocation is crucial for successful investing.
              </p>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Risk & Return</h3>

              <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6 mb-6">
                <h4 class="text-lg font-bold text-blue-900 mb-2">Core Principle</h4>
                <p class="text-blue-800 text-lg">
                  <strong>Higher potential returns come with higher risk.</strong> There's no such thing as high returns without risk. Understanding your risk tolerance is the foundation of investment planning.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200">
                  <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center mb-4">
                    <span class="text-white font-bold">L</span>
                  </div>
                  <h4 class="text-lg font-bold text-green-900 mb-2">Lower Risk</h4>
                  <ul class="text-sm text-green-800 space-y-1">
                    <li>• Cash</li>
                    <li>• Government bonds</li>
                    <li>• Corporate bonds (high grade)</li>
                  </ul>
                  <p class="text-xs text-green-700 mt-4 font-medium">
                    Expected return: 2-4% per year
                  </p>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                  <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mb-4">
                    <span class="text-white font-bold">M</span>
                  </div>
                  <h4 class="text-lg font-bold text-blue-900 mb-2">Medium Risk</h4>
                  <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Balanced funds</li>
                    <li>• Mixed asset portfolios</li>
                    <li>• Property funds</li>
                  </ul>
                  <p class="text-xs text-blue-700 mt-4 font-medium">
                    Expected return: 4-7% per year
                  </p>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                  <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mb-4">
                    <span class="text-white font-bold">H</span>
                  </div>
                  <h4 class="text-lg font-bold text-blue-900 mb-2">Higher Risk</h4>
                  <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Individual stocks</li>
                    <li>• Emerging markets</li>
                    <li>• Small cap funds</li>
                  </ul>
                  <p class="text-xs text-blue-700 mt-4 font-medium">
                    Expected return: 7-10%+ per year
                  </p>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Tax-Efficient Wrappers</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-r from-purple-50 to-purple-100 border-l-4 border-purple-600 rounded-r-2xl p-6">
                  <h4 class="text-lg font-bold text-purple-900 mb-2">ISAs (Individual Savings Accounts)</h4>
                  <p class="text-2xl font-bold text-purple-600 mb-3">£20,000 annual allowance</p>
                  <ul class="space-y-2 text-purple-800 text-sm">
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>Tax-free growth and withdrawals</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>No capital gains tax or income tax</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>Can hold cash or investments (Stocks & Shares ISA)</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>Flexible access to your money</li>
                  </ul>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6">
                  <h4 class="text-lg font-bold text-blue-900 mb-2">Pensions</h4>
                  <p class="text-2xl font-bold text-blue-600 mb-3">£60,000 annual allowance</p>
                  <ul class="space-y-2 text-blue-800 text-sm">
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>20-45% tax relief on contributions</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>Tax-free growth</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>25% tax-free lump sum at retirement</li>
                    <li class="flex items-center"><span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>Locked until age 55 (rising to 57 in 2028)</li>
                  </ul>
                </div>
              </div>

              <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200 mt-6">
                <h4 class="text-lg font-bold text-green-900 mb-4">Investment Tips</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-800 text-sm">Start early - compound growth is powerful</span>
                  </div>
                  <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-800 text-sm">Invest regularly (pound-cost averaging)</span>
                  </div>
                  <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-800 text-sm">Think long-term (5+ years minimum)</span>
                  </div>
                  <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-800 text-sm">Keep costs low (fees eat into returns)</span>
                  </div>
                  <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-800 text-sm">Rebalance periodically</span>
                  </div>
                  <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-green-800 text-sm">Don't try to time the market</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tax Planning -->
      <div v-if="activeCategory === 'tax'" class="space-y-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-8 py-6">
            <h2 class="text-2xl font-bold text-white">UK Tax Planning Guide</h2>
            <p class="text-indigo-100 mt-1">Maximising your allowances and minimising your tax burden</p>
          </div>

          <div class="p-8">
            <div class="prose prose-lg max-w-none text-slate-600 space-y-6">
              <p class="text-lg leading-relaxed">
                Tax planning involves arranging your financial affairs to minimise tax legally. Understanding allowances and using them effectively can save thousands of pounds each year.
              </p>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Income Tax (2025/26)</h3>

              <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl overflow-hidden shadow-sm border border-slate-200">
                  <thead class="bg-gradient-to-r from-slate-100 to-slate-200">
                    <tr>
                      <th class="px-6 py-4 text-left text-sm font-bold text-slate-900">Band</th>
                      <th class="px-6 py-4 text-left text-sm font-bold text-slate-900">Income Range</th>
                      <th class="px-6 py-4 text-left text-sm font-bold text-slate-900">Rate</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Personal Allowance</td>
                      <td class="px-6 py-4 text-sm text-slate-700">£0 - £12,570</td>
                      <td class="px-6 py-4"><span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">0%</span></td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Basic Rate</td>
                      <td class="px-6 py-4 text-sm text-slate-700">£12,571 - £50,270</td>
                      <td class="px-6 py-4"><span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-bold">20%</span></td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Higher Rate</td>
                      <td class="px-6 py-4 text-sm text-slate-700">£50,271 - £125,140</td>
                      <td class="px-6 py-4"><span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-bold">40%</span></td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Additional Rate</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Over £125,140</td>
                      <td class="px-6 py-4"><span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-bold">45%</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6 my-6">
                <h4 class="text-lg font-bold text-blue-900 mb-2">Personal Allowance Taper</h4>
                <p class="text-blue-800 text-sm">
                  For every £2 earned over £100,000, you lose £1 of personal allowance. Between £100k-£125k, the effective tax rate is <strong>60%</strong>.
                </p>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Key Tax Allowances</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                  <h4 class="text-lg font-bold text-blue-900 mb-3">Personal Savings Allowance</h4>
                  <ul class="text-sm text-blue-800 space-y-2">
                    <li class="flex justify-between"><span>Basic rate:</span><span class="font-bold">£1,000 tax-free</span></li>
                    <li class="flex justify-between"><span>Higher rate:</span><span class="font-bold">£500 tax-free</span></li>
                    <li class="flex justify-between"><span>Additional rate:</span><span class="font-bold">£0</span></li>
                  </ul>
                </div>

                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200">
                  <h4 class="text-lg font-bold text-green-900 mb-3">Dividend Allowance</h4>
                  <ul class="text-sm text-green-800 space-y-2">
                    <li class="flex justify-between"><span>Tax-free:</span><span class="font-bold">£500</span></li>
                    <li class="flex justify-between"><span>Basic rate:</span><span class="font-bold">8.75%</span></li>
                    <li class="flex justify-between"><span>Higher rate:</span><span class="font-bold">33.75%</span></li>
                  </ul>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 border border-purple-200">
                  <h4 class="text-lg font-bold text-purple-900 mb-3">Capital Gains Tax Allowance</h4>
                  <ul class="text-sm text-purple-800 space-y-2">
                    <li class="flex justify-between"><span>Annual exemption:</span><span class="font-bold">£3,000</span></li>
                    <li class="flex justify-between"><span>Basic rate:</span><span class="font-bold">10%</span></li>
                    <li class="flex justify-between"><span>Higher rate:</span><span class="font-bold">20%</span></li>
                  </ul>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                  <h4 class="text-lg font-bold text-blue-900 mb-3">Trading Allowance</h4>
                  <ul class="text-sm text-blue-800 space-y-2">
                    <li class="flex justify-between"><span>Self-employment:</span><span class="font-bold">£1,000 tax-free</span></li>
                    <li class="flex justify-between"><span>Property income:</span><span class="font-bold">£1,000 tax-free</span></li>
                    <li><span class="text-xs">Useful for side hustles</span></li>
                  </ul>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Tax-Efficient Strategies</h3>

              <div class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">1. Maximise Pension Contributions</h4>
                  <p class="text-slate-700 text-sm">
                    Get immediate tax relief at your marginal rate (20-45%). Reduces taxable income and can prevent personal allowance taper.
                  </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">2. Use ISA Allowances</h4>
                  <p class="text-slate-700 text-sm">
                    £20,000 annual allowance for completely tax-free growth and withdrawals. Use it or lose it - doesn't carry forward.
                  </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">3. Split Income with Spouse</h4>
                  <p class="text-slate-700 text-sm">
                    Transfer assets to lower-earning spouse to use their allowances and lower tax bands. Particularly effective for savings and investment income.
                  </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">4. Harvest Capital Gains</h4>
                  <p class="text-slate-700 text-sm">
                    Use your £3,000 Capital Gains Tax allowance each year. Sell and repurchase investments to "crystallise" gains within your allowance.
                  </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
                  <h4 class="text-lg font-bold text-slate-900 mb-2">5. Salary Sacrifice</h4>
                  <p class="text-slate-700 text-sm">
                    Exchange salary for pension contributions or other benefits. Saves income tax and National Insurance for both employee and employer.
                  </p>
                </div>
              </div>

              <h3 class="text-2xl font-bold text-slate-900 mt-10 mb-6">Section 24: Mortgage Interest Tax Relief for Landlords</h3>

              <div class="bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-600 rounded-r-2xl p-6 mb-6">
                <h4 class="text-lg font-bold text-red-900 mb-2">What Changed?</h4>
                <p class="text-red-800">
                  The Finance Act 2015 (Section 24) fundamentally changed how UK residential landlords receive tax relief on mortgage interest. Fully phased in from April 2020, it replaced the old system of deducting mortgage interest from rental income with a flat 20% tax credit.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-2xl p-6 border border-slate-200">
                  <h4 class="text-lg font-bold text-slate-900 mb-3">Before Section 24</h4>
                  <ul class="space-y-2 text-slate-700 text-sm">
                    <li class="flex items-start"><span class="w-2 h-2 bg-slate-500 rounded-full mr-3 mt-1.5"></span>Mortgage interest was deducted from rental income before calculating tax</li>
                    <li class="flex items-start"><span class="w-2 h-2 bg-slate-500 rounded-full mr-3 mt-1.5"></span>Higher-rate (40%) taxpayers got 40% relief on interest</li>
                    <li class="flex items-start"><span class="w-2 h-2 bg-slate-500 rounded-full mr-3 mt-1.5"></span>Additional-rate (45%) taxpayers got 45% relief</li>
                  </ul>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                  <h4 class="text-lg font-bold text-blue-900 mb-3">After Section 24 (Now)</h4>
                  <ul class="space-y-2 text-blue-800 text-sm">
                    <li class="flex items-start"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3 mt-1.5"></span>Mortgage interest is NOT deducted from rental income</li>
                    <li class="flex items-start"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3 mt-1.5"></span>Full rental profit is added to your taxable income</li>
                    <li class="flex items-start"><span class="w-2 h-2 bg-blue-600 rounded-full mr-3 mt-1.5"></span>All landlords get a flat 20% tax credit on mortgage interest</li>
                  </ul>
                </div>
              </div>

              <h4 class="text-xl font-bold text-slate-900 mt-8 mb-4">How It Works in Practice</h4>

              <div class="space-y-4 mb-6">
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                  <p class="text-sm font-bold text-slate-900 mb-2">Step 1: Calculate Taxable Rental Income</p>
                  <p class="text-slate-700 text-sm">Rental income minus allowable expenses (running costs, insurance, agent fees, maintenance) but <strong>not</strong> mortgage interest. This full figure is added to your other income for income tax.</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                  <p class="text-sm font-bold text-slate-900 mb-2">Step 2: Calculate Your Tax Bill</p>
                  <p class="text-slate-700 text-sm">Income tax is calculated on all your income (employment + rental + other) at the applicable rates (20%, 40%, or 45%).</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                  <p class="text-sm font-bold text-slate-900 mb-2">Step 3: Apply Section 24 Tax Credit</p>
                  <p class="text-slate-700 text-sm">20% of your mortgage interest is deducted from your tax bill (not from your income). This is the tax credit.</p>
                </div>
              </div>

              <h4 class="text-xl font-bold text-slate-900 mt-8 mb-4">Mortgage Type &amp; Interest Portion</h4>

              <div class="overflow-x-auto mb-6">
                <table class="min-w-full bg-white rounded-xl overflow-hidden shadow-sm border border-slate-200">
                  <thead class="bg-gradient-to-r from-slate-100 to-slate-200">
                    <tr>
                      <th class="px-6 py-4 text-left text-sm font-bold text-slate-900">Mortgage Type</th>
                      <th class="px-6 py-4 text-left text-sm font-bold text-slate-900">Interest Portion</th>
                      <th class="px-6 py-4 text-left text-sm font-bold text-slate-900">Tax Credit</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Interest-only</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Entire monthly payment is interest</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Payment x 20%</td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Repayment</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Only part of payment is interest (rest is capital)</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Interest portion x 20%</td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-medium text-slate-900">Mixed</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Interest-only part + interest from repayment part</td>
                      <td class="px-6 py-4 text-sm text-slate-700">Total interest x 20%</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-600 rounded-r-2xl p-6 mb-6">
                <h4 class="text-lg font-bold text-blue-900 mb-2">Who Is Affected Most?</h4>
                <p class="text-blue-800 text-sm mb-3">
                  Basic-rate taxpayers are largely unaffected (they get 20% relief, same as before). Higher-rate and additional-rate taxpayers lose out significantly:
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div class="bg-white/70 rounded-xl p-3 text-center">
                    <p class="font-semibold text-green-700 text-sm">Basic Rate (20%)</p>
                    <p class="text-xs text-slate-700 mt-1">No change - same 20% relief</p>
                  </div>
                  <div class="bg-white/70 rounded-xl p-3 text-center">
                    <p class="font-semibold text-blue-700 text-sm">Higher Rate (40%)</p>
                    <p class="text-xs text-slate-700 mt-1">Only gets 20% credit instead of 40% deduction</p>
                  </div>
                  <div class="bg-white/70 rounded-xl p-3 text-center">
                    <p class="font-semibold text-red-700 text-sm">Additional Rate (45%)</p>
                    <p class="text-xs text-slate-700 mt-1">Only gets 20% credit instead of 45% deduction</p>
                  </div>
                </div>
              </div>

              <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                <h4 class="text-lg font-bold text-blue-900 mb-3">Example</h4>
                <p class="text-blue-800 text-sm mb-3">A higher-rate taxpayer with £12,000/year rental profit and £6,000/year mortgage interest:</p>
                <dl class="space-y-2 text-sm">
                  <div class="flex justify-between py-1 border-b border-blue-200">
                    <dt class="text-blue-800">Taxable rental income (added to other income):</dt>
                    <dd class="font-bold text-blue-900">£12,000</dd>
                  </div>
                  <div class="flex justify-between py-1 border-b border-blue-200">
                    <dt class="text-blue-800">Tax on rental income at 40%:</dt>
                    <dd class="font-bold text-red-700">£4,800</dd>
                  </div>
                  <div class="flex justify-between py-1 border-b border-blue-200">
                    <dt class="text-blue-800">Section 24 tax credit (20% of £6,000):</dt>
                    <dd class="font-bold text-green-700">-£1,200</dd>
                  </div>
                  <div class="flex justify-between py-2 border-t-2 border-blue-300">
                    <dt class="text-blue-900 font-semibold">Net tax on rental income:</dt>
                    <dd class="font-bold text-blue-900">£3,600</dd>
                  </div>
                </dl>
                <p class="text-xs text-blue-700 mt-3">Under the old system, only £6,000 (£12,000 - £6,000 interest) would have been taxable, resulting in just £2,400 tax. Section 24 costs this landlord an extra £1,200/year.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 py-16 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0">
        <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-purple-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 left-1/4 w-64 h-64 bg-indigo-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
      </div>

      <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 p-8 text-center">
          <h2 class="text-3xl font-bold text-white mb-4">
            Ready to Put Your Knowledge into Action?
          </h2>
          <p class="text-slate-300 mb-8">
            Create a free account to start planning your financial future with our comprehensive tools.
          </p>
          <router-link
            to="/register"
            class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-purple-500 to-indigo-500 text-white rounded-xl font-semibold hover:from-purple-600 hover:to-indigo-600 transition-all shadow-lg hover:shadow-xl"
          >
            Get Started Free
            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </router-link>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'LearningCentre',

  components: {
    PublicLayout,
  },

  data() {
    return {
      activeCategory: 'basics',
      categories: [
        { id: 'basics', name: 'Financial Planning Basics' },
        { id: 'protection', name: 'Protection Planning' },
        { id: 'retirement', name: 'Retirement Planning' },
        { id: 'estate', name: 'Estate Planning' },
        { id: 'investment', name: 'Investment Planning' },
        { id: 'tax', name: 'Tax Planning' },
      ],
    };
  },
};
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
