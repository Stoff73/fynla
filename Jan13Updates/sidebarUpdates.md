# Sidebar Updates - January 13, 2026

## Changes Made

### Auto-Collapse Sidebar on Cash Tab
**File:** `resources/js/views/NetWorth/NetWorthDashboard.vue`

Added collapsible sidebar functionality to provide more screen space when viewing the Cash tab dashboard.

**Features:**
- Sidebar automatically collapses when navigating to the Cash tab
- Manual toggle button (chevron icon) to collapse/expand sidebar
- Collapsed state shows icons only (60px width vs 240px expanded)
- Tooltips appear on hover when collapsed to show item labels
- Smooth CSS transitions for collapse/expand animations

**Technical Details:**
- Added `sidebarCollapsed` data property (default: false)
- Added `toggleSidebar()` method for manual control
- Added watcher on `currentSection` to auto-collapse when `'cash'`
- Grid columns change from `240px 1fr` to `60px 1fr` when collapsed
- Sidebar links center icons and hide text labels when collapsed

**CSS Changes:**
- `.sidebar-collapsed` class for grid layout adjustment
- `.sidebar.collapsed` styles for icon-only display
- `.sidebar-toggle` button styling with hover states
- Collapsed link styles with centered icons and tooltips

## Commit
```
30aad0e feat: Auto-collapse sidebar on Cash tab for more screen space
```
