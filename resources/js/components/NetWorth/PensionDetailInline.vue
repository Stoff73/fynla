<template>
  <div class="pension-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      <p class="mt-4 text-neutral-500">Loading pension details...</p>
    </div>

    <!-- Pension Content -->
    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', badgeClass]">{{ pensionTypeLabel }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ pensionName }}</h1>
            <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ providerName }}</p>
          </div>
          <div class="flex space-x-2 w-full sm:w-auto">
            <button
              v-preview-disabled="'edit'"
              @click="showEditModal = true"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
            >
              Edit
            </button>
            <button
              v-if="pensionType !== 'state'"
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-violet-600 text-violet-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- DC Pension Details -->
              <template v-if="pensionType === 'dc'">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Pension Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Scheme Name:</dt>
                      <dd class="text-sm font-medium text-horizon-500 text-right">{{ pension.scheme_name || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Provider:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.provider || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Pension Type:</dt>
                      <dd class="text-sm font-medium text-horizon-500 capitalize">{{ pension.pension_type || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Policy Number:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.member_number || pension.policy_number || 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Fund Value</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Current Fund Value:</dt>
                      <dd class="text-sm font-semibold text-violet-600">{{ formatCurrency(pension.current_fund_value) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Valuation Date:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatDate(pension.valuation_date) || 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Contributions</h3>
                  <dl class="space-y-2">
                    <div v-if="pension.employee_contribution_percent" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employee Rate:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employee_contribution_percent }}% ({{ formatCurrency(monthlyEmployeeContribution) }}/mo)</dd>
                    </div>
                    <div v-if="pension.employer_contribution_percent" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employer Rate:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employer_contribution_percent }}% ({{ formatCurrency(monthlyEmployerContribution) }}/mo)</dd>
                    </div>
                    <div v-if="pension.employer_matching_limit" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employer Matching:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employer_matching_limit == 100 ? 'Full matching' : 'Up to ' + pension.employer_matching_limit + '%' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Total Monthly:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(totalMonthlyContribution) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Annual Contribution:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(annualContribution) }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Retirement</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Retirement Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500" data-testid="pension-retirement-age">{{ pensionRetirementAge }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Growth Rate Assumption:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.growth_rate ? (pension.growth_rate * 100).toFixed(1) + '%' : 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Beneficiary:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.beneficiary_name || 'Not specified' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Fees</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Platform Fee:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ platformFeeDisplay }}</dd>
                    </div>
                    <div v-if="advisorFeePercent > 0" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Advisor Fee:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ advisorFeePercent.toFixed(2) }}% p.a.</dd>
                    </div>
                    <div v-if="hasHoldings" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Average Fund Charge:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ weightedAverageOCF.toFixed(2) }}%</dd>
                    </div>
                    <!--
                      Totals only once SOMETHING is recorded. A pension with no
                      platform fee on record and no holdings reported "Total
                      Annual Cost 0.00%" and "Annual Fee Impact £0/year" — which
                      reads as a pension that costs nothing to run, when what is
                      true is that nobody has said what it costs. The `?? null`
                      versus `|| 0` distinction in `app/Http/CLAUDE.md`: a
                      zero-default collapses "not recorded" and "zero" into one
                      figure and the reader cannot tell them apart.
                    -->
                    <template v-if="hasRecordedFees">
                      <div class="flex justify-between border-t border-light-gray pt-2 mt-2">
                        <dt class="text-sm text-neutral-500 font-medium">Total Annual Cost:</dt>
                        <dd class="text-sm font-semibold text-horizon-500">{{ totalFeePercent.toFixed(2) }}%</dd>
                      </div>
                      <div class="flex justify-between">
                        <dt class="text-sm text-neutral-500">Annual Fee Impact:</dt>
                        <dd class="text-sm font-medium text-raspberry-600">{{ formatCurrency(annualFeeCost) }}/year</dd>
                      </div>
                    </template>
                    <p v-else class="text-sm text-neutral-500 border-t border-light-gray pt-2 mt-2">
                      No charges recorded for this pension yet. Add its platform fee, or the
                      funds it holds, to see what it costs to run.
                    </p>
                  </dl>
                </div>
              </template>

              <!-- DB Pension Details -->
              <template v-else-if="pensionType === 'db'">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Scheme Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Scheme Name:</dt>
                      <dd class="text-sm font-medium text-horizon-500 text-right">{{ pension.scheme_name || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employer:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employer || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Scheme Type:</dt>
                      <dd class="text-sm font-medium text-horizon-500 capitalize">{{ formatDBSchemeType(pension.scheme_type) }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Benefits</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Annual Pension:</dt>
                      <dd class="text-sm font-semibold text-violet-600">{{ formatCurrency(pension.accrued_annual_pension) }}/yr</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Lump Sum Entitlement:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Revaluation Rate:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.revaluation_rate ? (pension.revaluation_rate * 100).toFixed(1) + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Payment Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Normal Retirement Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.normal_retirement_age || DEFAULT_DB_NORMAL_RETIREMENT_AGE }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Payment Start Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.payment_start_age || pension.normal_retirement_age || DEFAULT_DB_NORMAL_RETIREMENT_AGE }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Spouse Pension:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.spouse_pension_percentage ? pension.spouse_pension_percentage + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Service Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Date Joined:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatDate(pension.date_joined) || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Date Left:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatDate(pension.date_left) || 'Current' }}</dd>
                    </div>
                  </dl>
                </div>
              </template>

              <!-- State Pension Details -->
              <template v-else-if="pensionType === 'state'">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">State Pension Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Forecast Annual Amount:</dt>
                      <dd class="text-sm font-semibold text-spring-600">{{ formatCurrency(pension.state_pension_forecast_annual || 0) }}/yr</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Weekly Amount:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency((pension.state_pension_forecast_annual || 0) / 52) }}/wk</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">National Insurance Record</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">National Insurance Years Completed:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.ni_years_completed || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Years to Full Pension:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ Math.max(0, 35 - (pension.ni_years_completed || 0)) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">State Pension Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.state_pension_age || 67 }}</dd>
                    </div>
                  </dl>
                </div>
              </template>
            </div>

            <!-- Notes -->
            <div v-if="pension.notes" class="mt-6">
              <h3 class="text-lg font-semibold text-horizon-500 mb-3">Notes</h3>
              <p class="text-neutral-500 whitespace-pre-wrap">{{ pension.notes }}</p>
            </div>
          </div>

          <!-- Holdings Tab (DC pensions) -->
          <div v-show="activeTab === 'holdings'" class="space-y-4">
            <div class="flex justify-between items-center">
              <p class="text-sm text-neutral-500">
                The funds held inside this pension.
              </p>
              <button
                v-preview-disabled="'edit'"
                data-testid="pension-add-holding"
                @click="openAddHolding"
                class="px-4 py-2 bg-raspberry-500 text-white rounded-button text-sm font-medium hover:bg-raspberry-600 transition-colors"
              >
                Add Holding
              </button>
            </div>

            <p v-if="holdingsError" class="text-sm text-raspberry-600" role="alert">{{ holdingsError }}</p>

            <div v-if="holdings.length === 0" class="text-center py-10 border border-light-gray rounded-lg">
              <p class="text-base font-medium text-horizon-500 mb-1">No holdings recorded</p>
              <p class="text-sm text-neutral-500">
                Add the funds this pension is invested in to see its fund charges and their effect over time.
              </p>
            </div>

            <!--
              Units Held, Purchase Price, Current Price and Purchase Date are
              captured, validated and stored, and this table displayed none of
              them (W-0442). W-0039 made them enterable; nothing made them
              visible, so a user could type a unit count and never see it again.
            -->
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-light-gray">
                    <th class="text-left py-2 text-neutral-500 font-medium">Fund Name</th>
                    <th class="text-left py-2 text-neutral-500 font-medium">Type</th>
                    <th class="text-right py-2 text-neutral-500 font-medium whitespace-nowrap">Units Held</th>
                    <th class="text-right py-2 text-neutral-500 font-medium whitespace-nowrap">Purchase Price</th>
                    <th class="text-right py-2 text-neutral-500 font-medium whitespace-nowrap">Current Price</th>
                    <th class="text-left py-2 text-neutral-500 font-medium whitespace-nowrap">Purchase Date</th>
                    <th class="text-right py-2 text-neutral-500 font-medium">Allocation</th>
                    <th class="text-right py-2 text-neutral-500 font-medium">Value</th>
                    <th class="text-right py-2 text-neutral-500 font-medium whitespace-nowrap">Ongoing Charge Figure</th>
                    <th class="text-right py-2 text-neutral-500 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="holding in holdings" :key="holding.id" class="border-b border-light-gray last:border-0" :data-testid="`pension-holding-${holding.id}`">
                    <td class="py-2 text-horizon-500 font-medium">{{ holding.security_name || 'Unnamed' }}</td>
                    <td class="py-2 text-neutral-500 capitalize">{{ formatAssetType(holding.asset_type) }}</td>
                    <td class="py-2 text-right text-horizon-500" data-testid="holding-units">{{ formatUnits(holding.quantity) }}</td>
                    <td class="py-2 text-right text-horizon-500">{{ holding.purchase_price ? formatCurrencyWithPence(holding.purchase_price) : '—' }}</td>
                    <td class="py-2 text-right text-horizon-500">{{ holding.current_price ? formatCurrencyWithPence(holding.current_price) : '—' }}</td>
                    <td class="py-2 text-neutral-500 whitespace-nowrap">{{ formatDate(holding.purchase_date) || '—' }}</td>
                    <td class="py-2 text-right text-horizon-500">{{ holding.allocation_percent || 0 }}%</td>
                    <td class="py-2 text-right text-horizon-500">{{ formatCurrency(holdingValue(holding)) }}</td>
                    <td class="py-2 text-right text-neutral-500">{{ holding.ocf_percent ? parseFloat(holding.ocf_percent).toFixed(2) + '%' : '—' }}</td>
                    <td class="py-2 text-right whitespace-nowrap">
                      <button
                        v-preview-disabled="'edit'"
                        @click="openEditHolding(holding)"
                        class="text-violet-600 hover:text-violet-800 font-medium"
                      >
                        Edit
                      </button>
                      <button
                        v-preview-disabled="'delete'"
                        @click="confirmDeleteHolding(holding)"
                        class="ml-3 text-raspberry-600 hover:text-raspberry-800 font-medium"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="holdingsCashPercent > 0">
                  <tr class="border-t border-light-gray">
                    <td class="py-2 text-neutral-500 italic">Cash (unallocated)</td>
                    <td colspan="5"></td>
                    <td class="py-2 text-right text-neutral-500">{{ holdingsCashPercent.toFixed(1) }}%</td>
                    <td class="py-2 text-right text-neutral-500">{{ formatCurrency(holdingsCashValue) }}</td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Fee summary tied to holdings. Hidden with no holdings, because
                 with none the only figures it can show are zeros, and a fund
                 charge of "0.00%" is a claim rather than an absence. -->
            <div v-if="holdings.length > 0" class="bg-savannah-100 rounded-lg p-4">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Weighted Average Fund Charge</span>
                <span class="font-medium text-horizon-500">{{ weightedAverageOCF.toFixed(2) }}%</span>
              </div>
              <div class="flex justify-between text-sm mt-1">
                <span class="text-neutral-500">Total Annual Cost (platform + advisor + fund fees)</span>
                <span class="font-semibold text-horizon-500">{{ totalFeePercent.toFixed(2) }}%</span>
              </div>
            </div>

            <!-- 10-Year Fee Impact -->
            <div v-if="annualFeeCost > 0" class="bg-white border border-light-gray rounded-lg p-4">
              <h4 class="text-sm font-semibold text-horizon-500 mb-3">10-Year Fee Impact</h4>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <p class="text-xs text-neutral-500">Cumulative Fees Paid</p>
                  <p class="text-base font-semibold text-raspberry-600">{{ formatCurrency(feeImpact10yr.totalFees) }}</p>
                </div>
                <div>
                  <p class="text-xs text-neutral-500">Lost Growth (Fee Drag)</p>
                  <p class="text-base font-semibold text-raspberry-600">{{ formatCurrency(feeImpact10yr.lostGrowth) }}</p>
                </div>
                <div>
                  <p class="text-xs text-neutral-500">Total Impact</p>
                  <p class="text-base font-semibold text-horizon-500">{{ formatCurrency(feeImpact10yr.totalImpact) }}</p>
                </div>
              </div>
              <p class="text-xs text-neutral-500 mt-2">
                Assuming {{ pension.growth_rate ? (pension.growth_rate * 100).toFixed(1) + '%' : '5%' }} growth rate and current contribution levels.
              </p>
            </div>
          </div>

          <!-- Projections Tab (DC pensions only) -->
          <div v-show="activeTab === 'projections'" class="projections-tab">
            <div v-if="projectionLoading" class="text-center py-12">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
              <p class="mt-4 text-neutral-500">Loading projections...</p>
            </div>
            <div v-else-if="projectionData">
              <!-- Summary Cards -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Current Value</p>
                  <p class="text-xl font-bold text-violet-600">{{ formatCurrency(projectionData.current_value) }}</p>
                </div>
                <div class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">80% Probability at Retirement</p>
                  <p class="text-xl font-bold text-spring-600">{{ formatCurrency(projectionData.percentile_20_at_retirement) }}</p>
                </div>
              </div>

              <!-- Monte Carlo Chart -->
              <div class="bg-white rounded-lg border border-light-gray p-4">
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Projected Pension Pot Growth</h3>
                <PensionPotProjectionChart :data="projectionData" />
              </div>

              <!-- Assumptions -->
              <div class="mt-4 text-sm text-neutral-500">
                <p>Based on {{ projectionData.years_to_retirement }} years to retirement age {{ projectionData.retirement_age }},
                {{ projectionData.risk_level }} risk profile ({{ projectionData.expected_return }}% expected return),
                and {{ formatCurrency(projectionData.monthly_contribution) }}/month contributions.</p>
              </div>
            </div>
            <div v-else class="text-center py-12 text-neutral-500">
              <p>Unable to load projection data</p>
            </div>
          </div>

          <!-- Documents Tab (placeholder) -->
          <div v-show="activeTab === 'documents'" class="text-center py-12 text-neutral-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <p class="text-lg font-medium">Documents Coming Soon</p>
            <p class="text-sm">Upload and manage pension documents in a future update.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <UnifiedPensionForm
      v-if="showEditModal"
      :pension="pension"
      :state-pension="pensionType === 'state' ? pension : null"
      :is-edit="true"
      :initial-pension-type="pensionType"
      @close="showEditModal = false"
      @save="handleUpdate"
    />

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Pension"
      message="Are you sure you want to delete this pension? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />

    <!--
      The SAME holding form the investment accounts use, given a pension as its
      owner instead of an account (Rule 20). A pension-shaped copy of it would be
      a second place to add a units input to, and W-0039 has already shown what
      happens when a holding field has more than one home — or none.
    -->
    <HoldingForm
      v-if="showHoldingModal"
      :show="showHoldingModal"
      :holding="editingHolding"
      :accounts="[]"
      :save-error="holdingSaveError"
      :owner="holdingOwner"
      @close="closeHoldingModal"
      @save="handleHoldingSave"
    />

    <ConfirmDialog
      :show="!!holdingToDelete"
      title="Delete Holding"
      :message="`Are you sure you want to delete ${holdingToDelete ? holdingToDelete.security_name : 'this holding'}? This action cannot be undone.`"
      @confirm="handleHoldingDelete"
      @cancel="holdingToDelete = null"
    />
  </div>
</template>

<script>
import { DEFAULT_DB_NORMAL_RETIREMENT_AGE } from '@/constants/retirementAge';
import { mapActions, mapState } from 'vuex';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';
import HoldingForm from '@/components/Investment/HoldingForm.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import retirementService from '@/services/retirementService';
import dcPensionHoldingsService from '@/services/dcPensionHoldingsService';
import { formatUnits } from '@/utils/holdingUnits';

import logger from '@/utils/logger';
export default {
  name: 'PensionDetailInline',
  mixins: [currencyMixin],

  components: {
    UnifiedPensionForm,
    ConfirmDialog,
    PensionPotProjectionChart,
    HoldingForm,
  },

  props: {
    pension: {
      type: Object,
      required: true,
    },
    pensionType: {
      type: String,
      required: true,
      validator: (value) => ['dc', 'db', 'state'].includes(value),
    },
  },

  emits: ['back', 'deleted', 'pension-updated'],

  data() {
    return {
      activeTab: 'overview',
      loading: false,
      showEditModal: false,
      showDeleteConfirm: false,
      projectionData: null,
      projectionLoading: false,
      // Holdings written through this panel go straight to the pension holdings
      // endpoints, so the `pension` prop the parent handed down goes stale the
      // moment one lands. Null means "nothing written yet, the prop is still
      // the truth"; an array means this panel now owns the list.
      localHoldings: null,
      showHoldingModal: false,
      editingHolding: null,
      holdingToDelete: null,
      holdingSaveError: null,
      holdingsError: null,
    };
  },

  computed: {
    // W-0196 — exposed so the template reads the one home rather than a literal.
    DEFAULT_DB_NORMAL_RETIREMENT_AGE: () => DEFAULT_DB_NORMAL_RETIREMENT_AGE,

    ...mapState('auth', ['user']),

    /**
     * THIS pension's retirement age, which is what the label beside it claims.
     *
     * It read `this.user?.target_retirement_age || 67` — two faults in one line.
     * It rendered the USER's household target under a label a reader takes as the
     * pension's, and where the store carried no target it fell back to a hardcoded
     * 67. `dc_pensions.retirement_age` is captured by the pension's own form
     * (`DCPensionForm.vue:291-314`), validated 55-75, and was never read here.
     *
     * `/m` already reads the pension's own value (`RetirementPensionDetail.vue`,
     * "Retirement age" row) and shows an em dash when it has none. Web now says
     * the same thing from the same field rather than inventing a number.
     *
     * **The live data cannot tell these apart** — David's `users.target_retirement_age`
     * and his SIPP's `retirement_age` are both 60. Three mutually distinct values
     * are needed to discriminate; see `PensionDetailRetirementAge.test.js`.
     */
    pensionRetirementAge() {
      return this.pension.retirement_age || '—';
    },

    tabs() {
      const baseTabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'documents', label: 'Documents' },
      ];
      if (this.pensionType === 'dc') {
        // Holdings is NOT gated on already having holdings. It was, and that was
        // the whole of "a pension's holdings cannot be entered" (W-0441): no
        // holdings meant no tab, no tab meant no way to add one, and no way to
        // add one meant no holdings. The endpoints, the service and the form all
        // existed the entire time — only the way in was missing.
        baseTabs.splice(1, 0, { id: 'holdings', label: 'Holdings' });
        baseTabs.splice(2, 0, { id: 'projections', label: 'Projections' });
      }
      return baseTabs;
    },

    pensionName() {
      if (this.pensionType === 'dc') {
        return this.pension.scheme_name || 'Defined Contribution Pension';
      } else if (this.pensionType === 'db') {
        return this.pension.scheme_name || 'Defined Benefit Pension';
      }
      return 'UK State Pension';
    },

    providerName() {
      if (this.pensionType === 'dc') {
        return this.pension.provider || '';
      } else if (this.pensionType === 'db') {
        return this.pension.employer || '';
      }
      return 'State Retirement Pension';
    },

    pensionTypeLabel() {
      if (this.pensionType === 'dc') {
        return this.formatDCPensionType(this.pension.pension_type);
      } else if (this.pensionType === 'db') {
        return this.formatDBSchemeType(this.pension.scheme_type);
      }
      return 'State Pension';
    },

    badgeClass() {
      const classes = {
        dc: 'badge-dc',
        db: 'badge-db',
        state: 'badge-state',
      };
      return classes[this.pensionType] || 'badge-dc';
    },

    // Calculate employee contribution from percentage or fixed amount
    monthlyEmployeeContribution() {
      if (this.pension.employee_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employee_contribution_percent / 100) / 12;
      }
      return this.pension.monthly_contribution_amount || 0;
    },

    // Calculate employer contribution from percentage
    monthlyEmployerContribution() {
      if (this.pension.employer_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employer_contribution_percent / 100) / 12;
      }
      return 0;
    },

    // Total monthly contribution (employee + employer)
    totalMonthlyContribution() {
      return this.monthlyEmployeeContribution + this.monthlyEmployerContribution;
    },

    // Annual contribution
    annualContribution() {
      return this.totalMonthlyContribution * 12;
    },

    // Platform fee as annualised percentage
    platformFeePercent() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      if (this.pension.platform_fee_type === 'fixed' && fundValue > 0) {
        const amount = parseFloat(this.pension.platform_fee_amount) || 0;
        let annualAmount = amount;
        if (this.pension.platform_fee_frequency === 'monthly') annualAmount = amount * 12;
        else if (this.pension.platform_fee_frequency === 'quarterly') annualAmount = amount * 4;
        return (annualAmount / fundValue) * 100;
      }
      return parseFloat(this.pension.platform_fee_percent) || 0;
    },

    /**
     * Has this pension been told what it charges?
     *
     * `platform_fee_percent` is NULL on David's SIPP, and every reader of it
     * coerces with `|| 0` — so an unanswered question rendered as "0.00% p.a.",
     * which is not an absence but a claim that the platform charges nothing.
     */
    hasPlatformFee() {
      if (this.pension.platform_fee_type === 'fixed') {
        return this.pension.platform_fee_amount !== null
          && this.pension.platform_fee_amount !== undefined
          && this.pension.platform_fee_amount !== '';
      }
      return this.pension.platform_fee_percent !== null
        && this.pension.platform_fee_percent !== undefined
        && this.pension.platform_fee_percent !== '';
    },

    // Anything at all on record that a total could be built from.
    hasRecordedFees() {
      return this.hasPlatformFee || this.advisorFeePercent > 0 || this.hasHoldings;
    },

    // Platform fee display string matching the form inputs
    platformFeeDisplay() {
      if (! this.hasPlatformFee) {
        return 'Not recorded';
      }
      if (this.pension.platform_fee_type === 'fixed') {
        const amount = parseFloat(this.pension.platform_fee_amount) || 0;
        const freqLabel = { monthly: '/month', quarterly: '/quarter', annually: '/year' };
        const freq = freqLabel[this.pension.platform_fee_frequency] || '/year';
        return this.formatCurrency(amount) + freq;
      }
      return this.platformFeePercent.toFixed(2) + '% p.a.';
    },

    // Advisor fee percentage
    advisorFeePercent() {
      return parseFloat(this.pension.advisor_fee_percent) || 0;
    },

    /**
     * The holdings on this pension — the ones this panel has written if it has
     * written any, otherwise the ones the parent handed down.
     *
     * Every consumer below reads THIS, never `pension.holdings` directly, so a
     * holding added on the Holdings tab moves the fee figures on the Overview
     * tab in the same breath. That is the whole point of the tab existing.
     */
    holdings() {
      return this.localHoldings ?? this.pension.holdings ?? [];
    },

    // What `HoldingForm` needs to stand in for its account select.
    holdingOwner() {
      return {
        label: 'Pension',
        name: this.pensionName,
        valueLabel: 'Fund Value:',
        value: parseFloat(this.pension.current_fund_value) || 0,
      };
    },

    // Check if pension has holdings
    hasHoldings() {
      return this.holdings.length > 0;
    },

    // Total allocation percentage across holdings
    totalHoldingsAllocation() {
      if (!this.hasHoldings) return 0;
      return this.holdings.reduce((sum, h) => sum + (parseFloat(h.allocation_percent) || 0), 0);
    },

    // Cash percentage (unallocated)
    holdingsCashPercent() {
      return Math.max(0, 100 - this.totalHoldingsAllocation);
    },

    // Cash value
    holdingsCashValue() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      return fundValue * (this.holdingsCashPercent / 100);
    },

    // Total holdings value (by current_value if available, otherwise by allocation)
    totalHoldingsValue() {
      if (!this.hasHoldings) return 0;
      return this.holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
    },

    // Weighted average OCF across holdings
    weightedAverageOCF() {
      if (!this.hasHoldings) return 0;
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      if (fundValue === 0) return 0;
      const totalWeightedOCF = this.holdings.reduce((sum, h) => {
        const value = this.holdingValue(h);
        return sum + (value * (parseFloat(h.ocf_percent) || 0));
      }, 0);
      return totalWeightedOCF / fundValue;
    },

    // Total fee percentage (platform + advisor + weighted OCF)
    totalFeePercent() {
      return this.platformFeePercent + this.advisorFeePercent + this.weightedAverageOCF;
    },

    // Annual fee cost in pounds
    annualFeeCost() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      return fundValue * (this.totalFeePercent / 100);
    },

    // 10-year fee impact projection
    feeImpact10yr() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      const feeRate = this.totalFeePercent / 100;
      const grossGrowth = this.pension.growth_rate ? parseFloat(this.pension.growth_rate) : 0.05;
      const annualContribution = this.totalMonthlyContribution * 12;
      const years = 10;

      // Project WITH fees (net growth)
      const netGrowth = grossGrowth - feeRate;
      let valueWithFees = fundValue;
      for (let i = 0; i < years; i++) {
        valueWithFees = (valueWithFees + annualContribution) * (1 + netGrowth);
      }

      // Project WITHOUT fees (gross growth)
      let valueWithoutFees = fundValue;
      for (let i = 0; i < years; i++) {
        valueWithoutFees = (valueWithoutFees + annualContribution) * (1 + grossGrowth);
      }

      const totalFees = this.annualFeeCost * years;
      const lostGrowth = Math.max(0, valueWithoutFees - valueWithFees - totalFees);
      const totalImpact = totalFees + lostGrowth;

      return { totalFees, lostGrowth, totalImpact };
    },
  },

  watch: {
    activeTab(newTab) {
      if (newTab === 'projections' && !this.projectionData && this.pensionType === 'dc') {
        this.loadProjections();
      }
    },
  },

  methods: {
    ...mapActions('retirement', [
      'updateDCPension',
      'updateDBPension',
      'updateStatePension',
      'deleteDCPension',
      'deleteDBPension',
      'fetchRetirementData',
    ]),

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },

    /**
     * What this holding is worth.
     *
     * The stored value first. This recomputed it from the allocation percentage
     * unconditionally, so a row storing £160,018 — 4,211 units at £38.00, which
     * the server derived through `HoldingValuation` — displayed £160,000, being
     * 50% of the pot. The table was showing a figure the database does not hold
     * while the column it holds sat unread (W-0442).
     *
     * The allocation fallback stays for rows with no value of their own, and for
     * the unallocated-cash footer, which is a percentage by construction.
     */
    holdingValue(holding) {
      const stored = parseFloat(holding.current_value);
      if (!Number.isNaN(stored) && stored > 0) {
        return stored;
      }
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      return fundValue * (parseFloat(holding.allocation_percent) || 0) / 100;
    },

    formatUnits,

    formatAssetType(type) {
      const labels = {
        equity: 'Equity',
        uk_equity: 'UK Equity',
        us_equity: 'US Equity',
        international_equity: 'Intl Equity',
        fund: 'Fund',
        etf: 'ETF',
        bond: 'Bond',
        cash: 'Cash',
        alternative: 'Alternative',
        property: 'Property',
      };
      return labels[type] || type || '—';
    },

    formatDCPensionType(type) {
      const types = {
        occupational: 'Work Pension',
        sipp: 'Self-Invested Personal Pension',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'Defined Contribution Pension';
    },

    formatDBSchemeType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'Defined Benefit Pension';
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleUpdate(data) {
      try {
        const pensionType = data._pensionType || this.pensionType;
        delete data._pensionType;

        if (pensionType === 'dc') {
          await this.updateDCPension({ id: this.pension.id, data });
        } else if (pensionType === 'db') {
          await this.updateDBPension({ id: this.pension.id, data });
        } else if (pensionType === 'state') {
          await this.updateStatePension(data);
        }

        this.showEditModal = false;

        // In preview mode, update local state only (API returned fake success, DB not updated)
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (isPreview) {
          // Emit updated pension data to parent so it can update local state
          this.$emit('pension-updated', { ...this.pension, ...data });
        } else {
          // Normal mode: reload from API
          await this.fetchRetirementData();
        }

        this.$emit('back'); // Return to list to show updated data
      } catch (error) {
        logger.error('Failed to update pension:', error);
      }
    },

    async handleDelete() {
      try {
        if (this.pensionType === 'dc') {
          await this.deleteDCPension(this.pension.id);
        } else if (this.pensionType === 'db') {
          await this.deleteDBPension(this.pension.id);
        }

        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        logger.error('Failed to delete pension:', error);
      }
    },

    openAddHolding() {
      this.editingHolding = null;
      this.holdingSaveError = null;
      this.showHoldingModal = true;
    },

    openEditHolding(holding) {
      this.editingHolding = holding;
      this.holdingSaveError = null;
      this.showHoldingModal = true;
    },

    closeHoldingModal() {
      this.showHoldingModal = false;
      this.editingHolding = null;
      this.holdingSaveError = null;
    },

    confirmDeleteHolding(holding) {
      this.holdingToDelete = holding;
    },

    /**
     * The parent owns the API call and closes the modal on success only —
     * CLAUDE.md Rule 3, and W-0009's lesson that a modal which closes itself
     * makes a discarded save look like a successful one.
     */
    async handleHoldingSave(holdingData) {
      this.holdingSaveError = null;
      this.holdingsError = null;

      try {
        if (holdingData.id) {
          await dcPensionHoldingsService.updateHolding(this.pension.id, holdingData.id, holdingData);
        } else {
          await dcPensionHoldingsService.createHolding(this.pension.id, holdingData);
        }
      } catch (error) {
        logger.error('Failed to save pension holding:', error);
        this.holdingSaveError = error.response?.data?.message
          || 'Failed to save the holding. Please try again.';
        return;
      }

      this.closeHoldingModal();
      await this.refreshHoldings();
    },

    async handleHoldingDelete() {
      const holding = this.holdingToDelete;
      this.holdingToDelete = null;
      if (!holding) return;

      this.holdingsError = null;
      try {
        await dcPensionHoldingsService.deleteHolding(this.pension.id, holding.id);
      } catch (error) {
        logger.error('Failed to delete pension holding:', error);
        this.holdingsError = error.response?.data?.message
          || 'Failed to delete the holding. Please try again.';
        return;
      }

      await this.refreshHoldings();
    },

    /**
     * Re-read the holdings from the server rather than patching them locally.
     *
     * The server reconciles units, price, value and cost basis on every write
     * (`App\Support\HoldingValuation`), so the row that comes back is not the
     * row that was sent. Patching local state would show the user their own
     * input back and hide the one figure worth seeing — what was actually stored.
     */
    async refreshHoldings() {
      try {
        const response = await dcPensionHoldingsService.listHoldings(this.pension.id);
        this.localHoldings = response.data ?? [];
      } catch (error) {
        logger.error('Failed to reload pension holdings:', error);
        this.holdingsError = 'The holding was saved, but the list could not be reloaded. Reopen this pension to see it.';
        return;
      }

      // Keep the rest of the app in step — net worth, fee analysis and the
      // pension list all read holdings through the retirement module.
      this.$emit('pension-updated', { ...this.pension, holdings: this.localHoldings });
      await this.fetchRetirementData();
    },

    async loadProjections() {
      if (this.projectionLoading || this.projectionData) return;

      this.projectionLoading = true;
      try {
        const response = await retirementService.getDCPensionProjection(this.pension.id);
        if (response.success) {
          this.projectionData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load projections:', error);
      } finally {
        this.projectionLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.pension-detail-inline {
  animation: fadeIn 0.3s ease-out;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-dc {
  @apply bg-raspberry-500;
  color: white;
}

.badge-db {
  @apply bg-violet-500;
  color: white;
}

.badge-state {
  @apply bg-spring-500;
  color: white;
}
</style>
