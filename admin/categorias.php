<?php
/**
 * ART FOR SALE - Gerenciamento de Categorias (Painel Administrativo)
 * Arquivo: /admin/categorias.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Permissões e Ações:
 * - Listar categorias cadastradas (Supabase + Persistência Local)
 * - Cadastrar nova categoria (link para categoria-nova.php)
 * - Editar categoria existente (link para categoria-editar.php)
 * - Ativar / Desativar com 1 clique direto
 * - Exibir contagem de obras vinculadas
 * - Prévia da imagem da categoria
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
    $categoryId = trim($_POST['category_id'] ?? '');

    if (!empty($categoryId)) {
        if ($action === 'toggle_active') {
            $active = !empty($_POST['active']);
            $res = supabase_alternar_ativo_categoria($categoryId, $active, $adminToken);
            if (empty($res['error'])) {
                header('Location: categorias.php?msg=status_updated');
                exit;
            } else {
                $msgErro = 'Erro ao alterar status da categoria: ' . $res['error'];
            }
        }
    }
}

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $msgSucesso = 'Categoria cadastrada com sucesso!';
            break;
        case 'updated':
            $msgSucesso = 'Categoria atualizada com sucesso!';
            break;
        case 'status_updated':
            $msgSucesso = 'Status da categoria atualizado com sucesso!';
            break;
    }
}

// Carrega categorias, obras e artistas para calcular estatísticas
$categorias = supabase_buscar_categorias(false, $adminToken);
$obras = supabase_buscar_obras(null, null, 100, false, $adminToken);
$artistas = supabase_buscar_artistas(false, $adminToken);

// Contagem de obras por categoria (slug ou id)
$obrasPorCategoria = [];
foreach ($obras as $o) {
    $catSlug = $o['categoria_slug'] ?? '';
    $catId = $o['categoria_id'] ?? '';
    if ($catSlug) {
        $obrasPorCategoria[$catSlug] = ($obrasPorCategoria[$catSlug] ?? 0) + 1;
    }
    if ($catId) {
        $obrasPorCategoria[$catId] = ($obrasPorCategoria[$catId] ?? 0) + 1;
    }
}

$currentFilter = $_GET['filter'] ?? 'all';
$counts = [
    'all'      => count($categorias),
    'active'   => count(array_filter($categorias, fn($c) => !isset($c['active']) || $c['active'] !== false)),
    'inactive' => count(array_filter($categorias, fn($c) => isset($c['active']) && $c['active'] === false))
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias — Painel Art For Sale</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/site/logo-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin.js"></script>

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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        /* Topbar */
        .admin-topbar {
            background-color: var(--bg-topbar);
            border-bottom: 1px solid var(--border-color);
            padding: 0.9rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 14px rgba(28, 27, 24, 0.04);
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .badge-role {
            background: rgba(179, 138, 84, 0.15);
            border: 1px solid var(--border-gold);
            color: var(--color-gold);
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-email-label {
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .user-email-label strong {
            color: var(--text-main);
        }

        .btn-link-site {
            color: var(--color-gold);
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .btn-link-site:hover {
            color: var(--color-gold-hover);
        }

        .btn-logout-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8125rem;
            transition: color 0.2s;
        }

        .btn-logout-link:hover {
            color: #ffffff;
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
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.75rem 1.4rem;
            border-radius: 4px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(179, 138, 84, 0.25);
        }

        .btn-create-new:hover {
            background-color: var(--color-gold-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(179, 138, 84, 0.35);
        }

        /* Alertas */
        .alert-success {
            background: rgba(46, 125, 50, 0.15);
            border: 1px solid var(--color-success);
            color: #81c784;
            padding: 0.85rem 1.25rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.75rem;
        }

        .alert-error {
            background: rgba(201, 59, 59, 0.15);
            border: 1px solid var(--color-danger);
            color: #ff9999;
            padding: 0.85rem 1.25rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.75rem;
        }

        /* Filtros e Barra de Busca */
        .filters-search-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-chips-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            background: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
        }

        .filter-chip:hover {
            border-color: var(--border-gold);
            color: var(--text-main);
        }

        .filter-chip.active {
            background: var(--color-gold);
            color: #ffffff;
            border-color: var(--color-gold);
        }

        .chip-count {
            background: rgba(0, 0, 0, 0.25);
            padding: 0.1rem 0.4rem;
            border-radius: 8px;
            font-size: 0.6875rem;
        }

        .search-input-box {
            position: relative;
            min-width: 280px;
        }

        .search-input {
            width: 100%;
            background-color: #0b0a09;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.55rem 0.85rem;
            padding-left: 2rem;
            color: #ffffff;
            font-family: inherit;
            font-size: 0.8125rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus { border-color: var(--color-gold); }

        .search-icon-pos {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8125rem;
        }

        /* Tabela Principal */
        .table-container {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.8125rem;
        }

        .data-table th {
            background-color: #12110e;
            padding: 0.95rem 1.15rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid #1e1c18;
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: #1c1a16;
        }

        .cat-thumb-box {
            width: 70px;
            height: 52px;
            border-radius: 4px;
            overflow: hidden;
            background: #000;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cat-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .btn-action-edit {
            background: rgba(179, 138, 84, 0.12);
            color: var(--color-gold);
            border: 1px solid var(--border-gold);
            padding: 0.4rem 0.8rem;
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

        .btn-action-site {
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

        .btn-action-site:hover {
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
            <span class="badge-role">Admin • Categorias</span>
        </div>

        <nav class="user-nav">
            <span class="user-email-label">
                Curador: <strong><?= htmlspecialchars($user['email']) ?></strong>
            </span>
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleAdminTheme()" title="Alternar tema visual (Claro / Escuro)">
                <span id="themeToggleIcon">🌙</span>
                <span id="themeToggleLabel">Escuro</span>
            </button>
            <a href="../pages/categorias.php" target="_blank" class="btn-link-site">Ver Categorias no Site ↗</a>
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
        <a href="categorias.php" class="tab-btn active">
            <span>📁 Categorias</span>
            <span class="tab-count"><?= count($categorias) ?></span>
        </a>
        <a href="artistas.php" class="tab-btn">
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
                <h1 class="section-headline">Gerenciamento de Categorias</h1>
                <p class="section-subhead">
                    Cadastre, edite, ative e adicione imagens às categorias e estilos artísticos do acervo.
                </p>
            </div>
            <a href="categoria-nova.php" class="btn-create-new">
                + Nova Categoria
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

        <!-- Filtros e Busca -->
        <div class="filters-search-bar">
            <div class="filter-chips-row">
                <a href="categorias.php?filter=all" class="filter-chip <?= $currentFilter === 'all' ? 'active' : '' ?>">
                    <span>Todas</span>
                    <span class="chip-count"><?= $counts['all'] ?></span>
                </a>
                <a href="categorias.php?filter=active" class="filter-chip <?= $currentFilter === 'active' ? 'active' : '' ?>">
                    <span>● Ativas</span>
                    <span class="chip-count"><?= $counts['active'] ?></span>
                </a>
                <a href="categorias.php?filter=inactive" class="filter-chip <?= $currentFilter === 'inactive' ? 'active' : '' ?>">
                    <span>○ Inativas</span>
                    <span class="chip-count"><?= $counts['inactive'] ?></span>
                </a>
            </div>

            <div class="search-input-box">
                <span class="search-icon-pos">🔍</span>
                <input 
                    type="text" 
                    id="searchCategoriesTable" 
                    class="search-input" 
                    placeholder="Filtrar por nome ou slug..."
                >
            </div>
        </div>

        <!-- Tabela de Categorias -->
        <div class="table-container">
            <table class="data-table" id="categoriesTable">
                <thead>
                    <tr>
                        <th style="width: 85px;">Imagem</th>
                        <th>Nome & Slug</th>
                        <th>Descrição</th>
                        <th style="width: 120px;">Obras</th>
                        <th style="width: 140px;">Publicação</th>
                        <th style="text-align: right; width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $displayed = 0;
                    foreach ($categorias as $cat): 
                        $isActive = !isset($cat['active']) || $cat['active'] !== false;
                        if ($currentFilter === 'active' && !$isActive) continue;
                        if ($currentFilter === 'inactive' && $isActive) continue;
                        $displayed++;

                        $slug = $cat['slug'] ?? '';
                        $catId = $cat['id'] ?? '';
                        $totalObras = $obrasPorCategoria[$slug] ?? ($obrasPorCategoria[$catId] ?? 0);
                        $imgUrl = artsale_resolve_image_url($cat['image_url'] ?? '', '../');
                    ?>
                        <tr class="category-row" data-search="<?= htmlspecialchars(strtolower(($cat['name'] ?? '') . ' ' . $slug . ' ' . ($cat['description'] ?? ''))) ?>">
                            <td>
                                <div class="cat-thumb-box">
                                    <img 
                                        src="<?= htmlspecialchars($imgUrl) ?>" 
                                        alt="<?= htmlspecialchars($cat['name'] ?? '') ?>"
                                        onerror="this.src='../assets/images/obras/placeholder-obra.svg';"
                                    >
                                </div>
                            </td>

                            <td>
                                <strong style="font-size: 0.9375rem; color: var(--text-main);"><?= htmlspecialchars($cat['name'] ?? '') ?></strong><br>
                                <span style="color: var(--text-muted); font-size: 0.72rem;">slug: <code><?= htmlspecialchars($slug) ?></code></span>
                            </td>

                            <td style="color: var(--text-muted); max-width: 380px; line-height: 1.45;">
                                <?php 
                                    $catDesc = $cat['description'] ?? 'Sem descrição informada.';
                                    if (function_exists('mb_strimwidth')) {
                                        echo htmlspecialchars(mb_strimwidth($catDesc, 0, 100, '...'));
                                    } else {
                                        echo htmlspecialchars(strlen($catDesc) > 100 ? substr($catDesc, 0, 97) . '...' : $catDesc);
                                    }
                                ?>
                            </td>

                            <td>
                                <span style="background: rgba(179, 138, 84, 0.12); color: var(--color-gold); padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                                    <?= $totalObras ?> <?= $totalObras === 1 ? 'obra' : 'obras' ?>
                                </span>
                            </td>

                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="category_id" value="<?= htmlspecialchars($catId) ?>">
                                    <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                    <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;" title="Clique para alternar ativação no site">
                                        <?php if ($isActive): ?>
                                            <span class="badge-active-live">● Ativa no Site</span>
                                        <?php else: ?>
                                            <span class="badge-inactive-muted">○ Oculta / Inativa</span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </td>

                            <td style="text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="categoria-editar.php?id=<?= urlencode((string)$catId) ?>" class="btn-action-edit" title="Editar dados e imagem">
                                        ✎ Editar
                                    </a>
                                    <a href="../pages/categorias.php?slug=<?= urlencode($slug) ?>" target="_blank" class="btn-action-site" title="Ver no site público">
                                        ↗
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($displayed === 0): ?>
                        <tr>
                            <td colspan="6" class="empty-state-card">
                                Nenhuma categoria encontrada para o filtro selecionado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        // Filtro em tempo real na tabela de categorias
        const searchInput = document.getElementById('searchCategoriesTable');
        const rows = document.querySelectorAll('.category-row');

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                rows.forEach(r => {
                    const text = r.dataset.search || '';
                    r.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

    </script>
</body>
</html>
