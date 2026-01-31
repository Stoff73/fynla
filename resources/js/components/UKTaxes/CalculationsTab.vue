<template>
  <div class="space-y-6">
    <div class="card">
      <h2 class="text-h2 text-gray-900 mb-4">How Fynla Calculations Work</h2>
      <p class="text-body text-gray-600 mb-6">
        This section explains the key financial calculations used throughout the Financial Planning System.
      </p>
    </div>

    <!-- Income Tax Calculation -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        Income Tax Calculation
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Personal Allowance</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          If income > £100,000:
          <br>Personal Allowance = £12,570 - ((income - £100,000) / 2)
          <br>Personal Allowance = max(0, Personal Allowance)
        </code>

        <p><strong>Step 2:</strong> Calculate Taxable Income</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Taxable Income = Gross Income - Personal Allowance
        </code>

        <p><strong>Step 3:</strong> Apply Tax Bands</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          £0 - £37,700: 20% (Basic Rate)
          <br>£37,701 - £125,140: 40% (Higher Rate)
          <br>£125,141+: 45% (Additional Rate)
        </code>

        <p><strong>Example:</strong> Income of £60,000</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Personal Allowance: £12,570
          <br>Taxable Income: £60,000 - £12,570 = £47,430
          <br>Basic Rate Tax: £37,700 × 20% = £7,540
          <br>Higher Rate Tax: £9,730 × 40% = £3,892
          <br>Total Tax: £7,540 + £3,892 = £11,432
        </code>
      </div>
    </div>

    <!-- IHT Calculation -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>
        Inheritance Tax (IHT) Calculation
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Gross Estate Value</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Gross Estate = Total Assets - Total Liabilities
          <br>(Includes: property, investments, savings, personal possessions)
        </code>

        <p><strong>Step 2:</strong> Determine Available Allowances</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          NRB Available = £325,000 × (1 + spouse_nrb_transferred)
          <br><br>If property asset AND passing to descendants:
          <br>&nbsp;&nbsp;RNRB Available = £175,000 × (1 + spouse_rnrb_transferred)
          <br>&nbsp;&nbsp;If estate > £2,000,000:
          <br>&nbsp;&nbsp;&nbsp;&nbsp;RNRB Reduction = (estate - £2,000,000) / 2
          <br>&nbsp;&nbsp;&nbsp;&nbsp;RNRB Available = max(0, RNRB Available - RNRB Reduction)
        </code>

        <p><strong>Step 3:</strong> Calculate Taxable Estate</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Total Allowances = NRB Available + RNRB Available
          <br>Taxable Estate = max(0, Gross Estate - Total Allowances)
        </code>

        <p><strong>Step 4:</strong> Calculate IHT Liability</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          IHT Liability = Taxable Estate × 40%
          <br>(Or 36% if 10%+ of net estate left to charity)
        </code>

        <p><strong>Example:</strong> Estate worth £800,000 with property, no spouse transfers</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Gross Estate: £800,000
          <br>NRB Available: £325,000
          <br>RNRB Available: £175,000 (property present)
          <br>Total Allowances: £500,000
          <br>Taxable Estate: £800,000 - £500,000 = £300,000
          <br>IHT Liability: £300,000 × 40% = £120,000
        </code>
      </div>
    </div>

    <!-- CGT Calculation -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        Capital Gains Tax (CGT) Calculation
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Capital Gain</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Capital Gain = Sale Price - Purchase Price - Allowable Costs
          <br>(Allowable costs: purchase fees, improvement costs, sale fees)
        </code>

        <p><strong>Step 2:</strong> Apply Annual Exempt Amount</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Taxable Gain = max(0, Capital Gain - £3,000)
        </code>

        <p><strong>Step 3:</strong> Determine CGT Rate</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          General Assets (shares, funds):
          <br>&nbsp;&nbsp;Basic Rate Taxpayer: 10%
          <br>&nbsp;&nbsp;Higher/Additional Rate: 20%
          <br><br>Residential Property:
          <br>&nbsp;&nbsp;Basic Rate Taxpayer: 18%
          <br>&nbsp;&nbsp;Higher/Additional Rate: 24%
        </code>

        <p><strong>Step 4:</strong> Calculate CGT Due</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          CGT Due = Taxable Gain × Applicable Rate
        </code>

        <p><strong>Example:</strong> Higher rate taxpayer sells shares</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Sale Price: £50,000
          <br>Purchase Price: £30,000
          <br>Sale Costs: £500
          <br>Capital Gain: £50,000 - £30,000 - £500 = £19,500
          <br>Annual Exempt Amount: £3,000
          <br>Taxable Gain: £19,500 - £3,000 = £16,500
          <br>CGT Rate: 20% (higher rate taxpayer, general asset)
          <br>CGT Due: £16,500 × 20% = £3,300
        </code>
      </div>
    </div>

    <!-- Pension Annual Allowance -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Pension Annual Allowance Calculation
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Determine Base Allowance</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          If accessed pension flexibly: £10,000 (MPAA)
          <br>Otherwise: £60,000 (Standard Annual Allowance)
        </code>

        <p><strong>Step 2:</strong> Check for Tapering (High Earners)</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          If threshold_income > £200,000 AND adjusted_income > £260,000:
          <br>&nbsp;&nbsp;Reduction = (adjusted_income - £260,000) / 2
          <br>&nbsp;&nbsp;Tapered Allowance = max(£10,000, £60,000 - Reduction)
        </code>

        <p><strong>Step 3:</strong> Add Carry Forward (if applicable)</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Total Available = Current Year Allowance + Unused from Last 3 Years
          <br>(Subject to pension scheme membership in those years)
        </code>

        <p><strong>Example:</strong> High earner with £300,000 adjusted income</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Standard Allowance: £60,000
          <br>Adjusted Income: £300,000
          <br>Exceeds £260,000 by: £40,000
          <br>Reduction: £40,000 / 2 = £20,000
          <br>Tapered Allowance: £60,000 - £20,000 = £40,000
        </code>
      </div>
    </div>

    <!-- Emergency Fund Calculation -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        Emergency Fund Runway Calculation
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Monthly Essential Expenses</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Essential Expenses = Mortgage/Rent + Bills + Food + Transport + Insurance
          <br>(Excludes discretionary spending like entertainment, dining out)
        </code>

        <p><strong>Step 2:</strong> Calculate Total Emergency Fund</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Emergency Fund = Sum of all savings accounts marked as "emergency fund"
          <br>(Easy access and notice accounts, not long-term fixed accounts)
        </code>

        <p><strong>Step 3:</strong> Calculate Runway</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Runway (months) = Emergency Fund / Monthly Essential Expenses
        </code>

        <p><strong>Recommendation:</strong></p>
        <code class="block bg-green-50 p-3 rounded text-xs font-mono">
          Target: 3-6 months of essential expenses
          <br>Higher for self-employed or single income households
        </code>

        <p><strong>Example:</strong></p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Monthly Essential Expenses: £2,500
          <br>Emergency Fund Balance: £15,000
          <br>Runway: £15,000 / £2,500 = 6 months
          <br>Status: Adequate ✓
        </code>
      </div>
    </div>

    <!-- Portfolio Allocation Calculation -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        Investment Portfolio Allocation
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Current Allocation</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          For each asset class:
          <br>Current % = (Asset Class Value / Total Portfolio Value) × 100
        </code>

        <p><strong>Step 2:</strong> Compare to Target Allocation</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Deviation = Current % - Target %
          <br>Absolute Deviation = |Current % - Target %|
        </code>

        <p><strong>Step 3:</strong> Determine Rebalancing Need</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          If any Absolute Deviation > 5%:
          <br>&nbsp;&nbsp;Rebalancing Recommended = true
        </code>

        <p><strong>Example:</strong> Portfolio worth £100,000</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Target: 60% Equities, 40% Bonds
          <br>Current: £65,000 Equities (65%), £35,000 Bonds (35%)
          <br>Equities Deviation: 65% - 60% = +5%
          <br>Bonds Deviation: 35% - 40% = -5%
          <br>Rebalancing: Recommended (deviation = 5%)
          <br>Action: Sell £5,000 equities, buy £5,000 bonds
        </code>
      </div>
    </div>

    <!-- Retirement Readiness Score -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        Retirement Readiness Score
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Projected Retirement Income</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          DC Pension Pot at Retirement = Current Value × (1 + growth_rate)^years
          <br>DC Annual Income = Pot × Safe Withdrawal Rate (typically 4%)
          <br>Total Projected Income = DC Income + DB Income + State Pension
        </code>

        <p><strong>Step 2:</strong> Determine Target Retirement Income</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Target Income = Current Income × Replacement Ratio
          <br>(Replacement Ratio typically 50-70% of pre-retirement income)
        </code>

        <p><strong>Step 3:</strong> Calculate Readiness Score</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Readiness Score = (Projected Income / Target Income) × 100
          <br>Score = min(100, Readiness Score)
        </code>

        <p><strong>Score Interpretation:</strong></p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          90-100%: On track (green)
          <br>70-89%: Nearly there (orange)
          <br>Below 70%: Action needed (red)
        </code>

        <p><strong>Example:</strong> 20 years to retirement</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Current DC Pot: £150,000
          <br>Growth Rate: 5% per year
          <br>Future Pot: £150,000 × 1.05^20 = £397,989
          <br>DC Income: £397,989 × 4% = £15,920
          <br>State Pension: £11,502
          <br>Total Projected: £27,422
          <br>Target Income: £40,000 (67% of £60,000 current income)
          <br>Readiness Score: (£27,422 / £40,000) × 100 = 69%
          <br>Status: Action needed - increase contributions
        </code>
      </div>
    </div>

    <!-- Protection Coverage Gap -->
    <div class="card">
      <h3 class="text-h3 text-gray-900 mb-3 flex items-center">
        <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        Protection Coverage Gap Analysis
      </h3>
      <div class="space-y-3 text-sm">
        <p><strong>Step 1:</strong> Calculate Human Capital Value</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Years to Retirement = Retirement Age - Current Age
          <br>Future Earnings = Annual Income × Years to Retirement
          <br>Discount Rate = 3% (inflation-adjusted)
          <br>Human Capital = Present Value of Future Earnings
        </code>

        <p><strong>Step 2:</strong> Determine Required Coverage</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Life Insurance Need = Human Capital + Debts - Existing Assets
          <br>Critical Illness Need = 3-5 years of income
          <br>Income Protection Need = 60-70% of gross income until retirement
        </code>

        <p><strong>Step 3:</strong> Calculate Coverage Gap</p>
        <code class="block bg-gray-50 p-3 rounded text-xs font-mono">
          Coverage Gap = Required Coverage - Existing Coverage
          <br>Adequacy Score = (Existing Coverage / Required Coverage) × 100
        </code>

        <p><strong>Example:</strong> 35-year-old earning £50,000</p>
        <code class="block bg-blue-50 p-3 rounded text-xs font-mono">
          Years to Retirement: 67 - 35 = 32 years
          <br>Future Earnings: £50,000 × 32 = £1,600,000
          <br>Present Value (3% discount): £987,654
          <br>Add Mortgage: £200,000
          <br>Less Existing Assets: -£50,000
          <br>Required Life Cover: £1,137,654
          <br>Existing Cover: £250,000
          <br>Coverage Gap: £887,654
          <br>Adequacy Score: (£250,000 / £1,137,654) × 100 = 22%
          <br>Status: Significant gap - increase coverage
        </code>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CalculationsTab',
};
</script>
