<template>
  <div class="gift-form">
    <div class="form-header">
      <h3>{{ isEditMode ? 'Edit Gift' : 'Record New Gift' }}</h3>
      <p class="subtitle">Track gifts for IHT planning (7-year rule)</p>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- Gift Date -->
      <div class="form-group">
        <label for="gift_date">Gift Date</label>
        <input
          id="gift_date"
          v-model="formData.gift_date"
          type="date"
          class="form-control"
          :class="{ 'is-invalid': errors.gift_date }"
          :max="todayDate"
        />
        <span v-if="errors.gift_date" class="error-message">
          {{ errors.gift_date }}
        </span>
        <small class="form-text">
          Date when the gift was made (affects 7-year survival timeline)
        </small>
      </div>

      <!-- Recipient -->
      <div class="form-group">
        <label for="recipient">Recipient Name</label>
        <input
          id="recipient"
          v-model="formData.recipient"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.recipient }"
          placeholder="e.g., John Smith, Jane Doe"
        />
        <span v-if="errors.recipient" class="error-message">
          {{ errors.recipient }}
        </span>
      </div>

      <!-- Gift Value -->
      <div class="form-group">
        <label for="gift_value">Gift Value (£)</label>
        <div class="input-with-icon">
          <span class="input-icon">£</span>
          <input
            id="gift_value"
            v-model.number="formData.gift_value"
            type="number"
            class="form-control with-icon"
            :class="{ 'is-invalid': errors.gift_value }"
            placeholder="0"
            min="0"
            step="0.01"
          />
        </div>
        <span v-if="errors.gift_value" class="error-message">
          {{ errors.gift_value }}
        </span>
      </div>

      <!-- Gift Type -->
      <div class="form-group">
        <label for="gift_type">Gift Type</label>
        <select
          id="gift_type"
          v-model="formData.gift_type"
          class="form-control"
          :class="{ 'is-invalid': errors.gift_type }"
        >
          <option value="">Select gift type...</option>
          <option value="pet">Potentially Exempt Transfer (PET)</option>
          <option value="clt">Chargeable Lifetime Transfer (CLT)</option>
          <option value="exempt">Exempt Gift</option>
          <option value="small_gift">Small Gift Exemption (£250 limit)</option>
          <option value="annual_exemption">Annual Exemption (£3,000)</option>
        </select>
        <span v-if="errors.gift_type" class="error-message">
          {{ errors.gift_type }}
        </span>
        <small class="form-text">
          {{ giftTypeDescription }}
        </small>
      </div>

      <!-- Notes (Optional) -->
      <div class="form-group">
        <label for="notes">Notes (Optional)</label>
        <textarea
          id="notes"
          v-model="formData.notes"
          class="form-control"
          rows="3"
          placeholder="Additional information about this gift..."
        ></textarea>
      </div>

      <!-- Automatic Exemptions Info -->
      <div v-if="showExemptionInfo" class="exemption-info">
        <div class="info-header">
          <i class="fas fa-info-circle"></i>
          <span>Automatic Exemption Check</span>
        </div>
        <ul>
          <li v-if="qualifiesForSmallGift">
            ✓ This gift qualifies for the Small Gift Exemption (£250 or less per person per year)
          </li>
          <li v-if="canUseAnnualExemption">
            ✓ You can use your Annual Exemption (£3,000 per tax year)
          </li>
          <li v-if="!qualifiesForSmallGift && formData.gift_value > 3000">
            ⚠️ This gift exceeds typical exemptions and will be a PET (subject to 7-year rule)
          </li>
        </ul>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" @click="handleCancel">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
          <i v-if="!isSubmitting" class="fas fa-save"></i>
          <i v-else class="fas fa-spinner fa-spin"></i>
          {{ isSubmitting ? 'Saving...' : (isEditMode ? 'Update Gift' : 'Record Gift') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  name: 'GiftForm',

  props: {
    gift: {
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
        gift_date: '',
        recipient: '',
        gift_value: null,
        gift_type: '',
        notes: '',
      },
      errors: {},
      isSubmitting: false,
    };
  },

  computed: {
    isEditMode() {
      return this.mode === 'edit' && this.gift !== null;
    },

    todayDate() {
      return new Date().toISOString().split('T')[0];
    },

    giftTypeDescription() {
      const descriptions = {
        pet: 'Most common type - becomes IHT-free if you survive 7 years',
        clt: 'Gift to a trust or company - immediately taxable at 20%',
        exempt: 'Gifts to spouses, charities, or political parties',
        small_gift: 'Up to £250 per person per year (exempt immediately)',
        annual_exemption: 'First £3,000 of gifts each tax year (exempt immediately)',
      };
      return descriptions[this.formData.gift_type] || 'Select a type to see description';
    },

    showExemptionInfo() {
      return this.formData.gift_value > 0 && this.formData.gift_type;
    },

    qualifiesForSmallGift() {
      return this.formData.gift_value <= 250 && this.formData.gift_type === 'small_gift';
    },

    canUseAnnualExemption() {
      return this.formData.gift_value <= 3000 && this.formData.gift_type === 'annual_exemption';
    },
  },

  watch: {
    gift: {
      immediate: true,
      handler(newGift) {
        if (newGift && this.isEditMode) {
          this.populateForm(newGift);
        }
      },
    },
  },

  methods: {
    formatDateForInput(date) {
      if (!date) return '';
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    },

    populateForm(gift) {
      this.formData = {
        gift_date: this.formatDateForInput(gift.gift_date),
        recipient: gift.recipient || '',
        gift_value: gift.gift_value || null,
        gift_type: gift.gift_type || '',
        notes: gift.notes || '',
      };
    },

    validateForm() {
      // All fields are optional - no validation required
      this.errors = {};
      return true;
    },

    async handleSubmit() {
      if (!this.validateForm()) {
        return;
      }

      this.isSubmitting = true;

      try {
        const payload = {
          ...this.formData,
          id: this.isEditMode ? this.gift.id : undefined,
        };

        this.$emit('save', payload);

        // Reset form if creating new gift
        if (!this.isEditMode) {
          this.resetForm();
        }
      } catch (error) {
        console.error('Error submitting gift form:', error);
        alert('An error occurred while saving the gift. Please try again.');
      } finally {
        this.isSubmitting = false;
      }
    },

    handleCancel() {
      this.resetForm();
      this.$emit('cancel');
    },

    resetForm() {
      this.formData = {
        gift_date: '',
        recipient: '',
        gift_value: null,
        gift_type: '',
        notes: '',
      };
      this.errors = {};
    },
  },
};
</script>

<style scoped>
.gift-form {
  background: white;
  border-radius: 8px;
  padding: 24px;
}

.form-header {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 2px solid #e5e7eb;
}

.form-header h3 {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.form-group {
  margin-bottom: 20px;
}

label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

label.required::after {
  content: ' *';
  color: #ef4444;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  transition: border-colour 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control.is-invalid {
  border-color: #ef4444;
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
  color: #6b7280;
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
  color: #ef4444;
}

.form-text {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.4;
}

select.form-control {
  cursor: pointer;
}

textarea.form-control {
  resize: vertical;
  min-height: 80px;
}

.exemption-info {
  margin: 20px 0;
  padding: 16px;
  background-color: #eff6ff;
  border-left: 4px solid #3b82f6;
  border-radius: 4px;
}

.info-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #1e40af;
  margin-bottom: 12px;
  font-size: 14px;
}

.exemption-info ul {
  margin: 0;
  padding-left: 20px;
  list-style-type: none;
}

.exemption-info li {
  font-size: 13px;
  color: #1e3a8a;
  margin-bottom: 8px;
  line-height: 1.5;
  padding-left: 4px;
}

.exemption-info li:last-child {
  margin-bottom: 0;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
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
  background-color: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #2563eb;
}

.btn-secondary {
  background-color: #e5e7eb;
  color: #374151;
}

.btn-secondary:hover {
  background-color: #d1d5db;
}
</style>
