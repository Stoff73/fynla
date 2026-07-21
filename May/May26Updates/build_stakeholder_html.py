#!/usr/bin/env python3
"""Build the stakeholder-brief HTML from the brief markdown.

Single-column reading-room layout, larger type, no engineering sidebar.
Reuses the same renderer (marked + DOMPurify + mermaid) and Fynla palette
as the tech-spec artifact. DOMPurify returns a sanitised DocumentFragment
which we splice in via replaceChildren — no innerHTML on the rendering path.

Reads:  /Users/CSJ/Desktop/fynla/fynla-coala-stakeholder-brief.md
Writes: /Users/CSJ/Desktop/fynla/May/May26Updates/fynla-coala-stakeholder-brief.html
"""

import json
from pathlib import Path

MD_PATH = Path("/Users/CSJ/Desktop/fynla/fynla-coala-stakeholder-brief.md")
OUT_PATH = Path("/Users/CSJ/Desktop/fynla/May/May26Updates/fynla-coala-stakeholder-brief.html")

md_text = MD_PATH.read_text(encoding="utf-8")
md_json = json.dumps(md_text, ensure_ascii=False).replace("</", "<\\/")

html = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="data:,">
<title>Fyn brain rewire — stakeholder brief</title>
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
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--eggshell-500);
    color: var(--horizon-500);
    font-family: "Segoe UI", Inter, system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
    font-size: 17px;
    line-height: 1.7;
    -webkit-font-smoothing: antialiased;
  }
  main {
    max-width: 880px;
    margin: 0 auto;
    padding: 64px 48px 120px;
    background: var(--eggshell-500);
  }
  .doc-banner {
    background: var(--horizon-500);
    color: var(--eggshell-50);
    padding: 28px 36px;
    border-radius: 12px;
    margin-bottom: 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
  }
  .doc-banner .brand {
    font-weight: 900;
    font-size: 26px;
    letter-spacing: -0.01em;
    line-height: 1.1;
  }
  .doc-banner .subtitle {
    font-size: 14px;
    color: var(--horizon-300);
    font-weight: 500;
    margin-top: 4px;
  }
  .doc-banner .pill {
    background: var(--raspberry-500);
    color: var(--eggshell-50);
    font-size: 12px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 999px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  main > h1:first-of-type { display: none; }
  h1 {
    font-size: 44px;
    font-weight: 900;
    line-height: 1.1;
    margin: 0 0 12px;
    letter-spacing: -0.025em;
    color: var(--horizon-500);
  }
  h2 {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.25;
    margin: 64px 0 20px;
    color: var(--horizon-500);
    letter-spacing: -0.01em;
  }
  h2::before {
    content: '';
    display: block;
    width: 48px;
    height: 4px;
    background: var(--raspberry-500);
    border-radius: 2px;
    margin-bottom: 16px;
  }
  h3 {
    font-size: 21px;
    font-weight: 700;
    line-height: 1.3;
    margin: 32px 0 12px;
    color: var(--horizon-500);
  }
  p { margin: 14px 0; }
  p:first-of-type { font-size: 19px; line-height: 1.6; }
  strong { color: var(--horizon-500); font-weight: 700; }
  em { color: var(--horizon-500); }
  a { color: var(--raspberry-500); text-decoration: none; font-weight: 500; }
  a:hover { text-decoration: underline; color: var(--raspberry-700); }
  ul, ol { padding-left: 28px; margin: 16px 0; }
  li { margin: 10px 0; }
  hr {
    border: 0;
    border-top: 1px solid var(--horizon-200);
    margin: 56px 0;
  }
  code {
    font-family: "SF Mono", Menlo, Consolas, monospace;
    font-size: 14px;
    background: var(--horizon-100);
    color: var(--horizon-700);
    padding: 2px 7px;
    border-radius: 5px;
    border: 1px solid var(--horizon-200);
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin: 28px 0;
    font-size: 15px;
    background: var(--eggshell-50);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(31, 42, 68, 0.06);
  }
  thead { background: var(--horizon-500); }
  th {
    color: var(--eggshell-50);
    text-align: left;
    padding: 14px 18px;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.02em;
  }
  td {
    padding: 14px 18px;
    border-top: 1px solid var(--horizon-200);
    vertical-align: top;
    color: var(--horizon-500);
  }
  tr:nth-child(even) td { background: var(--savannah-100); }
  .mermaid {
    background: var(--eggshell-50);
    border: 1px solid var(--horizon-200);
    border-radius: 14px;
    padding: 36px 28px;
    margin: 36px 0;
    text-align: center;
    overflow-x: auto;
    box-shadow: 0 2px 8px rgba(31, 42, 68, 0.06);
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
  blockquote {
    border-left: 4px solid var(--raspberry-500);
    background: var(--raspberry-50);
    margin: 24px 0;
    padding: 16px 24px;
    border-radius: 0 8px 8px 0;
    color: var(--horizon-500);
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
    main { padding: 24px; max-width: 100%; }
    .doc-banner { background: none; color: var(--horizon-500); border: 1px solid var(--horizon-300); }
    .doc-banner .brand, .doc-banner .subtitle { color: var(--horizon-500); }
    h2 { page-break-after: avoid; }
    .mermaid, table { page-break-inside: avoid; }
  }
  @media (max-width: 720px) {
    main { padding: 32px 22px 80px; }
    h1 { font-size: 32px; }
    h2 { font-size: 23px; }
    body { font-size: 16px; }
  }
</style>
</head>
<body>
<main>
  <div class="doc-banner">
    <div>
      <div class="brand">Fynla</div>
      <div class="subtitle">Fyn brain rewire &middot; Stakeholder brief</div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <span class="pill">For leadership</span>
      <span class="subtitle">2026-05-26</span>
    </div>
  </div>
  <div id="content" class="loading">Rendering brief&hellip;</div>
</main>

<script>
  window.__FYNLA_STAKEHOLDER_MD__ = __MD_JSON__;
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
      contentEl.replaceChildren(div);
    }
    var raw = window.__FYNLA_STAKEHOLDER_MD__;
    if (typeof raw !== 'string') { showError('Brief content missing.'); return; }
    if (typeof marked === 'undefined') { showError('Markdown renderer failed to load. If you opened this from file://, some browsers block CDN fetches — try opening via a local web server, or view the .md file directly.'); return; }
    if (typeof DOMPurify === 'undefined') { showError('Sanitiser failed to load. Refusing to render unsanitised content.'); return; }
    var hasMermaid = typeof mermaid !== 'undefined';

    var renderer = new marked.Renderer();
    var baseCode = renderer.code.bind(renderer);
    renderer.code = function (code, lang) {
      if (lang === 'mermaid') {
        var esc = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<div class="mermaid">' + esc + '</div>';
      }
      return baseCode(code, lang);
    };
    marked.setOptions({ renderer: renderer, gfm: true, breaks: false, headerIds: true, mangle: false });

    var dirty = marked.parse(raw);
    // DOMPurify returns a sanitised DocumentFragment — splice in via
    // replaceChildren so the raw HTML string never touches the DOM directly.
    var fragment = DOMPurify.sanitize(dirty, {
      USE_PROFILES: { html: true },
      ADD_ATTR: ['id', 'class'],
      RETURN_DOM_FRAGMENT: true
    });
    contentEl.classList.remove('loading');
    contentEl.replaceChildren(fragment);

    if (hasMermaid) {
      mermaid.initialize({
        startOnLoad: false,
        theme: 'base',
        securityLevel: 'strict',
        flowchart: { curve: 'basis', htmlLabels: true, useMaxWidth: true },
        sequence: { useMaxWidth: true, mirrorActors: false },
        gantt: { useMaxWidth: true, barHeight: 22, barGap: 6, fontSize: 13 },
        themeVariables: {
          fontFamily: '"Segoe UI", Inter, system-ui, -apple-system, sans-serif',
          fontSize: '15px',
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
          sectionBkgColor: '#FDFAF7',
          sectionBkgColor2: '#F7F6F4',
          taskBkgColor: '#FCE7F3',
          taskTextColor: '#1F2A44',
          taskBorderColor: '#E83E6D',
          gridColor: '#E2E8F0'
        }
      });
      try {
        mermaid.run({ querySelector: '.mermaid', suppressErrors: false });
      } catch (e) {
        console.error('Mermaid render error:', e);
      }
    }
  })();
</script>
</body>
</html>
"""

html = html.replace("__MD_JSON__", md_json)

OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
OUT_PATH.write_text(html, encoding="utf-8")

print(f"Wrote {OUT_PATH} ({OUT_PATH.stat().st_size} bytes)")
