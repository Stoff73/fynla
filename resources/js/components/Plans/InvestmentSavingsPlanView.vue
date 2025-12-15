<template>
  <div class="investment-savings-plan-view">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <span class="ml-3 text-gray-600">Loading comprehensive plan...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
      <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="text-lg font-medium text-red-800 mb-2">Failed to load plan</h3>
      <p class="text-sm text-red-600 mb-4">{{ error }}</p>
      <button
        @click="loadPlan"
        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200"
      >
        Try Again
      </button>
    </div>

    <!-- Plan Content -->
    <div v-else-if="plan" class="space-y-8">
      <!-- Scaffold Warning Banner -->
      <div class="bg-amber-50 border-2 border-amber-400 rounded-lg p-4">
        <div class="flex items-start">
          <svg class="h-6 w-6 text-amber-600 mr-3 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <h3 class="text-lg font-semibold text-amber-800">Work in Progress</h3>
            <p class="text-sm text-amber-700 mt-1">
              This Investment & Savings Plan is currently a scaffold and not fully implemented.
              Some data may be incomplete or calculations may not reflect your full financial picture.
              Full functionality is coming soon.
            </p>
          </div>
        </div>
      </div>

      <!-- Executive Summary -->
      <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">Executive Summary</h2>
            <p class="text-sm text-gray-600 mt-1">Overview of your wealth and financial position</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-gray-500">Generated</p>
            <p class="text-sm font-medium text-gray-700">{{ formatDate(plan.generated_at) }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Total Wealth -->
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-sm text-gray-600 mb-1">Total Wealth</p>
            <p class="text-3xl font-bold text-gray-900">£{{ formatNumber(plan.executive_summary.total_wealth) }}</p>
            <div class="mt-3 space-y-1">
              <div class="flex justify-between text-xs">
                <span class="text-gray-600">Investments:</span>
                <span class="font-medium">£{{ formatNumber(plan.executive_summary.total_investment_value) }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-600">Savings:</span>
                <span class="font-medium">£{{ formatNumber(plan.executive_summary.total_savings_value) }}</span>
              </div>
            </div>
          </div>

          <!-- Portfolio Health -->
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-sm text-gray-600 mb-1">Portfolio Health</p>
            <p class="text-3xl font-bold" :class="getHealthColour(plan.executive_summary.portfolio_health_score)">
              {{ plan.executive_summary.portfolio_health_score || 'N/A' }}/100
            </p>
            <div class="mt-3 space-y-1">
              <div class="flex justify-between text-xs">
                <span class="text-gray-600">Diversification:</span>
                <span class="font-medium">{{ plan.executive_summary.diversification_score || 'N/A' }}/100</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-600">Holdings:</span>
                <span class="font-medium">{{ plan.executive_summary.total_holdings }}</span>
              </div>
            </div>
          </div>

          <!-- Emergency Fund -->
          <div class="bg-white rounded-lg p-4 shadow-sm">
            <p class="text-sm text-gray-600 mb-1">Emergency Fund</p>
            <p class="text-3xl font-bold" :class="getRunwayColour(plan.executive_summary.emergency_fund_runway)">
              {{ plan.executive_summary.emergency_fund_runway }} months
            </p>
            <div class="mt-3">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusBadgeClass(plan.executive_summary.emergency_fund_status)">
                {{ plan.executive_summary.emergency_fund_status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Investment Section -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
          <h2 class="text-2xl font-bold text-white">Investment Portfolio</h2>
          <p class="text-sm text-blue-100 mt-1">Portfolio analysis, holdings, and optimisation strategy</p>
        </div>

        <div class="p-6 space-y-6">
          <!-- Investment Summary -->
          <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Portfolio Summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">Total Value</p>
                <p class="text-xl font-semibold text-gray-900">£{{ formatNumber(plan.investment.summary.total_value) }}</p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">Accounts</p>
                <p class="text-xl font-semibold text-gray-900">{{ plan.investment.summary.accounts_count }}</p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">Holdings</p>
                <p class="text-xl font-semibold text-gray-900">{{ plan.investment.summary.holdings_count }}</p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">Tax Efficiency</p>
                <p class="text-xl font-semibold text-gray-900">
                  {{ plan.investment.summary.tax_efficiency ? plan.investment.summary.tax_efficiency + '%' : 'N/A' }}
                </p>
              </div>
            </div>
          </div>

          <!-- Holdings Overview -->
          <div v-if="plan.investment.holdings.accounts.length > 0">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Holdings by Account</h3>
            <div class="space-y-4">
              <div
                v-for="account in plan.investment.holdings.accounts"
                :key="account.account_id"
                class="border border-gray-200 rounded-lg p-4"
              >
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h4 class="font-semibold text-gray-900">{{ account.account_name }}</h4>
                    <p class="text-sm text-gray-600">{{ account.account_type }} • {{ account.provider }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-lg font-semibold text-gray-900">£{{ formatNumber(account.total_value) }}</p>
                    <p class="text-xs text-gray-600">{{ account.holdings_count }} holdings</p>
                  </div>
                </div>
                <div v-if="account.holdings.length > 0" class="space-y-2">
                  <div
                    v-for="holding in account.holdings"
                    :key="holding.ticker"
                    class="flex justify-between items-center text-sm py-2 border-t border-gray-100"
                  >
                    <div>
                      <span class="font-medium text-gray-900">{{ holding.ticker }}</span>
                      <span class="text-gray-600 ml-2">{{ holding.name }}</span>
                      <span class="text-gray-500 ml-2 text-xs">{{ holding.asset_class }}</span>
                    </div>
                    <div class="text-right">
                      <p class="font-medium text-gray-900">£{{ formatNumber(holding.current_value) }}</p>
                      <p class="text-xs text-gray-600">{{ holding.quantity }} units</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Performance Overview -->
          <div v-if="plan.investment.performance">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance</h3>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                  <p class="text-xs text-gray-600 mb-1">Total Return</p>
                  <p class="text-lg font-semibold" :class="getReturnColour(plan.investment.performance.total_return)">
                    {{ formatPercentage(plan.investment.performance.total_return) }}%
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 mb-1">Annualized</p>
                  <p class="text-lg font-semibold" :class="getReturnColour(plan.investment.performance.annualized_return)">
                    {{ formatPercentage(plan.investment.performance.annualized_return) }}%
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 mb-1">Volatility</p>
                  <p class="text-lg font-semibold text-gray-900">
                    {{ formatPercentage(plan.investment.performance.volatility) }}%
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 mb-1">Sharpe Ratio</p>
                  <p class="text-lg font-semibold text-gray-900">
                    {{ plan.investment.performance.sharpe_ratio || 'N/A' }}
                  </p>
                </div>
              </div>
              <p class="text-xs text-gray-600 mt-3 italic">{{ plan.investment.performance.note }}</p>
            </div>
          </div>

          <!-- Risk Metrics Card -->
          <div v-if="plan.investment.summary.risk_metrics">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk-Adjusted Metrics</h3>
            <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-6">
              <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg p-4 shadow-sm">
                  <p class="text-xs text-gray-600 mb-1">Alpha</p>
                  <p class="text-2xl font-bold" :class="getReturnColour(plan.investment.summary.risk_metrics.alpha)">
                    {{ formatPercentage(plan.investment.summary.risk_metrics.alpha) }}%
                  </p>
                  <p class="text-xs text-gray-500 mt-1">Excess return vs benchmark</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                  <p class="text-xs text-gray-600 mb-1">Beta</p>
                  <p class="text-2xl font-bold text-gray-900">
                    {{ formatNumber(plan.investment.summary.risk_metrics.beta) || 'N/A' }}
                  </p>
                  <p class="text-xs text-gray-500 mt-1">Market sensitivity</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                  <p class="text-xs text-gray-600 mb-1">Sharpe Ratio</p>
                  <p class="text-2xl font-bold text-gray-900">
                    {{ formatNumber(plan.investment.summary.risk_metrics.sharpe_ratio) || 'N/A' }}
                  </p>
                  <p class="text-xs text-gray-500 mt-1">Risk-adjusted return</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                  <p class="text-xs text-gray-600 mb-1">Volatility</p>
                  <p class="text-2xl font-bold text-orange-600">
                    {{ formatPercentage(plan.investment.summary.risk_metrics.volatility) }}%
                  </p>
                  <p class="text-xs text-gray-500 mt-1">Standard deviation</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                  <p class="text-xs text-gray-600 mb-1">Max Drawdown</p>
                  <p class="text-2xl font-bold text-red-600">
                    {{ formatPercentage(plan.investment.summary.risk_metrics.max_drawdown) }}%
                  </p>
                  <p class="text-xs text-gray-500 mt-1">Largest peak-to-trough decline</p>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                  <p class="text-xs text-gray-600 mb-1">VaR (95%)</p>
                  <p class="text-2xl font-bold text-gray-900">
                    {{ formatPercentage(plan.investment.summary.risk_metrics.var_95) }}%
                  </p>
                  <p class="text-xs text-gray-500 mt-1">Value at Risk</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Monte Carlo Analysis -->
          <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Monte Carlo Simulation</h3>
            <div class="bg-gradient-to-br from-blue-50 to-cyan-50 border border-blue-200 rounded-lg p-6">
              <div class="text-center py-8">
                <svg class="mx-auto h-16 w-16 text-blue-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h4 class="text-lg font-semibold text-gray-900 mb-2">Portfolio Projections</h4>
                <p class="text-sm text-gray-600 mb-4">
                  Monte Carlo simulations provide probabilistic projections of your portfolio's future performance.
                </p>
                <router-link
                  to="/investment?tab=scenarios"
                  class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                  View Monte Carlo Analysis
                  <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </router-link>
              </div>
            </div>
          </div>

          <!-- Efficient Frontier -->
          <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Efficient Frontier Analysis</h3>
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-6">
              <div class="text-center py-8">
                <svg class="mx-auto h-16 w-16 text-green-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <h4 class="text-lg font-semibold text-gray-900 mb-2">Portfolio Optimisation</h4>
                <p class="text-sm text-gray-600 mb-4">
                  Efficient Frontier shows the optimal risk-return trade-offs for your portfolio allocation.
                </p>
                <router-link
                  to="/investment?tab=optimization"
                  class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium"
                >
                  View Efficient Frontier
                  <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </router-link>
              </div>
            </div>
          </div>

          <!-- Investment Strategy -->
          <div v-if="plan.investment.strategy.recommendations.length > 0">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Optimisation Strategy</h3>
            <div class="space-y-3">
              <div
                v-for="(rec, index) in plan.investment.strategy.recommendations"
                :key="index"
                class="border-l-4 rounded-r-lg p-4"
                :class="getPriorityBorderClass(rec.priority)"
              >
                <div class="flex justify-between items-start mb-2">
                  <h4 class="font-semibold text-gray-900">{{ rec.category }}</h4>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getPriorityBadgeClass(rec.priority)">
                    {{ rec.priority }}
                  </span>
                </div>
                <div v-if="rec.potential_saving" class="text-sm text-green-600 font-medium mb-2">
                  Potential saving: £{{ formatNumber(rec.potential_saving) }}/year
                </div>
                <ul v-if="rec.recommendations" class="list-disc list-inside text-sm text-gray-700 space-y-1">
                  <li v-for="(item, i) in rec.recommendations" :key="i">{{ item }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Savings Section -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
          <h2 class="text-2xl font-bold text-white">Savings & Emergency Fund</h2>
          <p class="text-sm text-green-100 mt-1">Cash reserves, emergency fund analysis, and savings strategy</p>
        </div>

        <div class="p-6 space-y-6">
          <!-- Savings Summary -->
          <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Savings Summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">Total Savings</p>
                <p class="text-xl font-semibold text-gray-900">£{{ formatNumber(plan.savings.summary.total_savings) }}</p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">Accounts</p>
                <p class="text-xl font-semibold text-gray-900">{{ plan.savings.summary.accounts_count }}</p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">ISA Used</p>
                <p class="text-xl font-semibold text-gray-900">£{{ formatNumber(plan.savings.summary.isa_allowance_used) }}</p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs text-gray-600 mb-1">ISA Remaining</p>
                <p class="text-xl font-semibold text-gray-900">£{{ formatNumber(plan.savings.summary.isa_allowance_remaining) }}</p>
              </div>
            </div>
          </div>

          <!-- Emergency Fund Analysis -->
          <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Emergency Fund Analysis</h3>
            <div class="border-2 rounded-lg p-6"
              :class="getEmergencyFundBorderClass(plan.savings.emergency_fund.status_colour)">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-1">Current Runway</p>
                    <p class="text-4xl font-bold" :class="getRunwayColour(plan.savings.emergency_fund.current_runway_months)">
                      {{ plan.savings.emergency_fund.current_runway_months }} months
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                      Target: {{ plan.savings.emergency_fund.target_runway_months }} months
                    </p>
                  </div>
                  <div class="bg-gray-100 rounded-lg p-4">
                    <div class="mb-2">
                      <p class="text-xs text-gray-600">Current Amount</p>
                      <p class="text-lg font-semibold text-gray-900">£{{ formatNumber(plan.savings.emergency_fund.current_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Target Amount</p>
                      <p class="text-lg font-semibold text-gray-900">£{{ formatNumber(plan.savings.emergency_fund.target_amount) }}</p>
                    </div>
                  </div>
                </div>
                <div>
                  <div class="mb-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                      :class="getStatusBadgeClass(plan.savings.emergency_fund.adequacy_category)">
                      {{ plan.savings.emergency_fund.adequacy_category }}
                    </span>
                    <p class="text-sm text-gray-700 mt-3">{{ plan.savings.emergency_fund.recommendation }}</p>
                  </div>
                  <div v-if="plan.savings.emergency_fund.shortfall_months > 0"
                    class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <p class="text-sm font-semibold text-orange-900 mb-2">Shortfall</p>
                    <p class="text-xl font-bold text-orange-600">
                      £{{ formatNumber(plan.savings.emergency_fund.shortfall_amount) }}
                    </p>
                    <p class="text-xs text-orange-700 mt-1">
                      {{ plan.savings.emergency_fund.shortfall_months }} months needed
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Savings Accounts -->
          <div v-if="plan.savings.accounts.accounts.length > 0">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Savings Accounts</h3>
            <div class="space-y-3">
              <div
                v-for="account in plan.savings.accounts.accounts"
                :key="account.id"
                class="border border-gray-200 rounded-lg p-4"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="font-semibold text-gray-900">{{ account.institution }}</h4>
                    <p class="text-sm text-gray-600">
                      {{ account.account_type }}
                      <span v-if="account.is_instant_access" class="text-green-600 ml-2">• Instant Access</span>
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="text-lg font-semibold text-gray-900">£{{ formatNumber(account.balance) }}</p>
                    <p class="text-sm text-gray-600">{{ formatPercentage(account.interest_rate) }}% AER</p>
                  </div>
                </div>
                <div v-if="account.rate_comparison" class="mt-3 pt-3 border-t border-gray-100">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getRatingBadgeClass(account.rate_comparison.rating)">
                    Rate: {{ account.rate_comparison.rating }}
                  </span>
                  <span v-if="account.potential_gain > 0" class="ml-2 text-sm text-gray-600">
                    Potential gain: £{{ formatNumber(account.potential_gain) }}/year
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Savings Strategy -->
          <div v-if="plan.savings.strategy.recommendations.length > 0">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Savings Strategy</h3>
            <div class="space-y-3">
              <div
                v-for="(rec, index) in plan.savings.strategy.recommendations"
                :key="index"
                class="border-l-4 rounded-r-lg p-4"
                :class="getPriorityBorderClass(rec.priority)"
              >
                <div class="flex justify-between items-start mb-2">
                  <h4 class="font-semibold text-gray-900">{{ rec.category }}</h4>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getPriorityBadgeClass(rec.priority)">
                    {{ rec.priority }}
                  </span>
                </div>
                <p class="text-sm text-gray-700 mb-2">{{ rec.action_required }}</p>
                <div v-if="rec.shortfall_amount" class="bg-orange-50 rounded p-3 text-sm">
                  <p class="text-orange-900">
                    <span class="font-semibold">Shortfall:</span> £{{ formatNumber(rec.shortfall_amount) }}
                  </p>
                  <p v-if="rec.suggested_monthly_contribution" class="text-orange-800 mt-1">
                    <span class="font-semibold">Suggested monthly contribution:</span>
                    £{{ formatNumber(rec.suggested_monthly_contribution) }} over {{ rec.timeline }}
                  </p>
                </div>
                <div v-if="rec.potential_annual_gain" class="text-sm text-green-600 font-medium">
                  Potential gain: £{{ formatNumber(rec.potential_annual_gain) }}/year
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Combined Action Plan -->
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
          <h2 class="text-2xl font-bold text-white">Priority Action Plan</h2>
          <p class="text-sm text-purple-100 mt-1">
            Recommended actions prioritized by urgency and impact
          </p>
        </div>

        <div class="p-6">
          <div v-if="plan.action_plan.actions.length > 0" class="space-y-3">
            <div
              v-for="action in plan.action_plan.actions"
              :key="action.priority"
              class="border-l-4 rounded-r-lg p-4 flex items-start"
              :class="getUrgencyBorderClass(action.urgency)"
            >
              <div class="flex-shrink-0 mr-4">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white"
                  :class="getUrgencyBgClass(action.urgency)">
                  {{ action.priority }}
                </div>
              </div>
              <div class="flex-1">
                <div class="flex justify-between items-start mb-2">
                  <h4 class="font-semibold text-gray-900">{{ action.category }}</h4>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ml-2"
                    :class="getUrgencyBadgeClass(action.urgency)">
                    {{ action.urgency }}
                  </span>
                </div>
                <p class="text-sm text-gray-700 mb-1">{{ action.action }}</p>
                <p class="text-xs text-gray-600">Timeline: {{ action.timeline }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-500">
            <p>No urgent actions identified. Your financial position is healthy!</p>
          </div>

          <div v-if="plan.action_plan.total_actions > 0" class="mt-6 pt-6 border-t border-gray-200">
            <div class="grid grid-cols-3 gap-4 text-center">
              <div>
                <p class="text-2xl font-bold text-red-600">{{ plan.action_plan.critical_actions }}</p>
                <p class="text-xs text-gray-600">Critical</p>
              </div>
              <div>
                <p class="text-2xl font-bold text-orange-600">{{ plan.action_plan.high_priority_actions }}</p>
                <p class="text-xs text-gray-600">High Priority</p>
              </div>
              <div>
                <p class="text-2xl font-bold text-gray-600">{{ plan.action_plan.total_actions }}</p>
                <p class="text-xs text-gray-600">Total Actions</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import plansService from '@/services/plansService';

export default {
  name: 'InvestmentSavingsPlanView',

  data() {
    return {
      loading: false,
      error: null,
      plan: null,
    };
  },

  mounted() {
    this.loadPlan();
  },

  methods: {
    async loadPlan() {
      this.loading = true;
      this.error = null;

      try {
        const response = await plansService.generateInvestmentSavingsPlan();
        this.plan = response.data;
      } catch (err) {
        console.error('Failed to load plan:', err);
        this.error = err.response?.data?.message || 'An error occurred while loading the plan';
      } finally {
        this.loading = false;
      }
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '0';
      return Number(value).toLocaleString('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      });
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return '0.00';
      return Number(value).toFixed(2);
    },

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },

    getHealthColour(score) {
      if (!score) return 'text-gray-400';
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-yellow-600';
      return 'text-red-600';
    },

    getRunwayColour(months) {
      if (months >= 6) return 'text-green-600';
      if (months >= 3) return 'text-yellow-600';
      if (months >= 1) return 'text-orange-600';
      return 'text-red-600';
    },

    getReturnColour(value) {
      if (value === null || value === undefined) return 'text-gray-600';
      return value >= 0 ? 'text-green-600' : 'text-red-600';
    },

    getStatusBadgeClass(status) {
      const statusMap = {
        'Excellent': 'bg-green-100 text-green-800',
        'Adequate': 'bg-green-100 text-green-800',
        'Good': 'bg-blue-100 text-blue-800',
        'Moderate': 'bg-yellow-100 text-yellow-800',
        'Low': 'bg-orange-100 text-orange-800',
        'Critical': 'bg-red-100 text-red-800',
        'Unknown': 'bg-gray-100 text-gray-800',
      };
      return statusMap[status] || 'bg-gray-100 text-gray-800';
    },

    getPriorityBadgeClass(priority) {
      const priorityMap = {
        'Critical': 'bg-red-100 text-red-800',
        'High': 'bg-orange-100 text-orange-800',
        'Medium': 'bg-yellow-100 text-yellow-800',
        'Low': 'bg-blue-100 text-blue-800',
      };
      return priorityMap[priority] || 'bg-gray-100 text-gray-800';
    },

    getPriorityBorderClass(priority) {
      const priorityMap = {
        'Critical': 'border-red-500 bg-red-50',
        'High': 'border-orange-500 bg-orange-50',
        'Medium': 'border-yellow-500 bg-yellow-50',
        'Low': 'border-blue-500 bg-blue-50',
      };
      return priorityMap[priority] || 'border-gray-300 bg-gray-50';
    },

    getUrgencyBadgeClass(urgency) {
      const urgencyMap = {
        'Critical': 'bg-red-100 text-red-800',
        'High': 'bg-orange-100 text-orange-800',
        'Medium': 'bg-yellow-100 text-yellow-800',
        'Low': 'bg-blue-100 text-blue-800',
      };
      return urgencyMap[urgency] || 'bg-gray-100 text-gray-800';
    },

    getUrgencyBorderClass(urgency) {
      const urgencyMap = {
        'Critical': 'border-red-500 bg-red-50',
        'High': 'border-orange-500 bg-orange-50',
        'Medium': 'border-yellow-500 bg-yellow-50',
        'Low': 'border-blue-500 bg-blue-50',
      };
      return urgencyMap[urgency] || 'border-gray-300 bg-gray-50';
    },

    getUrgencyBgClass(urgency) {
      const urgencyMap = {
        'Critical': 'bg-red-600',
        'High': 'bg-orange-600',
        'Medium': 'bg-yellow-600',
        'Low': 'bg-blue-600',
      };
      return urgencyMap[urgency] || 'bg-gray-600';
    },

    getEmergencyFundBorderClass(colour) {
      const colourMap = {
        'green': 'border-green-500',
        'yellow': 'border-yellow-500',
        'orange': 'border-orange-500',
        'red': 'border-red-500',
      };
      return colourMap[colour] || 'border-gray-300';
    },

    getRatingBadgeClass(rating) {
      const ratingMap = {
        'Excellent': 'bg-green-100 text-green-800',
        'Good': 'bg-blue-100 text-blue-800',
        'Fair': 'bg-yellow-100 text-yellow-800',
        'Poor': 'bg-red-100 text-red-800',
      };
      return ratingMap[rating] || 'bg-gray-100 text-gray-800';
    },
  },
};
</script>

<style scoped>
/* Additional styles if needed */
</style>
