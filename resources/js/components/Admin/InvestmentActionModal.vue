<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

    <!-- Modal container -->
    <div class="flex items-center justify-center min-h-screen p-4">
      <!-- Modal panel -->
      <div class="relative bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-light-gray flex-shrink-0">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-horizon-500">
                {{ isEditing ? 'Edit Action Definition' : 'Create Action Definition' }}
              </h3>
              <p v-if="isEditing" class="text-sm text-neutral-500 mt-0.5">
                <span class="font-mono text-xs bg-savannah-100 px-1.5 py-0.5 rounded">{{ form.key }}</span>
              </p>
            </div>
            <button
              @click="$emit('close')"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Body (scrollable) -->
        <div class="p-6 overflow-y-auto flex-1 space-y-6">
          <!-- Server Validation Errors -->
          <div v-if="serverErrors" class="rounded-md bg-raspberry-50 border border-raspberry-200 p-4">
            <div class="flex">
              <svg class="h-5 w-5 text-raspberry-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="ml-3">
                <p class="text-sm font-medium text-raspberry-800">Please fix the following errors:</p>
                <ul class="mt-1 text-sm text-raspberry-700 list-disc list-inside">
                  <li v-for="(msg, field) in serverErrors" :key="field">{{ Array.isArray(msg) ? msg[0] : msg }}</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Section: Identity -->
          <fieldset>
            <legend class="text-sm font-semibold text-horizon-500 uppercase tracking-wide mb-3">Identity</legend>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <!-- Key -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Key</label>
                <input
                  v-model="form.key"
                  type="text"
                  :readonly="isEditing"
                  class="input-field"
                  :class="{ 'bg-savannah-100 text-neutral-500 cursor-not-allowed': isEditing, 'border-raspberry-300': errors.key }"
                  placeholder="high_total_fees"
                />
                <p v-if="errors.key" class="mt-1 text-xs text-raspberry-600">{{ errors.key }}</p>
                <p v-else-if="!isEditing" class="mt-1 text-xs text-neutral-500">Unique slug identifier.</p>
              </div>
              <!-- Source -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Source</label>
                <select v-model="form.source" class="input-field">
                  <option value="agent">Agent</option>
                  <option value="goal">Goal</option>
                </select>
              </div>
              <!-- Scope -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Scope</label>
                <select v-model="form.scope" class="input-field">
                  <option value="portfolio">Portfolio</option>
                  <option value="account">Account</option>
                </select>
              </div>
            </div>
          </fieldset>

          <!-- Section: Templates -->
          <fieldset>
            <legend class="text-sm font-semibold text-horizon-500 uppercase tracking-wide mb-3">Templates</legend>
            <p class="text-xs text-neutral-500 mb-3">
              Use <span class="font-mono bg-savannah-100 px-1 rounded">{placeholder}</span> for dynamic values such as
              <span class="font-mono text-xs">{account_name}</span>,
              <span class="font-mono text-xs">{total_fee_percent}</span>,
              <span class="font-mono text-xs">{isa_allowance}</span>,
              <span class="font-mono text-xs">{goal_name}</span>.
            </p>
            <div class="space-y-4">
              <!-- Title Template -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Title Template</label>
                <input
                  v-model="form.title_template"
                  type="text"
                  class="input-field"
                  :class="{ 'border-raspberry-300': errors.title_template }"
                  placeholder="Reduce Total Fees on {account_name}"
                />
                <p v-if="errors.title_template" class="mt-1 text-xs text-raspberry-600">{{ errors.title_template }}</p>
              </div>
              <!-- Description Template -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Description Template</label>
                <textarea
                  v-model="form.description_template"
                  rows="3"
                  class="input-field"
                  :class="{ 'border-raspberry-300': errors.description_template }"
                  placeholder="The total annual charge on your {account_name} account is {total_fee_percent}%..."
                ></textarea>
                <p v-if="errors.description_template" class="mt-1 text-xs text-raspberry-600">{{ errors.description_template }}</p>
              </div>
              <!-- Action Template -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Action Template
                  <span class="font-normal text-horizon-400">(optional)</span>
                </label>
                <input
                  v-model="form.action_template"
                  type="text"
                  class="input-field"
                  placeholder="Review your investment platform and fund selection..."
                />
              </div>
            </div>
          </fieldset>

          <!-- Section: Classification -->
          <fieldset>
            <legend class="text-sm font-semibold text-horizon-500 uppercase tracking-wide mb-3">Classification</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <!-- Category -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Category</label>
                <input
                  v-model="form.category"
                  type="text"
                  class="input-field"
                  :class="{ 'border-raspberry-300': errors.category }"
                  placeholder="Fees"
                />
                <p v-if="errors.category" class="mt-1 text-xs text-raspberry-600">{{ errors.category }}</p>
              </div>
              <!-- Priority -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Priority</label>
                <select v-model="form.priority" class="input-field">
                  <option value="critical">Critical</option>
                  <option value="high">High</option>
                  <option value="medium">Medium</option>
                  <option value="low">Low</option>
                </select>
              </div>
              <!-- What-if Impact Type -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">What-if Impact</label>
                <select v-model="form.what_if_impact_type" class="input-field">
                  <option value="fee_reduction">Fee Reduction</option>
                  <option value="savings_increase">Savings Increase</option>
                  <option value="contribution">Contribution</option>
                  <option value="tax_optimisation">Tax Optimisation</option>
                  <option value="default">Default</option>
                </select>
              </div>
              <!-- Sort Order -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Sort Order</label>
                <input
                  v-model.number="form.sort_order"
                  type="number"
                  min="0"
                  max="9999"
                  class="input-field"
                />
              </div>
            </div>
          </fieldset>

          <!-- Section: Trigger Configuration -->
          <fieldset>
            <legend class="text-sm font-semibold text-horizon-500 uppercase tracking-wide mb-3">Trigger Configuration</legend>
            <div class="bg-savannah-100 rounded-lg border border-light-gray p-4 space-y-4">
              <!-- Condition -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Condition</label>
                <select v-model="form.trigger_config.condition" class="input-field" :class="{ 'border-raspberry-300': errors.trigger_config }">
                  <option value="">Select a condition...</option>
                  <option v-for="opt in conditionOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
                <p v-if="errors.trigger_config" class="mt-1 text-xs text-raspberry-600">{{ errors.trigger_config }}</p>
                <p v-else-if="conditionHint" class="mt-1 text-xs text-neutral-500">{{ conditionHint }}</p>
              </div>

              <!-- Threshold (simple) -->
              <div v-if="showThreshold">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Threshold</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="form.trigger_config.threshold"
                    type="number"
                    step="0.01"
                    class="input-field w-40"
                  />
                  <span class="text-sm text-neutral-500">{{ thresholdUnit }}</span>
                </div>
              </div>

              <!-- Low/High thresholds (emergency fund) -->
              <div v-if="showDualThresholds" class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Low Threshold (months)</label>
                  <input
                    v-model.number="form.trigger_config.threshold"
                    type="number"
                    min="0"
                    max="24"
                    class="input-field"
                  />
                  <p class="mt-1 text-xs text-neutral-500">Below this triggers critical.</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">High Threshold (months)</label>
                  <input
                    v-model.number="form.trigger_config.high_threshold"
                    type="number"
                    min="0"
                    max="24"
                    class="input-field"
                  />
                  <p class="mt-1 text-xs text-neutral-500">Grow towards this target.</p>
                </div>
              </div>

              <!-- Goal thresholds (deadline approaching) -->
              <div v-if="showGoalThresholds" class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Months Remaining Threshold</label>
                  <input
                    v-model.number="form.trigger_config.months_threshold"
                    type="number"
                    min="1"
                    max="60"
                    class="input-field"
                  />
                  <p class="mt-1 text-xs text-neutral-500">Trigger when fewer than this many months remain.</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Progress Threshold (%)</label>
                  <input
                    v-model.number="form.trigger_config.progress_threshold"
                    type="number"
                    min="0"
                    max="100"
                    class="input-field"
                  />
                  <p class="mt-1 text-xs text-neutral-500">Trigger when progress is below this percentage.</p>
                </div>
              </div>
            </div>
          </fieldset>

          <!-- Section: Settings -->
          <fieldset>
            <legend class="text-sm font-semibold text-horizon-500 uppercase tracking-wide mb-3">Settings</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Enabled -->
              <div class="flex items-center gap-3 py-2">
                <button
                  type="button"
                  @click="form.is_enabled = !form.is_enabled"
                  :class="[
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2',
                    form.is_enabled ? 'bg-raspberry-600' : 'bg-savannah-100'
                  ]"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      form.is_enabled ? 'translate-x-5' : 'translate-x-0'
                    ]"
                  />
                </button>
                <div>
                  <span class="text-sm font-medium text-neutral-500">Enabled</span>
                  <p class="text-xs text-neutral-500">Disabled actions are skipped during plan generation.</p>
                </div>
              </div>
              <!-- Notes -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">
                  Notes
                  <span class="font-normal text-horizon-400">(optional)</span>
                </label>
                <input
                  v-model="form.notes"
                  type="text"
                  class="input-field"
                  placeholder="Internal admin notes..."
                />
              </div>
            </div>
          </fieldset>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-light-gray bg-savannah-100 flex justify-end gap-3 flex-shrink-0 rounded-b-lg">
          <button
            @click="$emit('close')"
            :disabled="saving"
            class="px-4 py-2 border border-horizon-300 rounded-button text-neutral-500 hover:bg-savannah-100 transition-colors disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            @click="handleSubmit"
            :disabled="saving"
            class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors disabled:opacity-50"
          >
            <span v-if="saving" class="flex items-center gap-2">
              <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Saving...
            </span>
            <span v-else>{{ isEditing ? 'Update' : 'Create' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InvestmentActionModal',

  props: {
    definition: {
      type: Object,
      default: null,
    },
    saving: {
      type: Boolean,
      default: false,
    },
    serverErrors: {
      type: Object,
      default: null,
    },
  },

  emits: ['save', 'close'],

  data() {
    return {
      form: this.buildForm(),
      errors: {},
    };
  },

  computed: {
    isEditing() {
      return !!this.definition?.id;
    },

    conditionOptions() {
      return [
        // Investment triggers
        { value: 'risk_profile_missing', label: 'Risk profile missing' },
        { value: 'no_holdings_in_accounts', label: 'No holdings in accounts' },
        { value: 'diversification_score_below_threshold', label: 'Diversification score below threshold' },
        { value: 'total_fee_above_threshold', label: 'Total fee above threshold (per account)' },
        { value: 'fund_ocf_above_threshold', label: 'Fund charges above threshold (per account)' },
        { value: 'platform_fee_above_threshold', label: 'Platform fee above threshold (per account)' },
        { value: 'allocation_needs_rebalancing', label: 'Asset allocation needs rebalancing' },
        { value: 'tax_loss_harvesting_opportunities', label: 'Tax loss harvesting opportunities' },
        // Tax efficiency triggers
        { value: 'has_gia_no_isa', label: 'Has General Investment Account but no ISA' },
        { value: 'isa_remaining_with_gia', label: 'ISA allowance remaining with General Investment Account holdings' },
        { value: 'gia_value_above_threshold_no_bonds', label: 'General Investment Account value above threshold, no bonds' },
        // Savings triggers
        { value: 'emergency_fund_below_threshold', label: 'Emergency fund below critical threshold' },
        { value: 'emergency_fund_below_target', label: 'Emergency fund below target (grow)' },
        { value: 'poor_savings_rate', label: 'Poor savings rate detected' },
        { value: 'savings_isa_allowance_remaining', label: 'Savings ISA allowance remaining' },
        // Surplus waterfall
        { value: 'surplus_available_isa', label: 'Surplus available for ISA' },
        { value: 'surplus_available_pension', label: 'Surplus available for pension' },
        { value: 'surplus_available_bond', label: 'Surplus available for bond' },
        // Goal triggers
        { value: 'linked_goal_no_monthly_contribution', label: 'Linked goal has no monthly contribution' },
        { value: 'linked_goal_off_track', label: 'Linked goal is off track' },
        { value: 'goal_months_remaining_below_and_progress_below', label: 'Goal deadline approaching with low progress' },
      ];
    },

    conditionHint() {
      const hints = {
        risk_profile_missing: 'Triggers when the user has no risk profile set.',
        no_holdings_in_accounts: 'Triggers when investment accounts exist but have no holdings.',
        diversification_score_below_threshold: 'Triggers when portfolio diversification is below the threshold.',
        total_fee_above_threshold: 'Triggers per account when total annual charge exceeds the threshold percentage.',
        fund_ocf_above_threshold: 'Triggers per account when weighted fund charges exceed the threshold percentage.',
        platform_fee_above_threshold: 'Triggers per account when platform fee exceeds the threshold percentage.',
        allocation_needs_rebalancing: 'Triggers when asset allocation has drifted and needs rebalancing.',
        tax_loss_harvesting_opportunities: 'Triggers when tax loss harvesting opportunities are identified.',
        has_gia_no_isa: 'Triggers when the user has a General Investment Account but no ISA.',
        isa_remaining_with_gia: 'Triggers when ISA allowance remains and General Investment Account holdings exist.',
        gia_value_above_threshold_no_bonds: 'Triggers when General Investment Account value exceeds threshold with no bond wrappers.',
        emergency_fund_below_threshold: 'Triggers when emergency fund is critically low.',
        emergency_fund_below_target: 'Triggers when emergency fund is below the target but above critical.',
        poor_savings_rate: 'Triggers when poor-rated savings accounts have significant potential gains.',
        savings_isa_allowance_remaining: 'Triggers when savings ISA allowance remains and emergency fund is adequate.',
        surplus_available_isa: 'Triggers when excess emergency fund can be moved to ISA.',
        surplus_available_pension: 'Triggers when surplus exceeds ISA capacity for pension.',
        surplus_available_bond: 'Triggers when surplus exceeds pension capacity for bonds.',
        linked_goal_no_monthly_contribution: 'Triggers when a linked investment goal has no monthly contribution.',
        linked_goal_off_track: 'Triggers when a linked investment goal is off track.',
        goal_months_remaining_below_and_progress_below: 'Triggers when a goal is near its deadline with low progress.',
      };
      return hints[this.form.trigger_config.condition] || null;
    },

    showThreshold() {
      return [
        'diversification_score_below_threshold',
        'total_fee_above_threshold',
        'fund_ocf_above_threshold',
        'platform_fee_above_threshold',
        'gia_value_above_threshold_no_bonds',
      ].includes(this.form.trigger_config.condition);
    },

    thresholdUnit() {
      const units = {
        diversification_score_below_threshold: '(score 0-100)',
        total_fee_above_threshold: '% total annual charge',
        fund_ocf_above_threshold: '% weighted fund charge',
        platform_fee_above_threshold: '% platform fee',
        gia_value_above_threshold_no_bonds: 'minimum General Investment Account value',
      };
      return units[this.form.trigger_config.condition] || '';
    },

    showDualThresholds() {
      return [
        'emergency_fund_below_threshold',
        'emergency_fund_below_target',
      ].includes(this.form.trigger_config.condition);
    },

    showGoalThresholds() {
      return this.form.trigger_config.condition === 'goal_months_remaining_below_and_progress_below';
    },
  },

  methods: {
    buildForm() {
      if (this.definition) {
        return {
          key: this.definition.key || '',
          source: this.definition.source || 'agent',
          title_template: this.definition.title_template || '',
          description_template: this.definition.description_template || '',
          action_template: this.definition.action_template || '',
          category: this.definition.category || '',
          priority: this.definition.priority || 'medium',
          scope: this.definition.scope || 'portfolio',
          what_if_impact_type: this.definition.what_if_impact_type || 'default',
          trigger_config: { ...(this.definition.trigger_config || { condition: '' }) },
          is_enabled: this.definition.is_enabled ?? true,
          sort_order: this.definition.sort_order ?? 100,
          notes: this.definition.notes || '',
        };
      }

      return {
        key: '',
        source: 'agent',
        title_template: '',
        description_template: '',
        action_template: '',
        category: '',
        priority: 'medium',
        scope: 'portfolio',
        what_if_impact_type: 'default',
        trigger_config: { condition: '' },
        is_enabled: true,
        sort_order: 100,
        notes: '',
      };
    },

    validate() {
      this.errors = {};

      if (!this.form.key) this.errors.key = 'Key is required.';
      else if (!/^[a-z0-9_]+$/.test(this.form.key)) this.errors.key = 'Only lowercase letters, numbers, and underscores.';
      if (!this.form.title_template) this.errors.title_template = 'Title template is required.';
      if (!this.form.description_template) this.errors.description_template = 'Description template is required.';
      if (!this.form.category) this.errors.category = 'Category is required.';
      if (!this.form.trigger_config.condition) this.errors.trigger_config = 'Trigger condition is required.';

      return Object.keys(this.errors).length === 0;
    },

    handleSubmit() {
      if (!this.validate()) return;
      this.$emit('save', { ...this.form });
    },
  },
};
</script>
