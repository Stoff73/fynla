<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Proposed semantic facts</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Per-user facts the learning pipeline has proposed from conversations. Approving writes the fact to
          that user's private semantic memory; rejecting discards it. Nothing is applied automatically.
        </p>
      </header>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="card-lg text-center text-raspberry-500 py-10">{{ error }}</div>

      <!-- Empty -->
      <div v-else-if="facts.length === 0" class="card-lg text-center text-neutral-500 py-10">
        There are no pending proposed facts to review.
      </div>

      <!-- Pending facts table -->
      <div v-else class="card-lg overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-neutral-500 border-b border-light-gray">
              <th class="py-2 pr-3 font-medium">User</th>
              <th class="py-2 px-3 font-medium">Title</th>
              <th class="py-2 px-3 font-medium">Fact</th>
              <th class="py-2 px-3 font-medium">Source conversation</th>
              <th class="py-2 pl-3 font-medium text-right">Review</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="fact in facts"
              :key="fact.id"
              class="border-b border-light-gray align-top"
            >
              <td class="py-3 pr-3 text-horizon-500 font-semibold">{{ fact.user_id }}</td>
              <td class="py-3 px-3 text-horizon-500 font-semibold">{{ fact.title }}</td>
              <td class="py-3 px-3 text-neutral-500 max-w-md whitespace-pre-wrap break-words">{{ fact.body }}</td>
              <td class="py-3 px-3 text-neutral-500">
                {{ fact.derived_from_conversation_id === null ? '—' : fact.derived_from_conversation_id }}
              </td>
              <td class="py-3 pl-3 text-right whitespace-nowrap">
                <button
                  type="button"
                  class="btn btn-primary btn-sm mr-2"
                  :disabled="processingId === fact.id"
                  @click="review(fact, 'approve')"
                >
                  Approve
                </button>
                <button
                  type="button"
                  class="btn btn-secondary btn-sm"
                  :disabled="processingId === fact.id"
                  @click="review(fact, 'reject')"
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
import proposedSemanticFactsService from '@/services/proposedSemanticFactsService';

export default {
  name: 'ProposedSemanticFactsViewer',

  components: { AppLayout },

  data() {
    return {
      loading: true,
      error: null,
      facts: [],
      processingId: null,
    };
  },

  mounted() {
    this.fetchFacts();
  },

  methods: {
    async fetchFacts() {
      this.loading = true;
      this.error = null;
      try {
        const response = await proposedSemanticFactsService.getPending();
        const data = response.data || [];
        this.facts = Array.isArray(data) ? data : [];
      } catch (e) {
        this.error = 'Could not load proposed facts. Please try again.';
        this.facts = [];
      } finally {
        this.loading = false;
      }
    },

    async review(fact, action) {
      if (this.processingId !== null) return;
      this.processingId = fact.id;
      try {
        await proposedSemanticFactsService.review(fact.id, action);
        // Remove the reviewed fact from the pending list.
        this.facts = this.facts.filter((f) => f.id !== fact.id);
      } catch (e) {
        this.error = 'Could not record your review. Please try again.';
      } finally {
        this.processingId = null;
      }
    },
  },
};
</script>
