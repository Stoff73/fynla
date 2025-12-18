<template>
  <AppLayout>
    <PrintHeader title="Comprehensive Protection Plan" />
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header with Download Button -->
      <div class="no-print mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Your Comprehensive Protection Plan</h1>
          <p class="text-gray-600 mt-1">Complete protection analysis covering Life, Critical Illness, and Income Protection</p>
        </div>
        <button
          @click="downloadPDF"
          class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 flex items-center gap-2 shadow-md"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Download PDF
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="text-center">
          <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-2 border-primary-600 mb-4"></div>
          <p class="text-gray-600 text-lg">Generating your comprehensive protection plan...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-start">
          <svg class="h-6 w-6 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">Failed to Generate Protection Plan</h3>
            <p class="mt-2 text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Protection Plan Document -->
      <div v-else-if="plan" id="protection-plan-document" class="bg-white shadow-lg rounded-lg">
        <!-- PDF Logo Header -->
        <div class="pdf-header flex items-center justify-between p-6 border-b-2 border-gray-200">
          <img :src="logoUrl" alt="Fynla" class="h-12 w-auto" />
          <div class="text-right">
            <div class="text-lg font-semibold text-gray-700">Comprehensive Protection Plan</div>
            <div class="text-sm text-gray-500">{{ plan.plan_metadata.generated_date }}</div>
          </div>
        </div>

        <!-- Document Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-8">
          <h2 class="text-3xl font-bold mb-2">{{ plan.executive_summary.title }}</h2>
          <p class="text-blue-100">Generated on {{ plan.plan_metadata.generated_date }} at {{ plan.plan_metadata.generated_time }}</p>
          <p class="text-blue-100">Plan Version: {{ plan.plan_metadata.plan_version }}</p>

          <!-- Plan Type Badge -->
          <div class="mt-4">
            <span
              v-if="plan.plan_metadata.plan_type"
              :class="getPlanTypeBadgeClass(plan.plan_metadata.is_complete)"
              class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
            >
              <svg v-if="plan.plan_metadata.is_complete" class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <svg v-else class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              {{ plan.plan_metadata.plan_type }} Plan
            </span>
            <span v-if="!plan.plan_metadata.is_complete" class="ml-2 text-blue-100 text-sm">
              ({{ plan.plan_metadata.completeness_score }}% profile complete)
            </span>
          </div>
        </div>

        <!-- Profile Completeness Warning Banner -->
        <div v-if="plan.completeness_warning" class="border-b border-gray-200">
          <div
            :class="getCompletenessWarningClass(plan.completeness_warning.severity)"
            class="p-6"
          >
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6" :class="getWarningIconColour(plan.completeness_warning.severity)" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-4 flex-1">
                <h4 class="text-base font-semibold mb-2" :class="getWarningTitleColour(plan.completeness_warning.severity)">
                  Plan Completeness: {{ plan.completeness_warning.score }}%
                </h4>
                <p class="text-sm mb-4" :class="getWarningTextColour(plan.completeness_warning.severity)">
                  {{ plan.completeness_warning.disclaimer }}
                </p>

                <!-- Missing Fields -->
                <div v-if="plan.completeness_warning.missing_fields && plan.completeness_warning.missing_fields.length > 0" class="mt-4">
                  <p class="text-sm font-medium mb-2" :class="getWarningTextColour(plan.completeness_warning.severity)">
                    To improve this plan, please complete:
                  </p>
                  <ul class="list-disc list-inside space-y-1">
                    <li
                      v-for="field in plan.completeness_warning.missing_fields"
                      :key="field.field"
                      class="text-sm"
                      :class="getWarningTextColour(plan.completeness_warning.severity)"
                    >
                      {{ field.message }}
                    </li>
                  </ul>
                  <router-link
                    to="/profile"
                    class="inline-block mt-4 px-4 py-2 text-sm font-medium rounded-md transition-colors"
                    :class="getCompleteProfileButtonClass(plan.completeness_warning.severity)"
                  >
                    Complete Your Profile →
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="p-8">
          <!-- Executive Summary -->
          <section class="mb-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Executive Summary</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <!-- Adequacy Score Gauge -->
              <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-6 border border-blue-200">
                <p class="text-sm text-blue-600 font-medium mb-3">Overall Protection Adequacy</p>
                <div class="flex items-center justify-center mb-4">
                  <div class="text-center">
                    <p class="text-5xl font-bold" :class="getScoreColourClass(plan.executive_summary.adequacy_score.overall)">
                      {{ plan.executive_summary.adequacy_score.overall }}
                    </p>
                    <p class="text-sm text-gray-600 mt-1">out of 100</p>
                    <p class="text-lg font-semibold mt-2" :class="getScoreColourClass(plan.executive_summary.adequacy_score.overall)">
                      {{ plan.executive_summary.adequacy_score.rating }}
                    </p>
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs">
                  <div class="text-center p-2 bg-white rounded">
                    <p class="text-gray-600">Life</p>
                    <p class="font-bold" :class="getScoreColourClass(plan.executive_summary.adequacy_score.life)">
                      {{ plan.executive_summary.adequacy_score.life }}
                    </p>
                  </div>
                  <div class="text-center p-2 bg-white rounded">
                    <p class="text-gray-600">CI</p>
                    <p class="font-bold" :class="getScoreColourClass(plan.executive_summary.adequacy_score.critical_illness)">
                      {{ plan.executive_summary.adequacy_score.critical_illness }}
                    </p>
                  </div>
                  <div class="text-center p-2 bg-white rounded">
                    <p class="text-gray-600">IP</p>
                    <p class="font-bold" :class="getScoreColourClass(plan.executive_summary.adequacy_score.income_protection)">
                      {{ plan.executive_summary.adequacy_score.income_protection }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Critical Gaps -->
              <div class="space-y-4">
                <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                  <p class="text-sm text-red-600 font-medium mb-2">Critical Protection Gaps</p>
                  <ul v-if="plan.executive_summary.critical_gaps.length > 0" class="space-y-2">
                    <li v-for="(gap, index) in plan.executive_summary.critical_gaps" :key="index" class="text-sm text-red-800 flex items-start">
                      <svg class="w-4 h-4 text-red-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                      {{ gap }}
                    </li>
                  </ul>
                  <p v-else class="text-sm text-green-700">No critical gaps identified</p>
                </div>

                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                  <p class="text-xs text-blue-600 mb-1">Recommended Action</p>
                  <p class="text-sm text-blue-900">{{ plan.executive_summary.recommended_action }}</p>
                </div>
              </div>
            </div>
          </section>

          <!-- User Profile -->
          <section class="mb-12 pdf-page-break">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Your Profile</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-3">
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Name:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.name }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Age:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.age }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Occupation:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.occupation }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Education Level:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.education_level }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Retirement Age:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.retirement_age }}</span>
                </div>
              </div>

              <div class="space-y-3">
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Marital Status:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.marital_status }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Number of Dependents:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.number_of_dependents }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Smoker Status:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.smoker_status }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                  <span class="text-gray-600">Health Status:</span>
                  <span class="font-semibold text-gray-900">{{ plan.user_profile.health_status }}</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Financial Summary -->
          <section class="mb-12 pdf-page-break">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Financial Summary</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-xs text-green-600 mb-1">Annual Income</p>
                <p class="text-2xl font-bold text-green-900">{{ formatCurrency(plan.financial_summary.annual_income) }}</p>
              </div>
              <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                <p class="text-xs text-amber-600 mb-1">Annual Expenditure</p>
                <p class="text-2xl font-bold text-amber-900">{{ formatCurrency(plan.financial_summary.annual_expenditure) }}</p>
              </div>
              <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                <p class="text-xs text-red-600 mb-1">Total Debt</p>
                <p class="text-2xl font-bold text-red-900">{{ formatCurrency(plan.financial_summary.total_debt) }}</p>
              </div>
            </div>

            <div v-if="plan.financial_summary.debt_breakdown.total > 0" class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 mb-3">Debt Breakdown</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-700">Mortgage:</span>
                  <span class="font-semibold text-gray-900">{{ formatCurrency(plan.financial_summary.debt_breakdown.mortgage) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-700">Other Debts:</span>
                  <span class="font-semibold text-gray-900">{{ formatCurrency(plan.financial_summary.debt_breakdown.other) }}</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Current Coverage -->
          <section class="mb-12 pdf-page-break">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Current Protection Coverage</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <p class="text-sm text-blue-600 mb-1">Life Insurance</p>
                <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(plan.current_coverage.life_insurance.total_coverage) }}</p>
                <p class="text-xs text-blue-600 mt-1">{{ plan.current_coverage.life_insurance.policy_count }} {{ plan.current_coverage.life_insurance.policy_count === 1 ? 'policy' : 'policies' }}</p>
              </div>
              <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <p class="text-sm text-purple-600 mb-1">Critical Illness</p>
                <p class="text-2xl font-bold text-purple-900">{{ formatCurrency(plan.current_coverage.critical_illness.total_coverage) }}</p>
                <p class="text-xs text-purple-600 mt-1">{{ plan.current_coverage.critical_illness.policy_count }} {{ plan.current_coverage.critical_illness.policy_count === 1 ? 'policy' : 'policies' }}</p>
              </div>
              <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
                <p class="text-sm text-indigo-600 mb-1">Income Protection</p>
                <p class="text-2xl font-bold text-indigo-900">{{ formatCurrency(plan.current_coverage.income_protection.monthly_benefit) }}/mo</p>
                <p class="text-xs text-indigo-600 mt-1">{{ plan.current_coverage.income_protection.policy_count }} {{ plan.current_coverage.income_protection.policy_count === 1 ? 'policy' : 'policies' }}</p>
              </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-4">
              <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-gray-700">Total Monthly Premiums:</span>
                <span class="text-lg font-bold text-gray-900">{{ formatCurrency(plan.current_coverage.total_monthly_premiums) }}/month</span>
              </div>
              <div class="flex justify-between items-center mt-2 pt-2 border-t">
                <span class="text-sm font-medium text-gray-700">Total Annual Premiums:</span>
                <span class="text-lg font-bold text-gray-900">{{ formatCurrency(plan.current_coverage.total_annual_premiums) }}/year</span>
              </div>
            </div>

            <!-- Policy Details -->
            <div class="space-y-4">
              <!-- Life Insurance Policies -->
              <div v-if="plan.current_coverage.life_insurance.policies.length > 0">
                <h4 class="font-semibold text-gray-900 mb-2">Life Insurance Policies</h4>
                <div class="border border-gray-200 rounded overflow-hidden">
                  <div v-for="(policy, index) in plan.current_coverage.life_insurance.policies" :key="index" class="flex justify-between p-3 border-b last:border-b-0 hover:bg-gray-50">
                    <div>
                      <p class="font-medium text-gray-900">{{ policy.provider }}</p>
                      <p class="text-sm text-gray-600">{{ policy.type }}</p>
                    </div>
                    <div class="text-right">
                      <p class="font-semibold text-gray-900">{{ formatCurrency(policy.sum_assured) }}</p>
                      <p class="text-sm text-gray-600">{{ formatCurrency(policy.annual_premium) }}/year</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Critical Illness Policies -->
              <div v-if="plan.current_coverage.critical_illness.policies.length > 0">
                <h4 class="font-semibold text-gray-900 mb-2">Critical Illness Policies</h4>
                <div class="border border-gray-200 rounded overflow-hidden">
                  <div v-for="(policy, index) in plan.current_coverage.critical_illness.policies" :key="index" class="flex justify-between p-3 border-b last:border-b-0 hover:bg-gray-50">
                    <div>
                      <p class="font-medium text-gray-900">{{ policy.provider }}</p>
                      <p class="text-sm text-gray-600">{{ policy.type }}</p>
                    </div>
                    <div class="text-right">
                      <p class="font-semibold text-gray-900">{{ formatCurrency(policy.sum_assured) }}</p>
                      <p class="text-sm text-gray-600">{{ formatCurrency(policy.annual_premium) }}/year</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Income Protection Policies -->
              <div v-if="plan.current_coverage.income_protection.policies.length > 0">
                <h4 class="font-semibold text-gray-900 mb-2">Income Protection Policies</h4>
                <div class="border border-gray-200 rounded overflow-hidden">
                  <div v-for="(policy, index) in plan.current_coverage.income_protection.policies" :key="index" class="flex justify-between p-3 border-b last:border-b-0 hover:bg-gray-50">
                    <div>
                      <p class="font-medium text-gray-900">{{ policy.provider }}</p>
                      <p class="text-sm text-gray-600">Benefit Period: {{ policy.benefit_period }}</p>
                    </div>
                    <div class="text-right">
                      <p class="font-semibold text-gray-900">{{ formatCurrency(policy.benefit_amount) }}/month</p>
                      <p class="text-sm text-gray-600">{{ formatCurrency(policy.annual_premium) }}/year</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Coverage Analysis -->
          <section class="mb-12 pdf-page-break">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Coverage Gap Analysis</h3>

            <div class="space-y-6">
              <!-- Life Insurance Analysis -->
              <div class="border rounded-lg overflow-hidden" :class="getCoverageBorderClass(plan.coverage_analysis.life_insurance.score)">
                <div class="p-4" :class="getCoverageHeaderClass(plan.coverage_analysis.life_insurance.score)">
                  <div class="flex justify-between items-center">
                    <h4 class="font-bold text-gray-900">Life Insurance</h4>
                    <span class="px-3 py-1 rounded-full text-sm font-bold" :class="getCoverageStatusClass(plan.coverage_analysis.life_insurance.status)">
                      {{ plan.coverage_analysis.life_insurance.status }}
                    </span>
                  </div>
                </div>
                <div class="p-4 bg-white">
                  <div class="grid grid-cols-3 gap-4 text-center mb-4">
                    <div>
                      <p class="text-xs text-gray-600">Need</p>
                      <p class="text-lg font-bold text-gray-900">{{ formatCurrency(plan.coverage_analysis.life_insurance.need) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Current Coverage</p>
                      <p class="text-lg font-bold text-blue-600">{{ formatCurrency(plan.coverage_analysis.life_insurance.coverage) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Gap</p>
                      <p class="text-lg font-bold" :class="plan.coverage_analysis.life_insurance.gap > 0 ? 'text-red-600' : 'text-green-600'">
                        {{ formatCurrency(plan.coverage_analysis.life_insurance.gap) }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center justify-between">
                    <div class="flex-1 bg-gray-200 rounded-full h-4 mr-4">
                      <div class="bg-blue-600 h-4 rounded-full" :style="{ width: plan.coverage_analysis.life_insurance.coverage_percentage + '%' }"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700">{{ plan.coverage_analysis.life_insurance.coverage_percentage }}%</span>
                  </div>
                </div>
              </div>

              <!-- Critical Illness Analysis -->
              <div class="border rounded-lg overflow-hidden" :class="getCoverageBorderClass(plan.coverage_analysis.critical_illness.score)">
                <div class="p-4" :class="getCoverageHeaderClass(plan.coverage_analysis.critical_illness.score)">
                  <div class="flex justify-between items-center">
                    <h4 class="font-bold text-gray-900">Critical Illness</h4>
                    <span class="px-3 py-1 rounded-full text-sm font-bold" :class="getCoverageStatusClass(plan.coverage_analysis.critical_illness.status)">
                      {{ plan.coverage_analysis.critical_illness.status }}
                    </span>
                  </div>
                </div>
                <div class="p-4 bg-white">
                  <div class="grid grid-cols-3 gap-4 text-center mb-4">
                    <div>
                      <p class="text-xs text-gray-600">Need</p>
                      <p class="text-lg font-bold text-gray-900">{{ formatCurrency(plan.coverage_analysis.critical_illness.need) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Current Coverage</p>
                      <p class="text-lg font-bold text-purple-600">{{ formatCurrency(plan.coverage_analysis.critical_illness.coverage) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Gap</p>
                      <p class="text-lg font-bold" :class="plan.coverage_analysis.critical_illness.gap > 0 ? 'text-red-600' : 'text-green-600'">
                        {{ formatCurrency(plan.coverage_analysis.critical_illness.gap) }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center justify-between">
                    <div class="flex-1 bg-gray-200 rounded-full h-4 mr-4">
                      <div class="bg-purple-600 h-4 rounded-full" :style="{ width: plan.coverage_analysis.critical_illness.coverage_percentage + '%' }"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700">{{ plan.coverage_analysis.critical_illness.coverage_percentage }}%</span>
                  </div>
                </div>
              </div>

              <!-- Income Protection Analysis -->
              <div class="border rounded-lg overflow-hidden" :class="getCoverageBorderClass(plan.coverage_analysis.income_protection.score)">
                <div class="p-4" :class="getCoverageHeaderClass(plan.coverage_analysis.income_protection.score)">
                  <div class="flex justify-between items-center">
                    <h4 class="font-bold text-gray-900">Income Protection</h4>
                    <span class="px-3 py-1 rounded-full text-sm font-bold" :class="getCoverageStatusClass(plan.coverage_analysis.income_protection.status)">
                      {{ plan.coverage_analysis.income_protection.status }}
                    </span>
                  </div>
                </div>
                <div class="p-4 bg-white">
                  <div class="grid grid-cols-3 gap-4 text-center mb-4">
                    <div>
                      <p class="text-xs text-gray-600">Monthly Need</p>
                      <p class="text-lg font-bold text-gray-900">{{ formatCurrency(plan.coverage_analysis.income_protection.need) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Current Coverage</p>
                      <p class="text-lg font-bold text-indigo-600">{{ formatCurrency(plan.coverage_analysis.income_protection.coverage) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-600">Gap</p>
                      <p class="text-lg font-bold" :class="plan.coverage_analysis.income_protection.gap > 0 ? 'text-red-600' : 'text-green-600'">
                        {{ formatCurrency(plan.coverage_analysis.income_protection.gap) }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center justify-between">
                    <div class="flex-1 bg-gray-200 rounded-full h-4 mr-4">
                      <div class="bg-indigo-600 h-4 rounded-full" :style="{ width: plan.coverage_analysis.income_protection.coverage_percentage + '%' }"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700">{{ plan.coverage_analysis.income_protection.coverage_percentage }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Optimised Strategy -->
          <section class="mb-12 pdf-page-break">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Optimised Protection Strategy</h3>

            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-6 border border-blue-200">
              <h4 class="text-lg font-bold text-gray-900 mb-4">{{ plan.optimized_strategy.strategy_name }}</h4>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Total Coverage Increase</p>
                  <p class="text-xl font-bold text-blue-600">{{ formatCurrency(plan.optimized_strategy.summary.total_coverage_increase) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Est. Monthly Cost</p>
                  <p class="text-xl font-bold text-amber-600">{{ formatCurrency(plan.optimized_strategy.summary.total_estimated_monthly_cost) }}</p>
                </div>
                <div class="bg-white rounded-lg p-4">
                  <p class="text-xs text-gray-600 mb-1">Est. Annual Cost</p>
                  <p class="text-xl font-bold text-purple-600">{{ formatCurrency(plan.optimized_strategy.summary.total_estimated_annual_cost) }}</p>
                </div>
              </div>
            </div>

            <!-- Strategy Recommendations -->
            <div class="space-y-4">
              <div
                v-for="(rec, index) in plan.optimized_strategy.recommendations"
                :key="index"
                class="border rounded-lg overflow-hidden"
                :class="getPriorityBorderClass(rec.priority)"
              >
                <div class="px-6 py-4" :class="getPriorityHeaderClass(rec.priority)">
                  <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-white" :class="getPriorityTextClass(rec.priority)">
                      Priority {{ rec.priority }}
                    </span>
                    <h5 class="text-lg font-bold text-gray-900">{{ rec.category }}</h5>
                    <span class="ml-auto px-3 py-1 rounded-full text-xs font-bold" :class="getImportanceClass(rec.importance)">
                      {{ rec.importance }}
                    </span>
                  </div>
                </div>

                <div class="px-6 py-4 bg-white">
                  <h6 class="font-semibold text-gray-900 mb-2">{{ rec.action }}</h6>
                  <p class="text-sm text-gray-700 mb-4">{{ rec.details }}</p>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div v-if="rec.coverage_amount">
                      <span class="text-gray-600">Coverage Amount:</span>
                      <span class="font-semibold text-gray-900 ml-2">{{ formatCurrency(rec.coverage_amount) }}</span>
                    </div>
                    <div v-if="rec.monthly_benefit">
                      <span class="text-gray-600">Monthly Benefit:</span>
                      <span class="font-semibold text-gray-900 ml-2">{{ formatCurrency(rec.monthly_benefit) }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Est. Monthly Cost:</span>
                      <span class="font-semibold text-amber-600 ml-2">{{ formatCurrency(rec.estimated_monthly_cost) }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Timeframe:</span>
                      <span class="font-semibold text-blue-600 ml-2">{{ rec.timeframe }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Next Steps -->
          <section class="mb-12 pdf-page-break">
            <h3 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-blue-600">Next Steps</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div
                v-for="(steps, timeframe) in plan.next_steps"
                :key="timeframe"
                class="bg-gray-50 rounded-lg p-6"
              >
                <h4 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                  {{ timeframe }}
                </h4>
                <ul v-if="steps.length > 0" class="space-y-2">
                  <li v-for="(step, index) in steps" :key="index" class="text-sm text-gray-700 flex items-start gap-2">
                    <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    {{ step }}
                  </li>
                </ul>
                <p v-else class="text-sm text-gray-500 italic">No actions required in this timeframe</p>
              </div>
            </div>
          </section>

          <!-- Professional Disclaimer -->
          <section class="mb-8">
            <div class="bg-amber-50 border-l-4 border-amber-500 p-6 rounded">
              <div class="flex items-start">
                <svg class="h-6 w-6 text-amber-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h4 class="text-sm font-medium text-amber-800">Important: Professional Advice Required</h4>
                  <p class="mt-2 text-sm text-amber-700">
                    This protection plan is for educational and planning purposes only. It is not regulated financial advice.
                    Before purchasing any protection policies, you must consult with a qualified independent financial adviser (IFA) who can:
                  </p>
                  <ul class="mt-2 text-sm text-amber-700 list-disc list-inside space-y-1">
                    <li>Assess your individual circumstances and protection needs</li>
                    <li>Recommend suitable products from the whole market</li>
                    <li>Assist with medical underwriting and policy application</li>
                    <li>Ensure policies are written in trust where appropriate</li>
                  </ul>
                  <p class="mt-2 text-sm text-amber-700">
                    Premium estimates shown are indicative only and will vary based on individual underwriting. Actual quotes should be obtained from insurers via an IFA.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <!-- Footer -->
          <div class="text-center text-sm text-gray-500 pt-8 border-t">
            <p>Generated by Fynla (Financial Planning System) - Version {{ plan.plan_metadata.plan_version }}</p>
            <p class="mt-1">{{ plan.plan_metadata.generated_date }} at {{ plan.plan_metadata.generated_time }}</p>
            <p class="mt-2">🤖 Generated with <a href="https://claude.com/claude-code" class="text-blue-600 hover:underline" target="_blank">Claude Code</a></p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import PrintHeader from '@/components/Common/PrintHeader.vue';
import protectionService from '@/services/protectionService';
import html2pdf from 'html2pdf.js';
import logoImage from '@/assets/logo.png';

export default {
  name: 'ComprehensiveProtectionPlan',

  components: {
    AppLayout,
    PrintHeader,
  },

  data() {
    return {
      plan: null,
      loading: false,
      error: null,
      logoUrl: logoImage,
    };
  },

  mounted() {
    this.loadProtectionPlan();
  },

  methods: {
    async loadProtectionPlan() {
      this.loading = true;
      this.error = null;

      try {
        // Refresh user data first to ensure we have latest spouse linkage info
        await this.$store.dispatch('auth/fetchUser');

        const response = await protectionService.getComprehensiveProtectionPlan();
        if (response.success) {
          this.plan = response.data;
        } else {
          this.error = response.message || 'Failed to load protection plan';
        }
      } catch (error) {
        console.error('Failed to load protection plan:', error);
        this.error = error.response?.data?.message || 'An error occurred while generating your protection plan. Please ensure you have created a protection profile first.';
      } finally {
        this.loading = false;
      }
    },

    downloadPDF() {
      const element = document.getElementById('protection-plan-document');
      if (!element) return;

      // Show the PDF header before generating
      const pdfHeader = element.querySelector('.pdf-header');
      if (pdfHeader) pdfHeader.style.display = 'flex';

      const opt = {
        margin: [10, 10, 10, 10],
        filename: `Protection_Plan_${new Date().toISOString().split('T')[0]}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css'], before: '.pdf-page-break' },
      };

      html2pdf().set(opt).from(element).save().then(() => {
        // Hide the PDF header after generating
        if (pdfHeader) pdfHeader.style.display = 'none';
      });
    },

    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value || 0);
    },

    getScoreColourClass(score) {
      if (score >= 80) return 'text-green-600';
      if (score >= 60) return 'text-blue-600';
      if (score >= 40) return 'text-amber-600';
      return 'text-red-600';
    },

    getCoverageBorderClass(score) {
      if (score >= 80) return 'border-green-300';
      if (score >= 60) return 'border-blue-300';
      if (score >= 40) return 'border-amber-300';
      return 'border-red-300';
    },

    getCoverageHeaderClass(score) {
      if (score >= 80) return 'bg-green-50';
      if (score >= 60) return 'bg-blue-50';
      if (score >= 40) return 'bg-amber-50';
      return 'bg-red-50';
    },

    getPlanTypeBadgeClass(isComplete) {
      return isComplete
        ? 'bg-blue-100 text-blue-800'
        : 'bg-amber-100 text-amber-800';
    },

    getCompletenessWarningClass(severity) {
      const classes = {
        critical: 'bg-red-50 border-l-4 border-red-500',
        warning: 'bg-amber-50 border-l-4 border-amber-500',
        success: 'bg-green-50 border-l-4 border-green-500',
      };
      return classes[severity] || classes.warning;
    },

    getWarningIconColour(severity) {
      const colours = {
        critical: 'text-red-600',
        warning: 'text-amber-600',
        success: 'text-green-600',
      };
      return colours[severity] || colours.warning;
    },

    getWarningTitleColour(severity) {
      const colours = {
        critical: 'text-red-900',
        warning: 'text-amber-900',
        success: 'text-green-900',
      };
      return colours[severity] || colours.warning;
    },

    getWarningTextColour(severity) {
      const colours = {
        critical: 'text-red-700',
        warning: 'text-amber-700',
        success: 'text-green-700',
      };
      return colours[severity] || colours.warning;
    },

    getCompleteProfileButtonClass(severity) {
      const classes = {
        critical: 'bg-red-600 hover:bg-red-700 text-white',
        warning: 'bg-amber-600 hover:bg-amber-700 text-white',
        success: 'bg-green-600 hover:bg-green-700 text-white',
      };
      return classes[severity] || classes.warning;
    },

    getCoverageStatusClass(status) {
      if (status === 'Excellent') return 'bg-green-100 text-green-800';
      if (status === 'Good') return 'bg-blue-100 text-blue-800';
      if (status === 'Fair') return 'bg-amber-100 text-amber-800';
      return 'bg-red-100 text-red-800';
    },

    getPriorityBorderClass(priority) {
      if (priority === 1) return 'border-red-300';
      if (priority === 2) return 'border-orange-300';
      if (priority === 3) return 'border-yellow-300';
      return 'border-gray-300';
    },

    getPriorityHeaderClass(priority) {
      if (priority === 1) return 'bg-red-50';
      if (priority === 2) return 'bg-orange-50';
      if (priority === 3) return 'bg-yellow-50';
      return 'bg-gray-50';
    },

    getPriorityTextClass(priority) {
      if (priority === 1) return 'text-red-700';
      if (priority === 2) return 'text-orange-700';
      if (priority === 3) return 'text-yellow-700';
      return 'text-gray-700';
    },

    getImportanceClass(importance) {
      if (importance === 'Critical') return 'bg-red-100 text-red-800';
      if (importance === 'High') return 'bg-orange-100 text-orange-800';
      if (importance === 'Medium') return 'bg-yellow-100 text-yellow-800';
      return 'bg-gray-100 text-gray-800';
    },
  },
};
</script>

<style scoped>
/* Print styles moved to global app.css */
</style>
