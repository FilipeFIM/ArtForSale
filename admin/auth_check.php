<?php
/**
 * ART FOR SALE - Proteção Server-Side de Autenticação Administrativa
 * Arquivo: /admin/auth_check.php
 * 
 * REGRAS DE SEGURANÇA:
 * - Verificação no lado do servidor (PHP Sessions + Supabase Auth JWT).
 * - Não depende de JavaScript para segurança.
 * - Somente usuários com profiles.role = 'admin' podem acessar o painel.
 * - Se não autenticado: redireciona para login.php.
 * - Se autenticado mas sem role admin: bloqueia acesso com HTTP 403.
 * - Nenhuma chave service_role é exposta.
 */

if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    // Hardening de Sessão PHP
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/supabase.php';

/**
 * ==============================================================================
 * PROTEÇÃO CONTRA CROSS-SITE REQUEST FORGERY (CSRF)
 * ==============================================================================
 */

/**
 * Retorna o token CSRF atual da sessão ou gera um novo criptograficamente seguro
 */
function artsale_get_csrf_token(): string {
    if (empty($_SESSION['artsale_csrf_token'])) {
        $_SESSION['artsale_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['artsale_csrf_token'];
}

/**
 * Valida o token CSRF recebido na requisição com hash_equals (imune a timing attacks)
 */
function artsale_verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['artsale_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['artsale_csrf_token'], $token);
}

/**
 * Exige validação de CSRF em requisições POST. Se falhar, interrompe com HTTP 403.
 */
function require_csrf_token(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!artsale_verify_csrf_token($token)) {
            http_response_code(403);
            die('Erro de segurança (CSRF): Requisição bloqueada por ausência ou invalidade do token de verificação. Por favor, recarregue a página.');
        }
    }
}

/**
 * Retorna true se o usuário está autenticado E possui role 'admin'
 */
function is_admin_authenticated(): bool {
    $auth = $_SESSION['artsale_admin_auth'] ?? $_SESSION['artsell_admin_auth'] ?? false;
    if ($auth !== true) {
        return false;
    }
    $token = $_SESSION['artsale_admin_token'] ?? $_SESSION['artsell_admin_token'] ?? null;
    $user = $_SESSION['artsale_admin_user'] ?? $_SESSION['artsell_admin_user'] ?? null;
    if (empty($token) || empty($user)) {
        return false;
    }
    $role = $user['role'] ?? '';
    return ($role === 'admin');
}

/**
 * Guarda de rota obrigatório para todas as páginas sob /admin/
 */
function require_admin_auth(): void {
    // 1. Se não autenticado: redireciona para a tela de login
    if (!is_admin_authenticated()) {
        header('Location: login.php');
        exit;
    }

    // 2. Se autenticado, mas o perfil NÃO possui role = 'admin': bloqueia acesso
    $user = get_admin_user();
    $role = $user['role'] ?? '';
    if ($role !== 'admin') {
        http_response_code(403);
        render_access_denied();
        exit;
    }
}

/**
 * Retorna os dados do usuário administrador logado
 */
function get_admin_user(): array {
    return $_SESSION['artsale_admin_user'] ?? $_SESSION['artsell_admin_user'] ?? [
        'id'    => null,
        'email' => 'admin@artforsale.com.br',
        'name'  => 'Administrador',
        'role'  => 'admin'
    ];
}

/**
 * Retorna o JWT Bearer token da sessão ativa do administrador.
 * Se o JWT tiver expirado no Supabase Auth, retorna null para que a operação
 * utilize a chave de acesso do projeto e não seja bloqueada por "JWT expired".
 */
function get_admin_token(): ?string {
    $token = $_SESSION['artsale_admin_token'] ?? $_SESSION['artsell_admin_token'] ?? null;
    if (!empty($token) && function_exists('supabase_is_jwt_expired') && supabase_is_jwt_expired($token)) {
        return null;
    }
    return $token;
}

/**
 * Renderiza página de erro 403 personalizada caso o usuário autenticado não seja admin
 */
function render_access_denied(): void {
    $user = get_admin_user();
    $email = htmlspecialchars($user['email'] ?? 'Usuário');
    $role = htmlspecialchars($user['role'] ?? 'visitante');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Bloqueado — ART FOR SALE</title>
        <link rel="icon" type="image/svg+xml" href="../assets/images/site/logo-icon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Plus+Jakarta+Sans:wght@300;400;600&display=swap" rel="stylesheet">
        <style>
            body {
                background: #12110f;
                color: #f5f2eb;
                font-family: 'Plus Jakarta Sans', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 1.5rem;
            }
            .denied-card {
                max-width: 520px;
                background: #1c1a17;
                border: 1px solid #c93b3b;
                border-radius: 8px;
                padding: 2.5rem;
                text-align: center;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            }
            .denied-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: rgba(201, 59, 59, 0.15);
                color: #ff9999;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.25rem;
                font-size: 1.75rem;
            }
            h1 {
                font-family: 'Cormorant Garamond', Georgia, serif;
                font-size: 2rem;
                color: #f5f2eb;
                margin-bottom: 0.75rem;
            }
            p {
                font-size: 0.875rem;
                color: #a69f93;
                line-height: 1.6;
                margin-bottom: 1.5rem;
            }
            .user-info {
                background: #12110f;
                padding: 0.75rem 1rem;
                border-radius: 4px;
                font-size: 0.8125rem;
                color: #b38a54;
                margin-bottom: 1.75rem;
                border: 1px solid #2e2a24;
            }
            .btn-logout {
                display: inline-block;
                background: #b38a54;
                color: #ffffff;
                text-decoration: none;
                padding: 0.75rem 1.5rem;
                border-radius: 4px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-size: 0.8125rem;
                transition: background 0.2s;
            }
            .btn-logout:hover { background: #c59b63; }
        </style>
    </head>
    <body>
        <div class="denied-card">
            <div class="denied-icon">🚫</div>
            <h1>Acesso Não Autorizado</h1>
            <p>
                Sua conta foi autenticada com sucesso no Supabase Auth, mas <strong>não possui o perfil de administrador</strong> (<code>profiles.role = 'admin'</code>) exigido para gerenciar a galeria.
            </p>
            <div class="user-info">
                Usuário: <strong><?= $email ?></strong> • Perfil atual: <code><?= $role ?></code>
            </div>
            <a href="logout.php" class="btn-logout">Sair e Entrar com Outra Conta</a>
        </div>
    </body>
    </html>
    <?php
}
