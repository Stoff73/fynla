<template>
  <div class="p-6">
    <h3 class="text-lg font-semibold text-horizon-500">
      {{ isEditMode ? 'Edit Bequest' : 'Add Bequest' }}
    </h3>
    <p class="text-sm text-neutral-500 mt-1 mb-6">
      A specific gift left to a named beneficiary in your will.
    </p>

    <form @submit.prevent="handleSubmit" class="space-y-5">
      <div>
        <label for="beneficiary_name" class="block text-sm font-medium text-neutral-500 mb-2">
          Beneficiary Name
        </label>
        <input
          id="beneficiary_name"
          v-model.trim="formData.beneficiary_name"
          type="text"
          maxlength="255"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="Who is this gift for?"
        />
        <p v-if="errors.beneficiary_name" class="text-xs text-raspberry-600 mt-1">
          {{ errors.beneficiary_name }}
        </p>
      </div>

      <!--
        W-0037. Charitable status was INFERRED from the beneficiary's name, and
        that decides a tax rate: a legacy of 10% or more of the baseline moves the
        whole estate from the standard Inheritance Tax rate to the reduced one.
        A charity whose name does not look like one was silently treated as an
        individual, and the household lost the reduced rate without being told.

        `Bequest::isCharitable()` reads this field FIRST and falls back to the
        name only when it is unset, so answering here settles it.
      -->
      <div>
        <label for="beneficiary_type" class="block text-sm font-medium text-neutral-500 mb-2">
          Who is the beneficiary?
        </label>
        <select
          id="beneficiary_type"
          v-model="formData.beneficiary_type"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
        >
          <option value="individual">A person</option>
          <option value="charity">A charity</option>
          <option value="trust">A trust</option>
          <option value="organization">An organisation</option>
        </select>
      </div>

      <div v-if="formData.beneficiary_type === 'charity'">
        <label for="charity_registration_number" class="block text-sm font-medium text-neutral-500 mb-2">
          Charity registration number (optional)
        </label>
        <input
          id="charity_registration_number"
          v-model.trim="formData.charity_registration_number"
          type="text"
          maxlength="50"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="For example 1089464"
        />
        <p class="text-xs text-neutral-500 mt-1">
          Helps your executors identify the right charity.
        </p>
      </div>

      <div>
        <label for="priority_order" class="block text-sm font-medium text-neutral-500 mb-2">
          Order of priority (optional)
        </label>
        <input
          id="priority_order"
          v-model.number="formData.priority_order"
          type="number"
          min="1"
          step="1"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="1"
        />
        <p class="text-xs text-neutral-500 mt-1">
          If the estate cannot meet every gift, lower numbers are paid first.
        </p>
      </div>

      <div>
        <label for="bequest_type" class="block text-sm font-medium text-neutral-500 mb-2">
          What are you leaving them?
        </label>
        <select
          id="bequest_type"
          v-model="formData.bequest_type"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
        >
          <option value="percentage">A percentage of your estate</option>
          <option value="specific_amount">A fixed sum of money</option>
          <option value="specific_asset">A specific asset</option>
          <option value="residuary">Whatever remains after other gifts</option>
        </select>
      </div>

      <div v-if="formData.bequest_type === 'percentage'">
        <label for="percentage_of_estate" class="block text-sm font-medium text-neutral-500 mb-2">
          Percentage of Estate (%)
        </label>
        <input
          id="percentage_of_estate"
          v-model.number="formData.percentage_of_estate"
          type="number"
          min="0"
          max="100"
          step="0.01"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="0"
        />
        <p v-if="errors.percentage_of_estate" class="text-xs text-raspberry-600 mt-1">
          {{ errors.percentage_of_estate }}
        </p>
      </div>

      <div v-else-if="formData.bequest_type === 'specific_amount'">
        <label for="specific_amount" class="block text-sm font-medium text-neutral-500 mb-2">
          Amount (£)
        </label>
        <input
          id="specific_amount"
          v-model.number="formData.specific_amount"
          type="number"
          min="0"
          step="0.01"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="0"
        />
        <p v-if="errors.specific_amount" class="text-xs text-raspberry-600 mt-1">
          {{ errors.specific_amount }}
        </p>
      </div>

      <div v-else-if="formData.bequest_type === 'specific_asset'">
        <label for="specific_asset_description" class="block text-sm font-medium text-neutral-500 mb-2">
          Which Asset?
        </label>
        <textarea
          id="specific_asset_description"
          v-model.trim="formData.specific_asset_description"
          rows="2"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="Describe the asset clearly enough for your executor to identify it"
        ></textarea>
        <p v-if="errors.specific_asset_description" class="text-xs text-raspberry-600 mt-1">
          {{ errors.specific_asset_description }}
        </p>
      </div>

      <div>
        <label for="conditions" class="block text-sm font-medium text-neutral-500 mb-2">
          Conditions (Optional)
        </label>
        <textarea
          id="conditions"
          v-model.trim="formData.conditions"
          rows="3"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="Any condition attached to this gift, such as the beneficiary reaching a certain age"
        ></textarea>
      </div>

      <div class="flex justify-end gap-3 pt-4 border-t border-light-gray">
        <button type="button" class="btn-secondary" :disabled="saving" @click="$emit('cancel')">
          Cancel
        </button>
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : (isEditMode ? 'Save Changes' : 'Add Bequest') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  name: 'BequestForm',

  emits: ['save', 'cancel'],

  props: {
    bequest: {
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
      formData: {
        beneficiary_name: '',
        // W-0037 — stated, not inferred from the name.
        beneficiary_type: 'individual',
        charity_registration_number: '',
        priority_order: null,
        bequest_type: 'percentage',
        percentage_of_estate: null,
        specific_amount: null,
        specific_asset_description: '',
        conditions: '',
      },
      errors: {},
    };
  },

  computed: {
    isEditMode() {
      return this.bequest !== null;
    },
  },

  created() {
    if (this.bequest) {
      this.formData = {
        beneficiary_name: this.bequest.beneficiary_name ?? '',
        beneficiary_type: this.bequest.beneficiary_type ?? 'individual',
        charity_registration_number: this.bequest.charity_registration_number ?? '',
        priority_order: this.bequest.priority_order !== null && this.bequest.priority_order !== undefined
          ? parseInt(this.bequest.priority_order, 10)
          : null,
        bequest_type: this.bequest.bequest_type ?? 'percentage',
        percentage_of_estate: this.bequest.percentage_of_estate !== null
          ? parseFloat(this.bequest.percentage_of_estate)
          : null,
        specific_amount: this.bequest.specific_amount !== null
          ? parseFloat(this.bequest.specific_amount)
          : null,
        specific_asset_description: this.bequest.specific_asset_description ?? '',
        conditions: this.bequest.conditions ?? '',
      };
    }
  },

  methods: {
    validate() {
      const errors = {};

      if (!this.formData.beneficiary_name) {
        errors.beneficiary_name = 'Tell us who this gift is for.';
      }

      // The server accepts each amount as nullable, so an unfilled bequest would
      // save as a gift of nothing. Catch it here instead.
      if (this.formData.bequest_type === 'percentage') {
        const percentage = this.formData.percentage_of_estate;
        if (percentage === null || percentage === '' || percentage <= 0 || percentage > 100) {
          errors.percentage_of_estate = 'Enter a percentage between 0 and 100.';
        }
      }

      if (this.formData.bequest_type === 'specific_amount') {
        const amount = this.formData.specific_amount;
        if (amount === null || amount === '' || amount <= 0) {
          errors.specific_amount = 'Enter an amount greater than zero.';
        }
      }

      if (this.formData.bequest_type === 'specific_asset' && !this.formData.specific_asset_description) {
        errors.specific_asset_description = 'Describe the asset you are leaving.';
      }

      this.errors = errors;

      return Object.keys(errors).length === 0;
    },

    handleSubmit() {
      if (!this.validate()) return;

      const type = this.formData.bequest_type;

      // Clear the fields the chosen type does not use, so switching type on an
      // existing bequest does not leave the old figure behind on the record.
      this.$emit('save', {
        beneficiary_name: this.formData.beneficiary_name,
        // W-0037 — sent explicitly. The controller falls back to
        // `Bequest::inferBeneficiaryType($name)` only when this key is absent
        // (`WillController:140-150`), so a form that does not send it leaves the
        // charitable decision to a guess about the beneficiary's name.
        beneficiary_type: this.formData.beneficiary_type,
        // Only meaningful for a charity, and cleared otherwise so switching type
        // does not leave a stale number behind on the record.
        charity_registration_number: this.formData.beneficiary_type === 'charity'
          ? (this.formData.charity_registration_number || null)
          : null,
        priority_order: this.formData.priority_order || null,
        bequest_type: type,
        percentage_of_estate: type === 'percentage' ? this.formData.percentage_of_estate : null,
        specific_amount: type === 'specific_amount' ? this.formData.specific_amount : null,
        specific_asset_description: type === 'specific_asset' ? this.formData.specific_asset_description : null,
        conditions: this.formData.conditions || null,
      });
    },
  },
};
</script>
