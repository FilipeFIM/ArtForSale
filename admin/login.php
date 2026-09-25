<?php
/**
 * ART FOR SALE - Login Administrativo Seguro (Supabase Auth)
 * Arquivo: /admin/login.php
 * Slogan: "Arte que transforma espaços."
 * 
 * REGRAS DE SEGURANÇA:
 * - Autenticação obrigatória com Supabase Auth (E-mail e Senha).
 * - Verificação estrita de profiles.role = 'admin'.
 * - Não permite cadastro público de administradores.
 * - Proteção server-side com sessão PHP segura e tokens JWT.
 * - Suporta autenticação direta pelo navegador (fetch nativo) com sincronização segura da sessão PHP,
 *   garantindo funcionamento perfeito mesmo se o PHP local não possuir cURL ou OpenSSL habilitados.
 * - Identidade visual editorial de luxo preservada.
 */

require_once __DIR__ . '/auth_check.php';

// Se já estiver autenticado como admin, vai direto para o dashboard
if (is_admin_authenticated()) {
    header('Location: index.php');
    exit;
}

// 1. Sincronização de Sessão via Client-Side Auth (fetch do navegador para o PHP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'client_auth_sync') {
    header('Content-Type: application/json');
    $token = trim($_POST['access_token'] ?? '');

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Token de acesso não informado.']);
        exit;
    }

    // Validação estrita do JWT diretamente com a API do Supabase Auth (/auth/v1/user)
    // O backend NUNCA confia cegamente em parâmetros POST enviados pelo navegador.
    $authAccount = supabase_get_auth_user($token);
    if (empty($authAccount) || empty($authAccount['id'])) {
        http_response_code(401);
        echo json_encode(['sucesso' => false, 'erro' => 'Token de autenticação inválido ou expirado no Supabase Auth.']);
        exit;
    }

    $userId = $authAccount['id'];
    $userEmail = $authAccount['email'] ?? '';

    // Consulta perfil na tabela public.profiles para confirmar role = 'admin'
    $profile = supabase_get_user_profile($userId, $token);
    if (!$profile || ($profile['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado: Este usuário não possui a permissão de administrador (profiles.role = admin).']);
        exit;
    }

    // Autenticação comprovada: regenera ID de sessão para blindagem contra Session Fixation
    session_regenerate_id(true);

    $_SESSION['artsale_admin_auth'] = true;
    $_SESSION['artsale_admin_token'] = $token;
    $_SESSION['artsale_admin_user'] = [
        'id'    => $userId,
        'email' => $userEmail,
        'name'  => $profile['name'] ?? ($authAccount['user_metadata']['name'] ?? (explode('@', $userEmail)[0] ?? 'Administrador')),
        'role'  => 'admin'
    ];
    $_SESSION['artsell_admin_auth'] = true;
    $_SESSION['artsell_admin_token'] = $token;
    $_SESSION['artsell_admin_user'] = $_SESSION['artsale_admin_user'];

    echo json_encode(['sucesso' => true, 'redirect' => 'index.php']);
    exit;
}

$erro = '';
$sucesso = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $sucesso = 'Sessão administrativa encerrada com segurança.';
}

// 2. Processamento Tradicional Server-Side (Fallback com validação CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'client_auth_sync') {
    require_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha o e-mail e a senha cadastrados.';
    } else {
        $authSuccess = false;
        $adminUser = null;
        $adminToken = null;

        if (supabase_is_configured()) {
            $authRes = supabase_auth_login($email, $senha);

            if (!empty($authRes['sucesso']) && !empty($authRes['access_token'])) {
                $jwtToken = $authRes['access_token'];
                $supabaseUser = $authRes['user'];
                $userId = $supabaseUser['id'] ?? '';

                $profile = supabase_get_user_profile($userId, $jwtToken);

                if ($profile && isset($profile['role']) && $profile['role'] === 'admin') {
                    $authSuccess = true;
                    $adminToken = $jwtToken;
                    $adminUser = [
                        'id'    => $userId,
                        'email' => $supabaseUser['email'] ?? $email,
                        'name'  => $profile['name'] ?? ($supabaseUser['user_metadata']['name'] ?? (explode('@', $email)[0] ?? 'Administrador')),
                        'role'  => 'admin'
                    ];
                } else {
                    $erro = 'Acesso bloqueado: Este usuário não possui o perfil de administrador (profiles.role = admin) cadastrado na galeria.';
                }
            } else {
                $erro = $authRes['erro'] ?? 'E-mail ou senha incorretos.';
            }
        } else {
            $erro = 'Supabase não configurado. Verifique as variáveis de ambiente no arquivo .env.';
        }

        if ($authSuccess && $adminUser) {
            session_regenerate_id(true);

            $_SESSION['artsale_admin_auth'] = true;
            $_SESSION['artsale_admin_token'] = $adminToken;
            $_SESSION['artsale_admin_user'] = $adminUser;
            $_SESSION['artsell_admin_auth'] = true;
            $_SESSION['artsell_admin_token'] = $adminToken;
            $_SESSION['artsell_admin_user'] = $adminUser;

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Administrativo — Art For Sale</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/site/logo-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Anti-flicker Theme Initialization -->
    <script>
        (function() {
            const saved = localStorage.getItem('artforsale_admin_theme') || 'light';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        :root {
            --bg-dark: #faf8f5;
            --bg-card: #ffffff;
            --bg-card-alt: #f7f3ec;
            --color-gold: #b38a54;
            --color-gold-hover: #9c733e;
            --color-gold-subtle: rgba(179, 138, 84, 0.08);
            --text-main: #1c1b18;
            --text-muted: #6e675f;
            --border-color: #e8e2d8;
            --border-gold: rgba(179, 138, 84, 0.35);
            --shadow-card: 0 24px 60px rgba(28, 27, 24, 0.08), 0 4px 16px rgba(179, 138, 84, 0.06);
        }

        [data-theme="dark"] {
            --bg-dark: #0e0d0b;
            --bg-card: #171513;
            --bg-card-alt: #201e1a;
            --color-gold: #c59b63;
            --color-gold-hover: #d8af77;
            --color-gold-subtle: rgba(179, 138, 84, 0.15);
            --text-main: #f5f2eb;
            --text-muted: #9c968b;
            --border-color: #27241f;
            --border-gold: rgba(179, 138, 84, 0.35);
            --shadow-card: 0 25px 60px rgba(0, 0, 0, 0.65);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            position: relative;
            background-image: 
                radial-gradient(circle at 50% 12%, rgba(179, 138, 84, 0.12) 0%, transparent 60%),
                radial-gradient(circle at 80% 88%, rgba(179, 138, 84, 0.06) 0%, transparent 45%);
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .login-wrapper {
            width: 100%;
            max-width: 470px;
        }

        .login-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 12px;
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-card);
            text-align: center;
            position: relative;
            transition: all 0.25s ease;
        }

        .login-logo {
            margin-bottom: 1.75rem;
        }

        .badge-security {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: var(--color-gold-subtle);
            color: var(--color-gold);
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            border: 1px solid var(--border-gold);
            margin-bottom: 1.25rem;
        }

        .login-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 2.25rem;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 0.4rem;
            letter-spacing: -0.01em;
            line-height: 1.15;
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.85rem;
            line-height: 1.5;
        }

        .alert-error {
            background-color: rgba(201, 59, 59, 0.10);
            border: 1px solid #c93b3b;
            color: #d32f2f;
            padding: 0.9rem 1.15rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            margin-bottom: 1.5rem;
            text-align: left;
            line-height: 1.45;
        }

        [data-theme="dark"] .alert-error {
            background-color: rgba(201, 59, 59, 0.18);
            color: #ff9999;
        }

        .alert-success {
            background-color: rgba(46, 125, 50, 0.10);
            border: 1px solid #2e7d32;
            color: #2e7d32;
            padding: 0.9rem 1.15rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        [data-theme="dark"] .alert-success {
            background-color: rgba(46, 125, 50, 0.2);
            color: #81c784;
        }

        .form-group {
            text-align: left;
            margin-bottom: 1.35rem;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--color-gold);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.9rem 1.15rem;
            color: var(--text-main);
            font-size: 0.9375rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: var(--color-gold);
            box-shadow: 0 0 0 3px rgba(179, 138, 84, 0.2);
            background-color: var(--bg-card);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--color-gold) 0%, var(--color-gold-hover) 100%);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 1rem;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 0.85rem;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 18px rgba(179, 138, 84, 0.3);
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(179, 138, 84, 0.45);
        }

        .btn-submit:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .login-notice {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.55;
        }

        .login-notice strong {
            color: var(--color-gold);
        }

        .back-link {
            display: inline-block;
            margin-top: 1.25rem;
            color: var(--text-muted);
            font-size: 0.78rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--color-gold);
        }
    </style>
</head>
<body>

    <!-- Theme Switcher Top-Right -->
    <div style="position: fixed; top: 1.25rem; right: 1.5rem; z-index: 50;">
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleAdminTheme()" title="Alternar tema visual (Claro / Escuro)">
            <span id="themeToggleIcon">🌙</span>
            <span id="themeToggleLabel">Escuro</span>
        </button>
    </div>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-logo" style="display: flex; justify-content: center;">
                <a href="../index.php">
                    <img src="../assets/images/site/logo.svg" alt="ART FOR SALE" class="admin-logo-light" width="190" height="44">
                    <img src="../assets/images/site/logo-white.svg" alt="ART FOR SALE" class="admin-logo-dark" width="190" height="44">
                </a>
            </div>

            <div class="badge-security">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Supabase Auth • Role: Admin
            </div>

            <h1 class="login-title">Painel da Curadoria</h1>
            <p class="login-subtitle">Acesso exclusivo para administradores autorizados</p>

            <!-- Alerta dinâmico via JavaScript -->
            <div class="alert-error" id="jsAlertBox" style="display: none;"></div>

            <!-- Alertas gerados pelo PHP -->
            <?php if (!empty($erro)): ?>
                <div class="alert-error" id="phpAlertBox"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <?php if (!empty($sucesso)): ?>
                <div class="alert-success"><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="adminLoginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                <div class="form-group">
                    <label for="email" class="form-label">E-mail Administrativo</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="artforsale1944@gmail.com" 
                        required 
                        autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? 'filipefim93@gmail.com') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        class="form-input" 
                        placeholder="••••••••••••" 
                        required 
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit">Entrar com Supabase Auth</button>
            </form>

            <div class="login-notice">
                O cadastro público de administradores é desativado por segurança. Novos operadores devem ser autorizados no Supabase com <code>profiles.role = 'admin'</code>.
            </div>

            <details style="margin-top: 1.5rem; text-align: left; font-size: 0.75rem; color: var(--text-muted); background: var(--bg-card-alt); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.85rem 1rem;">
                <summary style="cursor: pointer; color: var(--color-gold); font-weight: 600; outline: none;">Primeiro acesso? Como conceder a role no Supabase</summary>
                <div style="margin-top: 0.65rem; line-height: 1.5; color: var(--text-muted);">
                    1. No <a href="https://supabase.com/dashboard" target="_blank" rel="noopener" style="color: var(--color-gold); text-decoration: underline;">Supabase Dashboard</a>, confirme seu usuário em <strong>Authentication &gt; Users</strong>.<br>
                    2. No <strong>SQL Editor</strong>, execute para conceder a role de administrador:<br>
                    <code style="display:block; margin: 0.45rem 0; padding: 0.55rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.7rem; color: var(--color-gold); overflow-x: auto; font-family: monospace;">INSERT INTO public.profiles (id, name, role) SELECT id, 'Administrador Art For Sale', 'admin' FROM auth.users WHERE email = 'filipefim93@gmail.com' ON CONFLICT (id) DO UPDATE SET role = 'admin';</code>
                </div>
            </details>

            <a href="../index.php" class="back-link">← Retornar à Galeria Pública</a>
        </div>
    </div>

    <!-- Scripts de Autenticação -->
    <script src="../assets/js/supabase-config.js"></script>
    <script src="../assets/js/supabase-client.js"></script>
    <script>
    const form = document.getElementById('adminLoginForm');
    const btnSubmit = document.getElementById('btnSubmit');
    const alertBox = document.getElementById('jsAlertBox');
    const phpAlert = document.getElementById('phpAlertBox');

    function showAlert(html) {
        if (phpAlert) phpAlert.style.display = 'none';
        alertBox.innerHTML = html;
        alertBox.style.display = 'block';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const senha = document.getElementById('senha').value;
        if (!email || !senha) return;

        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Conectando ao Supabase Auth...';
        alertBox.style.display = 'none';

        const cfg = window.ARTSALE_CONFIG || window.ARTSELL_CONFIG;
        if (!cfg || !cfg.supabaseUrl) {
            form.submit();
            return;
        }

        try {
            const cleanBase = cfg.supabaseUrl.replace(/\/+$/, '');
            
            // 1. Chamada direta ao endpoint de autenticação do Supabase
            const authResp = await fetch(`${cleanBase}/auth/v1/token?grant_type=password`, {
                method: 'POST',
                headers: {
                    'apikey': cfg.supabaseAnonKey,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: email, password: senha })
            });

            const authData = await authResp.json();

            if (!authResp.ok) {
                let msg = authData.error_description || authData.msg || authData.message || 'Credenciais inválidas.';
                if (msg.includes('Invalid login credentials')) {
                    msg = 'E-mail ou senha incorretos no Supabase Auth. Verifique se digitou a senha correta cadastrada no Supabase.';
                } else if (msg.includes('Email not confirmed')) {
                    msg = 'O e-mail ainda não foi confirmado no Supabase. Acesse o painel do Supabase > Authentication > Users e confirme o usuário.';
                }
                showAlert(msg);
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Entrar com Supabase Auth';
                return;
            }

            const token = authData.access_token;
            const user = authData.user || {};
            const userId = user.id;

            btnSubmit.textContent = 'Verificando perfil de administrador...';

            // 2. Consulta à tabela public.profiles para verificar role = 'admin'
            const profResp = await fetch(`${cleanBase}/rest/v1/profiles?id=eq.${encodeURIComponent(userId)}&select=id,name,role`, {
                headers: {
                    'apikey': cfg.supabaseAnonKey,
                    'Authorization': `Bearer ${token}`
                }
            });

            let profile = null;
            if (profResp.ok) {
                const profData = await profResp.json();
                if (Array.isArray(profData) && profData.length > 0) {
                    profile = profData[0];
                }
            }

            if (!profile || profile.role !== 'admin') {
                const userName = (user.user_metadata && user.user_metadata.name) || email.split('@')[0];
                const sqlSnippet = `INSERT INTO public.profiles (id, name, role) VALUES ('${userId}', '${userName}', 'admin') ON CONFLICT (id) DO UPDATE SET role = 'admin';`;
                showAlert(`
                    <strong>Acesso Bloqueado:</strong> O usuário <code>${email}</code> foi autenticado no Supabase Auth, mas ainda não possui a permissão de administrador (<code>profiles.role = 'admin'</code>).<br><br>
                    Para autorizar este usuário, execute no <strong>SQL Editor</strong> do Supabase:<br>
                    <code style="display:block;margin:0.5rem 0;padding:0.5rem;background:#000;border-radius:4px;font-size:0.7rem;color:#e5b980;word-break:break-all;">${sqlSnippet}</code>
                `);
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Entrar com Supabase Auth';
                return;
            }

            // 3. Usuário autenticado e com role = 'admin'! Sincroniza sessão no PHP
            btnSubmit.textContent = 'Iniciando sessão administrativa...';
            const syncFormData = new FormData();
            syncFormData.append('action', 'client_auth_sync');
            syncFormData.append('access_token', token);
            syncFormData.append('user_id', userId);
            syncFormData.append('email', user.email || email);
            syncFormData.append('name', profile.name || email.split('@')[0]);
            syncFormData.append('role', 'admin');

            const syncResp = await fetch('login.php', {
                method: 'POST',
                body: syncFormData
            });

            const syncData = await syncResp.json();
            if (syncData.sucesso) {
                try {
                    sessionStorage.setItem('artsale_admin_token', token);
                    sessionStorage.setItem('artsale_admin_user', JSON.stringify({ id: userId, email: email, role: 'admin', name: profile.name }));
                    sessionStorage.setItem('artsell_admin_token', token);
                    sessionStorage.setItem('artsell_admin_user', JSON.stringify({ id: userId, email: email, role: 'admin', name: profile.name }));
                } catch (_) {}
                window.location.href = syncData.redirect || 'index.php';
            } else {
                showAlert(syncData.erro || 'Falha ao sincronizar sessão.');
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Entrar com Supabase Auth';
            }

        } catch (err) {
            // Se o fetch falhar por qualquer motivo no cliente, envia o formulário tradicional para o PHP
            console.warn('Fallback para submit do formulário PHP:', err);
            form.submit();
        }
    });
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
