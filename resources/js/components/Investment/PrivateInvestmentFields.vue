<template>
  <div>
    <!-- Company Details Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Company Details</h4>
      <div class="space-y-4">
        <div>
          <label for="company_legal_name" class="block text-sm font-medium text-neutral-500 mb-1">
            Company Legal Name          </label>
          <input
            id="company_legal_name"
            v-model="modelValue.company_legal_name"
            type="text"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            :class="{ 'border-raspberry-500': errors.company_legal_name }"
            placeholder="e.g., Acme Technologies Ltd"
          />
          <p v-if="errors.company_legal_name" class="mt-1 text-sm text-raspberry-600">{{ errors.company_legal_name }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="company_trading_name" class="block text-sm font-medium text-neutral-500 mb-1">
              Trading Name
            </label>
            <input
              id="company_trading_name"
              v-model="modelValue.company_trading_name"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="If different from legal name"
            />
          </div>
          <div>
            <label for="company_registration_number" class="block text-sm font-medium text-neutral-500 mb-1">
              Registration Number
            </label>
            <input
              id="company_registration_number"
              v-model="modelValue.company_registration_number"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., 12345678"
            />
          </div>
        </div>
        <div>
          <label for="company_website" class="block text-sm font-medium text-neutral-500 mb-1">
            Website
          </label>
          <input
            id="company_website"
            v-model="modelValue.company_website"
            type="url"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            placeholder="https://example.com"
          />
        </div>
        <div v-if="isCrowdfunding">
          <label for="crowdfunding_platform" class="block text-sm font-medium text-neutral-500 mb-1">
            Crowdfunding Platform          </label>
          <select
            id="crowdfunding_platform"
            v-model="modelValue.crowdfunding_platform"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            :class="{ 'border-raspberry-500': errors.crowdfunding_platform }"
          >
            <option value="">Select platform</option>
            <option value="Seedrs">Seedrs</option>
            <option value="Crowdcube">Crowdcube</option>
            <option value="Republic">Republic</option>
            <option value="Wefunder">Wefunder</option>
            <option value="other">Other</option>
          </select>
          <p v-if="errors.crowdfunding_platform" class="mt-1 text-sm text-raspberry-600">{{ errors.crowdfunding_platform }}</p>
        </div>
      </div>
    </div>

    <!-- Investment Details Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Investment Details</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="investment_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Investment Date            </label>
            <input
              id="investment_date"
              v-model="modelValue.investment_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              :class="{ 'border-raspberry-500': errors.investment_date }"
            />
            <p v-if="errors.investment_date" class="mt-1 text-sm text-raspberry-600">{{ errors.investment_date }}</p>
          </div>
          <div>
            <label for="investment_amount" class="block text-sm font-medium text-neutral-500 mb-1">
              Investment Amount            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="investment_amount"
                v-model.number="modelValue.investment_amount"
                type="number"
                min="0"
                step="100"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.investment_amount }"
                placeholder="10000"
              />
            </div>
            <p v-if="errors.investment_amount" class="mt-1 text-sm text-raspberry-600">{{ errors.investment_amount }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="funding_round" class="block text-sm font-medium text-neutral-500 mb-1">
              Funding Round
            </label>
            <select
              id="funding_round"
              v-model="modelValue.funding_round"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="">Select round</option>
              <option value="pre_seed">Pre-Seed</option>
              <option value="seed">Seed</option>
              <option value="series_a">Series A</option>
              <option value="series_b">Series B</option>
              <option value="series_c">Series C</option>
              <option value="bridge">Bridge</option>
              <option value="safe">SAFE</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label for="instrument_type" class="block text-sm font-medium text-neutral-500 mb-1">
              Instrument Type            </label>
            <select
              id="instrument_type"
              v-model="modelValue.instrument_type"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              :class="{ 'border-raspberry-500': errors.instrument_type }"
            >
              <option value="">Select type</option>
              <option value="ordinary_shares">Ordinary Shares</option>
              <option value="preference_shares">Preference Shares</option>
              <option value="convertible_loan_note">Convertible Loan Note</option>
              <option value="safe">SAFE</option>
              <option value="revenue_share">Revenue Share</option>
              <option value="fund_nominee_interest">Fund/Nominee Interest</option>
            </select>
            <p v-if="errors.instrument_type" class="mt-1 text-sm text-raspberry-600">{{ errors.instrument_type }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="number_of_shares" class="block text-sm font-medium text-neutral-500 mb-1">
              Number of Shares
            </label>
            <input
              id="number_of_shares"
              v-model.number="modelValue.number_of_shares"
              type="number"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="1000"
            />
          </div>
          <div>
            <label for="price_per_share" class="block text-sm font-medium text-neutral-500 mb-1">
              Price Per Share
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="price_per_share"
                v-model.number="modelValue.price_per_share"
                type="number"
                min="0"
                step="0.01"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="10.00"
              />
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="pre_money_valuation" class="block text-sm font-medium text-neutral-500 mb-1">
              Pre-Money Valuation
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="pre_money_valuation"
                v-model.number="modelValue.pre_money_valuation"
                type="number"
                min="0"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="1000000"
              />
            </div>
          </div>
          <div>
            <label for="post_money_valuation" class="block text-sm font-medium text-neutral-500 mb-1">
              Post-Money Valuation
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="post_money_valuation"
                v-model.number="modelValue.post_money_valuation"
                type="number"
                min="0"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="1500000"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Ownership & Legal Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Ownership & Legal</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="share_class" class="block text-sm font-medium text-neutral-500 mb-1">
              Share Class
            </label>
            <input
              id="share_class"
              v-model="modelValue.share_class"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., A Ordinary"
            />
          </div>
          <div>
            <label for="holding_structure" class="block text-sm font-medium text-neutral-500 mb-1">
              Holding Structure
            </label>
            <select
              id="holding_structure"
              v-model="modelValue.holding_structure"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="direct">Direct Shareholding</option>
              <option value="nominee">Nominee Held</option>
            </select>
          </div>
        </div>
        <div v-if="modelValue.holding_structure === 'nominee'">
          <label for="nominee_name" class="block text-sm font-medium text-neutral-500 mb-1">
            Nominee Name          </label>
          <input
            id="nominee_name"
            v-model="modelValue.nominee_name"
            type="text"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            :class="{ 'border-raspberry-500': errors.nominee_name }"
            placeholder="e.g., Seedrs Nominees Ltd"
          />
          <p v-if="errors.nominee_name" class="mt-1 text-sm text-raspberry-600">{{ errors.nominee_name }}</p>
        </div>
        <div class="flex flex-wrap gap-4">
          <label class="inline-flex items-center">
            <input
              v-model="modelValue.has_anti_dilution"
              type="checkbox"
              class="rounded border-horizon-300 text-violet-600 focus:ring-violet-500"
            />
            <span class="ml-2 text-sm text-neutral-500">Anti-Dilution Protection</span>
          </label>
        </div>
        <div v-if="isDebtInstrument" class="grid grid-cols-2 gap-4">
          <div>
            <label for="interest_rate" class="block text-sm font-medium text-neutral-500 mb-1">
              Interest Rate (%)
            </label>
            <input
              id="interest_rate"
              v-model.number="modelValue.interest_rate"
              type="number"
              min="0"
              max="100"
              step="0.1"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="8.0"
            />
          </div>
          <div>
            <label for="maturity_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Maturity Date
            </label>
            <input
              id="maturity_date"
              v-model="modelValue.maturity_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- UK Tax Relief Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <div class="bg-violet-50 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-violet-900 mb-3">UK Tax Relief</h4>
        <div class="space-y-4">
          <div>
            <label for="tax_relief_type" class="block text-sm font-medium text-violet-800 mb-1">
              Tax Relief Type
            </label>
            <select
              id="tax_relief_type"
              v-model="modelValue.tax_relief_type"
              class="w-full border border-violet-200 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
            >
              <option value="">No tax relief</option>
              <option value="eis">Enterprise Investment Scheme (30% relief)</option>
              <option value="seis">SEIS (50% relief)</option>
              <option value="sitr">SITR</option>
              <option value="vct">Venture Capital Trust</option>
              <option value="none">None / Not Eligible</option>
            </select>
          </div>
          <div v-if="requiresTaxReliefTracking" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label for="eis3_certificate_number" class="block text-sm font-medium text-violet-800 mb-1">
                  EIS3/SEIS3 Certificate Number
                </label>
                <input
                  id="eis3_certificate_number"
                  v-model="modelValue.eis3_certificate_number"
                  type="text"
                  class="w-full border border-violet-200 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                  placeholder="Certificate number"
                />
              </div>
              <div>
                <label for="hmrc_reference" class="block text-sm font-medium text-violet-800 mb-1">
                  HM Revenue & Customs Reference
                </label>
                <input
                  id="hmrc_reference"
                  v-model="modelValue.hmrc_reference"
                  type="text"
                  class="w-full border border-violet-200 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                  placeholder="HMRC reference"
                />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label for="relief_claimed_date" class="block text-sm font-medium text-violet-800 mb-1">
                  Date Relief Claimed
                </label>
                <input
                  id="relief_claimed_date"
                  v-model="modelValue.relief_claimed_date"
                  type="date"
                  class="w-full border border-violet-200 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                />
              </div>
              <div>
                <label for="relief_amount_claimed" class="block text-sm font-medium text-violet-800 mb-1">
                  Amount Claimed
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                  <input
                    id="relief_amount_claimed"
                    v-model.number="modelValue.relief_amount_claimed"
                    type="number"
                    min="0"
                    class="w-full border border-violet-200 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                    placeholder="3000"
                  />
                </div>
              </div>
            </div>
            <div class="bg-violet-50 border border-violet-200 rounded-md p-3">
              <p class="text-xs text-violet-800">
                <strong>Note:</strong> Enterprise Investment Scheme / Seed Enterprise Investment Scheme investments must be held for at least 3 years to retain tax relief.
                The disposal restriction date will be automatically calculated from your investment date.
              </p>
            </div>
            <label class="inline-flex items-center">
              <input
                v-model="modelValue.clawback_risk"
                type="checkbox"
                class="rounded border-violet-300 text-violet-600 focus:ring-violet-500"
              />
              <span class="ml-2 text-sm text-violet-800">Clawback Risk Flag</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Business Asset Disposal Relief Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <div class="bg-spring-50 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-spring-900 mb-3">Business Asset Disposal Relief (BADR)</h4>
        <p class="text-xs text-spring-700 mb-4">
          BADR reduces Capital Gains Tax to 14% (from 6 April 2025) on qualifying disposals, up to a £1 million lifetime limit.
        </p>
        <div class="space-y-4">
          <label class="flex items-start gap-3">
            <input
              v-model="modelValue.badr_eligible"
              type="checkbox"
              class="mt-1 rounded border-spring-300 text-spring-600 focus:ring-violet-500"
            />
            <div>
              <span class="text-sm font-medium text-spring-800">This investment may qualify for BADR</span>
              <p class="text-xs text-spring-600 mt-0.5">Check if you believe this investment meets the qualifying conditions</p>
            </div>
          </label>

          <div v-if="modelValue.badr_eligible" class="space-y-4 pt-3 border-t border-spring-200">
            <!-- Qualifying Conditions -->
            <div class="space-y-3">
              <p class="text-sm font-medium text-spring-800">Qualifying Conditions</p>

              <label class="flex items-start gap-3">
                <input
                  v-model="modelValue.badr_is_employee"
                  type="checkbox"
                  class="mt-1 rounded border-spring-300 text-spring-600 focus:ring-violet-500"
                />
                <div>
                  <span class="text-sm text-spring-800">Employee or officer of the company</span>
                  <p class="text-xs text-spring-600">You must be employed by or hold office in the company (or group company)</p>
                </div>
              </label>

              <label class="flex items-start gap-3">
                <input
                  v-model="modelValue.badr_trading_company"
                  type="checkbox"
                  class="mt-1 rounded border-spring-300 text-spring-600 focus:ring-violet-500"
                />
                <div>
                  <span class="text-sm text-spring-800">Trading company (not investment-focused)</span>
                  <p class="text-xs text-spring-600">The company must primarily conduct trading activities</p>
                </div>
              </label>

              <label class="flex items-start gap-3">
                <input
                  v-model="modelValue.badr_5_percent_holding"
                  type="checkbox"
                  class="mt-1 rounded border-spring-300 text-spring-600 focus:ring-violet-500"
                />
                <div>
                  <span class="text-sm text-spring-800">5% shareholding requirement met</span>
                  <p class="text-xs text-spring-600">You hold at least 5% of shares AND voting rights (not required for EMI shares)</p>
                </div>
              </label>

              <label class="flex items-start gap-3">
                <input
                  v-model="modelValue.badr_held_2_years"
                  type="checkbox"
                  class="mt-1 rounded border-spring-300 text-spring-600 focus:ring-violet-500"
                />
                <div>
                  <span class="text-sm text-spring-800">2-year qualifying period met</span>
                  <p class="text-xs text-spring-600">All conditions have been met for at least 2 years before disposal</p>
                </div>
              </label>

              <label class="flex items-start gap-3">
                <input
                  v-model="modelValue.badr_emi_shares"
                  type="checkbox"
                  class="mt-1 rounded border-spring-300 text-spring-600 focus:ring-violet-500"
                />
                <div>
                  <span class="text-sm text-spring-800">EMI share option scheme</span>
                  <p class="text-xs text-spring-600">Shares acquired under an Enterprise Management Incentive scheme (5% requirement waived)</p>
                </div>
              </label>
            </div>

            <!-- Lifetime Allowance Used -->
            <div>
              <label for="badr_lifetime_used" class="block text-sm font-medium text-spring-800 mb-1">
                BADR Lifetime Allowance Already Used (£)
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  id="badr_lifetime_used"
                  v-model.number="modelValue.badr_lifetime_used"
                  type="number"
                  min="0"
                  max="1000000"
                  step="1000"
                  class="w-full border border-spring-200 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                  placeholder="0"
                />
              </div>
              <p class="text-xs text-spring-600 mt-1">Lifetime limit is £1,000,000. Enter any amount you've already claimed.</p>
            </div>

            <!-- Info Box -->
            <div class="bg-spring-100 border border-spring-300 rounded-md p-3">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-spring-700 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-xs text-spring-800">
                  <p class="font-medium">Key Requirements:</p>
                  <ul class="mt-1 space-y-1 list-disc list-inside">
                    <li>Must be employee/officer AND conditions met for 2+ years before sale</li>
                    <li>Non-EMI shares require 5% shareholding AND voting rights</li>
                    <li>EMI shares only need option granted 2+ years before sale</li>
                    <li>Claims must be filed via Self Assessment by 31 January following disposal</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Status & Valuation Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Status & Current Valuation</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="company_status" class="block text-sm font-medium text-neutral-500 mb-1">
              Company Status
            </label>
            <select
              id="company_status"
              v-model="modelValue.company_status"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="active">Active</option>
              <option value="distressed">Distressed</option>
              <option value="dormant">Dormant</option>
              <option value="failed">Failed</option>
              <option value="exited">Exited</option>
            </select>
          </div>
          <div>
            <label for="current_ownership_percent" class="block text-sm font-medium text-neutral-500 mb-1">
              Current Ownership %
            </label>
            <input
              id="current_ownership_percent"
              v-model.number="modelValue.current_ownership_percent"
              type="number"
              min="0"
              max="100"
              step="0.01"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0.5"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="latest_valuation" class="block text-sm font-medium text-neutral-500 mb-1">
              Latest Valuation
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="latest_valuation"
                v-model.number="modelValue.latest_valuation"
                type="number"
                min="0"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="Your share value"
              />
            </div>
          </div>
          <div>
            <label for="latest_valuation_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Valuation Date
            </label>
            <input
              id="latest_valuation_date"
              v-model="modelValue.latest_valuation_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Exit Details Section (only if status is 'exited') -->
    <div v-if="showExitFields" class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Exit Details</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="exit_type" class="block text-sm font-medium text-neutral-500 mb-1">
              Exit Type
            </label>
            <select
              id="exit_type"
              v-model="modelValue.exit_type"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="">Select exit type</option>
              <option value="acquisition">Acquisition</option>
              <option value="secondary_sale">Secondary Sale</option>
              <option value="buyback">Buyback</option>
              <option value="ipo">IPO</option>
              <option value="liquidation">Liquidation</option>
            </select>
          </div>
          <div>
            <label for="exit_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Exit Date
            </label>
            <input
              id="exit_date"
              v-model="modelValue.exit_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label for="exit_gross_proceeds" class="block text-sm font-medium text-neutral-500 mb-1">
              Gross Proceeds
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="exit_gross_proceeds"
                v-model.number="modelValue.exit_gross_proceeds"
                type="number"
                min="0"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              />
            </div>
          </div>
          <div>
            <label for="exit_fees" class="block text-sm font-medium text-neutral-500 mb-1">
              Fees
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="exit_fees"
                v-model.number="modelValue.exit_fees"
                type="number"
                min="0"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              />
            </div>
          </div>
          <div>
            <label for="exit_net_proceeds" class="block text-sm font-medium text-neutral-500 mb-1">
              Net Proceeds
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="exit_net_proceeds"
                v-model.number="modelValue.exit_net_proceeds"
                type="number"
                min="0"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              />
            </div>
          </div>
        </div>
        <div class="flex flex-wrap gap-4">
          <label class="inline-flex items-center">
            <input
              v-model="modelValue.loss_relief_eligible"
              type="checkbox"
              class="rounded border-horizon-300 text-violet-600 focus:ring-violet-500"
            />
            <span class="ml-2 text-sm text-neutral-500">Loss Relief Eligible</span>
          </label>
          <label class="inline-flex items-center">
            <input
              v-model="modelValue.negligible_value_claim"
              type="checkbox"
              class="rounded border-horizon-300 text-violet-600 focus:ring-violet-500"
            />
            <span class="ml-2 text-sm text-neutral-500">Negligible Value Claim Filed</span>
          </label>
        </div>
        <div v-if="modelValue.loss_relief_eligible">
          <label for="capital_loss_amount" class="block text-sm font-medium text-neutral-500 mb-1">
            Capital Loss Amount
          </label>
          <div class="relative">
            <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
            <input
              id="capital_loss_amount"
              v-model.number="modelValue.capital_loss_amount"
              type="number"
              min="0"
              class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrivateInvestmentFields',

  props: {
    modelValue: {
      type: Object,
      required: true,
    },
    errors: {
      type: Object,
      default: () => ({}),
    },
    isCrowdfunding: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['update:modelValue'],

  computed: {
    requiresTaxReliefTracking() {
      return ['eis', 'seis', 'sitr', 'vct'].includes(this.modelValue.tax_relief_type);
    },

    isDebtInstrument() {
      return ['convertible_loan_note', 'safe'].includes(this.modelValue.instrument_type);
    },

    showExitFields() {
      return this.modelValue.company_status === 'exited';
    },
  },
};
</script>
