<?php
// DELETE /api/posts/{id}
$user   = require_auth();
$postId = (int)($m[1] ?? 0);

$stmt = db()->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) json_error('Post nie istnieje', 404);
if ($post['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
    json_error('Brak uprawnień', 403);
}

// Usuń plik z dysku jeśli istnieje
if ($post['media_url']) {
    $localPath = str_replace(UPLOAD_URL, UPLOAD_PATH, $post['media_url']);
    if (file_exists($localPath)) unlink($localPath);
}

db()->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);
json_ok([], 'Post usunięty');
