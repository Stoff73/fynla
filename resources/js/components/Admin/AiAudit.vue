<template>
  <div class="flex flex-col gap-3 h-[calc(100vh-220px)] min-h-[500px]">
    <!-- Tab switcher: Conversations vs Chain view -->
    <div class="flex items-center gap-2 border-b border-light-gray pb-2">
      <button
        type="button"
        :class="[
          'px-3 py-1.5 text-sm rounded-md transition-colors',
          activeTab === 'conversations'
            ? 'bg-raspberry-500 text-white'
            : 'text-horizon-500 hover:bg-savannah-100'
        ]"
        @click="activeTab = 'conversations'"
      >Conversations</button>
      <button
        type="button"
        :class="[
          'px-3 py-1.5 text-sm rounded-md transition-colors',
          activeTab === 'chain'
            ? 'bg-raspberry-500 text-white'
            : 'text-horizon-500 hover:bg-savannah-100'
        ]"
        @click="onChainTabSelected"
      >Chain view</button>

      <!-- Chain integrity banner (only relevant on chain tab) -->
      <div v-if="activeTab === 'chain'" class="ml-auto flex items-center gap-2">
        <span
          v-if="chainStatus.loading"
          class="text-xs text-neutral-500"
        >Verifying chain…</span>
        <span
          v-else-if="chainStatus.result?.chain_valid"
          class="text-xs px-2 py-1 rounded-md bg-spring-100 text-spring-700"
          :data-tip-hash="chainStatus.result.tip_hash"
          :title="`tip ${chainStatus.result.tip_hash}`"
        >Chain valid · {{ chainStatus.result.row_count }} rows · tip {{ shortTipHash(chainStatus.result.tip_hash) }}</span>
        <span
          v-else-if="chainStatus.result"
          class="text-xs px-2 py-1 rounded-md bg-raspberry-100 text-raspberry-700"
        >Chain broken at row #{{ chainStatus.result.broken_at }}</span>
        <button
          type="button"
          class="text-xs px-2 py-1 rounded-md border border-light-gray hover:bg-savannah-100"
          :disabled="chainStatus.loading"
          @click="verifyChain"
        >Re-verify</button>
      </div>
    </div>

    <!-- Conversations tab -->
    <div v-if="activeTab === 'conversations'" class="flex gap-4 flex-1 min-h-0">
    <!-- Left Panel: User List -->
    <div class="w-1/4 min-w-[220px] flex flex-col border border-light-gray rounded-lg bg-white overflow-hidden">
      <div class="p-3 border-b border-light-gray">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search users..."
          class="w-full px-3 py-2 text-sm border border-light-gray rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
        />
      </div>
      <div class="flex-1 overflow-y-auto">
        <div v-if="loadingUsers" class="flex items-center justify-center py-8">
          <div class="w-6 h-6 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="users.length === 0" class="p-4 text-center text-sm text-neutral-500">
          No users with AI conversations
        </div>
        <button
          v-for="user in users"
          :key="user.id"
          @click="selectUser(user)"
          :class="[
            'w-full text-left px-3 py-2.5 border-b border-light-gray transition-colors text-sm',
            selectedUser?.id === user.id
              ? 'bg-raspberry-50 border-l-2 border-l-raspberry-500'
              : 'hover:bg-savannah-100'
          ]"
        >
          <div class="font-medium text-horizon-500 truncate">{{ user.name || user.email }}</div>
          <div class="text-xs text-neutral-500 truncate">{{ user.email }}</div>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-xs text-neutral-500">{{ user.conversation_count }} conversation{{ user.conversation_count !== 1 ? 's' : '' }}</span>
            <span v-if="user.is_preview_user" class="text-xs px-1.5 py-0.5 bg-violet-100 text-violet-700 rounded">Preview</span>
          </div>
        </button>
      </div>
    </div>

    <!-- Middle Panel: Conversations -->
    <div class="w-1/4 min-w-[220px] flex flex-col border border-light-gray rounded-lg bg-white overflow-hidden">
      <div class="p-3 border-b border-light-gray">
        <h3 class="text-sm font-semibold text-horizon-500">
          {{ selectedUser ? (selectedUser.name || selectedUser.email) : 'Conversations' }}
        </h3>
      </div>
      <div class="flex-1 overflow-y-auto">
        <div v-if="!selectedUser" class="p-4 text-center text-sm text-neutral-500">
          Select a user to view conversations
        </div>
        <div v-else-if="loadingConversations" class="flex items-center justify-center py-8">
          <div class="w-6 h-6 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="conversations.length === 0" class="p-4 text-center text-sm text-neutral-500">
          No conversations for this user
        </div>
        <button
          v-for="conv in conversations"
          :key="conv.id"
          @click="selectConversation(conv)"
          :class="[
            'w-full text-left px-3 py-2.5 border-b border-light-gray transition-colors text-sm',
            selectedConversation?.id === conv.id
              ? 'bg-raspberry-50 border-l-2 border-l-raspberry-500'
              : 'hover:bg-savannah-100'
          ]"
        >
          <div class="font-medium text-horizon-500 truncate">{{ conv.title || 'Untitled' }}</div>
          <div class="flex items-center gap-2 mt-1 text-xs text-neutral-500">
            <span>{{ formatDate(conv.created_at) }}</span>
            <span>{{ conv.message_count }} msg{{ conv.message_count !== 1 ? 's' : '' }}</span>
          </div>
          <div class="text-xs text-neutral-500 mt-0.5">
            {{ formatTokens(conv.total_input_tokens) }} in / {{ formatTokens(conv.total_output_tokens) }} out
          </div>
        </button>
      </div>
    </div>

    <!-- Right Panel: Message Thread -->
    <div class="flex-1 flex flex-col border border-light-gray rounded-lg bg-white overflow-hidden">
      <!-- Header -->
      <div v-if="selectedConversation" class="p-3 border-b border-light-gray">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold text-horizon-500 truncate">{{ selectedConversation.title }}</h3>
            <div class="text-xs text-neutral-500 mt-0.5">
              {{ selectedUser?.email }} | {{ selectedConversation.model_used || 'Unknown model' }} | {{ formatTokens(selectedConversation.total_input_tokens + selectedConversation.total_output_tokens) }} tokens
            </div>
          </div>
        </div>
        <!-- Advice Log Summary -->
        <div v-if="adviceLog" class="mt-2 flex flex-wrap gap-1.5">
          <span class="text-xs px-2 py-0.5 bg-horizon-100 text-horizon-700 rounded-full font-medium">{{ adviceLog.query_type }}</span>
          <span
            v-for="related in (adviceLog.classification?.related || [])"
            :key="related"
            class="text-xs px-2 py-0.5 bg-savannah-100 text-horizon-500 rounded-full"
          >{{ related }}</span>
          <span
            v-if="adviceLog.kyc_status"
            :class="[
              'text-xs px-2 py-0.5 rounded-full font-medium',
              adviceLog.kyc_status.passed ? 'bg-spring-100 text-spring-700' : 'bg-raspberry-100 text-raspberry-700'
            ]"
          >KYC: {{ adviceLog.kyc_status.passed ? 'PASS' : 'BLOCKED' }}</span>
        </div>
      </div>

      <!-- Messages -->
      <div class="flex-1 overflow-y-auto p-4 space-y-3">
        <div v-if="!selectedConversation" class="flex flex-col items-center justify-center h-full text-neutral-500">
          <svg class="w-12 h-12 mb-3 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <p class="text-sm">Select a conversation to view messages</p>
        </div>
        <div v-else-if="loadingMessages" class="flex items-center justify-center py-8">
          <div class="w-8 h-8 border-3 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>
        <template v-else>
          <div
            v-for="msg in messages"
            :key="msg.id"
            :class="[
              'rounded-lg p-3',
              msg.role === 'user' ? 'bg-raspberry-50' : 'bg-white border border-light-gray'
            ]"
          >
            <!-- Message header -->
            <div class="flex items-center justify-between mb-1.5">
              <span :class="[
                'text-xs font-semibold uppercase',
                msg.role === 'user' ? 'text-raspberry-600' : 'text-horizon-500'
              ]">{{ msg.role === 'user' ? 'User' : 'Fyn' }}</span>
              <span class="text-xs text-neutral-500">{{ formatDate(msg.created_at) }}</span>
            </div>

            <!-- Content -->
            <div class="text-sm text-horizon-500 whitespace-pre-wrap break-words">{{ msg.content || '(empty response)' }}</div>

            <!-- Assistant message extras -->
            <div v-if="msg.role === 'assistant'" class="mt-2 space-y-1.5">
              <!-- Token info -->
              <div v-if="msg.input_tokens || msg.output_tokens" class="text-xs text-neutral-500">
                {{ formatTokens(msg.input_tokens) }} in / {{ formatTokens(msg.output_tokens) }} out | {{ msg.model_used }}
              </div>

              <!-- Validation violations -->
              <div v-if="msg.metadata?.validation_violations?.length" class="flex flex-wrap gap-1">
                <span
                  v-for="(v, i) in msg.metadata.validation_violations"
                  :key="i"
                  class="text-xs px-2 py-0.5 bg-raspberry-100 text-raspberry-700 rounded-full"
                >{{ v.rule }}: {{ v.detail }}</span>
              </div>

              <!-- Expandable: Tool Calls -->
              <button
                v-if="msg.metadata?.tool_calls?.length"
                @click="toggleSection(msg.id, 'tools')"
                class="text-xs text-violet-600 hover:text-violet-800 font-medium"
              >
                {{ expandedSections[msg.id + '-tools'] ? 'Hide' : 'Show' }} Tool Calls ({{ msg.metadata.tool_calls.length }})
              </button>
              <div v-if="expandedSections[msg.id + '-tools']" class="bg-savannah-100 rounded p-2 text-xs font-mono space-y-1">
                <div v-for="(tc, i) in msg.metadata.tool_calls" :key="i">
                  <span class="font-semibold text-horizon-500">{{ tc.tool }}</span>
                  <span class="text-neutral-500"> → {{ tc.result_summary }}</span>
                </div>
              </div>

              <!-- Expandable: System Prompt -->
              <button
                v-if="msg.system_prompt"
                @click="toggleSection(msg.id, 'prompt')"
                class="text-xs text-violet-600 hover:text-violet-800 font-medium"
              >
                {{ expandedSections[msg.id + '-prompt'] ? 'Hide' : 'Show' }} System Prompt ({{ Math.round((msg.system_prompt || '').length / 4) }} tokens)
              </button>
              <pre
                v-if="expandedSections[msg.id + '-prompt']"
                class="bg-horizon-50 text-xs font-mono p-3 rounded overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap break-words text-horizon-500"
              >{{ msg.system_prompt }}</pre>
            </div>
          </div>
        </template>
      </div>
    </div>
    </div>

    <!-- Chain view tab -->
    <div v-else-if="activeTab === 'chain'" class="flex flex-col flex-1 min-h-0 border border-light-gray rounded-lg bg-white overflow-hidden">
      <div class="p-3 border-b border-light-gray flex flex-wrap items-center gap-2">
        <select
          v-model="chainFilters.operation"
          class="text-sm px-2 py-1.5 border border-light-gray rounded-md"
          @change="loadChain(1)"
        >
          <option value="">All operations</option>
          <option value="read">read</option>
          <option value="write">write</option>
          <option value="handoff">handoff</option>
          <option value="classify">classify</option>
        </select>
        <select
          v-model="chainFilters.status"
          class="text-sm px-2 py-1.5 border border-light-gray rounded-md"
          @change="loadChain(1)"
        >
          <option value="">All statuses</option>
          <option value="dispatched">dispatched</option>
          <option value="persisted">persisted</option>
          <option value="failed">failed</option>
          <option value="stripped">stripped</option>
        </select>
        <input
          v-model="chainFilters.userId"
          type="number"
          placeholder="Filter by user_id"
          class="text-sm px-2 py-1.5 border border-light-gray rounded-md w-44"
          @change="loadChain(1)"
        />
        <span class="ml-auto text-xs text-neutral-500" v-if="chainPagination.total != null">
          {{ chainPagination.total }} rows
        </span>
      </div>

      <div class="flex-1 overflow-y-auto">
        <div v-if="chainLoading" class="flex items-center justify-center py-12">
          <div class="w-8 h-8 border-3 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="chainEvents.length === 0" class="p-8 text-center text-sm text-neutral-500">
          No audit rows match these filters.
        </div>
        <table v-else class="w-full text-xs">
          <thead class="bg-savannah-100 text-horizon-500 sticky top-0">
            <tr>
              <th class="text-left px-3 py-2">#</th>
              <th class="text-left px-3 py-2">Tool</th>
              <th class="text-left px-3 py-2">Op</th>
              <th class="text-left px-3 py-2">Status</th>
              <th class="text-left px-3 py-2">User</th>
              <th class="text-left px-3 py-2">Entity</th>
              <th class="text-left px-3 py-2">Hash</th>
              <th class="text-left px-3 py-2">When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in chainEvents" :key="row.id" class="border-t border-light-gray hover:bg-savannah-100/40">
              <td class="px-3 py-2 font-mono">{{ row.id }}</td>
              <td class="px-3 py-2">{{ row.tool_name }}</td>
              <td class="px-3 py-2">{{ row.operation }}</td>
              <td class="px-3 py-2">
                <span :class="statusBadgeClass(row.status)" class="px-2 py-0.5 rounded-full">{{ row.status }}</span>
              </td>
              <td class="px-3 py-2 font-mono">{{ row.user_id }}</td>
              <td class="px-3 py-2">
                <span v-if="row.entity_type">{{ row.entity_type }}#{{ row.entity_id }}</span>
                <span v-else class="text-neutral-400">—</span>
              </td>
              <td class="px-3 py-2 font-mono text-neutral-500">{{ row.row_hash.slice(0, 12) }}…</td>
              <td class="px-3 py-2 text-neutral-500">{{ formatDate(row.signed_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="chainPagination.last_page > 1" class="p-2 border-t border-light-gray flex items-center justify-between text-xs">
        <button
          type="button"
          class="px-2 py-1 rounded-md border border-light-gray disabled:opacity-50"
          :disabled="chainPagination.current_page <= 1"
          @click="loadChain(chainPagination.current_page - 1)"
        >Prev</button>
        <span class="text-neutral-500">
          Page {{ chainPagination.current_page }} of {{ chainPagination.last_page }}
        </span>
        <button
          type="button"
          class="px-2 py-1 rounded-md border border-light-gray disabled:opacity-50"
          :disabled="chainPagination.current_page >= chainPagination.last_page"
          @click="loadChain(chainPagination.current_page + 1)"
        >Next</button>
      </div>
    </div>
  </div>
</template>

<script>
import aiAuditService from '../../services/aiAuditService';

export default {
  name: 'AiAudit',

  data() {
    return {
      activeTab: 'conversations',
      users: [],
      conversations: [],
      messages: [],
      adviceLog: null,
      selectedUser: null,
      selectedConversation: null,
      searchQuery: '',
      loadingUsers: false,
      loadingConversations: false,
      loadingMessages: false,
      expandedSections: {},
      searchTimeout: null,
      // Chain view state (S0.12)
      chainEvents: [],
      chainLoading: false,
      chainFilters: { userId: '', status: '', operation: '' },
      chainPagination: { current_page: 1, last_page: 1, total: null },
      chainStatus: { loading: false, result: null },
    };
  },

  watch: {
    searchQuery() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.loadUsers(), 300);
    },
  },

  mounted() {
    this.loadUsers();
  },

  methods: {
    async loadUsers() {
      this.loadingUsers = true;
      try {
        const response = await aiAuditService.getUsers(this.searchQuery);
        this.users = response.data?.data || [];
      } catch (e) {
        this.users = [];
      } finally {
        this.loadingUsers = false;
      }
    },

    async selectUser(user) {
      this.selectedUser = user;
      this.selectedConversation = null;
      this.messages = [];
      this.adviceLog = null;
      this.loadingConversations = true;
      try {
        const response = await aiAuditService.getUserConversations(user.id);
        this.conversations = response.data?.conversations || [];
      } catch (e) {
        this.conversations = [];
      } finally {
        this.loadingConversations = false;
      }
    },

    async selectConversation(conv) {
      this.selectedConversation = conv;
      this.loadingMessages = true;
      try {
        const response = await aiAuditService.getConversationMessages(conv.id);
        this.messages = response.data?.messages || [];
        this.adviceLog = response.data?.advice_log || null;
      } catch (e) {
        this.messages = [];
        this.adviceLog = null;
      } finally {
        this.loadingMessages = false;
      }
    },

    toggleSection(messageId, section) {
      const key = messageId + '-' + section;
      this.expandedSections = {
        ...this.expandedSections,
        [key]: !this.expandedSections[key],
      };
    },

    formatDate(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    },

    formatTokens(n) {
      if (!n) return '0';
      if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
      return String(n);
    },

    // ─── Chain view (S0.12) ──────────────────────────────────────────
    async onChainTabSelected() {
      this.activeTab = 'chain';
      if (this.chainEvents.length === 0) {
        await Promise.all([this.loadChain(1), this.verifyChain()]);
      }
    },

    async loadChain(page = 1) {
      this.chainLoading = true;
      try {
        const response = await aiAuditService.getChain({
          userId: this.chainFilters.userId || '',
          status: this.chainFilters.status || '',
          operation: this.chainFilters.operation || '',
          page,
        });
        const paginator = response.data || {};
        this.chainEvents = paginator.data || [];
        this.chainPagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total ?? null,
        };
      } catch (e) {
        this.chainEvents = [];
      } finally {
        this.chainLoading = false;
      }
    },

    async verifyChain() {
      this.chainStatus.loading = true;
      try {
        const response = await aiAuditService.verifyChain();
        this.chainStatus.result = response.data || null;
      } catch (e) {
        this.chainStatus.result = null;
      } finally {
        this.chainStatus.loading = false;
      }
    },

    statusBadgeClass(status) {
      switch (status) {
        case 'persisted': return 'bg-spring-100 text-spring-700';
        case 'failed': return 'bg-raspberry-100 text-raspberry-700';
        case 'stripped': return 'bg-violet-100 text-violet-700';
        case 'dispatched':
        default:
          return 'bg-horizon-100 text-horizon-700';
      }
    },

    shortTipHash(hash) {
      return typeof hash === 'string' && hash.length >= 12 ? hash.slice(0, 12) + '…' : hash;
    },
  },
};
</script>
