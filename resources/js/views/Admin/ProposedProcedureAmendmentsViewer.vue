<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Proposed procedure amendments</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Amendments the learning pipeline has proposed when a workflow procedure failed. Approving marks the proposal
          accepted only — it does not apply the change. An engineer must update the procedural corpus by hand.
        </p>
      </header>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="card-lg text-center text-raspberry-500 py-10">{{ error }}</div>

      <!-- Empty -->
      <div v-else-if="amendments.length === 0" class="card-lg text-center text-neutral-500 py-10">
        There are no pending proposed procedure amendments to review.
      </div>

      <!-- Pending amendments table -->
      <div v-else class="card-lg overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-neutral-500 border-b border-light-gray">
              <th class="py-2 pr-3 font-medium">Procedure</th>
              <th class="py-2 px-3 font-medium">Problem observed</th>
              <th class="py-2 px-3 font-medium">Proposed fix</th>
              <th class="py-2 px-3 font-medium">Failure type</th>
              <th class="py-2 pl-3 font-medium text-right">Review</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="amendment in amendments"
              :key="amendment.id"
              class="border-b border-light-gray align-top"
            >
              <td class="py-3 pr-3 text-horizon-500 font-semibold">{{ amendment.procedure_id }}</td>
              <td class="py-3 px-3 text-neutral-500 max-w-xs whitespace-pre-wrap break-words">{{ amendment.problem_observed }}</td>
              <td class="py-3 px-3 text-neutral-500 max-w-md whitespace-pre-wrap break-words">{{ amendment.proposed_fix }}</td>
              <td class="py-3 px-3 text-neutral-500">{{ amendment.failure_type ?? '—' }}</td>
              <td class="py-3 pl-3 text-right whitespace-nowrap">
                <button
                  type="button"
                  class="btn btn-primary btn-sm mr-2"
                  :disabled="processingId === amendment.id"
                  @click="review(amendment, 'approve')"
                >
                  Approve
                </button>
                <button
                  type="button"
                  class="btn btn-secondary btn-sm"
                  :disabled="processingId === amendment.id"
                  @click="review(amendment, 'reject')"
                >
                  Reject
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import proposedProcedureAmendmentsService from '@/services/proposedProcedureAmendmentsService';

export default {
  name: 'ProposedProcedureAmendmentsViewer',

  components: { AppLayout },

  data() {
    return {
      loading: true,
      error: null,
      amendments: [],
      processingId: null,
    };
  },

  mounted() {
    this.fetchAmendments();
  },

  methods: {
    async fetchAmendments() {
      this.loading = true;
      this.error = null;
      try {
        const response = await proposedProcedureAmendmentsService.getPending();
        const data = response.data || [];
        this.amendments = Array.isArray(data) ? data : [];
      } catch (e) {
        this.error = 'Could not load proposed amendments. Please try again.';
        this.amendments = [];
      } finally {
        this.loading = false;
      }
    },

    async review(amendment, action) {
      if (this.processingId !== null) return;
      this.processingId = amendment.id;
      try {
        await proposedProcedureAmendmentsService.review(amendment.id, action);
        // Remove the reviewed amendment from the pending list.
        this.amendments = this.amendments.filter((a) => a.id !== amendment.id);
      } catch (e) {
        this.error = 'Could not record your review. Please try again.';
      } finally {
        this.processingId = null;
      }
    },
  },
};
</script>
