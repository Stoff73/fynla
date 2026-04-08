<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
      <div class="fixed inset-0 bg-savannah-1000/75" @click="$emit('close')"></div>

      <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6 z-10 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-lg font-semibold text-horizon-500">
            {{ isEditing ? 'Edit Discount Code' : 'Create Discount Code' }}
          </h3>
          <button @click="$emit('close')" class="p-1 text-horizon-400 hover:text-neutral-500 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Code -->
          <div>
            <label class="block text-sm font-medium text-horizon-500 mb-1">Code</label>
            <input
              v-model="form.code"
              type="text"
              required
              class="w-full px-3 py-2 border border-light-gray rounded-lg text-sm uppercase focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              placeholder="e.g. LAUNCH20"
              @input="form.code = form.code.toUpperCase().replace(/[^A-Z0-9]/g, '')"
            />
          </div>

          <!-- Type -->
          <div>
            <label class="block text-sm font-medium text-horizon-500 mb-1">Discount Type</label>
            <div class="flex gap-1 bg-eggshell-500 rounded-lg p-1">
              <button
                v-for="t in types"
                :key="t.value"
                type="button"
                @click="form.type = t.value"
                :class="form.type === t.value ? 'bg-white shadow text-horizon-500' : 'text-neutral-500 hover:text-horizon-500'"
                class="flex-1 py-1.5 text-xs font-medium rounded-md transition-all"
              >
                {{ t.label }}
              </button>
            </div>
          </div>

          <!-- Value -->
          <div>
            <label class="block text-sm font-medium text-horizon-500 mb-1">{{ valueLabel }}</label>
            <input
              v-model.number="form.value"
              type="number"
              required
              min="1"
              :max="form.type === 'percentage' ? 100 : undefined"
              class="w-full px-3 py-2 border border-light-gray rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              :placeholder="valuePlaceholder"
            />
          </div>

          <!-- Max Uses / Per User -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-horizon-500 mb-1">Maximum Uses</label>
              <input
                v-model.number="form.max_uses"
                type="number"
                min="1"
                class="w-full px-3 py-2 border border-light-gray rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
                placeholder="Unlimited"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-horizon-500 mb-1">Uses Per User</label>
              <input
                v-model.number="form.max_uses_per_user"
                type="number"
                min="1"
                required
                class="w-full px-3 py-2 border border-light-gray rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              />
            </div>
          </div>

          <!-- Applicable Plans -->
          <div>
            <label class="block text-sm font-medium text-horizon-500 mb-1">Applicable Plans <span class="text-neutral-400 font-normal">(blank = all)</span></label>
            <div class="flex flex-wrap gap-2">
              <label v-for="p in plans" :key="p" class="inline-flex items-center gap-1.5">
                <input type="checkbox" :value="p" v-model="form.applicable_plans" class="rounded border-light-gray text-raspberry-500 focus:ring-violet-500" />
                <span class="text-sm text-horizon-500 capitalize">{{ p }}</span>
              </label>
            </div>
          </div>

          <!-- Applicable Cycles -->
          <div>
            <label class="block text-sm font-medium text-horizon-500 mb-1">Applicable Billing Cycles <span class="text-neutral-400 font-normal">(blank = both)</span></label>
            <div class="flex gap-4">
              <label class="inline-flex items-center gap-1.5">
                <input type="checkbox" value="monthly" v-model="form.applicable_cycles" class="rounded border-light-gray text-raspberry-500 focus:ring-violet-500" />
                <span class="text-sm text-horizon-500">Monthly</span>
              </label>
              <label class="inline-flex items-center gap-1.5">
                <input type="checkbox" value="yearly" v-model="form.applicable_cycles" class="rounded border-light-gray text-raspberry-500 focus:ring-violet-500" />
                <span class="text-sm text-horizon-500">Yearly</span>
              </label>
            </div>
          </div>

          <!-- Date Range -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-horizon-500 mb-1">Valid From <span class="text-neutral-400 font-normal">(optional)</span></label>
              <input
                v-model="form.starts_at"
                type="date"
                class="w-full px-3 py-2 border border-light-gray rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-horizon-500 mb-1">Valid Until <span class="text-neutral-400 font-normal">(optional)</span></label>
              <input
                v-model="form.expires_at"
                type="date"
                :min="form.starts_at || undefined"
                class="w-full px-3 py-2 border border-light-gray rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500"
              />
            </div>
          </div>

          <!-- Active Toggle -->
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-horizon-500">Active</label>
            <button
              type="button"
              @click="form.is_active = !form.is_active"
              :class="form.is_active ? 'bg-spring-500' : 'bg-neutral-300'"
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
            >
              <span
                :class="form.is_active ? 'translate-x-5' : 'translate-x-0'"
                class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out mt-0.5 ml-0.5"
              ></span>
            </button>
          </div>

          <!-- Buttons -->
          <div class="flex gap-3 justify-end pt-4 border-t border-light-gray">
            <button type="button" @click="$emit('close')" class="px-4 py-2 text-sm text-neutral-500 hover:text-horizon-500 transition-colors">
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-6 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-lg hover:bg-raspberry-600 disabled:opacity-50 transition-colors"
            >
              {{ saving ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DiscountCodeModal',

  emits: ['save', 'close'],

  props: {
    code: {
      type: Object,
      default: null,
    },
    saving: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      form: {
        code: '',
        type: 'percentage',
        value: null,
        max_uses: null,
        max_uses_per_user: 1,
        applicable_plans: [],
        applicable_cycles: [],
        starts_at: null,
        expires_at: null,
        is_active: true,
      },
      types: [
        { value: 'percentage', label: 'Percentage' },
        { value: 'fixed_amount', label: 'Fixed Amount' },
        { value: 'trial_extension', label: 'Trial Extension' },
      ],
      plans: ['student', 'standard', 'family', 'pro'],
    };
  },

  computed: {
    isEditing() {
      return !!this.code;
    },
    valueLabel() {
      return { percentage: 'Discount (%)', fixed_amount: 'Discount (pence)', trial_extension: 'Extra Trial Days' }[this.form.type] || 'Value';
    },
    valuePlaceholder() {
      return { percentage: 'e.g. 20', fixed_amount: 'e.g. 1000 (= \u00A310)', trial_extension: 'e.g. 14' }[this.form.type] || '';
    },
  },

  created() {
    if (this.code) {
      this.form = {
        code: this.code.code || '',
        type: this.code.type || 'percentage',
        value: this.code.value,
        max_uses: this.code.max_uses,
        max_uses_per_user: this.code.max_uses_per_user || 1,
        applicable_plans: this.code.applicable_plans || [],
        applicable_cycles: this.code.applicable_cycles || [],
        starts_at: this.code.starts_at ? this.code.starts_at.substring(0, 10) : null,
        expires_at: this.code.expires_at ? this.code.expires_at.substring(0, 10) : null,
        is_active: this.code.is_active ?? true,
      };
    }
  },

  methods: {
    handleSubmit() {
      const data = {
        ...this.form,
        applicable_plans: this.form.applicable_plans.length > 0 ? this.form.applicable_plans : null,
        applicable_cycles: this.form.applicable_cycles.length > 0 ? this.form.applicable_cycles : null,
        max_uses: this.form.max_uses || null,
        starts_at: this.form.starts_at || null,
        expires_at: this.form.expires_at || null,
      };
      this.$emit('save', data);
    },
  },
};
</script>
