<template>
  <div class="bg-violet-50 border border-violet-200 p-4">
    <div class="flex">
      <div class="flex-shrink-0">
        <svg
          class="h-5 w-5 text-violet-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      <div class="ml-3 flex-1">
        <h3 class="text-sm font-medium text-violet-800">Missing Information</h3>
        <div class="mt-2 text-sm text-violet-700">
          <p>{{ message }}</p>

          <ul class="list-disc list-inside mt-2 space-y-1">
            <li v-for="(item, index) in missingData" :key="index">
              {{ getMissingDataLabel(item) }}
            </li>
          </ul>

          <div class="mt-3">
            <router-link
              :to="getNavigationLink()"
              class="text-sm font-medium text-violet-800 underline hover:text-violet-900"
            >
              Add missing information →
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MissingDataAlert',

  props: {
    missingData: {
      type: Array,
      required: true,
    },
    message: {
      type: String,
      default: 'Some information is missing to complete the second death Inheritance Tax calculation:',
    },
  },

  methods: {
    getMissingDataLabel(item) {
      const labels = {
        spouse_account: 'Spouse account not linked',
        user: 'Your date of birth and gender',
        spouse: "Spouse's date of birth and gender",
        date_of_birth: 'Date of birth',
        gender: 'Gender',
        income: 'Annual income',
        expenditure: 'Monthly expenditure',
      };

      // Handle nested objects
      if (typeof item === 'object' && item !== null) {
        const key = Object.keys(item)[0];
        const fields = item[key];
        return `${key.charAt(0).toUpperCase() + key.slice(1)}: ${fields.join(', ')}`;
      }

      return labels[item] || item;
    },

    getNavigationLink() {
      // Determine where to send user based on missing data
      if (this.missingData.includes('spouse_account')) {
        return '/profile';
      }
      if (this.missingData.some(item => typeof item === 'object' && item.user)) {
        return '/profile';
      }
      if (this.missingData.some(item => typeof item === 'object' && item.spouse)) {
        return '/profile';
      }
      if (this.missingData.includes('income') || this.missingData.includes('expenditure')) {
        return '/profile';
      }
      return '/profile';
    },
  },
};
</script>
