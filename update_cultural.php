<?php
// Run ONCE while logged in as admin, then DELETE this file from the server.
// Replaces ALL Cultural games (and their encoded scores) with the list below.
// Sports games and scores are not touched.
require __DIR__ . '/lib.php';
if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }

// Heading => sub-games, in display order
const CULTURAL = [
    'Literary Event' => [
        'Pagkukwento',
        'Storytelling',
        'Dramatic Monologue (English)',
        'Dramatic Monologue (Filipino)',
        'Dagliang Talumpati',
        'Extemporaneous Speaking',
        'Quiz Bowl',
    ],
    'Visual Arts' => [
        'Photo Contest (DSLR and Mobile)',
        'Pencil Rendering',
        'Charcoal Painting',
    ],
    'Musical Event' => [
        'Vocal Solo',
        'Vocal Duet',
        'Instrumental Solo',
    ],
    'Dance Event' => [
        'Contemporary',
        'Streetdance',
    ],
    'Special Event' => [
        'Mr. CHMSU-A',
        'Ms. CHMSU-A',
    ],
];

$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $old = array_column(q("SELECT id FROM games WHERE section='Cultural'")->fetchAll(), 'id');
        foreach ($old as $id) q('DELETE FROM results WHERE game_id=?', [$id]);
        q("DELETE FROM games WHERE section='Cultural'");

        $next = (int)q('SELECT COALESCE(MAX(sort),0)+1 AS n FROM games')->fetch()['n'];
        foreach (CULTURAL as $heading => $kids) {
            q("INSERT INTO games (section,parent_id,name,sort) VALUES ('Cultural',NULL,?,?)", [$heading, $next++]);
            $pid = (int)$pdo->lastInsertId();
            foreach ($kids as $k)
                q("INSERT INTO games (section,parent_id,name,sort) VALUES ('Cultural',?,?,?)", [$pid, $k, $next++]);
        }
        $pdo->commit();
        $done = true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        exit('Failed, nothing was changed: ' . h($e->getMessage()));
    }
}
?><!doctype html><meta charset="utf-8">
<body style="font-family:sans-serif;max-width:640px;margin:2rem auto">
<?php if ($done): ?>
  <h2>Cultural games updated ✔</h2>
  <p>Check <a href="admin.php?s=Cultural">admin → Cultural</a>, then <b>delete update_cultural.php from the server.</b></p>
<?php else: ?>
  <h2>Replace Cultural games</h2>
  <p>This deletes the current Cultural games <b>and their scores</b>, then adds:</p>
  <?php foreach (CULTURAL as $heading => $kids): ?>
    <p><b><?= h($heading) ?></b></p><ul><?php foreach ($kids as $k): ?><li><?= h($k) ?></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><button style="padding:.6rem 1rem">Replace Cultural games</button></form>
<?php endif; ?>
</body>