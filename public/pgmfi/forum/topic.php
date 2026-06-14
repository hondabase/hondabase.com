<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

$topicId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT t.topic_id, t.title, t.forum_id, f.name AS forum_name
     FROM topics t LEFT JOIN forums f ON f.forum_id = t.forum_id
     WHERE t.topic_id = ?'
);
$stmt->execute([$topicId]);
$topic = $stmt->fetch();
if (!$topic) {
    http_response_code(404);
    layout_top('Topic not found', [['index', '/pgmfi/forum/'], ['404', null]]);
    echo '<p class="row-meta">Topic not found in the recovered archive.</p>';
    layout_bottom();
    exit;
}

$stmt = db()->prepare('SELECT COUNT(*) FROM posts WHERE topic_id = ?');
$stmt->execute([$topicId]);
$total = (int) $stmt->fetchColumn();

[$page, $pages, $offset] = paginate($total, 15, (int) ($_GET['page'] ?? 1));

$stmt = db()->prepare(
    'SELECT p.post_id, p.author_name, p.subject, p.posted_at, p.body_html,
            a.rank_seen, a.post_count_seen,
            c.archive_timestamp, c.is_supplemental
     FROM posts p
     LEFT JOIN authors a ON a.author_id = p.author_id
     LEFT JOIN captures c ON c.id = p.source_capture_id
     WHERE p.topic_id = ?
     ORDER BY p.posted_at IS NULL, p.posted_at, p.post_id
     LIMIT 15 OFFSET ' . $offset
);
$stmt->execute([$topicId]);
$posts = $stmt->fetchAll();

$crumbs = [['index', '/pgmfi/forum/']];
if ($topic['forum_id']) {
    $crumbs[] = [$topic['forum_name'] ?? 'forum', '/pgmfi/forum/forum.php?id=' . (int) $topic['forum_id']];
}
$crumbs[] = [mb_strimwidth($topic['title'], 0, 60, '…'), null];

$base = '/pgmfi/forum/topic.php?id=' . $topicId;
layout_top($topic['title'], $crumbs, 'mode=topic&id=' . $topicId);
?>

<div class="page-head">
  <h2><?= h($topic['title']) ?></h2>
  <nav data-live-region="pager-top"><?= page_links($base, $page, $pages) ?></nav>
</div>

<?php if (!$posts) : ?>
  <p class="row-meta">
    No post content recovered for this topic yet. The title was mined from an archived forum listing;
    recovery of the topic page is still in progress (or no readable capture survives).
  </p>
<?php endif; ?>

<div data-live-region="posts">
  <?php foreach ($posts as $i => $p) : ?>
    <article class="post" data-key="p<?= (int) $p['post_id'] ?>">
      <header>
        <h3>
          <?= h($p['author_name']) ?>
          <?php if ($p['rank_seen']) : ?><small><?= h($p['rank_seen']) ?></small><?php endif; ?>
        </h3>
        <p>
          <span>#<?= $offset + $i + 1 ?></span>
          <?= time_tag($p['posted_at']) ?>
          <?php if ($p['archive_timestamp']) : ?>
            <span class="capture" title="Internet Archive capture date<?= $p['is_supplemental'] ? ' (supplemental capture)' : '' ?>">
              ◈ <?= h(fmt_capture($p['archive_timestamp'])) ?>
            </span>
          <?php endif; ?>
        </p>
      </header>
      <?php if ($p['subject'] && strcasecmp(trim($p['subject']), trim($topic['title'])) !== 0) : ?>
        <h4><?= h($p['subject']) ?></h4>
      <?php endif; ?>
      <div class="postbody">
        <?= render_body($p['body_html']) /* parser-generated markup from the recovery pipeline */ ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<nav data-live-region="pager-bottom"><?= page_links($base, $page, $pages) ?></nav>

<?php layout_bottom(); ?>
