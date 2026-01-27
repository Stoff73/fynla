<template>
  <div v-if="show" class="modal-overlay">
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <h3 class="modal-title">{{ isEdit ? 'Edit Property' : 'Add Property' }}</h3>
        <button @click="closeModal" class="close-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form @submit.prevent="submitForm" class="modal-body">
        <!-- Property Type -->
        <div class="form-group">
          <label for="property_type" class="form-label">Property Type</label>
          <select
            id="property_type"
            v-model="formData.property_type"
            class="input-field"
          >
            <option value="">Select property type</option>
            <option value="main_residence">Main Residence</option>
            <option value="secondary_residence">Secondary Residence</option>
            <option value="buy_to_let">Buy to Let</option>
          </select>
          <span v-if="errors.property_type" class="error-message">{{ errors.property_type }}</span>
        </div>

        <!-- Address -->
        <div class="form-group">
          <label for="address" class="form-label">Address</label>
          <input
            id="address"
            v-model="formData.address"
            type="text"
            class="input-field"
            placeholder="123 Main Street, London"
          />
          <span v-if="errors.address" class="error-message">{{ errors.address }}</span>
        </div>

        <!-- Postcode -->
        <div class="form-group">
          <label for="postcode" class="form-label">Postcode</label>
          <input
            id="postcode"
            v-model="formData.postcode"
            type="text"
            class="input-field"
            placeholder="SW1A 1AA"
          />
        </div>

        <!-- Current Value -->
        <div class="form-group">
          <label for="current_value" class="form-label">Current Value (£)</label>
          <input
            id="current_value"
            v-model.number="formData.current_value"
            type="number"
            step="any"
            min="0"
            class="input-field"
            placeholder="500000"
          />
          <span v-if="errors.current_value" class="error-message">{{ errors.current_value }}</span>
        </div>

        <!-- Purchase Price -->
        <div class="form-group">
          <label for="purchase_price" class="form-label">Purchase Price (£)</label>
          <input
            id="purchase_price"
            v-model.number="formData.purchase_price"
            type="number"
            step="any"
            min="0"
            class="input-field"
            placeholder="400000"
          />
        </div>

        <!-- Purchase Date -->
        <div class="form-group">
          <label for="purchase_date" class="form-label">Purchase Date</label>
          <input
            id="purchase_date"
            v-model="formData.purchase_date"
            type="date"
            class="input-field"
          />
        </div>

        <!-- Outstanding Mortgage -->
        <div class="form-group">
          <label for="outstanding_mortgage" class="form-label">Outstanding Mortgage (£)</label>
          <input
            id="outstanding_mortgage"
            v-model.number="formData.outstanding_mortgage"
            type="number"
            step="any"
            min="0"
            class="input-field"
            placeholder="200000"
          />
        </div>

        <!-- Rental Income (for Buy to Let) -->
        <div v-if="formData.property_type === 'buy_to_let'" class="form-group">
          <label for="rental_income" class="form-label">Monthly Rental Income (£)</label>
          <input
            id="rental_income"
            v-model.number="formData.rental_income"
            type="number"
            step="any"
            min="0"
            class="input-field"
            placeholder="2000"
          />
        </div>

        <!-- Ownership Type -->
        <div class="form-group">
          <label class="form-label">Ownership Type</label>
          <div class="flex gap-4">
            <label class="flex items-center">
              <input
                type="radio"
                v-model="formData.ownership_type"
                value="individual"
                class="mr-2"
              />
              <span>Individual</span>
            </label>
            <label class="flex items-center">
              <input
                type="radio"
                v-model="formData.ownership_type"
                value="joint"
                class="mr-2"
              />
              <span>Joint Ownership</span>
            </label>
          </div>
        </div>

        <!-- Ownership Percentage -->
        <div v-if="formData.ownership_type === 'joint'" class="form-group">
          <label for="ownership_percentage" class="form-label">Your Ownership Percentage (%)</label>
          <input
            id="ownership_percentage"
            v-model.number="formData.ownership_percentage"
            type="number"
            step="any"
            min="0"
            max="100"
            class="input-field"
            placeholder="50"
          />
        </div>

        <!-- Joint Owner -->
        <div v-if="formData.ownership_type === 'joint'" class="form-group">
          <label for="joint_owner_id" class="form-label">Joint Owner</label>
          <select
            id="joint_owner_id"
            v-model="formData.joint_owner_id"
            class="input-field"
          >
            <option value="">Select joint owner</option>
            <option v-if="spouse" :value="spouse.id">{{ spouse.name }} (Spouse)</option>
            <option v-if="!spouse" value="" disabled>No spouse linked - add spouse in Family Members</option>
          </select>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label for="notes" class="form-label">Notes</label>
          <textarea
            id="notes"
            v-model="formData.notes"
            class="form-textarea"
            rows="3"
            placeholder="Additional notes about this property"
          ></textarea>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="button" @click="closeModal" class="btn-secondary">
            Cancel
          </button>
          <button type="submit" :disabled="submitting" class="btn-primary">
            {{ submitting ? 'Saving...' : (isEdit ? 'Update Property' : 'Add Property') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'PropertyForm',

  props: {
    show: {
      type: Boolean,
      default: false,
    },
    property: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      formData: {
        property_type: '',
        address: '',
        postcode: '',
        current_value: null,
        purchase_price: null,
        purchase_date: null,
        outstanding_mortgage: 0,
        rental_income: null,
        ownership_type: 'individual',
        ownership_percentage: 100,
        joint_owner_id: null,
        notes: '',
      },
      familyMembers: [],
      errors: {},
      submitting: false,
    };
  },

  computed: {
    isEdit() {
      return this.property && this.property.id;
    },

    spouse() {
      return this.$store.getters['userProfile/spouse'];
    },
  },

  watch: {
    property: {
      immediate: true,
      handler(newProperty) {
        if (newProperty) {
          this.formData = { ...newProperty };
        } else {
          this.resetForm();
        }
      },
    },
    'formData.ownership_type'(newVal) {
      if (newVal === 'individual') {
        this.formData.ownership_percentage = 100;
        this.formData.joint_owner_id = null;
      } else if (newVal === 'joint' && this.formData.ownership_percentage === 100) {
        this.formData.ownership_percentage = 50;
      }
    },
  },

  mounted() {
    this.loadFamilyMembers();
  },

  methods: {
    async loadFamilyMembers() {
      try {
        const response = await axios.get('/api/family-members');
        const familyMembers = response.data.data?.family_members || [];
        this.familyMembers = familyMembers.filter(member => member.user_id !== null);
      } catch (error) {
        console.error('Error loading family members:', error);
        this.familyMembers = [];
      }
    },

    closeModal() {
      this.$emit('close');
      this.resetForm();
    },

    resetForm() {
      this.formData = {
        property_type: '',
        address: '',
        postcode: '',
        current_value: null,
        purchase_price: null,
        purchase_date: null,
        outstanding_mortgage: 0,
        rental_income: null,
        ownership_type: 'individual',
        ownership_percentage: 100,
        joint_owner_id: null,
        notes: '',
      };
      this.errors = {};
      this.submitting = false;
    },

    validateForm() {
      // All fields are optional - no validation required
      this.errors = {};
      return true;
    },

    submitForm() {
      if (!this.validateForm()) {
        return;
      }

      this.submitting = true;
      this.$emit('save', this.formData);
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 16px;
}

.modal-container {
  background: white;
  border-radius: 12px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  @apply border-b border-gray-200;
}

.modal-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-gray-900;
  margin: 0;
}

.close-button {
  background: none;
  border: none;
  @apply text-gray-500;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
}

.close-button:hover {
  @apply bg-gray-100;
  @apply text-gray-900;
}

.modal-body {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  @apply text-gray-700;
  margin-bottom: 6px;
}

.input-field,
.input-field,
.form-textarea {
  width: 100%;
  padding: 10px 12px;
  @apply border border-gray-300;
  border-radius: 8px;
  font-size: 14px;
  @apply text-gray-900;
  transition: all 0.2s;
}

.input-field:focus,
.input-field:focus,
.form-textarea:focus {
  outline: none;
  @apply border-primary-500;
  @apply ring-2 ring-primary-500/20;
}

.form-textarea {
  resize: vertical;
  font-family: inherit;
}

.error-message {
  display: block;
  @apply text-red-500;
  font-size: 12px;
  margin-top: 4px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 20px;
  @apply border-t border-gray-200;
}

.btn-primary,
.btn-secondary {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-primary {
  @apply bg-primary-500;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  @apply bg-blue-600;
}

.btn-primary:disabled {
  @apply bg-gray-400;
  cursor: not-allowed;
}

.btn-secondary {
  background: white;
  @apply text-gray-700;
  @apply border border-gray-300;
}

.btn-secondary:hover {
  @apply bg-gray-50;
  @apply border-gray-400;
}

@media (max-width: 640px) {
  .modal-container {
    max-height: 100vh;
    border-radius: 0;
  }

  .modal-header,
  .modal-body {
    padding: 16px;
  }

  .form-actions {
    flex-direction: column;
  }

  .btn-primary,
  .btn-secondary {
    width: 100%;
  }
}
</style>
