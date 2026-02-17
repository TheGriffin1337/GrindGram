<?php
// GET /api/posts?page=1&type=all
$page   = max(1, (int)($_GET['page']   ?? 1));
$limit  = min(20, max(1, (int)($_GET['limit'] ?? 10)));
$type   = $_GET['type'] ?? 'all';
$offset = ($page - 1) * $limit;
$user   = auth(); // opcjonalne

$where  = "WHERE p.is_published = 1";
$params = [];

if (in_array($type, ['image','video','text','ai'])) {
    $where   .= " AND p.type = ?";
    $params[] = $type;
}

$sql = "SELECT
            p.id, p.type, p.title, p.content, p.media_url, p.thumbnail,
            p.tags, p.views, p.likes_count, p.created_at,
            u.username, u.avatar,
            " . ($user ? "EXISTS(SELECT 1 FROM likes l WHERE l.post_id = p.id AND l.user_id = {$user['id']}) as liked" : "0 as liked") . "
        FROM posts p
        JOIN users u ON u.id = p.user_id
        {$where}
        ORDER BY p.created_at DESC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Dekoduj JSON tags
foreach ($posts as &$post) {
    $post['tags'] = json_decode($post['tags'] ?? '[]', true);
    // Zwiększ views
    db()->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);
}

// Policz łączną liczbę
$countStmt = db()->prepare("SELECT COUNT(*) FROM posts p {$where}");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

json_ok([
    'posts'      => $posts,
    'page'       => $page,
    'limit'      => $limit,
    'total'      => (int)$total,
    'has_more'   => ($offset + $limit) < $total,
]);
