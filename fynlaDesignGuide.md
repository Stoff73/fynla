# Fynla Design System

**Version:** 1.3.0  
**Last Updated:** 02 April 2026  
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
27. [Login & Registration Pages](#login--registration-pages)
28. [Verification Code / OTP Input](#verification-code--otp-input)
29. [Onboarding Wizard](#onboarding-wizard)
30. [Toggle Switch](#toggle-switch)
31. [Currency Input](#currency-input)
32. [Percentage Input](#percentage-input)
33. [Range Slider](#range-slider)
34. [File Upload / Drag-and-Drop](#file-upload--drag-and-drop)
35. [Autocomplete / Typeahead](#autocomplete--typeahead)
36. [Risk Level Selector](#risk-level-selector)
37. [Button Group Selector](#button-group-selector)
38. [Card Selector](#card-selector)
39. [View Toggle (Segmented Control)](#view-toggle-segmented-control)
40. [Sidebar Navigation (Detailed)](#sidebar-navigation-detailed)
41. [Floating Action Buttons (FABs)](#floating-action-buttons-fabs)
42. [Slide-in Panels (Drawers)](#slide-in-panels-drawers)
43. [Preview Mode Banner](#preview-mode-banner)
44. [Trial Countdown Banner](#trial-countdown-banner)
45. [Toast / Notification System](#toast--notification-system)
46. [Confirm Dialog](#confirm-dialog)
47. [Guidance Tooltip (Walkthrough)](#guidance-tooltip-walkthrough)
48. [Progress Bars](#progress-bars)
49. [Gauge / Radial Charts](#gauge--radial-charts)
50. [Milestone Tracker](#milestone-tracker)
51. [Contribution Streak Meter](#contribution-streak-meter)
52. [Data Retention Overlay](#data-retention-overlay)
53. [Print Styles](#print-styles)
54. [Wealth Summary Grid](#wealth-summary-grid)
55. [Modal Patterns (Three Generations)](#modal-patterns-three-generations)
56. [Event Icon Overlay System](#event-icon-overlay-system)
57. [Persona Selection Cards](#persona-selection-cards)
58. [Billing Toggle](#billing-toggle)
59. [In-Page Tab Navigation](#in-page-tab-navigation)
60. [User Dropdown Menu](#user-dropdown-menu)
61. [Search Input](#search-input)
62. [Pagination](#pagination)
63. [Sortable Table Headers](#sortable-table-headers)
64. [Expandable Table Rows](#expandable-table-rows)
65. [Inline Edit Pattern](#inline-edit-pattern)
66. [Avatar / User Representation](#avatar--user-representation)
67. [Dynamic Financial Values](#dynamic-financial-values)
68. [Number Input Scroll Prevention](#number-input-scroll-prevention)
69. [Clickable Card CTA Pattern](#clickable-card-cta-pattern)

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

**Brand Updates (v1.2.0):** Incorporates redesigned logo with upward growth steps (representing life journey and financial growth), Planet Estyle typography for logo elements, and a vibrant yet approachable color palette blending modern tones with softer shades. The character "Fyn" (springbok) is integrated for humanized AI representation, emphasizing companionship in financial planning.

---

## Color System

The color system has been updated to the recommended Option 2 palette with adjustments for versatility, accessibility (AA compliance), and alignment with the character "Fyn". It features a mix of vibrant modern colors (raspberry, green, violet) and softer tones (horizon blue, savannah sand, eggshell). Primary colors dominate (~50-70% visual weight), secondary for contrast (~20-30%), and tertiary for highlights (~5-10%).

### Primary Palette

Core brand colors for main elements, text, backgrounds, and visual weight.

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Raspberry 50 | #FDF2F8 | `raspberry-50` | Light backgrounds |
| Raspberry 100 | #FCE7F3 | `raspberry-100` | Subtle highlights |
| Raspberry 200 | #F9A8D4 | `raspberry-200` | Light accents |
| Raspberry 300 | #F472B6 | `raspberry-300` | Disabled states |
| Raspberry 400 | #EC4899 | `raspberry-400` | Hover accents |
| **Raspberry 500** | #E83E6D | `raspberry-500` | **Accent color** - CTAs, links |
| Raspberry 600 | #DB2777 | `raspberry-600` | Active states |
| Raspberry 700 | #BE185D | `raspberry-700` | Dark accents |
| Raspberry 800 | #9D174D | `raspberry-800` | Pressed states |
| Raspberry 900 | #831843 | `raspberry-900` | Very dark (rare) |

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Horizon Blue 50 | #F8FAFC | `horizon-50` | Light backgrounds |
| Horizon Blue 100 | #F1F5F9 | `horizon-100` | Page backgrounds |
| Horizon Blue 200 | #E2E8F0 | `horizon-200` | Borders |
| Horizon Blue 300 | #CBD5E1 | `horizon-300` | Disabled |
| Horizon Blue 400 | #94A3B8 | `horizon-400` | Placeholders |
| **Horizon Blue 500** | #1F2A44 | `horizon-500` | **Main brand dark** - text, nav |
| Horizon Blue 600 | #0F172A | `horizon-600` | Hover dark |
| Horizon Blue 700 | #020617 | `horizon-700` | Active dark |
| Horizon Blue 800 | #0A0E1A | `horizon-800` | Deep backgrounds |
| Horizon Blue 900 | #03060D | `horizon-900` | Near-black |

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Spring Green 50 | #F0FDF9 | `spring-50` | Light backgrounds |
| Spring Green 100 | #D1FAE5 | `spring-100` | Subtle success |
| Spring Green 200 | #A7F3D0 | `spring-200` | Light accents |
| Spring Green 300 | #6EE7B7 | `spring-300` | Disabled |
| Spring Green 400 | #34D399 | `spring-400` | Hover |
| **Spring Green 500** | #20B486 | `spring-500` | Success, growth |
| Spring Green 600 | #059669 | `spring-600` | Active |
| Spring Green 700 | #047857 | `spring-700` | Dark success |
| Spring Green 800 | #065F46 | `spring-800` | Pressed |
| Spring Green 900 | #064E3B | `spring-900` | Deep green |

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Violet 50 | #F5F3FF | `violet-50` | Light backgrounds |
| Violet 100 | #EDE9FE | `violet-100` | Subtle highlights |
| Violet 200 | #DDD6FE | `violet-200` | Light accents |
| Violet 300 | #C4B5FD | `violet-300` | Disabled |
| Violet 400 | #A78BFA | `violet-400` | Hover |
| **Violet 500** | #5854E6 | `violet-500` | Info, accents |
| Violet 600 | #7C3AED | `violet-600` | Active |
| Violet 700 | #6D28D9 | `violet-700` | Dark accents |
| Violet 800 | #581C87 | `violet-800` | Pressed |
| Violet 900 | #4C1D5F | `violet-900` | Deep violet |

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Savannah Sand 50 | #FEFCFB | `savannah-50` | Light backgrounds |
| Savannah Sand 100 | #FDFAF7 | `savannah-100` | Off-white pages |
| Savannah Sand 200 | #FAF5F0 | `savannah-200` | Subtle borders |
| Savannah Sand 300 | #F5EDE5 | `savannah-300` | Disabled |
| Savannah Sand 400 | #EFDCD1 | `savannah-400` | Hover light |
| **Savannah Sand 500** | #E6C9A8 | `savannah-500` | Neutral accents, backgrounds |
| Savannah Sand 600 | #D1B08C | `savannah-600` | Active neutral |
| Savannah Sand 700 | #A88E6E | `savannah-700` | Dark neutral |
| Savannah Sand 800 | #8A7359 | `savannah-800` | Pressed |
| Savannah Sand 900 | #6B5845 | `savannah-900` | Deep sand |

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Eggshell 50 | #FFFFFF | `eggshell-50` | Pure white |
| Eggshell 100 | #FEFEFE | `eggshell-100` | Near-white |
| **Eggshell 500** | #F7F6F4 | `eggshell-500` | **Off-white backgrounds** |
| Eggshell 900 | #E7E5E2 | `eggshell-900` | Subtle dark (rare) |

### Secondary Palette

For contrasting or complementary elements like CTAs, secondary priorities, and backgrounds.

| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Neutral Gray | #717171 | `neutral-500` | Secondary text, borders |
| Light Gray | #EEEEEE | `light-gray` | Dividers, backgrounds |
| Light Blue | #6C83BC | `light-blue-500` | Subtle accents |
| Lighter Blue | #DDE2EF | `light-blue-100` | Highlights |
| Light Pink | #EF7598 | `light-pink-400` | Hover raspberry |
| Lighter Pink | #FAD6E0 | `light-pink-100` | Subtle backgrounds |

### Semantic Colors

Updated to align with new palette.

#### Success (Spring Green)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Success 100 | #D1FAE5 | `success-100` | Backgrounds |
| Success 500 | #20B486 | `success-500` | Text, icons |
| Success 600 | #059669 | `success-600` | Borders, buttons |
| Success 700 | #047857 | `success-700` | Hover |

#### Error (Raspberry - adjusted for danger)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Error 100 | #FCE7F3 | `error-100` | Backgrounds |
| Error 500 | #E83E6D | `error-500` | Icons |
| Error 600 | #DB2777 | `error-600` | Text, borders |
| Error 700 | #BE185D | `error-700` | Hover |

#### Warning (Violet)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Warning 100 | #EDE9FE | `warning-100` | Backgrounds |
| Warning 500 | #5854E6 | `warning-500` | Icons, text |
| Warning 600 | #7C3AED | `warning-600` | Borders |
| Warning 700 | #6D28D9 | `warning-700` | Hover |

#### Info (Light Blue)
| Name | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Info 100 | #DDE2EF | `info-100` | Backgrounds |
| Info 500 | #6C83BC | `info-500` | Icons |
| Info 600 | #5A6FA3 | `info-600` | Text, borders |
| Info 700 | #4C5D8A | `info-700` | Hover |

### Chart Colors

Updated for new palette consistency:

| ID | Hex | Usage |
|----|-----|-------|
| Chart 1 | #1F2A44 | Horizon Blue - Primary series |
| Chart 2 | #20B486 | Spring Green - Growth/positive |
| Chart 3 | #5854E6 | Violet - Alternative |
| Chart 4 | #E83E6D | Raspberry - Accent/secondary |
| Chart 5 | #E6C9A8 | Savannah Sand - Neutral |
| Chart 6 | #6C83BC | Light Blue - Tertiary |
| Chart 7 | #717171 | Neutral Gray - Subtle |
| Chart 8 | #0F172A | Dark Horizon - Deep accent |

### Text Colors

| Usage | Tailwind Class | Hex |
|-------|---------------|-----|
| Primary text (headings) | `text-horizon-500` | #1F2A44 |
| Secondary text (body) | `text-neutral-500` | #717171 |
| Tertiary text (subtle) | `text-light-gray` | #EEEEEE |
| Muted text (captions) | `text-horizon-300` | Derived from scale |
| Placeholder text | `text-horizon-400` | Derived |
| Disabled text | `text-horizon-300` | Derived |

### Background Colors

| Usage | Tailwind Class | Hex |
|-------|---------------|-----|
| Page background | `bg-eggshell-500` | #F7F6F4 |
| Card/component background | `bg-white` | #FFFFFF |
| Subtle highlight | `bg-savannah-100` | Derived |
| Modal overlay | `bg-horizon-500/75` | - |
| Hero gradient | `bg-gradient-to-br from-raspberry-500 to-horizon-500` | For public pages |

### Gradients & Patterns

- Use subtle linear (0° or 45°) or radial gradients with primary color blends only (e.g., raspberry to horizon blue).
- Avoid complex patterns; stick to solid or gradient backgrounds for elements.

### FORBIDDEN Colors

- **Amber/Orange:** All amber/orange variants banned (`amber-*`, `orange-*`). Use violet for warnings.
- **Mustard Yellow:** Any yellow-brown/gold (e.g., #C9A000).
- **Pastel Washes:** Desaturated colors (>80% lightness, <20% saturation).
- **Pure Black:** Use horizon-700 (#020617) instead.
- **Neons:** #00FF00, etc.
- **Teal as Primary:** Limited to charts/risk badges only.

---

## Typography

Updated to Segoe UI for all assets (regular copy, titles). Logo uses Planet Estyle for innovative, rounded feel. Font sizes 10-24pt for body, with exceptions.

### Font Families

```css
--font-sans: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
--font-display: 'Planet Estyle', 'Segoe UI', sans-serif;
--font-mono: 'JetBrains Mono', 'Courier New', monospace;
```

| Usage | Font Family | Tailwind |
|-------|-------------|----------|
| Body text | Segoe UI | `font-sans` |
| Display/Headlines | Segoe UI (Bold/Black) | `font-display` |
| Logo | Planet Estyle | Custom for logo only |
| Code/numbers | JetBrains Mono | `font-mono` |

### Type Scale

| Name | Size | Line Height | Weight | Letter Spacing | Tailwind |
|------|------|-------------|--------|----------------|----------|
| Display | 3.75rem (60px) | 1.1 | 900 (Black) | -0.02em | `text-display` |
| H1 | 2.25rem (36px) | 1.2 | 900 (Black) | -0.01em | `text-h1` |
| H2 | 1.875rem (30px) | 1.3 | 700 (Bold) | normal | `text-h2` |
| H3 | 1.5rem (24px) | 1.4 | 700 (Bold) | normal | `text-h3` |
| H4 | 1.25rem (20px) | 1.5 | 700 (Bold) | normal | `text-h4` |
| H5 | 1rem (16px) | 1.5 | 700 (Bold) | normal | `text-h5` |
| Body Large | 1.125rem (18px) | 1.7 | 400 (Regular) | normal | `text-body-lg` |
| Body | 1rem (16px) | 1.6 | 400 (Regular) | normal | `text-body` |
| Body Small | 0.875rem (14px) | 1.5 | 400 (Regular) | normal | `text-body-sm` |
| Caption | 0.75rem (12px) | 1.4 | 400 (Regular) | normal | `text-caption` |

### Usage Guidelines

- **Dark Text:** Use horizon-500 (#1F2A44) for readability.
- **Light Text:** Use #FFFFFF on dark backgrounds.
- **Logo:** Planet Estyle only for "Fynla" wordmark and specific design cases; not for user-facing text.
- **Examples:**
  ```html
  <!-- Page title -->
  <h1 class="font-display text-h1 text-horizon-500">Dashboard</h1>

  <!-- Body text -->
  <p class="text-body text-neutral-500">Your emergency fund covers 4.5 months...</p>
  ```

### Font Weights

| Weight | Value | Usage |
|--------|-------|-------|
| Regular | 400 | Body text |
| Semibold | 600 | Labels, emphasis |
| Bold | 700 | Subheadings, buttons |
| Black | 900 | Statements, stats |

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
| Default | `border-light-gray` |
| Hover | `border-horizon-300` or `border-raspberry-300` |
| Focus | `border-raspberry-600` |
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

Updated colors: Primary uses raspberry-500 for vibrant CTAs; secondary uses horizon-500.

### Primary Button

The main call-to-action button.

```html
<button class="px-4 py-2 bg-raspberry-500 text-white rounded-button font-medium
               hover:bg-raspberry-600 active:bg-raspberry-700
               transition-all duration-150 shadow-sm hover:shadow-md
               disabled:opacity-50 disabled:cursor-not-allowed">
  Save Changes
</button>
```

| State | Background | Text | Border | Shadow |
|-------|------------|------|--------|--------|
| Default | `bg-raspberry-500` | `text-white` | none | `shadow-sm` |
| Hover | `bg-raspberry-600` | `text-white` | none | `shadow-md` |
| Active | `bg-raspberry-700` | `text-white` | none | `shadow-sm` |
| Disabled | `bg-raspberry-500 opacity-50` | `text-white` | none | none |

### Secondary Button

For secondary actions.

```html
<button class="px-4 py-2 bg-white text-horizon-500 border border-light-gray
               rounded-button font-medium hover:bg-savannah-100 active:bg-savannah-200
               transition-all duration-150 shadow-sm hover:shadow-md">
  Cancel
</button>
```

### Outline Button

For tertiary actions.

```html
<button class="px-4 py-2 bg-transparent text-spring-500 border border-spring-500
               rounded-button font-medium hover:bg-spring-100 active:bg-spring-200
               transition-all duration-150">
  Learn More
</button>
```

### Light Pink Button

For soft secondary actions (e.g. "Set a Financial Goal").

```html
<button class="px-4 py-2 bg-light-pink-100 text-neutral-500 rounded-button font-medium
               hover:bg-light-pink-200 active:bg-light-pink-300
               transition-all duration-150">
  Set a Financial Goal
</button>
```

| State | Background | Text | Border |
|-------|------------|------|--------|
| Default | `bg-light-pink-100` | `text-neutral-500` | none |
| Hover | `bg-light-pink-200` | `text-neutral-500` | none |
| Active | `bg-light-pink-300` | `text-neutral-500` | none |

**Rule:** Light pink buttons must only use regular grey text (`text-neutral-500`). Never use white, raspberry, or horizon text on light pink buttons.

### Success Button (Green)

For positive/forward actions (e.g. "Start a Planning Journey").

```html
<button class="px-4 py-2 bg-spring-500 text-white rounded-button font-medium
               hover:bg-spring-600 active:bg-spring-700
               transition-all duration-150 shadow-sm hover:shadow-md">
  Start a Planning Journey
</button>
```

| State | Background | Text | Border |
|-------|------------|------|--------|
| Default | `bg-spring-500` | `text-white` | none |
| Hover | `bg-spring-600` | `text-white` | none |
| Active | `bg-spring-700` | `text-white` | none |

**Rule:** Green (spring) buttons must only use white text. Never use dark or coloured text on green buttons.

### Danger Button

For destructive actions.

```html
<button class="px-4 py-2 bg-raspberry-600 text-white rounded-button font-medium
               hover:bg-raspberry-700 active:bg-raspberry-800
               transition-all duration-150 shadow-sm hover:shadow-md">
  Delete
</button>
```

---

## Forms & Inputs

Updated placeholder to neutral-500 (#717171), focus border to violet-500.

### Input Field

```html
<input class="px-4 py-2 border border-light-gray rounded-md text-horizon-500
              placeholder:text-neutral-500 focus:border-violet-500 focus:ring-violet-500/20
              disabled:opacity-50" placeholder="Enter amount" />
```

### Select

```html
<select class="px-4 py-2 border border-light-gray rounded-md text-horizon-500
               focus:border-violet-500 focus:ring-violet-500/20">
  <option>Option 1</option>
</select>
```

### Checkbox

```html
<input type="checkbox" class="h-4 w-4 text-violet-500 border-light-gray rounded
                               focus:ring-violet-500/20" />
```

### Radio

```html
<input type="radio" class="h-4 w-4 text-violet-500 border-light-gray rounded-full
                            focus:ring-violet-500/20" />
```

### Textarea

```html
<textarea class="px-4 py-2 border border-light-gray rounded-md text-horizon-500
                 placeholder:text-neutral-500 focus:border-violet-500 focus:ring-violet-500/20
                 disabled:opacity-50" rows="4"></textarea>
```

### Form Group

```html
<div class="mb-4">
  <label class="block text-body-sm font-medium text-neutral-500 mb-1">Label</label>
  <input ... />
  <p class="mt-1 text-caption text-neutral-500">Helper text</p>
</div>
```

### Error State

```html
<input class="... border-raspberry-500 focus:border-raspberry-500 focus:ring-raspberry-500/20" />
<p class="mt-1 text-caption text-raspberry-500">Error message</p>
```

---

## Cards

Updated backgrounds to eggshell-500 for subtle warmth; accents use spring-green or violet.

### Standard Card

```html
<div class="bg-white p-6 rounded-card shadow-card border border-light-gray">
  <h3 class="text-h4 font-bold text-horizon-500">Card Title</h3>
  <p class="text-body text-neutral-500">Content</p>
</div>
```

For "How Fyn can help" cards (dark mode):

```html
<div class="bg-horizon-500 p-6 rounded-card text-white">
  <!-- Content -->
</div>
```

### Feature Card

```html
<div class="bg-white p-6 rounded-xl shadow-lg border border-light-gray hover:shadow-xl transition-shadow">
  <div class="w-10 h-10 bg-raspberry-100 rounded-lg mb-4 flex items-center justify-center">
    <!-- Icon -->
  </div>
  <h3 class="text-h4 font-bold text-horizon-500 mb-2">Feature</h3>
  <p class="text-body-sm text-neutral-500">Description</p>
</div>
```

---

## Profile & Information Tabs

### Tab Navigation

```html
<div class="border-b border-light-gray">
  <nav class="flex space-x-8">
    <button class="py-4 px-1 border-b-2 border-transparent font-medium text-neutral-500
                   hover:text-horizon-500 hover:border-raspberry-500
                   aria-selected:border-raspberry-500 aria-selected:text-horizon-500">
      Tab 1
    </button>
  </nav>
</div>
```

### Profile Card

```html
<div class="bg-white p-6 rounded-card shadow-card">
  <div class="flex items-center gap-4">
    <img src="avatar.jpg" class="w-12 h-12 rounded-full" alt="Profile" />
    <div>
      <h3 class="text-h4 font-bold text-horizon-500">User Name</h3>
      <p class="text-body-sm text-neutral-500">Details</p>
    </div>
  </div>
</div>
```

---

## Tables

### Basic Table

```html
<table class="w-full text-left border-collapse">
  <thead>
    <tr class="border-b border-light-gray">
      <th class="py-3 px-4 text-body-sm font-medium text-neutral-500">Header</th>
    </tr>
  </thead>
  <tbody>
    <tr class="border-b border-light-gray hover:bg-savannah-100">
      <td class="py-3 px-4 text-body text-horizon-500">Cell</td>
    </tr>
  </tbody>
</table>
```

### Striped Table

Add `even:bg-savannah-50` to rows.

---

## Modals & Overlays

Updated overlay to horizon-500/75.

### Modal Structure

```html
<div class="fixed inset-0 bg-horizon-500/75 flex items-center justify-center z-50">
  <div class="bg-white rounded-card shadow-modal max-w-lg w-full p-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-h3 font-bold text-horizon-500">Title</h3>
      <button class="text-neutral-500 hover:text-horizon-500">×</button>
    </div>
    <div class="mb-6">Content</div>
    <div class="flex justify-end gap-3">
      <button class="secondary">Cancel</button>
      <button class="primary">Save</button>
    </div>
  </div>
</div>
```

### Overlay

Use `bg-horizon-500/75` or `bg-black/50` for darker overlays.

---

## Navigation

Updated nav background to horizon-500, text white.

### Top Nav

```html
<nav class="bg-horizon-500 text-white p-4 flex items-center justify-between">
  <div class="flex items-center gap-4">
    <img src="logo.svg" alt="Fynla" class="h-8" />
    <span class="text-h4 font-bold">Fynla</span>
  </div>
  <div class="flex gap-6">
    <a href="#" class="hover:text-raspberry-100">Link</a>
  </div>
</nav>
```

### Sidebar

```html
<aside class="bg-white w-64 p-6 border-r border-light-gray">
  <nav class="space-y-1">
    <a href="#" class="flex items-center px-3 py-2 text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500 rounded-md">
      <span class="mr-3"><!-- Icon --></span>
      Item
    </a>
  </nav>
</aside>
```

---

## Badges & Tags

Updated colors: Success - spring-500, Error - raspberry-500, etc.

### Badge

```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium
             bg-spring-100 text-spring-700">
  Success
</span>
```

### Tag

```html
<span class="inline-flex items-center px-3 py-1 rounded-md text-body-sm font-medium
             bg-violet-100 text-violet-700">
  Tag
</span>
```

### Variants

- Success: `bg-spring-100 text-spring-700`
- Error: `bg-raspberry-100 text-raspberry-700`
- Warning: `bg-violet-100 text-violet-700`
- Info: `bg-light-blue-100 text-light-blue-700`

---

## Alerts & Notifications

Updated to new semantic colors.

### Alert

```html
<div class="p-4 rounded-md flex items-start gap-3" role="alert">
  <span><!-- Icon --></span>
  <div>
    <h4 class="text-body font-medium">Title</h4>
    <p class="text-body-sm">Message</p>
  </div>
</div>
```

### Variants

- Success: `bg-spring-100 text-spring-700`
- Error: `bg-raspberry-100 text-raspberry-700`
- Warning: `bg-violet-100 text-violet-700`
- Info: `bg-light-blue-100 text-light-blue-700`

### Toast Notification

Use similar structure with `fixed bottom-4 right-4` positioning.

---

## Loading States

### Spinner

```html
<div class="flex items-center justify-center h-32">
  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-500"></div>
</div>
```

### Skeleton

```html
<div class="bg-savannah-100 h-4 w-full rounded animate-pulse"></div>
```

### Button Loading

Add `disabled` and spinner inside button.

---

## Empty States

```html
<div class="flex flex-col items-center justify-center h-64 text-center">
  <div class="w-16 h-16 bg-savannah-100 rounded-full flex items-center justify-center mb-4">
    <!-- Icon -->
  </div>
  <h3 class="text-h4 font-bold text-horizon-500 mb-2">No Data</h3>
  <p class="text-body text-neutral-500 mb-4">Description</p>
  <button class="primary">Action</button>
</div>
```

---

## Error States

```html
<div class="flex flex-col items-center justify-center h-64 text-center">
  <div class="w-16 h-16 bg-raspberry-100 rounded-full flex items-center justify-center mb-4 text-raspberry-500">
    <!-- Error Icon -->
  </div>
  <h3 class="text-h4 font-bold text-horizon-500 mb-2">Error</h3>
  <p class="text-body text-neutral-500 mb-4">Message</p>
  <button class="primary">Retry</button>
</div>
```

---

## Charts & Data Visualization

Updated chart colors as above. For Goals Projection:

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
| X-axis labels | `11px`, `neutral-500` |
| Y-axis labels | `11px`, `neutral-500`, compact format (K/M) |
| X-axis title | "Age", `12px`, `neutral-500` |

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

- Use new chart colors for series (e.g., Pensions: #1F2A44, Investments: #5854E6).
- Event icons: Integrate "Fyn" character elements where appropriate.

---

## Icons

Use Heroicons or similar library. Size: 20px default.

### Usage

```html
<svg class="h-5 w-5 text-raspberry-500" ...></svg>
```

### Colors

- Default: `text-neutral-500`
- Accent: `text-raspberry-500`
- Success: `text-spring-500`

---

## Animation & Motion

### Transitions

Use `transition-all duration-150` for hover/active.

### Animations

```css
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
```

Apply `animate-fadeIn` with `animation-duration: 300ms`.

### Guidelines

- Duration: 150-300ms
- Easing: `ease-in-out`
- Respect `prefers-reduced-motion`

---

## Responsive Breakpoints

Tailwind defaults:

- sm: 640px
- md: 768px
- lg: 1024px
- xl: 1280px
- 2xl: 1536px

### Layout Patterns

- Mobile: Stack vertically
- Tablet: 2-column grid
- Desktop: 3+ columns

Example:

```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
```

### Grid Overflow Prevention

When using CSS Grid inside constrained containers (e.g., pages with a sidebar navigation), **always add `min-width: 0` to grid children** to prevent content from overflowing the grid track:

```css
.my-grid-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.my-grid-row > * {
  min-width: 0;
}
```

Without `min-width: 0`, grid children with intrinsic content wider than their track (e.g., tables, long text) will push the column wider than the available space, causing horizontal overflow.

### Sidebar-Aware Breakpoints

The main content area inside `AppLayout` is approximately 350px narrower than the viewport (due to the sidebar navigation). When setting responsive breakpoints, account for this:

| Viewport | Content Area | Recommendation |
|----------|-------------|----------------|
| 1440px | ~1090px | 3-column grids work |
| 1200px | ~850px | Collapse to 2 columns |
| 1024px | ~674px | Collapse to 1 column for side-by-side layouts |
| 768px | ~418px | Full mobile stacking |

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
focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-opacity-20 focus:border-violet-600

/* High-visibility focus (keyboard navigation) */
focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2
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
| Use raspberry-500 for main actions | Use bright/neon colors |
| Use semantic colors for meaning | Use color as the only indicator |
| Keep to the defined palette | Introduce new colors without approval |
| Use horizon-700 for text | Use pure black (#000000) |

### Typography

| Do | Don't |
|----|-------|
| Use Segoe UI for body text | Mix multiple body fonts |
| Follow the type scale | Use arbitrary font sizes |
| Maintain hierarchy (h1 > h2 > h3) | Skip heading levels |
| Use bold/black sparingly | Make everything bold |

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

Add savannah-500 for beige sections.

### Hero Sections

Every public page starts with a hero section using a dark gradient background:

```html
<section class="relative overflow-hidden bg-gradient-to-br from-raspberry-500 to-horizon-500 py-20">
  <div class="absolute top-20 right-20 w-72 h-72 bg-violet-500/10 rounded-full blur-3xl"></div>
  <div class="absolute bottom-10 left-10 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h1 class="text-4xl md:text-5xl font-bold text-white mb-6">Meet Fyn</h1>
    <p class="text-xl text-white/80">Your financial companion for life</p>
  </div>
</section>
```

| Element | Specification |
|---------|--------------|
| Background | `bg-gradient-to-br from-raspberry-500 to-horizon-500` |
| Container | `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` |
| Title | `text-4xl md:text-5xl font-bold text-white` |
| Subtitle | `text-xl text-white/80` |
| Vertical padding | `py-20` |
| Decorative orbs | Max 10-20% opacity, `blur-3xl`, approved colors only |

### Section Headers (Gradient Cards)

Content sections on public pages use gradient header cards to introduce topics:

```html
<div class="bg-gradient-to-r from-raspberry-500 to-raspberry-600 rounded-xl p-6 mb-6 text-white">
  <div class="flex items-center gap-3 mb-2">
    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
      <!-- Icon -->
    </div>
    <h2 class="text-xl font-bold">Section Title</h2>
  </div>
  <p class="text-white/80 text-sm">Section description</p>
</div>
```

#### Approved Gradient Combinations

| Context | Gradient | Subtitle Text |
|---------|----------|---------------|
| Primary/Default | `from-raspberry-500 to-raspberry-600` | `text-white/80` |
| Secondary | `from-horizon-500 to-horizon-600` | `text-white/80` |
| Trust/Security | `from-spring-500 to-spring-600` | `text-white/80` |
| Feature/Accent | `from-violet-500 to-violet-600` | `text-white/80` |

**Do not use:** purple, pink, cyan, rose, or red gradients for section headers.

### CTA (Call-to-Action) Sections

Every public page ends with a consistent CTA section above the footer:

```html
<section class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-16">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl font-bold text-white mb-4">CTA Heading</h2>
    <p class="text-xl text-white/80 mb-8">Supporting description text</p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <!-- Primary CTA -->
      <router-link to="/register"
        class="inline-flex items-center px-8 py-3 bg-raspberry-500 text-white rounded-lg
               font-semibold hover:bg-raspberry-600 transition-colors shadow-lg">
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
| Background | `bg-gradient-to-r from-horizon-600 to-horizon-700` |
| Heading | `text-3xl font-bold text-white` |
| Description | `text-xl text-white/80` |
| Primary button | `bg-raspberry-500 text-white rounded-lg font-semibold shadow-lg` |
| Secondary button | `border-2 border-white/30 text-white rounded-lg` |
| Max width | `max-w-4xl` (narrower than content for focus) |

Use spring-500 for demo buttons.

### Trust Indicators

Use trust indicator rows to build confidence near pricing or signup CTAs:

```html
<div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
  <div class="text-center">
    <div class="w-12 h-12 bg-light-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3">
      <!-- Icon -->
    </div>
    <h3 class="font-semibold text-horizon-500 mb-1">Trust Point</h3>
    <p class="text-sm text-neutral-500">Supporting detail</p>
  </div>
</div>
```

### Accordion / FAQ Pattern

For collapsible FAQ or content sections:

```html
<div class="border border-light-gray rounded-lg overflow-hidden">
  <button @click="toggle(index)"
    class="w-full flex items-center justify-between p-4 text-left hover:bg-savannah-100 transition-colors">
    <span class="font-medium text-horizon-500">Question text</span>
    <svg class="w-5 h-5 text-neutral-500 transition-transform duration-200"
         :class="{ 'rotate-180': isOpen(index) }">
      <!-- Chevron icon -->
    </svg>
  </button>
  <div v-if="isOpen(index)" class="px-4 pb-4 text-sm text-neutral-500">
    Answer text
  </div>
</div>
```

| Element | Specification |
|---------|--------------|
| Container | `border border-light-gray rounded-lg` |
| Button padding | `p-4` |
| Question text | `font-medium text-horizon-500` |
| Answer text | `text-sm text-neutral-500` |
| Icon rotation | `transition-transform duration-200`, `rotate-180` when open |
| Hover | `hover:bg-savannah-100` |
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

Apply to new elements like character image: fadeSlideIn with 0.5s delay.

---

## Login & Registration Pages

Auth screens use a full-screen centred layout distinct from the main app.

### Page Layout

```html
<div class="min-h-screen flex items-center justify-center bg-eggshell-500 py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-md w-full space-y-8">
    <!-- Logo -->
    <img :src="logoUrl" alt="Fynla" class="h-48 w-auto mx-auto" />
    <!-- Heading -->
    <h2 class="text-h3 text-horizon-500 text-center mt-6">Sign in to your account</h2>
    <!-- Form -->
  </div>
</div>
```

| Element | Specification |
|---------|---------------|
| Background | `bg-eggshell-500` |
| Container | `max-w-md w-full space-y-8` |
| Logo height | `h-48` (192px) |
| Heading | `text-h3 text-horizon-500 text-center` |
| Form inputs | Use `.input-field` class from `app.css` |
| Submit button | `w-full btn-primary` |
| Error border | `:class="{ 'border-error-600': errors.field }"` |
| Error text | `text-body-sm text-error-600 mt-1` |

### Contextual Banners

| Banner | Classes | When Shown |
|--------|---------|------------|
| Beta Warning | `bg-spring-200 border-2 border-spring-500 rounded-lg p-4` | Always (during beta) |
| Inactivity | `bg-violet-500 border-violet-600 text-white rounded-lg p-4` | Session timeout redirect |
| Error | `bg-raspberry-50 border-raspberry-200 rounded-lg p-4 text-raspberry-800` | Failed login/register |

### Registration Specific

- Name fields: `grid grid-cols-1 sm:grid-cols-3 gap-4`
- Password hint: `text-xs text-neutral-500 mt-1`
- Terms note: `text-center text-body-sm text-neutral-500`
- Link to login: `text-raspberry-500 hover:text-raspberry-700 font-medium`

---

## Verification Code / OTP Input

A split 6-digit input used for email verification and MFA. Three instances exist — consolidate into a shared `SixDigitCodeInput.vue` component.

### Structure

```html
<div class="flex justify-center gap-2">
  <input
    v-for="(digit, index) in 6"
    type="text"
    maxlength="1"
    inputmode="numeric"
    pattern="[0-9]*"
    class="w-12 h-14 text-center text-2xl font-bold border-2 border-horizon-300 rounded-lg
           focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition-all"
  />
</div>
```

| State | Classes |
|-------|---------|
| Default | `border-2 border-horizon-300 rounded-lg` |
| Has value | `border-violet-500` |
| Error | `border-raspberry-500 bg-raspberry-50` |
| Disabled | `opacity-50 cursor-not-allowed` |

### Behaviour

- Auto-advance focus to next box on digit entry
- Backspace moves focus to previous box
- Arrow keys navigate between boxes
- Paste distributes digits across all boxes
- Auto-submits when all 6 digits entered

### Loading Overlay

```html
<div class="absolute inset-0 bg-white/80 rounded-2xl flex items-center justify-center">
  <div class="w-10 h-10 border-4 border-violet-200 border-t-violet-600 rounded-full animate-spin"></div>
  <p class="text-sm text-neutral-500 mt-3">Verifying...</p>
</div>
```

**Note:** Current implementation uses `indigo-*` colors — migrate to `violet-*` per design system.

---

## Onboarding Wizard

Multi-step wizard with horizontal progress indicator.

### Progress Indicator

```html
<div class="flex items-center overflow-x-auto">
  <!-- Per step -->
  <div class="flex flex-col items-center min-w-[80px]">
    <div class="w-9 h-9 rounded-full border-2 flex items-center justify-center">
      <!-- Number or icon -->
    </div>
    <span class="text-xs mt-1 whitespace-nowrap">Step Name</span>
  </div>
  <!-- Connector line between steps -->
  <div class="h-0.5 flex-1 mx-2"></div>
</div>
```

| Step State | Circle Classes | Label Classes | Connector |
|------------|---------------|---------------|-----------|
| Current | `bg-spring-500 border-spring-500 text-white` | `text-spring-600 font-semibold` | — |
| Completed | `bg-spring-600 border-spring-600 text-white` + checkmark SVG | `text-spring-600` | `bg-spring-600` |
| Skipped | `bg-violet-500 border-violet-500 text-white` + X icon | `text-violet-600` | `bg-violet-500` |
| Pending | `bg-white border-horizon-300 text-horizon-400` + number | `text-neutral-500` | `bg-horizon-300` |

### Step Shell (OnboardingStep)

```html
<div class="max-w-5xl mx-auto">
  <h2 class="text-h2 font-display text-horizon-500 mb-2">Step Title</h2>
  <p class="text-body text-neutral-500 mb-4">Step description</p>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <!-- Step content slot -->
  </div>
  <div class="flex justify-between mt-6">
    <button class="btn-secondary">← Back</button>
    <button class="text-body-sm text-neutral-500 hover:text-horizon-500 underline">Skip this step</button>
    <button class="btn-primary">Continue →</button>
  </div>
</div>
```

### Welcome Screen

- Logo: `h-36 w-auto`
- Greeting: `text-h2 font-display text-horizon-500`
- Info box: `bg-raspberry-50 rounded-lg p-4 border border-raspberry-100`
- Step number badges: `w-6 h-6 bg-raspberry-100 text-raspberry-700 rounded-full text-sm font-semibold`
- Start button: `px-8 py-3 bg-raspberry-500 rounded-button text-white hover:bg-raspberry-600`

---

## Toggle Switch

CSS-styled checkbox rendered as a sliding toggle.

```html
<label class="toggle">
  <input type="checkbox" v-model="value" />
  <span class="toggle-slider"></span>
</label>
```

```css
.toggle {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute;
  inset: 0;
  background-color: #CBD5E1; /* horizon-300 */
  border-radius: 24px;
  transition: 0.3s;
  cursor: pointer;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  border-radius: 50%;
  transition: 0.3s;
}
.toggle input:checked + .toggle-slider {
  background-color: #20B486; /* spring-500 */
}
.toggle input:checked + .toggle-slider::before {
  transform: translateX(20px);
}
```

| State | Track Colour | Knob |
|-------|-------------|------|
| Off | `horizon-300` (#CBD5E1) | White, left |
| On | `spring-500` (#20B486) | White, right |
| Disabled | `opacity-50` | `cursor-not-allowed` |

---

## Currency Input

Text input with a `£` prefix symbol.

```html
<div class="relative">
  <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
  <input
    type="number"
    min="0"
    step="25"
    class="input-field pl-8"
    placeholder="0"
  />
</div>
```

| Element | Specification |
|---------|---------------|
| Prefix | `absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500` |
| Input padding | `pl-8` (32px left to clear the £ symbol) |
| Step | `25` for general amounts, `1000` for large values, `0.01` for precise |
| Min | `0` (never negative for currency) |

Use `CurrencyInputField.vue` shared component where possible.

**Scroll Prevention:** All `<input type="number">` inputs are protected by a global wheel event handler in `app.js` that blurs the input on scroll. This prevents accidental value changes when users scroll past number fields. No per-component handling is needed.

---

## Percentage Input

Number input with a `%` suffix symbol.

```html
<div class="relative">
  <input
    type="number"
    step="0.01"
    min="0"
    max="100"
    class="input-field pr-8"
    placeholder="e.g., 5.0"
  />
  <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-neutral-500">%</span>
</div>
```

| Element | Specification |
|---------|---------------|
| Suffix | `absolute right-3 top-1/2 -translate-y-1/2 text-neutral-500` |
| Input padding | `pr-8` (32px right to clear the % symbol) |
| Step | `0.01` for rates, `0.1` for rough percentages, `1` for whole percentages |
| Range | `min="0" max="100"` |

---

## Range Slider

Native HTML range input with custom styling.

```html
<label class="block text-sm font-medium text-horizon-500 mb-2">
  Label: {{ formatNumber(value) }}
</label>
<input
  v-model.number="value"
  type="range"
  :min="min"
  :max="max"
  :step="step"
  class="w-full h-2 bg-savannah-200 rounded-lg appearance-none cursor-pointer"
/>
<div class="flex items-center justify-between text-xs text-neutral-500 mt-1">
  <span>{{ minLabel }}</span>
  <span>{{ maxLabel }}</span>
</div>
```

| Element | Specification |
|---------|---------------|
| Track | `h-2 bg-savannah-200 rounded-lg` |
| Appearance | `appearance-none` (removes OS default) |
| Min/Max labels | `text-xs text-neutral-500`, flex justified |
| Live value | Shown in the `<label>` text, updates reactively |

---

## File Upload / Drag-and-Drop

### Drop Zone

```html
<div
  class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors"
  :class="dropZoneClass"
  @dragenter.prevent @dragover.prevent @dragleave.prevent @drop.prevent
>
  <input ref="fileInput" type="file" class="hidden" :accept="acceptString" />
  <p class="text-sm text-neutral-500">Drag & drop your file here, or</p>
  <button type="button" class="text-raspberry-500 font-medium hover:text-raspberry-700">browse</button>
</div>
```

| State | Border & Background |
|-------|-------------------|
| Default | `border-horizon-300 bg-savannah-50 hover:border-horizon-400` |
| Dragging | `border-violet-500 bg-violet-50` |
| File selected | `border-spring-500 bg-spring-50` |
| Error | `border-raspberry-300 bg-raspberry-50` |

### Upload Wizard Steps

3-step indicator: Upload → Processing → Review. Uses numbered circles with connecting lines (same pattern as onboarding steps but smaller).

---

## Autocomplete / Typeahead

Searchable text input with dropdown suggestions.

```html
<div class="relative">
  <input
    v-model="searchQuery"
    type="text"
    class="input-field"
    autocomplete="off"
    @keydown.down.prevent="navigateDown"
    @keydown.up.prevent="navigateUp"
    @keydown.enter.prevent="selectHighlighted"
    @keydown.escape="closeDropdown"
  />
  <!-- Dropdown -->
  <ul v-if="showDropdown" class="absolute z-10 w-full mt-1 bg-white border border-light-gray
      rounded-md shadow-lg max-h-60 overflow-y-auto">
    <li v-for="(item, index) in suggestions"
        class="px-4 py-2 text-body-sm hover:bg-savannah-100 cursor-pointer"
        :class="{ 'bg-savannah-100': index === highlightedIndex }">
      {{ item }}
    </li>
  </ul>
</div>
```

| Element | Specification |
|---------|---------------|
| Dropdown | `absolute z-10 w-full mt-1 bg-white border border-light-gray rounded-md shadow-lg` |
| Max height | `max-h-60 overflow-y-auto` |
| Highlighted item | `bg-savannah-100` |
| Hover | `hover:bg-savannah-100` |
| Debounce | 300ms for API calls |
| Min chars | 3 characters before searching |
| Loading | Inline `animate-spin` spinner inside dropdown |

Used by: `OccupationAutocomplete.vue`, `CountrySelector.vue`.

---

## Risk Level Selector

A 5-button horizontal segmented control using risk-level colours from the design system.

```html
<div class="flex gap-1 sm:gap-2">
  <button
    v-for="level in riskLevels"
    type="button"
    class="flex-1 py-2 px-1 sm:px-3 rounded-lg text-xs sm:text-sm font-medium
           transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1"
    :style="getButtonStyle(level)"
  >
    <span class="hidden sm:inline">{{ level.label }}</span>
    <span class="sm:hidden">{{ level.shortLabel }}</span>
  </button>
</div>
```

| Level | Background (selected) | Text (selected) |
|-------|----------------------|-----------------|
| Low | `bg-yellow-100` | `text-yellow-800` |
| Lower Medium | `bg-pink-100` | `text-pink-800` |
| Medium | `bg-spring-100` | `text-spring-800` |
| Upper Medium | `bg-teal-100` | `text-teal-800` |
| High | `bg-violet-100` | `text-violet-800` |

Selected state adds `ring-2 ring-offset-1`. Unselected buttons use `bg-savannah-50 text-neutral-500 hover:bg-savannah-100`. An info panel slides in below when a level is selected.

---

## Button Group Selector

Styled buttons acting as radio alternatives for priority, certainty, or category selection.

```html
<div class="grid grid-cols-4 gap-2">
  <button
    v-for="option in options"
    type="button"
    class="px-3 py-2 text-sm rounded-md border transition-colors"
    :class="selected === option.value
      ? option.activeClass
      : 'border-horizon-300 text-horizon-500 hover:bg-savannah-100'"
  >
    {{ option.label }}
  </button>
</div>
```

Each option defines its own `activeClass` (e.g., `'bg-raspberry-500 text-white border-raspberry-500'`). These are NOT connected to native `<input type="radio">` elements.

---

## Card Selector

Large clickable cards used instead of a `<select>` when the choice benefits from visual explanation.

```html
<div class="grid grid-cols-3 gap-4">
  <button
    type="button"
    class="p-6 border-2 border-horizon-300 rounded-lg text-center
           hover:border-raspberry-500 hover:bg-raspberry-50 transition-all"
    :class="{ 'border-raspberry-500 bg-raspberry-50': selected }"
  >
    <div class="w-12 h-12 mx-auto mb-3"><!-- Icon --></div>
    <div class="font-semibold text-horizon-500 mb-1">Option Name</div>
    <div class="text-xs text-neutral-500">Short description</div>
  </button>
</div>
```

| State | Classes |
|-------|---------|
| Default | `border-2 border-horizon-300` |
| Hover | `border-raspberry-500 bg-raspberry-50` |
| Selected | `border-raspberry-500 bg-raspberry-50` |

---

## View Toggle (Segmented Control)

A reusable multi-option inline toggle for switching views.

```html
<div class="inline-flex rounded-md shadow-sm" role="group">
  <button
    v-for="(option, index) in options"
    type="button"
    class="px-3 py-2 text-sm font-medium border transition-colors"
    :class="[
      index === 0 ? 'rounded-l-md' : '',
      index === options.length - 1 ? 'rounded-r-md' : '',
      index > 0 ? '-ml-px' : '',
      modelValue === option
        ? 'bg-raspberry-500 text-white border-raspberry-500 z-10'
        : 'bg-white text-horizon-500 border-horizon-300 hover:bg-savannah-100'
    ]"
  >
    {{ option }}
  </button>
</div>
```

| State | Classes |
|-------|---------|
| Active | `bg-raspberry-500 text-white border-raspberry-500 z-10` |
| Inactive | `bg-white text-horizon-500 border-horizon-300 hover:bg-savannah-100` |
| First | `rounded-l-md` |
| Last | `rounded-r-md` |
| Middle | `-ml-px` (overlap borders) |

---

## Sidebar Navigation (Detailed)

The primary authenticated navigation. Uses `Teleport to="body"` and supports collapse/expand.

### Dimensions & States

| State | Width | Content |
|-------|-------|---------|
| Expanded (desktop) | `w-56` (224px) | Icon + label |
| Collapsed (desktop) | `w-16` (64px) | Icon only with `title` tooltip |
| Mobile overlay | `w-56` | Full width, overlay with backdrop |

### Active / Inactive Items

| State | Classes |
|-------|---------|
| Active | `bg-raspberry-50 text-raspberry-700` (icon: `text-raspberry-500`) |
| Inactive | `text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500` |

### Section Labels

- Expanded: `text-[11px] font-semibold uppercase tracking-wider text-horizon-400 px-4 pt-3 pb-1`
- Collapsed: thin `border-t border-light-gray` divider (no text)

### Sections

| Section | Items |
|---------|-------|
| Main | Dashboard, Net Worth |
| Planning | Retirement, Investments, Cash, Protection, Estate Planning, Personal Valuables |
| Advanced | Trusts, Business, Goals, Risk Profile |
| Plans & Actions | Holistic Plan, Plans, Actions |
| Account | User Profile, Valuable Info, Settings |
| Support | Help, Feedback (external), Bug Report (action) |
| Admin | Admin Panel, UK Taxes (admin-only) |

### Mobile Behaviour

- Toggle button: `fixed top-4 left-4 z-[61] sm:hidden` — white rounded with hamburger SVG
- Backdrop: `fixed inset-0 bg-black/50 z-[59] sm:hidden`
- Sidebar: `translate-x-0` (open) / `-translate-x-full` (closed), `z-[60]`
- Escape key closes; backdrop click closes; menu item click closes

### Collapse Toggle

Desktop-only button below logo, double-arrow SVG rotates 180° when collapsed. State persisted to `localStorage` key `sideMenuCollapsed`.

### Logo

- Expanded: `h-28 w-auto` (full wordmark)
- Collapsed: `w-8 h-8` (favicon)

---

## Floating Action Buttons (FABs)

Two FABs fixed to the bottom-right corner.

```html
<button class="fixed bottom-6 z-40 w-14 h-14 rounded-full shadow-lg
               flex items-center justify-center transition-all">
  <!-- Icon -->
</button>
```

| FAB | Position | Colour (closed) | Colour (open) |
|-----|----------|-----------------|----------------|
| AI Chat | `right-24` (96px) | `bg-raspberry-500` | `bg-horizon-500` + `ring-4 ring-horizon-200` |
| Info Guide | `right-6` (24px) | `bg-raspberry-500` | `bg-raspberry-500` + `ring-4 ring-violet-200` |

### Info Guide Badge

Green count badge for missing data items:

```html
<span class="absolute -top-1 -right-1 w-5 h-5 bg-spring-500 rounded-full
             text-xs font-bold flex items-center justify-center shadow">
  {{ count > 9 ? '9+' : count }}
</span>
```

### Visibility

Both hidden on public/auth routes (`/login`, `/register`, `/forgot-password`, `/reset-password`).

---

## Slide-in Panels (Drawers)

### Right-Side Drawer (Info Guide Panel)

```html
<Teleport to="body">
  <Transition
    enter-active-class="transition-all duration-300 ease-out"
    leave-active-class="transition-all duration-200 ease-in"
    enter-from-class="translate-x-full opacity-0"
    enter-to-class="translate-x-0 opacity-100"
    leave-from-class="translate-x-0 opacity-100"
    leave-to-class="translate-x-full opacity-0"
  >
    <div class="fixed right-0 top-0 bottom-0 w-96 max-w-full z-50
                bg-white border-l border-light-gray shadow-lg flex flex-col">
      <!-- Header, content, footer -->
    </div>
  </Transition>
</Teleport>
```

| Element | Specification |
|---------|---------------|
| Width | `w-96` (384px), `max-w-full` on mobile |
| Position | `fixed right-0 top-0 bottom-0` |
| z-index | `z-50` |
| Backdrop | None — page remains interactive |
| Header | `bg-violet-50 border-b` with title + X close |
| Body | `flex-1 overflow-y-auto p-4` |

### Floating Chat Panel (AI Chat)

```html
<div class="fixed bottom-24 right-6 w-[420px] z-[70]
            bg-white rounded-lg border border-light-gray shadow-md"
     style="max-height: calc(100vh - 8rem)">
```

| Element | Specification |
|---------|---------------|
| Width | `w-[420px]` |
| Position | `fixed bottom-24 right-6` (above FAB) |
| z-index | `z-[70]` (highest — above all modals) |
| Transition | Slide up: `translate-y-4 opacity-0` → `translate-y-0 opacity-100` |

---

## Preview Mode Banner

Per-persona gradient banner shown in preview/demo mode.

```html
<div class="bg-gradient-to-r px-4 py-2 text-white text-sm" :class="personaGradient">
  <!-- Content -->
</div>
```

### Persona Colour Mapping

| Persona | Gradient |
|---------|----------|
| young_family | `from-blue-500 to-blue-600` |
| peak_earners | `from-green-500 to-green-600` |
| widow | `from-purple-500 to-purple-600` |
| entrepreneur | `from-fuchsia-500 to-fuchsia-600` |
| young_saver | `from-cyan-500 to-cyan-600` |
| retired_couple | `from-rose-500 to-rose-600` |

### Elements

- Eye icon + "Preview Mode" label
- Persona name display
- Persona selector dropdown
- Spouse toggle button: `bg-white/20 hover:bg-white/30 text-white border border-white/30 rounded-md`
- "Exit Demo" text button
- "Signup Now" CTA: `bg-white text-{persona-color}-600 hover:bg-{persona-color}-50 rounded-md`

---

## Trial Countdown Banner

Shown for users with `status === 'trialing'`.

```html
<div class="bg-violet-50 border-b border-violet-200 px-4 py-2">
  <div class="flex items-center justify-between">
    <div>
      <span class="text-sm font-medium text-violet-800">X days remaining</span>
      <div class="w-full bg-violet-200 rounded-full h-1.5 mt-1">
        <div class="bg-violet-500 h-1.5 rounded-full transition-all duration-500"
             :style="{ width: progress + '%' }"></div>
      </div>
    </div>
    <button class="bg-violet-500 text-white rounded-lg px-3 py-1 text-sm hover:bg-violet-600">
      Upgrade Now
    </button>
    <button v-if="daysRemaining > 2" class="text-violet-400 hover:text-violet-600">✕</button>
  </div>
</div>
```

- Non-dismissable when ≤ 2 days remaining
- "Upgrade Now" opens `PlanSelectionModal`

---

## Toast / Notification System

### Canonical Pattern (Fixed Position)

```html
<div v-if="message" class="fixed top-5 right-5 z-[100] px-5 py-4 rounded-lg
     font-semibold text-sm text-white shadow-lg animate-slideIn"
     :class="type === 'success' ? 'bg-spring-500' : 'bg-raspberry-500'">
  {{ message }}
</div>
```

```css
@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
```

| Variant | Background |
|---------|-----------|
| Success | `bg-spring-500` |
| Error | `bg-raspberry-500` |

Auto-clear after 3–5 seconds via `setTimeout`.

### Inline Alert Pattern (Alternative)

For components that don't need global positioning, use inline dismissible alerts:

```html
<div class="rounded-md bg-spring-50 border border-spring-200 p-4 flex items-start gap-3">
  <svg class="h-5 w-5 text-spring-400 flex-shrink-0"><!-- check icon --></svg>
  <p class="text-sm text-spring-800">{{ message }}</p>
  <button @click="dismiss" class="ml-auto text-spring-400 hover:text-spring-600">✕</button>
</div>
```

---

## Confirm Dialog

A shared 4-type confirmation modal (`ConfirmDialog.vue`).

```html
<div class="fixed inset-0 z-50 overflow-y-auto">
  <div class="fixed inset-0 bg-horizon-500/75"></div>
  <div class="sm:max-w-lg bg-white rounded-lg shadow-xl">
    <div class="flex items-start gap-4 p-6">
      <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="iconBg">
        <!-- Type-specific icon -->
      </div>
      <div>
        <h3 class="text-lg font-semibold text-horizon-500">{{ title }}</h3>
        <p class="text-sm text-neutral-500 mt-2">{{ message }}</p>
      </div>
    </div>
    <div class="px-6 py-4 bg-savannah-50 flex justify-end gap-3">
      <button class="btn-secondary">Cancel</button>
      <button :class="confirmClass">{{ confirmLabel }}</button>
    </div>
  </div>
</div>
```

| Type | Icon Background | Icon Colour | Confirm Button |
|------|----------------|-------------|----------------|
| `danger` | `bg-raspberry-100` | `text-raspberry-600` | `bg-error-600 text-white` |
| `warning` | `bg-violet-100` | `text-violet-600` | `bg-violet-600 text-white` |
| `info` | `bg-violet-100` | `text-violet-600` | `bg-raspberry-500 text-white` |
| `success` | `bg-spring-100` | `text-spring-600` | `bg-spring-600 text-white` |

**Note:** The `warning` type must use `bg-violet-100` (not `bg-yellow-100`) per the forbidden amber/orange rule.

---

## Guidance Tooltip (Walkthrough)

Floating positioned tooltip for interactive guidance walkthroughs.

### Structure

```html
<Teleport to="body">
  <Transition enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
    <div class="fixed z-50 bg-white rounded-xl shadow-2xl border border-light-gray max-w-xs p-4">
      <div class="text-sm text-horizon-500 mb-3">{{ stepContent }}</div>
      <div class="flex items-center justify-between">
        <!-- Progress dots -->
        <div class="flex gap-1">
          <div v-for="i in totalSteps" class="w-1.5 h-1.5 rounded-full"
               :class="i <= currentStep ? 'bg-raspberry-500' : 'bg-savannah-200'"></div>
        </div>
        <!-- Actions -->
        <div class="flex gap-2">
          <button class="text-xs text-neutral-500">Skip</button>
          <button class="text-xs text-raspberry-500 font-medium">Next</button>
        </div>
      </div>
    </div>
  </Transition>
</Teleport>
```

### Arrow

A `w-3 h-3 bg-white` rotated 45° square with matching border, positioned on the appropriate side.

### Target Highlight

```css
.guidance-highlight {
  position: relative;
  z-index: 40;
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.3);
  border-radius: 8px;
  animation: pulse 2s infinite;
}
```

### Placement Logic

Prefers bottom → top → right → left based on available viewport space. Repositions on scroll/resize via `ResizeObserver`.

---

## Progress Bars

### Standard Progress Bar

```html
<div class="w-full bg-savannah-200 rounded-full" :class="trackHeight">
  <div class="rounded-full transition-all duration-500" :class="[fillColour, trackHeight]"
       :style="{ width: percentage + '%' }"></div>
</div>
```

### Sizes

| Size | Track Class |
|------|------------|
| Small (thin) | `h-1.5` |
| Medium | `h-2.5` |
| Large | `h-4` |

### Colour Variants

| Variant | Fill Colour | Usage |
|---------|------------|-------|
| Auto (default) | Green ≥100%, blue on-track | Goal progress |
| Success | `bg-spring-500` | Completed items |
| Warning | `bg-violet-500` | Approaching limits |
| Danger | `bg-raspberry-500` | Over limit |
| Info | `bg-violet-500` | Neutral progress |

### Multi-Segment Bar (ISA Allowance)

```html
<div class="w-full bg-savannah-200 rounded-full h-3">
  <div class="h-full flex rounded-full overflow-hidden">
    <div class="bg-violet-500 h-full" :style="{ width: segment1 + '%' }"></div>
    <div class="bg-purple-500 h-full" :style="{ width: segment2 + '%' }"></div>
    <div class="bg-spring-500 h-full" :style="{ width: segment3 + '%' }"></div>
  </div>
</div>
```

### Milestone Markers

Add `relative` to track and absolute white dividers at 25/50/75%:

```html
<div class="absolute top-0 bottom-0 w-0.5 bg-white" style="left: 25%"></div>
```

---

## Gauge / Radial Charts

ApexCharts `radialBar` type used for emergency fund, coverage adequacy, and IHT liability.

### Standard Configuration

```js
{
  chart: { type: 'radialBar', height: 300 },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: { size: '70%', dropShadow: { enabled: true, blur: 3 } },
      track: { background: BORDER_COLORS.default },
      dataLabels: {
        name: { fontSize: '14px', color: '#6B7280', offsetY: 20 },
        value: { fontSize: '36px', fontWeight: 'bold', offsetY: -15 }
      }
    }
  }
}
```

### Threshold Colours

| Condition | Colour | Usage |
|-----------|--------|-------|
| Good (≥ threshold) | `SUCCESS_COLORS[500]` | Sufficient coverage/fund |
| Caution (middle) | `WARNING_COLORS[500]` (blue) | Approaching limit |
| Critical (< threshold) | `ERROR_COLORS[500]` | Insufficient |

---

## Milestone Tracker

Visual milestone track for goal progress.

```html
<div class="relative flex items-center">
  <!-- Connecting line -->
  <div class="absolute h-1 bg-savannah-200 left-0 right-0 top-1/2 -translate-y-1/2"></div>
  <div class="absolute h-1 bg-gradient-to-r from-violet-500 to-spring-500"
       :style="{ width: progressPercent + '%' }"></div>
  <!-- Milestone circles -->
  <div v-for="milestone in milestones" class="relative z-10">
    <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center"
         :class="milestone.reached
           ? 'bg-spring-100 border-spring-500 text-spring-600'
           : 'bg-white border-horizon-300 text-horizon-400'">
      <!-- Check or target icon -->
    </div>
    <span class="text-xs text-center mt-1">{{ milestone.label }}</span>
  </div>
</div>
```

### Compact Mode

Row of small dots: `w-2 h-2 rounded-full` — `bg-spring-500` (reached) / `bg-savannah-200` (unreached).

---

## Contribution Streak Meter

12-segment circular meter showing monthly contribution consistency.

| Streak | Background | Colour | Emoji |
|--------|-----------|--------|-------|
| 0 months | `bg-savannah-50` | `text-horizon-400` | ❄️ |
| 1–2 months | `bg-savannah-50` | `text-savannah-600` | ✨ |
| 3–5 months | `bg-violet-50` | `text-violet-500` | 🔥 |
| 6–11 months | `bg-violet-50` | `text-violet-600` + `animate-ping` ring | 🔥 |
| 12+ months | `bg-violet-50` | `text-violet-600` | 🏆 |

---

## Data Retention Overlay

Non-dismissable blocking overlay shown when subscription expires.

```html
<div class="fixed inset-0 bg-horizon-500/60 z-40"></div>
<div class="fixed inset-0 z-50 flex items-center justify-center">
  <div class="max-w-lg bg-white rounded-lg p-8 shadow-2xl">
    <!-- Clock icon in bg-violet-100 -->
    <h2 class="text-xl font-bold text-horizon-500">Your Subscription Has Expired</h2>
    <!-- Countdown: days / hours / minutes -->
    <div class="bg-savannah-50 border border-light-gray rounded-lg p-4">
      <span class="text-3xl font-bold text-horizon-500">{{ days }}</span>
      <span class="text-xs text-neutral-500">Days</span>
    </div>
    <!-- Subscribe CTA -->
    <a href="/checkout" class="btn-primary w-full py-3 text-base font-semibold block text-center">
      Subscribe Now
    </a>
    <!-- Delete All Data (danger path) -->
  </div>
</div>
```

**No close button.** No backdrop click dismiss. User must subscribe or delete data.

Delete confirmation requires typing `DELETE` + entering password. Delete button: `bg-error-600` when valid, `bg-horizon-300 cursor-not-allowed` when incomplete.

---

## Print Styles

Used for generating printed financial plans.

### Print Header

```html
<div class="print-header">
  <img :src="logoImage" alt="Fynla" class="print-logo" />
  <div class="print-title">{{ title }}</div>
  <div class="print-date">{{ formattedDate }}</div>
</div>
```

```css
.print-logo { height: 48px; }
.print-header { display: flex; align-items: center; border-bottom: 2px solid #E5E7EB; padding-bottom: 16px; }

@media print {
  .no-print { display: none !important; }
  body { background: white; }
}
```

---

## Wealth Summary Grid

CSS Grid-based financial ledger for Net Worth display.

### Grid Structure

```css
/* Single user */
.wealth-grid { grid-template-columns: 1fr auto; }

/* With spouse */
.wealth-grid { grid-template-columns: minmax(0, 1fr) repeat(3, auto); }
/* Columns: Label | User Value | Spouse Value | Combined Total */
```

### Row Highlights

| Row Type | Classes |
|----------|---------|
| Standard row | `hover:bg-savannah-100 transition` |
| Total Assets | `bg-spring-100 border border-spring-500` |
| Total Liabilities | `bg-raspberry-100 border border-raspberry-500` |
| Net Worth | `bg-gradient-to-br from-sky-50 to-white border-2 border-raspberry-500` |
| Combined total column | `bg-violet-50` |

### Value Cells

```html
<span class="bg-savannah-50 rounded-md px-4 py-2 font-semibold text-right">
  {{ formatCurrency(value) }}
</span>
```

Net worth positive: `text-spring-500`. Negative: `text-raspberry-500`.

---

## Modal Patterns (Three Generations)

The codebase has three modal patterns. **New modals must use Generation 2.**

### Generation 1 (Legacy — do not use for new modals)

```html
<div class="fixed inset-0 z-50">
  <div class="fixed inset-0 bg-horizon-500/75"></div>
  <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
  <div class="inline-block align-bottom bg-white rounded-lg shadow-xl
              sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
```

### Generation 1.5 (Transitional)

```html
<div class="fixed inset-0 z-50 overflow-y-auto">
  <div class="flex min-h-screen items-center justify-center p-4">
    <div class="fixed inset-0 bg-horizon-500/75"></div>
    <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl">
```

### Generation 2 (Canonical — use for all new modals)

```html
<Teleport to="body">
  <Transition enter-active-class="ease-out duration-300" leave-active-class="ease-in duration-200"
    enter-from-class="opacity-0" enter-to-class="opacity-100"
    leave-from-class="opacity-100" leave-to-class="opacity-0">
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-horizon-500/75 backdrop-blur-sm" @click="close"></div>
      <div class="flex min-h-full items-center justify-center p-4">
        <Transition enter-active-class="ease-out duration-300" leave-active-class="ease-in duration-200"
          enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
          leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
          <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden"
               @click.stop>
            <!-- Modal content -->
          </div>
        </Transition>
      </div>
    </div>
  </Transition>
</Teleport>
```

| Element | Gen 1 | Gen 2 (Canonical) |
|---------|-------|-------------------|
| Teleport | No | `to="body"` |
| Backdrop | `bg-horizon-500/75` (legacy) | `bg-horizon-500/75 backdrop-blur-sm` |
| Panel radius | `rounded-lg` | `rounded-2xl` |
| Shadow | `shadow-xl` | `shadow-2xl` |
| Transitions | None | Dual nested (fade + scale) |
| Click-outside | Manual handler | `@click` on backdrop |

---

## Event Icon Overlay System

Floating circular event icons positioned over charts to mark goals and life events.

### Icon Badge

```html
<div class="rounded-full border border-white/30 shadow-sm flex items-center justify-center"
     :style="{ backgroundColor: event.color, width: '26px', height: '26px' }">
  <svg class="text-white" :width="16" :height="16" stroke="white" stroke-width="1.5">
    <!-- Heroicon path from eventIconSvgs.js -->
  </svg>
</div>
```

| Property | Value |
|----------|-------|
| Icon size | `26px` |
| Icon gap (stacked) | `8px` vertical |
| Float gap above bar | `20px` |
| Hover | `transform hover:scale-110` |
| Completed opacity | `0.4` |
| Pointer events | `auto` (ensures click interaction) |

### Connector Line

1px vertical coloured line from icon centre down to chart bar top.

### Tooltip

Dark tooltip (`bg-horizon-500 text-white rounded-lg shadow-lg p-3 max-w-xs`) positioned via `transform: translate(-50%, -100%)` above the icon. Shows event name, amount (income=green/expense=red), age, year, certainty, type badge.

---

## Persona Selection Cards

Grid of persona cards for the demo/preview flow.

```html
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <button class="rounded-xl border-2 overflow-hidden hover:-translate-y-0.5
                 hover:shadow-lg hover:border-horizon-300 transition-all">
    <!-- Gradient header -->
    <div class="bg-gradient-to-br px-4 py-3" :class="personaGradient">
      <div class="w-12 h-12 bg-white/20 rounded-full"><!-- Emoji --></div>
      <h3 class="text-lg font-bold text-white">{{ persona.name }}</h3>
    </div>
    <!-- White body -->
    <div class="p-4 bg-white">
      <p class="text-sm text-neutral-500 line-clamp-2">{{ persona.description }}</p>
      <span class="px-2 py-1 rounded-md text-xs font-medium bg-savannah-100 text-horizon-500">
        Net Worth: {{ netWorth }}
      </span>
    </div>
  </button>
</div>
```

### Per-Persona Gradients

| Persona | Gradient |
|---------|----------|
| young_family | `from-blue-500 to-blue-700` |
| peak_earners | `from-green-500 to-green-700` |
| widow | `from-purple-500 to-purple-700` |
| entrepreneur | `from-fuchsia-500 to-fuchsia-700` |
| young_saver | `from-cyan-500 to-cyan-700` |
| retired_couple | `from-rose-500 to-rose-700` |

---

## Billing Toggle

Monthly/yearly pricing toggle on the pricing page.

```html
<div class="bg-white/10 backdrop-blur-md rounded-full p-1.5 border border-white/20 inline-flex">
  <button
    v-for="period in ['monthly', 'yearly']"
    class="px-4 py-2 text-sm font-medium rounded-full transition-all"
    :class="selected === period
      ? 'bg-white text-horizon-500 shadow-md'
      : 'text-white/70 hover:text-white'"
  >
    {{ period }}
    <span v-if="period === 'yearly'" class="text-xs text-spring-600 font-semibold ml-1">
      Save {{ savingsPercent }}%
    </span>
  </button>
</div>
```

---

## In-Page Tab Navigation

Standard tab navigation pattern used across multiple views.

### Canonical Pattern

```html
<div class="border-b border-light-gray">
  <nav class="-mb-px flex overflow-x-auto scrollbar-hide">
    <button
      v-for="tab in tabs"
      class="whitespace-nowrap py-3 px-3 font-medium text-sm flex-shrink-0 border-b-2 transition-colors"
      :class="activeTab === tab.id
        ? 'border-raspberry-500 text-raspberry-700'
        : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300'"
    >
      {{ tab.label }}
    </button>
  </nav>
</div>
```

| State | Classes |
|-------|---------|
| Active | `border-b-2 border-raspberry-500 text-raspberry-700` |
| Inactive | `border-b-2 border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300` |
| Container | `-mb-px flex overflow-x-auto scrollbar-hide` |

### With Icons (Mobile-Responsive)

```html
<button class="...">
  <svg class="w-5 h-5 sm:hidden"><!-- Icon only on mobile --></svg>
  <span class="hidden sm:inline">{{ tab.label }}</span>
  <span class="sm:hidden">{{ tab.shortLabel }}</span>
</button>
```

### URL Sync (Optional)

For tabs that should be linkable, update the URL on tab change:

```js
this.$router.replace({ query: { section: newTab } });
```

---

## User Dropdown Menu

Navbar user dropdown with avatar, links, and actions.

```html
<div class="relative">
  <button class="flex items-center gap-2 bg-savannah-100 hover:bg-savannah-200 rounded-button px-3 py-2">
    <span class="text-sm font-medium text-horizon-500">{{ userName }}</span>
    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }">
      <!-- Chevron -->
    </svg>
  </button>
  <!-- Dropdown -->
  <div v-if="open" class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white
       ring-1 ring-black ring-opacity-5 z-50">
    <div class="py-1">
      <a class="flex items-center gap-3 px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100">
        <svg class="w-4 h-4 text-horizon-400"><!-- Icon --></svg>
        Link Label
      </a>
      <div class="border-t border-savannah-100"></div>
      <!-- More links -->
    </div>
  </div>
</div>
```

### Animation

```
enter: ease-out duration-100, opacity-0 scale-95 → opacity-100 scale-100
leave: ease-in duration-75, opacity-100 scale-100 → opacity-0 scale-95
```

---

## Search Input

Text input with a search icon prefix.

```html
<div class="relative">
  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-horizon-400">
    <!-- Search/magnifying glass icon -->
  </svg>
  <input
    type="text"
    class="input-field pl-10"
    placeholder="Search..."
  />
</div>
```

| Element | Specification |
|---------|---------------|
| Icon | `absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-horizon-400` |
| Input padding | `pl-10` (40px left to clear icon) |

---

## Pagination

### Desktop Pagination

```html
<div class="flex items-center justify-between px-4 py-3 bg-white border-t border-light-gray">
  <p class="text-sm text-horizon-500">
    Showing <span class="font-medium">{{ from }}</span> to
    <span class="font-medium">{{ to }}</span> of
    <span class="font-medium">{{ total }}</span> results
  </p>
  <nav class="flex gap-1">
    <button class="px-3 py-1 text-sm border border-horizon-300 rounded-md
                   hover:bg-savannah-100 disabled:opacity-50">Previous</button>
    <button v-for="page in pages"
            class="px-3 py-1 text-sm border rounded-md"
            :class="page === current
              ? 'bg-raspberry-500 text-white border-raspberry-500'
              : 'border-horizon-300 hover:bg-savannah-100'">
      {{ page }}
    </button>
    <button class="px-3 py-1 text-sm border border-horizon-300 rounded-md
                   hover:bg-savannah-100 disabled:opacity-50">Next</button>
  </nav>
</div>
```

### Mobile Pagination

Simplified previous/next only (no page numbers):

```html
<div class="flex justify-between px-4 py-3">
  <button class="btn-secondary text-sm">← Previous</button>
  <span class="text-sm text-neutral-500">Page {{ current }} of {{ total }}</span>
  <button class="btn-secondary text-sm">Next →</button>
</div>
```

---

## Sortable Table Headers

```html
<th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider
           cursor-pointer hover:bg-savannah-100 select-none"
    @click="sort(column)">
  {{ column.label }}
  <span v-if="sortBy === column.key" class="ml-1">
    {{ sortDir === 'asc' ? '↑' : '↓' }}
  </span>
</th>
```

| State | Classes |
|-------|---------|
| Default | `cursor-pointer hover:bg-savannah-100` |
| Sorted ascending | Append `↑` text |
| Sorted descending | Append `↓` text |
| Not sortable | Remove `cursor-pointer hover:bg-savannah-100` |

---

## Expandable Table Rows

```html
<tr class="cursor-pointer hover:bg-savannah-100" @click="toggleRow(index)">
  <td class="px-4 py-3">
    <svg class="w-4 h-4 text-horizon-400 transition-transform"
         :class="{ 'rotate-90': expanded.includes(index) }">
      <!-- Chevron right icon -->
    </svg>
  </td>
  <!-- Other cells -->
</tr>
<!-- Expanded detail row -->
<tr v-if="expanded.includes(index)">
  <td :colspan="columnCount" class="bg-savannah-50 px-8 py-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <!-- Detail fields -->
    </div>
  </td>
</tr>
```

| Element | Specification |
|---------|---------------|
| Toggle icon | `w-4 h-4 text-horizon-400 rotate-90` when expanded |
| Detail row background | `bg-savannah-50` |
| Detail padding | `px-8 py-4` (indented from parent row) |
| Detail layout | `grid grid-cols-2 md:grid-cols-4 gap-4` |

---

## Inline Edit Pattern

View/edit toggle for form sections.

### View Mode

```html
<div class="bg-white rounded-lg border border-light-gray p-6">
  <div class="flex justify-between items-center mb-4">
    <h3 class="text-lg font-semibold text-horizon-500">Section Title</h3>
    <button class="btn-secondary text-sm" @click="isEditing = true">Edit</button>
  </div>
  <div class="space-y-3">
    <div class="flex justify-between">
      <span class="text-body-sm text-neutral-500">Label</span>
      <span class="text-body-sm text-horizon-500">{{ value || '—' }}</span>
    </div>
  </div>
</div>
```

### Edit Mode

```html
<div class="bg-white rounded-lg border border-light-gray p-6">
  <div class="space-y-4">
    <div>
      <label class="label">Label</label>
      <input v-model="form.field" class="input-field" />
    </div>
  </div>
  <div class="flex justify-end gap-3 mt-6">
    <button class="btn-secondary" @click="isEditing = false">Cancel</button>
    <button class="btn-primary">Save</button>
  </div>
</div>
```

Empty values display as `'—'` (em dash).

---

## Avatar / User Representation

Fynla does not use photo avatars. User identity is represented through these patterns:

### Persona Emoji (Preview Mode)

```html
<div class="w-10 h-10 rounded-full flex items-center justify-center"
     :class="personaBgClass">
  <span class="text-lg">{{ personaEmoji }}</span>
</div>
```

| Persona | Emoji |
|---------|-------|
| young_family | 👨‍👩‍👧 |
| peak_earners | 💼 |
| widow | 🌹 |
| entrepreneur | 🚀 |
| young_saver | 🎯 |
| retired_couple | 🏖️ |

### User Name Display

```html
<span class="text-sm font-medium text-horizon-500">{{ userName }}</span>
```

Shown in the Navbar dropdown trigger button. No initials circle or generated avatar — text only.

### Profile Image Placeholder

If a profile image feature is added in future, use:

```html
<div class="w-12 h-12 rounded-full bg-raspberry-100 flex items-center justify-center">
  <span class="text-raspberry-700 font-semibold text-lg">{{ initials }}</span>
</div>
```

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

## Global CSS Utilities (`app.css`)

The following utility classes are defined globally in `resources/css/app.css`. **Always use these instead of defining equivalent styles in `<style scoped>` blocks.**

### Scrollbar Utilities

| Class | Purpose |
|-------|---------|
| `.scrollbar-hide` | Completely hides scrollbar (all browsers) |
| `.scrollbar-thin` | Thin 6px scrollbar with horizon-300 thumb — for modals, panels, sidebars |

### Animation Utilities

| Class | Effect |
|-------|--------|
| `.animate-fade-in` | Fade in (opacity 0→1, 300ms ease-out) |
| `.animate-fade-in-slide` | Fade in + slide up 10px (300ms ease-out) |

Tailwind's built-in `animate-spin` replaces all custom spinner keyframes. Use:
```html
<div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
```

### Back Button

| Class | Purpose |
|-------|---------|
| `.detail-inline-back` | Standard back button for inline detail views |

### Expand/Accordion Transitions

Vue `<transition name="expand">` uses global classes:
- `.expand-enter-active`, `.expand-leave-active` — 200ms ease
- `.expand-enter-from`, `.expand-leave-to` — opacity 0, max-height 0

### Range Slider

All `input[type="range"]` elements are globally styled with `raspberry-500` thumb. No scoped CSS needed.

### Badge Classes

All badge styles (account type, ownership, status, priority, risk, tax) are in `app.css @layer components`. Use classes like `.badge-isa`, `.badge-active`, `.badge-high-priority`, `.badge-risk-low`, `.badge-tax-free`.

### Card Variants

| Class | Usage |
|-------|-------|
| `.card` | Standard card (white, rounded-card, border, shadow-sm, p-6) |
| `.card-lg` | Large card container (rounded-xl, p-8) |
| `.card-sm` | Small/compact card (rounded-lg, p-4) |
| `.card-hover` | Clickable card with hover lift effect |
| `.card-highlighted` | Primary accent background |
| `.card-success` / `.card-warning` / `.card-error` | Semantic card variants |

---

---

## Dynamic Financial Values

**CRITICAL: Never hardcode tax years, allowances, thresholds, or rates anywhere in the UI.**

The UK tax year changes every 6 April. All financial values must be dynamic.

### Tax Year Display

Use `getCurrentTaxYear()` from `@/utils/dateFormatter` to display the current tax year string (e.g., "2025/26"). This function automatically calculates the correct year based on the UK April 6 boundary.

```javascript
// In Vue Options API components
import { getCurrentTaxYear } from '@/utils/dateFormatter';

computed: {
  currentTaxYear() {
    return getCurrentTaxYear();
  }
}
```

```html
<!-- Template usage -->
<h3>ISA Allowance {{ currentTaxYear }}</h3>
<p>Annual allowance for the {{ currentTaxYear }} tax year</p>
```

```javascript
// In static config files (called at module load time)
import { getCurrentTaxYear } from '@/utils/dateFormatter';
const TAX_YEAR = getCurrentTaxYear();

const config = {
  label: `Annual pension allowance (${TAX_YEAR})`
};
```

### Tax Values

Import from `@/constants/taxConfig` — never hardcode amounts:

```javascript
import { ISA_ANNUAL_ALLOWANCE, PENSION_ANNUAL_ALLOWANCE, CGT_ANNUAL_ALLOWANCE,
         PERSONAL_ALLOWANCE, HIGHER_RATE_THRESHOLD } from '@/constants/taxConfig';
```

| Instead of | Use |
|-----------|-----|
| `20000` (ISA) | `ISA_ANNUAL_ALLOWANCE` |
| `60000` (pension) | `PENSION_ANNUAL_ALLOWANCE` |
| `3000` (CGT) | `CGT_ANNUAL_ALLOWANCE` |
| `12570` (personal allowance) | `PERSONAL_ALLOWANCE` |
| `50270` (higher rate) | `HIGHER_RATE_THRESHOLD` |
| `125140` (additional rate) | `ADDITIONAL_RATE_THRESHOLD` |

### Backend (PHP)

Use `TaxConfigService` for all tax values:

```php
$taxYear = $this->taxConfig->getTaxYear();
$isaAllowance = $this->taxConfig->getISAAllowances()['annual'];
$nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'];
```

---

## Number Input Scroll Prevention

A global event handler in `app.js` prevents mouse wheel scroll from changing `<input type="number">` values. This is a common source of accidental data corruption — users scrolling the page inadvertently change field values when their cursor passes over a number input.

**Implementation:**

```javascript
// In app.js — global, no per-component handling needed
document.addEventListener('wheel', (e) => {
    if (e.target?.type === 'number') {
        e.target.blur();
    }
}, { passive: true });
```

**Rules:**
- Do NOT add `@wheel.prevent` on individual number inputs — the global handler covers all of them
- Do NOT use CSS-only solutions (`-moz-appearance: textfield`) — they don't prevent all cases
- The `{ passive: true }` option ensures scroll performance is not affected

---

## Clickable Card CTA Pattern

When a card navigates to a detail view or tab on click, include a visible CTA link to make the interaction discoverable. Users may not realise a card is clickable even with `cursor: pointer` and hover effects.

```html
<div class="planner-card clickable" @click="navigateToDetail">
  <div class="planner-card-header">
    <h3 class="planner-card-title">Card Title</h3>
  </div>
  <div class="planner-card-metrics">
    <!-- Metric values -->
  </div>
  <div class="planner-card-cta">
    <span class="view-detail-link">
      View full breakdown
      <svg class="w-4 h-4 inline" ...arrow icon... />
    </span>
  </div>
</div>
```

```css
.planner-card-cta {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
}

.view-detail-link {
  font-size: 13px;
  font-weight: 500;
  @apply text-raspberry-500;
  display: flex;
  align-items: center;
  gap: 4px;
}

.planner-card:hover .view-detail-link {
  @apply text-raspberry-600;
}
```

**When to use:** Any card that navigates somewhere on click but doesn't have an obvious button or link. The CTA text should describe what the user will see (e.g., "View income breakdown including all pensions and assets").

---

*This design system is a living document. Propose changes through the standard PR process with justification for any additions or modifications.*
