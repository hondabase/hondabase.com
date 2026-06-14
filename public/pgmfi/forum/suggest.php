<?php
declare(strict_types=1);
require __DIR__ . '/inc/boot.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) > 100) {
    echo '[]';
    exit;
}

if (mb_strlen($q) < 3) {
    $stmt = db()->prepare(
        'SELECT t.topic_id, t.title, f.name AS forum_name
         FROM topics t LEFT JOIN forums f ON f.forum_id = t.forum_id
         WHERE t.title LIKE ? ORDER BY t.last_post_at DESC LIMIT 8'
    );
    $stmt->execute([$q . '%']);
} else {
    // Prefix-match every word in boolean mode: "map sensor" -> "+map* +sensor*"
    $words = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $boolean = implode(' ', array_map(fn($w) => '+' . $w . '*', array_slice($words, 0, 6)));
    $stmt = db()->prepare(
        'SELECT t.topic_id, t.title, f.name AS forum_name,
                MATCH(t.title) AGAINST (? IN BOOLEAN MODE) AS score
         FROM topics t LEFT JOIN forums f ON f.forum_id = t.forum_id
         WHERE MATCH(t.title) AGAINST (? IN BOOLEAN MODE)
         ORDER BY score DESC LIMIT 8'
    );
    $stmt->execute([$boolean, $boolean]);
}

$out = [];
foreach ($stmt->fetchAll() as $row) {
    $out[] = [
        'id' => (int) $row['topic_id'],
        'title' => $row['title'],
        'forum' => $row['forum_name'],
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
