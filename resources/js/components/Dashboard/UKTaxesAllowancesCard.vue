<template>
  <div class="card cursor-pointer hover:shadow-lg transition-shadow" @click="openModal">
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-h4 text-gray-900">UK Taxes & Allowances</h3>
      <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
    </div>
    <p class="text-body text-gray-600 mb-4">
      2025/26 UK tax rules, rates, and allowances
    </p>
    <div class="flex gap-2 flex-wrap">
      <span class="badge-success">Income Tax</span>
      <span class="badge-info">Capital Gains Tax</span>
      <span class="badge-warning">Inheritance Tax</span>
    </div>
  </div>

  <!-- Modal -->
  <teleport to="body">
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-primary-50">
          <h2 class="text-h2 text-gray-900">UK Taxes & Allowances 2025/26</h2>
          <button
            @click="closeModal"
            class="text-gray-500 hover:text-gray-700 transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 bg-gray-50">
          <nav class="flex overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                activeTab === tab.id
                  ? 'border-primary-600 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
          <!-- Income Tax Tab -->
          <div v-if="activeTab === 'income'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Income Tax Rates & Bands</h3>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-blue-900">
                  <strong>Personal Allowance:</strong> £{{ formatNumber(taxConfig.income_tax.personal_allowance) }}
                  <br>
                  <em class="text-xs">Reduces by £1 for every £2 earned over £{{ formatNumber(taxConfig.income_tax.personal_allowance_taper_threshold) }}</em>
                </p>
              </div>

              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Band</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxable Income</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(band, index) in taxConfig.income_tax.bands" :key="index">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ band.name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                      £{{ formatNumber(band.min) }} - {{ band.max ? '£' + formatNumber(band.max) : 'No limit' }}
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (band.rate * 100).toFixed(0) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div>
              <h3 class="text-h3 text-gray-900 mb-4">National Insurance (Class 1 - Employed)</h3>
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Threshold/Limit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Employee (Main Rate)</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                      £{{ formatNumber(taxConfig.national_insurance.class_1.employee.primary_threshold) }} - £{{ formatNumber(taxConfig.national_insurance.class_1.employee.upper_earnings_limit) }}
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.national_insurance.class_1.employee.main_rate * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Employee (Additional Rate)</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Above £{{ formatNumber(taxConfig.national_insurance.class_1.employee.upper_earnings_limit) }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.national_insurance.class_1.employee.additional_rate * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Employer</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Above £{{ formatNumber(taxConfig.national_insurance.class_1.employer.secondary_threshold) }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.national_insurance.class_1.employer.rate * 100).toFixed(1) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- CGT Tab -->
          <div v-if="activeTab === 'cgt'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Capital Gains Tax</h3>
              <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-green-900">
                  <strong>Annual Exempt Amount:</strong> £{{ formatNumber(taxConfig.capital_gains_tax.annual_exempt_amount) }}
                  <br>
                  <em class="text-xs">First £{{ formatNumber(taxConfig.capital_gains_tax.annual_exempt_amount) }} of gains per year are tax-free</em>
                </p>
              </div>

              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxpayer Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">General Assets (stocks, funds, etc.)</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Basic Rate Taxpayer</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.capital_gains_tax.rates.basic_rate_taxpayer * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">General Assets</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Higher/Additional Rate Taxpayer</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.capital_gains_tax.rates.higher_rate_taxpayer * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-amber-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Residential Property</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Basic Rate Taxpayer</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.capital_gains_tax.rates.residential_property_basic * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-amber-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Residential Property</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Higher/Additional Rate Taxpayer</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.capital_gains_tax.rates.residential_property_higher * 100).toFixed(0) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Dividend Tax</h3>
              <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-purple-900">
                  <strong>Dividend Allowance:</strong> £{{ formatNumber(taxConfig.dividend_tax.allowance) }}
                  <br>
                  <em class="text-xs">First £{{ formatNumber(taxConfig.dividend_tax.allowance) }} of dividends per year are tax-free</em>
                </p>
              </div>

              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Band</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Basic Rate</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.dividend_tax.rates.basic_rate * 100).toFixed(2) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Higher Rate</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.dividend_tax.rates.higher_rate * 100).toFixed(2) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Additional Rate</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ (taxConfig.dividend_tax.rates.additional_rate * 100).toFixed(2) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- IHT Tab -->
          <div v-if="activeTab === 'iht'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Inheritance Tax (IHT)</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                  <p class="text-sm text-green-900">
                    <strong>Nil Rate Band (NRB):</strong> £{{ formatNumber(taxConfig.inheritance_tax.nil_rate_band) }}
                    <br>
                    <em class="text-xs">Transferable between spouses/civil partners</em>
                  </p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <p class="text-sm text-blue-900">
                    <strong>Residence NRB (RNRB):</strong> £{{ formatNumber(taxConfig.inheritance_tax.residence_nil_rate_band) }}
                    <br>
                    <em class="text-xs">For main residence passed to direct descendants</em>
                  </p>
                </div>
              </div>

              <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-red-900">
                  <strong>Standard IHT Rate:</strong> {{ (taxConfig.inheritance_tax.standard_rate * 100).toFixed(0) }}%
                  <br>
                  <strong>Reduced Rate (10%+ to charity):</strong> {{ (taxConfig.inheritance_tax.reduced_rate_charity * 100).toFixed(0) }}%
                </p>
              </div>

              <h4 class="text-h4 text-gray-900 mb-3 mt-6">RNRB Taper</h4>
              <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-amber-900">
                  RNRB tapers by £1 for every £2 the estate exceeds £{{ formatNumber(taxConfig.inheritance_tax.rnrb_taper_threshold) }}
                  <br>
                  <em class="text-xs">RNRB is fully lost when estate reaches £{{ formatNumber(taxConfig.inheritance_tax.rnrb_taper_threshold + taxConfig.inheritance_tax.residence_nil_rate_band * 2) }}</em>
                </p>
              </div>
            </div>

            <div>
              <h4 class="text-h4 text-gray-900 mb-3">Potentially Exempt Transfers (PETs)</h4>
              <p class="text-sm text-gray-600 mb-3">
                Gifts become fully exempt after 7 years. Taper relief applies if donor dies between years 3-7:
              </p>
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Years Since Gift</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IHT Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">0-3 years</td>
                    <td class="px-4 py-3 text-sm font-semibold text-red-600">40%</td>
                  </tr>
                  <tr v-for="(relief, index) in taxConfig.inheritance_tax.potentially_exempt_transfers.taper_relief" :key="index">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ relief.years - 1 }}-{{ relief.years }} years</td>
                    <td class="px-4 py-3 text-sm font-semibold" :class="getReliefColour(relief.rate)">{{ (relief.rate * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-green-50">
                    <td class="px-4 py-3 text-sm text-gray-900">7+ years</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">0% (Exempt)</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div>
              <h4 class="text-h4 text-gray-900 mb-3">IHT Gifting Exemptions</h4>
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exemption</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Annual Exemption</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.gifting_exemptions.annual_exemption) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Can carry forward 1 year</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Small Gifts</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.gifting_exemptions.small_gifts.amount) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Per person, per year</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Wedding Gift (Child)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.gifting_exemptions.wedding_gifts.child) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Parent to child</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Wedding Gift (Grandchild)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.gifting_exemptions.wedding_gifts.grandchild_great_grandchild) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Grandparent to grandchild</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Wedding Gift (Other)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.gifting_exemptions.wedding_gifts.other) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Any other person</td>
                  </tr>
                  <tr class="bg-green-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Normal Expenditure Out of Income</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">Unlimited</td>
                    <td class="px-4 py-3 text-sm text-gray-600">If regular and doesn't reduce standard of living</td>
                  </tr>
                  <tr class="bg-green-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Charity Gifts</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">Unlimited</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Fully exempt</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pensions Tab -->
          <div v-if="activeTab === 'pensions'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Pension Allowances</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <p class="text-sm text-blue-900">
                    <strong>Annual Allowance:</strong> £{{ formatNumber(taxConfig.pension.annual_allowance) }}
                    <br>
                    <em class="text-xs">Maximum tax-relieved contributions per year</em>
                  </p>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                  <p class="text-sm text-amber-900">
                    <strong>Reduced Allowance After Accessing Pension:</strong> £{{ formatNumber(taxConfig.pension.money_purchase_annual_allowance) }}
                    <br>
                    <em class="text-xs">After accessing pension flexibly</em>
                  </p>
                </div>
              </div>

              <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-green-900">
                  <strong>Lifetime Allowance:</strong> Abolished April 2024
                  <br>
                  <em class="text-xs">No limit on total pension savings from 2025/26 onwards</em>
                </p>
              </div>

              <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-purple-900">
                  <strong>Carry Forward:</strong> Unused allowance from previous {{ taxConfig.pension.carry_forward_years }} tax years
                  <br>
                  <em class="text-xs">Subject to membership of a pension scheme in those years</em>
                </p>
              </div>
            </div>

            <div>
              <h4 class="text-h4 text-gray-900 mb-3">Tapered Annual Allowance</h4>
              <p class="text-sm text-gray-600 mb-3">
                High earners face a reduced annual allowance:
              </p>
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Measure</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Threshold Income</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.pension.tapered_annual_allowance.threshold_income) }}</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Adjusted Income</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.pension.tapered_annual_allowance.adjusted_income) }}</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Minimum Allowance</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.pension.tapered_annual_allowance.minimum_allowance) }}</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Taper Rate</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£1 for every £2 over adjusted income</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div>
              <h4 class="text-h4 text-gray-900 mb-3">State Pension</h4>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-900">
                  <strong>Full New State Pension:</strong> £{{ formatNumber(taxConfig.pension.state_pension.full_new_state_pension) }} per year
                  <br>
                  <strong>Qualifying Years Required:</strong> {{ taxConfig.pension.state_pension.qualifying_years }} years
                  <br>
                  <strong>Minimum Qualifying Years:</strong> {{ taxConfig.pension.state_pension.minimum_qualifying_years }} years
                </p>
              </div>
            </div>
          </div>

          <!-- ISAs Tab -->
          <div v-if="activeTab === 'isas'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Tax-Free Savings Account Allowances</h3>

              <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-green-900">
                  <strong>Total Annual Tax-Free Savings Allowance:</strong> £{{ formatNumber(taxConfig.isa.annual_allowance) }}
                  <br>
                  <em class="text-xs">Split across Cash ISA, Stocks & Shares ISA, Innovative Finance ISA, and Lifetime ISA</em>
                </p>
              </div>

              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Annual Allowance</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Cash ISA</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.isa.annual_allowance) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Part of total ISA allowance</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Stocks & Shares ISA</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.isa.annual_allowance) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Part of total ISA allowance</td>
                  </tr>
                  <tr class="bg-purple-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Lifetime ISA (LISA)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.isa.lifetime_isa.annual_allowance) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Counts towards total ISA allowance</td>
                  </tr>
                  <tr class="bg-blue-50">
                    <td class="px-4 py-3 text-sm text-gray-900">Junior ISA</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.isa.junior_isa.annual_allowance) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Separate allowance for under 18s</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div>
              <h4 class="text-h4 text-gray-900 mb-3">Lifetime ISA Details</h4>
              <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <ul class="text-sm text-purple-900 space-y-2">
                  <li><strong>Max Age to Open:</strong> {{ taxConfig.isa.lifetime_isa.max_age_to_open }} years</li>
                  <li><strong>Government Bonus:</strong> {{ (taxConfig.isa.lifetime_isa.government_bonus_rate * 100).toFixed(0) }}% (£1,000 max per year on £4,000 contributions)</li>
                  <li><strong>Withdrawal Penalty:</strong> {{ (taxConfig.isa.lifetime_isa.withdrawal_penalty * 100).toFixed(0) }}% if withdrawn before age 60 (except first home purchase)</li>
                  <li><strong>Uses:</strong> First home purchase (£450,000 limit) or retirement from age 60</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Other Tab -->
          <div v-if="activeTab === 'other'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">Other Allowances & Rates</h3>

              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Allowance</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Marriage Allowance</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.other.marriage_allowance.transferable_amount) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Transfer to spouse if non-taxpayer</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Savings Allowance (Basic Rate)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.other.savings_allowance.basic_rate_taxpayer) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Tax-free savings interest</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Savings Allowance (Higher Rate)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.other.savings_allowance.higher_rate_taxpayer) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Tax-free savings interest</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Savings Allowance (Additional Rate)</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.other.savings_allowance.additional_rate_taxpayer) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">No allowance</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Starting Rate for Savings</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.other.starting_rate_for_savings.band) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">0% rate, reduces with other income</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">Blind Person's Allowance</td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">£{{ formatNumber(taxConfig.other.blind_persons_allowance) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">Additional income tax relief</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div>
              <h4 class="text-h4 text-gray-900 mb-3">Child Benefit</h4>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-900">
                  <strong>Eldest/Only Child:</strong> £{{ taxConfig.other.child_benefit.eldest_child.toFixed(2) }} per week
                  <br>
                  <strong>Additional Children:</strong> £{{ taxConfig.other.child_benefit.additional_child.toFixed(2) }} per week
                  <br>
                  <strong>High Income Charge:</strong> Applies if income exceeds £{{ formatNumber(taxConfig.other.child_benefit.high_income_charge_threshold) }}
                  <br>
                  <em class="text-xs">Charge is 1% per £100 over threshold (fully clawed back at £80,000)</em>
                </p>
              </div>
            </div>
          </div>

          <!-- Calculations Tab -->
          <div v-if="activeTab === 'calculations'" class="space-y-6">
            <div>
              <h3 class="text-h3 text-gray-900 mb-4">How Fynla Calculations Work</h3>
              <p class="text-sm text-gray-600 mb-6">
                This section explains the key financial calculations used throughout the Financial Planning System.
              </p>
            </div>

            <!-- Income Tax Calculation -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
              <h4 class="text-h4 text-gray-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Income Tax Calculation
              </h4>
              <div class="space-y-3 text-sm">
                <p><strong>Step 1:</strong> Calculate Personal Allowance</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  If income > £100,000:
                  <br>Personal Allowance = £12,570 - ((income - £100,000) / 2)
                  <br>Personal Allowance = max(0, Personal Allowance)
                </code>

                <p><strong>Step 2:</strong> Calculate Taxable Income</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Taxable Income = Gross Income - Personal Allowance
                </code>

                <p><strong>Step 3:</strong> Apply Tax Bands</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  £0 - £37,700: 20% (Basic Rate)
                  <br>£37,701 - £125,140: 40% (Higher Rate)
                  <br>£125,141+: 45% (Additional Rate)
                </code>

                <p><strong>Example:</strong> Income of £60,000</p>
                <code class="block bg-blue-50 p-3 rounded text-xs">
                  Personal Allowance: £12,570
                  <br>Taxable Income: £60,000 - £12,570 = £47,430
                  <br>Basic Rate Tax: £37,700 × 20% = £7,540
                  <br>Higher Rate Tax: £9,730 × 40% = £3,892
                  <br>Total Tax: £7,540 + £3,892 = £11,432
                </code>
              </div>
            </div>

            <!-- IHT Calculation -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
              <h4 class="text-h4 text-gray-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Inheritance Tax (IHT) Calculation
              </h4>
              <div class="space-y-3 text-sm">
                <p><strong>Step 1:</strong> Calculate Gross Estate Value</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Gross Estate = Total Assets - Total Liabilities
                  <br>(Includes: property, investments, savings, personal possessions)
                </code>

                <p><strong>Step 2:</strong> Determine Available Allowances</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  NRB Available = £325,000 × (1 + spouse_nrb_transferred)
                  <br><br>If property asset AND passing to descendants:
                  <br>&nbsp;&nbsp;RNRB Available = £175,000 × (1 + spouse_rnrb_transferred)
                  <br>&nbsp;&nbsp;If estate > £2,000,000:
                  <br>&nbsp;&nbsp;&nbsp;&nbsp;RNRB Reduction = (estate - £2,000,000) / 2
                  <br>&nbsp;&nbsp;&nbsp;&nbsp;RNRB Available = max(0, RNRB Available - RNRB Reduction)
                </code>

                <p><strong>Step 3:</strong> Calculate Taxable Estate</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Total Allowances = NRB Available + RNRB Available
                  <br>Taxable Estate = max(0, Gross Estate - Total Allowances)
                </code>

                <p><strong>Step 4:</strong> Calculate IHT Liability</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  IHT Liability = Taxable Estate × 40%
                  <br>(Or 36% if 10%+ of net estate left to charity)
                </code>

                <p><strong>Example:</strong> Estate worth £800,000 with property, no spouse transfers</p>
                <code class="block bg-blue-50 p-3 rounded text-xs">
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
            <div class="bg-white border border-gray-200 rounded-lg p-6">
              <h4 class="text-h4 text-gray-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                Capital Gains Tax (CGT) Calculation
              </h4>
              <div class="space-y-3 text-sm">
                <p><strong>Step 1:</strong> Calculate Capital Gain</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Capital Gain = Sale Price - Purchase Price - Allowable Costs
                  <br>(Allowable costs: purchase fees, improvement costs, sale fees)
                </code>

                <p><strong>Step 2:</strong> Apply Annual Exempt Amount</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Taxable Gain = max(0, Capital Gain - £3,000)
                </code>

                <p><strong>Step 3:</strong> Determine CGT Rate</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  General Assets (shares, funds):
                  <br>&nbsp;&nbsp;Basic Rate Taxpayer: 10%
                  <br>&nbsp;&nbsp;Higher/Additional Rate: 20%
                  <br><br>Residential Property:
                  <br>&nbsp;&nbsp;Basic Rate Taxpayer: 18%
                  <br>&nbsp;&nbsp;Higher/Additional Rate: 24%
                </code>

                <p><strong>Step 4:</strong> Calculate CGT Due</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  CGT Due = Taxable Gain × Applicable Rate
                </code>

                <p><strong>Example:</strong> Higher rate taxpayer sells shares</p>
                <code class="block bg-blue-50 p-3 rounded text-xs">
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
            <div class="bg-white border border-gray-200 rounded-lg p-6">
              <h4 class="text-h4 text-gray-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Pension Annual Allowance Calculation
              </h4>
              <div class="space-y-3 text-sm">
                <p><strong>Step 1:</strong> Determine Base Allowance</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  If accessed pension flexibly: £10,000 (MPAA)
                  <br>Otherwise: £60,000 (Standard Annual Allowance)
                </code>

                <p><strong>Step 2:</strong> Check for Tapering (High Earners)</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  If threshold_income > £200,000 AND adjusted_income > £260,000:
                  <br>&nbsp;&nbsp;Reduction = (adjusted_income - £260,000) / 2
                  <br>&nbsp;&nbsp;Tapered Allowance = max(£10,000, £60,000 - Reduction)
                </code>

                <p><strong>Step 3:</strong> Add Carry Forward (if applicable)</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Total Available = Current Year Allowance + Unused from Last 3 Years
                  <br>(Subject to pension scheme membership in those years)
                </code>

                <p><strong>Example:</strong> High earner with £300,000 adjusted income</p>
                <code class="block bg-blue-50 p-3 rounded text-xs">
                  Standard Allowance: £60,000
                  <br>Adjusted Income: £300,000
                  <br>Exceeds £260,000 by: £40,000
                  <br>Reduction: £40,000 / 2 = £20,000
                  <br>Tapered Allowance: £60,000 - £20,000 = £40,000
                </code>
              </div>
            </div>

            <!-- Emergency Fund Calculation -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
              <h4 class="text-h4 text-gray-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Emergency Fund Runway Calculation
              </h4>
              <div class="space-y-3 text-sm">
                <p><strong>Step 1:</strong> Calculate Monthly Essential Expenses</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Essential Expenses = Mortgage/Rent + Bills + Food + Transport + Insurance
                  <br>(Excludes discretionary spending like entertainment, dining out)
                </code>

                <p><strong>Step 2:</strong> Calculate Total Emergency Fund</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Emergency Fund = Sum of all savings accounts marked as "emergency fund"
                  <br>(Easy access and notice accounts, not long-term fixed accounts)
                </code>

                <p><strong>Step 3:</strong> Calculate Runway</p>
                <code class="block bg-gray-50 p-3 rounded text-xs">
                  Runway (months) = Emergency Fund / Monthly Essential Expenses
                </code>

                <p><strong>Recommendation:</strong></p>
                <code class="block bg-green-50 p-3 rounded text-xs">
                  Target: 3-6 months of essential expenses
                  <br>Higher for self-employed or single income households
                </code>

                <p><strong>Example:</strong></p>
                <code class="block bg-blue-50 p-3 rounded text-xs">
                  Monthly Essential Expenses: £2,500
                  <br>Emergency Fund Balance: £15,000
                  <br>Runway: £15,000 / £2,500 = 6 months
                  <br>Status: Adequate ✓
                </code>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
          <p class="text-xs text-gray-500">
            Tax year: {{ taxConfig.tax_year }} ({{ taxConfig.effective_from }} to {{ taxConfig.effective_to }})
          </p>
          <button
            @click="closeModal"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script>
import { ref, computed } from 'vue';

export default {
  name: 'UKTaxesAllowancesCard',

  setup() {
    const showModal = ref(false);
    const activeTab = ref('income');

    // Tax configuration (2025/26)
    const taxConfig = {
      tax_year: '2025/26',
      effective_from: '2025-04-06',
      effective_to: '2026-04-05',
      income_tax: {
        personal_allowance: 12570,
        personal_allowance_taper_threshold: 100000,
        bands: [
          { name: 'Basic Rate', min: 0, max: 37700, rate: 0.20 },
          { name: 'Higher Rate', min: 37700, max: 125140, rate: 0.40 },
          { name: 'Additional Rate', min: 125140, max: null, rate: 0.45 },
        ],
      },
      national_insurance: {
        class_1: {
          employee: {
            primary_threshold: 12570,
            upper_earnings_limit: 50270,
            main_rate: 0.12,
            additional_rate: 0.02,
          },
          employer: {
            secondary_threshold: 9100,
            rate: 0.138,
          },
        },
      },
      capital_gains_tax: {
        annual_exempt_amount: 3000,
        rates: {
          basic_rate_taxpayer: 0.10,
          higher_rate_taxpayer: 0.20,
          residential_property_basic: 0.18,
          residential_property_higher: 0.24,
        },
      },
      dividend_tax: {
        allowance: 500,
        rates: {
          basic_rate: 0.0875,
          higher_rate: 0.3375,
          additional_rate: 0.3935,
        },
      },
      inheritance_tax: {
        nil_rate_band: 325000,
        residence_nil_rate_band: 175000,
        rnrb_taper_threshold: 2000000,
        standard_rate: 0.40,
        reduced_rate_charity: 0.36,
        potentially_exempt_transfers: {
          taper_relief: [
            { years: 4, rate: 0.32 },
            { years: 5, rate: 0.24 },
            { years: 6, rate: 0.16 },
            { years: 7, rate: 0.08 },
          ],
        },
      },
      gifting_exemptions: {
        annual_exemption: 3000,
        small_gifts: { amount: 250 },
        wedding_gifts: {
          child: 5000,
          grandchild_great_grandchild: 2500,
          other: 1000,
        },
      },
      pension: {
        annual_allowance: 60000,
        money_purchase_annual_allowance: 10000,
        carry_forward_years: 3,
        tapered_annual_allowance: {
          threshold_income: 200000,
          adjusted_income: 260000,
          minimum_allowance: 10000,
        },
        state_pension: {
          full_new_state_pension: 11502.40,
          qualifying_years: 35,
          minimum_qualifying_years: 10,
        },
      },
      isa: {
        annual_allowance: 20000,
        lifetime_isa: {
          annual_allowance: 4000,
          max_age_to_open: 39,
          government_bonus_rate: 0.25,
          withdrawal_penalty: 0.25,
        },
        junior_isa: {
          annual_allowance: 9000,
        },
      },
      other: {
        marriage_allowance: {
          transferable_amount: 1260,
        },
        savings_allowance: {
          basic_rate_taxpayer: 1000,
          higher_rate_taxpayer: 500,
          additional_rate_taxpayer: 0,
        },
        starting_rate_for_savings: {
          band: 5000,
        },
        blind_persons_allowance: 3070,
        child_benefit: {
          eldest_child: 25.60,
          additional_child: 16.95,
          high_income_charge_threshold: 60000,
        },
      },
    };

    const tabs = [
      { id: 'income', label: 'Income Tax & National Insurance' },
      { id: 'cgt', label: 'Capital Gains Tax & Dividends' },
      { id: 'iht', label: 'Inheritance Tax' },
      { id: 'pensions', label: 'Pensions' },
      { id: 'isas', label: 'Tax-Free Savings (ISAs)' },
      { id: 'other', label: 'Other Allowances' },
      { id: 'calculations', label: 'Calculations' },
    ];

    const openModal = () => {
      showModal.value = true;
    };

    const closeModal = () => {
      showModal.value = false;
    };

    const formatNumber = (num) => {
      return num.toLocaleString('en-GB');
    };

    const getReliefColour = (rate) => {
      if (rate >= 0.32) return 'text-red-600';
      if (rate >= 0.16) return 'text-orange-600';
      return 'text-green-600';
    };

    return {
      showModal,
      activeTab,
      taxConfig,
      tabs,
      openModal,
      closeModal,
      formatNumber,
      getReliefColour,
    };
  },
};
</script>
