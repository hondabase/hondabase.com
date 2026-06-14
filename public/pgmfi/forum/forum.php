<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

$forumId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT forum_id, name, description_text FROM forums WHERE forum_id = ?');
$stmt->execute([$forumId]);
$forum = $stmt->fetch();
if (!$forum) {
    http_response_code(404);
    layout_top('Forum not found', [['index', '/pgmfi/forum/'], ['404', null]]);
    echo '<p class="row-meta">Forum not found in the recovered archive.</p>';
    layout_bottom();
    exit;
}

$stmt = db()->prepare('SELECT COUNT(*) FROM topics WHERE forum_id = ?');
$stmt->execute([$forumId]);
$total = (int) $stmt->fetchColumn();

[$page, $pages, $offset] = paginate($total, 50, (int) ($_GET['page'] ?? 1));

$stmt = db()->prepare(
    'SELECT t.topic_id, t.title, t.first_post_at, t.last_post_at,
            COUNT(p.post_id) AS post_count,
            (SELECT p2.author_name FROM posts p2 WHERE p2.topic_id = t.topic_id
             ORDER BY p2.posted_at IS NULL, p2.posted_at, p2.post_id LIMIT 1) AS starter
     FROM topics t
     LEFT JOIN posts p ON p.topic_id = t.topic_id
     WHERE t.forum_id = ?
     GROUP BY t.topic_id
     ORDER BY COALESCE(t.last_post_at, t.first_post_at) IS NULL,
              COALESCE(t.last_post_at, t.first_post_at) DESC, t.topic_id DESC
     LIMIT 50 OFFSET ' . $offset
);
$stmt->execute([$forumId]);
$topics = $stmt->fetchAll();

$base = '/pgmfi/forum/forum.php?id=' . $forumId;
layout_top($forum['name'], [['index', '/pgmfi/forum/'], [$forum['name'], null]], 'mode=forum&id=' . $forumId);
?>

<div class="page-head">
  <div>
    <h2><?= h($forum['name']) ?></h2>
    <?php if ($forum['description_text']) : ?>
      <p><?= h($forum['description_text']) ?></p>
    <?php endif; ?>
    <p class="row-meta" data-live-region="meta"><span><?= number_format($total) ?> recovered topics</span></p>
  </div>
  <nav data-live-region="pager-top"><?= page_links($base, $page, $pages) ?></nav>
</div>

<ul class="panel" data-live-region="list">
  <?php if (!$topics) : ?>
    <li class="empty">No topic content recovered for this forum yet — recovery is still running.</li>
  <?php endif; ?>
  <?php foreach ($topics as $t) : ?>
    <li data-key="t<?= (int) $t['topic_id'] ?>">
      <div>
        <h3><a class="row-title" href="/pgmfi/forum/topic.php?id=<?= (int) $t['topic_id'] ?>"><?= h($t['title']) ?></a></h3>
        <?php if ((int) $t['post_count'] === 0) : ?>
          <i class="tag">title only</i>
        <?php endif; ?>
      </div>
      <p class="row-meta">
        <b><?= (int) $t['post_count'] ?></b> posts ·
        by <?= h($t['starter'] ?? '—') ?> ·
        <?= time_tag($t['last_post_at']) ?>
      </p>
    </li>
  <?php endforeach; ?>
</ul>

<nav data-live-region="pager-bottom"><?= page_links($base, $page, $pages) ?></nav>

<?php layout_bottom(); ?>
