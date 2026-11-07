<?php
require __DIR__ . '/config.php';
if (!defined('NO_SESSION')) session_start();

// Hard-coded super admin (this file is blocked from the web by .htaccess)
const SUPER_USER = 'lancelot782005';
const SUPER_PASS = 'P@ssw0rd0!';

// Same order and colors as the Excel sheet
const COLLEGES = [
    'CCS'  => ['name' => 'College of Computer Studies',      'color' => '#A6A6A6'],
    'COE'  => ['name' => 'College of Engineering',           'color' => '#BE5014'],
    'COED' => ['name' => 'College of Education',             'color' => '#215C98'],
    'CIT'  => ['name' => 'College of Industrial Technology', 'color' => '#FF0000'],
];
const SECTIONS = ['sports' => 'Sports', 'cultural' => 'Cultural'];

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function logo(string $c, string $cls = 'lg'): string {
    $c = strtolower(preg_replace('/[^A-Za-z]/', '', $c));
    return '<img class="' . $cls . '" src="assets/' . $c . '.jpg" alt="">';
}
function csrf_field(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Go back, refresh the page, and try again.');
    }
}
function setting(string $k, string $default = '0'): string {
    $r = q('SELECT v FROM settings WHERE k=?', [$k])->fetch();
    return $r ? $r['v'] : $default;
}
function set_setting(string $k, string $v): void {
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
}

/** All games in display order. A game with sub-games is a heading (not scored itself). */
function games_flat(?string $section = null): array {
    $all = q('SELECT * FROM games ORDER BY sort, id')->fetchAll();
    $kids = [];
    foreach ($all as $g) if ($g['parent_id']) $kids[$g['parent_id']][] = $g;
    $out = [];
    foreach ($all as $g) {
        if ($g['parent_id']) continue;
        if ($section !== null && $g['section'] !== $section) continue;
        $out[] = ['id' => (int)$g['id'], 'name' => $g['name'], 'section' => $g['section'], 'level' => 0, 'heading' => !empty($kids[$g['id']])];
        foreach ($kids[$g['id']] ?? [] as $c)
            $out[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'section' => $g['section'], 'level' => 1, 'heading' => false];
    }
    return $out;
}

/** 1,2,3,4 ... equal scores share a rank. Needs at least two scored colleges. */
function rank_map(array $vals): array {
    $out = array_fill_keys(array_keys($vals), null);
    $has = array_filter($vals, fn($v) => $v !== null);
    if (count($has) < 2) return $out;
    foreach ($has as $k => $v) $out[$k] = 1 + count(array_filter($has, fn($o) => $o > $v));
    return $out;
}

/** Everything the public scoreboard needs (mirrors the Excel sheet). */
function board(): array {
    $codes = array_keys(COLLEGES);
    $res = [];
    foreach (q('SELECT game_id, college, score FROM results')->fetchAll() as $r)
        $res[(int)$r['game_id']][$r['college']] = round((float)$r['score'], 2);

    $sections = []; $sum = []; $has = [];
    foreach (games_flat() as $g) {
        $s = $g['section'];
        if (!isset($sections[$s])) {
            $sections[$s] = ['name' => $s, 'rows' => []];
            $sum[$s] = array_fill_keys($codes, 0.0);
            $has[$s] = array_fill_keys($codes, false);
        }
        if ($g['heading']) {
            $sections[$s]['rows'][] = ['id' => $g['id'], 'name' => $g['name'], 'level' => 0, 'heading' => true];
            continue;
        }
        $scores = [];
        foreach ($codes as $c) {
            $v = $res[$g['id']][$c] ?? null;
            $scores[$c] = $v;
            if ($v !== null) { $sum[$s][$c] += $v; $has[$s][$c] = true; }
        }
        $sections[$s]['rows'][] = ['id' => $g['id'], 'name' => $g['name'], 'level' => $g['level'], 'heading' => false,
                                   'scores' => $scores, 'ranks' => rank_map($scores)];
    }

    $overall = array_fill_keys($codes, 0.0); $ohas = array_fill_keys($codes, false);
    foreach ($sections as $s => &$sec) {
        $tot = [];
        foreach ($codes as $c) {
            $tot[$c] = $has[$s][$c] ? round($sum[$s][$c], 2) : null;
            if ($has[$s][$c]) { $overall[$c] += $sum[$s][$c]; $ohas[$c] = true; }
        }
        $sec['totals'] = $tot; $sec['ranks'] = rank_map($tot);
    }
    unset($sec);

    $otot = [];
    foreach ($codes as $c) $otot[$c] = $ohas[$c] ? round($overall[$c], 2) : null;
    $oranks = rank_map($otot);

    $list = [];
    foreach ($codes as $c) {
        $parts = [];
        foreach ($sections as $s => $sec) $parts[$s] = $sec['totals'][$c];
        $list[] = ['code' => $c, 'name' => COLLEGES[$c]['name'], 'color' => COLLEGES[$c]['color'],
                   'total' => $otot[$c], 'rank' => $oranks[$c], 'parts' => $parts];
    }
    $cnt = array_count_values(array_filter($oranks, fn($r) => $r !== null));
    foreach ($list as &$o) $o['tie'] = $o['rank'] !== null && $cnt[$o['rank']] > 1;
    unset($o);
    $order = array_flip($codes);
    usort($list, fn($a, $b) => [$a['rank'] ?? 99, $order[$a['code']]] <=> [$b['rank'] ?? 99, $order[$b['code']]]);

    $colleges = [];
    foreach (COLLEGES as $c => $i) $colleges[] = ['code' => $c, 'name' => $i['name'], 'color' => $i['color']];
    return ['colleges' => $colleges, 'sections' => array_values($sections),
            'overall' => $list, 'overall_totals' => $otot, 'overall_ranks' => $oranks];
}

/** Save the posted s[gameId][CODE] cells of one section. Only changed cells are written. */
function save_scores(string $section): int {
    $n = 0;
    foreach (games_flat($section) as $g) {
        if ($g['heading']) continue;
        foreach (array_keys(COLLEGES) as $code) {
            if (!isset($_POST['s'][$g['id']][$code])) continue;
            $v = trim((string)$_POST['s'][$g['id']][$code]);
            $o = trim((string)($_POST['o'][$g['id']][$code] ?? ''));
            if ($v === $o) continue;            // untouched: never overwrite someone else's edit
            $v = str_replace(',', '.', $v);     // phone keyboards may type a comma
            if (!($v === '' || is_numeric($v))) continue;   // typo: ignore, never wipe a saved score
            if ($v === '') {
                q('DELETE FROM results WHERE game_id=? AND college=?', [$g['id'], $code]);
            } else {
                q('INSERT INTO results (game_id, college, score) VALUES (?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score)',
                  [$g['id'], $code, max(0, min(99999, round((float)$v, 2)))]);
            }
            $n++;
        }
    }
    return $n;
}

/** Excel-style encoding grid for one section (secretary + admin pages). */
function encode_grid(string $section, bool $locked): string {
    $rows = games_flat($section);
    if (!$rows) return '<p class="card">No games in this section.</p>';
    $res = [];
    foreach (q('SELECT game_id, college, score FROM results')->fetchAll() as $r)
        $res[(int)$r['game_id']][$r['college']] = rtrim(rtrim((string)$r['score'], '0'), '.');
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save_scores"><input type="hidden" name="section" value="<?= h($section) ?>">
      <div class="scroll"><table class="board grid">
        <thead><tr><th class="l"><?= h($section) ?></th>
          <?php foreach (COLLEGES as $c => $i): ?><th class="ch" style="background:<?= $i['color'] ?>"><?= $c ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $g): ?>
          <?php if ($g['heading']): ?>
            <tr class="hd"><td colspan="5"><?= h($g['name']) ?></td></tr>
          <?php else: ?>
            <tr><td class="l <?= $g['level'] ? 'ind' : '' ?>"><?= h($g['name']) ?></td>
              <?php foreach (array_keys(COLLEGES) as $c): $val = $res[$g['id']][$c] ?? ''; ?>
              <td data-label="<?= $c ?>" style="--cc:<?= COLLEGES[$c]['color'] ?>"><input type="text" inputmode="decimal" autocomplete="off" enterkeyhint="next" data-c="<?= $c ?>" name="s[<?= $g['id'] ?>][<?= $c ?>]" value="<?= h($val) ?>" <?= $locked ? 'disabled' : '' ?>>
                  <input type="hidden" name="o[<?= $g['id'] ?>][<?= $c ?>]" value="<?= h($val) ?>"></td>
              <?php endforeach; ?></tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="tot"><td class="l">TOTAL SCORE</td>
          <?php foreach (array_keys(COLLEGES) as $c): ?><td data-tot="<?= $c ?>" data-label="<?= $c ?>" style="--cc:<?= COLLEGES[$c]['color'] ?>">—</td><?php endforeach; ?></tr></tfoot>
      </table></div>
      <?php if (!$locked): ?><p class="savebar"><button class="btn">Save scores</button> <span class="muted">Type each college's overall score for the game. Leave blank = no score yet. The public scoreboard updates right away.</span></p><?php endif; ?>
    </form>
    <script>
    document.querySelectorAll('table.grid').forEach(t => {
      const upd = () => t.querySelectorAll('[data-tot]').forEach(td => {
        let s = 0, any = false;
        t.querySelectorAll('input[data-c="' + td.dataset.tot + '"]').forEach(i => { const n = parseFloat(i.value.replace(',', '.')); if (!isNaN(n)) { any = true; s += n; } });
        td.textContent = any ? Math.round(s * 100) / 100 : '—';
      });
      t.addEventListener('input', upd); upd();
    });
    </script>
    <?php return ob_get_clean();
}