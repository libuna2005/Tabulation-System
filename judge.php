<?php
require __DIR__ . '/lib.php';

if (isset($_GET['logout'])) { unset($_SESSION['judge']); header('Location: login.php'); exit; }
$judge = !empty($_SESSION['judge']) ? q('SELECT * FROM judges WHERE id=?', [$_SESSION['judge']])->fetch() : null;
if (!$judge) { header('Location: login.php'); exit; }

?><!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Judge Panel</title><link rel="stylesheet" href="style.css"></head><body>
<header class="hero small"><div class="wrap hero-in"><img class="cusg" src="assets/cusg.jpg" alt=""><div><h1>Judge Panel</h1><p><?= h(SITE_TITLE) ?></p></div></div></header>
<main class="wrap">
<?php if (true):
  $events = q('SELECT * FROM events WHERE type=? ORDER BY sort,id', [$judge['competition']])->fetchAll();
  $evId = (int)($_GET['ev'] ?? 0);
  $ev = null;
  foreach ($events as $e) if ($e['id'] == $evId) $ev = $e;
  if (!$ev && $events) { $ev = $events[0]; $evId = (int)$ev['id']; }
  $crit = q('SELECT * FROM criteria WHERE event_id=? ORDER BY sort,id', [$evId])->fetchAll();
  $entries = q('SELECT * FROM entries WHERE event_id=? ORDER BY category,number,id', [$evId])->fetchAll();
  $msg = '';

  if ($ev && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
      csrf_check();
      if ($ev['locked']) { $msg = 'Scoring is closed for this event.'; }
      else {
          $saved = 0;
          foreach ($entries as $e) foreach ($crit as $c) {
              $v = $_POST['s'][$e['id']][$c['id']] ?? '';
              if ($v === '' || !is_numeric($v)) continue;
              $v = max(0, min((float)$c['max_points'], (float)$v));
              q('INSERT INTO scores (judge_id,entry_id,criterion_id,points) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE points=VALUES(points)', [$judge['id'], $e['id'], $c['id'], $v]);
              $saved++;
          }
          $msg = "Saved $saved score(s).";
      }
  }
  $mine = [];
  foreach (q('SELECT s.* FROM scores s JOIN entries e ON e.id=s.entry_id WHERE s.judge_id=? AND e.event_id=?', [$judge['id'], $evId]) as $r)
      $mine[$r['entry_id']][$r['criterion_id']] = $r['points'];
?>
  <div class="bar"><span>Signed in: <b><?= h($judge['name']) ?></b> (<?= h($judge['role']) ?> · <?= h(ucfirst($judge['competition'])) ?>)</span>
    <a href="judge.php?logout=1">Log out</a></div>
  <nav class="tabs"><?php foreach ($events as $e): ?>
    <a class="<?= $e['id'] == $evId ? 'on' : '' ?>" href="judge.php?ev=<?= $e['id'] ?>"><?= h($e['name']) ?></a><?php endforeach; ?></nav>
  <?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
  <?php if ($ev && $ev['locked']): ?><p class="err">Scoring is closed for this event.</p><?php endif; ?>
  <?php if (!$entries): ?><p class="card">No entries for this event yet.</p>
  <?php else: ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="scroll"><table class="board">
      <thead><tr><th class="l">Entry</th>
        <?php foreach ($crit as $c): ?><th><?= h($c['name']) ?><br><small><?= rtrim(rtrim($c['weight'], '0'), '.') ?>% · max <?= rtrim(rtrim($c['max_points'], '0'), '.') ?></small></th><?php endforeach; ?>
        <th>Your total</th></tr></thead>
      <tbody>
      <?php $lastCat = null; foreach ($entries as $e):
        if ($e['category'] !== $lastCat && $e['category'] !== ''): $lastCat = $e['category']; ?>
        <tr class="catrow"><td colspan="<?= count($crit) + 2 ?>"><?= h($e['category']) ?></td></tr>
      <?php endif; ?>
        <tr data-row>
          <td class="l"><?= logo($e['college']) ?><b><?= $e['number'] ? '#' . (int)$e['number'] . ' ' : '' ?><?= h($e['name']) ?></b> <span class="badge"><?= h($e['college']) ?></span></td>
          <?php foreach ($crit as $c): ?>
          <td><input type="number" step="0.01" min="0" max="<?= h($c['max_points']) ?>"
              name="s[<?= $e['id'] ?>][<?= $c['id'] ?>]" data-max="<?= h($c['max_points']) ?>" data-w="<?= h($c['weight']) ?>"
              value="<?= isset($mine[$e['id']][$c['id']]) ? h(rtrim(rtrim($mine[$e['id']][$c['id']], '0'), '.')) : '' ?>"
              <?= $ev['locked'] ? 'disabled' : '' ?>></td>
          <?php endforeach; ?>
          <td class="fin" data-total>—</td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php if (!$ev['locked']): ?><p><button class="btn">Save scores</button> <span class="muted">You can edit and re-save until the event is closed.</span></p><?php endif; ?>
  </form>
  <script>
  document.querySelectorAll('[data-row]').forEach(row => {
    const calc = () => {
      let t = 0, any = false;
      row.querySelectorAll('input').forEach(i => { if (i.value !== '') { any = true; t += Math.min(+i.value, +i.dataset.max) / +i.dataset.max * +i.dataset.w; } });
      row.querySelector('[data-total]').textContent = any ? t.toFixed(2) : '—';
    };
    row.addEventListener('input', calc); calc();
  });
  </script>
  <?php endif; endif; ?>
</main></body></html>
