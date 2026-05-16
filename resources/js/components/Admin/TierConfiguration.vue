<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-horizon-500">Tier Configuration</h2>
        <p class="text-sm text-neutral-500 mt-1">
          Manage tier pricing, capabilities, and limits. Changes take effect immediately.
        </p>
      </div>
      <button
        @click="loadTiers"
        :disabled="loading"
        class="px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors whitespace-nowrap disabled:opacity-60 disabled:cursor-not-allowed"
      >
        Refresh
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="rounded-md bg-raspberry-50 border border-raspberry-200 p-4">
      <p class="text-sm text-raspberry-800">{{ error }}</p>
    </div>

    <!-- Success -->
    <div v-if="successMessage" class="rounded-md bg-spring-50 border border-spring-200 p-4">
      <p class="text-sm text-spring-800">{{ successMessage }}</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Tier rows -->
    <div v-else class="space-y-6">
      <div
        v-for="tier in tiers"
        :key="tier.tier"
        class="bg-white border border-light-gray rounded-xl p-6 space-y-5"
      >
        <!-- Tier header row -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold text-horizon-500">{{ tier.display_name }}</h3>
            <span class="text-xs font-mono text-neutral-400">{{ tier.tier }}</span>
          </div>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-horizon-500">
              <input
                type="checkbox"
                :checked="tierEdits[tier.tier].is_active"
                @change="tierEdits[tier.tier].is_active = $event.target.checked"
                class="rounded border-horizon-300 text-raspberry-500 focus:ring-violet-500"
              />
              Active
            </label>
            <button
              @click="saveTier(tier.tier)"
              :disabled="saving[tier.tier]"
              class="px-4 py-2 bg-raspberry-500 text-white text-sm rounded-button hover:bg-raspberry-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {{ saving[tier.tier] ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>

        <!-- Pricing fields -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Monthly price (pence)</label>
            <input
              type="number"
              min="0"
              v-model.number="tierEdits[tier.tier].price_monthly_pence"
              class="w-full px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Annual price (pence)</label>
            <input
              type="number"
              min="0"
              v-model.number="tierEdits[tier.tier].price_annual_pence"
              class="w-full px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            />
          </div>
        </div>

        <!-- Display name -->
        <div>
          <label class="block text-xs font-medium text-neutral-500 mb-1">Display name</label>
          <input
            type="text"
            v-model="tierEdits[tier.tier].display_name"
            class="w-full px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          />
        </div>

        <!-- Limits row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Document uploads</label>
            <input
              type="number"
              min="0"
              v-model.number="tierEdits[tier.tier].document_upload_allowance"
              class="w-full px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Fyn weekly token budget</label>
            <input
              type="number"
              min="0"
              v-model.number="tierEdits[tier.tier].fyn_weekly_token_budget"
              class="w-full px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 mb-1">Snapshot window (days)</label>
            <input
              type="number"
              min="0"
              v-model.number="tierEdits[tier.tier].snapshot_surfacing_window_days"
              class="w-full px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            />
          </div>
        </div>

        <!-- Currency display mode -->
        <div>
          <label class="block text-xs font-medium text-neutral-500 mb-1">Currency display mode</label>
          <select
            v-model="tierEdits[tier.tier].currency_display_mode"
            class="w-full sm:w-64 px-3 py-2 border border-horizon-300 rounded-button text-sm text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          >
            <option value="gbp_only">GBP only</option>
            <option value="user_choice">User choice</option>
          </select>
        </div>

        <!-- Capability matrix JSON -->
        <div>
          <label class="block text-xs font-medium text-neutral-500 mb-1">Capability matrix (JSON)</label>
          <textarea
            rows="4"
            v-model="capabilityJson[tier.tier]"
            @blur="parseCapabilityJson(tier.tier)"
            class="w-full px-3 py-2 border border-horizon-300 rounded-button text-xs font-mono text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            :class="{ 'border-raspberry-400': jsonErrors[tier.tier + '_capability'] }"
          ></textarea>
          <p v-if="jsonErrors[tier.tier + '_capability']" class="text-xs text-raspberry-600 mt-1">
            Invalid JSON: {{ jsonErrors[tier.tier + '_capability'] }}
          </p>
        </div>

        <!-- Count caps JSON -->
        <div>
          <label class="block text-xs font-medium text-neutral-500 mb-1">Count caps (JSON)</label>
          <textarea
            rows="3"
            v-model="countCapsJson[tier.tier]"
            @blur="parseCountCapsJson(tier.tier)"
            class="w-full px-3 py-2 border border-horizon-300 rounded-button text-xs font-mono text-horizon-500 focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            :class="{ 'border-raspberry-400': jsonErrors[tier.tier + '_caps'] }"
          ></textarea>
          <p v-if="jsonErrors[tier.tier + '_caps']" class="text-xs text-raspberry-600 mt-1">
            Invalid JSON: {{ jsonErrors[tier.tier + '_caps'] }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';

export default {
  name: 'TierConfiguration',

  data() {
    return {
      tiers: [],
      tierEdits: {},
      capabilityJson: {},
      countCapsJson: {},
      jsonErrors: {},
      loading: false,
      saving: {},
      error: null,
      successMessage: null,
    };
  },

  mounted() {
    this.loadTiers();
  },

  methods: {
    async loadTiers() {
      this.loading = true;
      this.error = null;
      try {
        const response = await api.get('/admin/tier-configurations');
        this.tiers = response.data.data;
        this.tiers.forEach(tier => {
          this.tierEdits[tier.tier] = {
            display_name: tier.display_name,
            price_monthly_pence: tier.price_monthly_pence,
            price_annual_pence: tier.price_annual_pence,
            document_upload_allowance: tier.document_upload_allowance,
            fyn_weekly_token_budget: tier.fyn_weekly_token_budget,
            snapshot_surfacing_window_days: tier.snapshot_surfacing_window_days,
            currency_display_mode: tier.currency_display_mode,
            capability_matrix: tier.capability_matrix ?? {},
            count_caps: tier.count_caps ?? {},
            is_active: tier.is_active,
          };
          this.capabilityJson[tier.tier] = JSON.stringify(tier.capability_matrix ?? {}, null, 2);
          this.countCapsJson[tier.tier] = JSON.stringify(tier.count_caps ?? {}, null, 2);
          this.saving[tier.tier] = false;
        });
      } catch {
        this.error = 'Failed to load tier configurations.';
      } finally {
        this.loading = false;
      }
    },

    parseCapabilityJson(tierKey) {
      const errorKey = tierKey + '_capability';
      try {
        this.tierEdits[tierKey].capability_matrix = JSON.parse(this.capabilityJson[tierKey]);
        this.$delete ? this.$delete(this.jsonErrors, errorKey) : delete this.jsonErrors[errorKey];
      } catch (e) {
        this.jsonErrors = { ...this.jsonErrors, [errorKey]: e.message };
      }
    },

    parseCountCapsJson(tierKey) {
      const errorKey = tierKey + '_caps';
      try {
        this.tierEdits[tierKey].count_caps = JSON.parse(this.countCapsJson[tierKey]);
        this.$delete ? this.$delete(this.jsonErrors, errorKey) : delete this.jsonErrors[errorKey];
      } catch (e) {
        this.jsonErrors = { ...this.jsonErrors, [errorKey]: e.message };
      }
    },

    async saveTier(tierKey) {
      // Parse JSON fields first in case user didn't blur
      this.parseCapabilityJson(tierKey);
      this.parseCountCapsJson(tierKey);

      const capabilityError = this.jsonErrors[tierKey + '_capability'];
      const capsError = this.jsonErrors[tierKey + '_caps'];
      if (capabilityError || capsError) {
        this.error = 'Fix JSON errors before saving.';
        return;
      }

      this.saving = { ...this.saving, [tierKey]: true };
      this.error = null;
      this.successMessage = null;

      try {
        await api.put(`/admin/tier-configurations/${tierKey}`, this.tierEdits[tierKey]);
        this.successMessage = `${this.tierEdits[tierKey].display_name} saved successfully.`;
        setTimeout(() => { this.successMessage = null; }, 4000);
      } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to save tier configuration.';
        this.error = msg;
      } finally {
        this.saving = { ...this.saving, [tierKey]: false };
      }
    },
  },
};
</script>
