<template>
  <div class="border-b border-light-gray mb-6">
    <nav class="flex space-x-4 sm:space-x-8 flex-wrap" aria-label="Admin sections">
      <template v-for="item in tabs" :key="item.id || item.group">
        <!-- Simple tab -->
        <button
          v-if="!item.children"
          @click="go(item)"
          :class="tabClass(isSimpleActive(item))"
        >
          <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icon(item.icon)" />
          </svg>
          <span class="hidden sm:inline">{{ item.label }}</span>
          <span class="sm:hidden">{{ item.shortLabel || item.label }}</span>
        </button>

        <!-- Dropdown group (opens on hover) -->
        <div
          v-else
          class="relative flex-shrink-0"
          @mouseenter="openGroup = item.group"
          @mouseleave="openGroup = null"
          @focusin="openGroup = item.group"
        >
          <button
            @click="openGroup = openGroup === item.group ? null : item.group"
            :class="tabClass(isGroupActive(item))"
            :aria-expanded="openGroup === item.group ? 'true' : 'false'"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icon(item.icon)" />
            </svg>
            <span class="hidden sm:inline">{{ item.label }}</span>
            <span class="sm:hidden">{{ item.shortLabel || item.label }}</span>
            <svg class="w-3 h-3 ml-1 transition-transform" :class="{ 'rotate-180': openGroup === item.group }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div
            v-show="openGroup === item.group"
            class="absolute top-full left-0 bg-white border border-light-gray rounded-lg shadow-lg py-1 z-50 min-w-[200px]"
          >
            <button
              v-for="child in item.children"
              :key="child.label"
              @click="go(child); openGroup = null"
              :class="[
                'w-full text-left px-4 py-2 text-sm flex items-center transition-colors',
                isSimpleActive(child)
                  ? 'text-raspberry-600 bg-raspberry-50 font-medium'
                  : 'text-horizon-500 hover:bg-savannah-100'
              ]"
            >
              <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icon(child.icon)" />
              </svg>
              {{ child.label }}
            </button>
          </div>
        </div>
      </template>
    </nav>
  </div>
</template>

<script>
/**
 * Shared admin tab bar. Rendered at the top of the Admin Panel AND every admin
 * CMS/Campaigns page so the tabs persist across routes with the active one
 * highlighted in raspberry (pink). Dropdown groups open on hover (and click /
 * keyboard focus for accessibility).
 *
 * Destination kinds:
 *   - `tab`  -> an in-page Admin Panel section, addressed as /admin?tab=<id>
 *   - `path` -> a standalone admin route (CMS pages, Campaigns, AI cost, etc.)
 */
const ICONS = {
  dashboard: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  ai: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
  discount: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  matrix: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
  tax: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
  tiers: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
  rates: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
  tables: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  currency: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  database: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
  cms: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
  campaigns: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
};

export default {
  name: 'AdminNav',
  data() {
    return {
      openGroup: null,
      tabs: [
        { id: 'dashboard', label: 'Dashboard', shortLabel: 'Home', icon: 'dashboard', tab: 'dashboard' },
        {
          group: 'users', label: 'Users', icon: 'users',
          children: [
            { label: 'User Metrics', icon: 'users', tab: 'user-metrics' },
            { label: 'User Management', icon: 'users', tab: 'users' },
          ],
        },
        {
          group: 'ai', label: 'AI', icon: 'ai',
          children: [
            { label: 'AI Audit', icon: 'ai', tab: 'ai-audit' },
            { label: 'AI Provider', icon: 'ai', tab: 'ai-settings' },
            { label: 'Eval Recordings', icon: 'ai', tab: 'eval-recordings' },
            { label: 'AI Cost', icon: 'ai', path: '/admin/ai-cost' },
            { label: 'Episodic Compliance', icon: 'ai', path: '/admin/episodic-compliance' },
          ],
        },
        { id: 'discount-codes', label: 'Discount Codes', shortLabel: 'Codes', icon: 'discount', tab: 'discount-codes' },
        { id: 'decision-matrix', label: 'Decision Matrix', shortLabel: 'Matrix', icon: 'matrix', tab: 'decision-matrix' },
        {
          group: 'config', label: 'Config', icon: 'tiers',
          children: [
            { label: 'Tax Settings', icon: 'tax', tab: 'tax-settings' },
            { label: 'Tier Configuration', icon: 'tiers', tab: 'tier-configuration' },
            { label: 'Savings Rates', icon: 'rates', tab: 'savings-market-rates' },
            { label: 'Life Tables', icon: 'tables', tab: 'actuarial-life-tables' },
            { label: 'Currency Rates', icon: 'currency', tab: 'currency-rates' },
          ],
        },
        { id: 'backups', label: 'Database', shortLabel: 'Data', icon: 'database', tab: 'backups' },
        {
          group: 'cms', label: 'CMS', icon: 'cms',
          children: [
            { label: 'Articles', icon: 'cms', path: '/admin/documents' },
            { label: 'Pages', icon: 'cms', path: '/admin/cms/pages' },
            { label: 'Emails', icon: 'cms', path: '/admin/cms/emails' },
          ],
        },
        { id: 'campaigns', label: 'Campaigns', shortLabel: 'Campaigns', icon: 'campaigns', path: '/admin/campaigns' },
      ],
    };
  },
  methods: {
    icon(key) { return ICONS[key] || ''; },
    tabClass(active) {
      return [
        'whitespace-nowrap py-3 sm:py-4 px-1 border-b-2 font-medium text-xs sm:text-sm transition-colors flex items-center flex-shrink-0',
        active
          ? 'border-raspberry-600 text-raspberry-600'
          : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300',
      ];
    },
    isSimpleActive(item) {
      if (item.path) {
        return this.$route.path === item.path || this.$route.path.startsWith(item.path + '/');
      }
      if (item.tab) {
        return this.$route.path === '/admin' && (this.$route.query.tab || 'dashboard') === item.tab;
      }
      return false;
    },
    isGroupActive(item) {
      return (item.children || []).some((c) => this.isSimpleActive(c));
    },
    go(item) {
      if (item.path) {
        if (this.$route.path !== item.path) this.$router.push(item.path);
        return;
      }
      if (item.tab) {
        this.$router.push({ path: '/admin', query: { tab: item.tab } }).catch(() => {});
      }
    },
  },
};
</script>
