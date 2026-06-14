<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

const WEBS = ['home' => 'Home', 'library' => 'Library'];
const ORIGIN = 'https://www.hondabase.com';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$rel = trim(preg_replace('#^/pgmfi/wiki#', '', $path), '/');
$seg = $rel === '' ? [] : explode('/', $rel);

// Topic URLs are canonical without a trailing slash.
if (count($seg) === 2 && str_ends_with($path, '/')) {
    header('Location: /pgmfi/wiki/' . $rel, true, 301);
    exit;
}

try {
    require_once '/var/www/tracker.php';
    track_page_view('PGMFI Wiki' . ($rel !== '' ? ': ' . $rel : ''));
} catch (Throwable $e) {
    error_log('Tracking failed: ' . $e->getMessage());
}

function webhome_body(string $web): ?string
{
    $st = db()->prepare('SELECT body_html FROM topics WHERE web = ? AND is_webhome = 1');
    $st->execute([$web]);
    return $st->fetchColumn() ?: null;
}

function topic_counts(): array
{
    return db()->query(
        "SELECT web, COUNT(*) c FROM topics WHERE is_webhome = 0 GROUP BY web"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
}

function not_found(array $seg): void
{
    http_response_code(404);
    $reason = null;
    if (count($seg) === 2 && isset(WEBS[$seg[0]])) {
        $st = db()->prepare('SELECT reason FROM skipped_topics WHERE web = ? AND (slug = ? OR name = ?)');
        $st->execute([$seg[0], $seg[1], $seg[1]]);
        $reason = $st->fetchColumn() ?: null;
    }
    layout_top('Not found', [['wiki', '/pgmfi/wiki/'], ['404', null]], '', '', true);
    ?>
    <article>
      <header><h1>Page not archived</h1></header>
      <div>
        <?php if ($reason === 'stub') : ?>
          <p>This page existed on pgmfi.org, but it only contained the default TWiki
             user template - no real content - so it was not carried into the archive.</p>
        <?php elseif ($reason === 'chrome') : ?>
          <p>This was a TWiki system page (search, change log, preferences …) and has no
             equivalent in the archive.</p>
        <?php else : ?>
          <p>No page by this name was found in the recovered pgmfi.org wiki capture.</p>
        <?php endif; ?>
        <p>Try the <a href="/pgmfi/wiki/library/all">library topic list</a> or the search box above.</p>
      </div>
    </article>
    <?php
    layout_bottom();
    exit;
}

// ---------------------------------------------------------------------------
// suggest: JSON for the autocomplete menu

if ($seg === ['suggest']) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        echo '[]';
        exit;
    }
    if (mb_strlen($q) < 3) {
        $st = db()->prepare(
            "SELECT web, slug, title FROM topics WHERE is_webhome = 0 AND title LIKE ? ORDER BY title LIMIT 8"
        );
        $st->execute([str_replace(['%', '_'], ['\%', '\_'], $q) . '%']);
    } else {
        $bool = implode(' ', array_map(
            fn($w) => '+' . preg_replace('/[+\-<>~*"()@]/', '', $w) . '*',
            preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY)
        ));
        $st = db()->prepare(
            "SELECT web, slug, title FROM topics
             WHERE is_webhome = 0 AND MATCH(title) AGAINST (? IN BOOLEAN MODE)
             ORDER BY title LIMIT 8"
        );
        $st->execute([$bool]);
    }
    echo json_encode(array_map(
        fn($r) => ['title' => $r['title'], 'web' => $r['web'], 'url' => "/pgmfi/wiki/{$r['web']}/{$r['slug']}"],
        $st->fetchAll()
    ));
    exit;
}

// ---------------------------------------------------------------------------
// sitemap for search engines

if ($seg === ['sitemap.xml']) {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $url = fn(string $loc, ?string $lastmod = null) => '<url><loc>' . h($loc) . '</loc>'
        . ($lastmod ? '<lastmod>' . gmdate('Y-m-d', strtotime($lastmod)) . '</lastmod>' : '')
        . "</url>\n";
    echo $url(ORIGIN . '/pgmfi/');
    echo $url(ORIGIN . '/pgmfi/wiki/');
    foreach (array_keys(WEBS) as $w) {
        echo $url(ORIGIN . "/pgmfi/wiki/$w/");
        echo $url(ORIGIN . "/pgmfi/wiki/$w/all");
    }
    $rows = db()->query('SELECT web, slug, revised_at FROM topics WHERE is_webhome = 0');
    foreach ($rows as $r) {
        echo $url(ORIGIN . "/pgmfi/wiki/{$r['web']}/{$r['slug']}", $r['revised_at']);
    }
    echo '</urlset>';
    exit;
}

// ---------------------------------------------------------------------------
// search results page (not indexed: infinite query space)

if ($seg === ['search']) {
    $q = trim((string) ($_GET['q'] ?? ''));
    layout_top($q === '' ? 'Search' : "Search: $q", [['wiki', '/pgmfi/wiki/'], ['search', null]], '', '', true);

    if ($q !== '') {
        $count = db()->prepare(
            'SELECT COUNT(*) FROM topics WHERE MATCH(title, body_text) AGAINST (? IN NATURAL LANGUAGE MODE)'
        );
        $count->execute([$q]);
        $total = (int) $count->fetchColumn();
        [$page, $pages, $offset] = paginate($total, 50, (int) ($_GET['page'] ?? 1));

        $st = db()->prepare(
            'SELECT web, name, slug, title, body_text, author, revised_at,
                    MATCH(title, body_text) AGAINST (:q IN NATURAL LANGUAGE MODE) AS score
             FROM topics
             WHERE MATCH(title, body_text) AGAINST (:q IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC
             LIMIT :lim OFFSET :off'
        );
        $st->bindValue(':q', $q);
        $st->bindValue(':lim', 50, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        $results = $st->fetchAll();

        $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $markRe = '/(' . implode('|', array_map(fn($w) => preg_quote($w, '/'), $words)) . ')/iu';
        ?>
        <section>
          <h3><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= h($q) ?>”</h3>
          <ol>
          <?php if (!$results) : ?>
            <li>No matches. Words shorter than 3 characters are not indexed - try a longer term
                or browse the <a href="/pgmfi/wiki/library/all">topic list</a>.</li>
          <?php endif; ?>
          <?php foreach ($results as $r) :
              $pos = false;
              foreach ($words as $w) {
                  $pos = mb_stripos($r['body_text'], $w);
                  if ($pos !== false) {
                      break;
                  }
              }
              $snippet = $pos === false
                  ? mb_substr($r['body_text'], 0, 240)
                  : ($pos > 120 ? '…' : '') . mb_substr($r['body_text'], max(0, $pos - 120), 240);
              $snippet = preg_replace($markRe, '<mark>$1</mark>', h($snippet));
          ?>
            <li>
              <div>
                <a href="/pgmfi/wiki/<?= h($r['web']) ?>/<?= h($r['slug']) ?>"><?= h($r['title']) ?></a>
                <small>· <?= h($r['web']) ?></small>
                <p><?= $snippet ?>…</p>
              </div>
              <small><?= time_tag($r['revised_at']) ?></small>
            </li>
          <?php endforeach; ?>
          </ol>
        </section>
        <?= page_links('/pgmfi/wiki/search?q=' . urlencode($q), $page, $pages) ?>
        <?php
    } else {
        echo '<section><h3>Search</h3><ol><li>Type a query in the box above.</li></ol></section>';
    }
    layout_bottom();
    exit;
}

// ---------------------------------------------------------------------------
// landing page

if ($seg === []) {
    $counts = topic_counts();
    layout_top(
        'Library',
        [['wiki', null]],
        'Archived pgmfi.org wiki: ' . (int) ($counts['library'] ?? 0) . ' technical articles on Honda and Acura '
        . 'ECU development - chipping, ROM editing, sensors, OBD0/OBD1/OBD2 hardware - preserved by HondaBase.',
        ORIGIN . '/pgmfi/wiki/'
    );
    ?>
    <nav aria-label="Webs">
      <a href="/pgmfi/wiki/library/all">
        <h3>Library</h3>
        <p>Technical documentation: ECU hardware, chipping, ROMs, sensors, tools.</p>
        <small><?= (int) ($counts['library'] ?? 0) ?> topics →</small>
      </a>
      <a href="/pgmfi/wiki/home/all">
        <h3>Home</h3>
        <p>Member pages, success stories and community projects.</p>
        <small><?= (int) ($counts['home'] ?? 0) ?> pages →</small>
      </a>
    </nav>
    <article>
      <div><?= webhome_body('library') ?></div>
    </article>
    <?php
    layout_bottom();
    exit;
}

// ---------------------------------------------------------------------------
// web routes

$web = $seg[0];
if (!isset(WEBS[$web]) || count($seg) > 2) {
    not_found($seg);
}

if (count($seg) === 1) { // web home
    layout_top(
        WEBS[$web],
        [['wiki', '/pgmfi/wiki/'], [$web, null]],
        ($web === 'library'
            ? 'The pgmfi.org Library: archived technical documentation on Honda/Acura ECU chipping, ROMs, sensors and tuning tools.'
            : 'Archived pgmfi.org member pages, success stories and community projects.'),
        ORIGIN . "/pgmfi/wiki/$web/"
    );
    ?>
    <article>
      <header>
        <h1><?= h(WEBS[$web]) ?></h1>
        <p><a href="/pgmfi/wiki/<?= h($web) ?>/all">browse all topics →</a></p>
      </header>
      <div><?= webhome_body($web) ?></div>
    </article>
    <?php
    layout_bottom();
    exit;
}

if ($seg[1] === 'all') { // topic list
    $st = db()->prepare(
        'SELECT name, slug, title, author, revised_at FROM topics
         WHERE web = ? AND is_webhome = 0 ORDER BY name'
    );
    $st->execute([$web]);
    $topics = $st->fetchAll();
    layout_top(
        WEBS[$web] . ' topic list',
        [['wiki', '/pgmfi/wiki/'], [$web, "/pgmfi/wiki/$web"], ['all', null]],
        'Alphabetical list of all ' . count($topics) . ' archived pgmfi.org ' . WEBS[$web] . ' wiki topics.',
        ORIGIN . "/pgmfi/wiki/$web/all"
    );
    ?>
    <section>
      <h3><?= count($topics) ?> topics in <?= h(WEBS[$web]) ?></h3>
      <ol>
      <?php foreach ($topics as $t) : ?>
        <li>
          <div>
            <a href="/pgmfi/wiki/<?= h($web) ?>/<?= h($t['slug']) ?>"><?= h($t['title']) ?></a>
            <?php if ($t['author']) : ?><small>· <?= h($t['author']) ?></small><?php endif; ?>
          </div>
          <small><?= time_tag($t['revised_at']) ?></small>
        </li>
      <?php endforeach; ?>
      </ol>
    </section>
    <?php
    layout_bottom();
    exit;
}

// topic page
$st = db()->prepare('SELECT * FROM topics WHERE web = ? AND (slug = ? OR name = ?)');
$st->execute([$web, $seg[1], $seg[1]]);
$topic = $st->fetch();
if (!$topic) {
    not_found($seg);
}
if ($topic['slug'] !== $seg[1]) { // legacy CamelCase URL -> canonical slug
    header('Location: /pgmfi/wiki/' . $web . '/' . $topic['slug'], true, 301);
    exit;
}

$parent = null;
if ($topic['parent_name']) {
    $st = db()->prepare('SELECT slug, title FROM topics WHERE web = ? AND name = ?');
    $st->execute([$web, $topic['parent_name']]);
    $parent = $st->fetch() ?: null;
}

$st = db()->prepare('SELECT filename, local_path, size_bytes FROM attachments WHERE topic_id = ? ORDER BY filename');
$st->execute([$topic['topic_id']]);
$attachments = $st->fetchAll();

$excerpt = mb_substr($topic['body_text'], 0, 155);
if (mb_strlen($topic['body_text']) > 155) {
    $excerpt = preg_replace('/\s+\S*$/u', '', $excerpt) . '…';
}
$canonical = ORIGIN . "/pgmfi/wiki/$web/{$topic['slug']}";
layout_top($topic['title'], [['wiki', '/pgmfi/wiki/'], [$web, "/pgmfi/wiki/$web"], [$topic['title'], null]], $excerpt, $canonical);
?>
<script type="application/ld+json">
<?= json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'TechArticle',
    'headline' => $topic['title'],
    'url' => $canonical,
    'dateModified' => $topic['revised_at'] ? gmdate('c', strtotime($topic['revised_at'])) : null,
    'author' => $topic['author'] ? ['@type' => 'Person', 'name' => $topic['author']] : null,
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'pgmfi.org wiki archive', 'url' => ORIGIN . '/pgmfi/wiki/'],
]), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
<article>
  <header>
    <h1><?= h($topic['title']) ?></h1>
    <p>
      <b><?= h(WEBS[$web]) ?>.<?= h($topic['name']) ?></b>
      <?php if ($topic['revision']) : ?> · r<?= h($topic['revision']) ?><?php endif; ?>
      · <?= time_tag($topic['revised_at']) ?>
      <?php if ($topic['author']) : ?> · <?= h($topic['author']) ?><?php endif; ?>
      <?php if ($parent) : ?>
        · parent: <a href="/pgmfi/wiki/<?= h($web) ?>/<?= h($parent['slug']) ?>"><?= h($parent['title']) ?></a>
      <?php endif; ?>
    </p>
  </header>
  <div><?= $topic['body_html'] ?></div>
  <?php if ($attachments) : ?>
  <section>
    <h3>Attachments</h3>
    <ul>
    <?php foreach ($attachments as $a) : ?>
      <li>
        <div>
          <img src="<?= h(attachment_icon($a['filename'])) ?>" alt="" width="16" height="16">
          <?php $href = str_starts_with($a['local_path'], 'http')
              ? $a['local_path']
              : '/pgmfi/wiki/media/' . implode('/', array_map('rawurlencode', explode('/', $a['local_path']))); ?>
          <a href="<?= h($href) ?>"><?= h($a['filename']) ?></a>
        </div>
        <small><?= fmt_bytes((int) $a['size_bytes']) ?></small>
      </li>
    <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</article>
<?php
layout_bottom();
