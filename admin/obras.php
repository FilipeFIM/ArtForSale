<?php
/**
 * ART FOR SALE - Catálogo de Obras (Painel Administrativo)
 * Arquivo: /admin/obras.php
 * Slogan: "Arte que transforma espaços."
 * 
 * REGRAS IMPLEMENTADAS:
 * - Proteção server-side (Supabase Auth, profiles.role = 'admin').
 * - Listagem completa de obras com busca em tempo real e filtros:
 *     Todas, Disponíveis, Reservadas, Vendidas, Indisponíveis, Destaques e Arquivadas.
 * - Ações imediatas:
 *     - Alterar disponibilidade (Disponível, Reservada, Vendida, Indisponível)
 *     - Ativar/Desativar publicação no site
 *     - Destacar na página inicial
 *     - Arquivar obra
 *     - Excluir obra
 * - Links diretos para:
 *     - /admin/obra-nova.php (Cadastrar nova obra)
 *     - /admin/obra-editar.php?id={id} (Editar dados e gerenciar imagens no Storage)
 *     - /pages/obra.php?id={id} (Visualização pública)
 * - Identidade visual editorial de luxo Art For Sale.
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
    $artworkId = trim($_POST['artwork_id'] ?? '');

    if (!empty($artworkId)) {
        // 1. Alterar Disponibilidade
        if ($action === 'update_availability') {
            $newStatus = trim($_POST['availability'] ?? 'available');
            $res = supabase_atualizar_status_obra($artworkId, $newStatus, $adminToken);
            if (empty($res['error'])) {
                header('Location: obras.php?msg=status_updated');
                exit;
            } else {
                $msgErro = 'Erro ao atualizar disponibilidade: ' . $res['error'];
            }
        }

        // 2. Alternar Destaque na Home
        elseif ($action === 'toggle_featured') {
            $featured = !empty($_POST['featured']);
            $res = supabase_alternar_destaque_obra($artworkId, $featured, $adminToken);
            if (empty($res['error'])) {
                header('Location: obras.php?msg=featured_updated');
                exit;
            } else {
                $msgErro = 'Erro ao atualizar destaque: ' . $res['error'];
            }
        }

        // 3. Ativar / Desativar Publicação
        elseif ($action === 'toggle_active') {
            $active = !empty($_POST['active']);
            $res = supabase_alternar_ativo_obra($artworkId, $active, $adminToken);
            if (empty($res['error'])) {
                header('Location: obras.php?msg=active_updated');
                exit;
            } else {
                $msgErro = 'Erro ao alterar estado: ' . $res['error'];
            }
        }

        // 4. Arquivar Obra (desativa e marca como indisponível)
        elseif ($action === 'archive_artwork') {
            $res1 = supabase_alternar_ativo_obra($artworkId, false, $adminToken);
            $res2 = supabase_atualizar_status_obra($artworkId, 'unavailable', $adminToken);
            if (empty($res1['error']) && empty($res2['error'])) {
                header('Location: obras.php?msg=archived');
                exit;
            } else {
                $msgErro = 'Erro ao arquivar obra.';
            }
        }

        // 5. Excluir Obra Permanentemente (Limpa Storage, registros vinculados e dados)
        elseif ($action === 'delete_artwork') {
            $res = supabase_excluir_obra($artworkId, true, $adminToken);
            if (function_exists('artsale_mark_artwork_deleted')) {
                artsale_mark_artwork_deleted($artworkId);
            }
            if (empty($res['error']) || stripos($res['error'] ?? '', '0 rows') !== false || stripos($res['error'] ?? '', 'not found') !== false) {
                header('Location: obras.php?msg=deleted');
                exit;
            } else {
                $msgErro = 'Erro ao excluir obra: ' . $res['error'];
            }
        }
    }
}

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $msgSucesso = 'Obra cadastrada com sucesso! Ela já está disponível no acervo.';
            break;
        case 'updated':
            $msgSucesso = 'Dados da obra e fotografias atualizados com sucesso!';
            break;
        case 'deleted':
            $msgSucesso = 'Obra excluída permanentemente do catálogo e acervo com sucesso!';
            break;
        case 'archived':
            $msgSucesso = 'Obra arquivada com sucesso! Ela foi desativada e marcada como Indisponível.';
            break;
        case 'status_updated':
            $msgSucesso = 'Disponibilidade da obra atualizada com sucesso!';
            break;
        case 'featured_updated':
            $msgSucesso = 'Destaque da obra na home atualizado com sucesso!';
            break;
        case 'active_updated':
            $msgSucesso = 'Status de publicação da obra atualizado com sucesso!';
            break;
    }
}

// Busca todas as obras (ativas e inativas para gestão)
$obras = [];
if (supabase_is_configured()) {
    $obras = supabase_buscar_obras(null, null, 250, false, $adminToken);
}
if (empty($obras)) {
    global $catalogoObras;
    $fallback = $catalogoObras ?? [];
    $deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
    $obras = array_values(array_filter($fallback, fn($item) => !in_array((string)$item['id'], $deletedIds, true)));
}

// Filtra estritamente por deletedIds para garantir que nenhuma obra excluída seja renderizada
$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
$obras = array_values(array_filter($obras, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

$totalCategorias = count(supabase_buscar_categorias(false, $adminToken));
$totalArtistas = count(supabase_buscar_artistas(false, $adminToken));

// Filtro selecionado na URL
$currentFilter = $_GET['filter'] ?? 'all';

// Contadores para os filtros
$counts = [
    'all'         => count($obras),
    'available'   => 0,
    'reserved'    => 0,
    'sold'        => 0,
    'unavailable' => 0,
    'featured'    => 0,
    'inactive'    => 0
];

foreach ($obras as $item) {
    $st = strtolower($item['disponibilidade'] ?? 'available');
    if ($st === 'available' || $st === 'disponível') $counts['available']++;
    elseif ($st === 'reserved' || $st === 'reservada') $counts['reserved']++;
    elseif ($st === 'sold' || $st === 'vendida') $counts['sold']++;
    elseif ($st === 'unavailable' || $st === 'indisponível') $counts['unavailable']++;

    if (!empty($item['destaque'])) $counts['featured']++;
    if (empty($item['ativo'])) $counts['inactive']++;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Obras — Painel Art For Sale</title>
    
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
            color: #ff9999;
            text-decoration: none;
            font-weight: 600;
            padding: 0.4rem 0.6rem;
            transition: color 0.2s;
        }

        .btn-logout-link:hover { color: #ffffff; text-decoration: underline; }

        /* Barra de Navegação */
        .admin-tabs-bar {
            background-color: var(--bg-tabs);
            border-bottom: 1px solid var(--border-color);
            padding: 0 2.25rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 1rem 1.25rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .tab-btn:hover { color: var(--text-main); }

        .tab-btn.active {
            color: var(--color-gold);
            border-bottom-color: var(--color-gold);
            background: rgba(179, 138, 84, 0.05);
        }

        .tab-count {
            background: var(--bg-card-alt);
            color: var(--text-muted);
            font-size: 0.6875rem;
            padding: 0.15rem 0.45rem;
            border-radius: 10px;
        }

        .tab-btn.active .tab-count {
            background: var(--color-gold);
            color: #ffffff;
        }

        /* Container Principal */
        .admin-content {
            max-width: 1440px;
            margin: 0 auto;
            padding: 2.5rem 2.25rem;
        }

        .page-header-row {
            display: flex;
            align-items: flex-start;
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
            margin-bottom: 0.35rem;
            letter-spacing: -0.01em;
            line-height: 1.15;
        }

        .section-subhead {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .btn-new-artwork {
            background-color: var(--color-gold);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            padding: 0.75rem 1.4rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(179, 138, 84, 0.25);
        }

        .btn-new-artwork:hover {
            background-color: var(--color-gold-hover);
            box-shadow: 0 6px 20px rgba(179, 138, 84, 0.35);
        }

        /* Alertas */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-success {
            background: rgba(46, 125, 50, 0.15);
            border: 1px solid #2e7d32;
            color: #81c784;
        }

        .alert-error {
            background: rgba(201, 59, 59, 0.15);
            border: 1px solid #c93b3b;
            color: #ff9999;
        }

        /* Barra de Filtros & Busca */
        .filter-controls-row {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .filter-chips-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-chip {
            background: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.4rem 0.85rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
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
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            text-align: left;
        }

        .data-table th {
            background-color: var(--bg-card-alt);
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .data-table td {
            padding: 0.95rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: rgba(255, 255, 255, 0.015);
        }

        .art-thumb-stage {
            width: 58px;
            height: 58px;
            border-radius: 4px;
            background: #080706;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
            position: relative;
        }

        .art-thumb-stage img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .badge-thumb-storage {
            position: absolute;
            bottom: 2px;
            right: 2px;
            background: rgba(179, 138, 84, 0.85);
            color: #ffffff;
            font-size: 0.6rem;
            padding: 1px 3px;
            border-radius: 2px;
            line-height: 1;
        }

        .art-title-cell strong {
            font-size: 0.9375rem;
            color: var(--text-main);
            display: block;
            margin-bottom: 0.2rem;
        }

        .art-code-badge {
            font-family: monospace;
            font-size: 0.6875rem;
            background: rgba(179, 138, 84, 0.12);
            color: var(--color-gold);
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
            border: 1px solid var(--border-gold);
        }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.22rem 0.65rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .status-available { background: rgba(46, 125, 50, 0.15); color: #81c784; border: 1px solid rgba(46, 125, 50, 0.35); }
        .status-reserved { background: rgba(255, 152, 0, 0.15); color: #ffb74d; border: 1px solid rgba(255, 152, 0, 0.35); }
        .status-sold { background: rgba(33, 150, 243, 0.15); color: #64b5f6; border: 1px solid rgba(33, 150, 243, 0.35); }
        .status-unavailable { background: rgba(158, 158, 158, 0.15); color: #bdbdbd; border: 1px solid rgba(158, 158, 158, 0.35); }

        .badge-active-live {
            color: #81c784;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .badge-inactive-muted {
            color: #9c968b;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .badge-featured-gold {
            background: rgba(179, 138, 84, 0.18);
            color: var(--color-gold);
            border: 1px solid var(--border-gold);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.2s;
        }

        .badge-featured-gold:hover {
            background: var(--color-gold);
            color: #ffffff;
        }

        .badge-unfeatured-muted {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .badge-unfeatured-muted:hover {
            color: var(--color-gold);
            border-color: var(--border-gold);
        }

        /* Seletor Inline de Status */
        .select-status-inline {
            background: #0b0a09;
            color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.35rem 0.5rem;
            font-family: inherit;
            font-size: 0.75rem;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .select-status-inline:focus {
            border-color: var(--color-gold);
        }

        /* Botões de Ação na Tabela */
        .actions-cell-group {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-action-edit {
            background: rgba(179, 138, 84, 0.12);
            color: var(--color-gold);
            border: 1px solid var(--border-gold);
            padding: 0.35rem 0.65rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-action-edit:hover {
            background: var(--color-gold);
            color: #ffffff;
        }

        .btn-action-icon {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.35rem 0.55rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-action-icon:hover {
            color: var(--text-main);
            border-color: var(--color-gold);
        }

        .btn-action-danger {
            color: #ff9999;
            border-color: rgba(201, 59, 59, 0.3);
        }

        .btn-action-danger:hover {
            background: var(--color-danger);
            color: #ffffff;
            border-color: var(--color-danger);
        }

        /* Preço Sob Consulta Notice */
        .notice-price-policy {
            margin-top: 2rem;
            padding: 1rem 1.25rem;
            background: rgba(179, 138, 84, 0.05);
            border: 1px solid var(--border-gold);
            border-radius: 6px;
            font-size: 0.8125rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .notice-price-policy strong {
            color: var(--color-gold);
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
            <span class="badge-role">Admin • Catálogo</span>
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
        <a href="obras.php" class="tab-btn active">
            <span>🖼️ Catálogo de Obras</span>
            <span class="tab-count"><?= count($obras) ?></span>
        </a>
        <a href="categorias.php" class="tab-btn">
            <span>📁 Categorias</span>
            <span class="tab-count"><?= $totalCategorias ?></span>
        </a>
        <a href="artistas.php" class="tab-btn">
            <span>🎨 Artistas</span>
            <span class="tab-count"><?= $totalArtistas ?></span>
        </a>
        <a href="obra-nova.php" class="tab-btn">
            <span>+ Cadastrar Obra</span>
        </a>
        <a href="index.php?tab=inquiries" class="tab-btn">
            <span>📩 Consultas</span>
        </a>
        <a href="index.php?tab=contacts" class="tab-btn">
            <span>💬 Contatos</span>
        </a>
    </nav>

    <main class="admin-content">

        <!-- Cabeçalho da Página -->
        <div class="page-header-row">
            <div>
                <h1 class="section-headline">Gestão do Catálogo de Obras</h1>
                <p class="section-subhead">
                    Controle de disponibilidade, destaques, fotografias no Supabase Storage e publicação em tempo real.
                </p>
            </div>
            <a href="obra-nova.php" class="btn-new-artwork">
                + Cadastrar Nova Obra
            </a>
        </div>

        <?php if (!empty($msgSucesso)): ?>
            <div class="alert alert-success">
                <span>✓ <?= htmlspecialchars($msgSucesso) ?></span>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;">✕</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($msgErro)): ?>
            <div class="alert alert-error">
                <span>✕ <?= htmlspecialchars($msgErro) ?></span>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;">✕</button>
            </div>
        <?php endif; ?>

        <!-- Controles de Filtro e Busca -->
        <div class="filter-controls-row">
            <div class="filter-chips-group">
                <a href="obras.php?filter=all" class="filter-chip <?= $currentFilter === 'all' ? 'active' : '' ?>">
                    <span>Todas</span>
                    <span class="chip-count"><?= $counts['all'] ?></span>
                </a>
                <a href="obras.php?filter=available" class="filter-chip <?= $currentFilter === 'available' ? 'active' : '' ?>">
                    <span>Disponíveis</span>
                    <span class="chip-count"><?= $counts['available'] ?></span>
                </a>
                <a href="obras.php?filter=reserved" class="filter-chip <?= $currentFilter === 'reserved' ? 'active' : '' ?>">
                    <span>Reservadas</span>
                    <span class="chip-count"><?= $counts['reserved'] ?></span>
                </a>
                <a href="obras.php?filter=sold" class="filter-chip <?= $currentFilter === 'sold' ? 'active' : '' ?>">
                    <span>Vendidas</span>
                    <span class="chip-count"><?= $counts['sold'] ?></span>
                </a>
                <a href="obras.php?filter=unavailable" class="filter-chip <?= $currentFilter === 'unavailable' ? 'active' : '' ?>">
                    <span>Indisponíveis</span>
                    <span class="chip-count"><?= $counts['unavailable'] ?></span>
                </a>
                <a href="obras.php?filter=featured" class="filter-chip <?= $currentFilter === 'featured' ? 'active' : '' ?>">
                    <span>★ Destaques</span>
                    <span class="chip-count"><?= $counts['featured'] ?></span>
                </a>
                <a href="obras.php?filter=inactive" class="filter-chip <?= $currentFilter === 'inactive' ? 'active' : '' ?>">
                    <span>📁 Inativas / Arquivadas</span>
                    <span class="chip-count"><?= $counts['inactive'] ?></span>
                </a>
            </div>

            <div class="search-input-box">
                <span class="search-icon-pos">🔍</span>
                <input 
                    type="text" 
                    id="searchArtworksTable" 
                    class="search-input" 
                    placeholder="Filtrar por título, artista, código..."
                >
            </div>
        </div>

        <!-- Tabela de Obras -->
        <div class="table-container">
            <table class="data-table" id="artworksTable">
                <thead>
                    <tr>
                        <th style="width: 70px;">Foto</th>
                        <th>Obra & Código</th>
                        <th>Artista & Categoria</th>
                        <th>Técnica & Dimensões</th>
                        <th>Disponibilidade</th>
                        <th>Destaque</th>
                        <th>Publicação</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $displayedCount = 0;
                    foreach ($obras as $item): 
                        $rawStatus = strtolower($item['disponibilidade'] ?? 'available');
                        $isFeatured = !empty($item['destaque']);
                        $isActive = !empty($item['ativo']);

                        // Aplica filtro da URL
                        if ($currentFilter === 'available' && $rawStatus !== 'available' && $rawStatus !== 'disponível') continue;
                        if ($currentFilter === 'reserved' && $rawStatus !== 'reserved' && $rawStatus !== 'reservada') continue;
                        if ($currentFilter === 'sold' && $rawStatus !== 'sold' && $rawStatus !== 'vendida') continue;
                        if ($currentFilter === 'unavailable' && $rawStatus !== 'unavailable' && $rawStatus !== 'indisponível') continue;
                        if ($currentFilter === 'featured' && !$isFeatured) continue;
                        if ($currentFilter === 'inactive' && $isActive) continue;

                        $displayedCount++;
                        $statusClass = in_array($rawStatus, ['available', 'reserved', 'sold', 'unavailable']) ? $rawStatus : 'available';
                        $statusLabel = $rawStatus === 'available' ? 'Disponível' : ($rawStatus === 'reserved' ? 'Reservada' : ($rawStatus === 'sold' ? 'Vendida' : 'Indisponível'));
                    ?>
                        <tr 
                            class="artwork-row"
                            data-search="<?= htmlspecialchars(strtolower(($item['titulo'] ?? '') . ' ' . ($item['artista'] ?? '') . ' ' . ($item['codigo'] ?? '') . ' ' . ($item['categoria'] ?? '') . ' ' . ($item['tecnica'] ?? ''))) ?>"
                        >
                            <td>
                                <?php 
                                $thumbUrl = artsale_get_thumbnail_url($item['imagem'] ?? '', 120, 120, 80, '../');
                                $isStoragePhoto = !empty($item['imagem_storage_path']) || str_starts_with($item['imagem'] ?? '', 'http');
                                ?>
                                <a href="obra-editar.php?id=<?= $item['id'] ?>" class="art-thumb-stage" title="Gerenciar fotos da obra: <?= htmlspecialchars($item['titulo']) ?>">
                                    <img 
                                        src="<?= htmlspecialchars($thumbUrl) ?>" 
                                        alt="<?= htmlspecialchars($item['titulo']) ?>"
                                        width="58"
                                        height="58"
                                        loading="lazy"
                                        decoding="async"
                                        onerror="if(!this.src.endsWith('.svg')) this.src='../assets/images/obras/placeholder-obra.svg';"
                                    >
                                    <?php if ($isStoragePhoto): ?>
                                        <span class="badge-thumb-storage" title="Fotografia salva no Supabase Storage">☁</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            
                            <td class="art-title-cell">
                                <strong><?= htmlspecialchars($item['titulo']) ?></strong>
                                <span class="art-code-badge"><?= htmlspecialchars($item['codigo'] ?? 'ASF-0000') ?></span>
                                <span style="color: var(--text-muted); font-size: 0.72rem; margin-left: 0.35rem;">
                                    slug: <code><?= htmlspecialchars($item['slug'] ?? '') ?></code>
                                </span>
                            </td>

                            <td>
                                <strong style="color: var(--text-main);"><?= htmlspecialchars($item['artista'] ?? 'Artista Convidado') ?></strong><br>
                                <span style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($item['categoria'] ?? 'Pinturas') ?></span>
                            </td>

                            <td style="color: var(--text-muted); line-height: 1.4;">
                                <?= htmlspecialchars($item['tecnica'] ?? 'Óleo sobre tela') ?><br>
                                <span style="font-size: 0.75rem; color: #d4cfc5;"><?= htmlspecialchars($item['dimensoes'] ?? '80 × 120 cm') ?> (<?= htmlspecialchars($item['ano'] ?? '2024') ?>)</span>
                            </td>

                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_availability">
                                    <input type="hidden" name="artwork_id" value="<?= $item['id'] ?>">
                                    <select name="availability" class="select-status-inline" onchange="this.form.submit()" title="Alterar disponibilidade">
                                        <option value="available" <?= ($rawStatus === 'available' || $rawStatus === 'disponível') ? 'selected' : '' ?>>Disponível</option>
                                        <option value="reserved" <?= ($rawStatus === 'reserved' || $rawStatus === 'reservada') ? 'selected' : '' ?>>Reservada</option>
                                        <option value="sold" <?= ($rawStatus === 'sold' || $rawStatus === 'vendida') ? 'selected' : '' ?>>Vendida</option>
                                        <option value="unavailable" <?= ($rawStatus === 'unavailable' || $rawStatus === 'indisponível') ? 'selected' : '' ?>>Indisponível</option>
                                    </select>
                                </form>
                            </td>

                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="artwork_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="featured" value="<?= $isFeatured ? '0' : '1' ?>">
                                    <button type="submit" class="<?= $isFeatured ? 'badge-featured-gold' : 'badge-unfeatured-muted' ?>" title="Clique para alternar destaque na home">
                                        <?= $isFeatured ? '★ Destaque' : '☆ Comum' ?>
                                    </button>
                                </form>
                            </td>

                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="artwork_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                    <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;" title="Clique para ativar/desativar publicação no site">
                                        <?php if ($isActive): ?>
                                             <span class="badge-active-live">● Ativo no Site</span>
                                        <?php else: ?>
                                            <span class="badge-inactive-muted">○ Oculto / Inativo</span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </td>

                            <td style="text-align: right;">
                                <div class="actions-cell-group" style="justify-content: flex-end;">
                                    <a href="obra-editar.php?id=<?= $item['id'] ?>" class="btn-action-edit" title="Editar dados e gerenciar imagens no Storage">
                                        ✎ Editar & Fotos
                                    </a>

                                    <a href="../pages/obra.php?id=<?= $item['id'] ?>" target="_blank" class="btn-action-icon" title="Ver no site público">
                                        ↗
                                    </a>

                                    <form method="POST" onsubmit="return confirm('Deseja arquivar esta obra? Ela será desativada do site.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="archive_artwork">
                                        <input type="hidden" name="artwork_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn-action-icon" title="Arquivar obra">📁</button>
                                    </form>

                                    <form method="POST" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir esta obra do catálogo?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_artwork">
                                        <input type="hidden" name="artwork_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn-action-icon btn-action-danger" title="Excluir">✕</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($displayedCount === 0): ?>
                        <tr>
                            <td colspan="8" style="padding: 3.5rem; text-align: center; color: var(--text-muted);">
                                Nenhuma obra encontrada para o filtro selecionado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Política de Preço Sob Consulta -->
        <div class="notice-price-policy">
            <span style="font-size: 1.25rem;">💎</span>
            <div>
                <strong>Política de Precificação Editorial da Art For Sale:</strong><br>
                Em consonância com as normas de galerias de alto padrão, as obras cadastradas nunca expõem valores numéricos.
                No frontend e nas páginas públicas, o valor é apresentado invariavelmente como <em>"Preço sob consulta"</em>, incentivando o contato direto do colecionador com a curadoria.
            </div>
        </div>

    </main>

    <script>
    // Filtro de Busca em Tempo Real na Tabela
    const searchInput = document.getElementById('searchArtworksTable');
    const tableRows = document.querySelectorAll('.artwork-row');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            tableRows.forEach(row => {
                const searchStr = row.getAttribute('data-search') || '';
                if (!query || searchStr.includes(query)) {
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
