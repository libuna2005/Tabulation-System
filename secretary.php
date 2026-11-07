<?php
require __DIR__ . '/lib.php';
if (isset($_GET['logout'])) { unset($_SESSION['sec']); header('Location: login.php'); exit; }
$sec = !empty($_SESSION['sec']) ? q('SELECT * FROM judges WHERE id=?', [$_SESSION['sec']])->fetch() : null;
if (!$sec) { header('Location: login.php'); exit; }
$section = $sec['competition'] === 'cultural' ? 'Cultural' : 'Sports';
$locked = setting('locked') === '1';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_scores') {
    csrf_check();
    if ($locked) $msg = 'Results are final. Ask the admin to reopen encoding.';
    else { $n = save_scores($section); $msg = $n ? "Saved $n change(s). The public scoreboard is updated." : 'No changes to save.'; }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Secretary</title><link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>"></head><body>
<header class="hero small"><div class="wrap hero-in"><img class="cusg" src="assets/cusg.jpg" alt=""><div><h1>Encode Results · <?= h($section) ?></h1><p><?= h(SITE_TITLE) ?></p></div></div></header>
<main class="wrap">
  <div class="bar"><span>Signed in: <b><?= h($sec['name']) ?></b> (<?= h($section) ?> secretary) · <a href="index.php" target="_blank">View scoreboard ↗</a></span><a href="secretary.php?logout=1">Log out</a></div>
  <?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
  <?php if ($locked): ?><p class="err">Results are final (encoding is closed). Ask the admin to reopen it.</p><?php endif; ?>
  <?= encode_grid($section, $locked) ?>
</main></body></html>
