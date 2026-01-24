<template>
  <AppLayout>
    <div class="net-worth-dashboard" :class="{ 'with-sidebar': showSidebar, 'sidebar-collapsed': sidebarCollapsed }">
      <!-- Sidebar Navigation (hidden on overview) -->
      <aside v-if="showSidebar" class="sidebar" :class="{ 'sidebar-offset': sidebarNeedsOffset, 'collapsed': sidebarCollapsed }">
        <!-- Expand Toggle (only shown when collapsed) -->
        <button
          v-if="sidebarCollapsed"
          @click="toggleSidebar"
          class="sidebar-toggle"
          title="Expand menu"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7" />
          </svg>
        </button>
        <nav class="sidebar-nav">
          <router-link
            v-for="item in sidebarItems"
            :key="item.path"
            :to="getRoutePath(item.path)"
            class="sidebar-link"
            :class="{ active: isActive(item.path) }"
            :title="sidebarCollapsed ? item.label : ''"
            @click.native="handleSidebarClick(item.path)"
          >
            <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.iconPath" />
              <path v-if="item.iconPath2" stroke-linecap="round" stroke-linejoin="round" :d="item.iconPath2" />
            </svg>
            <span v-if="!sidebarCollapsed">{{ item.label }}</span>
          </router-link>
        </nav>
      </aside>

      <!-- Main Content -->
      <main class="main-content">
        <router-view></router-view>
      </main>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'NetWorthDashboard',

  components: {
    AppLayout,
  },

  data() {
    return {
      sidebarCollapsed: false,
      sidebarItems: [
        {
          path: 'wealth-summary',
          label: 'Wealth Summary',
          iconPath: 'M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z',
          iconPath2: 'M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z'
        },
        {
          path: 'retirement',
          label: 'Retirement',
          iconPath: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'
        },
        {
          path: 'property',
          label: 'Property',
          iconPath: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'
        },
        {
          path: 'investments',
          label: 'Investments',
          iconPath: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'
        },
        {
          path: 'cash',
          label: 'Cash',
          iconPath: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z'
        },
        {
          path: 'business',
          label: 'Business Interests',
          iconPath: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z'
        },
        {
          path: 'chattels',
          label: 'Personal Valuables',
          iconPath: 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'
        },
      ],
    };
  },

  computed: {
    ...mapState('netWorth', ['loading']),

    isPreviewMode() {
      return this.$route.path.startsWith('/preview');
    },

    basePath() {
      return this.isPreviewMode ? '/preview/net-worth' : '/net-worth';
    },

    currentSection() {
      const path = this.$route.path;
      const match = path.match(/\/net-worth\/([^/]+)/);
      return match ? match[1] : 'wealth-summary';
    },

    showSidebar() {
      // Always show sidebar
      return true;
    },

    sidebarNeedsOffset() {
      // Sections with list headers need sidebar offset to align with first card
      // Wealth Summary has no offset (no header)
      const sectionsWithHeaders = ['retirement', 'property', 'investments', 'cash', 'business', 'chattels', 'joint-history'];
      return sectionsWithHeaders.includes(this.currentSection);
    },
  },

  methods: {
    ...mapActions('netWorth', ['refreshNetWorth', 'setDetailView']),
    ...mapActions('retirement', ['resetToMainDashboard']),

    getRoutePath(path) {
      return `${this.basePath}/${path}`;
    },

    isActive(path) {
      return this.currentSection === path;
    },

    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed;
    },

    handleSidebarClick(path) {
      // Clear detail view when clicking sidebar links
      // This allows returning to list view even when already on the same route
      this.setDetailView(false);

      // Reset retirement tab to main dashboard when clicking retirement link
      if (path === 'retirement') {
        this.resetToMainDashboard();
      }
    },
  },

  watch: {
    currentSection: {
      immediate: true,
      handler(section) {
        // Auto-collapse sidebar on certain tabs for more screen space
        if (section === 'investments' || section === 'cash' || section === 'retirement' || section === 'property') {
          this.sidebarCollapsed = true;
        }
      },
    },
  },

  mounted() {
    // Redirect to wealth-summary if at root
    if (this.$route.path === '/net-worth' || this.$route.path === '/net-worth/') {
      this.$router.replace('/net-worth/wealth-summary');
    }
    if (this.$route.path === '/preview/net-worth' || this.$route.path === '/preview/net-worth/') {
      this.$router.replace('/preview/net-worth/wealth-summary');
    }
  },
};
</script>

<style scoped>
.net-worth-dashboard {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.net-worth-dashboard.with-sidebar {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  transition: grid-template-columns 0.2s ease;
}

.net-worth-dashboard.with-sidebar.sidebar-collapsed {
  grid-template-columns: 60px 1fr;
}

/* Sidebar Styles */
.sidebar {
  background: white;
  border-radius: 12px;
  padding: 16px 0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  position: sticky;
  top: 100px;
  height: fit-content;
  max-height: calc(100vh - 140px);
  overflow-y: auto;
  transition: all 0.2s ease;
}

.sidebar.collapsed {
  padding: 8px 0;
}

/* Offset sidebar to align with first card on list views */
.sidebar.sidebar-offset {
  margin-top: 56px;
}

/* Toggle button */
.sidebar-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 8px;
  margin-bottom: 8px;
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  transition: all 0.15s;
}

.sidebar-toggle:hover {
  color: #2563eb;
  background: #f3f4f6;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  color: #4b5563;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.15s;
  border-left: 3px solid transparent;
  white-space: nowrap;
  overflow: hidden;
}

/* Collapsed sidebar link styles */
.sidebar.collapsed .sidebar-link {
  justify-content: center;
  padding: 12px 8px;
  border-left: none;
  border-radius: 0;
}

.sidebar-link:hover {
  background: #f3f4f6;
  color: #1f2937;
}

.sidebar-link.active {
  background: #eff6ff;
  color: #2563eb;
  border-left-color: #2563eb;
  font-weight: 600;
}

.sidebar.collapsed .sidebar-link.active {
  border-left: none;
  border-radius: 8px;
  margin: 0 4px;
}

.sidebar-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.sidebar.collapsed .sidebar-icon {
  width: 24px;
  height: 24px;
}

/* Main Content */
.main-content {
  min-height: 500px;
  min-width: 0; /* Prevents grid item from overflowing container */
  overflow-x: auto; /* Allow horizontal scroll if needed instead of clipping */
}

/* Mobile responsive */
@media (max-width: 1024px) {
  .net-worth-dashboard.with-sidebar {
    display: block; /* Switch from grid to block for simpler mobile layout */
  }

  .net-worth-dashboard.with-sidebar.sidebar-collapsed {
    display: block;
  }

  .sidebar {
    display: none;
  }

  .main-content {
    width: 100%;
  }
}

@media (max-width: 768px) {
  .net-worth-dashboard {
    padding: 16px;
  }
}
</style>
