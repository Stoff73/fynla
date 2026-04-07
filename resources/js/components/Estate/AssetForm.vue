<template>
  <div class="asset-form">
    <div class="form-header">
      <h3>{{ isEditMode ? 'Edit Asset' : 'Add New Asset' }}</h3>
      <p class="subtitle">Record assets for estate planning and net worth calculation</p>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- Asset Type -->
      <div class="form-group" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'asset_type' }">
        <label for="asset_type" class="required">Asset Type</label>
        <select
          id="asset_type"
          v-model="formData.asset_type"
          class="form-control"
          :class="{ 'is-invalid': errors.asset_type }"
          required
          @change="handleAssetTypeChange"
        >
          <option value="">Select asset type...</option>
          <option value="property">Property / Real Estate</option>
          <option value="pension">Pension</option>
          <option value="investment">Investments</option>
          <option value="savings">Savings / Cash</option>
          <option value="business">Business Interests</option>
          <option value="life_insurance">Life Insurance Policy</option>
          <option value="personal">Personal Possessions</option>
          <option value="other">Other</option>
        </select>
        <span v-if="errors.asset_type" class="error-message">
          {{ errors.asset_type }}
        </span>
      </div>

      <!-- Asset Name -->
      <div class="form-group" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'asset_name' }">
        <label for="asset_name" class="required">Asset Name / Description</label>
        <input
          id="asset_name"
          v-model="formData.asset_name"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.asset_name }"
          :placeholder="assetNamePlaceholder"
          required
        />
        <span v-if="errors.asset_name" class="error-message">
          {{ errors.asset_name }}
        </span>
      </div>

      <!-- Current Value -->
      <div class="form-group" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'current_value' }">
        <label for="current_value" class="required">Current Value (£)</label>
        <div class="input-with-icon">
          <span class="input-icon">£</span>
          <input
            id="current_value"
            v-model.number="formData.current_value"
            type="number"
            class="form-control with-icon"
            :class="{ 'is-invalid': errors.current_value }"
            placeholder="0"
            min="0"
            step="0.01"
            required
          />
        </div>
        <span v-if="errors.current_value" class="error-message">
          {{ errors.current_value }}
        </span>
      </div>

      <!-- Ownership Type -->
      <div class="form-group" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'ownership_type' }">
        <label for="ownership_type" class="required">Ownership</label>
        <select
          id="ownership_type"
          v-model="formData.ownership_type"
          class="form-control"
          :class="{ 'is-invalid': errors.ownership_type }"
          required
        >
          <option value="">Select ownership type...</option>
          <option value="individual">Individual Ownership</option>
          <option value="joint_tenants">Joint Tenants (with spouse/partner)</option>
          <option value="tenants_in_common">Tenants in Common</option>
          <option value="trust">Held in Trust</option>
        </select>
        <span v-if="errors.ownership_type" class="error-message">
          {{ errors.ownership_type }}
        </span>
        <small class="form-text">
          {{ ownershipTypeDescription }}
        </small>
      </div>

      <!-- Beneficiary Designation -->
      <div class="form-group">
        <label for="beneficiary_designation">Beneficiary Designation (Optional)</label>
        <input
          id="beneficiary_designation"
          v-model="formData.beneficiary_designation"
          type="text"
          class="form-control"
          placeholder="e.g., Spouse, Children, etc."
        />
        <small class="form-text">
          Applies to pensions and life insurance policies
        </small>
      </div>

      <!-- Valuation Date -->
      <div class="form-group">
        <label for="valuation_date" class="required">Valuation Date</label>
        <input
          id="valuation_date"
          v-model="formData.valuation_date"
          type="date"
          class="form-control"
          :class="{ 'is-invalid': errors.valuation_date }"
          :max="todayDate"
          required
        />
        <span v-if="errors.valuation_date" class="error-message">
          {{ errors.valuation_date }}
        </span>
        <small class="form-text">
          Date when this asset was last valued
        </small>
      </div>

      <!-- IHT Exemption Status -->
      <div class="form-group">
        <div class="checkbox-group">
          <input
            id="is_iht_exempt"
            v-model="formData.is_iht_exempt"
            type="checkbox"
            class="form-checkbox"
          />
          <label for="is_iht_exempt" class="checkbox-label">
            This asset is Inheritance Tax-exempt
          </label>
        </div>
        <small class="form-text">
          {{ ihtExemptDescription }}
        </small>
      </div>

      <!-- Conditional: Property-specific fields -->
      <div v-if="formData.asset_type === 'property'" class="conditional-fields">
        <h4 class="section-title">Property Details</h4>

        <div class="form-row">
          <div class="form-group">
            <label for="property_address">Property Address</label>
            <input
              id="property_address"
              v-model="formData.property_address"
              type="text"
              class="form-control"
              placeholder="Full address"
            />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group half-width">
            <label for="mortgage_outstanding">Outstanding Mortgage (£)</label>
            <div class="input-with-icon">
              <span class="input-icon">£</span>
              <input
                id="mortgage_outstanding"
                v-model.number="formData.mortgage_outstanding"
                type="number"
                class="form-control with-icon"
                placeholder="0"
                min="0"
              />
            </div>
          </div>

          <div class="form-group half-width">
            <div class="checkbox-group">
              <input
                id="is_main_residence"
                v-model="formData.is_main_residence"
                type="checkbox"
                class="form-checkbox"
              />
              <label for="is_main_residence" class="checkbox-label">
                Main Residence (eligible for Residence Nil Rate Band)
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="form-group">
        <label for="notes">Additional Notes (Optional)</label>
        <textarea
          id="notes"
          v-model="formData.notes"
          class="form-control"
          rows="3"
          placeholder="Any additional information about this asset..."
        ></textarea>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" @click="handleCancel">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
          <i v-if="!isSubmitting" class="fas fa-save"></i>
          <i v-else class="fas fa-spinner fa-spin"></i>
          {{ isSubmitting ? 'Saving...' : (isEditMode ? 'Update Asset' : 'Add Asset') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { mapState } from 'vuex';

import logger from '@/utils/logger';
export default {
  name: 'AssetForm',

  components: {},

  props: {
    asset: {
      type: Object,
      default: null,
    },
    mode: {
      type: String,
      default: 'create', // 'create' or 'edit'
    },
  },

  emits: ['save', 'cancel'],

  data() {
    return {
      formData: {
        asset_type: '',
        asset_name: '',
        current_value: null,
        ownership_type: '',
        beneficiary_designation: '',
        is_iht_exempt: false,
        valuation_date: new Date().toISOString().split('T')[0], // Default to today
        // Property-specific
        property_address: '',
        mortgage_outstanding: null,
        is_main_residence: false,
        // General
        notes: '',
      },
      postcodeValue: '', // For PostcodeLookup component
      errors: {},
      isSubmitting: false,
    };
  },

  computed: {
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

    isEditMode() {
      return this.mode === 'edit' && this.asset !== null;
    },

    todayDate() {
      return new Date().toISOString().split('T')[0];
    },

    assetNamePlaceholder() {
      const placeholders = {
        property: 'e.g., Main Residence, Buy-to-Let Property',
        pension: 'e.g., Workplace Pension, Self-Invested Personal Pension',
        investment: 'e.g., ISA, Investment Account',
        savings: 'e.g., Current Account, Premium Bonds',
        business: 'e.g., Company Shares, Partnership Interest',
        life_insurance: 'e.g., Whole of Life Policy',
        personal: 'e.g., Jewellery, Art Collection, Vehicle',
        other: 'e.g., Other Asset',
      };
      return placeholders[this.formData.asset_type] || 'Enter asset name';
    },

    ownershipTypeDescription() {
      const descriptions = {
        individual: 'You are the sole owner - passes via your will',
        joint_tenants: 'Automatically passes to surviving owner on death',
        tenants_in_common: 'Your share passes via your will',
        trust: 'Held in trust - special Inheritance Tax treatment may apply',
      };
      return descriptions[this.formData.ownership_type] || 'Select ownership type to see description';
    },

    ihtExemptDescription() {
      const exemptions = {
        pension: 'Pensions are typically Inheritance Tax-exempt if left to beneficiaries',
        life_insurance: 'Policies written in trust are Inheritance Tax-exempt',
        business: 'Business Relief may apply (50% or 100% relief)',
        property: 'May qualify for Agricultural Relief in specific cases',
      };
      return exemptions[this.formData.asset_type] || 'Check this if asset qualifies for Inheritance Tax exemptions (e.g., spouse transfers, charities, Business Property Relief, Agricultural Property Relief)';
    },
  },

  watch: {
    asset: {
      immediate: true,
      handler(newAsset) {
        if (newAsset && this.isEditMode) {
          this.populateForm(newAsset);
        }
      },
    },

    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'estate_asset' && fill.fields) {
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
          this.formData[fieldKey] = value;
        }
      }
    },

    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'estate_asset') {
        setTimeout(() => {
          this.handleSubmit();
        }, 250);
      }
    },
  },

  methods: {
    formatDateForInput(date) {
      if (!date) return new Date().toISOString().split('T')[0];
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return new Date().toISOString().split('T')[0];
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return new Date().toISOString().split('T')[0];
      }
    },

    populateForm(asset) {
      this.formData = {
        asset_type: asset.asset_type || '',
        asset_name: asset.asset_name || '',
        current_value: asset.current_value || null,
        ownership_type: asset.ownership_type || '',
        beneficiary_designation: asset.beneficiary_designation || '',
        is_iht_exempt: asset.is_iht_exempt || false,
        valuation_date: this.formatDateForInput(asset.valuation_date),
        property_address: asset.property_address || '',
        mortgage_outstanding: asset.mortgage_outstanding || null,
        is_main_residence: asset.is_main_residence || false,
        notes: asset.notes || '',
      };
    },

    handleAssetTypeChange() {
      // Clear property-specific fields if not property
      if (this.formData.asset_type !== 'property') {
        this.formData.property_address = '';
        this.formData.mortgage_outstanding = null;
        this.formData.is_main_residence = false;
        this.postcodeValue = '';
      }

      // Auto-suggest IHT exemption for certain asset types
      if (['pension', 'life_insurance'].includes(this.formData.asset_type)) {
        this.formData.is_iht_exempt = true;
      }
    },

    handleAddressSelected(address) {
      // Construct full address string from postcode lookup
      const parts = [
        address.line_1,
        address.line_2,
        address.city,
        address.county,
        address.postcode,
      ].filter(Boolean); // Remove empty parts
      this.formData.property_address = parts.join(', ');
    },

    validateForm() {
      this.errors = {};

      // Asset Type validation
      if (!this.formData.asset_type) {
        this.errors.asset_type = 'Asset type is required';
      }

      // Asset Name validation
      if (!this.formData.asset_name || this.formData.asset_name.trim() === '') {
        this.errors.asset_name = 'Asset name is required';
      }

      // Current Value validation
      if (!this.formData.current_value || this.formData.current_value <= 0) {
        this.errors.current_value = 'Current value must be greater than £0';
      }

      // Ownership Type validation
      if (!this.formData.ownership_type) {
        this.errors.ownership_type = 'Ownership type is required';
      }

      return Object.keys(this.errors).length === 0;
    },

    async handleSubmit() {
      if (!this.validateForm()) {
        return;
      }

      this.isSubmitting = true;

      try {
        const payload = {
          ...this.formData,
          id: this.isEditMode ? this.asset.id : undefined,
        };

        this.$emit('save', payload);

        // Reset form if creating new asset
        if (!this.isEditMode) {
          this.resetForm();
        }
      } catch (error) {
        logger.error('Error submitting asset form:', error);
        alert('An error occurred while saving the asset. Please try again.');
      } finally {
        this.isSubmitting = false;
      }
    },

    handleCancel() {
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.resetForm();
      this.$emit('cancel');
    },

    resetForm() {
      this.formData = {
        asset_type: '',
        asset_name: '',
        current_value: null,
        ownership_type: '',
        beneficiary_designation: '',
        is_iht_exempt: false,
        valuation_date: new Date().toISOString().split('T')[0],
        property_address: '',
        mortgage_outstanding: null,
        is_main_residence: false,
        notes: '',
      };
      this.postcodeValue = '';
      this.errors = {};
    },
  },
};
</script>

<style scoped>
.asset-form {
  background: white;
  border-radius: 8px;
  padding: 24px;
  max-height: 90vh;
  overflow-y: auto;
}

.form-header {
  margin-bottom: 24px;
  padding-bottom: 16px;
  @apply border-b-2 border-light-gray;
}

.form-header h3 {
  font-size: 20px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-neutral-500;
  margin: 24px 0 16px 0;
  padding-bottom: 8px;
  @apply border-b border-light-gray;
}

.form-group {
  margin-bottom: 20px;
}

.form-row {
  display: flex;
  gap: 16px;
}

.half-width {
  flex: 1;
}

label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  @apply text-neutral-500;
  margin-bottom: 6px;
}

label.required::after {
  content: ' *';
  @apply text-raspberry-500;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  @apply border border-horizon-300;
  border-radius: 6px;
  transition: border-colour 0.2s;
}

.form-control:focus {
  outline: none;
  @apply border-raspberry-500;
  @apply ring-2 ring-violet-500/20;
}

.form-control.is-invalid {
  @apply border-raspberry-500;
}

.form-control.is-invalid:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.input-with-icon {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  @apply text-neutral-500;
  font-weight: 500;
  pointer-events: none;
}

.form-control.with-icon {
  padding-left: 32px;
}

.error-message {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  @apply text-raspberry-500;
}

.form-text {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  @apply text-neutral-500;
  line-height: 1.4;
}

select.form-control {
  cursor: pointer;
}

textarea.form-control {
  resize: vertical;
  min-height: 80px;
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.checkbox-label {
  font-size: 14px;
  @apply text-neutral-500;
  cursor: pointer;
  margin: 0;
}

.conditional-fields {
  margin-top: 24px;
  padding: 20px;
  @apply bg-eggshell-500;
  border-radius: 6px;
  @apply border border-light-gray;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 20px;
  @apply border-t border-light-gray;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  @apply bg-raspberry-500;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  @apply bg-raspberry-500;
}

@media (max-width: 768px) {
  .form-row {
    flex-direction: column;
  }

  .half-width {
    width: 100%;
  }
}
</style>
