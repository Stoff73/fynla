<template>
  <div class="card overflow-hidden p-0">
    <!-- Header with PASS/FAIL banner -->
    <div
      class="px-5 py-4 border-b border-light-gray"
      :class="run.delta?.passes ? 'bg-spring-50' : 'bg-raspberry-50'"
    >
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="text-xs uppercase tracking-wider text-neutral-500 font-bold">{{ run.provider }}</div>
          <div class="font-mono text-sm text-horizon-500 mt-0.5">{{ run.model }}</div>
        </div>
        <div class="text-right">
          <span :class="passBadgeClass">{{ run.delta?.passes ? 'PASS' : 'FAIL' }}</span>
          <div class="text-xs text-neutral-500 mt-1">{{ run.duration_ms }}ms · {{ run.sse_event_count }} events</div>
        </div>
      </div>

      <div v-if="run.sse_event_types" class="flex flex-wrap gap-1 mt-2">
        <span
          v-for="(count, type) in run.sse_event_types"
          :key="type"
          class="inline-flex px-2 py-0.5 rounded text-xs bg-horizon-100 text-horizon-600 font-mono"
        >
          {{ type }}: {{ count }}
        </span>
      </div>
    </div>

    <!-- Delta report — what went wrong -->
    <div v-if="run.delta" class="px-5 py-4 border-b border-light-gray">
      <h5 class="text-xs uppercase tracking-wider text-neutral-400 font-bold mb-3">Delta vs scenario expectations</h5>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
        <ChecklistItem :passing="run.delta.missing_tools.length === 0" label="Required tools called">
          <template v-if="run.delta.missing_tools.length">
            <div class="text-xs text-raspberry-700 mt-1">Missing: <code>{{ run.delta.missing_tools.join(', ') }}</code></div>
          </template>
          <template v-if="run.delta.extra_tools.length">
            <div class="text-xs text-violet-700 mt-1">Extra: <code>{{ run.delta.extra_tools.join(', ') }}</code></div>
          </template>
        </ChecklistItem>

        <ChecklistItem :passing="run.delta.forbidden_tool_hits.length === 0" label="No forbidden tools">
          <div v-if="run.delta.forbidden_tool_hits.length" class="text-xs text-raspberry-700 mt-1">
            Hit: <code>{{ run.delta.forbidden_tool_hits.join(', ') }}</code>
          </div>
        </ChecklistItem>

        <ChecklistItem :passing="run.delta.forbidden_output_hits.length === 0" label="No forbidden phrases">
          <div v-if="run.delta.forbidden_output_hits.length" class="text-xs text-raspberry-700 mt-1">
            Hit: <span v-for="p in run.delta.forbidden_output_hits" :key="p" class="font-mono mr-2">"{{ p }}"</span>
          </div>
        </ChecklistItem>

        <ChecklistItem :passing="run.delta.timing_status !== 'over_budget'" label="Within timing budget">
          <div class="text-xs mt-1" :class="run.delta.timing_status === 'over_budget' ? 'text-raspberry-700' : 'text-neutral-500'">
            {{ run.delta.duration_ms }}ms / {{ run.delta.timing_budget_ms ?? '—' }}ms budget
            <span v-if="run.delta.timing_status === 'over_budget'"> ({{ run.delta.timing_overage_ms }}ms over)</span>
          </div>
        </ChecklistItem>

        <ChecklistItem :passing="run.delta.missing_sse_event_types.length === 0" label="Required SSE events emitted" class="sm:col-span-2">
          <div v-if="run.delta.missing_sse_event_types.length" class="text-xs text-raspberry-700 mt-1">
            Missing types: <code>{{ run.delta.missing_sse_event_types.join(', ') }}</code>
          </div>
        </ChecklistItem>
      </div>

      <!-- Tool-by-tool comparison -->
      <div class="mt-4">
        <h6 class="text-xs uppercase tracking-wider text-neutral-400 font-bold mb-2">Tool calls (expected → actual)</h6>
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div>
            <div class="text-neutral-500 mb-1">Expected ({{ run.delta.expected_tools.length }})</div>
            <div v-if="!run.delta.expected_tools.length" class="text-neutral-400 italic">none</div>
            <ul v-else class="space-y-1">
              <li v-for="t in run.delta.expected_tools" :key="t" class="font-mono">
                <span :class="run.delta.actual_tools.includes(t) ? 'text-spring-700' : 'text-raspberry-700'">
                  {{ run.delta.actual_tools.includes(t) ? '✓' : '✗' }} {{ t }}
                </span>
              </li>
            </ul>
          </div>
          <div>
            <div class="text-neutral-500 mb-1">Actual ({{ run.delta.actual_tools.length }})</div>
            <div v-if="!run.delta.actual_tools.length" class="text-neutral-400 italic">none called</div>
            <ul v-else class="space-y-1">
              <li v-for="t in run.delta.actual_tools" :key="t" class="font-mono">
                <span :class="run.delta.expected_tools.includes(t) ? 'text-spring-700' : 'text-violet-700'">
                  {{ run.delta.expected_tools.includes(t) ? '✓' : '+' }} {{ t }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Diagnosis hints (root cause) -->
      <div v-if="run.delta.diagnosis_hints?.length" class="mt-4 bg-violet-50 border border-violet-200 rounded p-3">
        <h6 class="text-xs uppercase tracking-wider text-violet-700 font-bold mb-2">Root cause / what went wrong</h6>
        <ul class="text-sm text-horizon-500 space-y-1 list-disc list-inside">
          <li v-for="(h, i) in run.delta.diagnosis_hints" :key="i">{{ h }}</li>
        </ul>
      </div>

      <!-- Suggested fixes -->
      <div v-if="run.delta.suggested_fixes?.length" class="mt-3 bg-horizon-50 border border-horizon-200 rounded p-3">
        <h6 class="text-xs uppercase tracking-wider text-horizon-700 font-bold mb-2">Possible fixes to try</h6>
        <ul class="text-sm text-horizon-500 space-y-1 list-disc list-inside">
          <li v-for="(f, i) in run.delta.suggested_fixes" :key="i">{{ f }}</li>
        </ul>
      </div>
    </div>

    <!-- User message -->
    <div class="px-5 py-3 border-b border-light-gray">
      <div class="text-xs uppercase tracking-wider text-neutral-400 font-bold mb-1">User asked</div>
      <div class="text-sm text-horizon-500">{{ run.user_message }}</div>
    </div>

    <!-- Assistant response -->
    <div class="px-5 py-3 border-b border-light-gray">
      <div class="text-xs uppercase tracking-wider text-neutral-400 font-bold mb-1">
        Assistant response ({{ assistantChars }} chars)
      </div>
      <div class="text-sm text-horizon-500 whitespace-pre-wrap">{{ run.assistant_text || '(empty)' }}</div>
    </div>

    <!-- Tool calls detail -->
    <div class="px-5 py-3 border-b border-light-gray">
      <div class="flex items-center justify-between mb-1">
        <div class="text-xs uppercase tracking-wider text-neutral-400 font-bold">
          Tool call detail ({{ toolCallCount }})
        </div>
        <button
          v-if="toolCallCount"
          @click="showTools = !showTools"
          class="text-xs text-raspberry-500 hover:text-raspberry-600 font-medium"
        >
          {{ showTools ? 'Hide' : 'Show' }}
        </button>
      </div>
      <div v-if="!toolCallCount" class="text-sm text-neutral-400 italic">none</div>
      <div v-else-if="showTools" class="space-y-2 mt-2">
        <div
          v-for="(t, i) in run.tool_calls"
          :key="i"
          class="bg-savannah-50 rounded p-3 text-xs font-mono"
        >
          <div class="font-bold text-horizon-500 mb-1">{{ i + 1 }}. {{ t.name || 'unknown' }}</div>
          <div class="text-neutral-500 mb-1">args:</div>
          <pre class="overflow-auto whitespace-pre-wrap text-horizon-500 mb-2">{{ formatJson(t.args) }}</pre>
          <div v-if="t.result !== null && t.result !== undefined">
            <div class="text-neutral-500 mb-1">result:</div>
            <pre class="overflow-auto whitespace-pre-wrap text-horizon-500">{{ typeof t.result === 'string' ? t.result : formatJson(t.result) }}</pre>
          </div>
        </div>
      </div>
    </div>

    <!-- DB writes -->
    <div class="px-5 py-3 border-b border-light-gray">
      <div class="text-xs uppercase tracking-wider text-neutral-400 font-bold mb-1">DB writes</div>
      <div class="flex gap-3 text-sm">
        <span><span class="font-bold text-spring-700">{{ writes.created }}</span> created</span>
        <span><span class="font-bold text-violet-700">{{ writes.updated }}</span> updated</span>
        <span><span class="font-bold text-raspberry-700">{{ writes.deleted }}</span> deleted</span>
      </div>
    </div>

    <!-- Engine + gate timeline -->
    <div v-if="traceEntries.length" class="px-5 py-3 border-b border-light-gray">
      <div class="flex items-center justify-between mb-2">
        <div class="text-xs uppercase tracking-wider text-neutral-400 font-bold">
          Engine + gate timeline ({{ traceEntries.length }})
        </div>
        <button
          @click="showTrace = !showTrace"
          class="text-xs text-raspberry-500 hover:text-raspberry-600 font-medium"
        >
          {{ showTrace ? 'Hide' : 'Show' }}
        </button>
      </div>
      <ul v-if="showTrace" class="space-y-1">
        <li
          v-for="(entry, i) in traceEntries"
          :key="i"
          class="flex items-start gap-3 text-xs font-mono"
        >
          <span class="text-neutral-400 w-16 text-right shrink-0">{{ formatTraceTime(entry) }}</span>
          <span :class="traceEventClass(entry.event)" class="font-bold w-28 shrink-0">{{ entry.event }}</span>
          <span class="text-horizon-500 flex-1">{{ formatTraceLine(entry) }}</span>
        </li>
      </ul>
    </div>

    <!-- Lazy-loaded raw fixture + system prompt links -->
    <div class="px-5 py-3 flex flex-wrap gap-3">
      <button
        v-if="run.has_system_prompt"
        @click="$emit('open-system-prompt', run)"
        class="text-xs font-medium text-raspberry-500 hover:text-raspberry-600 underline"
      >
        View full system prompt
      </button>
      <button
        v-if="run.fixture_exists"
        @click="$emit('open-raw-fixture', run)"
        class="text-xs font-medium text-raspberry-500 hover:text-raspberry-600 underline"
      >
        View raw fixture file ({{ run.sse_event_count }} events)
      </button>
      <span v-if="!run.fixture_exists" class="text-xs text-violet-600">
        Fixture missing: <code>{{ run.fixture_path }}</code>
      </span>
    </div>
  </div>
</template>

<script>
import ChecklistItem from './ChecklistItem.vue';

export default {
  name: 'RunPanel',

  components: { ChecklistItem },

  props: {
    run: { type: Object, required: true },
  },

  emits: ['open-system-prompt', 'open-raw-fixture'],

  data() {
    return {
      showTools: true,
      showTrace: true,
    };
  },

  computed: {
    assistantChars() {
      return (this.run.assistant_text || '').length;
    },
    toolCallCount() {
      return Array.isArray(this.run.tool_calls) ? this.run.tool_calls.length : 0;
    },
    writes() {
      const w = this.run.db_writes_made || {};
      const count = (key) => Array.isArray(w[key]) ? w[key].length : (Number.isFinite(w[key]) ? w[key] : 0);
      return {
        created: count('created'),
        updated: count('updated'),
        deleted: count('deleted'),
      };
    },
    passBadgeClass() {
      const base = 'inline-flex px-3 py-1 rounded-full text-xs font-bold';
      return this.run.delta?.passes
        ? `${base} bg-spring-100 text-spring-800`
        : `${base} bg-raspberry-100 text-raspberry-800`;
    },
    traceEntries() {
      return Array.isArray(this.run.engine_trace) ? this.run.engine_trace : [];
    },
  },

  methods: {
    formatJson(obj) {
      if (obj === null || obj === undefined) return '(null)';
      if (typeof obj === 'string') return obj;
      try {
        return JSON.stringify(obj, null, 2);
      } catch (_e) {
        return String(obj);
      }
    },
    traceEventClass(eventType) {
      const map = {
        AgentDecision: 'text-spring-700',
        GateChecked: 'text-horizon-500',
        EngineCalled: 'text-raspberry-500',
      };
      return map[eventType] || 'text-neutral-500';
    },
    formatTraceTime(entry) {
      if (entry.atMicrotime) {
        return `${Math.round(entry.atMicrotime * 1000) % 100000}ms`;
      }
      if (typeof entry.t_ms === 'number') return `${entry.t_ms}ms`;
      if (typeof entry.durationMs === 'number') return `${entry.durationMs}ms`;
      return '';
    },
    formatTraceLine(entry) {
      if (entry.event === 'GateChecked') {
        const passed = entry.passed === true ? 'PASS' : entry.passed === false ? 'FAIL' : '?';
        return `${entry.gate}/${entry.module} → ${passed}`;
      }
      if (entry.event === 'EngineCalled') {
        const path = entry.resultSummary?.result_path || entry.result_path || '';
        return `${entry.engine}${path ? ` → ${path}` : ''}`;
      }
      if (entry.event === 'AgentDecision') {
        const outcome = entry.outcome ?? '';
        return `${entry.agent || 'Agent'}.${entry.decisionPoint}${outcome ? ` → ${outcome}` : ''}`;
      }
      return JSON.stringify(entry);
    },
  },
};
</script>
