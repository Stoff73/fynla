# Fynla Form Modal Pattern

## Overview

Form modals are a critical pattern in Fynla for creating and editing data. This document provides the correct pattern to avoid common pitfalls, especially the `@submit` event conflict bug.

## The @submit Bug

**CRITICAL**: Never use `@submit` as a custom event name in Vue components.

### Why This Matters

When you use `@submit` on a component, Vue listens for BOTH:
1. Custom `$emit('submit')` events from the component
2. Native HTML form `submit` events

This causes **double submissions**:
- First submission: Sends correct form data
- Second submission: Sends SubmitEvent object
- Result: 422 validation errors, duplicate API calls

### Correct Pattern

Use `@save` instead of `@submit` for custom form events.

---

## Form Modal Component Pattern

### Template Structure

```vue
<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click.self="handleClose"
  >
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">{{ title }}</h2>
        <button
          @click="handleClose"
          class="text-gray-400 hover:text-gray-600 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm">
        <div class="px-6 py-4 space-y-4">
          <!-- Error Message -->
          <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-red-800 text-sm">{{ errorMessage }}</p>
          </div>

          <!-- Form Fields -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Field Name <span class="text-red-500">*</span>
            </label>
            <input
              v-model="formData.fieldName"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': errors.fieldName }"
              required
            />
            <p v-if="errors.fieldName" class="text-red-600 text-sm mt-1">
              {{ errors.fieldName }}
            </p>
          </div>

          <!-- More fields... -->
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
          <button
            type="button"
            @click="handleClose"
            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors"
            :disabled="submitting"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50"
            :disabled="submitting"
          >
            {{ submitting ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FormModal',

  props: {
    isOpen: {
      type: Boolean,
      required: true,
    },
    title: {
      type: String,
      required: true,
    },
    initialData: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      formData: {
        fieldName: '',
        // ... other fields
      },
      errors: {},
      errorMessage: '',
      submitting: false,
    };
  },

  watch: {
    isOpen(newVal) {
      if (newVal) {
        this.initializeForm();
      }
    },

    initialData: {
      handler(newVal) {
        if (newVal && this.isOpen) {
          this.initializeForm();
        }
      },
      deep: true,
    },
  },

  methods: {
    initializeForm() {
      if (this.initialData) {
        // Editing existing item
        this.formData = { ...this.initialData };
      } else {
        // Creating new item
        this.formData = {
          fieldName: '',
          // ... reset to defaults
        };
      }
      this.errors = {};
      this.errorMessage = '';
    },

    validateForm() {
      this.errors = {};

      if (!this.formData.fieldName) {
        this.errors.fieldName = 'This field is required';
      }

      // More validation...

      return Object.keys(this.errors).length === 0;
    },

    submitForm() {
      // Prevent double submission
      if (this.submitting) return;

      // Validate
      if (!this.validateForm()) {
        return;
      }

      // Emit 'save' event (NOT 'submit')
      this.submitting = true;
      this.$emit('save', { ...this.formData });
    },

    handleClose() {
      if (this.submitting) return;
      this.$emit('close');
    },

    // Parent component should call this on error
    handleError(message) {
      this.submitting = false;
      this.errorMessage = message;
    },

    // Parent component should call this on success
    handleSuccess() {
      this.submitting = false;
      this.initializeForm();
    },
  },
};
</script>
```

---

## Parent Component Pattern

### Using the Form Modal

```vue
<template>
  <div>
    <!-- Trigger Button -->
    <button @click="openModal" class="btn-primary">
      Add New Item
    </button>

    <!-- Form Modal -->
    <FormModal
      :is-open="isModalOpen"
      :title="modalTitle"
      :initial-data="editingItem"
      @save="handleSave"
      @close="closeModal"
      ref="formModal"
    />

    <!-- Success Message -->
    <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
      <p class="text-green-800">{{ successMessage }}</p>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import FormModal from '@/components/Common/FormModal.vue';

export default {
  name: 'ParentComponent',

  components: {
    FormModal,
  },

  data() {
    return {
      isModalOpen: false,
      editingItem: null,
      successMessage: '',
    };
  },

  computed: {
    modalTitle() {
      return this.editingItem ? 'Edit Item' : 'Add New Item';
    },
  },

  methods: {
    ...mapActions('module', ['createItem', 'updateItem']),

    openModal(item = null) {
      this.editingItem = item;
      this.isModalOpen = true;
      this.successMessage = '';
    },

    closeModal() {
      this.isModalOpen = false;
      this.editingItem = null;
    },

    async handleSave(formData) {
      try {
        if (this.editingItem) {
          // Update existing item
          await this.updateItem({
            id: this.editingItem.id,
            data: formData,
          });
          this.successMessage = 'Item updated successfully';
        } else {
          // Create new item
          await this.createItem(formData);
          this.successMessage = 'Item created successfully';
        }

        // Close modal on success
        this.$refs.formModal.handleSuccess();
        this.closeModal();

        // Refresh data
        await this.$store.dispatch('module/fetchData');
      } catch (error) {
        // Keep modal open on error
        this.$refs.formModal.handleError(
          error.response?.data?.message || 'Failed to save item'
        );
      }
    },
  },
};
</script>
```

---

## Form Field Patterns

### Text Input

```vue
<div>
  <label class="block text-sm font-medium text-gray-700 mb-2">
    {{ label }} <span v-if="required" class="text-red-500">*</span>
  </label>
  <input
    v-model="formData.fieldName"
    type="text"
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
    :class="{ 'border-red-500': errors.fieldName }"
    :required="required"
    :placeholder="placeholder"
  />
  <p v-if="errors.fieldName" class="text-red-600 text-sm mt-1">
    {{ errors.fieldName }}
  </p>
</div>
```

### Number Input (Currency)

```vue
<div>
  <label class="block text-sm font-medium text-gray-700 mb-2">
    Amount (£) <span class="text-red-500">*</span>
  </label>
  <input
    v-model.number="formData.amount"
    type="number"
    step="0.01"
    min="0"
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
    :class="{ 'border-red-500': errors.amount }"
    required
  />
  <p v-if="errors.amount" class="text-red-600 text-sm mt-1">
    {{ errors.amount }}
  </p>
</div>
```

### Select Dropdown

```vue
<div>
  <label class="block text-sm font-medium text-gray-700 mb-2">
    Ownership Type <span class="text-red-500">*</span>
  </label>
  <select
    v-model="formData.ownership_type"
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
    :class="{ 'border-red-500': errors.ownership_type }"
    required
  >
    <option value="">Select...</option>
    <option value="individual">Individual Owner</option>
    <option value="joint">Joint Owner</option>
    <option value="trust">Trust</option>
  </select>
  <p v-if="errors.ownership_type" class="text-red-600 text-sm mt-1">
    {{ errors.ownership_type }}
  </p>
</div>
```

### Date Input

```vue
<div>
  <label class="block text-sm font-medium text-gray-700 mb-2">
    Date <span class="text-red-500">*</span>
  </label>
  <input
    v-model="formData.date"
    type="date"
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
    :class="{ 'border-red-500': errors.date }"
    required
  />
  <p v-if="errors.date" class="text-red-600 text-sm mt-1">
    {{ errors.date }}
  </p>
</div>
```

### Checkbox

```vue
<div class="flex items-center">
  <input
    v-model="formData.is_active"
    type="checkbox"
    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
  />
  <label class="ml-2 block text-sm text-gray-700">
    Is Active
  </label>
</div>
```

### Textarea

```vue
<div>
  <label class="block text-sm font-medium text-gray-700 mb-2">
    Description
  </label>
  <textarea
    v-model="formData.description"
    rows="4"
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
    :class="{ 'border-red-500': errors.description }"
    placeholder="Enter description..."
  ></textarea>
  <p v-if="errors.description" class="text-red-600 text-sm mt-1">
    {{ errors.description }}
  </p>
</div>
```

---

## Validation Patterns

### Required Field Validation

```javascript
if (!this.formData.fieldName || this.formData.fieldName.trim() === '') {
  this.errors.fieldName = 'This field is required';
}
```

### Numeric Range Validation

```javascript
if (this.formData.amount < 0) {
  this.errors.amount = 'Amount must be positive';
}

if (this.formData.age < 18 || this.formData.age > 100) {
  this.errors.age = 'Age must be between 18 and 100';
}
```

### Conditional Validation

```javascript
// Joint owner required if ownership_type is 'joint'
if (this.formData.ownership_type === 'joint' && !this.formData.joint_owner_id) {
  this.errors.joint_owner_id = 'Joint owner is required for joint ownership';
}

// ISA must be individually owned (UK tax rule)
if (this.formData.account_type === 'isa' && this.formData.ownership_type !== 'individual') {
  this.errors.ownership_type = 'ISAs can only be individually owned under UK tax rules';
}
```

### Email Validation

```javascript
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
if (this.formData.email && !emailRegex.test(this.formData.email)) {
  this.errors.email = 'Please enter a valid email address';
}
```

### Date Validation

```javascript
const selectedDate = new Date(this.formData.date);
const today = new Date();

if (selectedDate > today) {
  this.errors.date = 'Date cannot be in the future';
}
```

---

## Common Pitfalls

### ❌ DON'T: Use @submit

```vue
<!-- WRONG -->
<FormModal @submit="handleSubmit" />
```

**Problem**: Causes double submission (custom event + native form submit)

### ✅ DO: Use @save

```vue
<!-- CORRECT -->
<FormModal @save="handleSubmit" />
```

### ❌ DON'T: Close modal immediately on submit

```vue
<!-- WRONG -->
async handleSubmit(formData) {
  this.closeModal(); // Closes before knowing if save succeeded
  await this.saveData(formData);
}
```

**Problem**: User loses form data if save fails

### ✅ DO: Close modal after successful save

```vue
<!-- CORRECT -->
async handleSubmit(formData) {
  try {
    await this.saveData(formData);
    this.closeModal(); // Only close on success
  } catch (error) {
    this.$refs.formModal.handleError(error.message);
    // Modal stays open so user can fix errors
  }
}
```

### ❌ DON'T: Forget to reset submitting state

```vue
<!-- WRONG -->
async handleSubmit(formData) {
  this.submitting = true;
  await this.saveData(formData);
  // Forgot: this.submitting = false
}
```

**Problem**: Submit button stays disabled after error

### ✅ DO: Always reset submitting state

```vue
<!-- CORRECT -->
async handleSubmit(formData) {
  try {
    this.submitting = true;
    await this.saveData(formData);
  } finally {
    this.submitting = false; // Always reset
  }
}
```

---

## Advanced: Multi-Step Form Modal

For complex forms with multiple steps:

```vue
<template>
  <div class="form-modal">
    <!-- Step Indicator -->
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <div
          v-for="(step, index) in steps"
          :key="index"
          class="flex items-center"
        >
          <div
            :class="[
              'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
              currentStep === index
                ? 'bg-blue-600 text-white'
                : currentStep > index
                ? 'bg-green-600 text-white'
                : 'bg-gray-200 text-gray-600',
            ]"
          >
            {{ index + 1 }}
          </div>
          <span class="ml-2 text-sm text-gray-700">{{ step.label }}</span>
          <div v-if="index < steps.length - 1" class="w-12 h-0.5 bg-gray-200 mx-4"></div>
        </div>
      </div>
    </div>

    <!-- Step Content -->
    <form @submit.prevent="handleNext">
      <div v-if="currentStep === 0" class="px-6 py-4 space-y-4">
        <!-- Step 1 fields -->
      </div>

      <div v-if="currentStep === 1" class="px-6 py-4 space-y-4">
        <!-- Step 2 fields -->
      </div>

      <!-- Footer with Back/Next buttons -->
      <div class="px-6 py-4 border-t border-gray-200 flex justify-between">
        <button
          v-if="currentStep > 0"
          type="button"
          @click="handleBack"
          class="btn-secondary"
        >
          Back
        </button>
        <div class="ml-auto space-x-3">
          <button type="button" @click="handleClose" class="btn-secondary">
            Cancel
          </button>
          <button type="submit" class="btn-primary">
            {{ currentStep === steps.length - 1 ? 'Save' : 'Next' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return {
      currentStep: 0,
      steps: [
        { label: 'Basic Info' },
        { label: 'Details' },
        { label: 'Review' },
      ],
    };
  },

  methods: {
    handleNext() {
      if (!this.validateCurrentStep()) return;

      if (this.currentStep === this.steps.length - 1) {
        // Last step - submit form
        this.submitForm();
      } else {
        // Move to next step
        this.currentStep++;
      }
    },

    handleBack() {
      if (this.currentStep > 0) {
        this.currentStep--;
      }
    },

    validateCurrentStep() {
      // Validate fields for current step only
      return true;
    },
  },
};
</script>
```
