<?php
// POST /api/auth/login
$data = json_decode(file_get_contents('php://input'), true);

$email    = clean($data['email']    ?? '');
$password =       $data['password']  ?? '';

if (!$email || !$password) json_error('Podaj email i hasło');

$stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    json_error('Nieprawidłowy email lub hasło', 401);
}

$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
db()->prepare("INSERT INTO user_sessions (token, user_id, expires_at) VALUES (?, ?, ?)")
    ->execute([$token, $user['id'], $expires]);

json_ok([
    'token'    => $token,
    'user_id'  => $user['id'],
    'username' => $user['username'],
    'avatar'   => $user['avatar'],
], 'Zalogowano!');
