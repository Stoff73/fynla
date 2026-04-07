<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <ModuleStatusBar />
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Help & Documentation</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Comprehensive guide to using Fynla Financial Planning System
        </p>
      </div>

      <!-- Search Bar -->
      <div class="mb-8">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            class="block w-full pl-10 pr-3 py-3 border border-horizon-300 rounded-lg leading-5 bg-white placeholder-neutral-500 focus:outline-none focus:placeholder-horizon-400 focus:ring-2 focus:ring-violet-500 focus:border-violet-500 sm:text-sm"
            placeholder="Search help articles... (e.g., 'how to add protection policy', 'inheritance tax', 'spouse linking')"
          >
        </div>
        <p v-if="searchQuery && filteredSections.length === 0" class="mt-2 text-sm text-red-600">
          No results found for "{{ searchQuery }}". Try different keywords.
        </p>
        <p v-else-if="searchQuery" class="mt-2 text-sm text-neutral-500">
          Found {{ filteredSections.length }} section(s) matching "{{ searchQuery }}"
        </p>
      </div>

      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar Table of Contents -->
        <div class="lg:w-64 flex-shrink-0">
          <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
            <h2 class="text-h5 font-semibold text-horizon-500 mb-4">Table of Contents</h2>
            <nav class="space-y-2">
              <a
                v-for="section in visibleSections"
                :key="section.id"
                :href="`#${section.id}`"
                @click.prevent="scrollToSection(section.id)"
                class="block text-sm text-neutral-500 hover:text-raspberry-500 hover:bg-savannah-100 px-3 py-2 rounded-md transition-colors"
                :class="{ 'bg-raspberry-50 text-raspberry-500 font-medium': activeSection === section.id }"
              >
                {{ section.title }}
              </a>
            </nav>
          </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 space-y-8">
          <!-- Getting Started -->
          <section v-if="shouldShowSection('getting-started')" id="getting-started" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Getting Started</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Welcome to Fynla</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Fynla is a comprehensive financial planning system designed for UK users. It helps you manage your protection, estate, retirement, investment, and savings planning all in one place.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">First Time Setup</h3>
                <ol class="list-decimal list-inside space-y-2 text-body-base text-horizon-500">
                  <li>Create your account using the registration page</li>
                  <li>Choose your focus area (Estate Planning, Protection, Retirement, etc.)</li>
                  <li>Complete the onboarding wizard with your personal and financial information</li>
                  <li>Review your dashboard and explore the modules</li>
                  <li>Add family members and link spouse accounts if applicable</li>
                </ol>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Key Concepts</h3>
                <ul class="space-y-2 text-body-base text-horizon-500">
                  <li><strong>Modules:</strong> Five main areas - Protection, Estate, Retirement, Investment, and Savings</li>
                  <li><strong>Plans:</strong> Consolidated views combining data from multiple modules</li>
                  <li><strong>Agents:</strong> AI-powered analysis engines that generate recommendations</li>
                  <li><strong>Spouse Linking:</strong> Connect two accounts for joint financial planning</li>
                  <li><strong>Data Sharing:</strong> Control what information is shared with your linked spouse</li>
                </ul>
              </div>
            </div>
          </section>

          <!-- Dashboard Overview -->
          <section v-if="shouldShowSection('dashboard')" id="dashboard" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Dashboard Overview</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Main Dashboard</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Your dashboard provides a high-level overview of your financial situation across all modules. Each card shows key metrics and provides quick access to detailed views.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Dashboard Cards</h3>
                <ul class="space-y-3 text-body-base text-horizon-500">
                  <li><strong>Net Worth:</strong> Total assets minus liabilities across all categories</li>
                  <li><strong>Estate Planning:</strong> Current inheritance tax position and net worth breakdown</li>
                  <li><strong>Protection:</strong> Coverage gap analysis for life, critical illness, and income protection</li>
                  <li><strong>Trusts:</strong> Number of trusts and total assets held in trust</li>
                  <li><strong>Plans:</strong> Quick access to Protection, Estate, Retirement, and Investment/Savings plans</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Quick Actions</h3>
                <p class="text-body-base text-horizon-500">
                  The Plans card on your dashboard provides quick access to comprehensive planning views that combine data from multiple modules for holistic analysis.
                </p>
              </div>
            </div>
          </section>

          <!-- User Profile -->
          <section v-if="shouldShowSection('user-profile')" id="user-profile" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">User Profile & Settings</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Personal Information</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Update your name, email, date of birth, gender, marital status, National Insurance number, phone, and address. This information is used throughout the system for calculations and projections.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Income & Occupation</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Enter your employment income, self-employment income, rental income, dividend income, and other income sources. Also specify your occupation, which affects protection insurance recommendations.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Health Information</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Specify your health status, smoking status, and education level. This information helps provide accurate protection recommendations and estimate insurance premium costs.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Domicile Information</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Enter your UK domicile status, residency details, and any foreign assets. This is critical for inheritance tax calculations and estate planning, as domicile status affects tax liability.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Family Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Add family members including spouse, children, and other dependents. For spouses with email addresses, you can create linked accounts for joint financial planning.
                </p>
              </div>
            </div>
          </section>

          <!-- Protection Module -->
          <section v-if="shouldShowSection('protection')" id="protection" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Protection Module</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Overview</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  The Protection module helps you analyse your insurance coverage across life insurance, critical illness, income protection, and disability insurance.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Current Situation Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  View all your existing protection policies. Add new policies using the "Add Policy" button. Each policy type has specific fields:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Life Insurance:</strong> Policy type (Term, Whole of Life, etc.), sum assured, premium, term</li>
                  <li><strong>Critical Illness:</strong> Standalone, Accelerated, or Additional coverage</li>
                  <li><strong>Income Protection:</strong> Benefit amount, frequency (monthly/weekly), deferred period</li>
                  <li><strong>Disability:</strong> Benefit amount and frequency</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Gap Analysis Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Analyses your protection needs versus current coverage:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Human Capital:</strong> Value of your future earnings (income replacement need)</li>
                  <li><strong>Debt Protection:</strong> Outstanding mortgages and liabilities</li>
                  <li><strong>Final Expenses:</strong> Funeral costs and immediate expenses (£7,500)</li>
                  <li><strong>Coverage Gap:</strong> Difference between total need and current coverage</li>
                </ul>
                <p class="text-body-base text-horizon-500 mt-4">
                  <strong>Note:</strong> Spouse income reduces your protection need, as their income continues after your death.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Strategy Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  View AI-generated recommendations for improving your protection coverage, prioritised by importance.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Policy Details Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Detailed list of all policies with edit and delete functionality. Policies are grouped by type for easy management.
                </p>
              </div>
            </div>
          </section>

          <!-- Estate Planning -->
          <section v-if="shouldShowSection('estate')" id="estate" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Estate Planning Module</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Overview</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  The Estate Planning module calculates your inheritance tax liability, tracks your net worth, and provides strategies for reducing the tax your beneficiaries will pay.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Current Situation Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  View your net worth breakdown and current inheritance tax position:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Assets:</strong> Property, pensions, investments, savings, business, other assets</li>
                  <li><strong>Liabilities:</strong> Mortgages, loans, credit cards, other debts</li>
                  <li><strong>Net Estate:</strong> Total assets minus liabilities</li>
                  <li><strong>Inheritance Tax Calculation:</strong> Tax-free allowance (£325k), home allowance (£175k for main residence), tax liability</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Inheritance Tax Planning Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  For married couples, view Second Death analysis with combined tax-free allowances (up to £650k basic allowance, plus up to £350k home allowance). Includes spouse exemption on first death.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Gifting Timeline</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Track gifts made in the last 7 years. Gifts older than 7 years are outside the estate for inheritance tax purposes. The timeline shows:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Potentially Exempt Transfers:</strong> Gifts to individuals, exempt after 7 years</li>
                  <li><strong>Chargeable Lifetime Transfers:</strong> Gifts to trusts, subject to Inheritance Tax immediately</li>
                  <li><strong>Taper Relief:</strong> Reduces Inheritance Tax on gifts made 3-7 years before death</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Will Planning Tab</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Enter your will information including executor details, death scenario (user only or simultaneous), spouse bequest percentage, and last updated date.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Letter to Spouse</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Create emergency instructions for your spouse including protection policies, estate information, savings accounts, and important contacts. View your spouse's letter to you if data sharing is enabled.
                </p>
              </div>
            </div>
          </section>

          <!-- Retirement Planning -->
          <section v-if="shouldShowSection('retirement')" id="retirement" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Retirement Planning Module</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Overview</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Track your pension pots, project retirement income, and ensure you're on track for your retirement goals.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Pension Types</h3>
                <ul class="space-y-2 text-body-base text-horizon-500">
                  <li><strong>Money Purchase Pensions:</strong> Defined contribution (pot-based) pensions with portfolio holdings management</li>
                  <li><strong>Final Salary Pensions:</strong> Defined benefit pensions with guaranteed income</li>
                  <li><strong>State Pension:</strong> UK State Pension based on National Insurance contributions</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Money Purchase Pension Holdings</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Manage individual fund holdings within money purchase pension pots including fund names, ISIN codes, units held, and fees. Access portfolio analysis including:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Asset allocation breakdown (Equities, Bonds, Property, Cash, Alternatives)</li>
                  <li>Risk metrics (Alpha, Beta, Sharpe Ratio, Volatility, Max Drawdown, VaR)</li>
                  <li>Fee analysis with low-cost alternatives comparison</li>
                  <li>Diversification analysis</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Annual Allowance</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Track contributions against the £60,000 annual allowance ({{ currentTaxYear }}). Includes carry forward from previous 3 years if you didn't use your full allowance.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Retirement Readiness</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Assessment of your retirement readiness based on your current savings, pension provisions, and projected income needs.
                </p>
              </div>
            </div>
          </section>

          <!-- Investment & Savings -->
          <section v-if="shouldShowSection('investment-savings')" id="investment-savings" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Investment & Savings</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Investment Module</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Manage investment accounts and holdings with portfolio analysis:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Account Types:</strong> ISA, General Investment Account, National Savings & Investments, Onshore/Offshore Bonds, Venture Capital Trust, Enterprise Investment Scheme</li>
                  <li><strong>Holdings:</strong> Individual fund/share holdings with ISIN codes</li>
                  <li><strong>Portfolio Analysis:</strong> Risk metrics, asset allocation, fee analysis</li>
                  <li><strong>Monte Carlo Simulation:</strong> 1,000 iterations projecting portfolio growth</li>
                  <li><strong>Efficient Frontier:</strong> Optimal portfolio allocation analysis</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">ISA Allowance Tracking</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Track ISA contributions across Cash ISAs (Savings module) and Stocks & Shares ISAs (Investment module) against the £20,000 annual allowance (tax year: April 6 - April 5).
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Savings Module</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Manage savings accounts and goals:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Account Types:</strong> Current Account, Savings Account, Cash ISA</li>
                  <li><strong>Access Types:</strong> Immediate Access, Notice Period, Fixed Term</li>
                  <li><strong>Emergency Fund:</strong> Calculate recommended emergency fund (3-6 months expenses)</li>
                  <li><strong>Savings Goals:</strong> Track progress toward specific financial goals</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Investment & Savings Plan</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Consolidated view combining Investment and Savings modules with risk metrics, goal tracking, and asset allocation analysis. Access from Quick Actions on the dashboard.
                </p>
              </div>
            </div>
          </section>

          <!-- Family & Spouse Management -->
          <section v-if="shouldShowSection('family-spouse')" id="family-spouse" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Family & Spouse Management</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Adding Family Members</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Go to User Profile → Family tab and click "Add Family Member". Enter details including:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Name, date of birth, gender</li>
                  <li>Relationship (spouse, child, parent, sibling, other)</li>
                  <li>National Insurance number (optional)</li>
                  <li>Annual income (for spouse)</li>
                  <li>Is dependent checkbox</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Spouse Account Linking</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  When adding a spouse with an email address, the system will:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>If spouse has an account:</strong> Link the two accounts together for joint planning</li>
                  <li><strong>If spouse is new:</strong> Create a new account with a temporary password sent via email</li>
                  <li>Both accounts are linked bidirectionally with marital_status set to 'married'</li>
                  <li>Spouse income is synced to the spouse's user account for calculations</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Data Sharing Permissions</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Control what data is shared between linked spouse accounts:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>View spouse's protection policies</li>
                  <li>View spouse's estate information</li>
                  <li>View spouse's gifts for inheritance tax timeline</li>
                  <li>View spouse's Letter to Spouse</li>
                  <li>Permissions must be accepted by both parties</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Joint Ownership</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Assets can be owned individually, jointly, or in trust. For joint ownership, specify the joint owner from your family members list. Common for:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Main residence (often jointly owned by spouses)</li>
                  <li>Joint savings accounts</li>
                  <li>Joint investment accounts</li>
                </ul>
                <p class="text-body-base text-horizon-500 mt-2">
                  <strong>Note:</strong> ISAs must always be individually owned (UK tax rule).
                </p>
              </div>
            </div>
          </section>

          <!-- Onboarding -->
          <section v-if="shouldShowSection('onboarding')" id="onboarding" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Onboarding Process</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Focus Area Selection</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Choose your primary focus area to customise the onboarding journey:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li><strong>Estate Planning:</strong> Inheritance tax reduction and will planning</li>
                  <li><strong>Protection:</strong> Insurance coverage gap analysis</li>
                  <li><strong>Retirement:</strong> Pension planning and projections</li>
                  <li><strong>Investment:</strong> Portfolio management and optimisation</li>
                  <li><strong>Tax Optimisation:</strong> (Coming soon)</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Onboarding Steps</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  The onboarding wizard guides you through essential information collection:
                </p>
                <ol class="list-decimal list-inside space-y-2 text-body-base text-horizon-500">
                  <li><strong>Personal Information:</strong> Date of birth, gender, marital status, address, health, and lifestyle</li>
                  <li><strong>Income & Expenditure:</strong> All income sources and monthly/annual expenses</li>
                  <li><strong>Domicile Information:</strong> UK domicile status and foreign assets (if applicable)</li>
                  <li><strong>Family Information:</strong> Spouse and children details</li>
                  <li><strong>Assets:</strong> Properties, pensions, investments, savings, business assets</li>
                  <li><strong>Liabilities:</strong> Mortgages, loans, credit cards</li>
                  <li><strong>Protection Policies:</strong> Existing insurance policies</li>
                  <li><strong>Will Information:</strong> Executor, bequests, last updated date</li>
                  <li><strong>Trust Information:</strong> (Optional) Existing trusts</li>
                </ol>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Skip vs Complete</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  You can skip optional steps during onboarding and return later to complete them from the relevant module. However, key information like personal details, income, and assets are required for accurate calculations.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Restarting Onboarding</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  If you want to restart the onboarding process, contact support. Note that this will not delete your existing data.
                </p>
              </div>
            </div>
          </section>

          <!-- FAQs -->
          <section v-if="shouldShowSection('faqs')" id="faqs" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Frequently Asked Questions</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">How do I add a protection policy?</h3>
                <p class="text-body-base text-horizon-500">
                  Go to Protection module → Policy Details tab → Click "Add Policy". Select the policy type (Life, Critical Illness, etc.) and fill in the required details including sum assured, premium, and term.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Why is my spouse income not showing in Gap Analysis?</h3>
                <p class="text-body-base text-horizon-500">
                  Ensure: 1) You've added your spouse in the Family tab with their income, 2) Created/linked their spouse account, 3) Both users have accepted data sharing permissions. Spouse income reduces your protection need as their income continues after your death.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">How is inheritance tax calculated?</h3>
                <p class="text-body-base text-horizon-500">
                  Inheritance tax is charged at 40% on your estate above the tax-free allowances. For {{ currentTaxYear }}: £325k basic allowance (transferable to spouse), £175k home allowance (for main residence left to children, transferable to spouse). Married couples can have combined allowances of £650k basic plus £350k home allowance on second death.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">What's the difference between money purchase and final salary pensions?</h3>
                <p class="text-body-base text-horizon-500">
                  Money purchase (defined contribution) pensions are pot-based - you contribute, it grows, and you draw from the pot. Final salary (defined benefit) pensions provide guaranteed income based on your salary and years of service. Fynla supports full holdings management for money purchase pensions with portfolio analysis.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Can I have multiple ISAs?</h3>
                <p class="text-body-base text-horizon-500">
                  Yes, but you can only contribute to one Cash ISA and one Stocks & Shares ISA per tax year. Total contributions across all ISAs cannot exceed £20,000 per tax year (April 6 - April 5). Fynla automatically tracks your ISA allowance usage.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">How do I link my spouse account?</h3>
                <p class="text-body-base text-horizon-500">
                  Go to User Profile → Family tab → Add Family Member → Select "spouse" and enter their email. If they have an account, it will link automatically. If not, the system creates an account and emails them login details.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">What is the Emergency Fund calculator?</h3>
                <p class="text-body-base text-horizon-500">
                  The Emergency Fund calculator in the Savings module recommends saving 3-6 months of essential expenses. It analyses your current savings runway and suggests additional contributions needed to reach this safety net.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">What are the portfolio risk metrics?</h3>
                <p class="text-body-base text-horizon-500">
                  Fynla calculates: Alpha (excess returns vs benchmark), Beta (market sensitivity), Sharpe Ratio (risk-adjusted returns), Volatility (standard deviation), Max Drawdown (largest peak-to-trough decline), and VaR 95% (potential loss at 95% confidence).
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Can I export my data?</h3>
                <p class="text-body-base text-horizon-500">
                  Currently, you can print/export the Protection Plan and Estate Plan as PDFs. Comprehensive CSV/Excel export functionality is in development.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Is my data secure?</h3>
                <p class="text-body-base text-horizon-500">
                  Yes. Fynla uses Laravel Sanctum for authentication, all API routes are protected, and users can only access their own data. Passwords are hashed, and all communication uses HTTPS. For demonstration purposes, this system should not be used for real financial planning.
                </p>
              </div>
            </div>
          </section>

          <!-- Troubleshooting -->
          <section v-if="shouldShowSection('troubleshooting')" id="troubleshooting" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Troubleshooting</h2>

            <div class="space-y-6">
              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">I can't see my policies in Gap Analysis</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Solutions:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Refresh the page to reload data from the server</li>
                  <li>Check Policy Details tab to verify policies were saved</li>
                  <li>Clear your browser cache and reload</li>
                  <li>If issue persists, the policies may need re-entry</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Inheritance tax calculation seems wrong</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Common issues:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Verify all assets are entered correctly (check Estate → Current Situation)</li>
                  <li>Ensure liabilities are entered (they reduce net estate)</li>
                  <li>Check domicile status (affects inheritance tax liability)</li>
                  <li>For married couples, check if tax-free allowance transfer from deceased spouse is set correctly</li>
                  <li>Verify home allowance eligibility (requires leaving main residence to direct descendants)</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Data not saving</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Try these steps:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Check for validation errors displayed in red at the top of the form</li>
                  <li>Ensure all required fields (marked with *) are filled</li>
                  <li>Check your internet connection</li>
                  <li>Look for error messages in the browser console (F12 → Console tab)</li>
                  <li>Try logging out and logging back in</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Spouse account linking failed</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Possible causes:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>The spouse email is already linked to another user</li>
                  <li>You entered your own email address (cannot link to yourself)</li>
                  <li>Email format is invalid</li>
                  <li>Check error message for specific issue</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Numbers not displaying correctly</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Common fixes:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>Refresh the page to recalculate</li>
                  <li>Check that all underlying data is correct (income, assets, etc.)</li>
                  <li>Verify date formats are correct (YYYY-MM-DD)</li>
                  <li>Clear browser cache if numbers seem outdated</li>
                </ul>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Monte Carlo simulation not running</h3>
                <p class="text-body-base text-horizon-500 mb-4">
                  Requirements:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4">
                  <li>You must have at least one investment account with holdings</li>
                  <li>Holdings must have current values entered</li>
                  <li>The simulation runs in background queue - may take a few moments</li>
                  <li>Refresh the page after 30 seconds to see results</li>
                </ul>
              </div>
            </div>
          </section>

          <!-- Contact Support -->
          <section v-if="shouldShowSection('contact')" id="contact" class="bg-white rounded-lg shadow-sm p-8">
            <h2 class="text-h3 font-display text-horizon-500 mb-4">Contact Support</h2>

            <div class="space-y-4">
              <p class="text-body-base text-horizon-500">
                Need additional help? Contact our support team:
              </p>

              <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Support Information</h3>
                <ul class="space-y-2 text-body-base text-horizon-500">
                  <li><strong>Email:</strong> support@fynla.com</li>
                  <li><strong>Response Time:</strong> Within 24 hours</li>
                  <li><strong>Available:</strong> Monday - Friday, 9am - 5pm GMT</li>
                </ul>
              </div>

              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Important Note</h3>
                <p class="text-body-base text-horizon-500">
                  Fynla is a demonstration financial planning system. It is <strong>not</strong> a regulated financial advice service. For actual financial planning, please consult with a qualified, FCA-regulated financial adviser.
                </p>
              </div>

              <div>
                <h3 class="text-h5 font-semibold text-horizon-500 mb-2">Report a Bug</h3>
                <p class="text-body-base text-horizon-500">
                  If you encounter technical issues or bugs, please report them via email with:
                </p>
                <ul class="list-disc list-inside space-y-1 text-body-base text-horizon-500 ml-4 mt-2">
                  <li>Description of the issue</li>
                  <li>Steps to reproduce</li>
                  <li>Browser and version</li>
                  <li>Screenshots if applicable</li>
                </ul>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

export default {
  name: 'HelpView',

  components: {
    AppLayout,
    ModuleStatusBar,
  },

  setup() {
    const searchQuery = ref('');
    const activeSection = ref('');
    const currentTaxYear = computed(() => getCurrentTaxYear());

    const sections = [
      { id: 'getting-started', title: 'Getting Started', keywords: ['welcome', 'first time', 'setup', 'introduction', 'start', 'begin', 'new user'] },
      { id: 'dashboard', title: 'Dashboard Overview', keywords: ['dashboard', 'overview', 'cards', 'quick actions', 'main page', 'home'] },
      { id: 'user-profile', title: 'User Profile & Settings', keywords: ['profile', 'settings', 'personal information', 'income', 'occupation', 'health', 'domicile', 'address', 'family tab'] },
      { id: 'protection', title: 'Protection Module', keywords: ['protection', 'insurance', 'life insurance', 'critical illness', 'income protection', 'disability', 'gap analysis', 'coverage', 'policy', 'human capital'] },
      { id: 'estate', title: 'Estate Planning', keywords: ['estate', 'iht', 'inheritance tax', 'will', 'gifting', 'nrb', 'rnrb', 'nil rate band', 'letter to spouse', 'probate', 'death'] },
      { id: 'retirement', title: 'Retirement Planning', keywords: ['retirement', 'pension', 'dc pension', 'db pension', 'state pension', 'annual allowance', 'holdings', 'portfolio'] },
      { id: 'investment-savings', title: 'Investment & Savings', keywords: ['investment', 'savings', 'isa', 'gia', 'portfolio', 'holdings', 'emergency fund', 'monte carlo', 'efficient frontier', 'risk metrics', 'asset allocation'] },
      { id: 'family-spouse', title: 'Family & Spouse Management', keywords: ['family', 'spouse', 'linking', 'joint', 'children', 'dependents', 'data sharing', 'permissions', 'joint ownership'] },
      { id: 'onboarding', title: 'Onboarding Process', keywords: ['onboarding', 'setup wizard', 'focus area', 'initial setup', 'getting started wizard'] },
      { id: 'faqs', title: 'FAQs', keywords: ['faq', 'frequently asked', 'questions', 'common', 'how to', 'help'] },
      { id: 'troubleshooting', title: 'Troubleshooting', keywords: ['troubleshooting', 'problem', 'issue', 'error', 'bug', 'not working', 'broken', 'fix'] },
      { id: 'contact', title: 'Contact Support', keywords: ['contact', 'support', 'help', 'email', 'report bug', 'assistance'] },
    ];

    const filteredSections = computed(() => {
      if (!searchQuery.value) {
        return sections;
      }

      const query = searchQuery.value.toLowerCase();

      return sections.filter(section => {
        // Check title
        if (section.title.toLowerCase().includes(query)) {
          return true;
        }

        // Check keywords
        if (section.keywords.some(keyword => keyword.toLowerCase().includes(query))) {
          return true;
        }

        // Check section content by ID
        const sectionElement = document.getElementById(section.id);
        if (sectionElement) {
          const sectionText = sectionElement.textContent.toLowerCase();
          if (sectionText.includes(query)) {
            return true;
          }
        }

        return false;
      });
    });

    const visibleSections = computed(() => {
      return searchQuery.value ? filteredSections.value : sections;
    });

    const shouldShowSection = (sectionId) => {
      if (!searchQuery.value) {
        return true;
      }
      return filteredSections.value.some(section => section.id === sectionId);
    };

    const scrollToSection = (sectionId) => {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        activeSection.value = sectionId;
      }
    };

    // Handle scroll to update active section
    const handleScroll = () => {
      const scrollPosition = window.scrollY + 100;

      for (const section of sections) {
        const element = document.getElementById(section.id);
        if (element) {
          const { offsetTop, offsetHeight } = element;
          if (scrollPosition >= offsetTop && scrollPosition < offsetTop + offsetHeight) {
            activeSection.value = section.id;
            break;
          }
        }
      }
    };

    onMounted(() => {
      window.addEventListener('scroll', handleScroll);
      handleScroll(); // Set initial active section
    });

    onUnmounted(() => {
      window.removeEventListener('scroll', handleScroll);
    });

    return {
      searchQuery,
      activeSection,
      currentTaxYear,
      sections,
      filteredSections,
      visibleSections,
      shouldShowSection,
      scrollToSection,
    };
  },
};
</script>

<style scoped>
/* Smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Highlight search results */
:deep(mark) {
  @apply bg-blue-100;
  padding: 2px 4px;
  border-radius: 2px;
}
</style>
