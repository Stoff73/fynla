<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Episodic compliance log</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Read-only forensic record of every Fyn advice episode across all users.
        </p>
      </header>

      <!-- Filter bar -->
      <form class="card-lg mb-6" @submit.prevent="applyFilters">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div>
            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-user">User ID</label>
            <input
              id="filter-user"
              v-model="filters.userId"
              type="number"
              min="1"
              placeholder="Any user"
              class="w-full rounded-lg border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:border-violet-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-from">From</label>
            <input
              id="filter-from"
              v-model="filters.from"
              type="date"
              class="w-full rounded-lg border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:border-violet-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-to">To</label>
            <input
              id="filter-to"
              v-model="filters.to"
              type="date"
              class="w-full rounded-lg border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:border-violet-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-module">Module</label>
            <input
              id="filter-module"
              v-model="filters.module"
              type="text"
              placeholder="Any module"
              class="w-full rounded-lg border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:border-violet-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-persona">Persona</label>
            <select
              id="filter-persona"
              v-model="filters.persona"
              class="w-full rounded-lg border border-light-gray px-3 py-2 text-sm text-horizon-500 focus:border-violet-500 focus:outline-none"
            >
              <option value="">Any persona</option>
              <option value="advice">Advice</option>
              <option value="data_capture">Data capture</option>
            </select>
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button
            type="submit"
            class="px-4 py-2 text-sm font-semibold rounded-full bg-raspberry-500 text-white hover:bg-raspberry-600 transition-colors"
          >
            Apply filters
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-semibold rounded-full bg-light-gray text-horizon-500 hover:bg-savannah-100 transition-colors"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </form>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="card-lg text-center text-raspberry-500 py-10">{{ error }}</div>

      <!-- Empty -->
      <div v-else-if="episodes.length === 0" class="card-lg text-center text-neutral-500 py-10">
        No episodes match the current filters.
      </div>

      <!-- Table -->
      <template v-else>
        <div class="card-lg overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-neutral-500 border-b border-light-gray">
                <th class="py-2 pr-3 font-medium">Date</th>
                <th class="py-2 px-3 font-medium">User</th>
                <th class="py-2 px-3 font-medium">Persona</th>
                <th class="py-2 px-3 font-medium">Model</th>
                <th class="py-2 pl-3 font-medium text-right">Tools</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="episode in episodes" :key="episode.id">
                <tr
                  class="border-b border-light-gray cursor-pointer hover:bg-savannah-100 transition-colors"
                  @click="toggleEpisode(episode.id)"
                >
                  <td class="py-3 pr-3 text-horizon-500 font-semibold">{{ formatDateShort(episode.created_at) }}</td>
                  <td class="py-3 px-3 text-neutral-500">{{ episode.user_id || 'Unknown' }}</td>
                  <td class="py-3 px-3 text-neutral-500">{{ formatPersona(episode.persona) }}</td>
                  <td class="py-3 px-3 text-neutral-500 truncate">{{ episode.model_used || 'Not recorded' }}</td>
                  <td class="py-3 pl-3 text-right text-neutral-500">
                    {{ episode.tool_count }} {{ episode.tool_count === 1 ? 'tool' : 'tools' }}
                  </td>
                </tr>

                <!-- Expanded detail row -->
                <tr v-if="expandedId === episode.id" :key="`detail-${episode.id}`">
                  <td colspan="5" class="bg-eggshell-500 px-4 py-4">
                    <div v-if="detailLoading" class="flex justify-center py-8">
                      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
                    </div>

                    <div v-else-if="detailError" class="py-6 text-sm text-raspberry-500 font-semibold">
                      {{ detailError }}
                    </div>

                    <div v-else-if="detail" class="space-y-4">
                      <!-- Chain-verify text badge -->
                      <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-horizon-500">Chain verification</span>
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                          :class="verifyBadgeClass"
                        >
                          {{ verifyBadgeLabel }}
                        </span>
                      </div>

                      <!-- Blob sections -->
                      <div v-if="sections.length === 0" class="text-sm text-neutral-500">
                        No forensic blob recorded for this episode.
                      </div>
                      <div v-else class="space-y-3">
                        <div
                          v-for="section in sections"
                          :key="section.heading"
                          class="bg-white border border-light-gray rounded-lg overflow-hidden"
                        >
                          <button
                            type="button"
                            class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-savannah-100 transition-colors"
                            @click="toggleSection(section.heading)"
                          >
                            <span class="text-sm font-semibold text-horizon-500">{{ formatHeading(section.heading) }}</span>
                            <span class="text-xs font-semibold text-neutral-500">
                              {{ openSections[section.heading] ? 'Hide' : 'Show' }}
                            </span>
                          </button>
                          <pre
                            v-if="openSections[section.heading]"
                            class="scrollbar-thin m-0 px-4 py-3 max-h-96 overflow-auto bg-eggshell-500 border-t border-light-gray text-xs text-horizon-500 whitespace-pre-wrap break-words font-mono"
                          >{{ section.body }}</pre>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between mt-6">
          <p class="text-sm text-neutral-500">
            Showing page {{ currentPage }} of {{ lastPage }} ({{ total }} episodes)
          </p>
          <div class="flex gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-full transition-colors"
              :class="currentPage <= 1 ? 'bg-light-gray text-neutral-500 cursor-not-allowed' : 'bg-horizon-500 text-white hover:bg-horizon-600'"
              :disabled="currentPage <= 1"
              @click="goToPage(currentPage - 1)"
            >
              Previous
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-full transition-colors"
              :class="currentPage >= lastPage ? 'bg-light-gray text-neutral-500 cursor-not-allowed' : 'bg-horizon-500 text-white hover:bg-horizon-600'"
              :disabled="currentPage >= lastPage"
              @click="goToPage(currentPage + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import aiAuditService from '@/services/aiAuditService';
import { formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'EpisodicComplianceLog',

  components: { AppLayout },

  data() {
    return {
      loading: true,
      error: null,
      episodes: [],
      currentPage: 1,
      lastPage: 1,
      total: 0,
      filters: {
        userId: '',
        from: '',
        to: '',
        module: '',
        persona: '',
      },
      expandedId: null,
      detail: null,
      detailLoading: false,
      detailError: null,
      verification: null,
      openSections: {},
    };
  },

  computed: {
    sections() {
      const body = this.detail && this.detail.blob_body;
      if (!body) return [];
      // Strip leading YAML frontmatter, then split only on the fixed top-level
      // headings so any "##" inside a section body is preserved.
      const withoutFrontmatter = body.replace(/^---[\s\S]*?\n---\n/, '');
      const headingRe = /^## (system_prompt|assembled_context|reasoning_trace|tool_calls|tool_results)\s*$/gm;
      const matches = [...withoutFrontmatter.matchAll(headingRe)];
      return matches.map((match, index) => {
        const heading = match[1];
        const start = match.index + match[0].length;
        const end = index + 1 < matches.length ? matches[index + 1].index : withoutFrontmatter.length;
        return { heading, body: withoutFrontmatter.slice(start, end).trim() };
      });
    },

    verifyBadgeLabel() {
      if (!this.verification) return 'Checking';
      const labels = {
        verified: 'Verified',
        modified: 'Tamper detected',
        missing: 'Blob missing',
        no_attestation: 'No attestation',
      };
      return labels[this.verification.state] || 'Unknown';
    },

    verifyBadgeClass() {
      const state = this.verification && this.verification.state;
      if (state === 'verified') return 'bg-spring-100 text-spring-700';
      if (state === 'modified') return 'bg-raspberry-50 text-raspberry-700';
      return 'bg-horizon-100 text-horizon-700';
    },
  },

  mounted() {
    this.fetchEpisodes();
  },

  methods: {
    async fetchEpisodes() {
      this.loading = true;
      this.error = null;
      this.expandedId = null;
      try {
        const response = await aiAuditService.getEpisodes({
          userId: this.filters.userId,
          from: this.filters.from,
          to: this.filters.to,
          module: this.filters.module,
          persona: this.filters.persona,
          page: this.currentPage,
        });
        const paginator = response.data || {};
        this.episodes = Array.isArray(paginator.data) ? paginator.data : [];
        this.currentPage = paginator.current_page || 1;
        this.lastPage = paginator.last_page || 1;
        this.total = paginator.total || 0;
      } catch (e) {
        this.error = 'Could not load the episodic compliance log. Please try again.';
        this.episodes = [];
      } finally {
        this.loading = false;
      }
    },

    applyFilters() {
      this.currentPage = 1;
      this.fetchEpisodes();
    },

    resetFilters() {
      this.filters = { userId: '', from: '', to: '', module: '', persona: '' };
      this.currentPage = 1;
      this.fetchEpisodes();
    },

    goToPage(page) {
      if (page < 1 || page > this.lastPage) return;
      this.currentPage = page;
      this.fetchEpisodes();
    },

    async toggleEpisode(id) {
      if (this.expandedId === id) {
        this.expandedId = null;
        return;
      }
      this.expandedId = id;
      this.detail = null;
      this.verification = null;
      this.detailError = null;
      this.openSections = {};
      this.detailLoading = true;

      try {
        const [detailRes, verifyRes] = await Promise.all([
          aiAuditService.getEpisode(id),
          aiAuditService.verifyEpisode(id),
        ]);
        this.detail = detailRes.data || null;
        this.verification = verifyRes.data || null;
      } catch (e) {
        this.detailError = 'Unable to load episode detail.';
      } finally {
        this.detailLoading = false;
      }
    },

    toggleSection(heading) {
      this.openSections = {
        ...this.openSections,
        [heading]: !this.openSections[heading],
      };
    },

    formatHeading(heading) {
      const labels = {
        system_prompt: 'System prompt',
        assembled_context: 'Assembled context',
        reasoning_trace: 'Reasoning trace',
        tool_calls: 'Tool calls',
        tool_results: 'Tool results',
      };
      return labels[heading] || heading.replace(/_/g, ' ');
    },

    formatPersona(persona) {
      if (!persona) return 'Not recorded';
      return persona.charAt(0).toUpperCase() + persona.slice(1).replace(/_/g, ' ');
    },

    formatDateShort(dateStr) {
      if (!dateStr) return '';
      return formatDateLong(dateStr, true);
    },
  },
};
</script>
