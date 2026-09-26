<?php
/**
 * ART FOR SALE - Encerramento de Sessão Administrativa
 * Arquivo: /admin/logout.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

require_once __DIR__ . '/auth_check.php';

if (function_exists('artsale_clear_auth_cookies')) {
    artsale_clear_auth_cookies();
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Encerrando sessão...</title>
</head>
<body style="background: #0f0e0c; color: #b38a54; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh;">
    <p>Encerrando sessão com segurança...</p>
    <script>
        try {
            sessionStorage.removeItem('artsale_admin_token');
            sessionStorage.removeItem('artsale_admin_refresh_token');
            sessionStorage.removeItem('artsale_admin_user');
            sessionStorage.removeItem('artsell_admin_token');
            sessionStorage.removeItem('artsell_admin_refresh_token');
            sessionStorage.removeItem('artsell_admin_user');
            localStorage.removeItem('artsale_admin_token');
            localStorage.removeItem('artsale_admin_refresh_token');
        } catch (_) {}
        window.location.href = 'login.php?msg=logout';
    </script>
</body>
</html>
