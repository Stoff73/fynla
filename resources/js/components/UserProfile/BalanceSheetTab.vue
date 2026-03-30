<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-horizon-500">Balance Sheet</h2>
      <p class="mt-1 text-body-sm text-neutral-500">
        A snapshot of your assets and liabilities as of {{ formatDate(asOfDate) }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500 mx-auto"></div>
        <p class="mt-4 text-body-base text-neutral-500">Loading balance sheet...</p>
      </div>
    </div>

    <div v-else-if="hasData" class="space-y-6">
      <!-- Assets Section -->
      <div class="card p-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-h5 font-semibold text-success-700" style="width: 40%">Assets</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">{{ userName }}</th>
              <th v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">{{ spouseName }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">Combined</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-light-gray">
            <!-- Property Group Header with Sub-total -->
            <tr class="bg-savannah-100">
              <td class="px-3 py-2 text-body-sm font-semibold text-neutral-500" style="width: 40%">Property</td>
              <td class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(propertySubtotal.user) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(propertySubtotal.spouse) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(propertySubtotal.combined) }}
              </td>
            </tr>
            <tr v-for="(item, index) in propertyAssets" :key="'property-' + index">
              <td class="px-3 py-2 text-body-sm text-neutral-500 pl-6">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.spouseAmount) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount + item.spouseAmount) }}
              </td>
            </tr>

            <!-- Investments Group Header with Sub-total -->
            <tr class="bg-savannah-100">
              <td class="px-3 py-2 text-body-sm font-semibold text-neutral-500">Investments (incl. Pensions)</td>
              <td class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(investmentSubtotal.user) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(investmentSubtotal.spouse) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(investmentSubtotal.combined) }}
              </td>
            </tr>
            <tr v-for="(item, index) in investmentAssets" :key="'investment-' + index">
              <td class="px-3 py-2 text-body-sm text-neutral-500 pl-6">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.spouseAmount) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount + item.spouseAmount) }}
              </td>
            </tr>

            <!-- Cash Group Header with Sub-total -->
            <tr class="bg-savannah-100">
              <td class="px-3 py-2 text-body-sm font-semibold text-neutral-500">Cash</td>
              <td class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(cashSubtotal.user) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(cashSubtotal.spouse) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500">
                {{ formatCurrency(cashSubtotal.combined) }}
              </td>
            </tr>
            <tr v-for="(item, index) in cashAssets" :key="'cash-' + index">
              <td class="px-3 py-2 text-body-sm text-neutral-500 pl-6">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.spouseAmount) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount + item.spouseAmount) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-success-50 border-t-2 border-success-300">
              <td class="px-3 py-3 text-body-base font-bold text-success-800">Total Assets</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(totalAssets.user) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(totalAssets.spouse) }}
              </td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-success-700">
                {{ formatCurrency(totalAssets.combined) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Liabilities Section -->
      <div class="card p-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-h5 font-semibold text-raspberry-700" style="width: 40%">Liabilities</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">{{ userName }}</th>
              <th v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">{{ spouseName }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">Combined</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-light-gray">
            <tr v-for="(item, index) in allLiabilities" :key="'liability-' + index">
              <td class="px-3 py-2 text-body-sm text-neutral-500" style="width: 40%">{{ item.line_item }}</td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.spouseAmount) }}
              </td>
              <td class="px-3 py-2 text-right text-body-sm font-medium text-horizon-500">
                {{ formatCurrency(item.userAmount + item.spouseAmount) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-raspberry-50 border-t-2 border-raspberry-300">
              <td class="px-3 py-3 text-body-base font-bold text-raspberry-800">Total Liabilities</td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                {{ formatCurrency(totalLiabilities.user) }}
              </td>
              <td v-if="hasSpouse" class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                {{ formatCurrency(totalLiabilities.spouse) }}
              </td>
              <td class="px-3 py-3 text-right text-h5 font-bold text-raspberry-700">
                {{ formatCurrency(totalLiabilities.combined) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Net Worth Section -->
      <div class="card p-6 bg-gradient-to-r from-raspberry-50 to-raspberry-100 overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-body-sm font-semibold text-horizon-500" style="width: 40%">Net Worth</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">{{ userName }}</th>
              <th v-if="hasSpouse" class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">{{ spouseName }}</th>
              <th class="px-3 py-2 text-right text-body-sm font-semibold text-horizon-500" style="width: 20%">Combined</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="px-3 py-3 text-body-base font-bold text-horizon-500" style="width: 40%">Total</td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="netWorth.user >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                >
                  {{ formatCurrency(netWorth.user) }}
                </p>
              </td>
              <td v-if="hasSpouse" class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="netWorth.spouse >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                >
                  {{ formatCurrency(netWorth.spouse) }}
                </p>
              </td>
              <td class="px-3 py-3 text-right">
                <p
                  class="text-h5 font-bold"
                  :class="netWorth.combined >= 0 ? 'text-success-700' : 'text-raspberry-700'"
                >
                  {{ formatCurrency(netWorth.combined) }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card p-8 text-center">
      <p class="text-body-base text-neutral-500">
        No data available. Please add assets and liabilities to your profile.
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import userProfileService from '@/services/userProfileService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'BalanceSheetTab',

  setup() {
    const store = useStore();
    const loading = ref(true);
    const userData = ref(null);
    const spouseData = ref(null);

    const user = computed(() => store.getters['userProfile/user']);
    const spouse = computed(() => store.getters['userProfile/spouse']);

    const asOfDate = computed(() => new Date().toISOString().split('T')[0]);

    const userName = computed(() => {
      if (!user.value) return 'You';
      const firstName = user.value.first_name || '';
      // User model uses surname, FamilyMember uses last_name
      const lastName = user.value.last_name || user.value.surname || '';
      return `${firstName} ${lastName}`.trim() || 'You';
    });

    const spouseName = computed(() => {
      if (!spouse.value) return 'Spouse';
      const firstName = spouse.value.first_name || '';
      // FamilyMember uses last_name, User uses surname
      const lastName = spouse.value.last_name || spouse.value.surname || '';
      return `${firstName} ${lastName}`.trim() || 'Spouse';
    });

    const hasSpouse = computed(() => !!spouseData.value);
    const hasData = computed(() => userData.value !== null);

    const formatDate = (date) => {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    // Get all unique line items from both user and spouse for a category
    const getMergedAssets = (category) => {
      const userAssets = userData.value?.assets?.filter(a => a.category === category) || [];
      const spouseAssets = spouseData.value?.assets?.filter(a => a.category === category) || [];

      const allLineItems = new Map();

      userAssets.forEach(item => {
        allLineItems.set(item.line_item, {
          line_item: item.line_item,
          userAmount: Number(item.amount) || 0,
          spouseAmount: 0,
        });
      });

      spouseAssets.forEach(item => {
        if (allLineItems.has(item.line_item)) {
          allLineItems.get(item.line_item).spouseAmount = Number(item.amount) || 0;
        } else {
          allLineItems.set(item.line_item, {
            line_item: item.line_item,
            userAmount: 0,
            spouseAmount: Number(item.amount) || 0,
          });
        }
      });

      return Array.from(allLineItems.values());
    };

    const propertyAssets = computed(() => getMergedAssets('property'));

    // Investment assets include 'investment', 'pension' (excluding state pension), 'business', and 'chattel' categories
    const investmentAssets = computed(() => {
      const investments = getMergedAssets('investment');
      const pensions = getMergedAssets('pension').filter(p =>
        !p.line_item.toLowerCase().includes('state pension')
      );
      const businesses = getMergedAssets('business');
      const chattels = getMergedAssets('chattel');
      return [...investments, ...pensions, ...businesses, ...chattels];
    });

    const cashAssets = computed(() => getMergedAssets('cash'));

    const calculateSubtotal = (assets) => {
      return {
        user: assets.reduce((sum, item) => sum + (item.userAmount || 0), 0),
        spouse: assets.reduce((sum, item) => sum + (item.spouseAmount || 0), 0),
        combined: assets.reduce((sum, item) => sum + (item.userAmount || 0) + (item.spouseAmount || 0), 0),
      };
    };

    const propertySubtotal = computed(() => calculateSubtotal(propertyAssets.value));
    const investmentSubtotal = computed(() => calculateSubtotal(investmentAssets.value));
    const cashSubtotal = computed(() => calculateSubtotal(cashAssets.value));

    const totalAssets = computed(() => ({
      user: propertySubtotal.value.user + investmentSubtotal.value.user + cashSubtotal.value.user,
      spouse: propertySubtotal.value.spouse + investmentSubtotal.value.spouse + cashSubtotal.value.spouse,
      combined: propertySubtotal.value.combined + investmentSubtotal.value.combined + cashSubtotal.value.combined,
    }));

    // Liabilities
    const getMergedLiabilities = () => {
      const userLiabilities = userData.value?.liabilities || [];
      const spouseLiabilities = spouseData.value?.liabilities || [];

      const allLineItems = new Map();

      userLiabilities.forEach(item => {
        allLineItems.set(item.line_item, {
          line_item: item.line_item,
          userAmount: Number(item.amount) || 0,
          spouseAmount: 0,
        });
      });

      spouseLiabilities.forEach(item => {
        if (allLineItems.has(item.line_item)) {
          allLineItems.get(item.line_item).spouseAmount = Number(item.amount) || 0;
        } else {
          allLineItems.set(item.line_item, {
            line_item: item.line_item,
            userAmount: 0,
            spouseAmount: Number(item.amount) || 0,
          });
        }
      });

      return Array.from(allLineItems.values());
    };

    const allLiabilities = computed(() => getMergedLiabilities());

    const totalLiabilities = computed(() => ({
      user: allLiabilities.value.reduce((sum, item) => sum + (item.userAmount || 0), 0),
      spouse: allLiabilities.value.reduce((sum, item) => sum + (item.spouseAmount || 0), 0),
      combined: allLiabilities.value.reduce((sum, item) => sum + (item.userAmount || 0) + (item.spouseAmount || 0), 0),
    }));

    const netWorth = computed(() => ({
      user: totalAssets.value.user - totalLiabilities.value.user,
      spouse: totalAssets.value.spouse - totalLiabilities.value.spouse,
      combined: totalAssets.value.combined - totalLiabilities.value.combined,
    }));

    const loadData = async () => {
      loading.value = true;
      try {
        const response = await userProfileService.calculatePersonalAccounts({
          as_of_date: asOfDate.value,
        });

        if (response.success && response.data) {
          userData.value = response.data.balance_sheet;
          if (response.data.spouse_data?.balance_sheet) {
            spouseData.value = response.data.spouse_data.balance_sheet;
          }
          // Data loaded successfully
        }
      } catch (error) {
        logger.error('Failed to load balance sheet:', error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      loadData();
    });

    return {
      loading,
      hasData,
      hasSpouse,
      asOfDate,
      userName,
      spouseName,
      formatDate,
      formatCurrency,
      propertyAssets,
      investmentAssets,
      cashAssets,
      propertySubtotal,
      investmentSubtotal,
      cashSubtotal,
      totalAssets,
      allLiabilities,
      totalLiabilities,
      netWorth,
    };
  },
};
</script>
