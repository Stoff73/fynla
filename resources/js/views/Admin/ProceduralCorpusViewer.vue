<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Procedural memory corpus</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Read-only view of every procedure Fyn loads from the git-tracked corpus, grouped by kind and module.
          Procedures are edited via pull request to the corpus, never from this page.
        </p>
      </header>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="card-lg text-center text-raspberry-500 py-10">{{ error }}</div>

      <!-- Empty -->
      <div v-else-if="groups.length === 0" class="card-lg text-center text-neutral-500 py-10">
        No procedures are present in the corpus.
      </div>

      <!-- Grouped list -->
      <template v-else>
        <section v-for="group in groups" :key="group.kind" class="mb-8">
          <h2 class="text-lg font-bold text-horizon-500 mb-3">{{ formatKind(group.kind) }}</h2>

          <div v-for="moduleGroup in group.modules" :key="`${group.kind}-${moduleGroup.module}`" class="mb-4">
            <h3 class="text-sm font-semibold text-neutral-500 mb-2">{{ formatModule(moduleGroup.module) }}</h3>

            <div class="card-lg overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-neutral-500 border-b border-light-gray">
                    <th class="py-2 pr-3 font-medium">Procedure</th>
                    <th class="py-2 px-3 font-medium">Active version</th>
                    <th class="py-2 px-3 font-medium">Versions</th>
                    <th class="py-2 pl-3 font-medium text-right">Detail</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="proc in moduleGroup.procedures" :key="proc.procedure_id">
                    <tr
                      class="border-b border-light-gray cursor-pointer hover:bg-savannah-100 transition-colors"
                      @click="toggleProcedure(proc.procedure_id)"
                    >
                      <td class="py-3 pr-3 text-horizon-500 font-semibold break-all">{{ proc.procedure_id }}</td>
                      <td class="py-3 px-3 text-neutral-500">
                        {{ proc.active_version === null ? 'None active' : `v${proc.active_version}` }}
                      </td>
                      <td class="py-3 px-3 text-neutral-500">{{ proc.version_count }}</td>
                      <td class="py-3 pl-3 text-right text-neutral-500">
                        {{ expandedId === proc.procedure_id ? 'Hide' : 'View' }}
                      </td>
                    </tr>

                    <!-- Expanded detail row -->
                    <tr v-if="expandedId === proc.procedure_id" :key="`detail-${proc.procedure_id}`">
                      <td colspan="4" class="bg-eggshell-500 px-4 py-4">
                        <div v-if="detailLoading" class="flex justify-center py-8">
                          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
                        </div>

                        <div v-else-if="detailError" class="py-6 text-sm text-raspberry-500 font-semibold">
                          {{ detailError }}
                        </div>

                        <div v-else-if="detailVersions.length === 0" class="py-6 text-sm text-neutral-500">
                          No versions found for this procedure.
                        </div>

                        <div v-else class="space-y-4">
                          <div
                            v-for="version in detailVersions"
                            :key="version.version"
                            class="bg-white border border-light-gray rounded-lg overflow-hidden"
                          >
                            <!-- Frontmatter header table -->
                            <table class="w-full text-xs">
                              <tbody>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500 w-40">Version</th>
                                  <td class="py-2 px-4 text-horizon-500">v{{ version.version }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Status</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ version.active ? 'Active' : 'Inactive' }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Kind</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ formatKind(version.kind) }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Module</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ formatModule(version.module) }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Effective from</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ displayDate(version.effective_from) }}</td>
                                </tr>
                                <tr>
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Effective to</th>
                                  <td class="py-2 px-4 text-horizon-500">
                                    {{ version.effective_to ? displayDate(version.effective_to) : 'Open-ended' }}
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                            <!-- Body, verbatim, no markdown rendering -->
                            <pre
                              class="scrollbar-thin m-0 px-4 py-3 max-h-96 overflow-auto bg-eggshell-500 border-t border-light-gray text-xs text-horizon-500 whitespace-pre-wrap break-words font-mono"
                            >{{ version.body }}</pre>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import proceduralCorpusService from '@/services/proceduralCorpusService';
import { formatDate } from '@/utils/dateFormatter';

export default {
  name: 'ProceduralCorpusViewer',

  components: { AppLayout },

  data() {
    return {
      loading: true,
      error: null,
      groups: [],
      expandedId: null,
      detailVersions: [],
      detailLoading: false,
      detailError: null,
    };
  },

  mounted() {
    this.fetchCorpus();
  },

  methods: {
    async fetchCorpus() {
      this.loading = true;
      this.error = null;
      this.expandedId = null;
      try {
        const response = await proceduralCorpusService.getCorpus();
        const data = response.data || {};
        this.groups = Array.isArray(data.groups) ? data.groups : [];
      } catch (e) {
        this.error = 'Could not load the procedural corpus. Please try again.';
        this.groups = [];
      } finally {
        this.loading = false;
      }
    },

    async toggleProcedure(procedureId) {
      if (this.expandedId === procedureId) {
        this.expandedId = null;
        return;
      }
      this.expandedId = procedureId;
      this.detailVersions = [];
      this.detailError = null;
      this.detailLoading = true;

      try {
        const response = await proceduralCorpusService.getProcedure(procedureId);
        const data = response.data || {};
        this.detailVersions = Array.isArray(data.versions) ? data.versions : [];
      } catch (e) {
        this.detailError = 'Unable to load this procedure. Please try again.';
      } finally {
        this.detailLoading = false;
      }
    },

    formatKind(kind) {
      const labels = {
        system_prompt_overlay: 'System prompt overlay',
        workflow: 'Workflow',
        tool_schema: 'Tool schema',
        fca_block: 'Regulatory block',
      };
      return labels[kind] || (kind || '').replace(/_/g, ' ');
    },

    formatModule(module) {
      if (!module) return 'General';
      return module.charAt(0).toUpperCase() + module.slice(1).replace(/_/g, ' ');
    },

    displayDate(dateStr) {
      if (!dateStr) return '';
      return formatDate(dateStr);
    },
  },
};
</script>
