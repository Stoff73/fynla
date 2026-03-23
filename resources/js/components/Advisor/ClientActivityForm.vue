<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-center justify-center"
  >
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-horizon-500/50"
      @click="$emit('close')"
    ></div>

    <!-- Modal -->
    <div class="relative bg-white rounded-xl shadow-xl border border-light-gray w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-light-gray">
        <h2 class="text-lg font-bold text-horizon-500">Log Activity</h2>
        <button
          class="text-neutral-500 hover:text-horizon-500 transition-colors"
          @click="$emit('close')"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-4">
          <!-- Client selector -->
          <div>
            <label class="block text-sm font-semibold text-horizon-500 mb-1">
              Client            </label>
            <select
              v-model="formData.client_id"
              class="w-full px-3 py-2 text-sm border rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
              :class="errors.client_id ? 'border-raspberry-500' : 'border-light-gray'"
            >
              <option value="">Select a client</option>
              <option
                v-for="client in clients"
                :key="client.client_id"
                :value="client.client_id"
              >
                {{ client.display_name }}
              </option>
            </select>
            <p v-if="errors.client_id" class="text-xs text-raspberry-500 mt-1">{{ errors.client_id }}</p>
          </div>

          <!-- Activity type -->
          <div>
            <label class="block text-sm font-semibold text-horizon-500 mb-1">
              Activity Type            </label>
            <select
              v-model="formData.activity_type"
              class="w-full px-3 py-2 text-sm border rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
              :class="errors.activity_type ? 'border-raspberry-500' : 'border-light-gray'"
            >
              <option value="">Select activity type</option>
              <option value="email">Email</option>
              <option value="phone">Phone</option>
              <option value="meeting">Meeting</option>
              <option value="letter">Letter</option>
              <option value="suitability_report">Suitability Report</option>
              <option value="review">Review</option>
              <option value="note">Note</option>
            </select>
            <p v-if="errors.activity_type" class="text-xs text-raspberry-500 mt-1">{{ errors.activity_type }}</p>
          </div>

          <!-- Summary -->
          <div>
            <label class="block text-sm font-semibold text-horizon-500 mb-1">
              Summary            </label>
            <input
              v-model="formData.summary"
              type="text"
              maxlength="500"
              placeholder="Brief summary of the activity"
              class="w-full px-3 py-2 text-sm border rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
              :class="errors.summary ? 'border-raspberry-500' : 'border-light-gray'"
            />
            <div class="flex justify-between mt-1">
              <p v-if="errors.summary" class="text-xs text-raspberry-500">{{ errors.summary }}</p>
              <span class="text-xs text-neutral-500 ml-auto">{{ formData.summary.length }}/500</span>
            </div>
          </div>

          <!-- Details -->
          <div>
            <label class="block text-sm font-semibold text-horizon-500 mb-1">Details</label>
            <textarea
              v-model="formData.details"
              rows="3"
              placeholder="Additional details (optional)"
              class="w-full px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all resize-none"
            ></textarea>
          </div>

          <!-- Activity date -->
          <div>
            <label class="block text-sm font-semibold text-horizon-500 mb-1">
              Activity Date            </label>
            <input
              v-model="formData.activity_date"
              type="date"
              class="w-full px-3 py-2 text-sm border rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
              :class="errors.activity_date ? 'border-raspberry-500' : 'border-light-gray'"
            />
            <p v-if="errors.activity_date" class="text-xs text-raspberry-500 mt-1">{{ errors.activity_date }}</p>
          </div>

          <!-- Follow-up date -->
          <div>
            <label class="block text-sm font-semibold text-horizon-500 mb-1">Follow-up Date</label>
            <input
              v-model="formData.follow_up_date"
              type="date"
              class="w-full px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
            />
          </div>

          <!-- Report type (shown only for suitability_report) -->
          <div v-if="formData.activity_type === 'suitability_report'">
            <label class="block text-sm font-semibold text-horizon-500 mb-1">Report Type</label>
            <input
              v-model="formData.report_type"
              type="text"
              placeholder="e.g. Annual Review, Pension Transfer"
              class="w-full px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
            />
          </div>

          <!-- Report sent date (shown only for suitability_report) -->
          <div v-if="formData.activity_type === 'suitability_report'">
            <label class="block text-sm font-semibold text-horizon-500 mb-1">Report Sent Date</label>
            <input
              v-model="formData.report_sent_date"
              type="date"
              class="w-full px-3 py-2 text-sm border border-light-gray rounded-md bg-white text-horizon-500 focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 transition-all"
            />
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 p-6 border-t border-light-gray">
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold rounded-lg border border-light-gray bg-white text-horizon-500 shadow-sm hover:bg-savannah-100 hover:shadow-md transition-all"
            @click="$emit('close')"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-semibold rounded-lg bg-raspberry-500 text-white shadow-sm hover:bg-raspberry-600 hover:shadow-md transition-all"
          >
            Save Activity
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { formatDateForInput } from '@/utils/dateFormatter';

export default {
  name: 'ClientActivityForm',

  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    clients: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['save', 'close'],

  data() {
    return {
      formData: {
        client_id: '',
        activity_type: '',
        summary: '',
        details: '',
        activity_date: formatDateForInput(new Date()),
        follow_up_date: '',
        report_type: '',
        report_sent_date: '',
      },
      errors: {},
    };
  },

  watch: {
    visible(newVal) {
      if (newVal) {
        this.resetForm();
      }
    },
  },

  methods: {
    validate() {
      this.errors = {};

      if (!this.formData.client_id) {
        this.errors.client_id = 'Please select a client.';
      }

      if (!this.formData.activity_type) {
        this.errors.activity_type = 'Please select an activity type.';
      }

      if (!this.formData.summary.trim()) {
        this.errors.summary = 'Summary is required.';
      } else if (this.formData.summary.length > 500) {
        this.errors.summary = 'Summary must be 500 characters or fewer.';
      }

      if (!this.formData.activity_date) {
        this.errors.activity_date = 'Activity date is required.';
      }

      if (this.formData.follow_up_date && this.formData.activity_date) {
        if (new Date(this.formData.follow_up_date) < new Date(this.formData.activity_date)) {
          this.errors.activity_date = 'Follow-up date must be after the activity date.';
        }
      }

      return Object.keys(this.errors).length === 0;
    },

    handleSubmit() {
      if (!this.validate()) return;

      const payload = {
        client_id: this.formData.client_id,
        activity_type: this.formData.activity_type,
        summary: this.formData.summary.trim(),
        details: this.formData.details.trim() || null,
        activity_date: this.formData.activity_date,
        follow_up_date: this.formData.follow_up_date || null,
      };

      if (this.formData.activity_type === 'suitability_report') {
        payload.report_type = this.formData.report_type.trim() || null;
        payload.report_sent_date = this.formData.report_sent_date || null;
      }

      this.$emit('save', payload);
    },

    resetForm() {
      this.formData = {
        client_id: '',
        activity_type: '',
        summary: '',
        details: '',
        activity_date: formatDateForInput(new Date()),
        follow_up_date: '',
        report_type: '',
        report_sent_date: '',
      };
      this.errors = {};
    },
  },
};
</script>
