# CLS Fixes, Sitemap Update & Fyn Image WebP Conversion

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate Cumulative Layout Shift (CLS) on the homepage by adding explicit dimensions to all images, convert the Fyn character image to WebP for ~70% size reduction, and update the sitemap with all public routes.

**Architecture:** Add `width`/`height` attributes to every `<img>` tag on public pages to reserve layout space before images load. Convert the 2.8MB Fyn PNG to WebP using `cwebp`. Update `sitemap.xml` with all public routes from the Vue router.

**Tech Stack:** Vue.js 3, Laravel Blade, cwebp CLI, static XML

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `resources/js/views/Public/LandingPage.vue` | Modify | Add width/height to hero image and Fyn character image |
| `resources/js/layouts/PublicLayout.vue` | Modify | Add width/height to nav logo and footer logo |
| `public/images/Fyn/Design Character 001a.webp` | Create | WebP version of Fyn character (replaces PNG reference) |
| `public/sitemap.xml` | Modify | Add missing public routes, update lastmod dates |

---

### Task 1: Add width/height to hero desktop image (LandingPage.vue)

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:52-56`

The hero desktop image (1315×489 actual pixels) renders full-width via `w-full h-auto`. Adding explicit `width` and `height` lets the browser calculate aspect ratio before the image loads, preventing layout shift.

- [ ] **Step 1: Add width and height attributes**

Change line 52-56 from:
```html
<img
  src="/images/Website/Homepage-Header-Desktop.png"
  alt="Fynla Brain — your financial planning intelligence"
  class="w-full h-auto block"
/>
```
To:
```html
<img
  src="/images/Website/Homepage-Header-Desktop.png"
  alt="Fynla Brain — your financial planning intelligence"
  width="1315"
  height="489"
  class="w-full h-auto block"
/>
```

---

### Task 2: Convert Fyn character image to WebP and add dimensions (LandingPage.vue)

**Files:**
- Modify: `resources/js/views/Public/LandingPage.vue:111`
- Create: `public/images/Fyn/Design Character 001a.webp`

The Fyn character PNG is 2.8MB. Converting to WebP should bring it under 500KB. The image renders at `h-[427px] w-auto` — actual dimensions are 1706×2250, aspect ratio ≈ 0.758, so at 427px height the rendered width is ~324px.

- [ ] **Step 1: Convert PNG to WebP**

Run:
```bash
cwebp -q 80 "public/images/Fyn/Design Character 001a.png" -o "public/images/Fyn/Design Character 001a.webp"
```

If `cwebp` is not installed, use an alternative:
```bash
npx sharp-cli -i "public/images/Fyn/Design Character 001a.png" -o "public/images/Fyn/Design Character 001a.webp" --format webp --quality 80
```

Expected: Output file should be ~200-500KB (vs 2.8MB PNG).

- [ ] **Step 2: Update image src and add dimensions**

Change line 111 from:
```html
<img src="/images/Fyn/Design Character 001a.png" alt="Fyn — your AI financial companion" loading="lazy" class="h-[427px] w-auto lg:-mb-[3em]" />
```
To:
```html
<img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" class="h-[427px] w-auto lg:-mb-[3em]" />
```

---

### Task 3: Add width/height to nav and footer logos (PublicLayout.vue)

**Files:**
- Modify: `resources/js/layouts/PublicLayout.vue:10,323`

Both logos are 1760×795 actual pixels, rendered at `h-14` (56px) height. Aspect ratio = 1760/795 ≈ 2.214, so rendered width ≈ 124px.

- [ ] **Step 1: Add dimensions to nav logo**

Change line 10 from:
```html
<img :src="logoUrl" alt="Fynla" class="h-14 w-auto" />
```
To:
```html
<img :src="logoUrl" alt="Fynla" width="124" height="56" class="h-14 w-auto" />
```

- [ ] **Step 2: Add dimensions to footer logo**

Change line 323 from:
```html
<img :src="footerLogoUrl" alt="Fynla" class="h-14 w-auto" />
```
To:
```html
<img :src="footerLogoUrl" alt="Fynla" width="124" height="56" class="h-14 w-auto" />
```

---

### Task 4: Update sitemap with all public routes

**Files:**
- Modify: `public/sitemap.xml`

Current sitemap is missing: `/pricing`, `/about`, `/terms`, `/privacy`, `/advisors`. All `lastmod` dates are stale (2026-01-16). Update to today's date for changed pages.

- [ ] **Step 1: Rewrite sitemap.xml**

Replace entire contents with:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://fynla.org/</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://fynla.org/pricing</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://fynla.org/about</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc>https://fynla.org/calculators</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://fynla.org/learning-centre</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://fynla.org/advisors</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc>https://fynla.org/security</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
  <url>
    <loc>https://fynla.org/terms</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.4</priority>
  </url>
  <url>
    <loc>https://fynla.org/privacy</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.4</priority>
  </url>
  <url>
    <loc>https://fynla.org/sitemap</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.3</priority>
  </url>
  <url>
    <loc>https://fynla.org/login</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.3</priority>
  </url>
  <url>
    <loc>https://fynla.org/register</loc>
    <lastmod>2026-03-19</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.3</priority>
  </url>
</urlset>
```

---

### Task 5: Commit

- [ ] **Step 1: Stage and commit all changes**

```bash
git add resources/js/views/Public/LandingPage.vue \
      resources/js/layouts/PublicLayout.vue \
      "public/images/Fyn/Design Character 001a.webp" \
      public/sitemap.xml
git commit -m "perf: fix CLS with image dimensions, convert Fyn to WebP, update sitemap"
```
