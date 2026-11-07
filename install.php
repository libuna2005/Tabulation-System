<?php
// Run ONCE in the browser, then DELETE this file from the server.
require __DIR__ . '/lib.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
  public TINYINT NOT NULL DEFAULT 1, locked TINYINT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0)");
$pdo->exec("CREATE TABLE IF NOT EXISTS criteria (
  id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, name VARCHAR(150) NOT NULL,
  weight DECIMAL(6,2) NOT NULL, max_points DECIMAL(6,2) NOT NULL,
  tiebreak TINYINT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, INDEX(event_id))");
$pdo->exec("CREATE TABLE IF NOT EXISTS entries (
  id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, name VARCHAR(150) NOT NULL,
  college VARCHAR(10) NOT NULL, category VARCHAR(20) NOT NULL DEFAULT '',
  number INT NOT NULL DEFAULT 0, deduction DECIMAL(6,2) NOT NULL DEFAULT 0, INDEX(event_id))");
$pdo->exec("CREATE TABLE IF NOT EXISTS judges (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, role VARCHAR(40) NOT NULL DEFAULT 'Judge',
  pin VARCHAR(20) NOT NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS scores (
  id INT AUTO_INCREMENT PRIMARY KEY, judge_id INT NOT NULL, entry_id INT NOT NULL, criterion_id INT NOT NULL,
  points DECIMAL(6,2) NOT NULL, UNIQUE KEY uq (judge_id, entry_id, criterion_id))");

echo '<meta charset="utf-8"><body style="font-family:sans-serif;max-width:640px;margin:2rem auto">';
if (q('SELECT COUNT(*) c FROM events')->fetch()['c'] > 0) {
    exit('<h2>Already installed.</h2><p>Delete install.php from the server.</p>');
}

function addEvent($name, $sort, $crit) {
    q('INSERT INTO events (name, sort) VALUES (?,?)', [$name, $sort]);
    $id = db()->lastInsertId();
    foreach ($crit as $i => [$n, $w, $max, $tb])
        q('INSERT INTO criteria (event_id,name,weight,max_points,tiebreak,sort) VALUES (?,?,?,?,?,?)', [$id, $n, $w, $max, $tb, $i]);
    return $id;
}
// Cheerdance: judges give points out of each weight (matches the score sheet)
$cheer = addEvent('Cheerdance', 1, [
    ['Choreography', 40, 40, 0],
    ['Jumps and Tumbling', 20, 20, 1],
    ['Stunts and Pyramids', 20, 20, 1],
    ['Cheer / Yells', 10, 10, 0],
    ['Production (Props and Costumes)', 10, 10, 0],
]);
$n = 1;
foreach (COLLEGES as $code => $full)
    q('INSERT INTO entries (event_id,name,college,number) VALUES (?,?,?,?)', [$cheer, $full, $code, $n++]);

// Pageant preliminary: judges score each segment 0-100, weighted
addEvent('Pageant - Preliminary', 2, [
    ['Preliminary Q&A', 20, 100, 0],
    ['Production Number', 15, 100, 0],
    ['Tropical Attire', 10, 100, 0],
    ['School Uniform', 15, 100, 0],
    ['Formal Wear', 20, 100, 0],
    ['Talent Portion', 20, 100, 0],
]);
// Pageant final: scores reset to zero (separate event); add the Top 3 in admin
addEvent('Pageant - Final Q&A (Top 3)', 3, [
    ['Beauty of the Face and Figure', 50, 50, 0],
    ['Ability to Answer', 50, 50, 0],
]);

$judges = [
    ['Rose Ann R. Aboso', 'Judge'],
    ['Francisco III P. Palma, LPT, MAEd', 'Judge'],
    ['Johnna Lea Belediano-Ellesorio, LPT', 'Judge'],
    ['Bob Ian Roa', 'Technical Judge'],
];
echo '<h2>Installed ✔</h2><p>Judge PINs (write these down, change them in admin.php):</p><ul>';
foreach ($judges as [$name, $role]) {
    $pin = (string)random_int(1000, 9999);
    q('INSERT INTO judges (name, role, pin) VALUES (?,?,?)', [$name, $role, $pin]);
    echo '<li>' . h($name) . ' — PIN <b>' . $pin . '</b></li>';
}
echo '</ul><p><b>Now delete install.php from the server.</b></p>';
