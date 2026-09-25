# Palette and Tailwind conversion (Phases 3–4)

## Contents
- Phase 3 — Fynla Design Palette (CSS variables — source of truth is `global.css`)
- Phase 4 — Tailwind-to-CSS conversion reference

## Phase 3 — Fynla Design Palette (CSS variables — source of truth is `global.css`)

**Read `public/pages/css/global.css` before every page.** The complete `:root` block is there. Key tokens:

```css
:root {
  /* Raspberry — CTAs, errors, highlights */
  --raspberry-300: #F472B6;
  --raspberry-400: #EC4899;
  --raspberry-500: #E83E6D;
  --raspberry-600: #DB2777;

  /* Horizon — text, nav, headings */
  --horizon-100: #F1F5F9;
  --horizon-200: #E2E8F0;
  --horizon-300: #CBD5E1;
  --horizon-400: #94A3B8;
  --horizon-500: #1F2A44;
  --horizon-600: #0F172A;
  --horizon-700: #020617;

  /* Spring — success, positive CTAs */
  --spring-400: #34D399;
  --spring-500: #20B486;
  --spring-600: #059669;
  --spring-700: #047857;

  /* Violet — warnings, focus */
  --violet-500: #5854E6;

  /* Savannah — hover, subtle backgrounds */
  --savannah-100: #FDFAF7;
  --savannah-200: #FAF5F0;
  --savannah-300: #F5EDE5;
  --savannah-400: #EFDCD1;
  --savannah-500: #E6C9A8;

  /* Eggshell — page background */
  --eggshell-500: #F7F6F4;

  /* Neutrals */
  --neutral-400: #9CA3AF;
  --neutral-500: #717171;
  --neutral-600: #4B5563;

  /* Light Blue */
  --light-blue-100: #DDE2EF;
  --light-blue-500: #6C83BC;

  /* Light Pink */
  --light-pink-50:  #FDF0F4;
  --light-pink-100: #FAD6E0;
  --light-pink-200: #F5B3C5;

  /* Utility */
  --light-gray: #EEEEEE;
  --white:      #FFFFFF;

  /* Alpha variants — define in :root, never inline rgba() */
  --white-80:       rgba(255, 255, 255, 0.80);
  --white-70:       rgba(255, 255, 255, 0.70);
  --white-40:       rgba(255, 255, 255, 0.40);
  --white-30:       rgba(255, 255, 255, 0.30);
  --horizon-500-30: rgba(31,  42,  68,  0.30);
  --black-05:       rgba(0,   0,   0,   0.05);
  --black-10:       rgba(0,   0,   0,   0.10);

  /* Typography */
  --font-primary: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;

  /* Radius */
  --radius-sm: 0.375rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 0.75rem;
  --radius-2xl: 1rem;
  --radius-button: 0.5rem;
  --radius-full: 9999px;

  /* Shadows */
  --shadow-sm: 0 1px 2px 0 var(--black-05);
  --shadow-lg: 0 10px 15px -3px var(--black-10), 0 4px 6px -4px var(--black-10);
}
```

Do NOT redefine `:root` in the page CSS file — `global.css` already contains it. Only add tokens to
the page CSS file if a new alpha variant is needed that isn't in `global.css`.

## Phase 4 — Tailwind-to-CSS conversion reference

| Tailwind | CSS |
|---|---|
| `font-black` | `font-weight: 900` |
| `font-bold` | `font-weight: 700` |
| `font-semibold` | `font-weight: 600` |
| `font-medium` | `font-weight: 500` |
| `tracking-widest` | `letter-spacing: 0.1em` |
| `leading-tight` | `line-height: 1.25` |
| `leading-relaxed` | `line-height: 1.625` |
| `leading-none` | `line-height: 1` |
| `truncate` | `overflow: hidden; text-overflow: ellipsis; white-space: nowrap` |
| `line-clamp-2` | `-webkit-line-clamp: 2; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden` |
| `transition-colors` | `transition: color 0.15s ease, background-color 0.15s ease, border-color 0.15s ease` |
| `shadow-sm` | `box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)` |
| `shadow-lg` | `box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)` |
| `rounded-xl` | `border-radius: 0.75rem` |
| `rounded-2xl` | `border-radius: 1rem` |
| `object-cover` | `object-fit: cover` |
| `aspect-[16/9]` | `aspect-ratio: 16/9` |
| `group-hover:` | CSS `:hover` on the parent with a descendant selector |
| `sm:` | `@media (min-width: 640px)` |
| `md:` | `@media (min-width: 768px)` |
| `lg:` | `@media (min-width: 1024px)` |
| `xl:` | `@media (min-width: 1280px)` |
