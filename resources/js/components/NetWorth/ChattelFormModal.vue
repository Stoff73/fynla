<template>
  <div class="fixed inset-0 bg-horizon-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden" @click.stop>
      <div class="overflow-y-auto max-h-[90vh]">
        <!-- Header -->
        <div class="sticky top-0 bg-white border-b border-light-gray px-6 py-4 rounded-t-lg z-10">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-semibold text-horizon-500">
              {{ isEditing ? 'Edit Valuable' : 'Add Valuable' }}
            </h3>
            <button
              @click="handleClose"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSave" class="px-6 py-4 space-y-6">
          <!-- Error Message -->
          <div v-if="error" class="p-4 bg-savannah-100 rounded-lg">
            <p class="text-sm text-raspberry-700">{{ error }}</p>
          </div>

          <!-- Type Selection -->
          <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'chattel_type' }">
            <label class="block text-sm font-medium text-horizon-500 mb-2">Type</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="type in chattelTypes"
                :key="type.value"
                type="button"
                @click="form.chattel_type = type.value"
                class="px-4 py-3 border rounded-lg text-sm font-medium transition-all"
                :class="form.chattel_type === type.value
                  ? 'border-pink-500 bg-pink-500 text-white'
                  : 'border-light-gray hover:border-horizon-300 text-neutral-500'"
              >
                {{ type.label }}
              </button>
            </div>
          </div>

          <!-- Basic Information -->
          <div class="space-y-4">
            <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'name' }">
              <label for="name" class="block text-sm font-medium text-horizon-500 mb-1">Name</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                placeholder="e.g., Vintage Rolex Submariner"
                required
              />
            </div>

            <div>
              <label for="description" class="block text-sm font-medium text-horizon-500 mb-1">Description</label>
              <textarea
                id="description"
                v-model="form.description"
                rows="2"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                placeholder="Brief description of the item"
              ></textarea>
            </div>
          </div>

          <!-- Vehicle Details (conditional) -->
          <div v-if="form.chattel_type === 'vehicle'" class="space-y-4 p-4 bg-savannah-100 rounded-lg">
            <h4 class="font-medium text-violet-900">Vehicle Details</h4>
            <p class="text-xs text-violet-700 mb-3">Vehicles are classified as wasting assets and are Capital Gains Tax exempt.</p>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-1">Make</label>
                <input
                  v-model="form.make"
                  type="text"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                  placeholder="e.g., BMW"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-1">Model</label>
                <input
                  v-model="form.model"
                  type="text"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                  placeholder="e.g., M5"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-1">Year</label>
                <input
                  v-model.number="form.year"
                  type="number"
                  min="1900"
                  :max="currentYear + 1"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                  placeholder="2024"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-1">Registration</label>
                <input
                  v-model="form.registration_number"
                  type="text"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                  placeholder="AB12 CDE"
                />
              </div>
            </div>
          </div>

          <!-- Valuation -->
          <div class="space-y-4">
            <h4 class="font-medium text-horizon-500">Valuation</h4>
            <div class="grid grid-cols-2 gap-4">
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_value' }">
                <label for="current_value" class="block text-sm font-medium text-horizon-500 mb-1">Current Value</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-neutral-500">£</span>
                  <input
                    id="current_value"
                    v-model.number="form.current_value"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                    placeholder="0.00"
                    required
                  />
                </div>
              </div>
              <div>
                <label for="valuation_date" class="block text-sm font-medium text-horizon-500 mb-1">Valuation Date</label>
                <input
                  id="valuation_date"
                  v-model="form.valuation_date"
                  type="date"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                />
              </div>
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'purchase_price' }">
                <label for="purchase_price" class="block text-sm font-medium text-horizon-500 mb-1">Purchase Price</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-neutral-500">£</span>
                  <input
                    id="purchase_price"
                    v-model.number="form.purchase_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                    placeholder="0.00"
                  />
                </div>
              </div>
              <div>
                <label for="purchase_date" class="block text-sm font-medium text-horizon-500 mb-1">Purchase Date</label>
                <input
                  id="purchase_date"
                  v-model="form.purchase_date"
                  type="date"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                />
              </div>
            </div>
          </div>

          <!-- Ownership -->
          <div class="space-y-4">
            <h4 class="font-medium text-horizon-500">Ownership</h4>
            <div>
              <label class="block text-sm font-medium text-horizon-500 mb-2">Ownership Type</label>
              <div class="flex gap-4">
                <label class="flex items-center">
                  <input
                    type="radio"
                    v-model="form.ownership_type"
                    value="individual"
                    class="mr-2"
                  />
                  <span>Individual (100%)</span>
                </label>
                <label class="flex items-center">
                  <input
                    type="radio"
                    v-model="form.ownership_type"
                    value="joint"
                    class="mr-2"
                  />
                  <span>Joint</span>
                </label>
              </div>
            </div>

            <div v-if="form.ownership_type === 'joint'" class="space-y-4 p-4 bg-savannah-100 rounded-lg">
              <!-- Ownership Split Display -->
              <div class="bg-white p-3 rounded border border-pink-300">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-horizon-500">Your Share</p>
                    <p class="text-2xl font-bold text-pink-600">{{ form.ownership_percentage || 50 }}%</p>
                  </div>
                  <div class="text-horizon-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-medium text-horizon-500">Joint Owner's Share</p>
                    <p class="text-2xl font-bold text-pink-600">{{ 100 - (form.ownership_percentage || 50) }}%</p>
                  </div>
                </div>
              </div>

              <!-- Ownership Percentage Input -->
              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-1">Your Ownership Share (%)</label>
                <input
                  v-model.number="form.ownership_percentage"
                  type="number"
                  min="1"
                  max="99"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                  placeholder="50"
                />
                <p class="text-xs text-neutral-500 mt-1">Default is 50/50. Adjust if ownership split is different.</p>
              </div>

              <!-- Joint Owner Selection -->
              <div>
                <label class="block text-sm font-medium text-horizon-500 mb-1">Joint Owner</label>
                <select
                  v-model="jointOwnerSelection"
                  @change="handleJointOwnerSelection"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                >
                  <option value="">Select joint owner</option>
                  <option v-if="spouse" :value="'linked_' + spouse.id">{{ spouse.name }} (Spouse - Linked Account)</option>
                  <option value="other">Other (Enter Name)</option>
                </select>
              </div>

              <!-- Free Text Joint Owner Name -->
              <div v-if="jointOwnerSelection === 'other'">
                <label class="block text-sm font-medium text-horizon-500 mb-1">Joint Owner Name</label>
                <input
                  v-model="form.joint_owner_name"
                  type="text"
                  placeholder="Enter joint owner's full name"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                />
                <p class="text-xs text-neutral-500 mt-1">
                  Note: This person doesn't have an account in the system. The chattel will only appear in your account.
                </p>
              </div>

              <p class="text-xs text-neutral-500">
                Joint assets with linked accounts will appear in both owners' accounts with respective ownership percentages.
              </p>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label for="notes" class="block text-sm font-medium text-horizon-500 mb-1">Notes</label>
            <textarea
              id="notes"
              v-model="form.notes"
              rows="2"
              class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
              placeholder="Any additional notes about this item"
            ></textarea>
          </div>
        </form>

        <!-- Footer -->
        <div class="bg-savannah-100 border-t border-light-gray px-6 py-4 flex justify-end gap-3 rounded-b-lg">
          <button
            type="button"
            @click="handleClose"
            class="px-4 py-2 border border-horizon-300 rounded-md text-neutral-500 hover:bg-savannah-100 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="handleSave"
            :disabled="saving"
            class="px-4 py-2 bg-pink-600 text-white rounded-button hover:bg-pink-700 transition-colors disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : (isEditing ? 'Save Changes' : 'Add Valuable') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
  name: 'ChattelFormModal',

  props: {
    chattel: {
      type: Object,
      default: null,
    },
    isEditing: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['close', 'save'],

  data() {
    return {
      jointOwnerSelection: '',
      form: {
        chattel_type: 'other',
        name: '',
        description: '',
        ownership_type: 'individual',
        ownership_percentage: 100,
        joint_owner_id: null,
        joint_owner_name: '',
        current_value: null,
        valuation_date: this.formatDateForInput(new Date()),
        purchase_price: null,
        purchase_date: null,
        make: '',
        model: '',
        year: null,
        registration_number: '',
        notes: '',
      },
      saving: false,
      error: null,
      chattelTypes: [
        { value: 'vehicle', label: 'Vehicle' },
        { value: 'art', label: 'Art' },
        { value: 'antique', label: 'Antique' },
        { value: 'jewelry', label: 'Jewellery' },
        { value: 'collectible', label: 'Collectible' },
        { value: 'other', label: 'Other' },
      ],
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },

    currentYear() {
      return new Date().getFullYear();
    },
  },

  watch: {
    'form.ownership_type'(newVal) {
      if (newVal === 'individual') {
        this.form.ownership_percentage = 100;
        this.form.joint_owner_id = null;
        this.form.joint_owner_name = '';
        this.jointOwnerSelection = '';
      } else if (newVal === 'joint' && this.form.ownership_percentage === 100) {
        this.form.ownership_percentage = 50;
      }
    },

    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'chattel' && fill.fields) {
          // Pre-set chattel_type — it's button-based selection, needs to be set before animation
          if (fill.fields.chattel_type) {
            this.form.chattel_type = fill.fields.chattel_type;
          }
          // Pre-set name — required for validation
          if (fill.fields.name) {
            this.form.name = fill.fields.name;
          }
          const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
          this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
        }
      },
      immediate: true,
    },

    highlightedField(fieldKey) {
      if (fieldKey && this.pendingFill?.fields) {
        const value = this.pendingFill.fields[fieldKey];
        if (value !== undefined && value !== null) {
          this.form[fieldKey] = value;
        }
      }
    },

    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'chattel') {
        setTimeout(() => {
          this.handleSave();
        }, 250);
      }
    },
  },

  created() {
    if (this.chattel) {
      this.populateForm();
    }
  },

  methods: {
    handleClose() {
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
    },

    populateForm() {
      this.form = {
        chattel_type: this.chattel.chattel_type || 'other',
        name: this.chattel.name || '',
        description: this.chattel.description || '',
        ownership_type: this.chattel.ownership_type || 'individual',
        ownership_percentage: this.chattel.ownership_percentage || 100,
        joint_owner_id: this.chattel.joint_owner_id || null,
        joint_owner_name: this.chattel.joint_owner_name || '',
        current_value: this.chattel.current_value || null,
        valuation_date: this.formatDateForInput(this.chattel.valuation_date),
        purchase_price: this.chattel.purchase_price || null,
        purchase_date: this.formatDateForInput(this.chattel.purchase_date),
        make: this.chattel.make || '',
        model: this.chattel.model || '',
        year: this.chattel.year || null,
        registration_number: this.chattel.registration_number || '',
        notes: this.chattel.notes || '',
      };

      // Set joint owner selection state based on existing data
      if (this.form.joint_owner_id) {
        this.jointOwnerSelection = 'linked_' + this.form.joint_owner_id;
      } else if (this.form.joint_owner_name) {
        this.jointOwnerSelection = 'other';
      } else {
        this.jointOwnerSelection = '';
      }
    },

    formatDateForInput(date) {
      if (!date) return '';
      const d = new Date(date);
      if (isNaN(d.getTime())) return '';
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    },

    handleJointOwnerSelection() {
      if (this.jointOwnerSelection.startsWith('linked_')) {
        // Extract ID and set joint_owner_id
        this.form.joint_owner_id = parseInt(this.jointOwnerSelection.replace('linked_', ''));
        this.form.joint_owner_name = ''; // Clear free text field
      } else if (this.jointOwnerSelection === 'other') {
        // Clear linked ID when using free text
        this.form.joint_owner_id = null;
      } else {
        // No selection - clear both
        this.form.joint_owner_id = null;
        this.form.joint_owner_name = '';
      }
    },

    validate() {
      if (!this.form.chattel_type) {
        this.error = 'Please select a chattel type';
        return false;
      }
      if (!this.form.name || !this.form.name.trim()) {
        this.error = 'Please enter a name for this item';
        return false;
      }
      if (!this.form.current_value || this.form.current_value <= 0) {
        this.error = 'Please enter a valid current value';
        return false;
      }
      return true;
    },

    async handleSave() {
      this.error = null;

      if (!this.validate()) {
        return;
      }

      this.saving = true;

      try {
        // Clean up form data
        const formData = { ...this.form };

        // Remove empty vehicle fields if not a vehicle
        if (formData.chattel_type !== 'vehicle') {
          delete formData.make;
          delete formData.model;
          delete formData.year;
          delete formData.registration_number;
        }

        // Clean up empty values
        if (!formData.purchase_price) delete formData.purchase_price;
        if (!formData.purchase_date) delete formData.purchase_date;
        if (!formData.valuation_date) delete formData.valuation_date;
        if (!formData.description) delete formData.description;
        if (!formData.notes) delete formData.notes;

        this.$emit('save', formData);
      } catch (err) {
        this.error = err.message || 'Failed to save chattel';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
