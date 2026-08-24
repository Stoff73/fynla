> **ARCHIVED**: This is the v1.1.0 design system (January 2026). The current design system is in [fynlaDesignGuide.md](fynlaDesignGuide.md).

# Fynla Design System (Legacy)

**Version:** 1.1.0
**Last Updated:** 28 January 2026
**Framework:** Vue.js 3 + Tailwind CSS

This document is the single source of truth for all design decisions in the Fynla financial planning application. Every component must adhere to these specifications to ensure visual consistency, professional quality, and user trust.

---

## Table of Contents

1. [Design Philosophy](#design-philosophy)
2. [Color System](#color-system)
3. [Typography](#typography)
4. [Spacing System](#spacing-system)
5. [Border & Radius](#border--radius)
6. [Shadows & Elevation](#shadows--elevation)
7. [Buttons](#buttons)
8. [Forms & Inputs](#forms--inputs)
9. [Cards](#cards)
10. [Profile & Information Tabs](#profile--information-tabs)
11. [Tables](#tables)
12. [Modals & Overlays](#modals--overlays)
13. [Navigation](#navigation)
14. [Badges & Tags](#badges--tags)
15. [Alerts & Notifications](#alerts--notifications)
16. [Loading States](#loading-states)
17. [Empty States](#empty-states)
18. [Error States](#error-states)
19. [Charts & Data Visualization](#charts--data-visualization)
20. [Icons](#icons)
21. [Animation & Motion](#animation--motion)
22. [Responsive Breakpoints](#responsive-breakpoints)
23. [Accessibility](#accessibility)
24. [Do's and Don'ts](#dos-and-donts)
25. [Public & Marketing Pages](#public--marketing-pages)
26. [Entrance Animations (Public Pages)](#entrance-animations-public-pages)

---

## Design Philosophy

Fynla is a **UK financial planning application** handling sensitive personal financial data. The UI must convey:

- **Trust & Transparency:** Users are sharing their finances with us - every pixel should reinforce credibility
- **Professional Clarity:** Clean, uncluttered interfaces that make complex financial data digestible
- **Calm Confidence:** Subdued, sophisticated palette that doesn't alarm - financial decisions need clear thinking
- **Modern but Timeless:** Contemporary design that won't feel dated in 2 years

### Core Principles

1. **Restraint:** Less is more. Every element must earn its place
2. **Hierarchy:** Clear visual hierarchy guides users to what matters most
3. **Consistency:** Same pattern, same appearance, every time
4. **Purposeful Motion:** Animations guide attention, never distract
5. **Accessibility First:** Meets WCAG 2.1 AA minimum

---

## Color System

### Primary Palette (Trust Blue & Deep Navy)

Our primary colors establish trust and professionalism. Blue is universally associated with reliability in financial services.

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Primary 50 | `#FFFFFF` | `primary-50` | Very light backgrounds (deprecated - use white) |
| Primary 100 | `#F1F5F9` | `primary-100` | Subtle backgrounds |
| Primary 200 | `#E2E8F0` | `primary-200` | Light borders, dividers |
| Primary 300 | `#CBD5E1` | `primary-300` | Disabled states |
| Primary 400 | `#94A3B8` | `primary-400` | Placeholder text |
| **Primary 500** | `#3B82F6` | `primary-500` | Accent blue, links |
| **Primary 600** | `#1257A0` | `primary-600` | **Main Brand Color** - buttons, active states |
| Primary 700 | `#0E3A66` | `primary-700` | Hover states on primary buttons |
| Primary 800 | `#0B2C4F` | `primary-800` | Active/pressed states |
| Primary 900 | `#051B33` | `primary-900` | Very dark text (rare) |

### Secondary Palette (Neutral Slate)

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Secondary 50 | `#FFFFFF` | `secondary-50` | Pure white |
| Secondary 100 | `#F1F5F9` | `secondary-100` | Page backgrounds |
| Secondary 200 | `#E2E8F0` | `secondary-200` | Card borders |
| Secondary 500 | `#64748B` | `secondary-500` | Secondary text |
| Secondary 600 | `#475569` | `secondary-600` | Body text |
| Secondary 700 | `#334155` | `secondary-700` | Headings |
| Secondary 800 | `#1E293B` | `secondary-800` | Strong emphasis |
| Secondary 900 | `#0F172A` | `secondary-900` | Near-black |

### Semantic Colors

These colors communicate meaning and should be used consistently.

#### Success (Solid Green)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Success 50 | `#FFFFFF` | `success-50` | - |
| Success 100 | `#F0FDF4` | `success-100` | Success background (very subtle) |
| Success 500 | `#15803D` | `success-500` | Success text, icons |
| Success 600 | `#166534` | `success-600` | Success borders, buttons |
| Success 700 | `#14532D` | `success-700` | Hover states |

#### Error (Solid Red)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Error 50 | `#FFFFFF` | `error-50` | - |
| Error 100 | `#FEF2F2` | `error-100` | Error background (very subtle) |
| Error 500 | `#EF4444` | `error-500` | Error icons |
| Error 600 | `#B91C1C` | `error-600` | Error text, borders |
| Error 700 | `#991B1B` | `error-700` | Hover states |

#### Warning (Blue)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Warning 50 | `#EFF6FF` | `blue-50` | Warning background (very subtle) |
| Warning 100 | `#DBEAFE` | `blue-100` | Warning background |
| Warning 500 | `#3B82F6` | `blue-500` | Warning icons, text |
| Warning 600 | `#2563EB` | `blue-600` | Warning borders |
| Warning 700 | `#1D4ED8` | `blue-700` | Hover states |

#### Info (Sky Blue)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Info 50 | `#FFFFFF` | `info-50` | - |
| Info 100 | `#F0F9FF` | `info-100` | Info background |
| Info 500 | `#0EA5E9` | `info-500` | Info icons |
| Info 600 | `#0284C7` | `info-600` | Info text, borders |
| Info 700 | `#0369A1` | `info-700` | Hover states |

### Chart Colors

For data visualization consistency:

| ID | Hex | Usage |
|----|-----|-------|
| Chart 1 | `#1257A0` | Trust Blue - Primary data series |
| Chart 2 | `#475569` | Slate - Secondary series |
| Chart 3 | `#15803D` | Green - Positive values |
| Chart 4 | `#60A5FA` | Blue - Neutral/caution |
| Chart 5 | `#B91C1C` | Red - Negative values |
| Chart 6 | `#7C3AED` | Purple - Alternative (charts only) |
| Chart 7 | `#3B82F6` | Blue - Tertiary |
| Chart 8 | `#0F172A` | Navy - Dark accent |

### Text Colors

| Usage | Tailwind Class | Hex |
|-------|---------------|-----|
| Primary text (headings) | `text-gray-900` | `#111827` |
| Secondary text (body) | `text-gray-700` | `#374151` |
| Tertiary text (subtle) | `text-gray-600` | `#4B5563` |
| Muted text (captions) | `text-gray-500` | `#6B7280` |
| Placeholder text | `text-gray-400` | `#9CA3AF` |
| Disabled text | `text-gray-300` | `#D1D5DB` |

### Background Colors

| Usage | Tailwind Class | Hex |
|-------|---------------|-----|
| Page background | `bg-gray-50` | `#F9FAFB` |
| Card/component background | `bg-white` | `#FFFFFF` |
| Subtle highlight | `bg-gray-100` | `#F3F4F6` |
| Modal overlay | `bg-gray-500/75` or `bg-black/50` | - |

### FORBIDDEN Colors

The following colors are **banned** from use. They create a cheap, unprofessional appearance:

- **Amber:** All amber variants are banned (`amber-*`). Use blue instead for warnings and caution states.
- **Orange:** All orange variants are banned (`orange-*`). Use blue instead for warnings and caution states.
- **Mustard Yellow:** Any yellow leaning toward brown/gold (e.g., `#C9A000`, `#DAA520`, `#FFD700`)
- **Pastel washes:** Overly desaturated colors with high lightness (any color at <20% saturation with >80% lightness)
- **Pure black:** Use `gray-900` (`#111827`) instead of `#000000`
- **Bright neons:** `#00FF00`, `#FF00FF`, `#00FFFF` etc.
- **Teal as primary:** Teal is only for risk badges and specific chart use

---

## Typography

### Font Families

```css
--font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
--font-display: 'Plus Jakarta Sans', 'Inter', sans-serif;
--font-mono: 'JetBrains Mono', 'Courier New', monospace;
```

| Usage | Font Family | Tailwind |
|-------|-------------|----------|
| Body text | Inter | `font-sans` |
| Display/Headlines | Plus Jakarta Sans | `font-display` |
| Code/numbers | JetBrains Mono | `font-mono` |

### Type Scale

| Name | Size | Line Height | Weight | Letter Spacing | Tailwind |
|------|------|-------------|--------|----------------|----------|
| Display | 3.75rem (60px) | 1.1 | 700 | -0.02em | `text-display` |
| H1 | 2.25rem (36px) | 1.2 | 700 | -0.01em | `text-h1` |
| H2 | 1.875rem (30px) | 1.3 | 600 | normal | `text-h2` |
| H3 | 1.5rem (24px) | 1.4 | 600 | normal | `text-h3` |
| H4 | 1.25rem (20px) | 1.5 | 600 | normal | `text-h4` |
| H5 | 1rem (16px) | 1.5 | 600 | normal | `text-h5` |
| Body Large | 1.125rem (18px) | 1.7 | 400 | normal | `text-body-lg` |
| Body | 1rem (16px) | 1.6 | 400 | normal | `text-body` |
| Body Small | 0.875rem (14px) | 1.5 | 400 | normal | `text-body-sm` |
| Caption | 0.75rem (12px) | 1.4 | 400 | normal | `text-caption` |

### Usage Guidelines

```html
<!-- Page title -->
<h1 class="font-display text-h1 text-gray-900">Dashboard</h1>

<!-- Section heading -->
<h2 class="font-display text-h2 text-gray-800">Net Worth Summary</h2>

<!-- Card title -->
<h3 class="text-h4 font-semibold text-gray-900">Protection Coverage</h3>

<!-- Body text -->
<p class="text-body text-gray-700">Your emergency fund covers 4.5 months...</p>

<!-- Small text / labels -->
<span class="text-body-sm text-gray-600">Last updated: Today</span>

<!-- Captions -->
<span class="text-caption text-gray-500">Figures are estimates only</span>
```

### Font Weights

| Weight | Value | Usage |
|--------|-------|-------|
| Regular | 400 | Body text |
| Medium | 500 | Labels, subtle emphasis |
| Semibold | 600 | Subheadings, buttons |
| Bold | 700 | Headlines, strong emphasis |

---

## Spacing System

Fynla uses Tailwind's default 4px base unit. All spacing should use these values.

| Tailwind | Pixels | Rem | Usage |
|----------|--------|-----|-------|
| `0.5` | 2px | 0.125rem | Micro spacing |
| `1` | 4px | 0.25rem | Tight spacing |
| `1.5` | 6px | 0.375rem | Small gap |
| `2` | 8px | 0.5rem | Default small |
| `3` | 12px | 0.75rem | Medium small |
| `4` | 16px | 1rem | **Standard** |
| `5` | 20px | 1.25rem | Medium |
| `6` | 24px | 1.5rem | **Section padding** |
| `8` | 32px | 2rem | Large |
| `10` | 40px | 2.5rem | Extra large |
| `12` | 48px | 3rem | Very large |
| `16` | 64px | 4rem | Huge |

### Component Spacing Patterns

| Component | Padding | Gap |
|-----------|---------|-----|
| Card | `p-6` (24px) | - |
| Modal body | `p-6` (24px) | - |
| Modal header | `px-6 py-4` | - |
| Button | `px-4 py-2` | - |
| Input | `px-4 py-2` | - |
| Form group | `mb-4` | - |
| Section | `space-y-6` | - |
| Card grid | - | `gap-6` |
| Button group | - | `gap-3` |

---

## Border & Radius

### Border Radius Scale

| Name | Value | Tailwind | Usage |
|------|-------|----------|-------|
| None | 0 | `rounded-none` | Tables |
| Small | 4px | `rounded` | Badges, chips |
| Default | 6px | `rounded-md` | Inputs, small elements |
| Button | 8px | `rounded-button` | Buttons |
| Card | 12px | `rounded-card` | Cards, modals |
| Large | 16px | `rounded-lg` | Large containers |
| XL | 24px | `rounded-2xl` | Feature cards |
| Full | 9999px | `rounded-full` | Avatars, pills |

### Border Widths

| Value | Tailwind | Usage |
|-------|----------|-------|
| 1px | `border` | Standard borders |
| 2px | `border-2` | Emphasis borders, badge outlines |
| 4px | `border-l-4` | Accent borders, side indicators |

### Border Colors

| Usage | Tailwind |
|-------|----------|
| Default | `border-gray-200` |
| Hover | `border-gray-300` or `border-primary-400` |
| Focus | `border-primary-600` |
| Error | `border-error-500` |
| Success | `border-success-500` |

---

## Shadows & Elevation

### Shadow Scale

| Name | CSS | Tailwind | Usage |
|------|-----|----------|-------|
| Subtle | `0 1px 2px rgba(0,0,0,0.04)` | `shadow-sm` | Hover hints |
| Card | `0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)` | `shadow-card` | Cards at rest |
| Card Hover | `0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)` | `shadow-card-hover` | Cards on hover |
| Medium | `0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)` | `shadow-md` | Dropdowns |
| Large | `0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)` | `shadow-lg` | Modals |
| Modal | `0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)` | `shadow-modal` | Modal containers |
| XL | `0 25px 50px -12px rgba(0,0,0,0.25)` | `shadow-xl` | Feature highlights |

### Elevation Guidelines

| Level | Shadow | Use Case |
|-------|--------|----------|
| 0 | None | Background content |
| 1 | `shadow-sm` | Subtle elements |
| 2 | `shadow-card` | Cards, panels |
| 3 | `shadow-md` | Dropdowns, popovers |
| 4 | `shadow-lg` | Drawers, dialogs |
| 5 | `shadow-modal` | Modals |

---

## Buttons

### Primary Button

The main call-to-action button.

```html
<button class="px-4 py-2 bg-primary-600 text-white rounded-button font-medium
               hover:bg-primary-700 active:bg-primary-800
               transition-all duration-150 shadow-sm hover:shadow-md
               disabled:opacity-50 disabled:cursor-not-allowed">
  Save Changes
</button>
```

| State | Background | Text | Border | Shadow |
|-------|------------|------|--------|--------|
| Default | `bg-primary-600` | `text-white` | none | `shadow-sm` |
| Hover | `bg-primary-700` | `text-white` | none | `shadow-md` |
| Active | `bg-primary-800` | `text-white` | none | `shadow-sm` |
| Disabled | `bg-primary-600 opacity-50` | `text-white` | none | none |

### Secondary Button

For secondary actions.

```html
<button class="px-4 py-2 bg-white text-gray-700 border border-gray-300
               rounded-button font-medium hover:bg-gray-50 active:bg-gray-100
               transition-all duration-150 shadow-sm hover:shadow-md">
  Cancel
</button>
```

### Outline Button

For tertiary actions.

```html
<button class="px-4 py-2 bg-transparent text-primary-600 border border-primary-600
               rounded-button font-medium hover:bg-primary-50 active:bg-primary-100
               transition-all duration-150">
  Learn More
</button>
```

### Danger Button

For destructive actions.

```html
<button class="px-4 py-2 bg-error-600 text-white rounded-button font-medium
               hover:bg-error-700 active:bg-error-800
               transition-all duration-150 shadow-sm hover:shadow-md">
  Delete
</button>
```

### Button Sizes

| Size | Padding | Font Size | Tailwind |
|------|---------|-----------|----------|
| Small | `px-3 py-1` | `text-xs` | `btn-sm` |
| Default | `px-4 py-2` | `text-sm` (14px) | default |
| Large | `px-6 py-3` | `text-base` (16px) | `btn-lg` |

### Button States

All buttons must implement:
- **Hover:** Darker background, lift shadow
- **Active/Pressed:** Even darker, pressed effect
- **Disabled:** 50% opacity, `cursor-not-allowed`
- **Loading:** Show spinner, disable interaction

---

## Forms & Inputs

### Standard Input

```html
<div class="form-group">
  <label for="email" class="block text-body-sm font-medium text-gray-700 mb-1.5">
    Email Address
  </label>
  <input
    id="email"
    type="email"
    class="w-full px-4 py-2 bg-white border border-gray-300 rounded-button
           text-gray-900 placeholder-gray-400
           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-opacity-20
           focus:border-primary-600 transition-all duration-150"
    placeholder="Enter your email"
  />
  <p class="text-caption text-gray-500 mt-1">We'll never share your email.</p>
</div>
```

### Input States

| State | Border | Ring | Background |
|-------|--------|------|------------|
| Default | `border-gray-300` | none | `bg-white` |
| Focus | `border-primary-600` | `ring-2 ring-primary-500/20` | `bg-white` |
| Error | `border-error-500` | `ring-2 ring-error-500/20` | `bg-error-50` |
| Disabled | `border-gray-200` | none | `bg-gray-100` |

### Select Dropdown

```html
<select class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm
               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
  <option value="">Select an option...</option>
  <option value="1">Option 1</option>
</select>
```

### Checkbox

```html
<div class="flex items-center">
  <input
    id="remember"
    type="checkbox"
    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
  />
  <label for="remember" class="ml-2 block text-sm text-gray-700">
    Remember me
  </label>
</div>
```

### Form Layout

- **Vertical spacing:** `mb-4` between form groups
- **Label:** Always above input, `mb-1.5` gap
- **Help text:** Below input, `mt-1` gap, `text-caption text-gray-500`
- **Error message:** Below input, `mt-1` gap, `text-caption text-error-600`

---

## Cards

### Standard Card

```html
<div class="bg-white rounded-card border border-gray-200 shadow-sm p-6
            transition-all duration-200">
  <h3 class="text-h4 font-semibold text-gray-900 mb-2">Card Title</h3>
  <p class="text-body-sm text-gray-600">Card content goes here...</p>
</div>
```

### Clickable Card

```html
<div class="bg-white rounded-card border border-gray-200 shadow-sm p-6
            hover:shadow-md hover:-translate-y-0.5 hover:border-primary-400
            transition-all duration-200 cursor-pointer">
  <!-- Card content -->
</div>
```

### Card Variants

| Variant | Border | Background | Hover |
|---------|--------|------------|-------|
| Default | `border-gray-200` | `bg-white` | none |
| Clickable | `border-gray-200` | `bg-white` | `border-primary-400`, lift |
| Highlighted | `border-primary-200` | `bg-primary-50` | - |
| Warning | `border-blue-200` | `bg-blue-50` | - |
| Success | `border-green-200` | `bg-green-50` | - |
| Error | `border-red-200` | `bg-red-50` | - |

### Card Anatomy

```
+----------------------------------+
|  Card Header (optional)          |
|  px-6 py-4 border-b              |
+----------------------------------+
|                                  |
|  Card Body                       |
|  p-6                             |
|                                  |
+----------------------------------+
|  Card Footer (optional)          |
|  px-6 py-4 border-t bg-gray-50   |
+----------------------------------+
```

### Account Cards (Pensions & Investments)

Account cards are used in the Pension Dashboard and Investment Dashboard to display individual accounts. They follow a consistent structure:

```html
<div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer">
  <!-- Header with badges -->
  <div class="flex items-start justify-between mb-3">
    <!-- Type Badge (left) -->
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                 bg-green-100 text-green-800">
      Stocks & Shares ISA
    </span>
    <!-- Optional secondary badge (right) -->
    <span class="text-xs font-medium text-blue-600">Retirement</span>
  </div>

  <!-- Account Name -->
  <h4 class="text-base font-semibold text-gray-900 mb-3">Vanguard</h4>

  <!-- Key-Value Details -->
  <div class="space-y-1">
    <div class="flex justify-between">
      <span class="text-sm text-gray-500">Current Value</span>
      <span class="text-sm font-medium text-gray-900">£175,000</span>
    </div>
    <div class="flex justify-between">
      <span class="text-sm text-gray-500">ISA Used (YTD)</span>
      <span class="text-sm font-medium text-gray-900">£0</span>
    </div>
  </div>
</div>
```

#### Account Type Badge Colors

| Account Type | Background | Text |
|--------------|------------|------|
| Stocks & Shares ISA | `bg-green-100` | `text-green-800` |
| Cash ISA | `bg-emerald-100` | `text-emerald-800` |
| GIA | `bg-gray-100` | `text-gray-700` |
| Onshore Bond | `bg-green-100` | `text-green-800` |
| Offshore Bond | `bg-green-100` | `text-green-800` |
| SIPP | `bg-blue-100` | `text-blue-800` |
| Workplace Pension | `bg-blue-100` | `text-blue-800` |
| DB Pension | `bg-indigo-100` | `text-indigo-800` |

#### Account Card with Toggle

When an account card has an include/exclude toggle (e.g., in Retirement Income Planner), the toggle appears at the end of the card without colored background styling:

```html
<div class="bg-white rounded-lg border border-gray-200 p-4">
  <!-- Badge and Name -->
  <div class="flex items-start justify-between mb-2">
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                 bg-green-100 text-green-800">ISA</span>
  </div>
  <h4 class="text-base font-semibold text-gray-900">Vanguard</h4>

  <!-- Value and Toggle Row -->
  <div class="flex items-center justify-between mt-3">
    <div>
      <p class="text-lg font-bold text-gray-900">£521,730</p>
      <p class="text-xs text-gray-500">Annual draw: £55,391</p>
    </div>
    <!-- Simple Toggle (no colored background) -->
    <button class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium
                   bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
      <span>Included</span>
      <span class="w-8 h-4 bg-gray-300 rounded-full relative">
        <span class="absolute w-3 h-3 bg-white rounded-full top-0.5 right-0.5"></span>
      </span>
    </button>
  </div>
</div>
```

**Important:** Toggle buttons in account cards should use neutral gray styling, not teal or other accent colors. The active/included state uses the same gray styling to maintain visual consistency.

---

## Profile & Information Tabs

This section defines the design patterns for User Profile, Valuable Info, and similar tabbed information displays. These patterns ensure consistency when displaying and editing user data.

### View Mode Layout

Profile tabs use a **clean two-column layout** in view mode with labels on the left and values right-aligned. This creates a professional, scannable format.

#### Card Wrapper Structure

All profile tab content is wrapped in a card with a header row:

```html
<div class="bg-white rounded-lg border border-gray-200 p-6">
  <div class="flex justify-between items-start mb-6">
    <div>
      <h3 class="text-h4 font-semibold text-gray-900">Section Title</h3>
      <p class="mt-1 text-body-sm text-gray-600">
        Section description text
      </p>
    </div>
    <button class="btn-secondary">
      Edit
    </button>
  </div>

  <!-- Content goes here -->
</div>
```

#### Two-Column View Mode

For displaying data in view mode, use a two-column grid with label-value rows:

```html
<div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
  <!-- Left Column -->
  <div class="space-y-8">
    <div>
      <h3 class="text-body-base font-semibold text-gray-900 mb-4">Section Name</h3>
      <div class="space-y-3">
        <div class="flex justify-between">
          <span class="text-body-sm text-gray-600">Label:</span>
          <span class="text-body-sm text-gray-900 text-right">Value</span>
        </div>
        <div class="flex justify-between">
          <span class="text-body-sm text-gray-600">Another Label:</span>
          <span class="text-body-sm text-gray-900 text-right">Another Value</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column -->
  <div class="space-y-8">
    <!-- Similar structure -->
  </div>
</div>
```

#### Label-Value Row Specifications

| Element | Class | Notes |
|---------|-------|-------|
| Row container | `flex justify-between` | Spreads label and value |
| Label | `text-body-sm text-gray-600` | Left-aligned, muted |
| Value | `text-body-sm text-gray-900 text-right` | Right-aligned, emphasis |
| Missing value | Display `—` (em dash) | Never leave blank |
| Section header | `text-body-base font-semibold text-gray-900 mb-4` | Bold, above rows |
| Row spacing | `space-y-3` | 12px between rows |
| Section spacing | `space-y-8` | 32px between sections |
| Column gap | `gap-x-12 gap-y-8` | 48px horizontal, 32px vertical |

### Edit Mode Layout

When user clicks "Edit", the view mode is replaced with form inputs:

```html
<!-- EDIT MODE -->
<div v-else class="bg-white rounded-lg border border-gray-200 p-6">
  <h3 class="text-h4 font-semibold text-gray-900 mb-6">Edit Section Title</h3>

  <div class="space-y-6">
    <!-- Form sections with border-t dividers -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
      <!-- Form fields -->
    </div>

    <!-- Additional sections -->
    <div class="border-t border-gray-200 pt-6">
      <h3 class="text-h5 font-semibold text-gray-900 mb-4">Subsection</h3>
      <!-- More fields -->
    </div>

    <!-- Action Buttons -->
    <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
      <button class="btn-secondary" @click="handleCancel">
        Cancel
      </button>
      <button class="btn-primary" type="submit">
        Save Changes
      </button>
    </div>
  </div>
</div>
```

### Button Patterns

| Button | Style | Location | Action |
|--------|-------|----------|--------|
| Edit | `btn-secondary` | Header row, right side | Toggles to edit mode |
| Add | `btn-secondary` | Header row, right side | Opens add modal |
| Cancel | `btn-secondary` | Footer, left of Save | Reverts and exits edit mode |
| Save Changes | `btn-primary` | Footer, rightmost | Submits form |

### Complete Tab Pattern

```vue
<template>
  <div class="space-y-6">
    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4">
      <p class="text-body-sm font-medium text-success-800">{{ successMessage }}</p>
    </div>

    <div v-if="errorMessage" class="rounded-md bg-error-50 p-4">
      <h3 class="text-body-sm font-medium text-error-800">Error</h3>
      <p class="mt-2 text-body-sm text-error-700">{{ errorMessage }}</p>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- VIEW MODE -->
      <div v-if="!isEditing" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h3 class="text-h4 font-semibold text-gray-900">Section Title</h3>
            <p class="mt-1 text-body-sm text-gray-600">Description</p>
          </div>
          <button type="button" @click="isEditing = true" class="btn-secondary">
            Edit
          </button>
        </div>

        <!-- Two-column label-value layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-6">
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-body-sm text-gray-600">Field:</span>
              <span class="text-body-sm text-gray-900 text-right">{{ value || '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- EDIT MODE -->
      <div v-else class="bg-white rounded-lg border border-gray-200 p-6">
        <h3 class="text-h4 font-semibold text-gray-900 mb-6">Edit Section Title</h3>

        <div class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <!-- Form fields with input-field class -->
          </div>

          <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <button type="button" @click="handleCancel" class="btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn-primary">
              Save Changes
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>
```

### Value Formatting

| Data Type | Format | Example |
|-----------|--------|---------|
| Currency | `formatCurrency()` | £45,000 |
| Date | `toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })` | 15 January 2024 |
| Percentage | Number + `%` | 50% |
| Status/Enum | Title case | Employed |
| Boolean | Yes/No | Yes |
| Empty | Em dash | — |
| Uppercase fields | Add `uppercase` class | AB123456C |
| Capitalised fields | Add `capitalize` class | Male |

### Info Boxes

For contextual information within profile sections:

```html
<!-- Info box (blue) -->
<div class="mt-4 p-3 bg-blue-50 rounded-lg">
  <p class="text-body-xs text-blue-700">
    Informational text explaining something to the user.
  </p>
</div>

<!-- Warning box (blue) -->
<div class="mt-4 p-3 bg-blue-50 rounded-lg">
  <p class="text-body-xs text-blue-700">
    Warning or important notice for the user.
  </p>
</div>
```

### Checklist for Profile/Info Tabs

When creating new profile or information tabs:

- [ ] Use `space-y-6` wrapper for overall tab content
- [ ] Place success/error messages at top, outside the card
- [ ] Wrap content in `bg-white rounded-lg border border-gray-200 p-6`
- [ ] Header row with title, description, and `btn-secondary` Edit/Add button
- [ ] View mode uses two-column grid with label-value rows
- [ ] Labels are `text-body-sm text-gray-600`
- [ ] Values are `text-body-sm text-gray-900 text-right`
- [ ] Use `—` (em dash) for empty values
- [ ] Edit mode shows form inputs with `input-field` class
- [ ] Form sections divided by `border-t border-gray-200 pt-6`
- [ ] Action buttons at bottom: Cancel (secondary) and Save (primary)
- [ ] Cancel button reverts form and exits edit mode
- [ ] Save button submits and shows success message

---

## Tables

### Standard Table

```html
<div class="overflow-x-auto border border-gray-200 rounded-lg">
  <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
      <tr>
        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500
                                uppercase tracking-wider">
          Name
        </th>
      </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-3 text-sm text-gray-900">Value</td>
      </tr>
    </tbody>
    <tfoot class="bg-gray-100 font-semibold">
      <tr>
        <td class="px-4 py-3 text-sm text-gray-900">Total</td>
      </tr>
    </tfoot>
  </table>
</div>
```

### Table Specifications

| Part | Background | Text | Border |
|------|------------|------|--------|
| Header | `bg-gray-50` | `text-gray-500` (uppercase, xs) | bottom divider |
| Body row | `bg-white` | `text-gray-900` | `divide-y divide-gray-200` |
| Row hover | `bg-gray-50` | - | - |
| Footer | `bg-gray-100` | `text-gray-900` (semibold) | top border |

### Sortable Headers

```html
<th class="cursor-pointer hover:bg-gray-100" @click="sortBy('name')">
  Name
  <span v-if="sortField === 'name'" class="ml-1">
    {{ sortDirection === 'asc' ? '\u2191' : '\u2193' }}
  </span>
</th>
```

---

## Modals & Overlays

### Modal Structure

```html
<div class="fixed inset-0 z-50 overflow-y-auto">
  <!-- Backdrop -->
  <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

  <!-- Modal container -->
  <div class="flex items-center justify-center min-h-screen p-4">
    <!-- Modal panel -->
    <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Modal Title</h3>
        <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
          <!-- Close icon -->
        </button>
      </div>

      <!-- Body -->
      <div class="p-6">
        <!-- Content -->
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
        <button class="btn-secondary">Cancel</button>
        <button class="btn-primary">Confirm</button>
      </div>
    </div>
  </div>
</div>
```

### Modal Sizes

| Size | Max Width | Use Case |
|------|-----------|----------|
| Small | `max-w-md` (448px) | Confirmations, simple forms |
| Medium | `max-w-lg` (512px) | Standard forms |
| Large | `max-w-2xl` (672px) | Complex forms, wizards |
| XL | `max-w-4xl` (896px) | Data-heavy content |

### Overlay/Backdrop

```css
/* Standard overlay */
background: rgba(107, 114, 128, 0.75); /* gray-500/75 */

/* Dark overlay (for focus) */
background: rgba(0, 0, 0, 0.50); /* black/50 */
backdrop-filter: blur(4px); /* optional glassmorphism */
```

---

## Navigation

### Top Navbar

```html
<nav class="bg-gray-50 shadow-sm border-b border-gray-200" style="background-color: #F9FAFB;">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16">
      <!-- Logo and primary nav -->
      <!-- User menu -->
    </div>
  </div>
</nav>
```

### Nav Link States

| State | Text Color | Border |
|-------|------------|--------|
| Default | `text-gray-500` | `border-transparent` |
| Hover | `text-gray-700` | `border-gray-300` |
| Active | `text-gray-900` | `border-primary-600` |

### Dropdown Menu

```html
<div class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white
            ring-1 ring-black ring-opacity-5 z-50">
  <div class="py-1">
    <a href="#" class="flex items-center px-4 py-2 text-body-sm text-gray-700
                       hover:bg-gray-100">
      Menu Item
    </a>
  </div>
</div>
```

---

## Badges & Tags

### Status Badges (Solid Background)

For high-visibility status indicators:

```html
<!-- Active -->
<span class="badge bg-green-600 text-white">Active</span>

<!-- Pending -->
<span class="badge bg-blue-500 text-white">Pending</span>

<!-- Completed -->
<span class="badge bg-blue-600 text-white">Completed</span>

<!-- Expired -->
<span class="badge bg-red-600 text-white">Expired</span>

<!-- Draft -->
<span class="badge bg-gray-500 text-white">Draft</span>
```

### Account Type Badges (Outlined)

For account/wrapper types:

```html
<!-- ISA -->
<span class="badge bg-white text-blue-700 border-2 border-blue-500">ISA</span>

<!-- SIPP -->
<span class="badge bg-white text-purple-700 border-2 border-purple-500">SIPP</span>

<!-- GIA -->
<span class="badge bg-white text-gray-700 border-2 border-gray-400">GIA</span>

<!-- Pension -->
<span class="badge bg-white text-indigo-700 border-2 border-indigo-500">Pension</span>
```

### Risk Level Badges

**IMPORTANT:** Risk levels use a specific color system that is distinct from semantic colors. These colors must be used consistently across all risk-related components.

| Level | Background | Text | Tailwind Classes |
|-------|------------|------|------------------|
| Low | `bg-yellow-100` | `text-yellow-800` | `bg-yellow-100 text-yellow-800` |
| Lower-Medium | `bg-pink-100` | `text-pink-800` | `bg-pink-100 text-pink-800` |
| Medium | `bg-green-100` | `text-green-800` | `bg-green-100 text-green-800` |
| Upper-Medium | `bg-teal-100` | `text-teal-800` | `bg-teal-100 text-teal-800` |
| High | `bg-blue-100` | `text-blue-800` | `bg-blue-100 text-blue-800` |

### Investment Asset Type Colors (Risk Context)

These colors align with the risk level system when showing asset type risk classifications:

| Asset Type | Risk Level | Color |
|------------|------------|-------|
| Cash & Cash Equivalents | Low | Yellow (`bg-yellow-100 text-yellow-800`) |
| Bonds (Fixed Income) | Lower-Medium | Pink (`bg-pink-100 text-pink-800`) |
| Commercial Property | Medium | Green (`bg-green-100 text-green-800`) |
| Equities (Shares) | Medium-High | Teal (`bg-teal-100 text-teal-800`) |
| Alternative Investments | High | Blue (`bg-blue-100 text-blue-800`) |

**Note:** Risk colors are defined centrally in `resources/js/constants/designSystem.js` and should be imported from there rather than hardcoded in components.

### Badge Base Styles

```css
.badge {
  @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
}
```

### Badge Sizes

| Size | Padding | Font |
|------|---------|------|
| Small | `px-2 py-0.5` | `text-xs` |
| Medium | `px-3 py-1` | `text-sm` |
| Large | `px-4 py-1.5` | `text-base` |

---

## Alerts & Notifications

### Alert Structure

```html
<div class="flex items-start p-3 rounded-lg border bg-[severity]-50 border-[severity]-300">
  <div class="flex-shrink-0 mr-3">
    <!-- Icon -->
  </div>
  <div class="flex-grow">
    <p class="text-sm font-semibold text-[severity]-800">Alert Title</p>
    <p class="text-sm text-gray-600 mt-1">Alert description...</p>
  </div>
  <button class="ml-3 text-gray-400 hover:text-gray-600">
    <!-- Dismiss icon -->
  </button>
</div>
```

### Alert Severity Colors

| Severity | Background | Border | Icon/Title Color |
|----------|------------|--------|------------------|
| Critical | `bg-red-50` | `border-red-300` | `text-red-600/800` |
| Important | `bg-blue-50` | `border-blue-300` | `text-blue-600/800` |
| Info | `bg-blue-50` | `border-blue-300` | `text-blue-600/800` |
| Success | `bg-green-50` | `border-green-300` | `text-green-600/800` |

---

## Loading States

### Spinner

```html
<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
```

### Spinner Sizes

| Size | Dimensions | Border Width |
|------|------------|--------------|
| Small | `h-4 w-4` | `border-2` |
| Medium | `h-8 w-8` | `border-2` |
| Large | `h-12 w-12` | `border-2` |

### Loading Overlay

```html
<div class="absolute inset-0 bg-white/80 flex items-center justify-center">
  <div class="flex flex-col items-center">
    <div class="w-10 h-10 border-4 border-primary-200 border-t-primary-600
                rounded-full animate-spin"></div>
    <p class="mt-3 text-sm text-gray-600">Loading...</p>
  </div>
</div>
```

### Progress Bar

```html
<div class="w-full bg-gray-200 rounded-full h-2">
  <div class="bg-primary-600 h-2 rounded-full transition-all duration-500"
       :style="{ width: progress + '%' }"></div>
</div>
```

### Progress Bar Colors by Context

| Context | Bar Color |
|---------|-----------|
| Default | `bg-primary-600` |
| Success (>=80%) | `bg-green-500` |
| Warning (50-79%) | `bg-blue-500` |
| Danger (<50%) | `bg-red-500` |

---

## Empty States

### Structure

```html
<div class="text-center py-12 bg-white border border-gray-200 rounded-lg">
  <svg class="mx-auto h-12 w-12 text-gray-400 mb-4">
    <!-- Relevant icon -->
  </svg>
  <h3 class="text-lg font-medium text-gray-900 mb-2">No items found</h3>
  <p class="text-gray-500 mb-4">Get started by creating your first item.</p>
  <button class="btn-primary">
    + Add Item
  </button>
</div>
```

### Guidelines

- **Icon:** 48px (h-12 w-12), `text-gray-400`
- **Heading:** `text-lg font-medium text-gray-900`
- **Description:** `text-gray-500`, max 2 lines
- **CTA:** Primary button when actionable

---

## Error States

### Inline Field Error

```html
<div class="form-group">
  <label class="label">Email</label>
  <input class="input-field border-error-500 focus:ring-error-500" />
  <p class="text-caption text-error-600 mt-1">Please enter a valid email</p>
</div>
```

### Form Error Banner

```html
<div class="rounded-md bg-error-50 p-4 mb-6">
  <div class="flex">
    <svg class="h-5 w-5 text-error-500" />
    <div class="ml-3">
      <h3 class="text-body-sm font-medium text-error-800">
        There were errors with your submission
      </h3>
      <ul class="mt-2 text-body-sm text-error-700 list-disc list-inside">
        <li>Error item 1</li>
        <li>Error item 2</li>
      </ul>
    </div>
  </div>
</div>
```

---

## Charts & Data Visualization

### Chart Configuration

All charts use ApexCharts with consistent styling:

```javascript
const chartDefaults = {
  chart: {
    fontFamily: 'Inter, system-ui, sans-serif',
  },
  colors: [
    '#1257A0', // chart-1: Trust Blue
    '#475569', // chart-2: Slate
    '#15803D', // chart-3: Green
    '#60A5FA', // chart-4: Blue
    '#B91C1C', // chart-5: Red
    '#7C3AED', // chart-6: Purple
    '#3B82F6', // chart-7: Blue
    '#0F172A', // chart-8: Navy
  ],
  dataLabels: {
    style: {
      fontSize: '12px',
      fontWeight: 600,
    },
  },
  legend: {
    fontSize: '14px',
    fontFamily: 'Inter, system-ui, sans-serif',
  },
  tooltip: {
    style: {
      fontSize: '14px',
      fontFamily: 'Inter, system-ui, sans-serif',
    },
  },
};
```

### Gauge Charts

For metrics like emergency fund runway:

- Use radial bar chart
- Start angle: -135, End angle: 135
- Color by threshold:
  - Green (`#10B981`): >= 80% or >= 6 months
  - Blue (`#3B82F6`): 60-79% or 3-5 months
  - Red (`#EF4444`): < 60% or < 3 months
- **Note:** Gauge charts must NOT display numerical scores (e.g. "75/100"). Use descriptive metrics instead (e.g. "6 months", currency values).

### Color Meaning in Charts

| Color | Meaning |
|-------|---------|
| Green | Positive, growth, success |
| Red | Negative, loss, decline |
| Blue | Neutral, primary data |
| Blue (light) | Warning, needs attention |
| Gray | Baseline, comparison |

---

## Icons

### Icon Sizes

| Size | Class | Use Case |
|------|-------|----------|
| XS | `w-3 h-3` | Inline with text |
| SM | `w-4 h-4` | Buttons, badges |
| MD | `w-5 h-5` | Standard icons |
| LG | `w-6 h-6` | Card headers |
| XL | `w-8 h-8` | Empty states |
| 2XL | `w-12 h-12` | Feature highlights |

### Icon Style

Use Heroicons (outline style preferred for UI, solid for status indicators):

```html
<!-- Outline icon (UI elements) -->
<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="..." />
</svg>

<!-- Solid icon (status indicators) -->
<svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
  <path fill-rule="evenodd" d="..." clip-rule="evenodd" />
</svg>
```

### Icon Colors

| Context | Color Class |
|---------|-------------|
| Default | `text-gray-400` |
| Hover | `text-gray-600` |
| Active | `text-primary-600` |
| Success | `text-green-600` |
| Warning | `text-blue-600` |
| Error | `text-red-600` |

---

## Animation & Motion

### Duration Scale

| Name | Duration | Use Case |
|------|----------|----------|
| Fast | `150ms` | Button hovers, micro-interactions |
| Default | `200ms` | Standard transitions |
| Slow | `300ms` | Panel slides, modals |
| Slower | `500ms` | Page transitions, charts |

### Easing Functions

| Name | Value | Use Case |
|------|-------|----------|
| Ease Out | `cubic-bezier(0.4, 0, 0.2, 1)` | Most transitions |
| Ease In | `cubic-bezier(0.4, 0, 1, 1)` | Exit animations |
| Bounce | `cubic-bezier(0.34, 1.56, 0.64, 1)` | Entrance animations |

### Common Animations

```css
/* Button hover */
transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);

/* Card hover lift */
transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
transform: translateY(-2px);

/* Modal fade in */
transition: opacity 0.3s ease-out;

/* Slide panel */
transition: transform 0.3s ease-out;

/* Progress bar fill */
transition: width 0.5s ease-out;

/* Spinner */
animation: spin 1s linear infinite;
```

### Vue Transitions

```html
<Transition
  enter-active-class="transition ease-out duration-300"
  enter-from-class="opacity-0 translate-y-4"
  enter-to-class="opacity-100 translate-y-0"
  leave-active-class="transition ease-in duration-200"
  leave-from-class="opacity-100"
  leave-to-class="opacity-0"
>
  <!-- Content -->
</Transition>
```

---

## Responsive Breakpoints

Fynla uses Tailwind's default breakpoints:

| Breakpoint | Min Width | CSS | Common Devices |
|------------|-----------|-----|----------------|
| `sm` | 640px | `@media (min-width: 640px)` | Large phones landscape |
| `md` | 768px | `@media (min-width: 768px)` | Tablets portrait |
| `lg` | 1024px | `@media (min-width: 1024px)` | Tablets landscape, small laptops |
| `xl` | 1280px | `@media (min-width: 1280px)` | Laptops, desktops |
| `2xl` | 1536px | `@media (min-width: 1536px)` | Large desktops |

### Content Width

```html
<!-- Main content container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
  <!-- Content -->
</div>
```

### Grid Patterns

```html
<!-- 2-column on tablet, 3-column on desktop -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  <!-- Cards -->
</div>

<!-- Sidebar layout -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
  <div class="lg:col-span-3"><!-- Main content --></div>
  <div class="lg:col-span-1"><!-- Sidebar --></div>
</div>
```

---

## Accessibility

### Color Contrast

All text must meet WCAG 2.1 AA minimum:
- **Normal text:** 4.5:1 contrast ratio
- **Large text (18px+ or 14px bold):** 3:1 contrast ratio

### Focus Indicators

All interactive elements must have visible focus:

```css
/* Standard focus ring */
focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-opacity-20 focus:border-primary-600

/* High-visibility focus (keyboard navigation) */
focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2
```

### Reduced Motion

Respect user preferences:

```css
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

### Screen Reader Support

- Use semantic HTML (`<nav>`, `<main>`, `<section>`, `<article>`)
- Add `aria-label` to icon-only buttons
- Use `sr-only` class for screen reader text
- Ensure all forms have associated labels

---

## Do's and Don'ts

### No Scores

Numerical scores (e.g. "75/100", adequacy scores, diversification scores, portfolio health scores) are **banned** from all user-facing UI. They oversimplify complex financial positions and can mislead users.

| Do | Don't |
|----|-------|
| Show specific metrics (currency, percentages, time periods) | Display "75/100" score badges |
| Use descriptive text ("coverage is strong") | Show "Adequacy Score: 82" |
| Present actionable guidance | Reduce complex positions to a single number |

### Colors

| Do | Don't |
|----|-------|
| Use `primary-600` for main actions | Use bright/neon colors |
| Use semantic colors for meaning | Use color as the only indicator |
| Keep to the defined palette | Introduce new colors without approval |
| Use gray-900 for text | Use pure black (#000000) |

### Typography

| Do | Don't |
|----|-------|
| Use Inter for body text | Mix multiple body fonts |
| Follow the type scale | Use arbitrary font sizes |
| Maintain hierarchy (h1 > h2 > h3) | Skip heading levels |
| Use semibold/bold sparingly | Make everything bold |

### Spacing

| Do | Don't |
|----|-------|
| Use consistent spacing (4px base) | Use arbitrary pixel values |
| Give content room to breathe | Cram elements together |
| Use `gap` for flex/grid layouts | Use margins on individual items |

### Components

| Do | Don't |
|----|-------|
| Use the card component for containers | Create new box styles |
| Follow button state patterns | Invent new button styles |
| Use established badge colors | Create new badge color schemes |
| Include loading/empty/error states | Leave states undefined |

### Interaction

| Do | Don't |
|----|-------|
| Provide visual feedback on hover | Use instant show/hide |
| Use subtle animations | Create distracting animations |
| Show loading states during async | Leave users guessing |
| Use 200-300ms transitions | Use very fast (<100ms) or slow (>500ms) |

---

## Component Checklist

When creating new components, ensure:

- [ ] Colors are from the defined palette
- [ ] Typography follows the type scale
- [ ] Spacing uses standard values
- [ ] Border radius is consistent
- [ ] Has hover state (if interactive)
- [ ] Has focus state (if interactive)
- [ ] Has disabled state (if applicable)
- [ ] Has loading state (if async)
- [ ] Has empty state (if displays data)
- [ ] Has error state (if can fail)
- [ ] Animations are 200-300ms
- [ ] Meets accessibility requirements
- [ ] Works on mobile (responsive)

---

## Goals Projection Bar Chart

The Goals Projection Chart (`GoalsProjectionChart.vue`) is a specialised component with unique design patterns that extend the standard chart configuration. It supports three distinct views with event icon overlays.

### Chart Views & Color Schemes

| View | Series | Colors | Bar Width | Border Radius | Stacked |
|------|--------|--------|-----------|---------------|---------|
| Net Worth | 1 (Net Worth) | `#A8B8D8` (muted periwinkle) | `50%` | `3px` (end only) | No |
| Cash Flow | 2 (Income, Expenditure) | `SUCCESS_COLORS[500]`, `ERROR_COLORS[500]` | `70%` | `2px` | No |
| Asset Breakdown | 4 (Pensions, Property, Investments, Cash) | `#1257A0`, `#15803D`, `#475569`, `#60A5FA` | `80%` | `2px` | Yes |

### Asset Breakdown Series Colors

| Series | Hex | Source |
|--------|-----|--------|
| Pensions | `#1257A0` | Trust Blue (Chart 1) |
| Property | `#15803D` | Green (Chart 3) |
| Investments | `#475569` | Slate (Chart 2) |
| Cash | `#60A5FA` | Light Blue (Chart 4) |

### Event Icon Overlay System

The chart features floating event icons positioned above bars at calculated coordinates:

| Property | Value | Purpose |
|----------|-------|---------|
| Icon size | `26px` | Standard event icon dimension |
| Icon gap | `8px` | Vertical spacing between stacked icons |
| Float gap | `20px` | Distance above bar top |
| Hover effect | `transform hover:scale-110` | Interactive feedback |
| Pointer events | `auto` | Ensures click interaction |

Icons stack vertically when multiple events occur at the same age. Positioning is calculated using SVG chart coordinates converted to viewport pixels, and updates responsively on chart resize.

### Retirement Age Annotation

A vertical dashed line marks the retirement age:

- **Line colour:** `PRIMARY_COLORS[600]` (`#1257A0`)
- **Style:** Dashed
- **Label:** Positioned above the chart area with matching background colour

### Grid & Axis Configuration

| Element | Style |
|---------|-------|
| Grid lines | `strokeDashArray: 4` (dashed), `BORDER_COLORS.default` |
| Extra top padding | `120px` (accommodates floating event icons) |
| X-axis labels | `11px`, `gray-600` |
| Y-axis labels | `11px`, `gray-600`, compact format (K/M) |
| X-axis title | "Age", `12px`, `gray-600` |

### Custom Tooltip

The chart uses a custom HTML tooltip (not ApexCharts default) with:

- Inline styles for cross-browser consistency
- Series values with coloured indicator dots
- Separated sections for goals vs life events
- Impact display (income/expense) with colour coding
- Age-based labelling ("Age {number}")

### Design System Compliance

- All colours sourced from `designSystem.js` constants
- No amber/orange per forbidden colours rule
- Inter font family throughout
- Standard Tailwind spacing (p-4, p-6, mb-6)

---

## Public & Marketing Pages

Public-facing pages (Landing, Calculators, Learning Centre, Security, Pricing) follow distinct patterns from the authenticated app UI. These pages prioritise visual impact and conversion while maintaining brand trust.

### Extended Color Palette (Public Pages)

Public pages may use additional Tailwind colors beyond the core primary/secondary palette, within these approved contexts:

| Color | Approved Usage | Not For |
|-------|---------------|---------|
| `indigo-*` | Calculator headers, feature accents, focus rings | Primary buttons, badges |
| `emerald-*` | Security/trust sections, data protection themes | Status indicators (use green) |
| `slate-*` | Dark backgrounds, neutral headers, body text on dark | Light mode card borders |
| `blue-*` | Warnings, loan/finance sections, links | Error states |
| `green-*` | Success, positive values, growth indicators | General decoration |
| `red-*` | Errors, negative values, critical alerts | Warnings |
| `purple-*` | Charts only (Chart 6), account badges (SIPP) | Headers, backgrounds, buttons |

**Still forbidden:** amber-*, orange-*, teal as primary (teal only for risk badges/charts).

### Hero Sections

Every public page starts with a hero section using a dark gradient background:

```html
<section class="relative overflow-hidden bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900 py-20">
  <!-- Optional decorative orbs (use approved colors with low opacity) -->
  <div class="absolute top-20 right-20 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl"></div>
  <div class="absolute bottom-10 left-10 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h1 class="text-4xl md:text-5xl font-bold text-white mb-6">Page Title</h1>
    <p class="text-xl text-blue-100 max-w-3xl mx-auto">Subtitle description text</p>
  </div>
</section>
```

| Element | Specification |
|---------|--------------|
| Background | `bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900` |
| Container | `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` |
| Title | `text-4xl md:text-5xl font-bold text-white` |
| Subtitle | `text-xl text-blue-100` |
| Vertical padding | `py-20` |
| Decorative orbs | Max 10-20% opacity, `blur-3xl`, approved colors only |

### Section Headers (Gradient Cards)

Content sections on public pages use gradient header cards to introduce topics:

```html
<div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-6 mb-6 text-white">
  <div class="flex items-center gap-3 mb-2">
    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
      <!-- Icon -->
    </div>
    <h2 class="text-xl font-bold">Section Title</h2>
  </div>
  <p class="text-blue-100 text-sm">Section description</p>
</div>
```

#### Approved Gradient Combinations

| Context | Gradient | Subtitle Text |
|---------|----------|---------------|
| Primary/Default | `from-blue-600 to-blue-700` | `text-blue-100` |
| Secondary | `from-slate-600 to-slate-700` | `text-slate-200` |
| Trust/Security | `from-emerald-700 to-emerald-800` | `text-emerald-100` |
| Feature/Accent | `from-indigo-600 to-indigo-700` | `text-indigo-100` |

**Do not use:** purple, pink, cyan, rose, or red gradients for section headers.

### CTA (Call-to-Action) Sections

Every public page ends with a consistent CTA section above the footer:

```html
<section class="bg-gradient-to-r from-blue-900 to-slate-900 py-16">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl font-bold text-white mb-4">CTA Heading</h2>
    <p class="text-xl text-blue-200 mb-8">Supporting description text</p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <!-- Primary CTA -->
      <router-link to="/register"
        class="inline-flex items-center px-8 py-3 bg-white text-slate-900 rounded-lg
               font-semibold hover:bg-gray-100 transition-colors shadow-lg">
        Primary Action
      </router-link>
      <!-- Secondary CTA (optional) -->
      <router-link to="/secondary"
        class="inline-flex items-center px-8 py-3 border-2 border-white/30 text-white
               rounded-lg font-semibold hover:bg-white/10 transition-colors">
        Secondary Action
      </router-link>
    </div>
  </div>
</section>
```

| Element | Specification |
|---------|--------------|
| Background | `bg-gradient-to-r from-blue-900 to-slate-900` |
| Heading | `text-3xl font-bold text-white` |
| Description | `text-xl text-blue-200` |
| Primary button | `bg-white text-slate-900 rounded-lg font-semibold shadow-lg` |
| Secondary button | `border-2 border-white/30 text-white rounded-lg` |
| Max width | `max-w-4xl` (narrower than content for focus) |

### Trust Indicators

Use trust indicator rows to build confidence near pricing or signup CTAs:

```html
<div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
  <div class="text-center">
    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3">
      <!-- Icon -->
    </div>
    <h3 class="font-semibold text-gray-900 mb-1">Trust Point</h3>
    <p class="text-sm text-gray-600">Supporting detail</p>
  </div>
</div>
```

### Accordion / FAQ Pattern

For collapsible FAQ or content sections:

```html
<div class="border border-gray-200 rounded-lg overflow-hidden">
  <button @click="toggle(index)"
    class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 transition-colors">
    <span class="font-medium text-gray-900">Question text</span>
    <svg class="w-5 h-5 text-gray-500 transition-transform duration-200"
         :class="{ 'rotate-180': isOpen(index) }">
      <!-- Chevron icon -->
    </svg>
  </button>
  <div v-if="isOpen(index)" class="px-4 pb-4 text-sm text-gray-600">
    Answer text
  </div>
</div>
```

| Element | Specification |
|---------|--------------|
| Container | `border border-gray-200 rounded-lg` |
| Button padding | `p-4` |
| Question text | `font-medium text-gray-900` |
| Answer text | `text-sm text-gray-600` |
| Icon rotation | `transition-transform duration-200`, `rotate-180` when open |
| Hover | `hover:bg-gray-50` |
| Spacing between items | `space-y-3` |

---

## Entrance Animations (Public Pages)

Public pages use CSS keyframe entrance animations for visual polish. These are CSS-only (no JS library required) and respect `prefers-reduced-motion`.

### Standard Entrance Animation

```css
@keyframes fadeSlideIn {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.entrance-animate {
  animation: fadeSlideIn 0.5s ease-out forwards;
}
```

### Staggered Reveals

For multiple cards or sections that animate in sequence:

```css
.stagger-item:nth-child(1) { animation-delay: 0s; }
.stagger-item:nth-child(2) { animation-delay: 0.1s; }
.stagger-item:nth-child(3) { animation-delay: 0.2s; }
.stagger-item:nth-child(4) { animation-delay: 0.3s; }
.stagger-item:nth-child(5) { animation-delay: 0.4s; }
.stagger-item:nth-child(6) { animation-delay: 0.5s; }
```

### Guidelines

| Rule | Value |
|------|-------|
| Duration | 400-600ms (public pages allow slightly slower than app UI) |
| Easing | `ease-out` for entrances |
| Direction | Slide up (`translateY(16px)` to `0`) preferred |
| Max delay | 0.5s for the last item in a stagger sequence |
| Reduced motion | Wrap in `@media (prefers-reduced-motion: no-preference)` or ensure `animation-duration: 0.01ms` fallback |

**Do not use entrance animations in the authenticated app UI** - they are reserved for public/marketing pages where first impressions matter. App UI should use transition animations (hover, focus, modal open/close) instead.

---

*This design system is a living document. Propose changes through the standard PR process with justification for any additions or modifications.*
