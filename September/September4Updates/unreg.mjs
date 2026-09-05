// For every .vue the bundle reaches: PascalCase tags used in the file that no import / components: entry / const defines.
import fs from 'node:fs';
const builtins = new Set(['Transition','TransitionGroup','KeepAlive','Teleport','Suspense','RouterView','RouterLink','Component','Slot']);
const files = fs.readFileSync(process.argv[2], 'utf8').split('\n').filter(f => f.endsWith('.vue'));
let total = 0;
for (const f of files) {
  const src = fs.readFileSync(f, 'utf8');
  const tags = new Set([...src.matchAll(/<([A-Z][A-Za-z0-9]*)[\s/>]/g)].map(m => m[1]).filter(t => !builtins.has(t)));
  const missing = [...tags].filter(t => !new RegExp(`\\bimport\\s+(?:\\{[^}]*\\b${t}\\b[^}]*\\}|${t})\\s+from|\\b(?:const|let|var)\\s+${t}\\b|\\b${t}\\s*:\\s*(?:defineAsyncComponent|\\(|\\{)`).test(src));
  if (missing.length) { total += missing.length; console.log(`${f}: ${missing.join(', ')}`); }
}
console.log(`unregistered PascalCase tags in reached .vue files: ${total}`);
