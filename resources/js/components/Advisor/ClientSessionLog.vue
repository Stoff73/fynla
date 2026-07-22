<template>
  <div class="bg-white border border-light-gray rounded-xl shadow-card">
    <div class="flex items-center justify-between p-6 border-b border-light-gray">
      <h2 class="text-lg font-bold text-horizon-500">Session Log</h2>
      <span class="text-xs text-neutral-500">{{ episodes.length }} sessions</span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-8 text-center">
      <div class="text-sm text-raspberry-500 font-semibold">{{ error }}</div>
    </div>

    <!-- Empty State -->
    <div v-else-if="episodes.length === 0" class="p-8 text-center text-neutral-500">
      No advice sessions recorded yet.
    </div>

    <!-- Episode List -->
    <div v-else>
      <!-- Column headings -->
      <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 border-b border-light-gray text-xs font-semibold uppercase tracking-wide text-neutral-500">
        <div class="col-span-4">Date</div>
        <div class="col-span-3">Persona</div>
        <div class="col-span-3">Model</div>
        <div class="col-span-2 text-right">Tools</div>
      </div>

      <div class="divide-y divide-light-gray">
        <div v-for="episode in episodes" :key="episode.id">
          <!-- Row -->
          <button
            type="button"
            class="w-full grid grid-cols-12 gap-4 px-6 py-4 text-left hover:bg-savannah-100 transition-colors"
            @click="toggleEpisode(episode.id)"
          >
            <div class="col-span-12 md:col-span-4 text-sm font-semibold text-horizon-500">
              {{ formatDateShort(episode.created_at) }}
            </div>
            <div class="col-span-6 md:col-span-3 text-sm text-neutral-500">
              {{ formatPersona(episode.persona) }}
            </div>
            <div class="col-span-6 md:col-span-3 text-sm text-neutral-500 truncate">
              {{ episode.model_used || 'Not recorded' }}
            </div>
            <div class="col-span-12 md:col-span-2 text-sm text-neutral-500 md:text-right">
              {{ episode.tool_count }} {{ episode.tool_count === 1 ? 'tool' : 'tools' }}
            </div>
          </button>

          <!-- Expanded detail -->
          <div v-if="expandedId === episode.id" class="px-6 pb-6 bg-eggshell-500">
            <!-- Detail loading -->
            <div v-if="detailLoading" class="flex justify-center py-8">
              <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
            </div>

            <!-- Detail error -->
            <div v-else-if="detailError" class="py-6 text-sm text-raspberry-500 font-semibold">
              {{ detailError }}
            </div>

            <div v-else-if="detail" class="pt-4 space-y-4">
              <!-- Chain-verify badge -->
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
                No forensic blob recorded for this session.
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
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import advisorService from '@/services/advisorService';
import { formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'ClientSessionLog',

  props: {
    clientId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      episodes: [],
      loading: false,
      error: null,
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
      // Strip leading YAML frontmatter (--- ... ---). The blob uses a fixed set of
      // top-level "## <section>" headings; split only on those so any "##" that
      // appears inside a section body (e.g. markdown in the assembled context) is
      // preserved rather than treated as a new section.
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
    this.loadEpisodes();
  },

  methods: {
    async loadEpisodes() {
      this.loading = true;
      this.error = null;
      try {
        const response = await advisorService.getClientEpisodes(this.clientId);
        const payload = response.data || {};
        this.episodes = Array.isArray(payload.data) ? payload.data : [];
      } catch (e) {
        this.error = 'Unable to load advice sessions.';
        this.episodes = [];
      } finally {
        this.loading = false;
      }
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
          advisorService.getClientEpisode(this.clientId, id),
          advisorService.verifyClientEpisode(this.clientId, id),
        ]);
        this.detail = detailRes.data || null;
        this.verification = verifyRes.data || null;
      } catch (e) {
        this.detailError = 'Unable to load session detail.';
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
