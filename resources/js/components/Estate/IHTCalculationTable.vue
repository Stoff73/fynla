<template>
  <div class="overflow-x-auto">
    <!-- Expand/Collapse All Button -->
    <div class="flex justify-end mb-2">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100 rounded-md transition-colors"
        @click="toggleExpandAll"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
          <path v-if="allExpanded" stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
        {{ allExpanded ? 'Collapse All' : 'Expand All' }}
      </button>
    </div>
    <table class="min-w-full divide-y divide-light-gray">
      <thead class="bg-eggshell-500">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ firstColumnHeader }}</th>
          <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Now</th>
          <th v-if="showMinus5Years" class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            <div>Age {{ projectionMinus5Age }}</div>
            <div class="text-[10px] font-normal text-horizon-400 normal-case mt-0.5">-5 years</div>
          </th>
          <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            <div>Age {{ estimatedAge }}</div>
            <div class="text-[10px] font-normal text-horizon-400 normal-case mt-0.5">Life expectancy</div>
          </th>
          <th v-if="showPlus5Years" class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
            <div>Age {{ projectionPlus5Age }}</div>
            <div class="text-[10px] font-normal text-horizon-400 normal-case mt-0.5">+5 years</div>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-light-gray">
        <!-- User Assets Section -->
        <IHTAssetBreakdown
          v-if="assetsBreakdown.user"
          owner-key="user"
          :owner-data="assetsBreakdown.user"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-assets="expandedAssets"
          :get-projected-minus-5="getProjectedValueMinus5"
          :get-projected-plus-5="getProjectedValuePlus5"
          :subtotal-label="showSpouse ? 'Subtotal' : 'Assets Subtotal'"
          @toggle-asset="toggleAssetGroup"
        />

        <!-- Spouse Assets Section -->
        <IHTAssetBreakdown
          v-if="showSpouse && assetsBreakdown.spouse"
          owner-key="spouse"
          :owner-data="assetsBreakdown.spouse"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-assets="expandedAssets"
          :get-projected-minus-5="getProjectedValueMinus5"
          :get-projected-plus-5="getProjectedValuePlus5"
          subtotal-label="Subtotal"
          @toggle-asset="toggleAssetGroup"
        />

        <!-- Total Gross Assets -->
        <tr :class="showSpouse ? 'bg-white ' : 'bg-white  border-t-2 border-horizon-300'">
          <td class="px-4 py-3 text-sm font-bold text-horizon-500">Total Gross Assets</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.grossAssets.plus5) }}</td>
        </tr>

        <!-- User Liabilities Section -->
        <IHTLiabilityBreakdown
          v-if="liabilitiesBreakdown.user"
          owner-key="user"
          :owner-data="liabilitiesBreakdown.user"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-liabilities="expandedLiabilities"
          :subtotal-label="showSpouse ? 'Subtotal' : 'Liabilities Subtotal'"
          @toggle-liability="toggleLiabilityGroup"
        />

        <!-- Spouse Liabilities Section -->
        <IHTLiabilityBreakdown
          v-if="showSpouse && liabilitiesBreakdown.spouse"
          owner-key="spouse"
          :owner-data="liabilitiesBreakdown.spouse"
          :show-minus-5-years="showMinus5Years"
          :show-plus-5-years="showPlus5Years"
          :expanded-liabilities="expandedLiabilities"
          subtotal-label="Subtotal"
          @toggle-liability="toggleLiabilityGroup"
        />

        <!-- Total Liabilities -->
        <tr :class="showSpouse ? 'bg-white ' : 'bg-white  border-t-2 border-horizon-300'">
          <td class="px-4 py-3 text-sm font-bold text-horizon-500">{{ showSpouse ? 'Less: Total Liabilities' : 'Total Liabilities' }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatLiability(totals.liabilities.plus5) }}</td>
        </tr>

        <!-- Net Estate -->
        <tr class="bg-white ">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">Net Estate</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(totals.netEstate.plus5) }}</td>
        </tr>

        <!-- ============================================== -->
        <!-- CHARITABLE BEQUEST OFF: Combined Allowances   -->
        <!-- ============================================== -->
        <template v-if="!charitableBequest">
          <!-- Allowances Section Header (Collapsible) -->
          <tr class="bg-white  cursor-pointer hover:bg-eggshell-500 select-none" @click="toggleAllowances">
            <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
              <span class="inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': expandedAllowances }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                Less: Tax-Free Allowances
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
          </tr>

          <!-- Allowances Detail (Expanded) - Combined NRB + RNRB -->
          <template v-if="expandedAllowances">
            <!-- NRB Allowances -->
            <template v-if="showSpouse">
              <template v-if="allowances.showSeparateSpouseAllowances">
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Tax-Free Allowance (Individual)</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                </tr>
                <tr v-if="allowances.nrbFromSpouse > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
                    Tax-Free Allowance from Spouse
                    <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-neutral-500 font-normal">(Default)</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                </tr>
              </template>
              <template v-else>
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.user?.name }}'s Tax-Free Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                </tr>
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.spouse?.name }}'s Tax-Free Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                </tr>
              </template>
            </template>
            <!-- Single user with transferred allowances (widow/widower) -->
            <template v-else-if="allowances.showSeparateSpouseAllowances">
              <tr class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.user?.name || 'Your' }} Tax-Free Allowance</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
              </tr>
              <tr v-if="allowances.nrbFromSpouse > 0" class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Transferred from Late Spouse's Estate</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
              </tr>
            </template>
            <!-- Single user without transferred allowances -->
            <template v-else>
              <tr class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Tax-Free Allowance (Nil Rate Band)</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
              </tr>
            </template>

            <!-- RNRB Allowances -->
            <template v-if="allowances.rnrbEligible && allowances.totalRnrb > 0">
              <template v-if="showSpouse && allowances.showSeparateSpouseAllowances">
                <tr v-if="allowances.rnrbIndividual > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Home Allowance (Individual)</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                </tr>
                <tr v-if="allowances.rnrbFromSpouse > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
                    Home Allowance from Spouse
                    <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-neutral-500 font-normal">(Default)</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                </tr>
              </template>
              <template v-else-if="showSpouse">
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.user?.name }}'s Home Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                </tr>
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.spouse?.name }}'s Home Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                </tr>
              </template>
              <!-- Single user with transferred RNRB (widow/widower) -->
              <template v-else-if="allowances.showSeparateSpouseAllowances">
                <tr v-if="allowances.rnrbIndividual > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.user?.name || 'Your' }} Home Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                </tr>
                <tr v-if="allowances.rnrbFromSpouse > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Transferred from Late Spouse's Estate</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                </tr>
              </template>
              <!-- Single user without transferred RNRB -->
              <template v-else>
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Home Allowance (Residence Nil Rate Band)</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                </tr>
              </template>
            </template>

            <!-- Residence Nil Rate Band Not Available Message -->
            <tr v-if="!allowances.rnrbEligible" class="bg-eggshell-500">
              <td :colspan="columnCount" class="px-4 py-2 text-xs text-neutral-500 pl-8">
                <strong>Note:</strong> Home allowance not available - no main residence identified or not left to direct descendants
              </td>
            </tr>

            <!-- RNRB Taper Warning -->
            <tr v-if="allowances.rnrbTapered" class="bg-eggshell-500">
              <td :colspan="columnCount" class="px-4 py-2 text-xs text-neutral-500 pl-8">
                <strong>Home Allowance Reduced:</strong> Estate value exceeds {{ formatCurrency(allowances.rnrbTaperThreshold || 2000000) }} threshold.
                <span v-if="allowances.totalRnrb === 0">Allowance completely removed.</span>
                <span v-else>Reduced by {{ formatCurrency(allowances.rnrbTaperAmount || 0) }}.</span>
              </td>
            </tr>

            <!-- Allowances Subtotal -->
            <tr class="bg-white ">
              <td class="px-4 py-2 text-sm font-semibold text-horizon-500 pl-8">Subtotal</td>
              <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
              <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(totalAllowances) }}</td>
            </tr>
          </template>
        </template>

        <!-- ============================================== -->
        <!-- CHARITABLE BEQUEST ON: Split Allowances       -->
        <!-- Order: NRB -> Baseline -> Charitable -> RNRB  -->
        <!-- ============================================== -->
        <template v-else>
          <!-- NRB Section Header (Collapsible) -->
          <tr class="bg-white  cursor-pointer hover:bg-eggshell-500 select-none" @click="toggleNRB">
            <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
              <span class="inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': expandedNRB }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                Less: Tax-Free Allowance (Nil Rate Band)
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalNrb) }}</td>
          </tr>

          <!-- NRB Detail (Expanded) -->
          <template v-if="expandedNRB">
            <template v-if="showSpouse && allowances.showSeparateSpouseAllowances">
              <tr class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Tax-Free Allowance (Individual)</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrb) }}</td>
              </tr>
              <tr v-if="allowances.nrbFromSpouse > 0" class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
                  Tax-Free Allowance from Spouse
                  <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-neutral-500 font-normal">(Default)</span>
                </td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.nrbFromSpouse) }}</td>
              </tr>
            </template>
            <template v-else-if="showSpouse">
              <tr class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.user?.name }}'s Tax-Free Allowance</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
              </tr>
              <tr class="bg-eggshell-500">
                <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.spouse?.name }}'s Tax-Free Allowance</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
                <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(IHT_NIL_RATE_BAND) }}</td>
              </tr>
            </template>
          </template>

          <!-- Estate after NRB (charitable bequest baseline) -->
          <tr class="bg-violet-50 ">
            <td class="px-4 py-3 text-sm font-semibold text-violet-800">
              Estate after Tax-Free Allowance{{ showSpouse ? 's' : '' }}
              <span class="block text-xs font-normal text-violet-600 mt-0.5">Charitable bequest baseline (10% calculated from this)</span>
            </td>
            <td class="px-4 py-3 text-sm text-right font-bold text-violet-800">{{ formatCurrency(estateAfterNRB.now) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-violet-800">{{ formatCurrency(estateAfterNRB.minus5) }}</td>
            <td class="px-4 py-3 text-sm text-right font-bold text-violet-800">{{ formatCurrency(estateAfterNRB.projected) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-violet-800">{{ formatCurrency(estateAfterNRB.plus5) }}</td>
          </tr>

          <!-- Charitable Bequest (deducted from estate) -->
          <tr class="bg-spring-50 ">
            <td class="px-4 py-3 text-sm font-semibold text-spring-800">
              Less: Charitable Bequest (10% minimum)
              <span class="block text-xs font-normal text-spring-600 mt-0.5">Deducted from estate, qualifies for 36% rate</span>
            </td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-spring-800">-{{ formatCurrency(charitableDonation.now) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-spring-800">-{{ formatCurrency(charitableDonation.minus5) }}</td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-spring-800">-{{ formatCurrency(charitableDonation.projected) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-spring-800">-{{ formatCurrency(charitableDonation.plus5) }}</td>
          </tr>

          <!-- RNRB Section Header (Collapsible) - only if eligible -->
          <template v-if="allowances.rnrbEligible && allowances.totalRnrb > 0">
            <tr class="bg-white  cursor-pointer hover:bg-eggshell-500 select-none" @click="toggleRNRB">
              <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
                <span class="inline-flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': expandedRNRB }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                  Less: Home Allowance (Residence Nil Rate Band)
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
              <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
              <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
            </tr>

            <!-- RNRB Detail (Expanded) -->
            <template v-if="expandedRNRB">
              <template v-if="showSpouse && allowances.showSeparateSpouseAllowances">
                <tr v-if="allowances.rnrbIndividual > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Home Allowance (Individual)</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbIndividual) }}</td>
                </tr>
                <tr v-if="allowances.rnrbFromSpouse > 0" class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
                    Home Allowance from Spouse
                    <span v-if="!hasSpouseLinked" class="ml-2 text-xs text-neutral-500 font-normal">(Default)</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.rnrbFromSpouse) }}</td>
                </tr>
              </template>
              <template v-else-if="showSpouse">
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.user?.name }}'s Home Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                </tr>
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">{{ assetsBreakdown.spouse?.name }}'s Home Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb / 2) }}</td>
                </tr>
              </template>
              <template v-else>
                <tr class="bg-eggshell-500">
                  <td class="px-4 py-2 text-sm text-neutral-500 pl-8">Home Allowance</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                  <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                  <td class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                  <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-neutral-500">-{{ formatCurrency(allowances.totalRnrb) }}</td>
                </tr>
              </template>

              <!-- RNRB Taper Warning -->
              <tr v-if="allowances.rnrbTapered" class="bg-eggshell-500">
                <td :colspan="columnCount" class="px-4 py-2 text-xs text-neutral-500 pl-8">
                  <strong>Home Allowance Reduced:</strong> Estate value exceeds {{ formatCurrency(allowances.rnrbTaperThreshold || 2000000) }} threshold.
                  <span v-if="allowances.totalRnrb === 0">Allowance completely removed.</span>
                  <span v-else>Reduced by {{ formatCurrency(allowances.rnrbTaperAmount || 0) }}.</span>
                </td>
              </tr>
            </template>
          </template>

          <!-- Residence Nil Rate Band Not Available Message -->
          <tr v-else class="bg-eggshell-500">
            <td :colspan="columnCount" class="px-4 py-2 text-xs text-neutral-500">
              <strong>Note:</strong> Home allowance not available - no main residence identified or not left to direct descendants
            </td>
          </tr>
        </template>

        <!-- Taxable Estate -->
        <tr class="bg-eggshell-500">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">Taxable Estate</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(taxableEstate.plus5) }}</td>
        </tr>

        <!-- Inheritance Tax Liability -->
        <tr class="bg-white ">
          <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
            Inheritance Tax Liability ({{ effectiveIHTRateLabel }})
            <span v-if="charitableBequest" class="ml-2 text-xs font-normal text-spring-600">(Reduced rate)</span>
          </td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.now) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.minus5) }}</td>
          <td class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.projected) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-bold text-horizon-500">{{ formatCurrency(ihtLiability.plus5) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { IHT_NIL_RATE_BAND } from '@/constants/taxConfig';
import IHTAssetBreakdown from './IHTAssetBreakdown.vue';
import IHTLiabilityBreakdown from './IHTLiabilityBreakdown.vue';

export default {
  name: 'IHTCalculationTable',

  mixins: [currencyMixin],

  components: {
    IHTAssetBreakdown,
    IHTLiabilityBreakdown,
  },

  props: {
    // Data
    assetsBreakdown: {
      type: Object,
      required: true,
      // Expected: { user: {...}, spouse?: {...} }
    },
    liabilitiesBreakdown: {
      type: Object,
      required: true,
      // Expected: { user: {...}, spouse?: {...} }
    },

    // Totals (pre-computed in parent)
    totals: {
      type: Object,
      required: true,
      // Expected: { grossAssets: {now, minus5, projected, plus5}, liabilities: {...}, netEstate: {...} }
    },

    // Allowances
    allowances: {
      type: Object,
      required: true,
      // Expected: { nrb, nrbFromSpouse, totalNrb, rnrbIndividual, rnrbFromSpouse, totalRnrb, rnrbEligible, rnrbTapered, rnrbTaperThreshold, rnrbTaperAmount, showSeparateSpouseAllowances }
    },

    // Estate after NRB (pre-computed in parent)
    estateAfterNRB: {
      type: Object,
      required: true,
      // Expected: { now, minus5, projected, plus5 }
    },

    // Taxable estate (pre-computed in parent)
    taxableEstate: {
      type: Object,
      required: true,
      // Expected: { now, minus5, projected, plus5 }
    },

    // IHT Liability (pre-computed in parent, accounts for charitable bequest)
    ihtLiability: {
      type: Object,
      required: true,
      // Expected: { now, minus5, projected, plus5 }
    },

    // Charitable bequest
    charitableBequest: {
      type: Boolean,
      default: false,
    },
    charitableDonation: {
      type: Object,
      default: () => ({ now: 0, minus5: 0, projected: 0, plus5: 0 }),
    },
    effectiveIHTRateLabel: {
      type: String,
      default: '40%',
    },

    // Display options
    showSpouse: {
      type: Boolean,
      default: false,
    },
    hasSpouseLinked: {
      type: Boolean,
      default: false,
    },
    estimatedAge: {
      type: Number,
      required: true,
    },
    projectionMinus5Age: {
      type: Number,
      default: 0,
    },
    projectionPlus5Age: {
      type: Number,
      default: 0,
    },

    // Column visibility
    showMinus5Years: {
      type: Boolean,
      default: false,
    },
    showPlus5Years: {
      type: Boolean,
      default: false,
    },

    // Growth rate for projections
    growthRate: {
      type: Number,
      default: 0.047,
    },
    yearsToDeathMinus5: {
      type: Number,
      default: 0,
    },
    yearsToDeathPlus5: {
      type: Number,
      default: 0,
    },

    // Table header customization
    firstColumnHeader: {
      type: String,
      default: 'Line Item',
    },
  },

  emits: ['toggle-minus-5', 'toggle-plus-5'],

  data() {
    return {
      expandedAssets: {},
      expandedLiabilities: {},
      expandedAllowances: false,
      expandedNRB: false,
      expandedRNRB: false,
      IHT_NIL_RATE_BAND,
    };
  },

  computed: {
    columnCount() {
      return 2 + (this.showMinus5Years ? 1 : 0) + (this.showPlus5Years ? 1 : 0);
    },
    totalAllowances() {
      return this.allowances.totalNrb + this.allowances.totalRnrb;
    },
    allExpanded() {
      // Check if all expandable sections are expanded
      const hasExpandedAssets = Object.keys(this.expandedAssets).length > 0 &&
        Object.values(this.expandedAssets).some(v => v);
      const hasExpandedLiabilities = Object.keys(this.expandedLiabilities).length > 0 &&
        Object.values(this.expandedLiabilities).some(v => v);
      const hasExpandedAllowances = this.expandedAllowances || this.expandedNRB || this.expandedRNRB;

      return hasExpandedAssets || hasExpandedLiabilities || hasExpandedAllowances;
    },
  },

  methods: {
    toggleAssetGroup(key) {
      this.expandedAssets = { ...this.expandedAssets, [key]: !this.expandedAssets[key] };
    },

    toggleLiabilityGroup(key) {
      this.expandedLiabilities = { ...this.expandedLiabilities, [key]: !this.expandedLiabilities[key] };
    },

    toggleAllowances() {
      this.expandedAllowances = !this.expandedAllowances;
    },

    toggleNRB() {
      this.expandedNRB = !this.expandedNRB;
    },

    toggleRNRB() {
      this.expandedRNRB = !this.expandedRNRB;
    },

    toggleExpandAll() {
      const shouldExpand = !this.allExpanded;

      // Toggle all asset groups (keys use format: ownerKey-type)
      const assetKeys = [
        'user-all', 'user-property', 'user-investment', 'user-cash', 'user-business', 'user-chattel',
        'spouse-all', 'spouse-property', 'spouse-investment', 'spouse-cash', 'spouse-business', 'spouse-chattel'
      ];
      const newExpandedAssets = {};
      assetKeys.forEach(key => {
        newExpandedAssets[key] = shouldExpand;
      });
      this.expandedAssets = newExpandedAssets;

      // Toggle all liability groups (keys use format: ownerKey-type)
      const liabilityKeys = ['user-all', 'user-mortgages', 'user-other', 'spouse-all', 'spouse-mortgages', 'spouse-other'];
      const newExpandedLiabilities = {};
      liabilityKeys.forEach(key => {
        newExpandedLiabilities[key] = shouldExpand;
      });
      this.expandedLiabilities = newExpandedLiabilities;

      // Toggle allowances
      this.expandedAllowances = shouldExpand;
      this.expandedNRB = shouldExpand;
      this.expandedRNRB = shouldExpand;
    },

    getProjectedValueMinus5(currentValue) {
      return currentValue * Math.pow(1 + this.growthRate, this.yearsToDeathMinus5);
    },

    getProjectedValuePlus5(currentValue) {
      return currentValue * Math.pow(1 + this.growthRate, this.yearsToDeathPlus5);
    },
  },
};
</script>
