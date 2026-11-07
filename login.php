<?php
require __DIR__ . '/lib.php';
if (!empty($_SESSION['admin'])) { header('Location: admin.php'); exit; }
if (!empty($_SESSION['sec'])) { header('Location: secretary.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? ''); $p = $_POST['password'] ?? ''; $comp = $_POST['competition'] ?? '';
    if (hash_equals(SUPER_USER, $u) && hash_equals(SUPER_PASS, $p)) {
        session_regenerate_id(true); $_SESSION['admin'] = 1; header('Location: admin.php'); exit;
    }
    if (in_array($comp, ['sports', 'cultural'], true)) {
        $j = q('SELECT * FROM judges WHERE username=? AND competition=?', [$u, $comp])->fetch();
        if ($j && $j['password'] !== '' && password_verify($p, $j['password'])) {
            session_regenerate_id(true); $_SESSION['sec'] = (int)$j['id']; header('Location: secretary.php'); exit;
        }
    }
    usleep(500000);
    $err = 'Wrong username or password for that competition.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title><link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>"></head><body>
<header class="hero small"><div class="wrap hero-in"><img class="cusg" src="assets/cusg.jpg" alt=""><div><h1>Secretary Login</h1><p><?= h(SITE_TITLE) ?></p></div></div></header>
<main class="wrap">
  <form method="post" class="card narrow"><?= csrf_field() ?>
    <label>Competition
      <select name="competition">
        <option value="sports">Sports Secretary</option>
        <option value="cultural">Cultural Secretary</option>
      </select></label>
    <label>Username <input name="username" autocomplete="username" required></label>
    <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
    <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
    <button class="btn">Log in</button>
    <p class="muted"><a href="index.php">← Back to live scoreboard</a></p>
  </form>
</main></body></html>
