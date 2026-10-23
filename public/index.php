<?php
declare(strict_types=1);

// RankPortfolio hub: principles, ranking cards in one row per category (some cards open a countdown), and questions answered.

require dirname(__DIR__) . '/src/Stats.php';

$config = require dirname(__DIR__) . '/config/products.php';
$products = $config['products'];
// Three rows by stage: live (including previews), coming soon, then planned. Within a row, cards follow the category order.
$order = array_flip(array_keys($config['categories']));
usort($products, fn($a, $b) => $order[$a['category']] <=> $order[$b['category']]);
$byStatus = fn(array $statuses) => array_values(array_filter($products, fn($p) => in_array($p['status'], $statuses, true)));
$rows = [];
foreach (['live' => ['Live', ['live', 'preview']], 'soon' => ['Coming soon', ['soon']], 'planned' => ['Planned', ['later']]] as $key => [$label, $statuses]) {
    $items = $byStatus($statuses);
    if ($items) {
        $rows[$key] = ['label' => $label, 'items' => $items];
    }
}
// "25 Nov – 13 Dec 2026", or "9–20 Nov 2026" within one month.
function dateRange(int $from, int $to): string
{
    if (gmdate('Y-m', $from) === gmdate('Y-m', $to)) {
        return gmdate('j', $from) . '–' . gmdate('j M Y', $to);
    }
    return gmdate('j M', $from) . ' – ' . gmdate('j M Y', $to);
}

// The CS2 Major countdown follows PandaScore once it lists the next Major: its dates win over the typed ones.
// If it's the same event (starts within 45 days of the typed date) the typed place and note stay; otherwise they're dropped.
$major = Stats::nextMajor();
foreach ($config['countdowns'] as &$c) {
    if (($c['auto'] ?? '') !== 'cs2-major' || !$major) {
        continue;
    }
    $from = strtotime($major['begin_at']);
    $to = $major['end_at'] ? strtotime($major['end_at']) : $from + 21 * 86400;
    if ($to < time()) {
        continue;
    }
    $same = abs($from - strtotime($c['starts'])) < 45 * 86400;
    $c = [
        'name' => $major['name'],
        'starts' => gmdate('Y-m-d\TH:i:s\Z', $from),
        'ends' => gmdate('Y-m-d\TH:i:s\Z', $to),
        'when' => dateRange($from, $to),
        'place' => $same ? $c['place'] : '',
        'why' => $same ? $c['why'] : 'The next CS2 Major',
    ] + $c;
}
unset($c);

// Each countdown opens from the first card of its category, wherever that card sits; ended ones are left out.
$countdownFor = [];
foreach (array_filter($config['countdowns'] ?? [], fn($c) => strtotime($c['ends']) > time()) as $c) {
    foreach ($rows as $row) {
        foreach ($row['items'] as $p) {
            if ($p['category'] === $c['category']) {
                $countdownFor[$p['key']] ??= $c;
                continue 3;
            }
        }
    }
}
$e = fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// File times as version tags, so browsers fetch new CSS, JS and the logo after a deploy.
$v = fn(string $file): int => (int) @filemtime(__DIR__ . '/assets/' . $file);
// Decorative chart beside the statements: three stacked series rising left to right (not real data).
// Drawn to stretch to any width: strokes and dots keep their size via non-scaling strokes.
function heroChart(): string
{
    $W = 700;
    $H = 300;
    $series = [
        'violet' => [165, 118, 112, 138, 80, 80, 42, 22],
        'green' => [205, 180, 192, 205, 170, 170, 128, 110],
        'pink' => [255, 220, 240, 248, 228, 232, 214, 196],
    ];
    $x = fn(int $i): int => (int) round($i * $W / 7);
    $grid = '';
    for ($gy = 50; $gy < $H; $gy += 50) {
        $grid .= "M0 {$gy}H{$W}";
    }
    for ($i = 1; $i < 7; $i++) {
        $grid .= 'M' . $x($i) . " 0V{$H}";
    }
    // Fills and grid fade out towards the right; lines and dots stay fully visible.
    $fills = '<path class="grid" d="' . $grid . '"/>';
    $marks = '';
    foreach ($series as $name => $ys) {
        $line = '';
        $dots = '';
        foreach ($ys as $i => $y) {
            $line .= ($i ? 'L' : 'M') . $x($i) . " {$y}";
            $dots .= 'M' . $x($i) . " {$y}h0";
        }
        $fills .= '<path class="area area-' . $name . '" d="' . $line . "L{$W} {$H}L0 {$H}Z\"/>";
        $marks .= '<path class="line line-' . $name . '" d="' . $line . '"/>';
        $marks .= '<path class="dot dot-' . $name . '" d="' . $dots . '"/>';
    }
    $fade = '<defs><linearGradient id="fade-right" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="' . $W . '" y2="0">'
        . '<stop offset=".45" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>'
        . '<mask id="fade-fills" maskUnits="userSpaceOnUse" x="0" y="0" width="' . $W . '" height="' . $H . '">'
        . '<rect width="' . $W . '" height="' . $H . '" fill="url(#fade-right)"/></mask>'
        // lines and dots only soften over the last stretch
        . '<linearGradient id="fade-end" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="' . $W . '" y2="0">'
        . '<stop offset=".8" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".25"/></linearGradient>'
        . '<mask id="fade-marks" maskUnits="userSpaceOnUse" x="-10" y="-10" width="' . ($W + 20) . '" height="' . ($H + 20) . '">'
        . '<rect x="-10" y="-10" width="' . ($W + 20) . '" height="' . ($H + 20) . '" fill="url(#fade-end)"/></mask></defs>';
    return '<div class="hero-chart" aria-hidden="true"><svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none">'
        . $fade . '<g mask="url(#fade-fills)">' . $fills . '</g><g mask="url(#fade-marks)">' . $marks . '</g></svg></div>';
}

$statusLabel = ['live' => 'Live', 'preview' => 'Preview', 'soon' => 'Coming soon', 'later' => 'Planned'];

$faq = [
    ['What is RankPortfolio?', 'A family of small public rankings, each built from open data and updated automatically. Every one follows the same approach, so the rankings read as one system rather than separate side projects.'],
    ['How are the scores calculated?', 'Each metric is turned into a percentile: the share of items this one beats, from 0 to 100. Percentiles are weighted into a score out of 100, and the weights are published on every site. Change over time is measured in score points over 7 and 28 days.'],
    ['Where does the data come from?', 'Only public sources: PandaScore with official Twitch and Kick streams for Sponsor Lens, the GitHub API for Rising Repos, and later Ember, Wikimedia and Lichess. Every site credits its sources and links to its method.'],
    ['Why never rank people?', 'Rankings are about teams, projects, countries and topics. Individual people aren’t ranked or profiled, and no personal data is collected.'],
    ['How often do they update?', 'Each site has its own rhythm: Sponsor Lens samples live matches, Rising Repos refreshes daily, Clean Power Momentum twice a month and the chess index monthly.'],
    ['Is it free? Can I use the data?', 'Yes, every ranking is free to use. Open datasets will be downloadable from each site, under the terms of its sources.'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RankPortfolio</title>
  <meta name="description" content="Small public rankings built from open data, updated automatically.">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/style.css?v=<?= $v('style.css') ?>">
  <script src="assets/hub.js?v=<?= $v('hub.js') ?>" defer></script>
</head>
<body>
  <header class="top">
    <a class="brand" href="./"><img src="assets/logo.svg?v=<?= $v('logo.svg') ?>" width="28" height="28" alt="">RankPortfolio</a>
    <nav>
      <div class="menu">
        <a href="#rankings" aria-haspopup="true">Rankings<svg viewBox="0 0 16 16" width="10" height="10" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg></a>
        <ul class="menu-list">
          <?php foreach ($rows as $cat => $row): ?>
          <li><a href="#cat-<?= $e($cat) ?>"><?= $e($row['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <a href="#questions">Questions</a><a class="nav-pill" href="https://github.com/leonmsync-droid">GitHub<svg class="gh" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg></a></nav>
  </header>

  <main>
    <section class="statements" aria-label="About RankPortfolio">
      <div class="statement-text">
        <h1 class="sr">RankPortfolio</h1>
        <p>Rankings Built from Open Data.</p>
        <p>Updated Automatically.</p>
        <p>Every Formula Public.</p>
        <p>Multiple Rankings, One Approach.</p>
        <p>Clear Signals for Better Decisions.</p>
      </div>
      <?= heroChart() ?>
    </section>

    <section class="rankings" id="rankings" aria-label="The rankings">
      <?php foreach ($rows as $cat => $row): ?>
      <div class="category" id="cat-<?= $e($cat) ?>" aria-labelledby="cat-<?= $e($cat) ?>-title">
        <div class="category-head">
          <h2 class="sr" id="cat-<?= $e($cat) ?>-title"><?= $e($row['label']) ?></h2>
          <div class="arrows" aria-label="Scroll <?= $e($row['label']) ?>">
            <button type="button" class="arrow prev" aria-label="Previous"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg></button>
            <button type="button" class="arrow next" aria-label="Next"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg></button>
          </div>
        </div>
        <div class="track">
          <?php foreach ($row['items'] as $p):
              $stat = Stats::for($p['key']);
              $open = !empty($p['url']);
              $c = $countdownFor[$p['key']] ?? null; ?>
          <div class="card-group<?= $c ? ' has-drawer' : '' ?>">
          <article class="card card-<?= $e($p['key']) ?> status-<?= $e($p['status']) ?><?= !empty($p['image']) ? ' has-art' : '' ?>">
            <?php if (!empty($p['image'])): ?><img class="card-art" src="<?= $e($p['image']) ?>" alt="" loading="lazy"><?php endif; ?>
            <div class="card-top">
              <div class="mark"><span class="swatch" aria-hidden="true"></span><span class="mark-name"><?= $e($p['name']) ?></span></div>
              <span class="tag tag-<?= $e($p['status']) ?>"><span><?= $e($statusLabel[$p['status']]) ?></span></span>
            </div>
            <h3 class="card-head"><?= $e($p['headline']) ?></h3>
            <p class="card-lines"><?= implode('<span class="dot" aria-hidden="true">·</span>', array_map(fn($l) => '<span class="line">' . $e($l) . '</span>', $p['lines'])) ?></p>
            <div class="card-foot">
              <?php if ($open): ?>
              <a class="pill pill-solid" href="<?= $e($p['url']) ?>" target="_blank" rel="noopener"><?= $e($p['cta']) ?><span class="sr"> (opens in a new tab)</span></a>
              <?php else: ?>
              <span class="pill pill-muted"><?= $p['status'] === 'later' ? 'Planned' : 'In the works' ?></span>
              <?php endif; ?>
              <?php if ($stat): ?>
              <span class="card-stat"><b><?= $e($stat['value']) ?></b> <?= $e($stat['label']) ?></span>
              <?php else: ?>
              <span class="card-kind"><?= $e($p['tag']) ?></span>
              <?php endif; ?>
            </div>
          </article>
          <?php if ($c): $starts = strtotime($c['starts']); $left = max(0, $starts - time()); $id = 'drawer-' . $e($p['key']); ?>
          <aside class="drawer" aria-label="Countdown">
            <button type="button" class="drawer-tab" aria-expanded="false" aria-controls="<?= $id ?>">
              <svg class="clock" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><circle cx="8" cy="8" r="6.25"/><path d="M8 4.5V8l2.5 1.5"/></svg>
              <svg class="drawer-chev" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
              <span class="sr">Countdown to <?= $e($c['name']) ?></span>
            </button>
            <div class="drawer-panel" id="<?= $id ?>">
              <span class="drawer-label">Countdown</span>
              <h4><?= $e($c['name']) ?></h4>
              <p class="drawer-meta"><?= $e($c['when']) ?><?= $c['place'] ? ' · ' . $e($c['place']) : '' ?></p>
              <p class="drawer-why"><?= $e($c['why']) ?></p>
              <time class="count" datetime="<?= $e($c['starts']) ?>" data-starts="<?= $starts * 1000 ?>" data-ends="<?= strtotime($c['ends']) * 1000 ?>"><?= $left ? intdiv($left, 86400) . 'd ' . intdiv($left % 86400, 3600) . 'h ' . intdiv($left % 3600, 60) . 'm' : 'Happening now' ?></time>
              <a class="card-source" href="<?= $e($c['source']) ?>" rel="noopener">Source</a>
            </div>
          </aside>
          <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </section>

    <section class="faq" id="questions" aria-labelledby="faq-title">
      <h2 id="faq-title">Questions? Answers.</h2>
      <div class="faq-list">
        <?php foreach ($faq as [$q, $a]): ?>
        <details>
          <summary><span><?= $e($q) ?></span><svg viewBox="0 0 16 16" width="20" height="20" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg></summary>
          <p><?= $e($a) ?></p>
        </details>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <footer class="foot">
    <span>RankPortfolio · built by LNM Digital</span>
    <span>Each ranking credits its own data sources. Not affiliated with any of them.</span>
  </footer>
</body>
</html>
