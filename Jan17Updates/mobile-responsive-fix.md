# Mobile/iPad Responsive Fix - Net Worth Module

**Date:** January 17, 2026
**Issue:** Page goes blank when screen size is reduced in Net Worth module

## Problem

When reducing screen width (mobile/iPad views), the Net Worth module content was being cut off on the left side, showing partial titles like "Per" and "Pen" (Pensions) with cards only partially visible.

## Root Cause

The CSS in `NetWorthDashboard.vue` used `overflow: hidden` on both the grid container and main content area. When the CSS grid layout transitioned from two columns to single column at the 1024px breakpoint, this caused content to be clipped incorrectly.

## Solution

Modified `/resources/js/views/NetWorth/NetWorthDashboard.vue`:

### 1. Removed `overflow: hidden` from grid container

```css
/* Before */
.net-worth-dashboard.with-sidebar {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  overflow: hidden;  /* REMOVED */
  transition: grid-template-columns 0.2s ease;
}

/* After */
.net-worth-dashboard.with-sidebar {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  transition: grid-template-columns 0.2s ease;
}
```

### 2. Changed main content overflow behavior

```css
/* Before */
.main-content {
  min-height: 500px;
  min-width: 0;
  overflow: hidden;
}

/* After */
.main-content {
  min-height: 500px;
  min-width: 0;
  overflow-x: auto;  /* Allow horizontal scroll if needed */
}
```

### 3. Simplified mobile layout

```css
/* Before */
@media (max-width: 1024px) {
  .net-worth-dashboard.with-sidebar {
    grid-template-columns: 1fr;
  }
  .sidebar {
    display: none;
  }
}

/* After */
@media (max-width: 1024px) {
  .net-worth-dashboard.with-sidebar {
    display: block;  /* Simpler than grid for mobile */
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
```

## Files Changed

- `resources/js/views/NetWorth/NetWorthDashboard.vue`

## Testing

1. Open Net Worth module at full desktop width
2. Gradually reduce browser window width
3. Content should remain visible at all screen sizes
4. At 1024px and below, sidebar should hide and content should take full width
