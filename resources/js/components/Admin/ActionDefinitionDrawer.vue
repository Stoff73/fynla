<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-full opacity-0"
    >
      <div v-if="definition" class="fixed inset-0 z-50 flex justify-end">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-horizon-500/20" @click="$emit('close')" />

        <!-- Drawer -->
        <div class="relative w-[420px] bg-white shadow-lg flex flex-col max-h-screen">
          <!-- Header -->
          <div class="p-6 border-b border-light-gray flex items-center justify-between flex-shrink-0">
            <div>
              <h2 class="text-xl font-bold text-horizon-500">
                {{ isNew ? 'New Action Definition' : 'Edit Action Definition' }}
              </h2>
              <span v-if="form.key" class="text-xs text-neutral-500 mt-0.5 font-mono block">
                {{ form.key }}
              </span>
            </div>
            <button
              @click="$emit('close')"
              class="w-8 h-8 rounded-lg border border-light-gray bg-white text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100 transition-all duration-150 flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 flex-1 overflow-y-auto">
            <!-- Key -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Key</label>
              <input
                v-model="form.key"
                type="text"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full font-mono text-sm"
                placeholder="snake_case_key"
              />
            </div>

            <!-- Source -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Source</label>
              <select
                v-model="form.source"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full bg-white"
              >
                <option
                  v-for="opt in sourceOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Title Template -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Title Template</label>
              <input
                v-model="form.title_template"
                type="text"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full"
                placeholder="Action title"
              />
            </div>

            <!-- Description Template -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Description Template</label>
              <textarea
                v-model="form.description_template"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full font-mono min-h-[80px] resize-y text-sm"
                placeholder="Template with {variables}..."
              />
              <!-- Variable tags -->
              <div v-if="templateVars.length > 0" class="flex flex-wrap gap-1.5 mt-1">
                <span
                  v-for="varName in templateVars"
                  :key="varName"
                  class="bg-violet-50 text-violet-700 text-xs px-2 py-0.5 rounded font-mono inline-block"
                >
                  {{ '{' + varName + '}' }}
                </span>
              </div>
            </div>

            <!-- Action Template -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Action Template</label>
              <textarea
                v-model="form.action_template"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full font-mono min-h-[60px] resize-y text-sm"
                placeholder="Recommended action..."
              />
            </div>

            <!-- Category -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Category</label>
              <input
                v-model="form.category"
                type="text"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full"
                placeholder="Category name"
              />
            </div>

            <!-- Priority & Scope row -->
            <div class="flex gap-4 mb-4">
              <div class="flex-1">
                <label class="block text-body-sm font-medium text-neutral-500 mb-1">Priority</label>
                <select
                  v-model="form.priority"
                  class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full bg-white"
                >
                  <option value="critical">Critical</option>
                  <option value="high">High</option>
                  <option value="medium">Medium</option>
                  <option value="low">Low</option>
                </select>
              </div>
              <div class="flex-1">
                <label class="block text-body-sm font-medium text-neutral-500 mb-1">Scope</label>
                <select
                  v-model="form.scope"
                  class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full bg-white"
                >
                  <option value="portfolio">Portfolio</option>
                  <option value="account">Account</option>
                </select>
              </div>
            </div>

            <!-- What-If Impact Type -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">What-If Impact Type</label>
              <select
                v-model="form.what_if_impact_type"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full bg-white"
              >
                <option
                  v-for="opt in impactOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Trigger Config -->
            <TriggerConfigEditor
              v-model="form.trigger_config"
              :module-config="moduleConfig"
            />

            <!-- Sort Order -->
            <div class="mb-4 mt-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Sort Order</label>
              <input
                v-model.number="form.sort_order"
                type="number"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full"
                min="0"
                max="9999"
              />
            </div>

            <!-- Notes -->
            <div class="mb-4">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">Notes</label>
              <input
                v-model="form.notes"
                type="text"
                class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full"
                placeholder="Internal notes..."
              />
            </div>

            <!-- Enabled toggle -->
            <div class="flex items-center gap-3 mt-4">
              <button
                type="button"
                :class="[
                  'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
                  form.is_enabled ? 'bg-spring-500' : 'bg-horizon-300',
                ]"
                @click="form.is_enabled = !form.is_enabled"
              >
                <span
                  :class="[
                    'pointer-events-none inline-block h-[18px] w-[18px] rounded-full bg-white shadow transform transition duration-200 ease-in-out mt-[1px]',
                    form.is_enabled ? 'translate-x-[20px]' : 'translate-x-0',
                  ]"
                />
              </button>
              <span :class="['text-sm font-semibold', form.is_enabled ? 'text-spring-500' : 'text-neutral-500']">
                {{ form.is_enabled ? 'Enabled' : 'Disabled' }}
              </span>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-4 border-t border-light-gray flex gap-3 justify-end flex-shrink-0">
            <button
              @click="$emit('close')"
              class="px-4 py-2 border border-light-gray rounded-md text-neutral-500 hover:bg-savannah-100 transition-colors text-sm font-medium"
            >
              Cancel
            </button>
            <button
              @click="handleSave"
              :disabled="saving"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-md hover:bg-raspberry-600 transition-colors text-sm font-medium disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import TriggerConfigEditor from './TriggerConfigEditor.vue';

export default {
  name: 'ActionDefinitionDrawer',

  components: {
    TriggerConfigEditor,
  },

  props: {
    definition: {
      type: Object,
      default: null,
    },
    module: {
      type: String,
      required: true,
    },
    moduleConfig: {
      type: Object,
      default: () => ({}),
    },
    saving: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['save', 'close'],

  data() {
    return {
      form: this.getDefaultForm(),
    };
  },

  computed: {
    isNew() {
      return !this.definition?.id;
    },

    sourceOptions() {
      return this.moduleConfig?.sourceOptions || [{ value: 'agent', label: 'Agent' }];
    },

    impactOptions() {
      return this.moduleConfig?.whatIfImpactOptions || [{ value: 'default', label: 'Default' }];
    },

    templateVars() {
      const text = (this.form.description_template || '') + ' ' + (this.form.title_template || '') + ' ' + (this.form.action_template || '');
      const matches = text.match(/\{([a-z_]+)\}/g) || [];
      return [...new Set(matches.map((m) => m.slice(1, -1)))];
    },
  },

  watch: {
    definition: {
      immediate: true,
      handler(def) {
        if (def) {
          this.form = { ...this.getDefaultForm(), ...def };
          // Ensure trigger_config is an object
          if (typeof this.form.trigger_config === 'string') {
            try {
              this.form.trigger_config = JSON.parse(this.form.trigger_config);
            } catch {
              this.form.trigger_config = { condition: '' };
            }
          }
        } else {
          this.form = this.getDefaultForm();
        }
      },
    },
  },

  methods: {
    getDefaultForm() {
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

    handleSave() {
      this.$emit('save', { ...this.form });
    },
  },
};
</script>
