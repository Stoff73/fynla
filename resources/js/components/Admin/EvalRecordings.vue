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

      <!-- Remedial report -->
      <div class="card">
        <div class="flex items-start justify-between gap-4 mb-3">
          <div>
            <h4 class="font-bold text-horizon-500">Remedial report</h4>
            <p class="text-xs text-neutral-500 mt-0.5">
              Rubric-driven write-up of the gaps surfaced by this run.
              <span v-if="selectedSession.session.remedial_report_updated_at">
                · Last saved {{ formatRelative(selectedSession.session.remedial_report_updated_at) }}
              </span>
              <span v-else class="text-neutral-400 italic">· No report yet</span>
            </p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <template v-if="!reportEditing">
              <button
                v-if="!selectedSession.session.remedial_report"
                @click="autoFillReport"
                class="text-xs px-3 py-1.5 rounded border border-horizon-300 text-horizon-700 hover:bg-horizon-50 font-medium"
              >
                Pre-fill rubric template
              </button>
              <button
                @click="startEditReport"
                class="text-xs px-3 py-1.5 rounded bg-raspberry-500 text-white hover:bg-raspberry-600 font-medium"
              >
                {{ selectedSession.session.remedial_report ? 'Edit' : 'Write report' }}
              </button>
            </template>
            <template v-else>
              <button
                @click="cancelEditReport"
                :disabled="reportSaving"
                class="text-xs px-3 py-1.5 rounded border border-neutral-300 text-neutral-700 hover:bg-neutral-50 font-medium disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                @click="saveReport"
                :disabled="reportSaving"
                class="text-xs px-3 py-1.5 rounded bg-raspberry-500 text-white hover:bg-raspberry-600 font-medium disabled:opacity-50"
              >
                {{ reportSaving ? 'Saving…' : 'Save report' }}
              </button>
            </template>
          </div>
        </div>

        <div v-if="reportError" class="mb-3 p-2 rounded bg-raspberry-50 border border-raspberry-200 text-raspberry-700 text-xs">
          {{ reportError }}
        </div>

        <textarea
          v-if="reportEditing"
          v-model="reportDraft"
          class="w-full min-h-[28rem] font-mono text-xs p-3 border border-light-gray rounded bg-white focus:border-raspberry-500 focus:ring-2 focus:ring-raspberry-200 focus:outline-none"
          placeholder="Write the remedial report here. Markdown is supported (rendered as preformatted text)."
        ></textarea>

        <div v-else-if="selectedSession.session.remedial_report" class="bg-savannah-50 rounded p-4">
          <pre class="whitespace-pre-wrap text-sm text-horizon-500 font-mono leading-relaxed">{{ selectedSession.session.remedial_report }}</pre>
        </div>

        <div v-else class="text-center py-8 text-sm text-neutral-400 italic">
          No remedial report yet for this session. Click <strong class="text-horizon-500">Pre-fill rubric template</strong> to seed one from the run deltas, or <strong class="text-horizon-500">Write report</strong> to start blank.
        </div>
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
      reportEditing: false,
      reportDraft: '',
      reportSaving: false,
      reportError: null,
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
      this.reportEditing = false;
      this.reportDraft = '';
      this.reportError = null;
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
      this.reportEditing = false;
      this.reportDraft = '';
      this.reportError = null;
    },

    startEditReport() {
      this.reportDraft = this.selectedSession?.session?.remedial_report || '';
      this.reportEditing = true;
      this.reportError = null;
    },

    cancelEditReport() {
      this.reportEditing = false;
      this.reportDraft = '';
      this.reportError = null;
    },

    async saveReport() {
      if (!this.selectedSession) return;
      this.reportSaving = true;
      this.reportError = null;
      try {
        const payload = (this.reportDraft || '').trim();
        const data = await evalRecordingService.updateReport(
          this.selectedSession.session.id,
          payload === '' ? null : payload
        );
        this.selectedSession.session.remedial_report = data.remedial_report;
        this.selectedSession.session.remedial_report_updated_at = data.remedial_report_updated_at;
        this.reportEditing = false;
        this.reportDraft = '';
      } catch (e) {
        this.reportError = e?.response?.data?.message || e.message || 'Failed to save remedial report';
      } finally {
        this.reportSaving = false;
      }
    },

    autoFillReport() {
      this.reportDraft = this.buildRubricTemplate();
      this.reportEditing = true;
      this.reportError = null;
    },

    buildRubricTemplate() {
      const s = this.selectedSession?.session;
      const runs = this.selectedSession?.runs || [];
      if (!s) return '';

      const summariseRun = (provider) => {
        const r = runs.find((x) => x.provider === provider);
        if (!r) return `- **${provider}:** no run captured.`;
        const d = r.delta || {};
        const path = d.detected_result_path || d.result_path || '—';
        const failures = d.failures && typeof d.failures === 'object'
          ? Object.keys(d.failures).filter((k) => d.failures[k])
          : [];
        const status = failures.length === 0 ? 'GREEN' : `RED (${failures.length} failure${failures.length === 1 ? '' : 's'}: ${failures.join(', ')})`;
        return `- **${provider}/${r.model}:** ${r.duration_ms ?? '—'}ms, ${(r.tool_calls || []).length} tool calls, path \`${path}\`, **${status}**.`;
      };

      const findingFor = (provider, key, label, fallback = 'no gap') => {
        const r = runs.find((x) => x.provider === provider);
        const f = r?.delta?.failures?.[key];
        if (!f) return `${label} — ${fallback}`;
        return `${label} — ${typeof f === 'string' ? f : JSON.stringify(f)}`;
      };

      const lines = [];
      lines.push(`# Remedial report — ${s.scenario_id} — session #${s.id}`);
      lines.push('');
      lines.push(`*Recording session: #${s.id}. Date: ${s.started_at ? new Date(s.started_at).toISOString().slice(0, 10) : '—'}. Branch: \`${s.fynla_branch || '—'}\` @ \`${s.fynla_sha || '—'}\`.*`);
      lines.push('');
      lines.push('## Run summary');
      lines.push('');
      lines.push(summariseRun('anthropic'));
      lines.push(summariseRun('xai'));
      lines.push('');
      lines.push('## Rubric findings');
      lines.push('');
      lines.push('Per rubric (`April/April27Updates/eval-remediation-process.md` §2). One bullet per section; replace "no gap" with the specific gap if one exists.');
      lines.push('');
      lines.push(`- **2.1 Classification** — ${findingFor('anthropic', 'classification_shape', 'anthropic')}; ${findingFor('xai', 'classification_shape', 'xai')}`);
      lines.push(`- **2.2 Tool use** — ${findingFor('anthropic', 'expected_tool_calls', 'anthropic tools')}; ${findingFor('xai', 'expected_tool_calls', 'xai tools')}`);
      lines.push(`- **2.3 LLM response mode + signposting** — ${findingFor('anthropic', 'response_mode', 'anthropic mode')}; ${findingFor('xai', 'response_mode', 'xai mode')}; signposting (recommendation mode only): inspect assistant_text.`);
      lines.push(`- **2.4 Engine output** — ${findingFor('anthropic', 'engine_call_level', 'anthropic engine')}; ${findingFor('xai', 'engine_call_level', 'xai engine')}.`);
      lines.push(`- **2.5 Code path / readiness gate** — ${findingFor('anthropic', 'kyc_state', 'anthropic kyc')}; ${findingFor('xai', 'kyc_state', 'xai kyc')}.`);
      lines.push('- **2.6 Response quality** — _human assessment required: read both providers\' assistant_text. Is it structured the way we want? Does it answer the user? Are concrete numbers from the seed surfaced? Tone right?_');
      lines.push('- **2.7 Provider parity** — _compare anthropic vs xAI side-by-side. Note any divergence in tool count, timing, or response shape. Decide: real bug → fix prompt; cosmetic → widen YAML._');
      lines.push(`- **2.8 SSE shape** — ${findingFor('anthropic', 'sse_structural', 'anthropic sse')}; ${findingFor('xai', 'sse_structural', 'xai sse')}.`);
      lines.push('- **2.9 DB writes** — advice mode: must be zero (INV-2.1.2). Inspect `db_writes_made` per run.');
      lines.push('- **2.10 Recording infrastructure** — not assessed unless other gaps suspect.');
      lines.push('');
      lines.push('## Gaps in detail');
      lines.push('');
      lines.push('For each non-"no gap" finding above, one stanza:');
      lines.push('');
      lines.push('### Gap 1: <description>');
      lines.push('');
      lines.push('- **Rubric section:** 2.X');
      lines.push('- **Evidence:** <quoted line or value>');
      lines.push('- **Likely category:** <YAML defect | classifier | tool/contract | prompt/engine | code path | response quality | provider drift | SSE | DB write | recording infra>');
      lines.push('- **Likely fix surface:** `<file:line>`');
      lines.push('- **Browser verification needed?** <yes / no / not yet decided>');
      lines.push('- **Notes:** <nuance, trade-off, open question>');
      lines.push('');
      lines.push('## Recommendation');
      lines.push('');
      lines.push('<One paragraph for CSJ. Either "no action recommended — all gaps cosmetic", or "recommend acting on Gap N first because <reason>; estimated fix surface <file:line>; estimated effort <S/M/L>". CSJ decides.>');
      return lines.join('\n');
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
