<?php
// Run ONCE while logged in as admin, then DELETE this file from the server.
// Splits "Photo Contest (DSLR and Mobile)" into two separately scored games.
// Keeps every score already encoded for the other games.
require __DIR__ . '/lib.php';
if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $g = q("SELECT * FROM games WHERE section='Cultural' AND name='Photo Contest (DSLR and Mobile)'")->fetch();
        if (!$g) {
            $msg = 'Nothing to do: "Photo Contest (DSLR and Mobile)" was not found (already split?).';
        } else {
            q('UPDATE games SET sort = sort + 1 WHERE sort > ?', [$g['sort']]);
            q("UPDATE games SET name='Photo Contest (DSLR)' WHERE id=?", [$g['id']]);
            q("INSERT INTO games (section,parent_id,name,sort) VALUES ('Cultural',?,?,?)",
              [$g['parent_id'], 'Photo Contest (Mobile)', $g['sort'] + 1]);
            $msg = 'Done: Photo Contest (DSLR) and Photo Contest (Mobile) are now separate. Delete split_photo.php from the server.';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $msg = 'Failed, nothing was changed: ' . h($e->getMessage());
    }
}
?><!doctype html><meta charset="utf-8">
<body style="font-family:sans-serif;max-width:640px;margin:2rem auto">
<h2>Split Photo Contest</h2>
<?php if ($msg): ?>
  <p><?= $msg ?></p><p><a href="admin.php">Back to admin</a></p>
<?php else: ?>
  <p>Renames "Photo Contest (DSLR and Mobile)" to <b>Photo Contest (DSLR)</b> and adds <b>Photo Contest (Mobile)</b> right after it. Other scores are not touched.</p>
  <form method="post"><?= csrf_field() ?><button style="padding:.6rem 1rem">Split Photo Contest</button></form>
<?php endif; ?>
</body>