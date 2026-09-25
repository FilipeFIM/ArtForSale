<?php
/**
 * ART FOR SALE - Gerenciamento de Artistas (Painel Administrativo)
 * Arquivo: /admin/artistas.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Permissões e Ações:
 * - Listar artistas cadastrados (Supabase + Persistência Local)
 * - Cadastrar novo artista (link para artista-novo.php)
 * - Editar artista existente (link para artista-editar.php)
 * - Ativar / Desativar com 1 clique direto
 * - Exibir contagem de obras vinculadas
 * - Prévia da fotografia do artista
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$msgSucesso = '';
$msgErro = '';

// Processamento de Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $action = $_POST['action'] ?? '';
    $artistId = trim($_POST['artist_id'] ?? '');

    if (!empty($artistId)) {
        if ($action === 'toggle_active') {
            $active = !empty($_POST['active']);
            $res = supabase_alternar_ativo_artista($artistId, $active, $adminToken);
            if (empty($res['error'])) {
                header('Location: artistas.php?msg=status_updated');
                exit;
            } else {
                $msgErro = 'Erro ao alterar status do artista: ' . $res['error'];
            }
        }
    }
}

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $msgSucesso = 'Artista cadastrado com sucesso!';
            break;
        case 'updated':
            $msgSucesso = 'Artista atualizado com sucesso!';
            break;
        case 'status_updated':
            $msgSucesso = 'Status do artista atualizado com sucesso!';
            break;
        case 'not_found':
            $msgErro = 'Artista não encontrado.';
            break;
    }
}

// Carrega artistas e obras para calcular estatísticas
$artistas = supabase_buscar_artistas(false, $adminToken);
$obras = supabase_buscar_obras(null, null, 100, false, $adminToken);
$categorias = supabase_buscar_categorias(false, $adminToken);

// Contagem de obras por artista (slug, id ou nome)
$obrasPorArtista = [];
foreach ($obras as $o) {
    $artSlug = $o['artista_slug'] ?? '';
    $artId = $o['artista_id'] ?? '';
    $artNome = strtolower(trim($o['artista'] ?? ''));

    if ($artSlug) {
        $obrasPorArtista[$artSlug] = ($obrasPorArtista[$artSlug] ?? 0) + 1;
    }
    if ($artId) {
        $obrasPorArtista[$artId] = ($obrasPorArtista[$artId] ?? 0) + 1;
    }
    if ($artNome) {
        $obrasPorArtista[$artNome] = ($obrasPorArtista[$artNome] ?? 0) + 1;
    }
}

$currentFilter = $_GET['filter'] ?? 'all';
$counts = [
    'all'      => count($artistas),
    'active'   => count(array_filter($artistas, fn($a) => !isset($a['active']) || $a['active'] !== false)),
    'inactive' => count(array_filter($artistas, fn($a) => isset($a['active']) && $a['active'] === false))
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artistas — Painel Art For Sale</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/site/logo-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <script>
        (function() {
            const saved = localStorage.getItem('artforsale_admin_theme') || 'light';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>

    <style>
        :root {
            --bg-dark: #faf8f5;
            --bg-topbar: #ffffff;
            --bg-tabs: #ffffff;
            --bg-card: #ffffff;
            --bg-card-alt: #fbf9f5;
            --bg-card-hover: #f7f3eb;
            --color-gold: #b38a54;
            --color-gold-hover: #9c7543;
            --color-gold-subtle: #f8f3eb;
            --text-main: #1c1b18;
            --text-muted: #7e796e;
            --border-color: #e8e3d8;
            --border-gold: rgba(179, 138, 84, 0.40);
            --color-danger: #c93b3b;
            --color-success: #2e7d32;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        /* Topbar Editorial */
        .admin-topbar {
            background-color: var(--bg-topbar);
            border-bottom: 1px solid var(--border-color);
            padding: 0.9rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 14px rgba(28, 27, 24, 0.04);
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .badge-role {
            background: var(--color-gold-subtle);
            color: var(--color-gold);
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            border: 1px solid var(--border-gold);
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-size: 0.8125rem;
        }

        .user-email-label { color: var(--text-muted); }
        .user-email-label strong { color: var(--text-main); }

        .btn-link-site {
            color: var(--color-gold-light, #dfc294);
            text-decoration: none;
            border: 1px solid var(--border-gold);
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
            background: rgba(179, 138, 84, 0.08);
        }

        .btn-link-site:hover {
            background-color: var(--color-gold);
            color: #ffffff;
            border-color: var(--color-gold);
            box-shadow: 0 4px 15px rgba(179, 138, 84, 0.3);
        }

        .btn-logout-link {
            color: #ffb4b4;
            text-decoration: none;
            font-weight: 600;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-logout-link:hover {
            color: #ffffff;
            background-color: rgba(201, 59, 59, 0.25);
            text-decoration: none;
        }



        /* Barra de Abas */
        .admin-tabs-bar {
            background-color: var(--bg-tabs);
            border-bottom: 1px solid var(--border-color);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            overflow-x: auto;
        }

        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.95rem 1.25rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: var(--text-main);
            border-bottom-color: rgba(179, 138, 84, 0.4);
        }

        .tab-btn.active {
            color: var(--color-gold);
            border-bottom-color: var(--color-gold);
            background: rgba(179, 138, 84, 0.04);
        }

        .tab-count {
            background: var(--bg-card-alt);
            color: var(--text-secondary);
            font-size: 0.6875rem;
            padding: 0.15rem 0.45rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        /* Conteúdo Principal */
        .admin-content {
            padding: 2.25rem 2rem;
            flex-grow: 1;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .section-headline {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 2.4rem;
            font-weight: 500;
            color: var(--text-main);
            letter-spacing: -0.01em;
            line-height: 1.15;
            margin-bottom: 0.35rem;
        }

        .section-subhead {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .btn-create-new {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--color-gold);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.75rem 1.4rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(179, 138, 84, 0.25);
        }

        .btn-create-new:hover {
            background-color: var(--color-gold-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(179, 138, 84, 0.35);
        }

        /* Alertas */
        .alert-success {
            background: rgba(46, 125, 50, 0.15);
            border: 1px solid rgba(46, 125, 50, 0.4);
            color: #81c784;
            padding: 0.9rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.75rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: rgba(201, 59, 59, 0.15);
            border: 1px solid rgba(201, 59, 59, 0.4);
            color: #ff9999;
            padding: 0.9rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.75rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Barra de Filtros e Busca */
        .toolbar-panel {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.15rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
        }

        .filters-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-chip {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 0.45rem 0.9rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .filter-chip:hover {
            color: var(--text-main);
            border-color: var(--border-gold);
        }

        .filter-chip.active {
            background: var(--color-gold);
            color: #ffffff;
            border-color: var(--color-gold);
        }

        .filter-chip .chip-count {
            background: rgba(0, 0, 0, 0.25);
            padding: 0.1rem 0.4rem;
            border-radius: 8px;
            font-size: 0.6875rem;
        }

        .search-box-wrap {
            position: relative;
            min-width: 300px;
        }

        .search-input {
            width: 100%;
            background-color: #0b0a09;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.55rem 0.85rem;
            padding-left: 2.1rem;
            color: #ffffff;
            font-family: inherit;
            font-size: 0.8125rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus { border-color: var(--color-gold); }

        .search-icon-pos {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Tabela de Artistas */
        .table-container {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            text-align: left;
        }

        .data-table th {
            background-color: #12110f;
            padding: 0.9rem 1.25rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.6875rem;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(39, 36, 31, 0.6);
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: #1c1a16;
        }

        .artist-avatar-box {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            overflow: hidden;
            background: #000;
            border: 1px solid var(--border-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .artist-avatar-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .artist-avatar-fallback {
            font-size: 1.4rem;
            color: var(--color-gold);
        }

        .artist-meta-cell {
            display: flex;
            align-items: center;
            gap: 1.15rem;
        }

        .artist-name-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.2rem;
        }

        .artist-slug-tag {
            font-family: monospace;
            font-size: 0.6875rem;
            color: var(--color-gold);
            background: rgba(179, 138, 84, 0.1);
            padding: 0.15rem 0.45rem;
            border-radius: 3px;
            border: 1px solid rgba(179, 138, 84, 0.25);
            display: inline-block;
        }

        .bio-excerpt {
            color: var(--text-muted);
            font-size: 0.775rem;
            line-height: 1.45;
            max-width: 440px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .badge-active-live {
            background: rgba(46, 125, 50, 0.15);
            color: #81c784;
            border: 1px solid rgba(46, 125, 50, 0.35);
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }

        .badge-active-live:hover {
            background: rgba(46, 125, 50, 0.3);
        }

        .badge-inactive-muted {
            background: rgba(201, 59, 59, 0.15);
            color: #ff9999;
            border: 1px solid rgba(201, 59, 59, 0.35);
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }

        .badge-inactive-muted:hover {
            background: rgba(201, 59, 59, 0.3);
        }

        .art-count-pill {
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--bg-card-alt);
            color: var(--text-main);
            padding: 0.2rem 0.55rem;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            display: inline-block;
        }

        .actions-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-action-edit {
            background: rgba(179, 138, 84, 0.12);
            color: var(--color-gold);
            border: 1px solid var(--border-gold);
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-action-edit:hover {
            background: var(--color-gold);
            color: #ffffff;
        }

        .btn-action-view {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 0.4rem 0.7rem;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-action-view:hover {
            color: var(--text-main);
            border-color: var(--border-gold);
        }

        .empty-state-card {
            padding: 3.5rem 2rem;
            text-align: center;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <!-- Topbar Editorial -->
    <header class="admin-topbar">
        <div class="brand-box">
            <a href="index.php">
                <img src="../assets/images/site/logo.svg" alt="Art For Sale" class="admin-logo-light" width="160" height="36">
                <img src="../assets/images/site/logo-white.svg" alt="Art For Sale" class="admin-logo-dark" width="160" height="36">
            </a>
            <span class="badge-role">Admin • Artistas</span>
        </div>

        <nav class="user-nav">
            <span class="user-email-label">
                Curador: <strong><?= htmlspecialchars($user['email']) ?></strong>
            </span>
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleAdminTheme()" title="Alternar tema visual (Claro / Escuro)">
                <span id="themeToggleIcon">🌙</span>
                <span id="themeToggleLabel">Escuro</span>
            </button>
            <a href="../index.php" target="_blank" class="btn-link-site">Galeria Pública ↗</a>
            <a href="logout.php" class="btn-logout-link">Sair</a>
        </nav>
    </header>

    <!-- Barra de Abas -->
    <nav class="admin-tabs-bar">
        <a href="index.php?tab=dashboard" class="tab-btn">
            <span>📊 Dashboard Geral</span>
        </a>
        <a href="obras.php" class="tab-btn">
            <span>🖼️ Catálogo de Obras</span>
            <span class="tab-count"><?= count($obras) ?></span>
        </a>
        <a href="categorias.php" class="tab-btn">
            <span>📁 Categorias</span>
            <span class="tab-count"><?= count($categorias) ?></span>
        </a>
        <a href="artistas.php" class="tab-btn active">
            <span>🎨 Artistas</span>
            <span class="tab-count"><?= count($artistas) ?></span>
        </a>
        <a href="index.php?tab=inquiries" class="tab-btn">
            <span>📩 Consultas</span>
        </a>
        <a href="index.php?tab=contacts" class="tab-btn">
            <span>💬 Contatos</span>
        </a>
    </nav>

    <main class="admin-content">

        <div class="page-header-row">
            <div>
                <h1 class="section-headline">Artistas</h1>
                <p class="section-subhead">
                    Gerenciamento dos artistas representados, biografias, fotografias e vinculação com obras do acervo.
                </p>
            </div>
            <a href="artista-novo.php" class="btn-create-new">
                <span>+ Novo Artista</span>
            </a>
        </div>

        <?php if (!empty($msgSucesso)): ?>
            <div class="alert-success">
                ✓ <?= htmlspecialchars($msgSucesso) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($msgErro)): ?>
            <div class="alert-error">
                ✕ <?= htmlspecialchars($msgErro) ?>
            </div>
        <?php endif; ?>

        <!-- Toolbar com Filtros e Busca em Tempo Real -->
        <div class="toolbar-panel">
            <div class="filters-group">
                <a href="artistas.php?filter=all" class="filter-chip <?= ($currentFilter === 'all') ? 'active' : '' ?>">
                    Todos <span class="chip-count"><?= $counts['all'] ?></span>
                </a>
                <a href="artistas.php?filter=active" class="filter-chip <?= ($currentFilter === 'active') ? 'active' : '' ?>">
                    Ativos <span class="chip-count"><?= $counts['active'] ?></span>
                </a>
                <a href="artistas.php?filter=inactive" class="filter-chip <?= ($currentFilter === 'inactive') ? 'active' : '' ?>">
                    Inativos <span class="chip-count"><?= $counts['inactive'] ?></span>
                </a>
            </div>

            <div class="search-box-wrap">
                <span class="search-icon-pos">🔍</span>
                <input 
                    type="text" 
                    id="artistSearchInput" 
                    class="search-input" 
                    placeholder="Filtrar artista por nome, slug..."
                    autocomplete="off"
                >
            </div>
        </div>

        <!-- Tabela de Artistas -->
        <div class="table-container">
            <table class="data-table" id="artistsTable">
                <thead>
                    <tr>
                        <th style="width: 320px;">Artista</th>
                        <th>Biografia</th>
                        <th style="width: 140px; text-align: center;">Obras</th>
                        <th style="width: 120px; text-align: center;">Status</th>
                        <th style="width: 180px; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $exibidos = 0;
                    foreach ($artistas as $art): 
                        $isActive = !isset($art['active']) || $art['active'] !== false;
                        if ($currentFilter === 'active' && !$isActive) continue;
                        if ($currentFilter === 'inactive' && $isActive) continue;
                        $exibidos++;

                        $artId = $art['id'] ?? '';
                        $artSlug = $art['slug'] ?? '';
                        $artName = $art['name'] ?? 'Artista Sem Nome';
                        $bio = $art['biography'] ?? '';
                        
                        $countObras = $obrasPorArtista[$artSlug] ?? ($obrasPorArtista[$artId] ?? ($obrasPorArtista[strtolower(trim($artName))] ?? 0));
                        
                        $imgUrl = !empty($art['image_url']) ? artsale_resolve_image_url($art['image_url'], '../') : '';
                    ?>
                    <tr class="artist-row" data-search="<?= strtolower(htmlspecialchars($artName . ' ' . $artSlug . ' ' . $bio)) ?>">
                        <td>
                            <div class="artist-meta-cell">
                                <div class="artist-avatar-box">
                                    <?php if (!empty($imgUrl)): ?>
                                        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($artName) ?>" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <span class="artist-avatar-fallback" style="display: none;">🎨</span>
                                    <?php else: ?>
                                        <span class="artist-avatar-fallback">🎨</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="artist-name-title"><?= htmlspecialchars($artName) ?></div>
                                    <span class="artist-slug-tag"><?= htmlspecialchars($artSlug) ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <?php if (!empty($bio)): ?>
                                <p class="bio-excerpt" title="<?= htmlspecialchars($bio) ?>">
                                    <?= htmlspecialchars($bio) ?>
                                </p>
                            <?php else: ?>
                                <span style="color: #635d55; font-style: italic;">Sem biografia informada</span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align: center;">
                            <span class="art-count-pill" title="<?= $countObras ?> obra(s) cadastrada(s)">
                                <?= $countObras ?> <?= $countObras === 1 ? 'obra' : 'obras' ?>
                            </span>
                        </td>

                        <td style="text-align: center;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="artist_id" value="<?= htmlspecialchars($artId) ?>">
                                <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                <?php if ($isActive): ?>
                                    <button type="submit" class="badge-active-live" title="Clique para desativar">
                                        ● Ativo
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="badge-inactive-muted" title="Clique para ativar">
                                        ○ Inativo
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>

                        <td style="text-align: right;">
                            <div class="actions-group" style="justify-content: flex-end;">
                                <a href="artista-editar.php?id=<?= urlencode($artId) ?>" class="btn-action-edit">
                                    ✎ Editar
                                </a>
                                <a href="obras.php?search=<?= urlencode($artName) ?>" class="btn-action-view" title="Ver obras deste artista">
                                    🖼️ Ver Obras
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if ($exibidos === 0): ?>
                    <tr>
                        <td colspan="5" class="empty-state-card">
                            <p style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 0.5rem;">Nenhum artista encontrado</p>
                            <p style="font-size: 0.8125rem;">Não há registros correspondentes ao filtro atual.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        // Filtro em tempo real por digitação
        const searchInput = document.getElementById('artistSearchInput');
        const rows = document.querySelectorAll('.artist-row');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                rows.forEach(row => {
                    const data = row.getAttribute('data-search') || '';
                    if (!term || data.includes(term)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
