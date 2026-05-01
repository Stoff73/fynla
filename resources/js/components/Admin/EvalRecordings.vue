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
              <span v-if="s.preview_user">{{ s.preview_user.email }}</span>
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
                View
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
            Back to all recordings
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

      <!-- HTTP log — every request the eval driver made against the local Laravel server.
           Per audit §5.7. Surfaced from session.http_log (EvalRecordingController:118). -->
      <div v-if="httpLogEntries.length" class="card">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h4 class="font-bold text-horizon-500">HTTP log</h4>
            <p class="text-xs text-neutral-500 mt-0.5">
              Every call the driver made against the local Laravel server during this recording.
              Entries from both providers' runs are interleaved in chronological order.
            </p>
          </div>
          <span class="text-xs text-neutral-500 font-mono">{{ httpLogEntries.length }} call{{ httpLogEntries.length === 1 ? '' : 's' }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-light-gray">
                <th class="text-left py-2 px-3 text-neutral-500 font-bold uppercase tracking-wider">Method</th>
                <th class="text-left py-2 px-3 text-neutral-500 font-bold uppercase tracking-wider">URL</th>
                <th class="text-right py-2 px-3 text-neutral-500 font-bold uppercase tracking-wider">Status</th>
                <th class="text-right py-2 px-3 text-neutral-500 font-bold uppercase tracking-wider">Duration</th>
                <th class="text-right py-2 px-3 text-neutral-500 font-bold uppercase tracking-wider">At</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(call, i) in httpLogEntries" :key="i" class="border-b border-light-gray hover:bg-savannah-50">
                <td class="py-2 px-3 font-mono font-bold text-horizon-500">{{ call.method }}</td>
                <td class="py-2 px-3 font-mono text-horizon-500 break-all">{{ formatHttpUrl(call.url) }}</td>
                <td class="py-2 px-3 text-right font-mono" :class="httpStatusClass(call.status)">{{ call.status }}</td>
                <td class="py-2 px-3 text-right font-mono text-neutral-500">{{ call.duration_ms }}ms</td>
                <td class="py-2 px-3 text-right font-mono text-neutral-400">{{ formatHttpTime(call.at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Remedial report (per-session, human-authored against the rubric) -->
      <div class="card">
        <div class="flex items-start justify-between mb-3 gap-3">
          <div>
            <h4 class="font-bold text-horizon-500">Remedial report</h4>
            <p class="text-xs text-neutral-500 mt-0.5">
              Human-authored against the rubric in
              <code class="px-1 bg-savannah-100 rounded">April/April27Updates/eval-remediation-process.md</code>.
              One report per session. The report is the deliverable; CSJ decides what to act on.
            </p>
          </div>
          <button
            v-if="!editingReport"
            @click="startEditReport()"
            class="text-sm text-raspberry-500 hover:text-raspberry-600 font-medium whitespace-nowrap"
          >
            {{ remedialReport ? 'Edit report' : 'Draft report' }}
          </button>
        </div>

        <div v-if="!editingReport && !remedialReport" class="text-sm text-neutral-500 italic">
          No report authored yet. Click <strong>Draft report</strong> to start with a rubric-prefilled template.
        </div>

        <div
          v-if="!editingReport && remedialReport"
          class="text-sm text-horizon-500 whitespace-pre-wrap font-mono leading-relaxed bg-savannah-50 rounded p-3"
        >{{ remedialReport }}</div>

        <div v-if="editingReport">
          <textarea
            v-model="reportDraft"
            rows="22"
            class="w-full border border-light-gray rounded p-3 text-sm font-mono leading-relaxed focus:border-raspberry-500 focus:ring-0"
            placeholder="Markdown content…"
          />
          <div v-if="saveReportError" class="text-xs text-raspberry-700 mt-2">{{ saveReportError }}</div>
          <div class="flex gap-2 mt-3">
            <button
              @click="saveReport()"
              :disabled="savingReport"
              class="px-4 py-2 bg-raspberry-500 text-white rounded text-sm font-medium hover:bg-raspberry-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ savingReport ? 'Saving…' : 'Save report' }}
            </button>
            <button
              @click="cancelEditReport()"
              :disabled="savingReport"
              class="px-4 py-2 border border-light-gray text-horizon-500 rounded text-sm font-medium hover:bg-savannah-50 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              v-if="remedialReport"
              @click="clearReport()"
              :disabled="savingReport"
              class="px-4 py-2 border border-raspberry-200 text-raspberry-600 rounded text-sm font-medium hover:bg-raspberry-50 disabled:opacity-50 ml-auto"
            >
              Clear report
            </button>
          </div>
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
      editingReport: false,
      reportDraft: '',
      savingReport: false,
      saveReportError: null,
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
    remedialReport() {
      return this.selectedSession?.session?.remedial_report || '';
    },
    httpLogEntries() {
      const log = this.selectedSession?.session?.http_log;
      return Array.isArray(log) ? log : [];
    },
  },

  mounted() {
    this.fetchSessions();
  },

  methods: {
    formatHttpUrl(url) {
      if (typeof url !== 'string') return '';
      // Strip the local server origin so the table shows just the path —
      // every entry in a single recording session has the same host.
      return url.replace(/^https?:\/\/[^/]+/, '');
    },
    formatHttpTime(at) {
      if (typeof at !== 'string' || at === '') return '';
      const t = at.length >= 19 ? at.substring(11, 19) : at;
      return t;
    },
    httpStatusClass(status) {
      const n = Number(status);
      if (!Number.isFinite(n)) return 'text-neutral-500';
      if (n >= 200 && n < 300) return 'text-spring-700 font-bold';
      if (n >= 300 && n < 400) return 'text-horizon-500';
      if (n >= 400 && n < 500) return 'text-violet-700 font-bold';
      if (n >= 500) return 'text-raspberry-700 font-bold';
      return 'text-neutral-500';
    },
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
      this.editingReport = false;
      this.reportDraft = '';
      this.saveReportError = null;
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
      this.editingReport = false;
      this.reportDraft = '';
      this.saveReportError = null;
    },

    startEditReport() {
      this.reportDraft = this.remedialReport || this.buildReportPrefill();
      this.saveReportError = null;
      this.editingReport = true;
    },

    cancelEditReport() {
      this.editingReport = false;
      this.reportDraft = '';
      this.saveReportError = null;
    },

    async saveReport() {
      this.savingReport = true;
      this.saveReportError = null;
      try {
        const trimmed = this.reportDraft && this.reportDraft.trim() ? this.reportDraft : null;
        const data = await evalRecordingService.updateReport(
          this.selectedSession.session.id,
          trimmed,
        );
        this.selectedSession.session.remedial_report = data.remedial_report;
        this.editingReport = false;
        this.reportDraft = '';
      } catch (e) {
        this.saveReportError = e?.response?.data?.message || e.message || 'Failed to save report';
      } finally {
        this.savingReport = false;
      }
    },

    async clearReport() {
      if (!confirm('Clear the remedial report for this session? This cannot be undone.')) return;
      this.savingReport = true;
      this.saveReportError = null;
      try {
        const data = await evalRecordingService.updateReport(this.selectedSession.session.id, null);
        this.selectedSession.session.remedial_report = data.remedial_report;
        this.editingReport = false;
        this.reportDraft = '';
      } catch (e) {
        this.saveReportError = e?.response?.data?.message || e.message || 'Failed to clear report';
      } finally {
        this.savingReport = false;
      }
    },

    buildReportPrefill() {
      if (!this.selectedSession) return '';
      const s = this.selectedSession.session || {};
      const runs = this.selectedSession.runs || [];
      const fmtDate = s.started_at ? new Date(s.started_at).toLocaleDateString('en-GB') : 'unknown';
      const branch = s.fynla_branch || 'unknown';

      const lines = [];
      lines.push(`# Remedial report — ${s.scenario_id || 'unknown'} — session #${s.id}`);
      lines.push('');
      lines.push(`*Recording session: #${s.id}. Date: ${fmtDate}. Branch: \`${branch}\`.*`);
      lines.push('');
      lines.push('## Run summary');
      lines.push('');
      if (!runs.length) {
        lines.push('- _no runs captured_');
      } else {
        for (const r of runs) {
          const failures = r?.delta?.failures ? Object.keys(r.delta.failures).length : 0;
          const status = failures === 0 ? 'green' : 'red';
          const path = r?.delta?.detected_result_path || '?';
          const tools = (r?.delta?.actual_tools?.length) ?? (Array.isArray(r?.tool_calls) ? r.tool_calls.length : 0);
          const ms = r?.delta?.duration_ms ?? r?.duration_ms ?? '?';
          lines.push(`- **${r.provider}/${r.model || '?'}:** ${ms}ms, ${tools} tool calls, \`${path}\` path, **${status} overall**.`);
        }
      }
      lines.push('');
      lines.push(`- **Dashboard URL:** \`/admin/eval-recordings/${s.id}\``);
      lines.push('');
      lines.push('## Rubric findings');
      lines.push('');

      const perProvider = (fn) => runs.map((r) => `${r.provider}: ${fn(r)}`).join('; ') || '_no runs_';

      // 2.1 Classification
      lines.push(`- **2.1 Classification** — ${perProvider((r) => {
        const cs = r?.delta?.classification_shape;
        if (!cs) return 'classification_shape not in delta';
        const actual = cs.actual?.primary || '?';
        const expected = cs.expected?.primary || '?';
        return `actual \`${actual}\` (expected \`${expected}\`)`;
      })}.`);

      // 2.2 Tool use
      lines.push(`- **2.2 Tool use** — ${perProvider((r) => {
        const d = r?.delta || {};
        const m = (d.missing_tools || []).length;
        const e = (d.extra_tools || []).length;
        const f = (d.forbidden_tool_hits || []).length;
        const ts = d.timing_status || '?';
        const overage = ts === 'over_budget' ? ` (over by ${d.timing_overage_ms || '?'}ms)` : '';
        return `missing=${m}, extra=${e}, forbidden=${f}, timing ${ts}${overage}`;
      })}.`);

      // 2.3 LLM response mode + signposting
      lines.push(`- **2.3 LLM response mode + signposting** — ${perProvider((r) => {
        const rm = r?.delta?.response_mode;
        const fp = (r?.delta?.forbidden_output_hits || []).length;
        if (!rm) return `forbidden_phrases=${fp}`;
        return `actual \`${rm.actual || '?'}\` (expected \`${rm.expected || '?'}\`); forbidden_phrases=${fp}`;
      })}.`);

      // 2.4 Engine output
      lines.push('- **2.4 Engine output** — TODO: assess whether engine output is surfaced verbatim or paraphrased (INV-2.3.2).');

      // 2.5 Code path / readiness gate
      lines.push(`- **2.5 Code path / readiness gate** — ${perProvider((r) => `detected_result_path=\`${r?.delta?.detected_result_path || '?'}\``)}.`);

      // 2.6 Response quality
      lines.push('- **2.6 Response quality** — TODO: assess assistant text qualitatively. Compare against canonical voice; check structure, numerical specificity, tone.');

      // 2.7 Provider parity
      lines.push('- **2.7 Provider parity** — TODO: cross-provider diff. Both runs available above.');

      // 2.8 SSE shape
      lines.push(`- **2.8 SSE shape** — ${perProvider((r) => {
        const m = (r?.delta?.missing_sse_event_types || []).length;
        return `missing_types=${m}`;
      })}.`);

      // 2.9 DB writes
      lines.push(`- **2.9 DB writes** — ${perProvider((r) => {
        const w = r?.db_writes_made;
        if (!w) return 'no db_writes captured';
        if (Array.isArray(w)) return `${w.length} writes`;
        return 'see db_writes_made on run row';
      })}.`);

      // 2.10 Recording infrastructure
      lines.push('- **2.10 Recording infrastructure** — not assessed unless other gaps suspect.');
      lines.push('');
      lines.push('## Gaps in detail');
      lines.push('');
      lines.push('_For each non-"no gap" finding above, add a stanza below. Delete this template line once populated._');
      lines.push('');
      lines.push('### Gap N: <one-line description>');
      lines.push('');
      lines.push('- **Rubric section:** <2.X>');
      lines.push('- **Evidence:** <quoted line from delta or assistant_text>');
      lines.push('- **Likely category:** <YAML | classifier | tool/contract | prompt/engine | code | quality | provider drift | SSE | DB | recording>');
      lines.push('- **Likely fix surface:** `<file:line>`');
      lines.push('- **Browser verification needed?** <yes/no/not yet decided>');
      lines.push('- **Notes:**');
      lines.push('');
      lines.push('## Recommendation');
      lines.push('');
      lines.push('TODO: one paragraph. Either no action recommended, or recommend acting on Gap N first because <reason>. Estimated fix surface, estimated effort. CSJ decides.');
      lines.push('');
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
