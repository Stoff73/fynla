<?php
// Mission Control — the workforce's actual interface.
// Served by PHP's built-in server. Reads the real tree; every button runs a real command.
// Started by START-MYRTLE.command. Nothing here needs a terminal.

$ROOT = realpath(__DIR__ . '/../../..');
chdir($ROOT);
$OPS = 'workforce/ops';

function run(string $cmd): string {
    return trim((string) shell_exec($cmd . ' 2>&1'));
}
function fm(string $file, string $key): string {
    if (!is_file($file)) return '';
    foreach (explode("\n", (string) file_get_contents($file)) as $i => $line) {
        if ($i > 40) break;
        if (str_starts_with($line, $key . ':')) return trim(substr($line, strlen($key) + 1));
    }
    return '';
}
function items(string $glob): array { $f = glob($glob) ?: []; sort($f); return $f; }

// ---- actions ---------------------------------------------------------------
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    $flash = match ($a) {
        'start'   => run('bash workforce/ops/myrtle-install.sh'),
        'stop'    => run('bash workforce/ops/myrtle-install.sh --stop'),
        'listen'  => run('bash workforce/ops/myrtle-listen.sh'),
        'brief'   => run('bash workforce/ops/wf.sh brief'),
        'monday'  => run('bash workforce/ops/wf.sh monday'),
        'friday'  => run('bash workforce/ops/wf.sh friday'),
        'sweep'   => run('bash workforce/ops/sweep.sh'),
        'post'    => run('bash workforce/ops/wf.sh slack ' . escapeshellarg($_POST['channel'] ?? 'fyn-brief') . ' ' . escapeshellarg($_POST['message'] ?? '')),
        'decide'  => (function () use ($OPS) {
            $id  = preg_replace('/[^A-Za-z0-9-]/', '', $_POST['gate'] ?? '');
            $d   = ($_POST['decision'] ?? '') === 'approve' ? 'approve' : 'hold';
            $who = preg_replace('/[^A-Za-z ]/', '', $_POST['who'] ?? 'CSJ');
            $now = gmdate('c');
            foreach (array_merge(items("$OPS/gates/*.md"), items("$OPS/provisioning/*.md")) as $f) {
                if (fm($f, 'id') !== $id) continue;
                $c = (string) file_get_contents($f);

                // Set each field if present; ADD it before the closing --- if not.
                // The original only replaced, so a gate written without a `decision:`
                // field silently stayed open after being approved.
                foreach (['decided_by' => $who, 'decided_at' => $now, 'decision' => $d, 'status' => 'resolved'] as $k => $v) {
                    if (preg_match('/^' . $k . ':.*$/m', $c)) {
                        $c = preg_replace('/^' . $k . ':.*$/m', "$k: $v", $c, 1);
                    } else {
                        // insert just before the second --- (end of frontmatter)
                        $pos = strpos($c, "\n---", 4);
                        if ($pos !== false) $c = substr($c, 0, $pos) . "\n$k: $v" . substr($c, $pos);
                    }
                }

                // Append the decision to the body so the reasoning survives.
                $c .= "\n\n## Decision — {$now}\n\n**{$who}: " . strtoupper($d) . "**\n";
                file_put_contents($f, $c);

                run('bash workforce/ops/wf.sh log chief-of-staff ' . escapeshellarg($id) . ' gate_' . $d . ' ' . escapeshellarg("by $who"));
                return "$id — {$d}ed by {$who}. Recorded in " . basename($f) . " and removed from the queue.";
            }
            return "gate $id not found";
        })(),
        'claim'   => run('bash workforce/ops/wf.sh claim ' . escapeshellarg($_POST['item'] ?? '') . ' ' . escapeshellarg($_POST['owner'] ?? '')),
        'move'    => run('bash workforce/ops/wf.sh move ' . escapeshellarg($_POST['item'] ?? '') . ' ' . escapeshellarg($_POST['status'] ?? '')),
        default   => '',
    };
    if ($a !== '') { run('bash workforce/ops/mission-control.sh'); }
}

// ---- read state ------------------------------------------------------------
$board = array_map(fn($f) => [
    'id' => fm($f, 'id'), 'title' => fm($f, 'title'), 'owner' => fm($f, 'owner'),
    'status' => fm($f, 'status'), 'surfaces' => fm($f, 'surfaces'),
    'prior_art' => fm($f, 'prior_art_checked'), 'evidence' => fm($f, 'evidence'),
], items("$OPS/board/*.md"));

$allGates = array_map(fn($f) => [
    'id' => fm($f, 'id'), 'action' => fm($f, 'action') ?: fm($f, 'needs'),
    'decision' => fm($f, 'decision'), 'severity' => fm($f, 'severity'),
    'by' => fm($f, 'decided_by'), 'status' => fm($f, 'status'), 'file' => $f,
], array_merge(items("$OPS/gates/*.md"), items("$OPS/provisioning/*.md")));

// Open = no decision recorded AND not resolved. A gate is only closed by an
// explicit decision, never by a stray field.
$gates  = array_values(array_filter($allGates, fn($g) =>
    !in_array($g['decision'], ['approve', 'hold'], true) && $g['status'] !== 'resolved'));
$closed = array_values(array_filter($allGates, fn($g) =>
    in_array($g['decision'], ['approve', 'hold'], true)));

$gaps = array_values(array_filter(array_map(fn($f) => [
    'id' => fm($f, 'id'), 'agent' => fm($f, 'agent'), 'sev' => fm($f, 'severity'),
    'status' => fm($f, 'status'),
], items("$OPS/gaps/*.md")), fn($g) => $g['status'] === 'open'));

$agents = [];
foreach (glob('.claude/agents/*.md') ?: [] as $f) {
    $n = fm($f, 'name');
    if (in_array($n, ['chief-of-staff','quartermaster','cartographer','archivist','build-lead','quality-lead','compliance-lead','intelligence-lead','product-lead','design-lead','growth-lead'], true)) {
        $held = 0; foreach ($board as $b) if ($b['owner'] === $n) $held++;
        $agents[] = ['name' => $n, 'held' => $held];
    }
}

$logFile = "$OPS/log/" . gmdate('Y-m') . '.jsonl';
$log = is_file($logFile) ? array_slice(array_filter(explode("\n", (string) file_get_contents($logFile))), -12) : [];
$running = (int) run("launchctl list 2>/dev/null | grep -c org.fynla.myrtle");
$briefFile = "$OPS/reports/brief-" . gmdate('Y-m-d') . '.md';
$brief = is_file($briefFile) ? (string) file_get_contents($briefFile) : '';
$counts = array_count_values(array_column($board, 'status'));
?><!DOCTYPE html><html lang=en-GB><head><meta charset=utf-8>
<title>Mission Control — Fynla Workforce</title>
<meta http-equiv=refresh content=30>
<style>
:root{color-scheme:light;--nav:#1F2A44;--ink:#0F172A;--dim:#5B6883;--line:#E4E9F1;--bg:#FAFBFC;
--spring:#20B486;--violet:#5854E6;--rasp:#E83E6D;--sav:#E6C9A8}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",Inter,-apple-system,sans-serif;background:var(--bg);color:var(--ink);font-size:13px;line-height:1.45}
.top{background:var(--nav);color:#fff;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:9}
.top h1{font-size:16px;font-weight:900;letter-spacing:-.2px}
.live{font-size:11px;padding:3px 10px;border-radius:20px;font-weight:700}
.on{background:#0E7A5A;color:#fff}.off{background:var(--rasp);color:#fff}
.menu{background:#fff;border-bottom:1px solid var(--line);padding:8px 22px;display:flex;gap:7px;flex-wrap:wrap;position:sticky;top:47px;z-index:8}
button,.btn{font:inherit;font-size:12px;font-weight:700;padding:6px 13px;border-radius:6px;border:1px solid var(--line);background:#fff;color:var(--ink);cursor:pointer}
button:hover{background:#F1F4F9}
.pri{background:var(--spring);color:#fff;border-color:var(--spring)}
.dan{background:#fff;color:var(--rasp);border-color:var(--rasp)}
.wrap{max-width:1240px;margin:0 auto;padding:18px 22px 40px}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
section{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-bottom:16px}
h2{font-size:11px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--dim);margin-bottom:11px;display:flex;justify-content:space-between}
table{width:100%;border-collapse:collapse;font-size:12px}
th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--dim);padding:0 8px 7px 0;border-bottom:1px solid var(--line)}
td{padding:7px 8px 7px 0;border-bottom:1px solid #F1F4F9;vertical-align:middle}
tr:last-child td{border-bottom:0}
.p{display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap}
.s-done{background:#E7F7F1;color:#0E7A5A}.s-in_progress,.s-claimed{background:#ECEBFD;color:#3F3BB8}
.s-review{background:#FBF3EA;color:#8A6A3C}.s-blocked,.s-gated{background:#FDEAF0;color:#B32350}
.s-queued{background:#F1F4F9;color:var(--dim)}
.gate{border:1px solid var(--sav);background:#FBF3EA;border-radius:8px;padding:11px 12px;margin-bottom:9px}
.gate b{font-size:12px}.gate p{font-size:11.5px;color:#6B5330;margin:4px 0 9px}
.flash{background:var(--nav);color:#DFF5EC;padding:11px 14px;border-radius:8px;font-family:ui-monospace,Menlo,monospace;font-size:11px;white-space:pre-wrap;max-height:230px;overflow:auto;margin-bottom:16px}
.log{font-family:ui-monospace,Menlo,monospace;font-size:10.5px;color:var(--dim);white-space:pre-wrap;max-height:230px;overflow:auto}
.dim{color:var(--dim)}.warn{color:var(--rasp);font-weight:700;font-size:11px}
textarea,select,input{font:inherit;font-size:12px;padding:6px 8px;border:1px solid var(--line);border-radius:6px;width:100%}
textarea{min-height:70px;resize:vertical}
.row{display:flex;gap:7px;align-items:center;margin-top:7px}
pre.brief{font-size:11.5px;white-space:pre-wrap;line-height:1.5}
form{display:inline}
</style></head><body>

<div class=top>
  <h1>Mission Control — Fynla Workforce</h1>
  <div>
    <span class="live <?= $running > 0 ? 'on' : 'off' ?>"><?= $running > 0 ? 'MYRTLE RUNNING' : 'MYRTLE STOPPED' ?></span>
    <span style="font-size:11px;opacity:.7;margin-left:10px"><?= gmdate('D d M H:i') ?> UTC</span>
  </div>
</div>

<div class=menu>
  <?php foreach ([
    ['start','Start Myrtle','pri'], ['stop','Stop','dan'], ['listen','Check Slack now',''],
    ['brief','Daily brief',''], ['monday','Monday plan',''], ['friday','Friday delta',''],
    ['sweep','Run sweep',''],
  ] as [$a,$label,$cls]): ?>
    <form method=post><input type=hidden name=action value="<?= $a ?>"><button class="<?= $cls ?>"><?= $label ?></button></form>
  <?php endforeach; ?>
</div>

<div class=wrap>
<?php if ($flash !== ''): ?><div class=flash><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<?php if (!$gates): ?>
<section style="border-color:var(--spring)">
  <h2>Awaiting a founder <span class=dim>clear</span></h2>
  <p class=dim style="font-size:12px">Nothing needs a decision.
  <?php if ($closed): ?>Last decided: <b><?= htmlspecialchars($closed[count($closed)-1]['id']) ?></b>
  — <?= htmlspecialchars($closed[count($closed)-1]['decision']) ?> by
  <?= htmlspecialchars($closed[count($closed)-1]['by'] ?: 'unknown') ?>.<?php endif; ?></p>
</section>
<?php else: ?>
<section style="border-color:var(--sav)">
  <h2>Awaiting a founder <span class=dim><?= count($gates) ?></span></h2>
  <?php foreach ($gates as $g): ?>
    <div class=gate>
      <b><?= htmlspecialchars($g['id']) ?></b><?= $g['severity'] === 'high' ? ' <span class=warn>HIGH</span>' : '' ?>
      <p><?= htmlspecialchars($g['action']) ?></p>
      <form method=post>
        <input type=hidden name=action value=decide><input type=hidden name=gate value="<?= htmlspecialchars($g['id']) ?>">
        <select name=who style="width:auto;display:inline-block">
          <option>CSJ</option><option>Azlan Raj</option><option>Brett Isenberg</option>
        </select>
        <button name=decision value=approve class=pri>Approve</button>
        <button name=decision value=hold class=dan>Hold</button>
      </form>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div class=grid>
<div>
  <section>
    <h2>Board <span class=dim><?= ($counts['done'] ?? 0) ?> done · <?= ($counts['in_progress'] ?? 0) + ($counts['claimed'] ?? 0) ?> active · <?= ($counts['review'] ?? 0) ?> review</span></h2>
    <table><thead><tr><th style=width:60px>ID</th><th>Title</th><th style=width:118px>Owner</th><th style=width:96px>Status</th><th style=width:150px></th></tr></thead><tbody>
    <?php foreach ($board as $b): ?>
      <tr>
        <td><b><?= htmlspecialchars($b['id']) ?></b></td>
        <td><?= htmlspecialchars($b['title']) ?>
          <?php if ($b['status'] === 'review' && $b['evidence'] !== 'full'): ?><br><span class=warn>evidence: <?= htmlspecialchars($b['evidence'] ?: 'none') ?></span><?php endif; ?>
        </td>
        <td class=dim><?= htmlspecialchars($b['owner'] ?: '—') ?></td>
        <td><span class="p s-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
        <td>
          <form method=post>
            <input type=hidden name=action value=move><input type=hidden name=item value="<?= htmlspecialchars($b['id']) ?>">
            <select name=status style="width:auto;display:inline-block;font-size:11px">
              <?php foreach (['queued','claimed','in_progress','handoff','review','gated','blocked','done'] as $s): ?>
                <option<?= $s === $b['status'] ? ' selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
            <button style="font-size:11px;padding:4px 9px">Set</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

  <section>
    <h2>Say something as Myrtle</h2>
    <form method=post>
      <input type=hidden name=action value=post>
      <textarea name=message placeholder="Posts to Slack as Myrtle, not as you."></textarea>
      <div class=row>
        <select name=channel style="width:auto">
          <option value=fyn-brief>#fyn-brief</option>
          <option value=fyn-decisions>#fyn-decisions</option>
          <option value=all-fynla>#all-fynla</option>
        </select>
        <button class=pri>Post</button>
      </div>
    </form>
  </section>

  <?php if ($brief !== ''): ?>
  <section><h2>Today's brief</h2><pre class=brief><?= htmlspecialchars($brief) ?></pre></section>
  <?php endif; ?>
</div>

<div>
  <section>
    <h2>Agents <span class=dim><?= count($agents) ?></span></h2>
    <table><tbody>
    <?php foreach ($agents as $a): ?>
      <tr><td><b><?= htmlspecialchars($a['name']) ?></b></td><td class=dim style=width:70px><?= $a['held'] ?: '—' ?> items</td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>

  <?php if ($gaps): ?>
  <section>
    <h2>Gaps <span class=dim><?= count($gaps) ?> open</span></h2>
    <table><tbody>
    <?php foreach ($gaps as $g): ?>
      <tr><td><b><?= htmlspecialchars($g['id']) ?></b><br><span class=dim><?= htmlspecialchars($g['agent']) ?></span></td>
      <td style=width:80px><span class="p <?= $g['sev'] === 'blocking' ? 's-blocked' : 's-queued' ?>"><?= htmlspecialchars($g['sev']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>

  <section>
    <h2>Event log <span class=dim>last <?= count($log) ?></span></h2>
    <div class=log><?php foreach (array_reverse($log) as $l) {
      $j = json_decode($l, true);
      if ($j) echo htmlspecialchars(substr($j['ts'] ?? '', 11, 5) . '  ' . ($j['event'] ?? '') . '  ' . ($j['agent'] ?? '')) . "\n";
    } ?></div>
  </section>
</div>
</div>
</div></body></html>
