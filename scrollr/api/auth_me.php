<?php
// GET /api/auth/me
$user = require_auth();
json_ok([
    'id'       => $user['id'],
    'username' => $user['username'],
    'email'    => $user['email'],
    'avatar'   => $user['avatar'],
    'bio'      => $user['bio'],
]);
