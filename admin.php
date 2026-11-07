<?php
require __DIR__ . '/lib.php';
if (isset($_GET['logout'])) { unset($_SESSION['admin']); header('Location: login.php'); exit; }
if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }

$secName = ($_GET['s'] ?? 'Sports') === 'Cultural' ? 'Cultural' : 'Sports';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $secName = ($_POST['section'] ?? $secName) === 'Cultural' ? 'Cultural' : 'Sports';
    switch ($_POST['action'] ?? '') {
        case 'save_scores': $n = save_scores($secName); $msg = $n ? "Saved $n change(s)." : 'No changes to save.'; break;
        case 'conclude':    set_setting('concluded', (string)time()); set_setting('locked', '1'); $msg = 'Concluded! The winners reveal plays on open scoreboards.'; break;
        case 'unconclude':  set_setting('concluded', '0'); set_setting('locked', '0'); $msg = 'Conclusion undone. Encoding is open again.'; break;
        case 'toggle_lock': set_setting('locked', setting('locked') === '1' ? '0' : '1'); break;
        case 'clear_all':   q('DELETE FROM results'); set_setting('concluded', '0'); $msg = 'All results cleared.'; break;

        case 'add_game':
            $name = trim($_POST['name'] ?? ''); $pid = (int)($_POST['parent_id'] ?? 0); $sec = $secName;
            if ($pid) { $p = q('SELECT section FROM games WHERE id=? AND parent_id IS NULL', [$pid])->fetch(); if ($p) $sec = $p['section']; else $pid = 0; }
            if ($name !== '') {
                $next = (int)q('SELECT COALESCE(MAX(sort),0)+1 AS n FROM games')->fetch()['n'];
                q('INSERT INTO games (section,parent_id,name,sort) VALUES (?,?,?,?)', [$sec, $pid ?: null, $name, $next]);
                $msg = 'Game added.';
            }
            break;
        case 'save_game':
            if (trim($_POST['name']) !== '') q('UPDATE games SET name=? WHERE id=?', [trim($_POST['name']), (int)$_POST['game_id']]);
            $msg = 'Game renamed.'; break;
        case 'del_game':
            $id = (int)$_POST['game_id'];
            $ids = array_merge([$id], array_column(q('SELECT id FROM games WHERE parent_id=?', [$id])->fetchAll(), 'id'));
            foreach ($ids as $i) { q('DELETE FROM results WHERE game_id=?', [$i]); q('DELETE FROM games WHERE id=?', [$i]); }
            $msg = 'Game deleted.'; break;

        case 'save_sec':
            $id = (int)$_POST['sec_id']; $user = trim($_POST['username']); $pw = $_POST['password'] ?? '';
            if ($user === '' || strcasecmp($user, SUPER_USER) === 0) { $msg = 'Invalid username.'; break; }
            try {
                q('UPDATE judges SET name=?, competition=?, username=? WHERE id=?',
                  [trim($_POST['name']), $_POST['competition'] === 'cultural' ? 'cultural' : 'sports', $user, $id]);
                if ($pw !== '') q('UPDATE judges SET password=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                $msg = 'Secretary saved.';
            } catch (PDOException $e) { $msg = 'That username is already taken.'; }
            break;
        case 'add_sec':
            $user = trim($_POST['username']); $pw = $_POST['password'] ?? '';
            if (trim($_POST['name']) === '' || $user === '' || $pw === '' || strcasecmp($user, SUPER_USER) === 0) { $msg = 'Fill in name, username and password.'; break; }
            try {
                q('INSERT INTO judges (name,competition,username,password) VALUES (?,?,?,?)',
                  [trim($_POST['name']), $_POST['competition'] === 'cultural' ? 'cultural' : 'sports', $user, password_hash($pw, PASSWORD_DEFAULT)]);
                $msg = 'Secretary added.';
            } catch (PDOException $e) { $msg = 'That username is already taken.'; }
            break;
        case 'del_sec': q('DELETE FROM judges WHERE id=?', [(int)$_POST['sec_id']]); $msg = 'Secretary deleted.'; break;
    }
    header('Location: admin.php?s=' . $secName . ($msg ? '&m=' . urlencode($msg) : '')); exit;
}
$msg = $_GET['m'] ?? '';
$locked = setting('locked') === '1';
$concluded = (int)setting('concluded');
$post = fn($action, $extra = '') => '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="section" value="' . h($secName) . '">' . csrf_field() . $extra;
?><!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin</title><link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>"></head><body>
<header class="hero small"><div class="wrap hero-in"><img class="cusg" src="assets/cusg.jpg" alt=""><div><h1>Admin</h1><p><?= h(SITE_TITLE) ?></p></div></div></header>
<main class="wrap">
  <div class="bar"><span><a href="index.php" target="_blank">View public scoreboard ↗</a></span><a href="admin.php?logout=1">Log out</a></div>
  <?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>

  <section class="card">
    <h3>Intramurals status</h3>
    <form method="post" class="inline" onsubmit="return confirm('<?= $concluded ? 'Replay' : 'Conclude' ?>: this locks encoding and plays the winners animation on every open scoreboard. Continue?')"><?= $post('conclude') ?>
      <button class="btn"><?= $concluded ? '▶ Replay winners reveal on all screens' : '🏆 Conclude &amp; reveal winners' ?></button></form>
    <?php if ($concluded): ?><form method="post" class="inline"><?= $post('unconclude') ?><button class="btn alt">↩ Undo conclusion</button></form><?php endif; ?>
    <form method="post" class="inline"><?= $post('toggle_lock') ?>
      <button class="btn alt"><?= $locked ? '🔒 Encoding closed — click to reopen' : '🔓 Encoding open — click to close' ?></button></form>
    <form method="post" class="inline" onsubmit="return confirm('Delete ALL scores for ALL games? This cannot be undone.')"><?= $post('clear_all') ?>
      <button class="btn danger">Clear all results</button></form>
  </section>

  <nav class="tabs">
    <?php foreach (['Sports', 'Cultural'] as $s): ?><a class="<?= $s === $secName ? 'on' : '' ?>" href="admin.php?s=<?= $s ?>"><?= $s ?></a><?php endforeach; ?>
  </nav>
  <section class="card"><h3>Encode scores · <?= h($secName) ?></h3><?= encode_grid($secName, false) ?></section>

  <section class="card">
    <h3>Games</h3>
    <p class="muted">Rename, add or delete games. A game with sub-games (like Basketball → Women / Men) is a heading; scores are encoded on the sub-games.</p>
    <?php foreach (games_flat() as $g): ?>
    <form method="post" class="row"><?= $post('save_game', '<input type="hidden" name="game_id" value="' . $g['id'] . '">') ?>
      <span class="muted" style="min-width:78px"><?= h($g['section']) ?><?= $g['level'] ? ' ↳' : '' ?></span>
      <input name="name" value="<?= h($g['name']) ?>" required>
      <button class="btn sm">Rename</button>
      <button class="btn danger sm" formaction="admin.php" name="action" value="del_game" onclick="return confirm('Delete this game (and its sub-games and scores)?')">Delete</button>
    </form>
    <?php endforeach; ?>
    <h4>Add a game</h4>
    <form method="post" class="row"><?= $post('add_game') ?>
      <input name="name" placeholder="Game name" required>
      <select name="parent_id"><option value="0">Top-level game in <?= h($secName) ?></option>
        <?php foreach (q('SELECT id,section,name FROM games WHERE parent_id IS NULL ORDER BY sort,id') as $p): ?>
        <option value="<?= $p['id'] ?>">Sub-game of: <?= h($p['section'] . ' – ' . $p['name']) ?></option><?php endforeach; ?></select>
      <button class="btn">Add game</button></form>
  </section>

  <section class="card">
    <h3>Secretaries</h3>
    <p class="muted">They log in at <b>login.php</b> and can only encode their own section (Sports or Cultural). Leave "new password" blank to keep the current one.</p>
    <?php foreach (q('SELECT * FROM judges ORDER BY competition,id') as $j): ?>
    <form method="post" class="row"><?= $post('save_sec', '<input type="hidden" name="sec_id" value="' . $j['id'] . '">') ?>
      <input name="name" value="<?= h($j['name']) ?>" placeholder="Full name" required>
      <select name="competition"><?php foreach (['sports' => 'Sports', 'cultural' => 'Cultural'] as $k => $l): ?><option value="<?= $k ?>" <?= $j['competition'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <input name="username" value="<?= h($j['username']) ?>" placeholder="username" required>
      <input name="password" type="text" placeholder="<?= $j['password'] === '' ? 'SET a password' : 'new password (optional)' ?>" autocomplete="off">
      <button class="btn sm">Save</button>
      <button class="btn danger sm" formaction="admin.php" name="action" value="del_sec" onclick="return confirm('Delete this secretary?')">Delete</button>
    </form>
    <?php endforeach; ?>
    <h4>Add a secretary</h4>
    <form method="post" class="row"><?= $post('add_sec') ?>
      <input name="name" placeholder="Full name" required>
      <select name="competition"><option value="sports">Sports</option><option value="cultural">Cultural</option></select>
      <input name="username" placeholder="username" required>
      <input name="password" type="text" placeholder="password" autocomplete="off" required>
      <button class="btn">Add secretary</button></form>
  </section>
</main></body></html>
