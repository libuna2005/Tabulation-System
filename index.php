<?php
define('NO_SESSION', 1);
require __DIR__ . '/lib.php';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Live Scores — <?= h(SITE_TITLE) ?></title>
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head><body>
<header class="hero">
  <div class="wrap hero-in">
    <img class="cusg" src="assets/cusg.jpg" alt="CUSG Alijis">
    <div>
      <small>Pagsiklab · CHMSU Alijis Campus</small>
      <h1><?= h(SITE_TITLE) ?></h1>
        <p><span class="dot"></span> Live Scoreboard	</p>    <p>	Partial and Unofficial</p>
    </div>
  </div>
</header>
<main class="wrap">
  <div id="status" class="muted">Loading…</div>
  <p><button id="replay" class="btn alt sm" style="display:none">▶ Replay winners reveal</button></p>

  <h2 class="grp">Overall Standing For Cultural and Sports</h2>
  <ol class="rank" id="cards"></ol>

  <h2 class="grp">Scores per Game</h2>
  <nav class="tabs" id="tabs"></nav>
  <div class="scroll" id="sheet"></div>
</main>
<footer class="wrap muted">Official results, updated automatically. · <a href="login.php">Secretary login</a></footer>

<div id="reveal"><button class="btn alt rv-x" id="rvClose">✕ Close</button><canvas id="confetti"></canvas><div class="rv-stage" id="rvStage"></div></div>
<script>
const statusEl = document.getElementById('status'), cardsEl = document.getElementById('cards'),
      sheetEl = document.getElementById('sheet'), tabsEl = document.getElementById('tabs');
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt = n => (n === null || n === undefined) ? '—' : Number(n).toLocaleString(undefined, {maximumFractionDigits: 2});
const ord = k => k + (['th','st','nd','rd'][(k % 100 > 10 && k % 100 < 14) ? 0 : (k % 10 < 4 ? k % 10 : 0)]);
const logoUrl = code => 'assets/' + String(code).toLowerCase().replace(/[^a-z]/g, '') + '.jpg';
const TITLES = {1: 'Champion', 2: '1st Runner Up', 3: '2nd Runner Up', 4: '3rd Runner Up'};

let lastData = null, secName = null, lastSig = '', prevCells = {}, concludedKnown;

/* ---------- animated overall standing (cards slide when the order changes) ---------- */
const nodes = new Map();
function countTo(el, from, to) {
  if (to === null) { el.textContent = '—'; return; }
  if (reduce || from === null || from === to) { el.textContent = fmt(to); return; }
  const t0 = performance.now(), dur = 900;
  (function step(t) {
    const k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
    el.textContent = fmt(from + (to - from) * e);
    if (k < 1) requestAnimationFrame(step);
  })(t0);
}
function renderCards(d) {
  const first = new Map();
  nodes.forEach((n, code) => first.set(code, n.li.getBoundingClientRect().top));
  d.overall.forEach(o => {
    let n = nodes.get(o.code);
    if (!n) {
      const li = document.createElement('li');
      li.innerHTML = '<span class="pos"></span><img class="lg big" alt=""><div class="who"><b></b><span class="ttl"></span>' +
        '<span><span class="badge"></span><span class="tie" style="display:none">TIE</span><span class="parts"></span></span></div>' +
        '<div class="score"><span class="num">—</span><small>pts</small></div>';
      li.querySelector('img').src = logoUrl(o.code);
      li.querySelector('b').textContent = o.name;
      li.querySelector('.badge').textContent = o.code;
      n = {li, total: null}; nodes.set(o.code, n);
    }
    const li = n.li;
    li.querySelector('.pos').textContent = o.rank ?? '–';
    li.classList.toggle('gold', o.rank === 1);
    li.classList.toggle('silver', o.rank === 2);
    li.classList.toggle('bronze', o.rank === 3);
    li.querySelector('.ttl').textContent = (lastData && lastData.concluded) ? (TITLES[o.rank] || '') : '';
    li.querySelector('.tie').style.display = o.tie ? 'inline' : 'none';
    li.querySelector('.parts').textContent = Object.entries(o.parts).map(([k, v]) => k + ' ' + fmt(v)).join(' · ');
    if (n.total !== o.total) {
      countTo(li.querySelector('.num'), n.total, o.total);
      if (n.total !== null && !reduce) { li.classList.remove('bump'); void li.offsetWidth; li.classList.add('bump'); }
      n.total = o.total;
    }
    cardsEl.appendChild(li);
  });
  if (!reduce) nodes.forEach((n, code) => {
    if (!first.has(code)) return;
    const dy = first.get(code) - n.li.getBoundingClientRect().top;
    if (Math.abs(dy) < 2) return;
    n.li.style.transition = 'none'; n.li.style.transform = `translateY(${dy}px)`; n.li.classList.add('moving');
    requestAnimationFrame(() => requestAnimationFrame(() => {
      n.li.style.transition = 'transform .9s cubic-bezier(.2,.8,.2,1)'; n.li.style.transform = '';
    }));
    setTimeout(() => n.li.classList.remove('moving'), 1000);
  });
}

/* ---------- Excel-style sheet: game x college, rank per game ---------- */
function rk(k) { return k ? `<span class="rk r${k > 4 ? 4 : k}">${ord(k)}</span>` : ''; }
function renderSheet(d) {
  if (!d.sections.length) { sheetEl.innerHTML = '<p class="card">No games yet.</p>'; tabsEl.innerHTML = ''; return; }
  if (!secName || !d.sections.some(s => s.name === secName)) secName = d.sections[0].name;
  tabsEl.innerHTML = d.sections.map(s => `<button data-s="${esc(s.name)}" class="${s.name === secName ? 'on' : ''}">${esc(s.name)}</button>`).join('');
  const s = d.sections.find(x => x.name === secName);
  const sig = JSON.stringify([secName, s, d.overall_totals, d.overall_ranks]);
  if (sig === lastSig) return;
  lastSig = sig;

  const cur = {};
  let h = `<table class="board gt"><thead><tr><th class="l">${esc(s.name)}</th>` +
    d.colleges.map(c => `<th class="ch" style="background:${c.color}"><img class="lg sm" src="${logoUrl(c.code)}" alt="">${esc(c.code)}</th>`).join('') + '</tr></thead><tbody>';
  s.rows.forEach(r => {
    if (r.heading) { h += `<tr class="hd"><td colspan="${d.colleges.length + 1}">${esc(r.name)}</td></tr>`; return; }
    h += `<tr><td class="l ${r.level ? 'ind' : ''}">${esc(r.name)}</td>`;
    d.colleges.forEach(c => {
      const v = r.scores[c.code], k = r.ranks[c.code], key = r.id + '|' + c.code;
      cur[key] = v;
      h += `<td class="c ${k === 1 ? 'win' : ''}" data-k="${key}">` + (v === null ? '<span class="na">—</span>' : `<span class="pt">${fmt(v)}</span>${rk(k)}`) + '</td>';
    });
    h += '</tr>';
  });
  const totRow = (label, totals, ranks, cls) => `<tr class="tot ${cls}"><td class="l">${label}</td>` +
    d.colleges.map(c => `<td class="c ${ranks[c.code] === 1 ? 'win' : ''}">${totals[c.code] === null ? '<span class="na">—</span>' : `<span class="pt">${fmt(totals[c.code])}</span>${rk(ranks[c.code])}`}</td>`).join('') + '</tr>';
  h += totRow('TOTAL SCORE', s.totals, s.ranks, '');
  h += totRow('OVER ALL TOTAL SCORE', d.overall_totals, d.overall_ranks, 'all');
  h += '</tbody></table>';
  sheetEl.innerHTML = h;

  if (!reduce) Object.keys(cur).forEach(key => {
    if (key in prevCells && prevCells[key] !== cur[key]) sheetEl.querySelector(`td[data-k="${key}"]`)?.classList.add('flash');
  });
  prevCells = cur;
}
tabsEl.addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  secName = b.dataset.s; prevCells = {}; lastSig = ''; renderSheet(lastData);
});

function render(d) {
  lastData = d;
  statusEl.textContent = (d.concluded ? '🏆 Official results · ' : d.locked ? 'Final results · ' : 'Live results · ') + 'last update ' + d.updated;
  document.getElementById('replay').style.display = d.concluded ? 'inline-block' : 'none';
  renderCards(d);
  renderSheet(d);
}

/* ---------- winners reveal (Champion, 1st / 2nd / 3rd Runner Up) ---------- */
const rv = document.getElementById('reveal'), stage = document.getElementById('rvStage');
let timers = [];
function stopReveal() {
  timers.forEach(clearTimeout); timers = [];
  rv.classList.remove('on'); document.body.classList.remove('noscroll');
  const c = document.getElementById('confetti'); c.getContext('2d').clearRect(0, 0, c.width, c.height);
}
function confetti() {
  if (reduce) return;
  const c = document.getElementById('confetti'), ctx = c.getContext('2d');
  c.width = innerWidth; c.height = innerHeight;
  const cols = ['#ffc857', '#ff7a1a', '#ffffff', '#e0a63f', '#ff4d4d'];
  const ps = Array.from({length: 200}, () => ({x: Math.random() * c.width, y: -Math.random() * c.height * .6, vx: (Math.random() - .5) * 3,
    vy: 2 + Math.random() * 4, s: 6 + Math.random() * 8, r: Math.random() * 6, vr: (Math.random() - .5) * .3, col: cols[(Math.random() * cols.length) | 0]}));
  const end = performance.now() + 7000;
  (function f(t) {
    ctx.clearRect(0, 0, c.width, c.height);
    ps.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.r += p.vr;
      if (p.y > c.height && t < end) { p.y = -10; p.x = Math.random() * c.width; }
      ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.col; ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); ctx.restore();
    });
    if (rv.classList.contains('on') && t < end + 3500) requestAnimationFrame(f); else ctx.clearRect(0, 0, c.width, c.height);
  })(performance.now());
}
function playReveal(d) {
  stopReveal();
  const win = d.overall.filter(o => o.rank !== null && o.rank <= 4);
  if (!win.length) return;
  rv.classList.add('on'); document.body.classList.add('noscroll');
  const k = reduce ? 0.25 : 1; let t = 0;
  const at = (ms, fn) => timers.push(setTimeout(fn, ms));
  stage.innerHTML = '<div class="rv-intro">🏆 The results are in…<small>And the overall winners are…</small></div>';
  t = 3200 * k;
  const slots = new Map();
  at(t, () => {
    stage.innerHTML = '<div class="rv-head">Overall Standing</div><div class="rv-slots"></div>';
    const box = stage.querySelector('.rv-slots');
    win.forEach(o => {
      const el = document.createElement('div');
      el.className = 'slot' + (o.rank === 1 ? ' champion' : '');
      el.innerHTML = `<div class="st">${esc(TITLES[o.rank])}</div><div class="sb"><span class="q">?</span></div>`;
      box.appendChild(el); slots.set(o.code, el);
    });
  });
  t += 1400 * k;
  [...win].sort((a, b) => b.rank - a.rank).forEach(o => {
    const champ = o.rank === 1;
    at(t, () => slots.get(o.code)?.classList.add('drum'));
    t += (champ ? 3200 : 1900) * k;
    at(t, () => {
      const el = slots.get(o.code); if (!el) return;
      el.classList.remove('drum'); el.classList.add('revealed');
      el.querySelector('.sb').innerHTML = `<img class="lg big" src="${logoUrl(o.code)}" alt=""><span class="nm">${esc(o.name)} <span class="badge">${esc(o.code)}</span></span><span class="sc">${fmt(o.total)}</span>`;
      if (champ) confetti();
    });
    t += (champ ? 5500 : 1800) * k;
  });
  t += 1500 * k;
  at(t, () => { const x = document.createElement('div'); x.className = 'rv-intro done'; x.textContent = '🎉 Congratulations!'; stage.appendChild(x); });
}
document.getElementById('rvClose').onclick = stopReveal;
document.getElementById('replay').onclick = () => lastData && playReveal(lastData);

async function load() {
  try {
    const r = await fetch('api.php', {cache: 'no-store'});
    const d = await r.json();
    render(d);
    // plays on every device: when Conclude is pressed while the page is open,
    // AND when the page is opened/refreshed any time after (as long as results are concluded)
    if (d.concluded && d.concluded !== concludedKnown) playReveal(d);
    concludedKnown = d.concluded;
  } catch (e) { statusEl.textContent = 'Connection issue — retrying…'; }
}
load(); setInterval(() => { if (!document.hidden) load(); }, 5000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
</script>
</body></html>