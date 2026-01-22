<template>
  <div class="postcode-lookup">
    <label
      v-if="label"
      :for="inputId"
      class="block text-body-sm font-medium text-gray-700 mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="flex gap-2">
      <!-- Postcode Input -->
      <div class="relative flex-grow">
        <input
          :id="inputId"
          v-model="postcodeInput"
          type="text"
          :placeholder="placeholder"
          :disabled="disabled || loading"
          class="input-field uppercase"
          :class="{
            'cursor-not-allowed bg-gray-100': disabled,
            'border-red-500': errorMessage,
          }"
          @keyup.enter="findAddresses"
          @input="clearError"
        />
      </div>

      <!-- Find Address Button -->
      <button
        type="button"
        :disabled="disabled || loading || !postcodeInput.trim()"
        class="btn-secondary whitespace-nowrap"
        :class="{ 'opacity-50 cursor-not-allowed': disabled || loading || !postcodeInput.trim() }"
        @click="findAddresses"
      >
        <span v-if="loading" class="flex items-center gap-2">
          <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          Finding...
        </span>
        <span v-else>Find Address</span>
      </button>
    </div>

    <!-- Error Message -->
    <p v-if="errorMessage" class="mt-1 text-body-sm text-red-600">
      {{ errorMessage }}
    </p>

    <!-- Address Dropdown -->
    <div
      v-if="showAddressDropdown && addresses.length > 0"
      class="mt-2"
    >
      <label class="block text-body-sm font-medium text-gray-700 mb-1">
        Select your address
      </label>
      <div class="relative">
        <select
          v-model="selectedAddressIndex"
          class="input-field"
          @change="handleAddressSelect"
        >
          <option :value="null" disabled>
            {{ addresses.length }} address{{ addresses.length !== 1 ? 'es' : '' }} found - select one
          </option>
          <option
            v-for="(address, index) in addresses"
            :key="index"
            :value="index"
          >
            {{ address.display }}
          </option>
        </select>
      </div>
    </div>

    <!-- No Results Message -->
    <p
      v-if="showNoResults"
      class="mt-2 text-body-sm text-gray-500"
    >
      No addresses found for this postcode. Please enter your address manually.
    </p>

    <!-- Manual Entry Link -->
    <p
      v-if="showManualEntryLink"
      class="mt-2 text-body-sm"
    >
      <button
        type="button"
        class="text-primary-600 hover:text-primary-700 underline"
        @click="handleManualEntry"
      >
        Enter address manually
      </button>
    </p>
  </div>
</template>

<script>
import postcodeService from '@/services/postcodeService';

export default {
  name: 'PostcodeLookup',

  props: {
    modelValue: {
      type: String,
      default: '',
    },
    label: {
      type: String,
      default: 'Postcode',
    },
    placeholder: {
      type: String,
      default: 'Enter postcode (e.g., SW1A 1AA)',
    },
    required: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['update:modelValue', 'address-selected', 'manual-entry'],

  data() {
    return {
      uniqueId: `postcode-lookup-${Math.random().toString(36).substr(2, 9)}`,
      postcodeInput: this.modelValue || '',
      loading: false,
      errorMessage: '',
      addresses: [],
      selectedAddressIndex: null,
      showAddressDropdown: false,
      showNoResults: false,
      hasSearched: false,
    };
  },

  computed: {
    inputId() {
      return this.uniqueId;
    },

    showManualEntryLink() {
      // Show after a search with no results or an error
      return this.hasSearched && (this.showNoResults || this.errorMessage);
    },
  },

  watch: {
    modelValue(newValue) {
      if (newValue !== this.postcodeInput) {
        this.postcodeInput = newValue;
      }
    },

    postcodeInput(newValue) {
      this.$emit('update:modelValue', newValue);
    },
  },

  methods: {
    clearError() {
      this.errorMessage = '';
      this.showNoResults = false;
    },

    async findAddresses() {
      if (!this.postcodeInput.trim()) {
        return;
      }

      this.loading = true;
      this.errorMessage = '';
      this.addresses = [];
      this.selectedAddressIndex = null;
      this.showAddressDropdown = false;
      this.showNoResults = false;

      try {
        const result = await postcodeService.findAddresses(this.postcodeInput);
        this.hasSearched = true;

        if (result.success) {
          // Update postcode to normalised format
          if (result.postcode) {
            this.postcodeInput = result.postcode;
            this.$emit('update:modelValue', result.postcode);
          }

          if (result.addresses && result.addresses.length > 0) {
            this.addresses = result.addresses;
            this.showAddressDropdown = true;
          } else {
            this.showNoResults = true;
          }
        } else {
          this.errorMessage = result.message || 'Address lookup failed. Please try again.';
        }
      } catch (error) {
        this.hasSearched = true;
        this.errorMessage = 'Address lookup unavailable. Please enter address manually.';
        console.error('Postcode lookup error:', error);
      } finally {
        this.loading = false;
      }
    },

    handleAddressSelect() {
      if (this.selectedAddressIndex !== null && this.addresses[this.selectedAddressIndex]) {
        const address = this.addresses[this.selectedAddressIndex];

        // Emit the selected address data
        this.$emit('address-selected', {
          line_1: address.line_1,
          line_2: address.line_2,
          city: address.city,
          county: address.county,
          postcode: address.postcode,
        });

        // Hide dropdown after selection
        this.showAddressDropdown = false;
      }
    },

    handleManualEntry() {
      // Reset state and emit manual-entry event
      this.showNoResults = false;
      this.errorMessage = '';
      this.$emit('manual-entry');
    },

    // Public method to reset the component state
    reset() {
      this.postcodeInput = '';
      this.loading = false;
      this.errorMessage = '';
      this.addresses = [];
      this.selectedAddressIndex = null;
      this.showAddressDropdown = false;
      this.showNoResults = false;
      this.hasSearched = false;
    },
  },
};
</script>

<style scoped>
/* Uppercase postcode input */
.uppercase {
  text-transform: uppercase;
}

.uppercase::placeholder {
  text-transform: none;
}
</style>
