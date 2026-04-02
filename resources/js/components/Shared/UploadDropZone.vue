<template>
  <div
    class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors"
    :class="dropZoneClass"
    @dragenter.prevent="handleDragEnter"
    @dragover.prevent="handleDragOver"
    @dragleave.prevent="handleDragLeave"
    @drop.prevent="handleDrop"
  >
    <!-- File Input (hidden) -->
    <input
      ref="fileInput"
      type="file"
      class="hidden"
      :accept="acceptString"
      @change="handleFileSelect"
    />

    <!-- No file selected -->
    <div v-if="!selectedFile" class="space-y-4">
      <!-- Upload Icon -->
      <div class="mx-auto w-16 h-16 text-horizon-400">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.5"
            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
          />
        </svg>
      </div>

      <!-- Instructions -->
      <div>
        <p class="text-neutral-500 font-medium">
          Drag and drop your document here
        </p>
        <p class="text-neutral-500 text-sm mt-1">
          or
          <button
            type="button"
            class="text-violet-600 hover:text-violet-700 font-medium"
            @click="openFileDialog"
          >
            click to browse
          </button>
        </p>
      </div>

      <!-- Supported formats -->
      <p class="text-horizon-400 text-xs">
        Supported: PDF, images, or Excel spreadsheets (max {{ maxSizeMB }}MB)
      </p>
      <p class="text-horizon-400 text-xs mt-1">
        Large images will be automatically compressed for processing
      </p>
    </div>

    <!-- File selected -->
    <div v-else class="space-y-4">
      <!-- File Icon -->
      <div class="mx-auto w-16 h-16" :class="fileIconClass">
        <!-- PDF Icon -->
        <svg v-if="isPdf" fill="currentColor" viewBox="0 0 24 24">
          <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10.92,12.31C10.68,11.54 10.15,9.08 11.55,9.04C12.95,9 12.03,12.16 12.03,12.16C12.42,13.65 14.05,14.72 14.05,14.72C14.55,14.57 17.4,14.24 17,15.72C16.57,17.2 13.5,15.81 13.5,15.81C11.55,15.95 10.09,16.47 10.09,16.47C8.96,18.58 7.64,19.5 7.1,18.61C6.43,17.5 9.23,16.07 9.23,16.07C10.68,13.72 10.92,12.31 10.92,12.31Z" />
        </svg>
        <!-- Excel Icon -->
        <svg v-else-if="isExcel" fill="currentColor" viewBox="0 0 24 24">
          <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M12.9,14.5L15.8,19H14L12,15.6L10,19H8.2L11.1,14.5L8.2,10H10L12,13.4L14,10H15.8L12.9,14.5Z" />
        </svg>
        <!-- Image Icon -->
        <svg v-else fill="currentColor" viewBox="0 0 24 24">
          <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" />
        </svg>
      </div>

      <!-- File Info -->
      <div>
        <p class="text-horizon-500 font-medium truncate max-w-xs mx-auto">
          {{ selectedFile.name }}
        </p>
        <p class="text-neutral-500 text-sm">
          {{ formatFileSize(selectedFile.size) }}
        </p>
      </div>

      <!-- Remove button -->
      <button
        type="button"
        class="text-raspberry-600 hover:text-raspberry-700 text-sm font-medium"
        @click="removeFile"
      >
        Remove
      </button>
    </div>

    <!-- Error message -->
    <div v-if="error" class="mt-4 text-raspberry-600 text-sm">
      {{ error }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'UploadDropZone',

  props: {
    acceptedTypes: {
      type: Array,
      default: () => ['.pdf', '.png', '.jpg', '.jpeg', '.webp', '.xlsx', '.xls', '.csv'],
    },
    maxSizeMB: {
      type: Number,
      default: 20,
    },
  },

  emits: ['file-selected', 'file-removed', 'error'],

  data() {
    return {
      selectedFile: null,
      isDragging: false,
      error: null,
    };
  },

  computed: {
    acceptString() {
      return this.acceptedTypes.join(',');
    },

    dropZoneClass() {
      if (this.isDragging) {
        return 'border-violet-500 bg-violet-50';
      }
      if (this.selectedFile) {
        return 'border-spring-500 bg-spring-50';
      }
      if (this.error) {
        return 'border-raspberry-300 bg-raspberry-50';
      }
      return 'border-horizon-300 bg-savannah-100 hover:border-horizon-400';
    },

    isPdf() {
      return this.selectedFile?.type === 'application/pdf';
    },

    isExcel() {
      const excelTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
      ];
      return excelTypes.includes(this.selectedFile?.type);
    },

    fileIconClass() {
      if (this.isPdf) return 'text-raspberry-500';
      if (this.isExcel) return 'text-spring-600';
      return 'text-violet-500';
    },
  },

  methods: {
    openFileDialog() {
      this.$refs.fileInput.click();
    },

    handleDragEnter() {
      this.isDragging = true;
    },

    handleDragOver() {
      this.isDragging = true;
    },

    handleDragLeave() {
      this.isDragging = false;
    },

    handleDrop(event) {
      this.isDragging = false;
      const files = event.dataTransfer.files;
      if (files.length > 0) {
        this.processFile(files[0]);
      }
    },

    handleFileSelect(event) {
      const files = event.target.files;
      if (files.length > 0) {
        this.processFile(files[0]);
      }
    },

    async processFile(file) {
      this.error = null;

      // Validate file type
      const allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
      ];
      if (!allowedMimes.includes(file.type)) {
        this.error = 'Invalid file type. Please upload a PDF, image, or spreadsheet (Excel, CSV).';
        this.$emit('error', this.error);
        return;
      }

      // Validate file size - 20MB for all file types
      const maxBytes = this.maxSizeMB * 1024 * 1024;

      if (file.size > maxBytes) {
        this.error = `File too large. Maximum size is ${this.maxSizeMB}MB.`;
        this.$emit('error', this.error);
        return;
      }

      // Compress images if they're large (> 2MB)
      let processedFile = file;
      if (file.type.startsWith('image/') && file.size > 2 * 1024 * 1024) {
        try {
          processedFile = await this.compressImage(file);
        } catch (err) {
          // If compression fails, use original file
          processedFile = file;
        }
      }

      this.selectedFile = processedFile;
      this.$emit('file-selected', processedFile);
    },

    compressImage(file) {
      return new Promise((resolve, reject) => {
        const img = new Image();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        img.onload = () => {
          // Calculate new dimensions (max 2000px on longest side for AI processing)
          const maxDimension = 2000;
          let { width, height } = img;

          if (width > maxDimension || height > maxDimension) {
            if (width > height) {
              height = Math.round((height / width) * maxDimension);
              width = maxDimension;
            } else {
              width = Math.round((width / height) * maxDimension);
              height = maxDimension;
            }
          }

          canvas.width = width;
          canvas.height = height;
          ctx.drawImage(img, 0, 0, width, height);

          // Convert to JPEG with 85% quality for good balance
          canvas.toBlob(
            (blob) => {
              if (blob) {
                // Create a new File object with the compressed data
                const compressedFile = new File(
                  [blob],
                  file.name.replace(/\.[^/.]+$/, '.jpg'),
                  { type: 'image/jpeg' }
                );
                resolve(compressedFile);
              } else {
                reject(new Error('Compression failed'));
              }
            },
            'image/jpeg',
            0.85
          );
        };

        img.onerror = () => reject(new Error('Failed to load image'));
        img.src = URL.createObjectURL(file);
      });
    },

    removeFile() {
      this.selectedFile = null;
      this.error = null;
      this.$refs.fileInput.value = '';
      this.$emit('file-removed');
    },

    formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },
  },
};
</script>
