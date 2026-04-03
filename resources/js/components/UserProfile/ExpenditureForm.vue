<template>
  <div class="space-y-6">
    <!-- Budget Type Segmented Control (only shown when showBudgetTabs is true) -->
    <div v-if="showBudgetTabs" class="mb-4">
      <div class="inline-flex bg-neutral-100 rounded-lg p-0.5">
        <button
          type="button"
          @click="activeBudgetTab = 'current'"
          :class="[
            'text-sm px-4 py-1.5 rounded-md font-medium transition-all duration-200',
            activeBudgetTab === 'current'
              ? 'bg-white text-horizon-500 font-semibold shadow-sm'
              : 'text-neutral-500 hover:text-horizon-500'
          ]"
        >
          Current Budget
        </button>
        <button
          type="button"
          @click="activeBudgetTab = 'retired'"
          :class="[
            'text-sm px-4 py-1.5 rounded-md font-medium transition-all duration-200',
            activeBudgetTab === 'retired'
              ? 'bg-white text-horizon-500 font-semibold shadow-sm'
              : 'text-neutral-500 hover:text-horizon-500'
          ]"
        >
          Budget at Retirement
        </button>
        <button
          v-if="isMarried"
          type="button"
          @click="activeBudgetTab = 'widowed'"
          :class="[
            'text-sm px-4 py-1.5 rounded-md font-medium transition-all duration-200',
            activeBudgetTab === 'widowed'
              ? 'bg-white text-horizon-500 font-semibold shadow-sm'
              : 'text-neutral-500 hover:text-horizon-500'
          ]"
        >
          Budget if Widowed
        </button>
      </div>
    </div>

    <!-- CURRENT BUDGET TAB -->
    <div v-if="activeBudgetTab === 'current'">
      <!-- VIEW MODE -->
      <div v-if="!isEditing">
        <!-- Notes Section (collapsed in view mode) -->
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
          <p class="text-body-sm text-violet-800">
            <strong>Note:</strong> Financial commitments (mortgages, loans, pensions, investments, protection) are automatically pulled from other modules.
          </p>
        </div>

        <!-- Header with Edit Button -->
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Monthly Expenditure Summary</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Entry Mode: {{ useSimpleEntry ? 'Simple Total' : 'Detailed Breakdown' }}
              <span v-if="isMarried"> · {{ useSeparateExpenditure ? 'Separate' : 'Joint (50/50)' }} expenditure</span>
            </p>
          </div>
          <button type="button" @click="isEditing = true" class="btn-secondary">
            Edit
          </button>
        </div>

      <!-- Simple Entry View -->
      <div v-if="useSimpleEntry">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-6">
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-body-sm text-neutral-500">Monthly Expenditure:</span>
              <span class="text-body-sm text-horizon-500 text-right font-medium">{{ formatCurrency(simpleMonthlyExpenditure) }}</span>
            </div>
            <div v-if="isMarried && useSeparateExpenditure" class="flex justify-between">
              <span class="text-body-sm text-neutral-500">Spouse Monthly:</span>
              <span class="text-body-sm text-horizon-500 text-right font-medium">{{ formatCurrency(spouseSimpleMonthlyExpenditure) }}</span>
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-body-sm text-neutral-500">Annual Expenditure:</span>
              <span class="text-body-sm text-horizon-500 text-right font-medium">{{ formatCurrency(simpleMonthlyExpenditure * 12) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Detailed Entry View -->
      <div v-else>
        <!-- Three Column Layout for Married, Two for Single -->
        <div :class="isMarried ? 'expenditure-grid-married' : 'expenditure-grid-single'">
          <!-- Column Headers -->
          <div class="col-label font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">Category</div>
          <div class="col-value font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">{{ userName }}</div>
          <div v-if="isMarried" class="col-value-mid font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">{{ spouseName }}</div>
          <div v-if="isMarried" class="col-total font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">Household</div>

          <!-- Essential Living Expenses -->
          <ExpenditureSection
            title="Essential Living"
            :is-expanded="isSectionExpanded('current', 'essential')"
            :user-total="essentialTotal"
            :spouse-total="spouseEssentialTotal"
            :household-total="householdEssentialTotal"
            :is-married="isMarried"
            @toggle="toggleSection('current', 'essential')"
          >
            <ExpenditureGridRow
              v-for="field in essentialFields"
              :key="field.key"
              :label="field.label"
              :value="formData[field.key]"
              :spouse-value="spouseFormData[field.key]"
              :household-value="getHouseholdValue(field.key)"
              :is-married="isMarried"
              indent
            />
          </ExpenditureSection>

          <!-- Communication & Technology -->
          <ExpenditureSection
            title="Communication & Technology"
            :is-expanded="isSectionExpanded('current', 'communication')"
            :user-total="communicationTotal"
            :spouse-total="spouseCommunicationTotal"
            :household-total="householdCommunicationTotal"
            :is-married="isMarried"
            @toggle="toggleSection('current', 'communication')"
          >
            <ExpenditureGridRow
              v-for="field in communicationFields"
              :key="field.key"
              :label="field.label"
              :value="formData[field.key]"
              :spouse-value="spouseFormData[field.key]"
              :household-value="getHouseholdValue(field.key)"
              :is-married="isMarried"
              indent
            />
          </ExpenditureSection>

          <!-- Personal & Lifestyle -->
          <ExpenditureSection
            title="Personal & Lifestyle"
            :is-expanded="isSectionExpanded('current', 'lifestyle')"
            :user-total="lifestyleTotal"
            :spouse-total="spouseLifestyleTotal"
            :household-total="householdLifestyleTotal"
            :is-married="isMarried"
            @toggle="toggleSection('current', 'lifestyle')"
          >
            <ExpenditureGridRow
              v-for="field in lifestyleFields"
              :key="field.key"
              :label="field.label"
              :value="formData[field.key]"
              :spouse-value="spouseFormData[field.key]"
              :household-value="getHouseholdValue(field.key)"
              :is-married="isMarried"
              indent
            />
          </ExpenditureSection>

          <!-- Children & Dependents -->
          <ExpenditureSection
            title="Children & Dependents"
            :is-expanded="isSectionExpanded('current', 'children')"
            :user-total="childrenTotal"
            :spouse-total="spouseChildrenTotal"
            :household-total="householdChildrenTotal"
            :is-married="isMarried"
            @toggle="toggleSection('current', 'children')"
          >
            <ExpenditureGridRow
              v-for="field in childrenFields"
              :key="field.key"
              :label="field.label"
              :value="formData[field.key]"
              :spouse-value="spouseFormData[field.key]"
              :household-value="getHouseholdValue(field.key)"
              :is-married="isMarried"
              indent
            />
          </ExpenditureSection>

          <!-- Other Expenses -->
          <ExpenditureSection
            title="Other Expenses"
            :is-expanded="isSectionExpanded('current', 'other')"
            :user-total="otherTotal"
            :spouse-total="spouseOtherTotal"
            :household-total="householdOtherTotal"
            :is-married="isMarried"
            @toggle="toggleSection('current', 'other')"
          >
            <ExpenditureGridRow
              v-for="field in otherFields"
              :key="field.key"
              :label="field.label"
              :value="formData[field.key]"
              :spouse-value="spouseFormData[field.key]"
              :household-value="getHouseholdValue(field.key)"
              :is-married="isMarried"
              indent
            />
            <!-- Gift Aid indicator -->
            <template v-if="formData.charitable_donations > 0 && isGiftAid">
              <div class="col-label text-body-sm text-violet-600 py-1 pl-7">Gift Aid claimed</div>
              <div class="col-value text-body-sm text-violet-600 py-1"></div>
              <div v-if="isMarried" class="col-value-mid text-body-sm text-violet-600 py-1"></div>
              <div v-if="isMarried" class="col-total text-body-sm text-violet-600 py-1"></div>
            </template>
          </ExpenditureSection>

          <!-- Manual Expenditure Total -->
          <div class="col-span-full border-t-2 border-horizon-300 mt-4"></div>
          <div class="col-label text-body font-semibold text-horizon-500 py-3">Manual Expenditure Total</div>
          <div class="col-value text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(totalMonthlyExpenditure) }}</div>
          <div v-if="isMarried" class="col-value-mid text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(spouseTotalMonthlyExpenditure) }}</div>
          <div v-if="isMarried" class="col-total text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(householdTotalMonthlyExpenditure) }}</div>
        </div>
      </div>

      <!-- Financial Commitments (Auto-pulled) - View Mode -->
      <div v-if="hasAnyCommitments" class="mt-6 border-t border-light-gray pt-6">
        <div :class="isMarried ? 'expenditure-grid-married' : 'expenditure-grid-single'">
          <!-- Financial Commitments Section Header -->
          <ExpenditureSection
            title="Financial Commitments"
            :is-expanded="isSectionExpanded('current', 'commitments')"
            :user-total="financialCommitments?.totals?.total || 0"
            :spouse-total="spouseFinancialCommitments?.totals?.total || 0"
            :household-total="(financialCommitments?.totals?.total || 0) + (spouseFinancialCommitments?.totals?.total || 0)"
            :is-married="isMarried"
            @toggle="toggleSection('current', 'commitments')"
          >
            <template #badge>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-violet-800">
                Auto-calculated
              </span>
            </template>

            <!-- Commitment Rows - Expandable to show individual items -->
            <template v-if="hasRetirementCommitments || spouseHasRetirementCommitments">
              <ExpenditureExpandableGridRow
                label="Pension Contributions"
                :value="financialCommitments?.totals?.retirement || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.retirement || 0"
                :household-value="(financialCommitments?.totals?.retirement || 0) + (spouseFinancialCommitments?.totals?.retirement || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.retirement || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.retirement || []"
                indent
              />
            </template>

            <template v-if="hasPropertyCommitments || spouseHasPropertyCommitments">
              <ExpenditureExpandableGridRow
                label="Property Expenses"
                :value="financialCommitments?.totals?.properties || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.properties || 0"
                :household-value="(financialCommitments?.totals?.properties || 0) + (spouseFinancialCommitments?.totals?.properties || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.properties || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.properties || []"
                indent
              />
            </template>

            <template v-if="hasInvestmentCommitments || spouseHasInvestmentCommitments">
              <ExpenditureExpandableGridRow
                label="Investment Contributions"
                :value="financialCommitments?.totals?.investments || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.investments || 0"
                :household-value="(financialCommitments?.totals?.investments || 0) + (spouseFinancialCommitments?.totals?.investments || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.investments || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.investments || []"
                indent
              />
            </template>

            <template v-if="hasSavingsCommitments || spouseHasSavingsCommitments">
              <ExpenditureExpandableGridRow
                label="Savings Contributions"
                :value="financialCommitments?.totals?.savings || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.savings || 0"
                :household-value="(financialCommitments?.totals?.savings || 0) + (spouseFinancialCommitments?.totals?.savings || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.savings || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.savings || []"
                indent
              />
            </template>

            <template v-if="hasProtectionCommitments || spouseHasProtectionCommitments">
              <ExpenditureExpandableGridRow
                label="Protection Premiums"
                :value="financialCommitments?.totals?.protection || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.protection || 0"
                :household-value="(financialCommitments?.totals?.protection || 0) + (spouseFinancialCommitments?.totals?.protection || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.protection || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.protection || []"
                indent
              />
            </template>

            <template v-if="hasLiabilityCommitments || spouseHasLiabilityCommitments">
              <ExpenditureExpandableGridRow
                label="Loan Repayments"
                :value="financialCommitments?.totals?.liabilities || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.liabilities || 0"
                :household-value="(financialCommitments?.totals?.liabilities || 0) + (spouseFinancialCommitments?.totals?.liabilities || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.liabilities || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.liabilities || []"
                indent
              />
            </template>
          </ExpenditureSection>
        </div>
      </div>

      <!-- Grand Total -->
      <div class="mt-6 pt-4 border-t-2 border-horizon-300">
        <div :class="isMarried ? 'expenditure-grid-married' : 'expenditure-grid-single'">
          <div class="col-label text-body font-semibold text-horizon-500">Total Monthly Expenditure</div>
          <div class="col-value text-body font-semibold text-horizon-500">{{ formatCurrency(totalMonthlyWithCommitments) }}</div>
          <div v-if="isMarried" class="col-value-mid text-body font-semibold text-horizon-500">{{ formatCurrency(spouseTotalMonthlyWithCommitments) }}</div>
          <div v-if="isMarried" class="col-total text-body font-semibold text-raspberry-500">{{ formatCurrency(householdTotalMonthlyWithCommitments) }}</div>

          <div class="col-label text-body-sm text-neutral-500 mt-2">Annual Equivalent</div>
          <div class="col-value text-body-sm text-horizon-500 mt-2">{{ formatCurrency(totalAnnualWithCommitments) }}</div>
          <div v-if="isMarried" class="col-value-mid text-body-sm text-horizon-500 mt-2">{{ formatCurrency(spouseTotalAnnualWithCommitments) }}</div>
          <div v-if="isMarried" class="col-total text-body-sm text-raspberry-500 mt-2 font-medium">{{ formatCurrency(householdTotalAnnualWithCommitments) }}</div>
        </div>
      </div>
    </div>

    <!-- EDIT MODE -->
    <div v-else>
      <!-- Notes Section (hidden in onboarding — info is in sidebar) -->
      <div v-if="!isOnboarding" class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>Why this matters:</strong> Understanding your expenditure helps us calculate your emergency fund needs, discretionary income, and protection requirements.
        </p>
      </div>

      <div class="bg-spring-50 border border-spring-200 rounded-lg p-4" :class="{ 'mt-4': !isOnboarding }">
        <p class="text-body-sm text-spring-800">
          <strong>Note:</strong> Household expenditure such as Council Tax, utilities, and maintenance are entered in the Properties tab. Car loans/repayments, other loans, credit cards, and hire purchase are entered in the Liabilities section.
        </p>
      </div>

      <!-- Options Cards (inline) -->
      <div class="flex flex-col sm:flex-row gap-4 mt-4">
        <!-- Separate Expenditure Option (Married Users Only) -->
        <div v-if="isMarried" class="bg-white border border-light-gray rounded-lg p-4 flex-1">
          <span class="text-body font-medium text-horizon-500 block mb-3">Spouse Expenditure</span>
          <div class="inline-flex rounded-lg border border-light-gray p-1 bg-white">
            <button
              type="button"
              @click="useSeparateExpenditure = false"
              :class="[
                'px-4 py-2 text-body-sm font-medium rounded-md transition-all duration-200',
                !useSeparateExpenditure
                  ? 'bg-raspberry-500 text-white'
                  : 'text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100'
              ]"
            >
              Joint
            </button>
            <button
              type="button"
              @click="useSeparateExpenditure = true"
              :class="[
                'px-4 py-2 text-body-sm font-medium rounded-md transition-all duration-200',
                useSeparateExpenditure
                  ? 'bg-raspberry-500 text-white'
                  : 'text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100'
              ]"
            >
              Separate
            </button>
          </div>
          <p class="text-body-sm text-neutral-500 mt-2">
            {{ useSeparateExpenditure ? 'Enter expenditure for each spouse separately.' : 'Expenditure is assumed to be split 50/50.' }}
          </p>
        </div>

        <!-- Entry Mode Toggle -->
        <div class="bg-white border border-light-gray rounded-lg p-4 flex-1">
          <div class="inline-flex rounded-full border border-neutral-300 p-0.5 bg-neutral-100">
            <button
              type="button"
              @click="useSimpleEntry = true"
              :class="[
                'px-5 py-2 text-body-sm font-medium rounded-full transition-all duration-200',
                useSimpleEntry
                  ? 'bg-horizon-500 text-white shadow-sm'
                  : 'text-neutral-600 hover:text-horizon-500'
              ]"
            >
              Simple View
            </button>
            <button
              type="button"
              @click="useSimpleEntry = false"
              :class="[
                'px-5 py-2 text-body-sm font-medium rounded-full transition-all duration-200',
                !useSimpleEntry
                  ? 'bg-horizon-500 text-white shadow-sm'
                  : 'text-neutral-600 hover:text-horizon-500'
              ]"
            >
              Detailed View
            </button>
          </div>
        </div>
      </div>

      <!-- Simple Entry Mode -->
      <div v-if="useSimpleEntry" class="card p-6 mt-6">
        <h3 class="text-h5 font-semibold text-horizon-500 mb-4">Total Monthly Expenditure</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div>
            <label for="simple_monthly_expenditure" class="label">
              {{ isMarried && useSeparateExpenditure ? 'Your Monthly Expenditure' : 'Monthly Expenditure' }}
            </label>
            <CurrencyInputField
              id="simple_monthly_expenditure"
              v-model="simpleMonthlyExpenditure"
              placeholder="3000"
              :step="100"
            />
          </div>
          <div v-if="isMarried && useSeparateExpenditure">
            <label for="spouse_simple_monthly_expenditure" class="label">
              {{ spouseName }}'s Monthly Expenditure
            </label>
            <CurrencyInputField
              id="spouse_simple_monthly_expenditure"
              v-model="spouseSimpleMonthlyExpenditure"
              placeholder="3000"
              :step="100"
            />
          </div>
        </div>
      </div>

      <!-- Detailed Entry Mode -->
      <div v-else class="space-y-6 mt-6">
        <!-- Person Tabs (only shown when separate expenditure is enabled for married users) -->
        <div v-if="isMarried && useSeparateExpenditure" class="border-b border-light-gray">
          <nav class="-mb-px flex space-x-8" aria-label="Person tabs">
            <button
              type="button"
              @click="activePersonTab = 'user'"
              :class="[
                activePersonTab === 'user'
                  ? 'border-raspberry-500 text-raspberry-500'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
                'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-body-sm'
              ]"
            >
              {{ userName }}
            </button>
            <button
              type="button"
              @click="activePersonTab = 'spouse'"
              :class="[
                activePersonTab === 'spouse'
                  ? 'border-raspberry-500 text-raspberry-500'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
                'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-body-sm'
              ]"
            >
              {{ spouseName }}
            </button>
          </nav>
        </div>

        <!-- Category Cards -->
        <ExpenditureCategoryCard
          title="Essential Living Expenses (Monthly)"
          :fields="essentialFields"
          v-model="formData"
          v-model:spouse-model-value="spouseFormData"
          :is-married="isMarried"
          :use-separate-expenditure="useSeparateExpenditure"
          :active-person-tab="activePersonTab"
        />

        <ExpenditureCategoryCard
          title="Communication & Technology (Monthly)"
          :fields="communicationFields"
          v-model="formData"
          v-model:spouse-model-value="spouseFormData"
          :is-married="isMarried"
          :use-separate-expenditure="useSeparateExpenditure"
          :active-person-tab="activePersonTab"
          :step="10"
        />

        <ExpenditureCategoryCard
          title="Personal & Lifestyle (Monthly)"
          :fields="lifestyleFields"
          v-model="formData"
          v-model:spouse-model-value="spouseFormData"
          :is-married="isMarried"
          :use-separate-expenditure="useSeparateExpenditure"
          :active-person-tab="activePersonTab"
        />

        <ExpenditureCategoryCard
          title="Children & Dependents (Monthly)"
          :fields="childrenFields"
          v-model="formData"
          v-model:spouse-model-value="spouseFormData"
          :is-married="isMarried"
          :use-separate-expenditure="useSeparateExpenditure"
          :active-person-tab="activePersonTab"
        />

        <ExpenditureCategoryCard
          title="Other Expenses (Monthly)"
          :fields="otherFields"
          v-model="formData"
          v-model:spouse-model-value="spouseFormData"
          :is-married="isMarried"
          :use-separate-expenditure="useSeparateExpenditure"
          :active-person-tab="activePersonTab"
        />

        <!-- Gift Aid toggle - shown when charitable donations > 0 -->
        <div v-if="formData.charitable_donations > 0" class="card p-4 -mt-2 border-violet-200 bg-violet-50">
          <div class="flex items-center gap-3">
            <input
              id="is_gift_aid"
              v-model="isGiftAid"
              type="checkbox"
              class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
            >
            <label for="is_gift_aid" class="text-body-sm text-horizon-500 font-medium">
              I use Gift Aid for my charitable donations
            </label>
          </div>
          <p class="mt-1 ml-7 text-body-sm text-neutral-500">
            Gift Aid lets charities claim 25p for every £1 you donate, and higher-rate taxpayers can claim back the difference
          </p>
        </div>

        <!-- Financial Commitments (Read-Only) - Edit Mode -->
        <div v-if="hasAnyCommitments" class="card p-6 bg-violet-50 border-2 border-violet-200">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h4 class="text-h5 font-semibold text-horizon-500">
                Financial Commitments (Automated)
              </h4>
              <p class="text-body-sm text-neutral-500 mt-1">
                These monthly commitments are automatically pulled from your entered data across all modules
              </p>
            </div>
            <span v-if="loadingCommitments" class="text-body-sm text-neutral-500">Loading...</span>
          </div>

          <!-- Retirement -->
          <div v-if="hasRetirementCommitments" class="mb-4">
            <h5 class="text-body font-medium text-horizon-500 mb-2">Pension Contributions</h5>
            <div class="space-y-2">
              <div
                v-for="pension in financialCommitments.commitments.retirement"
                :key="pension.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-horizon-500">{{ pension.name }}</p>
                  <p v-if="pension.is_joint" class="text-body-sm text-raspberry-500">50% of joint contribution</p>
                </div>
                <p class="text-body font-medium text-horizon-500">{{ formatCurrency(pension.monthly_amount) }}/month</p>
              </div>
            </div>
          </div>

          <!-- Property -->
          <div v-if="hasPropertyCommitments" class="mb-4">
            <h5 class="text-body font-medium text-horizon-500 mb-2">Property Expenses</h5>
            <div class="space-y-2">
              <div
                v-for="property in financialCommitments.commitments.properties"
                :key="property.id"
                class="p-3 bg-white rounded-lg"
              >
                <div class="flex justify-between items-center mb-2">
                  <p class="text-body font-medium text-horizon-500">{{ property.name }}</p>
                  <p class="text-body font-medium text-horizon-500">{{ formatCurrency(property.monthly_amount) }}/month</p>
                </div>
                <p v-if="property.is_joint" class="text-body-sm text-raspberry-500">
                  Your {{ property.ownership_percentage || 50 }}% share
                </p>
              </div>
            </div>
          </div>

          <!-- Investment -->
          <div v-if="hasInvestmentCommitments" class="mb-4">
            <h5 class="text-body font-medium text-horizon-500 mb-2">Investment Contributions</h5>
            <div class="space-y-2">
              <div
                v-for="investment in financialCommitments.commitments.investments"
                :key="investment.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-horizon-500">{{ investment.name }}</p>
                  <p v-if="investment.is_joint" class="text-body-sm text-raspberry-500">Your {{ investment.ownership_percentage || 50 }}% share</p>
                </div>
                <div class="text-right">
                  <p v-if="investment.monthly_amount > 0" class="text-body font-medium text-horizon-500">{{ formatCurrency(investment.monthly_amount) }}/month</p>
                  <p v-if="investment.lump_sum_amount > 0" class="text-body-sm text-neutral-500">{{ formatCurrency(investment.lump_sum_amount) }} lump sum</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Savings -->
          <div v-if="hasSavingsCommitments" class="mb-4">
            <h5 class="text-body font-medium text-horizon-500 mb-2">Savings Contributions</h5>
            <div class="space-y-2">
              <div
                v-for="saving in financialCommitments.commitments.savings"
                :key="saving.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-horizon-500">{{ saving.name }}</p>
                  <p v-if="saving.is_joint" class="text-body-sm text-raspberry-500">Your {{ saving.ownership_percentage || 50 }}% share</p>
                </div>
                <p class="text-body font-medium text-horizon-500">{{ formatCurrency(saving.monthly_amount) }}/month</p>
              </div>
            </div>
          </div>

          <!-- Protection -->
          <div v-if="hasProtectionCommitments" class="mb-4">
            <h5 class="text-body font-medium text-horizon-500 mb-2">Protection Premiums</h5>
            <div class="space-y-2">
              <div
                v-for="policy in financialCommitments.commitments.protection"
                :key="policy.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <p class="text-body text-horizon-500">{{ policy.name }}</p>
                <p class="text-body font-medium text-horizon-500">{{ formatCurrency(policy.monthly_amount) }}/month</p>
              </div>
            </div>
          </div>

          <!-- Liabilities -->
          <div v-if="hasLiabilityCommitments" class="mb-4">
            <h5 class="text-body font-medium text-horizon-500 mb-2">Loan Repayments</h5>
            <div class="space-y-2">
              <div
                v-for="liability in financialCommitments.commitments.liabilities"
                :key="liability.id"
                class="flex justify-between items-center p-3 bg-white rounded-lg"
              >
                <div>
                  <p class="text-body text-horizon-500">{{ liability.name }}</p>
                  <p class="text-body-sm text-neutral-500 capitalize">{{ liability.type?.replace('_', ' ') }}</p>
                  <p v-if="liability.is_joint" class="text-body-sm text-raspberry-500">50% of joint liability</p>
                </div>
                <p class="text-body font-medium text-horizon-500">{{ formatCurrency(liability.monthly_amount) }}/month</p>
              </div>
            </div>
          </div>

          <!-- Total Commitments -->
          <div class="pt-4 border-t border-violet-300">
            <div class="flex justify-between items-center">
              <p class="text-body font-semibold text-horizon-500">Total Monthly Commitments</p>
              <p class="text-h4 font-display text-horizon-500">{{ formatCurrency(financialCommitments?.totals?.total || 0) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="bg-eggshell-500 rounded-lg p-6">
        <h4 class="text-body font-medium text-horizon-500 mb-4">Summary</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <p class="text-body-sm text-neutral-500">Manual Monthly Expenditure</p>
            <p class="text-h4 font-display text-horizon-500">{{ formatCurrency(householdTotalMonthlyExpenditure) }}</p>
          </div>
          <div v-if="hasAnyCommitments">
            <p class="text-body-sm text-neutral-500">Financial Commitments</p>
            <p class="text-h4 font-display text-horizon-500">{{ formatCurrency((financialCommitments?.totals?.total || 0) + (spouseFinancialCommitments?.totals?.total || 0)) }}</p>
          </div>
          <div>
            <p class="text-body-sm text-neutral-500">Total Monthly</p>
            <p class="text-h3 font-display text-raspberry-500">{{ formatCurrency(householdTotalMonthlyWithCommitments) }}</p>
            <p class="text-body-sm text-neutral-500">{{ formatCurrency(householdTotalAnnualWithCommitments) }}/year</p>
          </div>
        </div>
      </div>

      <!-- Action Buttons (hidden during onboarding - continue button handles save) -->
      <div v-if="!isOnboarding" class="flex justify-end space-x-4 pt-4 border-t border-light-gray">
        <button
          type="button"
          @click="handleCancel"
          class="btn-secondary"
        >
          {{ cancelText }}
        </button>
        <button
          type="button"
          @click="handleSave"
          class="btn-primary"
        >
          {{ saveText }}
        </button>
      </div>
    </div>
  </div>

    <!-- RETIRED BUDGET TAB -->
    <div v-if="activeBudgetTab === 'retired'" class="space-y-6">
      <!-- Info Banner -->
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>Retired Budget</strong> shows your estimated expenses after retirement. We've automatically adjusted some costs based on your retirement age{{ retirementInfo.userRetirementAge ? ` (${retirementInfo.userRetirementAge})` : '' }}{{ isMarried && retirementInfo.spouseRetirementAge ? ` and your spouse's retirement age (${retirementInfo.spouseRetirementAge})` : '' }}.
        </p>
      </div>

      <!-- VIEW MODE -->
      <div v-if="!isEditingRetired">
        <!-- Header with Edit Button -->
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Retired Monthly Expenditure</h3>
            <p class="mt-1 text-body-sm text-neutral-500">
              Based on retirement at age {{ retirementInfo.userRetirementAge || 65 }}{{ isMarried ? `, spouse at ${retirementInfo.spouseRetirementAge || 65}` : '' }}
            </p>
          </div>
          <button type="button" @click="isEditingRetired = true" class="btn-secondary">
            Edit
          </button>
        </div>

        <!-- Simple Entry Mode: Show simple total for retirement -->
        <div v-if="useSimpleEntry" class="space-y-4 mb-6">
          <div class="bg-eggshell-500 border border-light-gray rounded-lg p-4">
            <p class="text-body-sm text-neutral-500 mb-3">Your current monthly expenditure entered as a simple total. For more detailed retirement planning, switch to detailed breakdown mode.</p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-3">
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Current Monthly Expenditure:</span>
                  <span class="text-body-sm text-horizon-500 font-medium">{{ formatCurrency(simpleMonthlyExpenditure) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Estimated Retired Monthly:</span>
                  <span class="text-body-sm text-horizon-500 font-bold">{{ formatCurrency(simpleMonthlyExpenditure * 0.85) }}</span>
                </div>
              </div>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Current Annual Expenditure:</span>
                  <span class="text-body-sm text-horizon-500 font-medium">{{ formatCurrency(simpleMonthlyExpenditure * 12) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Estimated Retired Annual:</span>
                  <span class="text-body-sm text-horizon-500 font-bold">{{ formatCurrency(simpleMonthlyExpenditure * 12 * 0.85) }}</span>
                </div>
              </div>
            </div>
            <p class="text-xs text-neutral-500 mt-3">Estimated at 85% of current spending — a common rule of thumb for retirement. Switch to detailed mode for personalised adjustments.</p>
          </div>
        </div>

        <!-- Auto-Adjustments Summary (detailed mode only) -->
        <div v-if="!useSimpleEntry && retiredAutoAdjustments.length > 0" class="bg-spring-50 border border-spring-200 rounded-lg p-4 mb-6">
          <h4 class="text-body font-medium text-spring-900 mb-2">Automatic Adjustments Applied</h4>
          <ul class="space-y-1">
            <li v-for="(adj, idx) in retiredAutoAdjustments" :key="idx" class="text-body-sm text-spring-800 flex items-start gap-2">
              <svg class="h-4 w-4 text-spring-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>{{ adj.description }}</span>
            </li>
          </ul>
        </div>

        <!-- Budget Grid (detailed mode only) -->
        <div v-if="!useSimpleEntry" :class="isMarried ? 'retired-grid-married' : 'retired-grid-single'">
          <!-- Column Headers -->
          <div class="col-label font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">Category</div>
          <div class="col-header font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">{{ userName }}</div>
          <div v-if="isMarried" class="col-header font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">{{ spouseName }}</div>
          <div v-if="isMarried" class="col-header font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">Household</div>

          <!-- Retired Budget Sections -->
          <template v-for="(sectionConfig, sectionKey) in retiredSectionConfigs" :key="sectionKey">
            <ExpenditureSection
              :title="sectionConfig.title"
              :is-expanded="isSectionExpanded('retired', sectionKey)"
              :user-total="getRetiredUserSectionTotal(sectionKey)"
              :spouse-total="getRetiredSpouseSectionTotal(sectionKey)"
              :household-total="getRetiredSectionTotal(sectionKey)"
              :is-married="isMarried"
              @toggle="toggleSection('retired', sectionKey)"
            >
              <template v-for="field in retiredBudgetFields[sectionKey]" :key="field.key">
                <div class="col-label text-body-sm text-neutral-500 py-1 pl-7">
                  {{ field.label }}
                  <span v-if="retiredBudgetData[field.key]?.adjusted && !retiredBudgetData[field.key]?.userModified" class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-raspberry-100 text-raspberry-700">Auto</span>
                  <span v-if="retiredBudgetData[field.key]?.userModified" class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-spring-100 text-spring-600">Custom</span>
                </div>
                <div class="col-value text-body-sm text-horizon-500 py-1 font-medium">
                  {{ formatCurrency(getRetiredUserValue(field.key)) }}<span v-if="getRetiredChange(field.key, false) !== 0" :class="getRetiredChange(field.key, false) < 0 ? 'text-spring-600' : 'text-raspberry-600'" class="text-xs ml-1">({{ getRetiredChange(field.key, false) < 0 ? '' : '+' }}{{ formatCurrency(getRetiredChange(field.key, false)) }})</span>
                </div>
                <div v-if="isMarried" class="col-value text-body-sm text-horizon-500 py-1 font-medium">
                  {{ formatCurrency(getRetiredSpouseValue(field.key)) }}<span v-if="getRetiredChange(field.key, true) !== 0" :class="getRetiredChange(field.key, true) < 0 ? 'text-spring-600' : 'text-raspberry-600'" class="text-xs ml-1">({{ getRetiredChange(field.key, true) < 0 ? '' : '+' }}{{ formatCurrency(getRetiredChange(field.key, true)) }})</span>
                </div>
                <div v-if="isMarried" class="col-total text-body-sm text-horizon-500 py-1 font-medium">
                  {{ formatCurrency(getRetiredUserValue(field.key) + getRetiredSpouseValue(field.key)) }}
                </div>
              </template>
            </ExpenditureSection>
          </template>

          <!-- Manual Expenditure Total -->
          <div class="col-span-full border-t-2 border-horizon-300 mt-4"></div>
          <div class="col-label text-body font-semibold text-horizon-500 py-3">Manual Expenditure Total</div>
          <div class="col-value text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(retiredManualExpenditureTotal) }}</div>
          <div v-if="isMarried" class="col-value text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(retiredSpouseManualExpenditureTotal) }}</div>
          <div v-if="isMarried" class="col-total text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(retiredHouseholdManualExpenditureTotal) }}</div>

          <!-- Financial Commitments -->
          <ExpenditureSection
            title="Financial Commitments"
            :is-expanded="isSectionExpanded('retired', 'commitments')"
            :user-total="(financialCommitments?.totals?.protection || 0) + (financialCommitments?.totals?.investments || 0)"
            :spouse-total="(spouseFinancialCommitments?.totals?.protection || 0) + (spouseFinancialCommitments?.totals?.investments || 0)"
            :household-total="(financialCommitments?.totals?.protection || 0) + (financialCommitments?.totals?.investments || 0) + (spouseFinancialCommitments?.totals?.protection || 0) + (spouseFinancialCommitments?.totals?.investments || 0)"
            :is-married="isMarried"
            @toggle="toggleSection('retired', 'commitments')"
          >
            <template #badge>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-spring-100 text-spring-800">
                Retirement adjusted
              </span>
            </template>

            <!-- Pension Contributions - removed in retirement -->
            <template v-if="hasRetirementCommitments || spouseHasRetirementCommitments">
              <div class="col-label text-body-sm text-neutral-500 py-1 pl-7 line-through">Pension Contributions</div>
              <div class="col-value text-body-sm text-horizon-400 py-1">
                {{ formatCurrency(0) }}<span class="text-spring-600 text-xs ml-1">(-{{ formatCurrency(financialCommitments?.totals?.retirement || 0) }})</span>
              </div>
              <div v-if="isMarried" class="col-value-mid text-body-sm text-horizon-400 py-1">
                {{ formatCurrency(0) }}<span class="text-spring-600 text-xs ml-1">(-{{ formatCurrency(spouseFinancialCommitments?.totals?.retirement || 0) }})</span>
              </div>
              <div v-if="isMarried" class="col-total text-body-sm text-horizon-400 py-1">{{ formatCurrency(0) }}</div>
            </template>

            <!-- Property Expenses - removed in retirement (mortgage paid off) -->
            <template v-if="hasPropertyCommitments || spouseHasPropertyCommitments">
              <div class="col-label text-body-sm text-neutral-500 py-1 pl-7 line-through">Property Expenses</div>
              <div class="col-value text-body-sm text-horizon-400 py-1">
                {{ formatCurrency(0) }}<span class="text-spring-600 text-xs ml-1">(-{{ formatCurrency(financialCommitments?.totals?.properties || 0) }})</span>
              </div>
              <div v-if="isMarried" class="col-value-mid text-body-sm text-horizon-400 py-1">
                {{ formatCurrency(0) }}<span class="text-spring-600 text-xs ml-1">(-{{ formatCurrency(spouseFinancialCommitments?.totals?.properties || 0) }})</span>
              </div>
              <div v-if="isMarried" class="col-total text-body-sm text-horizon-400 py-1">{{ formatCurrency(0) }}</div>
            </template>

            <!-- Investment Contributions - kept in retirement -->
            <template v-if="hasInvestmentCommitments || spouseHasInvestmentCommitments">
              <ExpenditureExpandableGridRow
                label="Investment Contributions"
                :value="financialCommitments?.totals?.investments || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.investments || 0"
                :household-value="(financialCommitments?.totals?.investments || 0) + (spouseFinancialCommitments?.totals?.investments || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.investments || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.investments || []"
                indent
              />
            </template>

            <!-- Savings Contributions - kept in retirement -->
            <template v-if="hasSavingsCommitments || spouseHasSavingsCommitments">
              <ExpenditureExpandableGridRow
                label="Savings Contributions"
                :value="financialCommitments?.totals?.savings || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.savings || 0"
                :household-value="(financialCommitments?.totals?.savings || 0) + (spouseFinancialCommitments?.totals?.savings || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.savings || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.savings || []"
                indent
              />
            </template>

            <!-- Protection Premiums - kept in retirement -->
            <template v-if="hasProtectionCommitments || spouseHasProtectionCommitments">
              <ExpenditureExpandableGridRow
                label="Protection Premiums"
                :value="financialCommitments?.totals?.protection || 0"
                :spouse-value="spouseFinancialCommitments?.totals?.protection || 0"
                :household-value="(financialCommitments?.totals?.protection || 0) + (spouseFinancialCommitments?.totals?.protection || 0)"
                :is-married="isMarried"
                :items="financialCommitments?.commitments?.protection || []"
                :spouse-items="spouseFinancialCommitments?.commitments?.protection || []"
                indent
              />
            </template>

            <!-- Loan Repayments - removed in retirement -->
            <template v-if="hasLiabilityCommitments || spouseHasLiabilityCommitments">
              <div class="col-label text-body-sm text-neutral-500 py-1 pl-7 line-through">Loan Repayments</div>
              <div class="col-value text-body-sm text-horizon-400 py-1">
                {{ formatCurrency(0) }}<span class="text-spring-600 text-xs ml-1">(-{{ formatCurrency(financialCommitments?.totals?.liabilities || 0) }})</span>
              </div>
              <div v-if="isMarried" class="col-value-mid text-body-sm text-horizon-400 py-1">
                {{ formatCurrency(0) }}<span class="text-spring-600 text-xs ml-1">(-{{ formatCurrency(spouseFinancialCommitments?.totals?.liabilities || 0) }})</span>
              </div>
              <div v-if="isMarried" class="col-total text-body-sm text-horizon-400 py-1">{{ formatCurrency(0) }}</div>
            </template>
          </ExpenditureSection>

          <!-- Total -->
          <div class="col-span-full border-t-2 border-horizon-300 mt-4"></div>
          <div class="col-label text-body font-semibold text-horizon-500 py-3">Total Monthly Expenditure</div>
          <div class="col-value text-body font-semibold text-raspberry-500 py-3">{{ formatCurrency(retiredTotalMonthly) }}</div>
          <div v-if="isMarried" class="col-value text-body font-semibold text-raspberry-500 py-3">{{ formatCurrency(retiredSpouseTotalMonthly) }}</div>
          <div v-if="isMarried" class="col-total text-body font-semibold text-raspberry-500 py-3">{{ formatCurrency(retiredHouseholdTotalMonthly) }}</div>

          <div class="col-label text-body-sm text-neutral-500">Annual Equivalent</div>
          <div class="col-value text-body-sm text-raspberry-500 font-medium">{{ formatCurrency(retiredTotalMonthly * 12) }}</div>
          <div v-if="isMarried" class="col-value text-body-sm text-raspberry-500 font-medium">{{ formatCurrency(retiredSpouseTotalMonthly * 12) }}</div>
          <div v-if="isMarried" class="col-total text-body-sm text-raspberry-500 font-medium">{{ formatCurrency(retiredHouseholdTotalMonthly * 12) }}</div>

          <!-- Monthly Savings Row -->
          <div class="col-label text-body font-semibold text-spring-700 py-3">Monthly Savings in Retirement</div>
          <template v-if="isMarried">
            <div class="col-value py-3"></div>
            <div class="col-value py-3"></div>
            <div class="col-total text-body font-semibold text-spring-600 py-3">{{ formatCurrency(householdTotalMonthlyWithCommitments - retiredHouseholdTotalMonthly) }}</div>
          </template>
          <div v-else class="col-value text-body font-semibold text-spring-600 py-3">{{ formatCurrency(householdTotalMonthlyWithCommitments - retiredHouseholdTotalMonthly) }}</div>
          <div class="col-span-full text-body-sm text-spring-600 -mt-2 mb-2">This is how much less you'll need per month after retirement</div>
        </div>
      </div>

      <!-- EDIT MODE -->
      <div v-else class="space-y-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Edit Retired Budget</h3>
            <p class="mt-1 text-body-sm text-neutral-500">Override auto-calculated values with your own estimates</p>
          </div>
        </div>

        <!-- Retirement Age Info -->
        <div class="bg-eggshell-500 border border-light-gray rounded-lg p-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Your Retirement Age</label>
              <p class="text-body font-medium text-horizon-500">{{ retirementInfo.userRetirementAge || 'Not set' }}</p>
            </div>
            <div v-if="isMarried">
              <label class="label">Spouse's Retirement Age</label>
              <p class="text-body font-medium text-horizon-500">{{ retirementInfo.spouseRetirementAge || 'Not set' }}</p>
            </div>
          </div>
        </div>

        <!-- Editable Fields -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-horizon-500 mb-4">Monthly Expenses in Retirement</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <template v-for="field in allRetiredFields" :key="field.key">
              <div>
                <label :for="'retired_' + field.key" class="label">
                  {{ field.label }}
                  <span v-if="retiredBudgetData[field.key]?.adjusted && !retiredBudgetData[field.key]?.userModified" class="text-raspberry-500 text-xs ml-1">(Auto-adjusted)</span>
                </label>
                <div class="flex gap-2 items-center">
                  <CurrencyInputField
                    :id="'retired_' + field.key"
                    v-model="retiredBudgetData[field.key].value"
                    @update:model-value="markRetiredFieldModified(field.key)"
                    class="flex-1"
                  />
                  <button
                    v-if="retiredBudgetData[field.key]?.userModified"
                    type="button"
                    @click="resetRetiredField(field.key)"
                    class="text-horizon-400 hover:text-neutral-500"
                    title="Reset to auto-calculated value"
                  >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                  </button>
                </div>
                <p v-if="field.hint" class="mt-1 text-body-sm text-neutral-500">{{ field.hint }}</p>
              </div>
            </template>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 pt-4 border-t border-light-gray">
          <button type="button" @click="cancelRetiredEdit" class="btn-secondary">Cancel</button>
          <button type="button" @click="saveRetiredBudget" class="btn-primary">Save Retired Budget</button>
        </div>
      </div>
    </div>

    <!-- WIDOWED BUDGET TAB -->
    <div v-if="activeBudgetTab === 'widowed' && isMarried" class="space-y-6">
      <!-- Info Banner -->
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>Widowed Budget</strong> estimates your expenses if you were to lose your spouse. This is essential for calculating adequate life insurance cover and ensuring financial security for the surviving spouse.
        </p>
      </div>

      <!-- VIEW MODE -->
      <div v-if="!isEditingWidowed">
        <!-- Header with Edit Button -->
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Widowed Monthly Expenditure</h3>
            <p class="mt-1 text-body-sm text-neutral-500">Estimated expenses for {{ userName }} as single-person household</p>
          </div>
          <button type="button" @click="isEditingWidowed = true" class="btn-secondary">
            Edit
          </button>
        </div>

        <!-- Simple Entry Mode: Show simple total for widowed -->
        <div v-if="useSimpleEntry" class="space-y-4 mb-6">
          <div class="bg-eggshell-500 border border-light-gray rounded-lg p-4">
            <p class="text-body-sm text-neutral-500 mb-3">Your current monthly expenditure entered as a simple total. For more detailed widowed budget planning, switch to detailed breakdown mode.</p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-3">
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Current Household Monthly:</span>
                  <span class="text-body-sm text-horizon-500 font-medium">{{ formatCurrency(simpleMonthlyExpenditure) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Estimated Widowed Monthly:</span>
                  <span class="text-body-sm text-horizon-500 font-bold">{{ formatCurrency(simpleMonthlyExpenditure * 0.7) }}</span>
                </div>
              </div>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Current Household Annual:</span>
                  <span class="text-body-sm text-horizon-500 font-medium">{{ formatCurrency(simpleMonthlyExpenditure * 12) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-body-sm text-neutral-500">Estimated Widowed Annual:</span>
                  <span class="text-body-sm text-horizon-500 font-bold">{{ formatCurrency(simpleMonthlyExpenditure * 12 * 0.7) }}</span>
                </div>
              </div>
            </div>
            <p class="text-xs text-neutral-500 mt-3">Estimated at 70% of current household spending for a single-person household. Switch to detailed mode for personalised adjustments.</p>
          </div>
        </div>

        <!-- Auto-Adjustments Summary (detailed mode only) -->
        <div v-if="!useSimpleEntry && widowedAutoAdjustments.length > 0" class="bg-spring-50 border border-spring-200 rounded-lg p-4 mb-6">
          <h4 class="text-body font-medium text-spring-900 mb-2">Automatic Adjustments Applied</h4>
          <ul class="space-y-1">
            <li v-for="(adj, idx) in widowedAutoAdjustments" :key="idx" class="text-body-sm text-spring-800 flex items-start gap-2">
              <svg class="h-4 w-4 text-spring-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>{{ adj.description }}</span>
            </li>
          </ul>
        </div>

        <!-- Budget Grid - Single column for widowed (detailed mode only) -->
        <div v-if="!useSimpleEntry" class="widowed-grid-single">
          <!-- Column Headers -->
          <div class="col-label font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">Category</div>
          <div class="col-header font-semibold text-body-sm text-neutral-500 pb-2 border-b border-light-gray">{{ userName }}</div>

          <!-- Widowed Budget Sections -->
          <template v-for="(sectionConfig, sectionKey) in widowedSectionConfigs" :key="sectionKey">
            <div class="col-label pt-4 pb-2 cursor-pointer select-none" @click="toggleSection('widowed', sectionKey)">
              <div class="flex items-center gap-2">
                <svg :class="['h-5 w-5 text-horizon-400 transition-transform', isSectionExpanded('widowed', sectionKey) ? 'rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="text-body-base font-semibold text-horizon-500">{{ sectionConfig.title }}</span>
              </div>
            </div>
            <div class="col-value pt-4 pb-2 text-body-sm text-horizon-500 font-semibold">{{ formatCurrency(getWidowedSectionTotal(sectionKey)) }}</div>
            <template v-if="isSectionExpanded('widowed', sectionKey)">
              <template v-for="field in widowedBudgetFields[sectionKey]" :key="field.key">
                <div class="col-label text-body-sm text-neutral-500 py-1 pl-7">
                  {{ field.label }}
                  <span v-if="widowedBudgetData[field.key]?.adjusted && !widowedBudgetData[field.key]?.userModified" class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-raspberry-100 text-raspberry-700">Auto</span>
                  <span v-if="widowedBudgetData[field.key]?.userModified" class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-xs font-medium bg-spring-100 text-spring-600">Custom</span>
                </div>
                <div class="col-value text-body-sm text-horizon-500 py-1 font-medium">
                  {{ formatCurrency(getWidowedValue(field.key)) }}<span v-if="getWidowedChange(field.key) !== 0" :class="getWidowedChange(field.key) < 0 ? 'text-spring-600' : 'text-raspberry-600'" class="text-xs ml-1">({{ getWidowedChange(field.key) < 0 ? '' : '+' }}{{ formatCurrency(getWidowedChange(field.key)) }})</span>
                </div>
              </template>
            </template>
          </template>

          <!-- Manual Expenditure Total -->
          <div class="col-span-full border-t-2 border-horizon-300 mt-4"></div>
          <div class="col-label text-body font-semibold text-horizon-500 py-3">Manual Expenditure Total</div>
          <div class="col-value text-body text-horizon-500 py-3 font-semibold">{{ formatCurrency(widowedManualExpenditureTotal) }}</div>

          <!-- Financial Commitments -->
          <ExpenditureSection
            title="Financial Commitments"
            :is-expanded="isSectionExpanded('widowed', 'commitments')"
            :user-total="widowedCommitments.propertyAmount + widowedCommitments.protectionAmount + widowedCommitments.investmentsAmount + widowedCommitments.savingsAmount + widowedCommitments.loansAmount"
            :spouse-total="0"
            :household-total="widowedCommitments.propertyAmount + widowedCommitments.protectionAmount + widowedCommitments.investmentsAmount + widowedCommitments.savingsAmount + widowedCommitments.loansAmount"
            :is-married="false"
            @toggle="toggleSection('widowed', 'commitments')"
          >
            <template #badge>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-savannah-100 text-neutral-500">
                Single-person household
              </span>
            </template>

            <!-- Property Expenses - combined household (survivor responsible for all) -->
            <template v-if="hasPropertyCommitments || spouseHasPropertyCommitments">
              <ExpenditureExpandableGridRow
                label="Property Expenses"
                :value="widowedCommitments.propertyAmount"
                :spouse-value="0"
                :household-value="widowedCommitments.propertyAmount"
                :is-married="false"
                :items="widowedCommitments.propertyItems"
                :spouse-items="[]"
                indent
              />
            </template>

            <!-- Investment Contributions - combined household -->
            <template v-if="hasInvestmentCommitments || spouseHasInvestmentCommitments">
              <ExpenditureExpandableGridRow
                label="Investment Contributions"
                :value="widowedCommitments.investmentsAmount"
                :spouse-value="0"
                :household-value="widowedCommitments.investmentsAmount"
                :is-married="false"
                :items="widowedCommitments.investmentItems"
                :spouse-items="[]"
                indent
              />
            </template>

            <!-- Savings Contributions - combined household -->
            <template v-if="hasSavingsCommitments || spouseHasSavingsCommitments">
              <ExpenditureExpandableGridRow
                label="Savings Contributions"
                :value="widowedCommitments.savingsAmount"
                :spouse-value="0"
                :household-value="widowedCommitments.savingsAmount"
                :is-married="false"
                :items="widowedCommitments.savingsItems"
                :spouse-items="[]"
                indent
              />
            </template>

            <!-- Protection Premiums - combined household -->
            <template v-if="hasProtectionCommitments || spouseHasProtectionCommitments">
              <ExpenditureExpandableGridRow
                label="Protection Premiums"
                :value="widowedCommitments.protectionAmount"
                :spouse-value="0"
                :household-value="widowedCommitments.protectionAmount"
                :is-married="false"
                :items="widowedCommitments.protectionItems"
                :spouse-items="[]"
                indent
              />
            </template>

            <!-- Loan Repayments - combined household -->
            <template v-if="hasLiabilityCommitments || spouseHasLiabilityCommitments">
              <ExpenditureExpandableGridRow
                label="Loan Repayments"
                :value="widowedCommitments.loansAmount"
                :spouse-value="0"
                :household-value="widowedCommitments.loansAmount"
                :is-married="false"
                :items="widowedCommitments.liabilityItems"
                :spouse-items="[]"
                indent
              />
            </template>
          </ExpenditureSection>

          <!-- Total -->
          <div class="col-span-full border-t-2 border-horizon-300 mt-4"></div>
          <div class="col-label text-body font-semibold text-horizon-500 py-3">Total Monthly Expenditure</div>
          <div class="col-value text-body font-semibold text-raspberry-500 py-3">{{ formatCurrency(widowedTotalMonthly) }}</div>

          <div class="col-label text-body-sm text-neutral-500">Annual Equivalent</div>
          <div class="col-value text-body-sm text-raspberry-500 font-medium">{{ formatCurrency(widowedTotalMonthly * 12) }}</div>

          <!-- Monthly Reduction Row -->
          <div class="col-label text-body font-semibold text-spring-700 py-3">Monthly Reduction from Current</div>
          <div class="col-value text-body font-semibold text-spring-600 py-3">{{ formatCurrency(householdTotalMonthlyWithCommitments - widowedTotalMonthly) }}</div>
          <div class="col-span-full text-body-sm text-spring-600 -mt-2 mb-2">This is how much less {{ userName }} would need per month as a single-person household</div>
        </div>
      </div>

      <!-- EDIT MODE -->
      <div v-else class="space-y-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="text-h4 font-semibold text-horizon-500">Edit Widowed Budget</h3>
            <p class="mt-1 text-body-sm text-neutral-500">Override auto-calculated values with your own estimates</p>
          </div>
        </div>

        <!-- Editable Fields -->
        <div class="card p-6">
          <h4 class="text-h5 font-semibold text-horizon-500 mb-4">Monthly Expenses if Widowed</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <template v-for="field in allWidowedFields" :key="field.key">
              <div>
                <label :for="'widowed_' + field.key" class="label">
                  {{ field.label }}
                  <span v-if="widowedBudgetData[field.key]?.adjusted && !widowedBudgetData[field.key]?.userModified" class="text-raspberry-500 text-xs ml-1">(Auto-adjusted)</span>
                </label>
                <div class="flex gap-2 items-center">
                  <CurrencyInputField
                    :id="'widowed_' + field.key"
                    v-model="widowedBudgetData[field.key].value"
                    @update:model-value="markWidowedFieldModified(field.key)"
                    class="flex-1"
                  />
                  <button
                    v-if="widowedBudgetData[field.key]?.userModified"
                    type="button"
                    @click="resetWidowedField(field.key)"
                    class="text-horizon-400 hover:text-neutral-500"
                    title="Reset to auto-calculated value"
                  >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                  </button>
                </div>
                <p v-if="field.hint" class="mt-1 text-body-sm text-neutral-500">{{ field.hint }}</p>
              </div>
            </template>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 pt-4 border-t border-light-gray">
          <button type="button" @click="cancelWidowedEdit" class="btn-secondary">Cancel</button>
          <button type="button" @click="saveWidowedBudget" class="btn-primary">Save Widowed Budget</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import api from '@/services/api';
import CurrencyInputField from '@/components/Shared/CurrencyInputField.vue';
import ExpenditureSection from './ExpenditureSection.vue';
import ExpenditureGridRow from './ExpenditureGridRow.vue';
import ExpenditureExpandableGridRow from './ExpenditureExpandableGridRow.vue';
import ExpenditureCategoryCard from './ExpenditureCategoryCard.vue';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'ExpenditureForm',

  components: {
    CurrencyInputField,
    ExpenditureSection,
    ExpenditureGridRow,
    ExpenditureExpandableGridRow,
    ExpenditureCategoryCard,
  },

  props: {
    initialData: {
      type: Object,
      default: () => ({}),
    },
    spouseData: {
      type: Object,
      default: () => ({}),
    },
    spouseName: {
      type: String,
      default: 'Spouse',
    },
    isMarried: {
      type: Boolean,
      default: false,
    },
    alwaysShowTabs: {
      type: Boolean,
      default: false,
    },
    showCancel: {
      type: Boolean,
      default: true,
    },
    cancelText: {
      type: String,
      default: 'Cancel',
    },
    saveText: {
      type: String,
      default: 'Save Changes',
    },
    startInEditMode: {
      type: Boolean,
      default: false,
    },
    showBudgetTabs: {
      type: Boolean,
      default: true,
    },
    isOnboarding: {
      type: Boolean,
      default: false,
    },
    context: {
      type: String,
      default: 'standalone',
      validator: (value) => ['standalone', 'onboarding'].includes(value),
    },
  },

  emits: ['save', 'cancel'],

  setup(props, { emit }) {
    const store = useStore();
    const activeBudgetTab = ref('current');
    const isEditing = ref(props.startInEditMode);
    const useSimpleEntry = ref(props.isOnboarding ? true : false);
    const useSeparateExpenditure = ref(false);
    const activePersonTab = ref('user');
    const simpleMonthlyExpenditure = ref(0);
    const spouseSimpleMonthlyExpenditure = ref(0);
    const isGiftAid = ref(false);
    const financialCommitments = ref(null);
    const spouseFinancialCommitments = ref(null);
    const loadingCommitments = ref(false);

    // Collapsible sections state
    const expandedSections = ref({
      current: { essential: false, communication: false, lifestyle: false, children: false, other: false, commitments: false },
      retired: { essential: false, communication: false, lifestyle: false, children: false, other: false, commitments: false },
      widowed: { essential: false, communication: false, lifestyle: false, children: false, other: false, commitments: false },
    });

    const toggleSection = (tab, section) => {
      expandedSections.value[tab][section] = !expandedSections.value[tab][section];
    };

    const isSectionExpanded = (tab, section) => {
      return expandedSections.value[tab]?.[section] ?? false;
    };

    const user = computed(() => store.getters['auth/currentUser']);
    const userName = computed(() => user.value?.name?.split(' ')[0] || 'You');

    // Property ownership detection
    const properties = ref([]);
    const hasMainResidence = computed(() => properties.value.some(p => p.property_type === 'main_residence'));
    const hasOnlyBuyToLet = computed(() =>
      properties.value.length > 0 &&
      !hasMainResidence.value &&
      properties.value.some(p => p.property_type === 'buy_to_let')
    );

    // Field definitions - full list used for data init/save
    const allEssentialFields = [
      { key: 'rent', label: 'Rent', placeholder: '0', hint: 'Monthly rent if not a homeowner' },
      { key: 'utilities', label: 'Utilities', placeholder: '150', hint: 'Gas, electricity, water, council tax' },
      { key: 'food_groceries', label: 'Food & Groceries', placeholder: '400' },
      { key: 'transport_fuel', label: 'Transport & Fuel', placeholder: '200', hint: 'Petrol, public transport, parking' },
      { key: 'healthcare_medical', label: 'Healthcare & Medical', placeholder: '50', hint: 'Prescriptions, dental, optician' },
      { key: 'insurance', label: 'Insurance (non-property)', placeholder: '150', hint: 'Car, private medical, mobile phone etc.' },
    ];

    // Filtered essential fields based on property ownership
    const essentialFields = computed(() => {
      if (hasMainResidence.value) {
        return allEssentialFields.filter(f => f.key !== 'rent' && f.key !== 'utilities');
      }
      if (hasOnlyBuyToLet.value) {
        return allEssentialFields.map(f => {
          if (f.key === 'utilities') {
            return { ...f, hint: 'Utilities for your rented home, not for properties owned' };
          }
          return f;
        });
      }
      return allEssentialFields;
    });

    const communicationFields = [
      { key: 'mobile_phones', label: 'Mobile Phones', placeholder: '50' },
      { key: 'internet_tv', label: 'Internet & TV', placeholder: '60', hint: 'Broadband, TV licence' },
      { key: 'subscriptions', label: 'Subscriptions', placeholder: '30', hint: 'Netflix, Spotify, gym memberships' },
    ];

    const lifestyleFields = [
      { key: 'clothing_personal_care', label: 'Clothing & Personal Care', placeholder: '100', hint: 'Clothes, toiletries, haircuts' },
      { key: 'entertainment_dining', label: 'Entertainment & Dining', placeholder: '200', hint: 'Restaurants, cinema, activities' },
      { key: 'holidays_travel', label: 'Holidays & Travel', placeholder: '200', hint: 'Monthly average for annual holidays' },
      { key: 'pets', label: 'Pets', placeholder: '50', hint: 'Food, vet bills, insurance' },
    ];

    const childrenFields = [
      { key: 'childcare', label: 'Childcare', placeholder: '0', hint: 'Nursery, childminder, after school' },
      { key: 'school_fees', label: 'School Fees', placeholder: '0', hint: 'Private education fees' },
      { key: 'school_lunches', label: 'School Lunches', placeholder: '0' },
      { key: 'school_extras', label: 'School Extras', placeholder: '0', hint: 'Uniforms, trips, equipment etc.' },
      { key: 'university_fees', label: 'University Fees', placeholder: '0', hint: 'Includes residential, books' },
      { key: 'children_activities', label: 'Children\'s Activities', placeholder: '0', hint: 'Sports, music lessons, clubs' },
    ];

    const otherFields = [
      { key: 'gifts_charity', label: 'Gifts & Presents', placeholder: '50', hint: 'Birthday and Christmas gifts' },
      { key: 'charitable_donations', label: 'Charitable Donations', placeholder: '20', hint: 'Monthly charitable giving' },
      { key: 'other_expenditure', label: 'Other Expenditure', placeholder: '0', hint: 'Any other monthly expenses' },
    ];

    const formData = ref({
      rent: 0,
      utilities: 0,
      food_groceries: 0,
      transport_fuel: 0,
      healthcare_medical: 0,
      insurance: 0,
      mobile_phones: 0,
      internet_tv: 0,
      subscriptions: 0,
      clothing_personal_care: 0,
      entertainment_dining: 0,
      holidays_travel: 0,
      pets: 0,
      childcare: 0,
      school_fees: 0,
      school_lunches: 0,
      school_extras: 0,
      university_fees: 0,
      children_activities: 0,
      gifts_charity: 0,
      charitable_donations: 0,
      other_expenditure: 0,
    });

    const spouseFormData = ref({
      rent: 0,
      utilities: 0,
      food_groceries: 0,
      transport_fuel: 0,
      healthcare_medical: 0,
      insurance: 0,
      mobile_phones: 0,
      internet_tv: 0,
      subscriptions: 0,
      clothing_personal_care: 0,
      entertainment_dining: 0,
      holidays_travel: 0,
      pets: 0,
      childcare: 0,
      school_fees: 0,
      school_lunches: 0,
      school_extras: 0,
      university_fees: 0,
      children_activities: 0,
      gifts_charity: 0,
      charitable_donations: 0,
      other_expenditure: 0,
    });

    // Retired/Widowed budget state
    const isEditingRetired = ref(false);
    const retiredBudgetData = ref({});
    const retiredBudgetOverrides = ref({});
    const isEditingWidowed = ref(false);
    const widowedBudgetData = ref({});
    const widowedBudgetOverrides = ref({});

    // Section configs for loops
    const retiredSectionConfigs = {
      essential: { title: 'Essential Living' },
      communication: { title: 'Communication & Technology' },
      lifestyle: { title: 'Personal & Lifestyle' },
      children: { title: 'Children & Dependents' },
      other: { title: 'Other Expenses' },
    };

    const widowedSectionConfigs = {
      essential: { title: 'Essential Living' },
      communication: { title: 'Communication & Technology' },
      lifestyle: { title: 'Personal & Lifestyle' },
      children: { title: 'Children & Dependents' },
      other: { title: 'Other Expenses' },
    };

    // Calculate sub-totals
    const calculateSubtotal = (fields, data) => {
      return fields.reduce((sum, field) => sum + (parseFloat(data[field.key]) || 0), 0);
    };

    const essentialTotal = computed(() => calculateSubtotal(essentialFields.value, formData.value));
    const communicationTotal = computed(() => calculateSubtotal(communicationFields, formData.value));
    const lifestyleTotal = computed(() => calculateSubtotal(lifestyleFields, formData.value));
    const childrenTotal = computed(() => calculateSubtotal(childrenFields, formData.value));
    const otherTotal = computed(() => calculateSubtotal(otherFields, formData.value));

    const spouseEssentialTotal = computed(() => calculateSubtotal(essentialFields.value, spouseFormData.value));
    const spouseCommunicationTotal = computed(() => calculateSubtotal(communicationFields, spouseFormData.value));
    const spouseLifestyleTotal = computed(() => calculateSubtotal(lifestyleFields, spouseFormData.value));
    const spouseChildrenTotal = computed(() => calculateSubtotal(childrenFields, spouseFormData.value));
    const spouseOtherTotal = computed(() => calculateSubtotal(otherFields, spouseFormData.value));

    const householdEssentialTotal = computed(() => essentialTotal.value + (props.isMarried ? spouseEssentialTotal.value : 0));
    const householdCommunicationTotal = computed(() => communicationTotal.value + (props.isMarried ? spouseCommunicationTotal.value : 0));
    const householdLifestyleTotal = computed(() => lifestyleTotal.value + (props.isMarried ? spouseLifestyleTotal.value : 0));
    const householdChildrenTotal = computed(() => childrenTotal.value + (props.isMarried ? spouseChildrenTotal.value : 0));
    const householdOtherTotal = computed(() => otherTotal.value + (props.isMarried ? spouseOtherTotal.value : 0));

    // Total calculations
    const totalMonthlyExpenditure = computed(() => {
      if (useSimpleEntry.value) return simpleMonthlyExpenditure.value || 0;
      return essentialTotal.value + communicationTotal.value + lifestyleTotal.value + childrenTotal.value + otherTotal.value;
    });

    const spouseTotalMonthlyExpenditure = computed(() => {
      if (useSimpleEntry.value) return spouseSimpleMonthlyExpenditure.value || 0;
      return spouseEssentialTotal.value + spouseCommunicationTotal.value + spouseLifestyleTotal.value + spouseChildrenTotal.value + spouseOtherTotal.value;
    });

    const householdTotalMonthlyExpenditure = computed(() => {
      return totalMonthlyExpenditure.value + (props.isMarried ? spouseTotalMonthlyExpenditure.value : 0);
    });

    const commitmentsTotal = computed(() => financialCommitments.value?.totals?.total || 0);
    const commitmentsLumpSumTotal = computed(() => financialCommitments.value?.totals?.annual_lump_sum || 0);
    const spouseCommitmentsTotal = computed(() => spouseFinancialCommitments.value?.totals?.total || 0);
    const spouseCommitmentsLumpSumTotal = computed(() => spouseFinancialCommitments.value?.totals?.annual_lump_sum || 0);

    const totalMonthlyWithCommitments = computed(() => totalMonthlyExpenditure.value + commitmentsTotal.value);
    const totalAnnualWithCommitments = computed(() => (totalMonthlyWithCommitments.value * 12) + commitmentsLumpSumTotal.value);

    const spouseTotalMonthlyWithCommitments = computed(() => spouseTotalMonthlyExpenditure.value + spouseCommitmentsTotal.value);
    const spouseTotalAnnualWithCommitments = computed(() => (spouseTotalMonthlyWithCommitments.value * 12) + spouseCommitmentsLumpSumTotal.value);

    const householdTotalMonthlyWithCommitments = computed(() => {
      if (!props.isMarried) return totalMonthlyWithCommitments.value;
      return totalMonthlyWithCommitments.value + spouseTotalMonthlyWithCommitments.value;
    });
    const householdTotalAnnualWithCommitments = computed(() => (householdTotalMonthlyWithCommitments.value * 12) + commitmentsLumpSumTotal.value + (props.isMarried ? spouseCommitmentsLumpSumTotal.value : 0));

    const getHouseholdValue = (key) => {
      const userVal = parseFloat(formData.value[key]) || 0;
      const spouseVal = props.isMarried ? (parseFloat(spouseFormData.value[key]) || 0) : 0;
      return userVal + spouseVal;
    };

    // Commitments checks
    const hasRetirementCommitments = computed(() => financialCommitments.value?.commitments?.retirement?.length > 0);
    const hasPropertyCommitments = computed(() => financialCommitments.value?.commitments?.properties?.length > 0);
    const hasInvestmentCommitments = computed(() => financialCommitments.value?.commitments?.investments?.length > 0);
    const hasSavingsCommitments = computed(() => financialCommitments.value?.commitments?.savings?.length > 0);
    const hasProtectionCommitments = computed(() => financialCommitments.value?.commitments?.protection?.length > 0);
    const hasLiabilityCommitments = computed(() => financialCommitments.value?.commitments?.liabilities?.length > 0);
    const hasAnyCommitments = computed(() => hasRetirementCommitments.value || hasPropertyCommitments.value || hasInvestmentCommitments.value || hasSavingsCommitments.value || hasProtectionCommitments.value || hasLiabilityCommitments.value);

    const spouseHasRetirementCommitments = computed(() => spouseFinancialCommitments.value?.commitments?.retirement?.length > 0);
    const spouseHasPropertyCommitments = computed(() => spouseFinancialCommitments.value?.commitments?.properties?.length > 0);
    const spouseHasInvestmentCommitments = computed(() => spouseFinancialCommitments.value?.commitments?.investments?.length > 0);
    const spouseHasSavingsCommitments = computed(() => spouseFinancialCommitments.value?.commitments?.savings?.length > 0);
    const spouseHasProtectionCommitments = computed(() => spouseFinancialCommitments.value?.commitments?.protection?.length > 0);
    const spouseHasLiabilityCommitments = computed(() => spouseFinancialCommitments.value?.commitments?.liabilities?.length > 0);

    // Retired Budget Logic
    const retirementInfo = computed(() => {
      const userData = props.initialData || {};
      const spouseUserData = props.spouseData || {};

      const calculateRetirementAge = (dob, retirementDate) => {
        if (!dob) return 65;
        if (retirementDate) {
          const birth = new Date(dob);
          const retire = new Date(retirementDate);
          return Math.floor((retire - birth) / (365.25 * 24 * 60 * 60 * 1000));
        }
        return 65;
      };

      return {
        userRetirementAge: calculateRetirementAge(userData.date_of_birth, userData.retirement_date),
        spouseRetirementAge: calculateRetirementAge(spouseUserData.date_of_birth, spouseUserData.retirement_date),
        userCurrentAge: userData.date_of_birth
          ? Math.floor((new Date() - new Date(userData.date_of_birth)) / (365.25 * 24 * 60 * 60 * 1000))
          : null,
        spouseCurrentAge: spouseUserData.date_of_birth
          ? Math.floor((new Date() - new Date(spouseUserData.date_of_birth)) / (365.25 * 24 * 60 * 60 * 1000))
          : null,
      };
    });

    const retiredBudgetFields = computed(() => ({
      essential: [
        { key: 'rent', label: 'Rent', hint: 'May be £0 if mortgage paid off or homeowner' },
        { key: 'utilities', label: 'Utilities', hint: 'Gas, electricity, water, council tax' },
        { key: 'food_groceries', label: 'Food & Groceries', hint: 'May reduce slightly - more time to cook at home' },
        { key: 'transport_fuel', label: 'Transport & Fuel', hint: 'Usually reduces significantly - no commuting' },
        { key: 'healthcare_medical', label: 'Healthcare & Medical', hint: 'May increase with age' },
        { key: 'insurance', label: 'Insurance (non-property)', hint: 'Car insurance may reduce' },
      ],
      communication: [
        { key: 'mobile_phones', label: 'Mobile Phones' },
        { key: 'internet_tv', label: 'Internet & TV' },
        { key: 'subscriptions', label: 'Subscriptions' },
      ],
      lifestyle: [
        { key: 'clothing_personal_care', label: 'Clothing & Personal Care', hint: 'Usually reduces - no work wardrobe' },
        { key: 'entertainment_dining', label: 'Entertainment & Dining', hint: 'May increase - more leisure time' },
        { key: 'holidays_travel', label: 'Holidays & Travel', hint: 'Often increases in early retirement' },
        { key: 'pets', label: 'Pets' },
      ],
      children: [
        { key: 'childcare', label: 'Childcare', hint: 'Typically £0 by retirement age' },
        { key: 'school_fees', label: 'School Fees', hint: 'Typically £0 by retirement age' },
        { key: 'school_lunches', label: 'School Lunches', hint: 'Typically £0 by retirement age' },
        { key: 'school_extras', label: 'School Extras', hint: 'Typically £0 by retirement age' },
        { key: 'university_fees', label: 'University Fees', hint: 'Typically £0 by retirement age' },
        { key: 'children_activities', label: 'Children\'s Activities', hint: 'Typically £0 by retirement age' },
      ],
      other: [
        { key: 'gifts_charity', label: 'Gifts & Presents' },
        { key: 'charitable_donations', label: 'Charitable Donations' },
        { key: 'other_expenditure', label: 'Other Expenditure' },
      ],
    }));

    const allRetiredFields = computed(() => [
      ...retiredBudgetFields.value.essential,
      ...retiredBudgetFields.value.communication,
      ...retiredBudgetFields.value.lifestyle,
      ...retiredBudgetFields.value.children,
      ...retiredBudgetFields.value.other,
    ]);

    const retiredAdjustmentRules = {
      transport_fuel: { factor: 0.4, reason: 'Reduced by 60% - no commuting' },
      clothing_personal_care: { factor: 0.7, reason: 'Reduced by 30% - no work wardrobe needed' },
      childcare: { factor: 0, reason: 'Set to £0 - children typically independent by retirement' },
      school_fees: { factor: 0, reason: 'Set to £0 - children typically finished education' },
      school_lunches: { factor: 0, reason: 'Set to £0 - children typically finished school' },
      school_extras: { factor: 0, reason: 'Set to £0 - children typically finished school' },
      university_fees: { factor: 0, reason: 'Set to £0 - children typically finished education' },
      children_activities: { factor: 0, reason: 'Set to £0 - children typically independent' },
      healthcare_medical: { factor: 1.3, reason: 'Increased by 30% - healthcare costs typically rise' },
      holidays_travel: { factor: 1.2, reason: 'Increased by 20% - more leisure time' },
    };

    const getCurrentBudgetValue = (key) => {
      const userVal = parseFloat(formData.value[key]) || 0;
      const spouseVal = props.isMarried ? (parseFloat(spouseFormData.value[key]) || 0) : 0;
      return userVal + spouseVal;
    };

    const initializeRetiredBudget = () => {
      const allFields = allRetiredFields.value;
      const newData = {};

      allFields.forEach(field => {
        const currentValue = getCurrentBudgetValue(field.key);
        const rule = retiredAdjustmentRules[field.key];
        const savedOverride = retiredBudgetOverrides.value[field.key];

        if (savedOverride !== undefined) {
          newData[field.key] = {
            value: savedOverride,
            originalValue: currentValue,
            adjusted: rule !== undefined,
            userModified: true,
            reason: rule?.reason || null,
          };
        } else if (rule) {
          newData[field.key] = {
            value: Math.round(currentValue * rule.factor),
            originalValue: currentValue,
            adjusted: true,
            userModified: false,
            reason: rule.reason,
          };
        } else {
          newData[field.key] = {
            value: currentValue,
            originalValue: currentValue,
            adjusted: false,
            userModified: false,
            reason: null,
          };
        }
      });

      retiredBudgetData.value = newData;
    };

    const retiredAutoAdjustments = computed(() => {
      const adjustments = [];

      if ((financialCommitments.value?.totals?.retirement || 0) > 0) {
        adjustments.push({
          description: `Pension contributions stopped (was £${Math.round(financialCommitments.value.totals.retirement)}/month)`,
        });
      }

      Object.entries(retiredBudgetData.value).forEach(([key, data]) => {
        if (data.adjusted && !data.userModified && data.value !== data.originalValue) {
          const field = allRetiredFields.value.find(f => f.key === key);
          if (field) {
            adjustments.push({
              description: `${field.label}: ${data.reason}`,
            });
          }
        }
      });

      return adjustments;
    });

    // User's retired total: manual expenditure + protection premiums + investment contributions
    const retiredTotalMonthly = computed(() => {
      return retiredManualExpenditureTotal.value +
        (financialCommitments.value?.totals?.protection || 0) +
        (financialCommitments.value?.totals?.investments || 0);
    });

    // Spouse's retired total: spouse's manual expenditure + spouse's protection + spouse's investments
    const retiredSpouseTotalMonthly = computed(() => {
      if (!props.isMarried) return 0;
      return retiredSpouseManualExpenditureTotal.value +
        (spouseFinancialCommitments.value?.totals?.protection || 0) +
        (spouseFinancialCommitments.value?.totals?.investments || 0);
    });

    // Household total is user + spouse
    const retiredHouseholdTotalMonthly = computed(() => {
      return retiredTotalMonthly.value + retiredSpouseTotalMonthly.value;
    });

    const getRetiredUserValue = (key) => {
      const currentValue = parseFloat(formData.value[key]) || 0;
      const rule = retiredAdjustmentRules[key];
      if (retiredBudgetOverrides.value[key] !== undefined) {
        const householdCurrent = getCurrentBudgetValue(key);
        if (householdCurrent > 0) {
          const userProportion = currentValue / householdCurrent;
          return Math.round(retiredBudgetOverrides.value[key] * userProportion);
        }
        return retiredBudgetOverrides.value[key];
      }
      return rule ? Math.round(currentValue * rule.factor) : currentValue;
    };

    const getRetiredSpouseValue = (key) => {
      if (!props.isMarried) return 0;
      const currentValue = parseFloat(spouseFormData.value[key]) || 0;
      const rule = retiredAdjustmentRules[key];
      if (retiredBudgetOverrides.value[key] !== undefined) {
        const householdCurrent = getCurrentBudgetValue(key);
        if (householdCurrent > 0) {
          const spouseProportion = currentValue / householdCurrent;
          return Math.round(retiredBudgetOverrides.value[key] * spouseProportion);
        }
        return 0;
      }
      return rule ? Math.round(currentValue * rule.factor) : currentValue;
    };

    const getRetiredChange = (key, isSpouse = false) => {
      if (isSpouse) {
        const current = spouseFormData.value[key] || 0;
        const retired = getRetiredSpouseValue(key);
        return retired - current;
      }
      const current = formData.value[key] || 0;
      const retired = getRetiredUserValue(key);
      return retired - current;
    };

    const getRetiredUserSectionTotal = (section) => {
      const fields = retiredBudgetFields.value[section] || [];
      return fields.reduce((total, field) => {
        return total + getRetiredUserValue(field.key);
      }, 0);
    };

    const getRetiredSpouseSectionTotal = (section) => {
      const fields = retiredBudgetFields.value[section] || [];
      return fields.reduce((total, field) => {
        return total + getRetiredSpouseValue(field.key);
      }, 0);
    };

    const getRetiredSectionTotal = (section) => {
      return getRetiredUserSectionTotal(section) + (props.isMarried ? getRetiredSpouseSectionTotal(section) : 0);
    };

    const retiredManualExpenditureTotal = computed(() => {
      const sections = ['essential', 'communication', 'lifestyle', 'children', 'other'];
      return sections.reduce((total, section) => {
        const fields = retiredBudgetFields.value[section] || [];
        return total + fields.reduce((sectionTotal, field) => {
          return sectionTotal + getRetiredUserValue(field.key);
        }, 0);
      }, 0);
    });

    const retiredSpouseManualExpenditureTotal = computed(() => {
      const sections = ['essential', 'communication', 'lifestyle', 'children', 'other'];
      return sections.reduce((total, section) => {
        const fields = retiredBudgetFields.value[section] || [];
        return total + fields.reduce((sectionTotal, field) => {
          return sectionTotal + getRetiredSpouseValue(field.key);
        }, 0);
      }, 0);
    });

    const retiredHouseholdManualExpenditureTotal = computed(() => {
      return retiredManualExpenditureTotal.value + retiredSpouseManualExpenditureTotal.value;
    });

    const markRetiredFieldModified = (key) => {
      if (retiredBudgetData.value[key]) {
        retiredBudgetData.value[key].userModified = true;
        retiredBudgetOverrides.value[key] = retiredBudgetData.value[key].value;
      }
    };

    const resetRetiredField = (key) => {
      const currentValue = getCurrentBudgetValue(key);
      const rule = retiredAdjustmentRules[key];

      delete retiredBudgetOverrides.value[key];

      retiredBudgetData.value[key] = {
        value: rule ? Math.round(currentValue * rule.factor) : currentValue,
        originalValue: currentValue,
        adjusted: rule !== undefined,
        userModified: false,
        reason: rule?.reason || null,
      };
    };

    const cancelRetiredEdit = () => {
      initializeRetiredBudget();
      isEditingRetired.value = false;
    };

    const saveRetiredBudget = () => {
      Object.entries(retiredBudgetData.value).forEach(([key, data]) => {
        if (data.userModified) {
          retiredBudgetOverrides.value[key] = data.value;
        }
      });
      isEditingRetired.value = false;
    };

    // Widowed Budget Logic
    const widowedBudgetFields = computed(() => ({
      essential: [
        { key: 'rent', label: 'Rent', hint: 'May continue or reduce depending on housing' },
        { key: 'utilities', label: 'Utilities', hint: 'May reduce slightly for single person' },
        { key: 'food_groceries', label: 'Food & Groceries', hint: 'Typically reduces to ~60% for single person' },
        { key: 'transport_fuel', label: 'Transport & Fuel', hint: 'May reduce - one car instead of two' },
        { key: 'healthcare_medical', label: 'Healthcare & Medical' },
        { key: 'insurance', label: 'Insurance (non-property)' },
      ],
      communication: [
        { key: 'mobile_phones', label: 'Mobile Phones', hint: 'Typically reduces by half' },
        { key: 'internet_tv', label: 'Internet & TV' },
        { key: 'subscriptions', label: 'Subscriptions', hint: 'May reduce - fewer shared subscriptions' },
      ],
      lifestyle: [
        { key: 'clothing_personal_care', label: 'Clothing & Personal Care', hint: 'Typically reduces to ~50%' },
        { key: 'entertainment_dining', label: 'Entertainment & Dining', hint: 'Typically reduces - dining alone less' },
        { key: 'holidays_travel', label: 'Holidays & Travel', hint: 'May reduce - single traveller' },
        { key: 'pets', label: 'Pets' },
      ],
      children: [
        { key: 'childcare', label: 'Childcare', hint: 'May increase if spouse provided care' },
        { key: 'school_fees', label: 'School Fees' },
        { key: 'school_lunches', label: 'School Lunches' },
        { key: 'school_extras', label: 'School Extras' },
        { key: 'university_fees', label: 'University Fees' },
        { key: 'children_activities', label: 'Children\'s Activities' },
      ],
      other: [
        { key: 'gifts_charity', label: 'Gifts & Presents' },
        { key: 'charitable_donations', label: 'Charitable Donations' },
        { key: 'other_expenditure', label: 'Other Expenditure' },
      ],
    }));

    const allWidowedFields = computed(() => [
      ...widowedBudgetFields.value.essential,
      ...widowedBudgetFields.value.communication,
      ...widowedBudgetFields.value.lifestyle,
      ...widowedBudgetFields.value.children,
      ...widowedBudgetFields.value.other,
    ]);

    const widowedAdjustmentRules = {
      utilities: { factor: 0.75, reason: 'Reduced by 25% - single person uses less' },
      food_groceries: { factor: 0.6, reason: 'Reduced by 40% - single person household' },
      transport_fuel: { factor: 0.6, reason: 'Reduced by 40% - one car/person' },
      mobile_phones: { factor: 0.5, reason: 'Reduced by 50% - one phone contract' },
      subscriptions: { factor: 0.7, reason: 'Reduced by 30% - fewer shared subscriptions' },
      clothing_personal_care: { factor: 0.5, reason: 'Reduced by 50% - single person' },
      entertainment_dining: { factor: 0.6, reason: 'Reduced by 40% - single person' },
      holidays_travel: { factor: 0.7, reason: 'Reduced by 30% - single traveller' },
      childcare: { factor: 1.3, reason: 'Increased by 30% - may need additional childcare' },
    };

    const initializeWidowedBudget = () => {
      const allFields = allWidowedFields.value;
      const newData = {};

      allFields.forEach(field => {
        const householdValue = getHouseholdValue(field.key);
        const rule = widowedAdjustmentRules[field.key];
        const savedOverride = widowedBudgetOverrides.value[field.key];

        if (savedOverride !== undefined) {
          newData[field.key] = {
            value: savedOverride,
            originalValue: householdValue,
            adjusted: rule !== undefined,
            userModified: true,
            reason: rule?.reason || null,
          };
        } else if (rule) {
          newData[field.key] = {
            value: Math.round(householdValue * rule.factor),
            originalValue: householdValue,
            adjusted: true,
            userModified: false,
            reason: rule.reason,
          };
        } else {
          newData[field.key] = {
            value: householdValue,
            originalValue: householdValue,
            adjusted: false,
            userModified: false,
            reason: null,
          };
        }
      });

      widowedBudgetData.value = newData;
    };

    const widowedAutoAdjustments = computed(() => {
      const adjustments = [];

      Object.entries(widowedBudgetData.value).forEach(([key, data]) => {
        if (data.adjusted && !data.userModified && data.value !== data.originalValue) {
          const field = allWidowedFields.value.find(f => f.key === key);
          if (field) {
            adjustments.push({
              description: `${field.label}: ${data.reason}`,
            });
          }
        }
      });

      return adjustments;
    });

    // Merge user and spouse items for widowed budget - combine same assets into household totals
    const mergeToHouseholdItems = (userItems, spouseItems) => {
      const itemMap = new Map();

      // Helper to add item to map (deep copy breakdown to avoid mutations)
      const addItem = (item) => {
        const key = `${item.id}-${item.name || item.policy_name || item.liability_name || ''}`;
        if (itemMap.has(key)) {
          // Same item exists - add amounts together
          const existing = itemMap.get(key);
          existing.monthly_amount = (existing.monthly_amount || 0) + (item.monthly_amount || 0);
          existing.lump_sum_amount = (existing.lump_sum_amount || 0) + (item.lump_sum_amount || 0);
          // Add breakdown values
          if (item.breakdown) {
            if (!existing.breakdown) existing.breakdown = {};
            Object.entries(item.breakdown).forEach(([k, v]) => {
              existing.breakdown[k] = (existing.breakdown[k] || 0) + v;
            });
          }
        } else {
          // Create new entry with deep copied breakdown
          itemMap.set(key, {
            ...item,
            monthly_amount: item.monthly_amount || 0,
            breakdown: item.breakdown ? { ...item.breakdown } : null,
          });
        }
      };

      // Add all user items
      (userItems || []).forEach(addItem);
      // Add all spouse items
      (spouseItems || []).forEach(addItem);

      // Return merged items marked as 100% ownership (survivor responsible for all)
      return Array.from(itemMap.values()).map(item => ({
        ...item,
        ownership_percentage: 100,
        is_joint: false,
      }));
    };

    const widowedCommitments = computed(() => {
      // In widowed scenario, survivor is responsible for ALL household expenses
      const userProperty = financialCommitments.value?.totals?.properties || 0;
      const spouseProperty = spouseFinancialCommitments.value?.totals?.properties || 0;
      const userInvestments = financialCommitments.value?.totals?.investments || 0;
      const spouseInvestments = spouseFinancialCommitments.value?.totals?.investments || 0;
      const userSavings = financialCommitments.value?.totals?.savings || 0;
      const spouseSavings = spouseFinancialCommitments.value?.totals?.savings || 0;
      const userProtection = financialCommitments.value?.totals?.protection || 0;
      const spouseProtection = spouseFinancialCommitments.value?.totals?.protection || 0;
      const userLoans = financialCommitments.value?.totals?.liabilities || 0;
      const spouseLoans = spouseFinancialCommitments.value?.totals?.liabilities || 0;

      // Merge items from both users into household totals
      const propertyItems = mergeToHouseholdItems(
        financialCommitments.value?.commitments?.properties,
        spouseFinancialCommitments.value?.commitments?.properties
      );
      const investmentItems = mergeToHouseholdItems(
        financialCommitments.value?.commitments?.investments,
        spouseFinancialCommitments.value?.commitments?.investments
      );
      const savingsItems = mergeToHouseholdItems(
        financialCommitments.value?.commitments?.savings,
        spouseFinancialCommitments.value?.commitments?.savings
      );
      const protectionItems = mergeToHouseholdItems(
        financialCommitments.value?.commitments?.protection,
        spouseFinancialCommitments.value?.commitments?.protection
      );
      const liabilityItems = mergeToHouseholdItems(
        financialCommitments.value?.commitments?.liabilities,
        spouseFinancialCommitments.value?.commitments?.liabilities
      );

      return {
        propertyAmount: userProperty + spouseProperty,
        protectionAmount: userProtection + spouseProtection,
        investmentsAmount: userInvestments + spouseInvestments,
        savingsAmount: userSavings + spouseSavings,
        loansAmount: userLoans + spouseLoans,
        // Include merged items for display
        propertyItems,
        investmentItems,
        savingsItems,
        protectionItems,
        liabilityItems,
      };
    });

    const widowedTotalMonthly = computed(() => {
      let total = 0;
      Object.values(widowedBudgetData.value).forEach(data => {
        total += data.value || 0;
      });
      total += widowedCommitments.value.propertyAmount;
      total += widowedCommitments.value.protectionAmount;
      total += widowedCommitments.value.investmentsAmount;
      total += widowedCommitments.value.savingsAmount;
      total += widowedCommitments.value.loansAmount;
      return total;
    });

    const getWidowedValue = (key) => {
      return widowedBudgetData.value[key]?.value || 0;
    };

    const getWidowedChange = (key) => {
      const currentHousehold = getHouseholdValue(key);
      const widowed = getWidowedValue(key);
      return widowed - currentHousehold;
    };

    const getWidowedSectionTotal = (section) => {
      const fields = widowedBudgetFields.value[section] || [];
      return fields.reduce((total, field) => {
        return total + getWidowedValue(field.key);
      }, 0);
    };

    const widowedManualExpenditureTotal = computed(() => {
      const sections = ['essential', 'communication', 'lifestyle', 'children', 'other'];
      return sections.reduce((total, section) => total + getWidowedSectionTotal(section), 0);
    });

    const markWidowedFieldModified = (key) => {
      if (widowedBudgetData.value[key]) {
        widowedBudgetData.value[key].userModified = true;
        widowedBudgetOverrides.value[key] = widowedBudgetData.value[key].value;
      }
    };

    const resetWidowedField = (key) => {
      const householdValue = getHouseholdValue(key);
      const rule = widowedAdjustmentRules[key];

      delete widowedBudgetOverrides.value[key];

      widowedBudgetData.value[key] = {
        value: rule ? Math.round(householdValue * rule.factor) : householdValue,
        originalValue: householdValue,
        adjusted: rule !== undefined,
        userModified: false,
        reason: rule?.reason || null,
      };
    };

    const cancelWidowedEdit = () => {
      initializeWidowedBudget();
      isEditingWidowed.value = false;
    };

    const saveWidowedBudget = () => {
      Object.entries(widowedBudgetData.value).forEach(([key, data]) => {
        if (data.userModified) {
          widowedBudgetOverrides.value[key] = data.value;
        }
      });
      isEditingWidowed.value = false;
    };

    const fetchCommitments = async () => {
      loadingCommitments.value = true;
      try {
        const response = await api.get('/user/financial-commitments');
        financialCommitments.value = response.data.data;

        // Always fetch spouse commitments if married - the backend will determine spouse from auth user
        // Don't rely on user.value?.spouse_id as it may not be loaded yet at mount time
        if (props.isMarried) {
          try {
            const spouseResponse = await api.get('/user/spouse/financial-commitments');
            spouseFinancialCommitments.value = spouseResponse.data.data;
          } catch (err) {
            // 404 is expected if no spouse linked - only log other errors
            if (err.response?.status !== 404) {
              logger.error('Failed to fetch spouse commitments:', err);
            }
          }
        }
      } catch (err) {
        logger.error('Failed to fetch commitments:', err);
      } finally {
        loadingCommitments.value = false;
      }
    };

    const initializeFromProps = () => {
      if (props.initialData) {
        useSeparateExpenditure.value = props.initialData.expenditure_sharing_mode === 'separate';
        useSimpleEntry.value = props.initialData.expenditure_entry_mode
          ? props.initialData.expenditure_entry_mode === 'simple'
          : (props.isOnboarding ? true : false);
        simpleMonthlyExpenditure.value = props.initialData.monthly_expenditure || 0;

        const allFields = [...allEssentialFields, ...communicationFields, ...lifestyleFields, ...childrenFields, ...otherFields];
        allFields.forEach(field => {
          formData.value[field.key] = parseFloat(props.initialData[field.key]) || 0;
        });

        // Restore persisted budget overrides
        if (props.initialData.retired_budget_overrides && typeof props.initialData.retired_budget_overrides === 'object') {
          retiredBudgetOverrides.value = { ...props.initialData.retired_budget_overrides };
        }
        if (props.initialData.widowed_budget_overrides && typeof props.initialData.widowed_budget_overrides === 'object') {
          widowedBudgetOverrides.value = { ...props.initialData.widowed_budget_overrides };
        }
      }

      if (props.spouseData) {
        spouseSimpleMonthlyExpenditure.value = parseFloat(props.spouseData.monthly_expenditure) || 0;
        const allFields = [...allEssentialFields, ...communicationFields, ...lifestyleFields, ...childrenFields, ...otherFields];
        allFields.forEach(field => {
          spouseFormData.value[field.key] = parseFloat(props.spouseData[field.key]) || 0;
        });
      }
    };

    const handleSave = () => {
      const allFields = [...allEssentialFields, ...communicationFields, ...lifestyleFields, ...childrenFields, ...otherFields];

      const saveData = {
        use_simple_entry: useSimpleEntry.value,
        expenditure_entry_mode: useSimpleEntry.value ? 'simple' : 'category',
        use_separate_expenditure: useSeparateExpenditure.value,
        monthly_expenditure: useSimpleEntry.value ? simpleMonthlyExpenditure.value : totalMonthlyExpenditure.value,
        annual_expenditure: useSimpleEntry.value ? simpleMonthlyExpenditure.value * 12 : totalMonthlyExpenditure.value * 12,
      };

      allFields.forEach(field => {
        saveData[field.key] = formData.value[field.key] || 0;
      });

      // Include budget overrides if user has customised them
      if (Object.keys(retiredBudgetOverrides.value).length > 0) {
        saveData.retired_budget_overrides = { ...retiredBudgetOverrides.value };
      }
      if (Object.keys(widowedBudgetOverrides.value).length > 0) {
        saveData.widowed_budget_overrides = { ...widowedBudgetOverrides.value };
      }

      if (props.isMarried && useSeparateExpenditure.value) {
        // Separate mode — each spouse has their own data
        const spouseData = {
          use_simple_entry: useSimpleEntry.value,
          monthly_expenditure: useSimpleEntry.value ? spouseSimpleMonthlyExpenditure.value : spouseTotalMonthlyExpenditure.value,
          annual_expenditure: useSimpleEntry.value ? spouseSimpleMonthlyExpenditure.value * 12 : spouseTotalMonthlyExpenditure.value * 12,
        };

        allFields.forEach(field => {
          spouseData[field.key] = spouseFormData.value[field.key] || 0;
        });

        emit('save', {
          userData: saveData,
          spouseData: spouseData,
        });
      } else if (props.isMarried) {
        // Joint mode — spouse gets the same expenditure values
        const spouseData = {
          use_simple_entry: useSimpleEntry.value,
          monthly_expenditure: saveData.monthly_expenditure,
          annual_expenditure: saveData.annual_expenditure,
        };

        allFields.forEach(field => {
          spouseData[field.key] = formData.value[field.key] || 0;
        });

        emit('save', {
          userData: saveData,
          spouseData: spouseData,
        });
      } else {
        emit('save', saveData);
      }

      // Save charitable donations and Gift Aid to user profile
      const monthlyCharitable = formData.value.charitable_donations || 0;
      if (monthlyCharitable > 0 || isGiftAid.value) {
        store.dispatch('userProfile/updatePersonalInfo', {
          annual_charitable_donations: monthlyCharitable * 12,
          is_gift_aid: isGiftAid.value,
        }).catch((err) => {
          logger.error('Failed to save charitable donations to user profile:', err);
        });
      }

      if (!props.isOnboarding) {
        isEditing.value = false;
      }
    };

    const handleCancel = () => {
      initializeFromProps();
      isEditing.value = false;
      emit('cancel');
    };

    watch(() => props.initialData, initializeFromProps, { deep: true });
    watch(() => props.spouseData, initializeFromProps, { deep: true });

    let budgetDebounceTimeout = null;
    watch([formData, spouseFormData], () => {
      if (budgetDebounceTimeout) clearTimeout(budgetDebounceTimeout);
      budgetDebounceTimeout = setTimeout(() => {
        initializeRetiredBudget();
        initializeWidowedBudget();
      }, 150);
    }, { deep: true });

    const mountTimeout = ref(null);
    onMounted(() => {
      initializeFromProps();
      fetchCommitments();
      api.get('/properties').then(res => {
        const data = res.data?.data || res.data;
        properties.value = Array.isArray(data) ? data : [];
      }).catch(() => {});
      mountTimeout.value = setTimeout(() => {
        initializeRetiredBudget();
        initializeWidowedBudget();
      }, 100);

      // Initialize Gift Aid from user profile
      if (user.value) {
        isGiftAid.value = user.value.is_gift_aid || false;
      }
    });

    onBeforeUnmount(() => {
      if (budgetDebounceTimeout) clearTimeout(budgetDebounceTimeout);
      if (mountTimeout.value) clearTimeout(mountTimeout.value);
    });

    // ── AI Form Fill ──────────────────────────────────────────────
    const pendingFill = computed(() => store.state.aiFormFill?.pendingFill);
    const highlightedField = computed(() => store.state.aiFormFill?.highlightedField);
    const filling = computed(() => store.state.aiFormFill?.filling);

    watch(pendingFill, (fill) => {
      if (fill && fill.entityType === 'expenditure' && fill.fields) {
        // Auto-enter edit mode and use detailed entry
        isEditing.value = true;
        useSimpleEntry.value = false;
        activeBudgetTab.value = 'current';
        // Start field sequence
        const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
        store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
      }
    }, { immediate: true });

    watch(highlightedField, (fieldKey) => {
      if (fieldKey && pendingFill.value?.fields) {
        const value = pendingFill.value.fields[fieldKey];
        if (value !== undefined && value !== null) {
          formData.value[fieldKey] = value;
        }
      }
    });

    watch(filling, (isFilling) => {
      if (isFilling === false && pendingFill.value?.entityType === 'expenditure') {
        setTimeout(() => {
          handleSave();
        }, 250);
      }
    });

    return {
      activeBudgetTab,
      activePersonTab,
      isEditing,
      useSimpleEntry,
      useSeparateExpenditure,
      simpleMonthlyExpenditure,
      spouseSimpleMonthlyExpenditure,
      isGiftAid,
      formData,
      spouseFormData,
      financialCommitments,
      spouseFinancialCommitments,
      loadingCommitments,
      userName,
      essentialFields,
      communicationFields,
      lifestyleFields,
      childrenFields,
      otherFields,
      essentialTotal,
      communicationTotal,
      lifestyleTotal,
      childrenTotal,
      otherTotal,
      spouseEssentialTotal,
      spouseCommunicationTotal,
      spouseLifestyleTotal,
      spouseChildrenTotal,
      spouseOtherTotal,
      householdEssentialTotal,
      householdCommunicationTotal,
      householdLifestyleTotal,
      householdChildrenTotal,
      householdOtherTotal,
      totalMonthlyExpenditure,
      spouseTotalMonthlyExpenditure,
      householdTotalMonthlyExpenditure,
      totalMonthlyWithCommitments,
      totalAnnualWithCommitments,
      spouseTotalMonthlyWithCommitments,
      spouseTotalAnnualWithCommitments,
      householdTotalMonthlyWithCommitments,
      householdTotalAnnualWithCommitments,
      getHouseholdValue,
      hasRetirementCommitments,
      hasPropertyCommitments,
      hasInvestmentCommitments,
      hasSavingsCommitments,
      hasProtectionCommitments,
      hasLiabilityCommitments,
      hasAnyCommitments,
      spouseHasRetirementCommitments,
      spouseHasPropertyCommitments,
      spouseHasInvestmentCommitments,
      spouseHasSavingsCommitments,
      spouseHasProtectionCommitments,
      spouseHasLiabilityCommitments,
      formatCurrency,
      handleSave,
      handleCancel,
      // Retired Budget
      isEditingRetired,
      retiredBudgetData,
      retirementInfo,
      retiredBudgetFields,
      allRetiredFields,
      retiredAutoAdjustments,
      retiredSectionConfigs,
      getCurrentBudgetValue,
      getRetiredUserValue,
      getRetiredSpouseValue,
      getRetiredChange,
      retiredTotalMonthly,
      retiredSpouseTotalMonthly,
      retiredHouseholdTotalMonthly,
      retiredManualExpenditureTotal,
      retiredSpouseManualExpenditureTotal,
      retiredHouseholdManualExpenditureTotal,
      markRetiredFieldModified,
      resetRetiredField,
      cancelRetiredEdit,
      saveRetiredBudget,
      // Widowed Budget
      isEditingWidowed,
      widowedBudgetData,
      widowedBudgetFields,
      allWidowedFields,
      widowedAutoAdjustments,
      widowedCommitments,
      widowedSectionConfigs,
      widowedTotalMonthly,
      widowedManualExpenditureTotal,
      getWidowedValue,
      getWidowedChange,
      getWidowedSectionTotal,
      markWidowedFieldModified,
      resetWidowedField,
      cancelWidowedEdit,
      saveWidowedBudget,
      // Collapsible sections
      expandedSections,
      toggleSection,
      isSectionExpanded,
      getRetiredSectionTotal,
      getRetiredUserSectionTotal,
      getRetiredSpouseSectionTotal,
      // Tab advancement for onboarding (called by ExpenditureStep)
      advanceToNextTab: () => {
        if (props.isMarried && useSeparateExpenditure.value && activePersonTab.value === 'user') {
          activePersonTab.value = 'spouse';
          return true; // more tabs to view
        }
        return false; // no more tabs
      },
    };
  },

};
</script>

<style scoped>
.expenditure-grid-single {
  display: grid;
  grid-template-columns: 1fr minmax(90px, max-content);
  gap: 0 1rem;
}

.expenditure-grid-married {
  display: grid;
  grid-template-columns: 1fr minmax(90px, max-content) minmax(90px, max-content) minmax(90px, max-content);
  gap: 0 1rem;
}

:deep(.col-label) {
  text-align: left;
}

:deep(.col-value) {
  text-align: right;
}

:deep(.col-value-mid) {
  text-align: right;
}

:deep(.col-total) {
  text-align: right;
}

:deep(.col-header) {
  text-align: right;
}

.retired-grid-single {
  display: grid;
  grid-template-columns: 1fr minmax(140px, max-content);
  gap: 0 1rem;
}

.retired-grid-married {
  display: grid;
  grid-template-columns: 1fr minmax(140px, max-content) minmax(140px, max-content) minmax(100px, max-content);
  gap: 0 1rem;
}

.widowed-grid-single {
  display: grid;
  grid-template-columns: 1fr minmax(120px, max-content);
  gap: 0 1rem;
}

@media (max-width: 640px) {
  .expenditure-grid-single,
  .expenditure-grid-married,
  .retired-grid-single,
  .retired-grid-married,
  .widowed-grid-single {
    grid-template-columns: 1fr;
  }

  :deep(.col-label),
  :deep(.col-value),
  :deep(.col-value-mid),
  :deep(.col-total),
  :deep(.col-header) {
    text-align: left !important;
    padding-left: 0 !important;
  }
}
</style>
