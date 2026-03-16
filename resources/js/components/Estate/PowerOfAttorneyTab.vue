<template>
  <div class="power-of-attorney-tab">
    <!-- Detail View -->
    <LpaDetailView
      v-if="viewingLpa"
      :lpa="viewingLpa"
      @back="viewingLpa = null"
      @edit="handleEdit"
    />

    <!-- List View -->
    <div v-else>
      <!-- Introduction -->
      <div class="bg-white rounded-lg border border-light-gray p-5 mb-6">
        <h2 class="text-lg font-bold text-horizon-500 mb-2">Lasting Power of Attorney</h2>
        <p class="text-sm text-neutral-500 mb-3">
          A Lasting Power of Attorney allows you to appoint someone you trust to make decisions on your behalf
          if you lose mental capacity. There are two types — one for property and financial matters, and one
          for health and welfare decisions.
        </p>
        <p class="text-sm text-neutral-500">
          Without a Lasting Power of Attorney, your family may need to apply to the Court of Protection to manage
          your affairs, which can cost over £1,000 per year and take months to arrange.
        </p>
      </div>

      <!-- Loading -->
      <div v-if="lpaLoading" class="text-center py-8">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
        <p class="mt-2 text-sm text-neutral-500">Loading Lasting Powers of Attorney...</p>
      </div>

      <div v-else>
        <!-- Two Type Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <!-- Property & Financial -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-center space-x-3 mb-3">
              <div class="w-10 h-10 rounded-lg bg-horizon-500 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-horizon-500">Property & Financial Affairs</h3>
                <p class="text-xs text-neutral-500">Bank accounts, investments, property, bills, tax affairs</p>
              </div>
            </div>

            <div v-if="propertyFinancialLpas.length > 0" class="space-y-3">
              <LpaSummaryCard
                v-for="lpa in propertyFinancialLpas"
                :key="lpa.id"
                :lpa="lpa"
                @view="viewLpa"
                @edit="handleEdit"
                @delete="handleDelete"
              />
            </div>
            <div v-else class="text-sm text-neutral-500 italic mb-3">
              No Property & Financial Affairs Lasting Power of Attorney created yet.
            </div>

            <div v-if="!hasPropertyFinancial" class="flex space-x-2 mt-4">
              <button
                v-preview-disabled
                class="px-3 py-1.5 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600"
                @click="createLpa('property_financial')"
              >
                Create
              </button>
              <button
                v-preview-disabled
                class="px-3 py-1.5 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
                @click="uploadType = 'property_financial'; showUploadModal = true"
              >
                Upload Existing
              </button>
            </div>
          </div>

          <!-- Health & Welfare -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-center space-x-3 mb-3">
              <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-horizon-500">Health & Welfare</h3>
                <p class="text-xs text-neutral-500">Medical treatment, care, daily routine, life-sustaining treatment</p>
              </div>
            </div>

            <div v-if="healthWelfareLpas.length > 0" class="space-y-3">
              <LpaSummaryCard
                v-for="lpa in healthWelfareLpas"
                :key="lpa.id"
                :lpa="lpa"
                @view="viewLpa"
                @edit="handleEdit"
                @delete="handleDelete"
              />
            </div>
            <div v-else class="text-sm text-neutral-500 italic mb-3">
              No Health & Welfare Lasting Power of Attorney created yet.
            </div>

            <div v-if="!hasHealthWelfare" class="flex space-x-2 mt-4">
              <button
                v-preview-disabled
                class="px-3 py-1.5 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600"
                @click="createLpa('health_welfare')"
              >
                Create
              </button>
              <button
                v-preview-disabled
                class="px-3 py-1.5 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
                @click="uploadType = 'health_welfare'; showUploadModal = true"
              >
                Upload Existing
              </button>
            </div>
          </div>
        </div>

        <!-- Legal Disclaimer -->
        <div class="bg-savannah-100 rounded-lg p-4 text-xs text-neutral-500">
          <p class="font-medium text-horizon-500 mb-1">Important Legal Information</p>
          <p>
            Fynla helps you record and organise your Lasting Power of Attorney details. To make a Lasting Power of
            Attorney legally valid, it must be printed, signed with wet ink signatures, and registered with the
            Office of the Public Guardian (currently £82 per registration). We recommend seeking professional
            legal advice when creating your Lasting Power of Attorney.
          </p>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <LpaUploadForm
      v-if="showUploadModal"
      :initial-type="uploadType"
      @close="showUploadModal = false"
      @uploaded="showUploadModal = false"
    />

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-sm mx-4">
        <h3 class="text-lg font-bold text-horizon-500 mb-2">Delete Lasting Power of Attorney</h3>
        <p class="text-sm text-neutral-500 mb-4">
          Are you sure you want to delete this {{ deletingLpa?.lpa_type === 'property_financial' ? 'Property & Financial Affairs' : 'Health & Welfare' }} Lasting Power of Attorney? This action cannot be undone.
        </p>
        <div class="flex justify-end space-x-2">
          <button
            class="px-4 py-2 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
            @click="showDeleteConfirm = false"
          >
            Cancel
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600"
            :disabled="deleting"
            @click="confirmDelete"
          >
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { previewModeMixin } from '@/mixins/previewModeMixin';
import LpaSummaryCard from './LpaSummaryCard.vue';
import LpaDetailView from './LpaDetailView.vue';
import LpaUploadForm from './LpaUploadForm.vue';

export default {
  name: 'PowerOfAttorneyTab',

  components: {
    LpaSummaryCard,
    LpaDetailView,
    LpaUploadForm,
  },

  mixins: [previewModeMixin],

  emits: ['switch-tab'],

  data() {
    return {
      viewingLpa: null,
      showDeleteConfirm: false,
      deletingLpa: null,
      deleting: false,
      showUploadModal: false,
      uploadType: 'property_financial',
    };
  },

  computed: {
    ...mapGetters('estate', ['lpas', 'lpaLoading', 'propertyFinancialLpas', 'healthWelfareLpas']),

    hasPropertyFinancial() {
      return this.propertyFinancialLpas.length > 0;
    },
    hasHealthWelfare() {
      return this.healthWelfareLpas.length > 0;
    },
  },

  mounted() {
    this.fetchLpas();
  },

  methods: {
    ...mapActions('estate', ['fetchLpas', 'removeLpa']),

    createLpa(type) {
      this.$router.push({ name: 'CreateLpa', params: { type } });
    },

    viewLpa(lpa) {
      this.viewingLpa = lpa;
    },

    handleEdit(lpa) {
      this.$router.push({ name: 'CreateLpa', params: { type: lpa.lpa_type }, query: { edit: lpa.id } });
    },

    handleDelete(lpa) {
      this.deletingLpa = lpa;
      this.showDeleteConfirm = true;
    },

    async confirmDelete() {
      if (!this.deletingLpa) return;
      this.deleting = true;
      try {
        await this.removeLpa(this.deletingLpa.id);
        this.showDeleteConfirm = false;
        this.deletingLpa = null;
      } catch {
        // Error handled by store
      } finally {
        this.deleting = false;
      }
    },
  },
};
</script>
