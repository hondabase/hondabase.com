<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

$q = trim((string) ($_GET['q'] ?? ''));
$results = [];
$total = 0;
$page = 1;
$pages = 1;

if ($q !== '') {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM topics WHERE MATCH(title) AGAINST (? IN NATURAL LANGUAGE MODE)'
    );
    $stmt->execute([$q]);
    $total = (int) $stmt->fetchColumn();

    [$page, $pages, $offset] = paginate($total, 50, (int) ($_GET['page'] ?? 1));

    $stmt = db()->prepare(
        'SELECT t.topic_id, t.title, t.last_post_at, f.name AS forum_name, f.forum_id,
                (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.topic_id) AS post_count
         FROM topics t
         LEFT JOIN forums f ON f.forum_id = t.forum_id
         WHERE MATCH(t.title) AGAINST (? IN NATURAL LANGUAGE MODE)
         LIMIT 50 OFFSET ' . $offset
    );
    $stmt->execute([$q]);
    $results = $stmt->fetchAll();
}

$base = '/pgmfi/forum/search.php?q=' . urlencode($q);
layout_top('Search', [['index', '/pgmfi/forum/'], ['search', null]]);
?>

<?php if ($q === '') : ?>
  <p class="row-meta">Type a query to search recovered topic titles.</p>
<?php else : ?>
  <div class="page-head">
    <p class="row-meta" data-live-region="meta">
      <span><b><?= number_format($total) ?></b> topic<?= $total === 1 ? '' : 's' ?> matching "<?= h($q) ?>"</span>
    </p>
    <nav data-live-region="pager-top"><?= page_links($base, $page, $pages) ?></nav>
  </div>

  <ul class="panel" data-live-region="results">
    <?php if (!$results) : ?>
      <li class="empty">No matches in the recovered archive.</li>
    <?php endif; ?>
    <?php foreach ($results as $r) : ?>
      <li data-key="t<?= (int) $r['topic_id'] ?>">
        <div>
          <h3><a class="row-title" href="/pgmfi/forum/topic.php?id=<?= (int) $r['topic_id'] ?>"><?= h($r['title']) ?></a></h3>
        </div>
        <p class="row-meta">
          <b><?= (int) $r['post_count'] ?></b> posts
          <?php if ($r['forum_id']) : ?>
            · in <a href="/pgmfi/forum/forum.php?id=<?= (int) $r['forum_id'] ?>"><?= h($r['forum_name']) ?></a>
          <?php endif; ?>
          · <?= time_tag($r['last_post_at']) ?>
        </p>
      </li>
    <?php endforeach; ?>
  </ul>

  <nav data-live-region="pager-bottom"><?= page_links($base, $page, $pages) ?></nav>
<?php endif; ?>

<?php layout_bottom(); ?>
