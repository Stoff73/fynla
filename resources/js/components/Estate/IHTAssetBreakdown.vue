<template>
  <!-- Asset Breakdown for One Person (User or Spouse) -->
  <template v-if="ownerData && ownerData.assets">
    <!-- Owner Assets Header -->
    <tr class="bg-white  cursor-pointer hover:bg-eggshell-500 select-none" @click="$emit('toggle-asset', ownerKey + '-all')">
      <td class="px-4 py-3 text-sm font-semibold text-horizon-500">
        <span class="inline-flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-all') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          {{ ownerData.name }}'s Assets
        </span>
      </td>
      <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(ownerData.total) }}</td>
      <td v-if="showMinus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(getProjectedMinus5(ownerData.total)) }}</td>
      <td class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(ownerData.projected_total) }}</td>
      <td v-if="showPlus5Years" class="px-4 py-3 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(getProjectedPlus5(ownerData.total)) }}</td>
    </tr>

    <template v-if="isExpanded(ownerKey + '-all')">
      <!-- Property Assets -->
      <template v-if="ownerData.assets.property?.length > 1">
        <tr class="bg-eggshell-500 cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-asset', ownerKey + '-property')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-property') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Property</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.assets.property.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(assetGroupTotal(ownerData.assets.property)) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(assetGroupTotal(ownerData.assets.property))) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(assetGroupProjectedTotal(ownerData.assets.property)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(assetGroupTotal(ownerData.assets.property))) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-property')">
          <tr v-for="(asset, index) in ownerData.assets.property" :key="ownerKey + '-property-' + index" class="bg-eggshell-500">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              {{ asset.name }}
              <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
              <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
            <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(asset, index) in ownerData.assets.property" :key="ownerKey + '-property-' + index" class="bg-eggshell-500">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">Property:</span> {{ asset.name }}
            <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
            <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
        </tr>
      </template>

      <!-- Investment Assets -->
      <template v-if="ownerData.assets.investment?.length > 1">
        <tr class="bg-eggshell-500 cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-asset', ownerKey + '-investment')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-investment') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Investment</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.assets.investment.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(assetGroupTotal(ownerData.assets.investment)) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(assetGroupTotal(ownerData.assets.investment))) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(assetGroupProjectedTotal(ownerData.assets.investment)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(assetGroupTotal(ownerData.assets.investment))) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-investment')">
          <tr v-for="(asset, index) in ownerData.assets.investment" :key="ownerKey + '-investment-' + index" class="bg-eggshell-500">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              {{ asset.name }}
              <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
              <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
            <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(asset, index) in ownerData.assets.investment" :key="ownerKey + '-investment-' + index" class="bg-eggshell-500">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">Investment:</span> {{ asset.name }}
            <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
            <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
        </tr>
      </template>

      <!-- Cash/Savings Assets -->
      <template v-if="ownerData.assets.cash?.length > 1">
        <tr class="bg-eggshell-500 cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-asset', ownerKey + '-cash')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-cash') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Cash/Savings</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.assets.cash.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(assetGroupTotal(ownerData.assets.cash)) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(assetGroupTotal(ownerData.assets.cash))) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(assetGroupProjectedTotal(ownerData.assets.cash)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(assetGroupTotal(ownerData.assets.cash))) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-cash')">
          <tr v-for="(asset, index) in ownerData.assets.cash" :key="ownerKey + '-cash-' + index" class="bg-eggshell-500">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              {{ asset.name }}
              <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
              <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
            <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(asset, index) in ownerData.assets.cash" :key="ownerKey + '-cash-' + index" class="bg-eggshell-500">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">Cash/Savings:</span> {{ asset.name }}
            <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
            <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
        </tr>
      </template>

      <!-- Business Assets -->
      <template v-if="ownerData.assets.business?.length > 1">
        <tr class="bg-eggshell-500 cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-asset', ownerKey + '-business')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-business') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Business</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.assets.business.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(assetGroupTotal(ownerData.assets.business)) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(assetGroupTotal(ownerData.assets.business))) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(assetGroupProjectedTotal(ownerData.assets.business)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(assetGroupTotal(ownerData.assets.business))) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-business')">
          <tr v-for="(asset, index) in ownerData.assets.business" :key="ownerKey + '-business-' + index" class="bg-eggshell-500">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              {{ asset.name }}
              <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
              <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
            <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(asset, index) in ownerData.assets.business" :key="ownerKey + '-business-' + index" class="bg-eggshell-500">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">Business:</span> {{ asset.name }}
            <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
            <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
        </tr>
      </template>

      <!-- Chattel (Personal Valuables) Assets -->
      <template v-if="ownerData.assets.chattel?.length > 1">
        <tr class="bg-eggshell-500 cursor-pointer hover:bg-savannah-100 select-none" @click="$emit('toggle-asset', ownerKey + '-chattel')">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="inline-flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-horizon-400 transition-transform mr-1" :class="{ 'rotate-90': isExpanded(ownerKey + '-chattel') }"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
              <span class="text-sm text-neutral-500">Personal Valuables</span>
              <span class="ml-1 text-xs text-horizon-400">({{ ownerData.assets.chattel.length }})</span>
            </span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(assetGroupTotal(ownerData.assets.chattel)) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(assetGroupTotal(ownerData.assets.chattel))) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(assetGroupProjectedTotal(ownerData.assets.chattel)) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(assetGroupTotal(ownerData.assets.chattel))) }}</td>
        </tr>
        <template v-if="isExpanded(ownerKey + '-chattel')">
          <tr v-for="(asset, index) in ownerData.assets.chattel" :key="ownerKey + '-chattel-' + index" class="bg-eggshell-500">
            <td class="px-4 py-2 text-sm text-neutral-500 pl-12">
              {{ asset.name }}
              <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
              <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
            </td>
            <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
            <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
            <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
            <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
          </tr>
        </template>
      </template>
      <template v-else>
        <tr v-for="(asset, index) in ownerData.assets.chattel" :key="ownerKey + '-chattel-' + index" class="bg-eggshell-500">
          <td class="px-4 py-2 text-sm text-neutral-500 pl-8">
            <span class="text-sm text-neutral-500">Valuable:</span> {{ asset.name }}
            <span v-if="asset.is_joint" class="ml-2 text-xs text-neutral-500 font-medium">(Joint)</span>
            <span v-else-if="asset.ownership_type === 'tenants_in_common'" class="ml-2 text-xs text-neutral-500 font-medium">(Tenancy in Common{{ asset.ownership_percentage ? ' - ' + asset.ownership_percentage + '%' : '' }})</span>
          </td>
          <td class="px-4 py-2 text-sm text-right text-neutral-500">{{ formatCurrency(asset.value) }}</td>
          <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedMinus5(asset.value)) }}</td>
          <td class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(asset.projected_value) }}</td>
          <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right text-horizon-500">{{ formatCurrency(getProjectedPlus5(asset.value)) }}</td>
        </tr>
      </template>

      <!-- Assets Subtotal -->
      <tr class="bg-white ">
        <td class="px-4 py-2 text-sm font-semibold text-horizon-500 pl-8">{{ subtotalLabel }}</td>
        <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(ownerData.total) }}</td>
        <td v-if="showMinus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(getProjectedMinus5(ownerData.total)) }}</td>
        <td class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(ownerData.projected_total) }}</td>
        <td v-if="showPlus5Years" class="px-4 py-2 text-sm text-right font-semibold text-horizon-500">{{ formatCurrency(getProjectedPlus5(ownerData.total)) }}</td>
      </tr>
    </template>
  </template>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'IHTAssetBreakdown',

  mixins: [currencyMixin],

  props: {
    ownerKey: {
      type: String,
      required: true, // 'user' or 'spouse'
    },
    ownerData: {
      type: Object,
      required: true,
      // Expected structure: { name: String, total: Number, projected_total: Number, assets: { property: [], investment: [], cash: [], business: [], chattel: [] } }
    },
    showMinus5Years: {
      type: Boolean,
      default: false,
    },
    showPlus5Years: {
      type: Boolean,
      default: false,
    },
    expandedAssets: {
      type: Object,
      required: true,
    },
    getProjectedMinus5: {
      type: Function,
      required: true,
    },
    getProjectedPlus5: {
      type: Function,
      required: true,
    },
    subtotalLabel: {
      type: String,
      default: 'Subtotal',
    },
  },

  emits: ['toggle-asset'],

  methods: {
    isExpanded(key) {
      return !!this.expandedAssets[key];
    },

    assetGroupTotal(assets) {
      return (assets || []).reduce((sum, a) => sum + (a.value || 0), 0);
    },

    assetGroupProjectedTotal(assets) {
      // Use nullish coalescing to handle 0 as valid (cash may project to 0)
      return (assets || []).reduce((sum, a) => sum + (a.projected_value ?? a.value ?? 0), 0);
    },
  },
};
</script>
