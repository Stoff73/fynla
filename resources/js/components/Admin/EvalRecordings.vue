<template>
  <div class="space-y-6">
    <div>
      <h2 class="font-display text-h2 text-horizon-500">Eval Recordings</h2>
      <p class="text-body text-neutral-500 mt-1">
        Forensic record of every <code class="px-1 bg-savannah-100 rounded">php artisan eval:record</code> run.
        Per-scenario, both providers side-by-side with delta analysis.
      </p>
    </div>

    <div v-if="loading && !sessions.length" class="flex justify-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <div v-else-if="error" class="card-sm bg-raspberry-50 border-raspberry-200 text-raspberry-700">
      {{ error }}
    </div>

    <div v-else-if="!sessions.length" class="card text-center py-12">
      <p class="text-body text-neutral-500">No eval recordings yet. Run <code>php artisan eval:record &lt;scenario&gt;</code>.</p>
    </div>

    <!-- Sessions index -->
    <div v-else-if="!selectedSession" class="card overflow-hidden p-0">
      <table class="min-w-full divide-y divide-light-gray">
        <thead class="bg-savannah-100">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">Scenario</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">Eval User</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">Anthropic</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">xAI</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">Branch / SHA</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-horizon-500 uppercase tracking-wider">Recorded</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-light-gray">
          <tr v-for="s in sessions" :key="s.id" class="hover:bg-savannah-50 transition-colors">
            <td class="px-4 py-3">
              <div class="font-mono text-sm text-horizon-500">{{ s.scenario_id }}</div>
              <div class="text-xs text-neutral-400">#{{ s.id }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-neutral-500">
              <span v-if="s.eval_user">{{ s.eval_user.email }}</span>
              <span v-else class="text-neutral-300 italic">—</span>
            </td>
            <td class="px-4 py-3 text-sm">
              <ProviderCell :run="findRun(s, 'anthropic')" />
            </td>
            <td class="px-4 py-3 text-sm">
              <ProviderCell :run="findRun(s, 'xai')" />
            </td>
            <td class="px-4 py-3">
              <span :class="statusBadgeClass(s.status)">{{ s.status }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-neutral-500 font-mono">
              <div>{{ s.fynla_branch || '—' }}</div>
              <div class="text-neutral-400">{{ s.fynla_sha || '' }}</div>
            </td>
            <td class="px-4 py-3 text-xs text-neutral-500">
              {{ formatRelative(s.started_at) }}
            </td>
            <td class="px-4 py-3 text-right">
              <button
                @click="openSession(s.id)"
                class="text-raspberry-500 hover:text-raspberry-600 text-sm font-medium"
              >
                View →
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detail view -->
    <div v-else class="space-y-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <button
            @click="closeDetail()"
            class="text-sm text-raspberry-500 hover:text-raspberry-600 font-medium mb-2"
          >
            ← Back to all recordings
          </button>
          <h3 class="font-display text-h3 text-horizon-500">{{ selectedSession.session.scenario_id }}</h3>
          <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-neutral-500 font-mono">
            <span>Session #{{ selectedSession.session.id }}</span>
            <span>{{ selectedSession.session.fynla_branch }} @ {{ selectedSession.session.fynla_sha }}</span>
            <span>Recorded {{ formatAbsolute(selectedSession.session.started_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Question + expected response (from YAML) -->
      <div class="card">
        <h4 class="font-bold text-horizon-500 mb-3">Question asked &amp; expected response</h4>
        <dl class="space-y-3 text-sm">
          <div>
            <dt class="text-xs uppercase tracking-wider text-neutral-400 font-bold">User asked</dt>
            <dd class="text-horizon-500 mt-0.5">{{ selectedSession.expected?.user_message || '—' }}</dd>
          </div>
          <div v-if="selectedSession.expected?.description">
            <dt class="text-xs uppercase tracking-wider text-neutral-400 font-bold">Scenario description</dt>
            <dd class="text-horizon-500 mt-0.5 whitespace-pre-wrap">{{ selectedSession.expected.description }}</dd>
          </div>
          <div v-if="expectedToolList.length">
            <dt class="text-xs uppercase tracking-wider text-neutral-400 font-bold">Expected tool calls</dt>
            <dd class="mt-0.5">
              <ol class="list-decimal list-inside text-horizon-500">
                <li v-for="(t, i) in selectedSession.expected.tool_calls" :key="i" class="font-mono text-sm">
                  {{ t.tool }}<span v-if="t.args">(<span class="text-neutral-500">{{ formatArgs(t.args) }}</span>)</span>
                </li>
              </ol>
            </dd>
          </div>
          <div v-if="selectedSession.expected?.forbidden_tools?.length">
            <dt class="text-xs uppercase tracking-wider text-neutral-400 font-bold">Forbidden tools</dt>
            <dd class="mt-0.5 font-mono text-sm text-raspberry-700">{{ selectedSession.expected.forbidden_tools.join(', ') }}</dd>
          </div>
          <div v-if="selectedSession.expected?.forbidden_outputs?.length">
            <dt class="text-xs uppercase tracking-wider text-neutral-400 font-bold">Forbidden phrases</dt>
            <dd class="mt-0.5 text-sm text-raspberry-700">
              <span v-for="p in selectedSession.expected.forbidden_outputs" :key="p" class="font-mono mr-3">"{{ p }}"</span>
            </dd>
          </div>
          <div v-if="selectedSession.expected?.timing_budget_ms">
            <dt class="text-xs uppercase tracking-wider text-neutral-400 font-bold">Timing budget</dt>
            <dd class="mt-0.5 text-sm text-horizon-500 font-mono">{{ selectedSession.expected.timing_budget_ms }}ms</dd>
          </div>
        </dl>
      </div>

      <!-- Side-by-side run cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <RunPanel
          v-for="run in selectedSession.runs"
          :key="run.id"
          :run="run"
          @open-system-prompt="openSystemPrompt"
          @open-raw-fixture="openRawFixture"
        />
      </div>

      <!-- Start state collapsible -->
      <div class="card">
        <button
          @click="showStartState = !showStartState"
          class="font-bold text-horizon-500 hover:text-raspberry-500 transition-colors"
        >
          {{ showStartState ? '▾' : '▸' }} Start state — what Fyn was working from
        </button>
        <pre v-if="showStartState" class="text-xs font-mono bg-savannah-50 rounded p-3 overflow-auto max-h-96 mt-3">{{ formatJson(selectedSession.session.start_state_snapshot) }}</pre>
      </div>

      <!-- Scenario YAML collapsible -->
      <div class="card">
        <button
          @click="showYaml = !showYaml"
          class="font-bold text-horizon-500 hover:text-raspberry-500 transition-colors"
        >
          {{ showYaml ? '▾' : '▸' }} Full scenario YAML
        </button>
        <pre v-if="showYaml" class="text-xs font-mono bg-savannah-50 rounded p-3 overflow-auto max-h-96 mt-3">{{ selectedSession.session.scenario_yaml }}</pre>
      </div>
    </div>

    <EvalDataModal
      :open="modal.open"
      :title="modal.title"
      :subtitle="modal.subtitle"
      :content="modal.content"
      :loading="modal.loading"
      :error="modal.error"
      :footer="modal.footer"
      @close="modal.open = false"
    />
  </div>
</template>

<script>
import evalRecordingService from '@/services/evalRecordingService';
import ProviderCell from './eval/ProviderCell.vue';
import RunPanel from './eval/RunPanel.vue';
import EvalDataModal from './eval/EvalDataModal.vue';

export default {
  name: 'EvalRecordings',

  components: { ProviderCell, RunPanel, EvalDataModal },

  data() {
    return {
      sessions: [],
      selectedSession: null,
      loading: false,
      error: null,
      showYaml: false,
      showStartState: false,
      modal: {
        open: false,
        title: '',
        subtitle: '',
        content: null,
        loading: false,
        error: null,
        footer: '',
      },
    };
  },

  computed: {
    expectedToolList() {
      return this.selectedSession?.expected?.tool_calls || [];
    },
  },

  mounted() {
    this.fetchSessions();
  },

  methods: {
    async fetchSessions() {
      this.loading = true;
      this.error = null;
      try {
        const data = await evalRecordingService.listSessions(200);
        this.sessions = data.sessions || [];
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load eval recordings';
      } finally {
        this.loading = false;
      }
    },

    async openSession(sessionId) {
      this.loading = true;
      this.error = null;
      this.showYaml = false;
      this.showStartState = false;
      try {
        this.selectedSession = await evalRecordingService.getSession(sessionId);
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load session';
      } finally {
        this.loading = false;
      }
    },

    closeDetail() {
      this.selectedSession = null;
    },

    findRun(session, provider) {
      return (session.runs || []).find((r) => r.provider === provider);
    },

    async openSystemPrompt(run) {
      this.modal = {
        open: true,
        title: 'System prompt',
        subtitle: `${run.provider} / ${run.model}`,
        content: null,
        loading: true,
        error: null,
        footer: '',
      };
      try {
        const data = await evalRecordingService.getSystemPrompt(run.id);
        this.modal.content = data.system_prompt || '(no system prompt persisted)';
        this.modal.footer = `${data.system_prompt_length || 0} chars · input tokens ${data.input_tokens ?? '?'} · output tokens ${data.output_tokens ?? '?'}`;
      } catch (e) {
        this.modal.error = e?.response?.data?.message || e.message || 'Failed to load system prompt';
      } finally {
        this.modal.loading = false;
      }
    },

    async openRawFixture(run) {
      this.modal = {
        open: true,
        title: 'Raw fixture file',
        subtitle: `${run.provider} / ${run.model} — ${run.fixture_path}`,
        content: null,
        loading: true,
        error: null,
        footer: '',
      };
      try {
        const data = await evalRecordingService.getRawFixture(run.id);
        if (!data.exists) {
          this.modal.error = `Fixture file not found at ${data.fixture_path}`;
        } else {
          this.modal.content = data.content;
          this.modal.footer = `${data.bytes ?? 0} bytes`;
        }
      } catch (e) {
        this.modal.error = e?.response?.data?.message || e.message || 'Failed to load raw fixture';
      } finally {
        this.modal.loading = false;
      }
    },

    statusBadgeClass(status) {
      const base = 'inline-flex px-2 py-0.5 rounded-full text-xs font-medium';
      if (status === 'completed') return `${base} bg-spring-100 text-spring-700`;
      if (status === 'failed') return `${base} bg-raspberry-100 text-raspberry-700`;
      return `${base} bg-violet-100 text-violet-700`;
    },

    formatRelative(iso) {
      if (!iso) return '—';
      const then = new Date(iso);
      const diff = Math.floor((Date.now() - then.getTime()) / 1000);
      if (diff < 60) return `${diff}s ago`;
      if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
      return then.toLocaleDateString('en-GB');
    },

    formatAbsolute(iso) {
      if (!iso) return '—';
      return new Date(iso).toLocaleString('en-GB');
    },

    formatJson(obj) {
      try {
        return JSON.stringify(obj, null, 2);
      } catch (_e) {
        return String(obj);
      }
    },

    formatArgs(args) {
      if (!args || typeof args !== 'object') return '';
      return Object.entries(args)
        .map(([k, v]) => `${k}: ${typeof v === 'string' ? v : JSON.stringify(v)}`)
        .join(', ');
    },
  },
};
</script>
