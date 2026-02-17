<?php
// POST /api/auth/register
$data = json_decode(file_get_contents('php://input'), true);

$username = clean($data['username'] ?? '');
$email    = clean($data['email']    ?? '');
$password =       $data['password']  ?? '';

if (strlen($username) < 3) json_error('Nazwa użytkownika musi mieć min. 3 znaki');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Nieprawidłowy email');
if (strlen($password) < 6) json_error('Hasło musi mieć min. 6 znaków');

// sprawdź czy email/username wolne
$stmt = db()->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) json_error('Email lub nazwa użytkownika jest zajęta');

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = db()->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
$stmt->execute([$username, $email, $hash]);
$userId = db()->lastInsertId();

// Utwórz sesję
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
db()->prepare("INSERT INTO user_sessions (token, user_id, expires_at) VALUES (?, ?, ?)")
    ->execute([$token, $userId, $expires]);

json_ok(['token' => $token, 'username' => $username], 'Konto zostało utworzone!');
