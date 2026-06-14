<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$totals = db()->query(
    'SELECT (SELECT COUNT(*) FROM topics) t, (SELECT COUNT(*) FROM posts) p, (SELECT COUNT(*) FROM authors) a'
)->fetch();

$mode = (string) ($_GET['mode'] ?? 'site');
$id = (int) ($_GET['id'] ?? 0);

// A page-scope signature: when it changes, the current page re-renders itself.
$sig = $totals['t'] . ':' . $totals['p'];
if ($mode === 'forum' && $id) {
    $stmt = db()->prepare(
        'SELECT CONCAT(COUNT(DISTINCT t.topic_id), ":", COUNT(p.post_id), ":", COALESCE(MAX(p.updated_at), ""))
         FROM topics t LEFT JOIN posts p ON p.topic_id = t.topic_id
         WHERE t.forum_id = ?'
    );
    $stmt->execute([$id]);
    $sig = (string) $stmt->fetchColumn();
} elseif ($mode === 'topic' && $id) {
    $stmt = db()->prepare(
        'SELECT CONCAT(COUNT(*), ":", COALESCE(MAX(updated_at), "")) FROM posts WHERE topic_id = ?'
    );
    $stmt->execute([$id]);
    $sig = (string) $stmt->fetchColumn();
}

echo json_encode([
    'sig' => $sig,
    'topics' => (int) $totals['t'],
    'posts' => (int) $totals['p'],
    'authors' => (int) $totals['a'],
]);
