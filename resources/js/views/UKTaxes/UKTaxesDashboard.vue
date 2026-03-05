<template>
  <AppLayout>
    <div class="px-4 sm:px-0">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="font-display text-h1 text-horizon-500">UK Taxes & Allowances 2025/26</h1>
        <p class="text-body text-neutral-500 mt-2">
          Current UK tax rules, rates, and allowances with detailed calculation methodologies
        </p>
      </div>

      <!-- Tabs -->
      <div class="border-b border-light-gray mb-6">
        <nav class="flex space-x-8 overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === tab.id
                ? 'border-raspberry-500 text-raspberry-500'
                : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="space-y-6">
        <!-- Income Tax & NI Tab -->
        <div v-if="activeTab === 'income'" class="space-y-6">
          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Income Tax Rates & Bands</h2>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
              <p class="text-sm text-blue-900">
                <strong>Personal Allowance:</strong> £{{ formatNumber(taxConfig.income_tax.personal_allowance) }}
                <br>
                <em class="text-xs">Reduces by £1 for every £2 earned over £{{ formatNumber(taxConfig.income_tax.personal_allowance_taper_threshold) }}</em>
              </p>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Band</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Taxable Income</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr v-for="(band, index) in taxConfig.income_tax.bands" :key="index">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-horizon-500">{{ band.name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                      £{{ formatNumber(band.min) }} - {{ band.max ? '£' + formatNumber(band.max) : 'No limit' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-horizon-500">{{ (band.rate * 100).toFixed(0) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">National Insurance (Class 1 - Employed)</h2>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Threshold/Limit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Employee (Main Rate)</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">
                      £{{ formatNumber(taxConfig.national_insurance.class_1.employee.primary_threshold) }} - £{{ formatNumber(taxConfig.national_insurance.class_1.employee.upper_earnings_limit) }}
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.national_insurance.class_1.employee.main_rate * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Employee (Additional Rate)</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Above £{{ formatNumber(taxConfig.national_insurance.class_1.employee.upper_earnings_limit) }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.national_insurance.class_1.employee.additional_rate * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-eggshell-500">
                    <td class="px-6 py-4 text-sm text-horizon-500">Employer</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Above £{{ formatNumber(taxConfig.national_insurance.class_1.employer.secondary_threshold) }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.national_insurance.class_1.employer.rate * 100).toFixed(1) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- CGT & Dividends Tab -->
        <div v-if="activeTab === 'cgt'" class="space-y-6">
          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Capital Gains Tax</h2>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
              <p class="text-sm text-green-900">
                <strong>Annual Exempt Amount:</strong> £{{ formatNumber(taxConfig.capital_gains_tax.annual_exempt_amount) }}
                <br>
                <em class="text-xs">First £{{ formatNumber(taxConfig.capital_gains_tax.annual_exempt_amount) }} of gains per year are tax-free</em>
              </p>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Asset Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Taxpayer Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">General Assets (stocks, funds, etc.)</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Basic Rate Taxpayer</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.capital_gains_tax.rates.basic_rate_taxpayer * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">General Assets</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Higher/Additional Rate Taxpayer</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.capital_gains_tax.rates.higher_rate_taxpayer * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-blue-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">Residential Property</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Basic Rate Taxpayer</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.capital_gains_tax.rates.residential_property_basic * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-blue-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">Residential Property</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Higher/Additional Rate Taxpayer</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.capital_gains_tax.rates.residential_property_higher * 100).toFixed(0) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Dividend Tax</h2>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
              <p class="text-sm text-purple-900">
                <strong>Dividend Allowance:</strong> £{{ formatNumber(taxConfig.dividend_tax.allowance) }}
                <br>
                <em class="text-xs">First £{{ formatNumber(taxConfig.dividend_tax.allowance) }} of dividends per year are tax-free</em>
              </p>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tax Band</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Basic Rate</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.dividend_tax.rates.basic_rate * 100).toFixed(2) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Higher Rate</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.dividend_tax.rates.higher_rate * 100).toFixed(2) }}%</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Additional Rate</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">{{ (taxConfig.dividend_tax.rates.additional_rate * 100).toFixed(2) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- IHT Tab -->
        <div v-if="activeTab === 'iht'" class="space-y-6">
          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Inheritance Tax</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-sm text-green-900">
                  <strong>Nil Rate Band:</strong> £{{ formatNumber(taxConfig.inheritance_tax.nil_rate_band) }}
                  <br>
                  <em class="text-xs">Transferable between spouses/civil partners</em>
                </p>
              </div>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-900">
                  <strong>Residence Nil Rate Band:</strong> £{{ formatNumber(taxConfig.inheritance_tax.residence_nil_rate_band) }}
                  <br>
                  <em class="text-xs">For main residence passed to direct descendants</em>
                </p>
              </div>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
              <p class="text-sm text-red-900">
                <strong>Standard Inheritance Tax Rate:</strong> {{ (taxConfig.inheritance_tax.standard_rate * 100).toFixed(0) }}%
                <br>
                <strong>Reduced Rate (10%+ to charity):</strong> {{ (taxConfig.inheritance_tax.reduced_rate_charity * 100).toFixed(0) }}%
              </p>
            </div>

            <h3 class="text-h3 text-horizon-500 mb-3">Residence Nil Rate Band Taper</h3>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
              <p class="text-sm text-blue-900">
                Residence Nil Rate Band tapers by £1 for every £2 the estate exceeds £{{ formatNumber(taxConfig.inheritance_tax.rnrb_taper_threshold) }}
                <br>
                <em class="text-xs">Residence Nil Rate Band is fully lost when estate reaches £{{ formatNumber(taxConfig.inheritance_tax.rnrb_taper_threshold + taxConfig.inheritance_tax.residence_nil_rate_band * 2) }}</em>
              </p>
            </div>

            <h3 class="text-h3 text-horizon-500 mb-3">Potentially Exempt Transfers</h3>
            <p class="text-sm text-neutral-500 mb-3">
              Gifts become fully exempt after 7 years. Taper relief applies if donor dies between years 3-7:
            </p>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Years Since Gift</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Inheritance Tax Rate</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">0-3 years</td>
                    <td class="px-6 py-4 text-sm font-semibold text-red-600">40%</td>
                  </tr>
                  <tr v-for="(relief, index) in taxConfig.inheritance_tax.potentially_exempt_transfers.taper_relief" :key="index">
                    <td class="px-6 py-4 text-sm text-horizon-500">{{ relief.years - 1 }}-{{ relief.years }} years</td>
                    <td class="px-6 py-4 text-sm font-semibold" :class="getReliefColour(relief.rate)">{{ (relief.rate * 100).toFixed(0) }}%</td>
                  </tr>
                  <tr class="bg-green-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">7+ years</td>
                    <td class="px-6 py-4 text-sm font-semibold text-green-600">0% (Exempt)</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Inheritance Tax Gifting Exemptions</h2>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Exemption</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Notes</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Annual Exemption</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.gifting_exemptions.annual_exemption) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Can carry forward 1 year</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Small Gifts</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.gifting_exemptions.small_gifts.amount) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Per person, per year</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Wedding Gift (Child)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.gifting_exemptions.wedding_gifts.child) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Parent to child</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Wedding Gift (Grandchild)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.gifting_exemptions.wedding_gifts.grandchild_great_grandchild) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Grandparent to grandchild</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Wedding Gift (Other)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.gifting_exemptions.wedding_gifts.other) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Any other person</td>
                  </tr>
                  <tr class="bg-green-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">Normal Expenditure Out of Income</td>
                    <td class="px-6 py-4 text-sm font-semibold text-green-600">Unlimited</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">If regular and doesn't reduce standard of living</td>
                  </tr>
                  <tr class="bg-green-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">Charity Gifts</td>
                    <td class="px-6 py-4 text-sm font-semibold text-green-600">Unlimited</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Fully exempt</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Pensions Tab -->
        <div v-if="activeTab === 'pensions'" class="space-y-6">
          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Pension Allowances</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-900">
                  <strong>Annual Allowance:</strong> £{{ formatNumber(taxConfig.pension.annual_allowance) }}
                  <br>
                  <em class="text-xs">Maximum tax-relieved contributions per year</em>
                </p>
              </div>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-900">
                  <strong>Money Purchase Annual Allowance:</strong> £{{ formatNumber(taxConfig.pension.money_purchase_annual_allowance) }}
                  <br>
                  <em class="text-xs">After accessing pension flexibly</em>
                </p>
              </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
              <p class="text-sm text-green-900">
                <strong>Lifetime Allowance:</strong> Abolished April 2024
                <br>
                <em class="text-xs">No limit on total pension savings from 2025/26 onwards</em>
              </p>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
              <p class="text-sm text-purple-900">
                <strong>Carry Forward:</strong> Unused allowance from previous {{ taxConfig.pension.carry_forward_years }} tax years
                <br>
                <em class="text-xs">Subject to membership of a pension scheme in those years</em>
              </p>
            </div>

            <h3 class="text-h3 text-horizon-500 mb-3">Tapered Annual Allowance</h3>
            <p class="text-sm text-neutral-500 mb-3">
              High earners face a reduced annual allowance:
            </p>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Measure</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Amount</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Threshold Income</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.pension.tapered_annual_allowance.threshold_income) }}</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Adjusted Income</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.pension.tapered_annual_allowance.adjusted_income) }}</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Minimum Allowance</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.pension.tapered_annual_allowance.minimum_allowance) }}</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Taper Rate</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£1 for every £2 over adjusted income</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">State Pension</h2>
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
          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">ISA Allowances</h2>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
              <p class="text-sm text-green-900">
                <strong>Total Annual ISA Allowance:</strong> £{{ formatNumber(taxConfig.isa.annual_allowance) }}
                <br>
                <em class="text-xs">Split across Cash ISA, Stocks & Shares ISA, Innovative Finance ISA, and Lifetime ISA</em>
              </p>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">ISA Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Annual Allowance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Notes</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Cash ISA</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.isa.annual_allowance) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Part of total ISA allowance</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Stocks & Shares ISA</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.isa.annual_allowance) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Part of total ISA allowance</td>
                  </tr>
                  <tr class="bg-purple-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">Lifetime ISA (LISA)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.isa.lifetime_isa.annual_allowance) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Counts towards total ISA allowance</td>
                  </tr>
                  <tr class="bg-blue-50">
                    <td class="px-6 py-4 text-sm text-horizon-500">Junior ISA</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.isa.junior_isa.annual_allowance) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Separate allowance for under 18s</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Lifetime ISA (LISA) Details</h2>
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

        <!-- Other Allowances Tab -->
        <div v-if="activeTab === 'other'" class="space-y-6">
          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Other Allowances & Rates</h2>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-light-gray">
                <thead class="bg-eggshell-500">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Allowance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Details</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-light-gray">
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Marriage Allowance</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.other.marriage_allowance.transferable_amount) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Transfer to spouse if non-taxpayer</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Savings Allowance (Basic Rate)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.other.savings_allowance.basic_rate_taxpayer) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Tax-free savings interest</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Savings Allowance (Higher Rate)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.other.savings_allowance.higher_rate_taxpayer) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Tax-free savings interest</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Savings Allowance (Additional Rate)</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.other.savings_allowance.additional_rate_taxpayer) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">No allowance</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Starting Rate for Savings</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.other.starting_rate_for_savings.band) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">0% rate, reduces with other income</td>
                  </tr>
                  <tr>
                    <td class="px-6 py-4 text-sm text-horizon-500">Blind Person's Allowance</td>
                    <td class="px-6 py-4 text-sm font-semibold text-horizon-500">£{{ formatNumber(taxConfig.other.blind_persons_allowance) }}</td>
                    <td class="px-6 py-4 text-sm text-neutral-500">Additional income tax relief</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h2 class="text-h2 text-horizon-500 mb-4">Child Benefit</h2>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <p class="text-sm text-blue-900">
                <strong>Eldest/Only Child:</strong> £{{ taxConfig.other.child_benefit.eldest_child.toFixed(2) }} per week
                <br>
                <strong>Additional Children:</strong> £{{ taxConfig.other.child_benefit.additional_child.toFixed(2) }} per week
                <br>
                <strong>High Income Charge:</strong> Applies if income exceeds £{{ formatNumber(taxConfig.other.child_benefit.high_income_charge_threshold) }}
                <br>
                <em class="text-xs">Charge is 1% per £200 over threshold (fully clawed back at £80,000)</em>
              </p>
            </div>
          </div>
        </div>

        <!-- Calculations Tab -->
        <div v-if="activeTab === 'calculations'">
          <CalculationsTab />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CalculationsTab from '@/components/UKTaxes/CalculationsTab.vue';

export default {
  name: 'UKTaxesDashboard',

  components: {
    AppLayout,
    CalculationsTab,
  },

  setup() {
    const activeTab = ref('income');

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
          eldest_child: 26.05,
          additional_child: 17.25,
          high_income_charge_threshold: 60000,
          high_income_full_clawback: 80000,
        },
      },
    };

    const tabs = [
      { id: 'income', label: 'Income Tax & National Insurance' },
      { id: 'cgt', label: 'Capital Gains Tax & Dividends' },
      { id: 'iht', label: 'Inheritance Tax' },
      { id: 'pensions', label: 'Pensions' },
      { id: 'isas', label: 'ISAs' },
      { id: 'other', label: 'Other Allowances' },
      { id: 'calculations', label: 'Calculations' },
    ];

    const formatNumber = (num) => {
      return num.toLocaleString('en-GB');
    };

    const getReliefColour = (rate) => {
      if (rate >= 0.32) return 'text-red-600';
      if (rate >= 0.16) return 'text-blue-600';
      return 'text-green-600';
    };

    return {
      activeTab,
      taxConfig,
      tabs,
      formatNumber,
      getReliefColour,
    };
  },
};
</script>
