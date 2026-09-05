// Walk real import edges from the Vite entries; report components/*.vue the graph never reaches.
import fs from 'node:fs'; import path from 'node:path';
const root = process.cwd();
const entries = process.argv.slice(2);
const alias = { '@/': 'resources/js/', '@m/': 'resources/mobile/' };
const exts = ['', '.vue', '.js', '.ts', '.mjs', '/index.js', '/index.ts', '/index.vue'];
const resolve = (spec, from) => {
  let p = null;
  for (const [a, dir] of Object.entries(alias)) if (spec.startsWith(a)) p = path.join(root, dir, spec.slice(a.length));
  if (!p && (spec.startsWith('./') || spec.startsWith('../') || spec.startsWith('/'))) p = spec.startsWith('/') ? path.join(root, spec) : path.resolve(path.dirname(from), spec);
  if (!p) return null; // bare package
  p = p.split('?')[0];
  for (const e of exts) { const c = p + e; if (fs.existsSync(c) && fs.statSync(c).isFile()) return c; }
  return null;
};
const specRe = /(?:from\s*|import\s*\(\s*|import\s+|require\s*\(\s*)['"]([^'"]+)['"]/g;
const seen = new Set(); const queue = entries.map(e => path.join(root, e));
while (queue.length) {
  const f = queue.pop(); if (seen.has(f)) continue; seen.add(f);
  const src = fs.readFileSync(f, 'utf8');
  for (const m of src.matchAll(specRe)) { const r = resolve(m[1], f); if (r && !seen.has(r)) queue.push(r); }
}
const walk = d => fs.readdirSync(d, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(d, e.name)) : e.name.endsWith('.vue') ? [path.join(d, e.name)] : []);
const comps = ['resources/js/components', 'resources/mobile/components'].filter(d => fs.existsSync(d)).flatMap(d => walk(path.join(root, d)));
const unreachable = comps.filter(c => !seen.has(c)).map(c => path.relative(root, c)).sort();
console.log(`modules reached from entries: ${seen.size}`);
console.log(`components: ${comps.length}, unreachable: ${unreachable.length}`);
fs.writeFileSync(process.env.REACHED || "reached.txt", [...seen].map(f => path.relative(root, f)).join("\n") + "\n");
fs.writeFileSync(process.env.OUT || 'unreachable.txt', unreachable.join('\n') + '\n');
