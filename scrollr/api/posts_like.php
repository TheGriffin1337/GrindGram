<?php
// POST /api/posts/{id}/like
$user   = require_auth();
$postId = (int)($m[1] ?? 0);

if (!$postId) json_error('Brak ID posta');

// Toggle like
$stmt = db()->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
$stmt->execute([$user['id'], $postId]);
$existing = $stmt->fetch();

if ($existing) {
    db()->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?")->execute([$user['id'], $postId]);
    db()->prepare("UPDATE posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?")->execute([$postId]);
    json_ok(['liked' => false], 'Polubienie usunięte');
} else {
    db()->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)")->execute([$user['id'], $postId]);
    db()->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$postId]);
    json_ok(['liked' => true], 'Polubiono!');
}
