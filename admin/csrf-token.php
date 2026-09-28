<?php
/**
 * ART FOR SALE - Heartbeat e Sincronização de Token CSRF
 * Arquivo: /admin/csrf-token.php
 * Slogan: "Arte que transforma espaços."
 */

require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!is_admin_authenticated()) {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'error'         => 'Sessão expirada'
    ]);
    exit;
}

$token = artsale_get_csrf_token();

echo json_encode([
    'authenticated' => true,
    'csrf_token'    => $token,
    'lifetime'      => 2592000 // 30 dias de validade
]);
