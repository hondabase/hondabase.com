<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

$forums = db()->query(
    'SELECT f.forum_id, f.parent_forum_id, f.is_category, f.name, f.description_text,
            COUNT(DISTINCT t.topic_id) AS topic_count,
            COUNT(p.post_id) AS post_count,
            MAX(p.posted_at) AS last_post_at
     FROM forums f
     LEFT JOIN topics t ON t.forum_id = f.forum_id
     LEFT JOIN posts p ON p.topic_id = t.topic_id
     GROUP BY f.forum_id
     ORDER BY f.forum_id'
)->fetchAll();

$categories = [];
$children = [];
$orphans = [];
foreach ($forums as $f) {
    if ((int) $f['is_category'] === 1) {
        $categories[$f['forum_id']] = $f;
    } elseif ($f['parent_forum_id'] !== null) {
        $children[$f['parent_forum_id']][] = $f;
    } else {
        $orphans[] = $f;
    }
}
$groups = [];
foreach ($categories as $id => $cat) {
    $groups[] = ['name' => $cat['name'], 'forums' => $children[$id] ?? []];
}
// Forums whose parent is not a known category still deserve a home.
foreach ($children as $pid => $list) {
    if (!isset($categories[$pid])) {
        $orphans = array_merge($orphans, $list);
    }
}
if ($orphans) {
    $groups[] = ['name' => 'Forums', 'forums' => $orphans];
}

layout_top('Forum index', [['index', null]]);
?>

<?php foreach ($groups as $g) : ?>
  <?php if (!$g['forums']) { continue; } ?>
  <section>
    <h2><?= h($g['name']) ?></h2>
    <ul class="panel" data-live-region="g<?= md5($g['name']) ?>">
      <?php foreach ($g['forums'] as $f) : ?>
        <li data-key="f<?= (int) $f['forum_id'] ?>">
          <div>
            <h3><a class="row-title" href="/pgmfi/forum/forum.php?id=<?= (int) $f['forum_id'] ?>"><?= h($f['name']) ?></a></h3>
            <?php if ($f['description_text']) : ?>
              <p class="row-desc"><?= h($f['description_text']) ?></p>
            <?php endif; ?>
          </div>
          <p class="row-meta">
            <b><?= number_format((int) $f['topic_count']) ?></b> topics ·
            <b><?= number_format((int) $f['post_count']) ?></b> posts ·
            <?= time_tag($f['last_post_at']) ?>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>

<?php layout_bottom(); ?>
