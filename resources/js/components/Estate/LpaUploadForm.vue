<template>
  <div class="lpa-upload-form">
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md mx-4 w-full">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-bold text-horizon-500">Upload Existing Lasting Power of Attorney</h3>
          <button
            class="text-neutral-400 hover:text-horizon-500"
            @click="$emit('close')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit">
          <!-- LPA Type -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-500 mb-1">Type</label>
            <select
              v-model="lpaType"
              class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            >
              <option value="property_financial">Property & Financial Affairs</option>
              <option value="health_welfare">Health & Welfare</option>
            </select>
          </div>

          <!-- File Drop Zone -->
          <div
            class="border-2 border-dashed rounded-lg p-6 text-center transition-colors mb-4"
            :class="dragOver ? 'border-violet-500 bg-violet-50' : 'border-light-gray'"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="handleDrop"
          >
            <div v-if="!file">
              <svg class="w-10 h-10 text-neutral-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <p class="text-sm text-neutral-500 mb-2">Drag and drop your file here, or</p>
              <label class="inline-block px-3 py-1.5 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600 cursor-pointer">
                Choose File
                <input
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png"
                  class="hidden"
                  @change="handleFileSelect"
                />
              </label>
              <p class="text-xs text-neutral-400 mt-2">PDF, JPG, or PNG — maximum 10 MB</p>
            </div>

            <div v-else class="flex items-center justify-between">
              <div class="flex items-center">
                <svg class="w-6 h-6 text-spring-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div>
                  <p class="text-sm font-medium text-horizon-500">{{ file.name }}</p>
                  <p class="text-xs text-neutral-500">{{ formatFileSize(file.size) }}</p>
                </div>
              </div>
              <button
                class="text-xs text-raspberry-500 hover:text-raspberry-600"
                @click="file = null"
              >
                Remove
              </button>
            </div>
          </div>

          <!-- Error -->
          <p v-if="error" class="text-xs text-raspberry-500 mb-3">{{ error }}</p>

          <!-- Actions -->
          <div class="flex justify-end space-x-2">
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
              @click="$emit('close')"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600 disabled:opacity-50"
              :disabled="!file || uploading"
            >
              {{ uploading ? 'Uploading...' : 'Upload' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import estateService from '@/services/estateService';

export default {
  name: 'LpaUploadForm',

  props: {
    initialType: {
      type: String,
      default: 'property_financial',
    },
  },

  emits: ['close', 'uploaded'],

  data() {
    return {
      lpaType: this.initialType,
      file: null,
      dragOver: false,
      uploading: false,
      error: null,
    };
  },

  methods: {
    ...mapActions('estate', ['fetchLpas']),

    handleFileSelect(event) {
      const file = event.target.files[0];
      if (file) this.setFile(file);
    },

    handleDrop(event) {
      this.dragOver = false;
      const file = event.dataTransfer.files[0];
      if (file) this.setFile(file);
    },

    setFile(file) {
      const maxSize = 10 * 1024 * 1024; // 10 MB
      const allowed = ['application/pdf', 'image/jpeg', 'image/png'];

      if (!allowed.includes(file.type)) {
        this.error = 'File must be a PDF, JPG, or PNG.';
        return;
      }
      if (file.size > maxSize) {
        this.error = 'File must be no larger than 10 MB.';
        return;
      }

      this.file = file;
      this.error = null;
    },

    async handleSubmit() {
      if (!this.file) return;

      this.uploading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append('file', this.file);
        formData.append('lpa_type', this.lpaType);

        await estateService.uploadLpa(formData);
        await this.fetchLpas();
        this.$emit('uploaded');
        this.$emit('close');
      } catch (error) {
        this.error = error.response?.data?.message || 'Upload failed. Please try again.';
      } finally {
        this.uploading = false;
      }
    },

    formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },
  },
};
</script>
