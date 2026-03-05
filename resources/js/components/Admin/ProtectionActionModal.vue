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
                {{ isEditing ? 'Edit Protection Action Definition' : 'Create Protection Action Definition' }}
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
                  placeholder="life_insurance_gap"
                />
                <p v-if="errors.key" class="mt-1 text-xs text-raspberry-600">{{ errors.key }}</p>
                <p v-else-if="!isEditing" class="mt-1 text-xs text-neutral-500">Unique slug identifier.</p>
              </div>
              <!-- Source -->
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Source</label>
                <select v-model="form.source" class="input-field">
                  <option value="agent">Agent</option>
                  <option value="gap">Gap</option>
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
              <span class="font-mono text-xs">{gap_amount}</span>,
              <span class="font-mono text-xs">{need_amount}</span>,
              <span class="font-mono text-xs">{coverage_amount}</span>,
              <span class="font-mono text-xs">{policy_count}</span>.
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
                  placeholder="Increase life insurance cover by {gap_amount}"
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
                  placeholder="Your current life insurance falls short by {gap_amount}..."
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
                  placeholder="Speak to a protection adviser about increasing your cover..."
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
                  placeholder="Life Insurance"
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
                  <option value="coverage_increase">Coverage Increase</option>
                  <option value="gap_reduction">Gap Reduction</option>
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

              <!-- Coverage Type (for gap conditions) -->
              <div v-if="showCoverageType">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Coverage Type</label>
                <select v-model="form.trigger_config.coverage_type" class="input-field">
                  <option value="life_insurance">Life Insurance</option>
                  <option value="critical_illness">Critical Illness</option>
                  <option value="income_protection">Income Protection</option>
                </select>
              </div>

              <!-- Category Match (for strategy conditions) -->
              <div v-if="showCategoryMatch">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Category Match</label>
                <input
                  v-model="form.trigger_config.category_match"
                  type="text"
                  class="input-field w-60"
                  placeholder="life, critical, income"
                />
                <p class="mt-1 text-xs text-neutral-500">Matches strategy recommendations containing this text.</p>
              </div>

              <!-- Threshold -->
              <div v-if="showThreshold">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Threshold</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="form.trigger_config.threshold"
                    type="number"
                    step="1"
                    min="0"
                    class="input-field w-40"
                  />
                  <span class="text-sm text-neutral-500">{{ thresholdUnit }}</span>
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
  name: 'ProtectionActionModal',

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
        { value: 'gap_exists', label: 'Coverage gap exists' },
        { value: 'strategy_recommendation', label: 'Strategy recommendation matches' },
        { value: 'policies_exist_with_gaps', label: 'Policies exist but gaps remain' },
        { value: 'multiple_policies', label: 'Multiple policies (consolidation)' },
        { value: 'profile_missing', label: 'Protection profile missing' },
        { value: 'no_policies_with_gaps', label: 'No policies with coverage gaps' },
      ];
    },

    conditionHint() {
      const hints = {
        gap_exists: 'Triggers when a specific coverage type has a gap greater than the threshold.',
        strategy_recommendation: 'Triggers when the optimized strategy contains a matching recommendation.',
        policies_exist_with_gaps: 'Triggers when the user has policies but coverage gaps remain.',
        multiple_policies: 'Triggers when the user has more policies than the threshold.',
        profile_missing: 'Triggers when the user has no protection profile.',
        no_policies_with_gaps: 'Triggers when the user has no policies and has coverage gaps.',
      };
      return hints[this.form.trigger_config.condition] || null;
    },

    showCoverageType() {
      return this.form.trigger_config.condition === 'gap_exists';
    },

    showCategoryMatch() {
      return this.form.trigger_config.condition === 'strategy_recommendation';
    },

    showThreshold() {
      return ['gap_exists', 'multiple_policies'].includes(this.form.trigger_config.condition);
    },

    thresholdUnit() {
      const units = {
        gap_exists: 'minimum gap amount (0 = any gap)',
        multiple_policies: 'minimum number of policies',
      };
      return units[this.form.trigger_config.condition] || '';
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
