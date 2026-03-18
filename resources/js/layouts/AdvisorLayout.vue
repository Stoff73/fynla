<template>
  <div class="min-h-screen flex flex-col">
    <!-- Top bar -->
    <div class="bg-horizon-500 text-white px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <img src="/images/logos/LogoHiResFynlaLight.png" alt="Fynla" class="h-7" />
        <div class="bg-violet-500 text-white text-[11px] font-bold px-3 py-1 rounded-full tracking-wide">
          ADVISOR VIEW
        </div>
      </div>
      <div class="flex items-center gap-2 text-sm">
        <span>{{ userName }}</span>
        <div class="w-8 h-8 rounded-full bg-raspberry-500 flex items-center justify-center font-bold text-[13px]">
          {{ userInitials }}
        </div>
      </div>
    </div>

    <!-- Main area: sidebar + content -->
    <div class="flex flex-1 min-h-0">
      <!-- Sidebar -->
      <div class="w-64 bg-white border-r border-light-gray py-6 flex-shrink-0">
        <!-- Overview section -->
        <div class="px-4 mb-6">
          <div class="text-[11px] font-bold uppercase tracking-widest text-neutral-500 mb-2.5 px-3">
            Overview
          </div>
          <router-link
            to="/advisor"
            exact
            class="sidebar-item"
            :class="isActive('/advisor', true) ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
                <rect x="14" y="14" width="7" height="7" />
              </svg>
            </div>
            Dashboard
          </router-link>
          <router-link
            to="/advisor/clients"
            class="sidebar-item"
            :class="isActive('/advisor/clients') ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            All Clients
            <span v-if="clientCount > 0" class="sidebar-badge">{{ clientCount }}</span>
          </router-link>
        </div>

        <!-- Actions section -->
        <div class="px-4 mb-6">
          <div class="text-[11px] font-bold uppercase tracking-widest text-neutral-500 mb-2.5 px-3">
            Actions
          </div>
          <router-link
            to="/advisor/reviews"
            class="sidebar-item"
            :class="isActive('/advisor/reviews') ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
            </div>
            Reviews Due
            <span v-if="reviewsDueCount > 0" class="sidebar-badge">{{ reviewsDueCount }}</span>
          </router-link>
          <router-link
            to="/advisor/activities?type=email,phone,letter,meeting"
            class="sidebar-item"
            :class="isActive('/advisor/activities') && hasCommType ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
            </div>
            Communications
          </router-link>
          <router-link
            to="/advisor/reports"
            class="sidebar-item"
            :class="isActive('/advisor/reports') ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
            </div>
            Suitability Reports
          </router-link>
        </div>

        <!-- Quick Access section -->
        <div class="px-4 mb-6">
          <div class="text-[11px] font-bold uppercase tracking-widest text-neutral-500 mb-2.5 px-3">
            Quick Access
          </div>
          <router-link
            to="/advisor/activities"
            class="sidebar-item"
            :class="isActive('/advisor/activities') && !hasCommType ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
            </div>
            Activity Log
          </router-link>
          <router-link
            to="/settings"
            class="sidebar-item"
            :class="isActive('/settings') ? 'sidebar-item-active' : ''"
          >
            <div class="sidebar-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3" />
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
              </svg>
            </div>
            Settings
          </router-link>
        </div>
      </div>

      <!-- Content area -->
      <div class="bg-eggshell-500 flex-1 p-6 overflow-y-auto">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'AdvisorLayout',

  computed: {
    ...mapGetters('auth', ['currentUser']),

    userName() {
      if (!this.currentUser) return '';
      return this.currentUser.name || `${this.currentUser.first_name || ''} ${this.currentUser.last_name || ''}`.trim();
    },

    userInitials() {
      const name = this.userName;
      if (!name) return '';
      const parts = name.split(' ').filter(Boolean);
      if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
      }
      return (parts[0] || '').substring(0, 2).toUpperCase();
    },

    clientCount() {
      return this.$store.state.advisor.clients.length;
    },

    reviewsDueCount() {
      return this.$store.state.advisor.reviewsDue.length;
    },

    hasCommType() {
      const query = this.$route.query;
      return query && query.type && query.type.includes('email');
    },
  },

  methods: {
    isActive(path, exact = false) {
      if (exact) {
        return this.$route.path === path;
      }
      return this.$route.path.startsWith(path);
    },
  },
};
</script>

<style scoped>
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 150ms ease-in-out;
  margin-bottom: 2px;
  text-decoration: none;
  @apply text-neutral-500;
}

.sidebar-item:hover {
  @apply bg-savannah-100 text-horizon-500;
}

.sidebar-item-active {
  @apply bg-raspberry-50 text-raspberry-500 font-semibold;
}

.sidebar-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sidebar-badge {
  margin-left: auto;
  @apply bg-raspberry-500 text-white text-xs font-bold px-2 py-0.5 rounded-full;
}
</style>
