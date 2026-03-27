# Solutions Stats Bar Layout Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce Solutions section bottom padding to 12, and eliminate the eggshell gap between the Solutions pink background and the footer by moving the stats bar inside the Solutions section.

**Architecture:** Template-only changes in `LandingPage.vue`. The stats bar div is moved from outside the Solutions `<div>` to inside it (before the closing tag). The `-mt-14` upward margin is removed since the bar is now naturally at the bottom of the pink section; `-mb-12` is kept so the footer gradient overlaps the card's bottom half. Solutions `pb` is set to `pb-12` for spacing between the View demo button and the stats card.

**Tech Stack:** Vue 3, Tailwind CSS

---

## File Map

| File | Lines | Change |
|------|-------|--------|
| `resources/js/views/Public/LandingPage.vue` | 211 | `pb-24 lg:pb-28` → `pb-12` on Solutions section |
| `resources/js/views/Public/LandingPage.vue` | 272–295 | Move stats bar div from outside Solutions to inside (before `</div></div>` closing tags); remove `-mt-14` |

---

## Current Structure (for reference)

```html
<!-- Solutions section ends here -->
    </div>   ← max-w-7xl inner div
  </div>     ← Solutions outer div  (line 273)

<!-- Stats bar currently OUTSIDE solutions — causes eggshell gap -->
<div class="relative z-10 -mt-14 -mb-12">   (line 276)
  ...card...
</div>
```

## Target Structure

```html
<!-- Stats bar moved INSIDE solutions, before closing tags -->
        <div class="relative z-10 -mb-12 mt-12">
          ...card...
        </div>

      </div>   ← max-w-7xl inner div
    </div>     ← Solutions outer div
```

---

## Task 1: Reduce Solutions Bottom Padding to 12

```html
<!-- BEFORE -->
<div id="solutions" class="bg-light-pink-100 pt-10 lg:pt-12 pb-24 lg:pb-28">

<!-- AFTER -->
<div id="solutions" class="bg-light-pink-100 pt-10 lg:pt-12 pb-12">
```

- [ ] **Step 1: Change `pb-24 lg:pb-28` to `pb-12` on the Solutions section div**

---

## Task 2: Move Stats Bar Inside Solutions Section

Remove the stats bar div from its current position (between Solutions and footer) and place it as the last child inside the Solutions `max-w-7xl` inner div, before its closing `</div>`.

Also update the stats bar's margins: remove `-mt-14` (no longer needed — the bar is now at the natural bottom of Solutions) and replace with `mt-12` (spacing from the View demo button above). Keep `-mb-12` so the footer gradient overlaps the card's lower half.

**BEFORE** — stats bar is a sibling after the Solutions closing tag:

```html
      </div>
    </div>
    <!-- Stats Bar - Straddles solutions section and footer -->
    <div class="relative z-10 -mt-14 -mb-12">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="card-lg flex flex-col sm:flex-row items-center justify-around gap-6">
          ...stats content...
        </div>
      </div>
    </div>
```

**AFTER** — stats bar is the last child inside the Solutions `max-w-7xl` div:

```html
        <!-- Stats Bar -->
        <div class="relative z-10 mt-12 -mb-12">
          <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="card-lg flex flex-col sm:flex-row items-center justify-around gap-6">
              ...stats content...
            </div>
          </div>
        </div>

      </div>
    </div>
```

- [ ] **Step 1: Remove the stats bar div from its current position outside Solutions**

- [ ] **Step 2: Paste the stats bar div as the last child inside the Solutions `max-w-7xl` inner div, with `mt-12 -mb-12` (no `-mt-14`)**

- [ ] **Step 3: Verify** — Refresh http://localhost:8000:
  - Solutions pink background runs continuously from the section top all the way to where the footer gradient begins — no eggshell strip visible
  - The stats card straddles the Solutions/footer boundary (top half pink, bottom half dark gradient)
  - Spacing between "View demo" button and the stats card looks comfortable

---

## Task 3: Commit

- [ ] **Step 1: Commit**

```bash
git add resources/js/views/Public/LandingPage.vue
git commit -m "style: move stats bar inside solutions section, eliminate eggshell gap, reduce solutions pb to 12"
```
