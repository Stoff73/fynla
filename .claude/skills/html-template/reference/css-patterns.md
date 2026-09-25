# Critical CSS patterns and gotchas (Phase 7)

## Contents
- Phase 7 — Critical CSS patterns and gotchas

## Phase 7 — Critical CSS patterns and gotchas

### The `[hidden]` reset — mandatory in every page's critical CSS

```css
[hidden] { display: none !important; }
```

Without this, `display: flex` or `display: grid` on a parent overrides the browser's default for
`[hidden]` and makes hidden panels visible. This breaks every accordion, mega menu, and modal.

### CSS loading — use blocking `<link>`, not the `media="print"` trick

The `media="print"` async trick causes FOUC (Flash of Unstyled Content) whenever the inline critical
CSS block is incomplete — and incomplete is inevitable in practice. FOUC IS a CLS event (content
shifts from unstyled to styled after first paint). Since our CSS files live on the same server as the
PHP, there is no meaningful render-blocking penalty from a standard `<link rel="stylesheet">`.

Always use plain blocking links:

```html
<link rel="stylesheet" href="/pages/css/global.css?v=N" />
<link rel="stylesheet" href="/pages/css/<slug>.css?v=N" />
```

Do not add `media="print" onload` or `<noscript>` fallbacks — they are no longer the pattern.

### Mega menu positioning

```css
/* nav-dropdown: position static so the mega panel anchors to nav-primary__inner,
   not the narrow trigger button. nav-primary__inner must be position:relative. */
.nav-dropdown    { position: static; }
.nav-primary__inner { position: relative; }

.mega-menu {
  position: absolute;
  top: 100%;
  left: 50%; transform: translateX(-50%);
  width: min(60rem, calc(100vw - 2rem));
}
```

### Equalising grid item heights — use JS, not `grid-auto-rows: 1fr`

`grid-auto-rows: 1fr` does NOT work on auto-height containers — `1fr` resolves to `auto`.
Use the `equalizeGridItems()` JS pattern called each time the panel opens:

```js
function equalizeGridItems(panel) {
  var grid  = panel.querySelector('.mega-menu__grid--full');
  if (!grid) return;
  var items = grid.querySelectorAll('.mega-menu__item');
  items.forEach(function (item) { item.style.minHeight = ''; });          // reset
  var maxH  = 0;
  items.forEach(function (item) { maxH = Math.max(maxH, item.offsetHeight); });
  if (maxH > 0) items.forEach(function (item) { item.style.minHeight = maxH + 'px'; });
}
```

### Bottom-anchoring a character/image when content above it grows

Flexbox `align-self: flex-end` does not work when the container height is `auto` — the row
doesn't grow with the content. Use CSS Grid instead:

```css
.section__inner {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: start;        /* both columns start at top by default */
}
.section__content   { align-self: start; }
.section__character { align-self: end; }   /* sticks to the bottom of whichever row is taller */
```

### Conditional state-based padding with `:has()`

Avoid always-on padding that wastes space in the collapsed state:

```css
/* Only add bottom padding when an accordion panel is open */
.section:has(.accordion__panel.is-open) { padding-bottom: 2.5rem; }
```

### Instant mega menu switching + mouseleave delay

```js
var openWrapper = null;   // tracks which menu is currently visible

function open() {
  // Close the previous menu instantly — no delay — before opening the new one
  if (openWrapper && openWrapper !== wrapper) {
    clearTimeout(openWrapper._closeTimer);
    openWrapper._closeNow();
  }
  panel.hidden = false;
  openWrapper  = wrapper;
  equalizeGridItems(panel);
}

function scheduleClose() {
  // 150ms delay so the cursor can cross the gap between trigger and panel
  closeTimer = setTimeout(close, 150);
  wrapper._closeTimer = closeTimer;
}

wrapper._closeNow   = close;
wrapper._closeTimer = null;
wrapper.addEventListener('mouseenter', open);
wrapper.addEventListener('mouseleave', scheduleClose);
```
