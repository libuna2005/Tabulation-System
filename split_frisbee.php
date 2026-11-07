<?php
// Run ONCE while logged in as admin, then DELETE this file from the server.
// Splits the Sports "Frisbee" game into "(Men)" and "(Mixed)", scored separately.
// Other games and scores are not touched. Any score already on Frisbee stays with (Men).
require __DIR__ . '/lib.php';
if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }

$found = q("SELECT g.*, (SELECT COUNT(*) FROM games c WHERE c.parent_id=g.id) AS kids
            FROM games g WHERE g.section='Sports' AND g.name LIKE '%frisbee%' ORDER BY g.sort")->fetchAll();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (count($found) !== 1) {
            $msg = 'Stopped: expected exactly one Frisbee game in Sports, found ' . count($found) . '. Nothing changed.';
        } elseif ($found[0]['kids'] > 0) {
            $msg = 'Stopped: Frisbee already has sub-games. Nothing changed.';
        } else {
            $g = $found[0];
            q('UPDATE games SET sort = sort + 1 WHERE sort > ?', [$g['sort']]);
            q('UPDATE games SET name=? WHERE id=?', [$g['name'] . ' (Men)', $g['id']]);
            q("INSERT INTO games (section,parent_id,name,sort) VALUES ('Sports',?,?,?)",
              [$g['parent_id'], $g['name'] . ' (Mixed)', $g['sort'] + 1]);
            $msg = 'Done: ' . h($g['name']) . ' (Men) and ' . h($g['name']) . ' (Mixed) are now separate. Delete split_frisbee.php from the server.';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $msg = 'Failed, nothing was changed: ' . h($e->getMessage());
    }
}
?><!doctype html><meta charset="utf-8">
<body style="font-family:sans-serif;max-width:640px;margin:2rem auto">
<h2>Split Frisbee</h2>
<?php if ($msg): ?>
  <p><?= $msg ?></p><p><a href="admin.php">Back to admin</a></p>
<?php else: ?>
  <p>Found in Sports: <b><?= $found ? h(implode(', ', array_column($found, 'name'))) : 'nothing' ?></b></p>
  <p>It will become "… (Men)" with a new "… (Mixed)" added right after it. Other scores are not touched.</p>
  <form method="post"><?= csrf_field() ?><button style="padding:.6rem 1rem">Split Frisbee</button></form>
<?php endif; ?>
</body>