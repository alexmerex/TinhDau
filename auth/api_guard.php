<?php
require_once __DIR__ . '/auth_helper.php';

function requireApiLogin(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function requireApiAdmin(): void {
    requireApiLogin();

    if (!isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
