#!/usr/bin/env python3
"""Build a self-contained HTML review artifact from the CoALA plan markdown.

Reads:  /Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md
Writes: /Users/CSJ/Desktop/fynla/May/May26Updates/fynla-coala-implementation-plan.html

Embeds the markdown verbatim inside a script tag and renders client-side
with marked.js (CDN). Output is sanitised with DOMPurify before assignment
to the DOM. Styled with the Fynla design palette (hex values from
tailwind.config.js).
"""

from pathlib import Path

MD_PATH = Path("/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md")
OUT_PATH = Path("/Users/CSJ/Desktop/fynla/May/May26Updates/fynla-coala-implementation-plan.html")

import json

md_text = MD_PATH.read_text(encoding="utf-8")

# JSON-encode the markdown so it lives inside a JS string literal, not a
# raw script-embed. JSON.stringify auto-escapes `</script>` to `<\/script>`
# which is safe inside a JS string AND does not form a script-close token
# in the HTML parser. Belt-and-braces: also explicitly defang any literal
# `</script>` slash so even a hand-edited HTML can't break.
md_json = json.dumps(md_text, ensure_ascii=False)
md_json = md_json.replace("</", "<\\/")  # double-safety against script-tag close

# HTML template. Fynla palette inline so the artifact is fully portable.
# Renders client-side via marked + DOMPurify to neutralise any embedded HTML.
html = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="data:,">
<title>Fynla CoALA Implementation Plan v0.4 — Team Review</title>
<style>
  :root {
    --raspberry-50: #FDF2F8;
    --raspberry-100: #FCE7F3;
    --raspberry-500: #E83E6D;
    --raspberry-700: #BE185D;
    --horizon-50: #F8FAFC;
    --horizon-100: #F1F5F9;
    --horizon-200: #E2E8F0;
    --horizon-300: #CBD5E1;
    --horizon-400: #94A3B8;
    --horizon-500: #1F2A44;
    --horizon-700: #020617;
    --spring-50: #F0FDF9;
    --spring-100: #D1FAE5;
    --spring-500: #20B486;
    --spring-700: #047857;
    --violet-50: #F5F3FF;
    --violet-100: #EDE9FE;
    --violet-500: #5854E6;
    --violet-700: #6D28D9;
    --savannah-100: #FDFAF7;
    --savannah-200: #FAF5F0;
    --savannah-300: #F5EDE5;
    --eggshell-50: #FFFFFF;
    --eggshell-500: #F7F6F4;
    --eggshell-900: #E7E5E2;
    --neutral-500: #717171;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--eggshell-500);
    color: var(--horizon-500);
    font-family: "Segoe UI", Inter, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  .layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0;
    max-width: 1400px;
    margin: 0 auto;
    min-height: 100vh;
  }
  .toc {
    background: var(--eggshell-50);
    border-right: 1px solid var(--horizon-200);
    padding: 32px 24px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
  }
  .toc-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--horizon-400);
    margin: 0 0 16px;
  }
  .toc-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 14px;
  }
  .toc-list li { margin: 6px 0; }
  .toc-list a {
    color: var(--horizon-500);
    text-decoration: none;
    display: block;
    padding: 4px 8px;
    border-left: 2px solid transparent;
    border-radius: 0 4px 4px 0;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
  }
  .toc-list a:hover {
    background: var(--savannah-200);
    border-left-color: var(--raspberry-500);
    color: var(--horizon-700);
  }
  .toc-list a.active {
    background: var(--raspberry-50);
    border-left-color: var(--raspberry-500);
    color: var(--raspberry-700);
    font-weight: 600;
  }
  main {
    background: var(--eggshell-500);
    padding: 48px 64px 96px;
    max-width: 920px;
  }
  .doc-banner {
    background: var(--horizon-500);
    color: var(--eggshell-50);
    padding: 16px 28px;
    border-radius: 10px;
    margin-bottom: 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .doc-banner .brand {
    font-weight: 900;
    font-size: 22px;
    letter-spacing: -0.01em;
  }
  .doc-banner .meta {
    font-size: 13px;
    color: var(--horizon-300);
    font-weight: 500;
  }
  .doc-banner .status {
    background: var(--raspberry-500);
    color: var(--eggshell-50);
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    letter-spacing: 0.02em;
  }
  h1 {
    font-size: 40px;
    font-weight: 900;
    line-height: 1.15;
    margin: 0 0 8px;
    letter-spacing: -0.02em;
    color: var(--horizon-500);
  }
  h2 {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.25;
    margin: 56px 0 16px;
    color: var(--horizon-500);
    padding-bottom: 8px;
    border-bottom: 2px solid var(--horizon-200);
    scroll-margin-top: 24px;
  }
  h3 {
    font-size: 21px;
    font-weight: 700;
    line-height: 1.3;
    margin: 36px 0 12px;
    color: var(--horizon-500);
    scroll-margin-top: 24px;
  }
  h4 {
    font-size: 17px;
    font-weight: 700;
    line-height: 1.35;
    margin: 28px 0 8px;
    color: var(--horizon-500);
    scroll-margin-top: 24px;
  }
  p { margin: 12px 0; }
  strong { color: var(--horizon-500); font-weight: 700; }
  em { color: var(--horizon-500); }
  a { color: var(--raspberry-500); text-decoration: none; font-weight: 500; }
  a:hover { text-decoration: underline; color: var(--raspberry-700); }
  ul, ol { padding-left: 24px; margin: 12px 0; }
  li { margin: 6px 0; }
  li > ul, li > ol { margin: 4px 0; }
  code {
    font-family: "SF Mono", Menlo, Consolas, "Courier New", monospace;
    font-size: 13px;
    background: var(--horizon-100);
    color: var(--horizon-700);
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid var(--horizon-200);
  }
  pre {
    background: var(--horizon-500);
    color: var(--eggshell-50);
    padding: 20px 24px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 20px 0;
    line-height: 1.5;
    border: 1px solid var(--horizon-700);
  }
  pre code {
    background: transparent;
    color: var(--eggshell-50);
    padding: 0;
    border: 0;
    font-size: 13px;
    border-radius: 0;
  }
  blockquote {
    border-left: 4px solid var(--violet-500);
    background: var(--violet-50);
    margin: 20px 0;
    padding: 14px 20px;
    border-radius: 0 6px 6px 0;
    color: var(--horizon-500);
  }
  blockquote p { margin: 6px 0; }
  blockquote strong { color: var(--violet-700); }
  table {
    width: 100%;
    border-collapse: collapse;
    margin: 24px 0;
    font-size: 14px;
    background: var(--eggshell-50);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(31, 42, 68, 0.06);
  }
  thead { background: var(--horizon-500); }
  th {
    color: var(--eggshell-50);
    text-align: left;
    padding: 12px 16px;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.02em;
  }
  td {
    padding: 12px 16px;
    border-top: 1px solid var(--horizon-200);
    vertical-align: top;
    color: var(--horizon-500);
  }
  tr:nth-child(even) td { background: var(--savannah-100); }
  hr {
    border: 0;
    border-top: 1px solid var(--horizon-200);
    margin: 48px 0;
  }
  main > h1:first-of-type { display: none; }
  .mermaid {
    background: var(--eggshell-50);
    border: 1px solid var(--horizon-200);
    border-radius: 10px;
    padding: 24px;
    margin: 28px 0;
    text-align: center;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(31, 42, 68, 0.05);
  }
  .mermaid svg {
    max-width: 100%;
    height: auto;
    font-family: "Segoe UI", Inter, system-ui, sans-serif !important;
  }
  .mermaid:not([data-processed="true"]) {
    color: var(--horizon-400);
    font-style: italic;
    font-size: 13px;
    font-family: "SF Mono", Menlo, Consolas, monospace;
    text-align: left;
    white-space: pre-wrap;
  }
  #content.loading {
    color: var(--horizon-400);
    font-style: italic;
    padding: 40px 0;
    text-align: center;
  }
  .render-error {
    background: var(--raspberry-50);
    color: var(--raspberry-700);
    border: 1px solid var(--raspberry-500);
    padding: 16px 20px;
    border-radius: 8px;
    font-weight: 500;
  }
  @media print {
    .toc { display: none; }
    .layout { grid-template-columns: 1fr; }
    main { padding: 24px; max-width: 100%; }
    .doc-banner { background: none; color: var(--horizon-500); border: 1px solid var(--horizon-300); }
    .doc-banner .brand, .doc-banner .meta { color: var(--horizon-500); }
    pre { background: var(--horizon-100); color: var(--horizon-700); border: 1px solid var(--horizon-300); }
    pre code { color: var(--horizon-700); }
    h2 { page-break-after: avoid; }
    pre, blockquote, table { page-break-inside: avoid; }
  }
  @media (max-width: 900px) {
    .layout { grid-template-columns: 1fr; }
    .toc {
      position: static;
      height: auto;
      border-right: 0;
      border-bottom: 1px solid var(--horizon-200);
      padding: 20px;
    }
    main { padding: 24px 20px 64px; }
    h1 { font-size: 30px; }
    h2 { font-size: 22px; }
  }
</style>
</head>
<body>
<div class="layout">
  <aside class="toc" aria-label="Table of contents">
    <div class="toc-title">Contents</div>
    <ul id="toc-list" class="toc-list"></ul>
  </aside>
  <main>
    <div class="doc-banner">
      <div>
        <div class="brand">Fynla</div>
        <div class="meta">CoALA implementation plan &middot; Team review</div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <span class="status">v0.4</span>
        <span class="meta">2026-05-26</span>
      </div>
    </div>
    <div id="content" class="loading">Rendering plan&hellip;</div>
  </main>
</div>

<script>
  // Markdown source embedded as a JSON-encoded JS string literal. JSON
  // encoding ensures no character sequence in the markdown can terminate
  // this enclosing tag.
  window.__FYNLA_COALA_MD__ = __MD_JSON__;
</script>
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.8/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.min.js"></script>
<script>
  (function () {
    var contentEl = document.getElementById('content');
    function showError(msg) {
      contentEl.classList.remove('loading');
      var div = document.createElement('div');
      div.className = 'render-error';
      div.textContent = msg;
      contentEl.textContent = '';
      contentEl.appendChild(div);
    }
    var raw = window.__FYNLA_COALA_MD__;
    if (typeof raw !== 'string') { showError('Plan content missing.'); return; }
    if (typeof marked === 'undefined') { showError('Markdown renderer failed to load. If you opened this from file://, some browsers block CDN fetches — try opening via a local web server, or view the .md file directly.'); return; }
    if (typeof DOMPurify === 'undefined') { showError('Sanitiser failed to load. Refusing to render unsanitised content.'); return; }
    var hasMermaid = typeof mermaid !== 'undefined';

    // Marked renderer: emit mermaid blocks as <div class="mermaid"> so mermaid
    // can find and replace them with SVG after sanitisation.
    var renderer = new marked.Renderer();
    var baseCode = renderer.code.bind(renderer);
    renderer.code = function (code, lang) {
      if (lang === 'mermaid') {
        // Escape for HTML attribute / text node; mermaid reads textContent so
        // the rendered SVG comes from the trusted markdown source, not user input.
        var esc = code
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
        return '<div class="mermaid">' + esc + '</div>';
      }
      return baseCode(code, lang);
    };
    marked.setOptions({ renderer: renderer, gfm: true, breaks: false, headerIds: true, mangle: false });

    var dirty = marked.parse(raw);
    var clean = DOMPurify.sanitize(dirty, {
      USE_PROFILES: { html: true },
      ADD_ATTR: ['id', 'class']
    });
    contentEl.classList.remove('loading');
    contentEl.innerHTML = clean;

    if (hasMermaid) {
      mermaid.initialize({
        startOnLoad: false,
        theme: 'base',
        securityLevel: 'strict',
        flowchart: { curve: 'basis', htmlLabels: true, useMaxWidth: true },
        sequence: { useMaxWidth: true, mirrorActors: false },
        themeVariables: {
          fontFamily: '"Segoe UI", Inter, system-ui, -apple-system, sans-serif',
          fontSize: '14px',
          background: '#FFFFFF',
          primaryColor: '#FCE7F3',
          primaryTextColor: '#1F2A44',
          primaryBorderColor: '#E83E6D',
          secondaryColor: '#EDE9FE',
          secondaryBorderColor: '#5854E6',
          secondaryTextColor: '#1F2A44',
          tertiaryColor: '#D1FAE5',
          tertiaryBorderColor: '#20B486',
          tertiaryTextColor: '#1F2A44',
          lineColor: '#1F2A44',
          textColor: '#1F2A44',
          mainBkg: '#FCE7F3',
          nodeBorder: '#1F2A44',
          clusterBkg: '#FDFAF7',
          clusterBorder: '#CBD5E1',
          edgeLabelBackground: '#F7F6F4',
          actorBkg: '#FCE7F3',
          actorBorder: '#E83E6D',
          actorTextColor: '#1F2A44',
          actorLineColor: '#1F2A44',
          signalColor: '#1F2A44',
          signalTextColor: '#1F2A44',
          labelBoxBkgColor: '#F7F6F4',
          labelBoxBorderColor: '#1F2A44',
          labelTextColor: '#1F2A44',
          noteBkgColor: '#FDFAF7',
          noteBorderColor: '#CBD5E1',
          noteTextColor: '#1F2A44'
        }
      });
      try {
        mermaid.run({ querySelector: '.mermaid', suppressErrors: false });
      } catch (e) {
        console.error('Mermaid render error:', e);
      }
    }

    // Build the TOC from H2 headings.
    var tocList = document.getElementById('toc-list');
    var headings = contentEl.querySelectorAll('h2');
    headings.forEach(function (h, i) {
      if (!h.id) { h.id = 'section-' + i; }
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent.replace(/\\s*\\(NEW( in v[0-9.]+)?\\)\\s*$/, '');
      li.appendChild(a);
      tocList.appendChild(li);
    });

    function setActive() {
      var anchors = tocList.querySelectorAll('a');
      var current = null;
      headings.forEach(function (h) {
        var rect = h.getBoundingClientRect();
        if (rect.top <= 120) { current = h.id; }
      });
      anchors.forEach(function (a) {
        a.classList.toggle('active', a.getAttribute('href') === '#' + current);
      });
    }
    window.addEventListener('scroll', setActive, { passive: true });
    setActive();
  })();
</script>
</body>
</html>
"""

html = html.replace("__MD_JSON__", md_json)

OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
OUT_PATH.write_text(html, encoding="utf-8")

print(f"Wrote {OUT_PATH} ({OUT_PATH.stat().st_size} bytes)")
