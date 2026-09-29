<?php
/**
 * ART FOR SALE - Painel Administrativo & Dashboard da Curadoria
 * Arquivo: /admin/index.php
 * Slogan: "Arte que transforma espaços."
 * 
 * DIRETRIZES ATENDIDAS:
 * - Proteção server-side com Supabase Auth (profiles.role = 'admin').
 * - 8 Indicadores no Dashboard em tempo real com links e filtros diretos:
 *     1. Total de obras
 *     2. Disponíveis
 *     3. Reservadas
 *     4. Vendidas
 *     5. Categorias
 *     6. Artistas
 *     7. Consultas recebidas
 *     8. Contatos recebidos
 * - Gestão de Catálogo:
 *     - Alteração direta de status de disponibilidade (Disponível, Reservada, Vendida)
 *     - Ativação/Desativação de Destaque na Home
 *     - Edição de metadados da obra e cadastro de novas obras
 *     - Filtros em tempo real por status e busca por título/código/artista
 * - Supabase Storage (Bucket 'artworks'):
 *     - Pastas {artwork_id}/principal/ e {artwork_id}/gallery/
 *     - 7 tipos de vista com tags visuais exclusivas
 *     - Regra estrita de 1 única imagem principal por obra
 *     - Validação rigorosa (JPG, JPEG, PNG, WEBP até 10 MB)
 *     - 1-clique para copiar URL pública ou caminho no Storage com Toast
 *     - Lightbox em alta resolução (object-fit: contain sem distorção)
 * - Gestão de Consultas e Contatos:
 *     - Ação direta de atendimento via WhatsApp com mensagem personalizada
 *     - Atualização de status e exclusão com segurança
 * - Identidade visual editorial de luxo Art For Sale preservada.
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$msgSucesso = '';
$msgErro = '';
$currentTab = $_GET['tab'] ?? 'dashboard';

// Processamento de Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $action = $_POST['action'] ?? '';

    // 1. Upload de Imagem para o Supabase Storage
    if ($action === 'upload_image') {
        $artworkId = trim($_POST['artwork_id'] ?? '');
        $imageType = trim($_POST['image_type'] ?? 'principal');
        $isPrimary = !empty($_POST['is_primary']);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $file = $_FILES['artwork_file'] ?? null;

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $msgErro = 'Por favor, selecione um arquivo de imagem válido.';
        } else {
            $val = supabase_validar_arquivo_imagem($file);
            if (!$val['valido']) {
                $msgErro = $val['erro'];
            } else {
                $res = supabase_upload_imagem_obra(
                    $file['tmp_name'],
                    $artworkId,
                    $file['name'],
                    $imageType,
                    $isPrimary,
                    $sortOrder,
                    $adminToken
                );

                if (!empty($res['sucesso'])) {
                    $msgSucesso = 'Imagem enviada, associada à obra e salva com sucesso!';
                } else {
                    $msgErro = 'Erro no envio para o Storage: ' . ($res['erro'] ?? 'Falha desconhecida.');
                }
            }
        }
        $currentTab = 'artworks';
    }

    // 2. Definir Imagem Principal Única
    elseif ($action === 'set_primary') {
        $artworkId = trim($_POST['artwork_id'] ?? '');
        $imageId = trim($_POST['image_id'] ?? '');

        if (!empty($imageId)) {
            $res = supabase_definir_imagem_principal($artworkId, $imageId, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Imagem definida como Principal com sucesso! As demais foram desmarcadas automaticamente.';
            } else {
                $msgErro = 'Erro ao definir imagem principal: ' . $res['error'];
            }
        }
        $currentTab = 'artworks';
    }

    // 3. Excluir Imagem do Storage e Banco
    elseif ($action === 'delete_image') {
        $artworkId = trim($_POST['artwork_id'] ?? '');
        $imageId = trim($_POST['image_id'] ?? '');
        $storagePath = trim($_POST['storage_path'] ?? '');

        if (!empty($imageId)) {
            $res = supabase_excluir_imagem_obra($imageId, $storagePath, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Imagem excluída com sucesso do Supabase Storage e do banco!';
            } else {
                $msgErro = 'Erro ao excluir imagem: ' . $res['error'];
            }
        }
        $currentTab = 'artworks';
    }

    // 4. Atualizar Status de Disponibilidade da Obra (Disponível, Reservada, Vendida)
    elseif ($action === 'update_artwork_status') {
        $artworkId = trim($_POST['artwork_id'] ?? '');
        $newStatus = trim($_POST['availability'] ?? 'available');

        if (!empty($artworkId)) {
            $res = supabase_atualizar_status_obra($artworkId, $newStatus, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Status da obra atualizado com sucesso!';
            } else {
                $msgErro = 'Erro ao atualizar status da obra: ' . $res['error'];
            }
        }
        $currentTab = 'artworks';
    }

    // 5. Alternar Destaque da Obra na Home
    elseif ($action === 'toggle_featured') {
        $artworkId = trim($_POST['artwork_id'] ?? '');
        $featured = !empty($_POST['featured']);

        if (!empty($artworkId)) {
            $res = supabase_alternar_destaque_obra($artworkId, $featured, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = $featured ? 'Obra marcada como destaque na página inicial!' : 'Obra removida dos destaques da página inicial.';
            } else {
                $msgErro = 'Erro ao alterar destaque da obra: ' . $res['error'];
            }
        }
        $currentTab = 'artworks';
    }

    // 6. Atualizar Dados Cadastrais da Obra
    elseif ($action === 'update_artwork_details') {
        $artworkId = trim($_POST['artwork_id'] ?? '');
        if (!empty($artworkId)) {
            $dados = [
                'name'               => $_POST['name'] ?? '',
                'technique'          => $_POST['technique'] ?? '',
                'year'               => $_POST['year'] ?? 2024,
                'width'              => $_POST['width'] ?? 0,
                'height'             => $_POST['height'] ?? 0,
                'depth'              => $_POST['depth'] ?? null,
                'conservation_state' => $_POST['conservation_state'] ?? '',
                'provenance'         => $_POST['provenance'] ?? '',
                'location'           => $_POST['location'] ?? '',
                'description'        => $_POST['description'] ?? '',
                'code'               => $_POST['code'] ?? '',
                'availability'       => $_POST['availability'] ?? 'available',
                'featured'           => !empty($_POST['featured'])
            ];
            if (!empty($_POST['artist_id'])) $dados['artist_id'] = $_POST['artist_id'];
            if (!empty($_POST['artist_name'])) $dados['artist_name'] = trim($_POST['artist_name']);
            if (!empty($_POST['category_id'])) $dados['category_id'] = $_POST['category_id'];
            if (!empty($_POST['category_name'])) $dados['category_name'] = trim($_POST['category_name']);

            $res = supabase_atualizar_dados_obra($artworkId, $dados, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Dados da obra atualizados com sucesso!';
            } else {
                $msgErro = 'Erro ao atualizar dados: ' . $res['error'];
            }
        }
        $currentTab = 'artworks';
    }

    // 7. Cadastrar Nova Obra
    elseif ($action === 'create_artwork') {
        $unit = in_array($_POST['dimension_unit'] ?? 'cm', ['cm', 'm'], true) ? $_POST['dimension_unit'] : 'cm';
        $width = supabase_parse_dimensao($_POST['width'] ?? null) ?? 0.0;
        $height = supabase_parse_dimensao($_POST['height'] ?? null) ?? 0.0;
        $depth = supabase_parse_dimensao($_POST['depth'] ?? null);

        $dados = [
            'name'               => trim($_POST['name'] ?? ''),
            'code'               => trim($_POST['code'] ?? ''),
            'technique'          => trim($_POST['technique'] ?? ''),
            'year'               => (int)($_POST['year'] ?? date('Y')),
            'dimension_unit'     => $unit,
            'width'              => $width,
            'height'             => $height,
            'depth'              => $depth,
            'conservation_state' => trim($_POST['conservation_state'] ?? 'Excelente'),
            'provenance'         => trim($_POST['provenance'] ?? 'Acervo Art For Sale'),
            'location'           => trim($_POST['location'] ?? 'Brasil'),
            'description'        => trim($_POST['description'] ?? ''),
            'availability'       => trim($_POST['availability'] ?? 'available'),
            'featured'           => !empty($_POST['featured'])
        ];
        if (!empty($_POST['artist_id'])) $dados['artist_id'] = $_POST['artist_id'];
        if (!empty($_POST['artist_name'])) $dados['artist_name'] = trim($_POST['artist_name']);
        if (!empty($_POST['category_id'])) $dados['category_id'] = $_POST['category_id'];
        if (!empty($_POST['category_name'])) $dados['category_name'] = trim($_POST['category_name']);

        if (empty($dados['name'])) {
            $msgErro = 'O título da obra é obrigatório.';
        } else {
            $res = supabase_criar_obra($dados, $adminToken);
            if (empty($res['error']) && !empty($res['data'])) {
                $newArtwork = is_array($res['data']) ? ($res['data'][0] ?? $res['data']) : null;
                $newId = $newArtwork['id'] ?? '';
                $msgSucesso = 'Nova obra cadastrada com sucesso! Agora você pode enviar as fotografias para o Storage.';
                if ($newId) {
                    header("Location: index.php?tab=artworks&artwork_id={$newId}&msg=created");
                    exit;
                }
            } else {
                $msgErro = 'Erro ao cadastrar obra: ' . ($res['error'] ?? 'Falha desconhecida.');
            }
        }
        $currentTab = 'artworks';
    }

    // 8. Atualizar Status de Consulta
    elseif ($action === 'update_inquiry_status') {
        $inquiryId = trim($_POST['inquiry_id'] ?? '');
        $newStatus = trim($_POST['status'] ?? 'in_progress');
        if (!empty($inquiryId)) {
            $res = supabase_request("inquiries?id=eq.{$inquiryId}", 'PATCH', ['status' => $newStatus], $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Status da consulta atualizado!';
            } else {
                $msgErro = 'Erro ao atualizar consulta: ' . $res['error'];
            }
        }
        $currentTab = 'inquiries';
    }

    // 9. Excluir Consulta
    elseif ($action === 'delete_inquiry') {
        $inquiryId = trim($_POST['inquiry_id'] ?? '');
        if (!empty($inquiryId)) {
            $res = supabase_excluir_inquiry($inquiryId, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Consulta removida com sucesso.';
            }
        }
        $currentTab = 'inquiries';
    }

    // 10. Atualizar Status de Contato
    elseif ($action === 'update_contact_status') {
        $contactId = trim($_POST['contact_id'] ?? '');
        $newStatus = trim($_POST['status'] ?? 'viewed');
        if (!empty($contactId)) {
            $res = supabase_request("contacts?id=eq.{$contactId}", 'PATCH', ['status' => $newStatus], $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Status da mensagem atualizado!';
            } else {
                $msgErro = 'Erro ao atualizar contato: ' . $res['error'];
            }
        }
        $currentTab = 'contacts';
    }

    // 11. Excluir Mensagem de Contato
    elseif ($action === 'delete_contact') {
        $contactId = trim($_POST['contact_id'] ?? '');
        if (!empty($contactId)) {
            $res = supabase_excluir_contact($contactId, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Mensagem de contato removida com sucesso.';
            }
        }
        $currentTab = 'contacts';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $msgSucesso = 'Nova obra cadastrada com sucesso! Selecione ou envie imagens abaixo.';
}

// Coleta de métricas para o Dashboard
$metrics = supabase_get_dashboard_metrics($adminToken);

// Obras para a aba de gestão (admin visualiza todas as cadastradas)
$obras = [];
if (supabase_is_configured()) {
    $obras = supabase_buscar_obras(null, null, 150, false, $adminToken);
}
if (empty($obras)) {
    global $catalogoObras;
    $fallback = $catalogoObras ?? [];
    $obras = $fallback;
}

$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
$obras = array_values(array_filter($obras, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

// Obra selecionada na aba de fotos
$selectedArtworkId = $_GET['artwork_id'] ?? ($obras[0]['id'] ?? '101');
$selectedArtwork = null;
foreach ($obras as $item) {
    if ((string)$item['id'] === (string)$selectedArtworkId) {
        $selectedArtwork = $item;
        break;
    }
}
if (!$selectedArtwork && !empty($obras)) {
    $selectedArtwork = $obras[0];
    $selectedArtworkId = $selectedArtwork['id'];
}
$galeriaAtual = !empty($selectedArtwork['galeria']) ? $selectedArtwork['galeria'] : [];

// Lista de Categorias e Artistas para modais de cadastro/edição
$allCategories = supabase_buscar_categorias_list($adminToken);
$allArtists = supabase_buscar_artistas_list($adminToken);

// Filtro de status da obra na interface
$statusFilter = $_GET['status'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel da Curadoria & Dashboard — Art For Sale</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/site/logo-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --color-gold-glow: rgba(179, 138, 84, 0.22);
            --text-main: #1c1b18;
            --text-muted: #7e796e;
            --border-color: #e8e3d8;
            --border-gold: rgba(179, 138, 84, 0.40);
            --color-danger: #c93b3b;
            --color-success: #2e7d32;
            --color-warning: #b25900;
            --color-info: #1565c0;
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
            position: sticky;
            top: 0;
            z-index: 100;
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

        .user-email-label {
            color: var(--text-muted);
        }

        .user-email-label strong {
            color: var(--text-main);
        }

        .btn-link-site {
            color: var(--color-gold-light, #dfc294);
            text-decoration: none;
            border: 1px solid var(--border-gold);
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
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

        .tab-btn:hover {
            color: var(--text-main);
        }

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
            width: 100%;
            margin: 0 auto;
            padding: 2.5rem 2.25rem;
            flex-grow: 1;
        }

        .header-title-box {
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

        /* ========================================================
           DASHBOARD: GRADE COM OS 8 INDICADORES EXIGIDOS
           ======================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        .stat-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--border-gold);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
        }

        .stat-card.gold-top::before { background: var(--color-gold); }
        .stat-card.green-top::before { background: #4caf50; }
        .stat-card.orange-top::before { background: #ff9800; }
        .stat-card.blue-top::before { background: #2196f3; }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .stat-icon {
            font-size: 1.25rem;
            opacity: 0.85;
        }

        .stat-number {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 2.85rem;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .stat-description {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: auto;
        }

        /* Seções de Resumo no Dashboard */
        .dash-tables-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        @media (max-width: 992px) {
            .dash-tables-row { grid-template-columns: 1fr; }
        }

        .panel-box {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.75rem;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .panel-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--color-gold);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .panel-link {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .panel-link:hover {
            color: var(--color-gold);
        }

        /* Tabela Estilizada */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .data-table th {
            text-align: left;
            padding: 0.75rem 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table td {
            padding: 0.85rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: rgba(255, 255, 255, 0.015);
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

        .status-available, .status-disponivel {
            background: rgba(46, 125, 50, 0.15);
            color: #81c784;
            border: 1px solid rgba(46, 125, 50, 0.35);
        }

        .status-reserved, .status-reservada {
            background: rgba(255, 152, 0, 0.15);
            color: #ffb74d;
            border: 1px solid rgba(255, 152, 0, 0.35);
        }

        .status-sold, .status-vendida {
            background: rgba(33, 150, 243, 0.15);
            color: #64b5f6;
            border: 1px solid rgba(33, 150, 243, 0.35);
        }

        .status-new {
            background: rgba(179, 138, 84, 0.15);
            color: var(--color-gold);
            border: 1px solid var(--border-gold);
        }

        .status-in_progress, .status-viewed {
            background: rgba(255, 152, 0, 0.15);
            color: #ffb74d;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .status-completed, .status-answered {
            background: rgba(76, 175, 80, 0.15);
            color: #81c784;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .badge-destaque {
            background: rgba(179, 138, 84, 0.2);
            color: var(--color-gold);
            border: 1px solid var(--border-gold);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 3px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* ========================================================
           GESTOR DE OBRAS & IMAGENS NO STORAGE
           ======================================================== */
        .storage-layout-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .storage-layout-grid { grid-template-columns: 1fr; }
        }

        .artwork-search-box {
            margin-bottom: 1rem;
        }

        .artwork-nav-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            max-height: 640px;
            overflow-y: auto;
            padding-right: 0.35rem;
        }

        .art-nav-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.75rem;
            background: var(--bg-card-alt);
            border-radius: 6px;
            border: 1px solid transparent;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.2s;
            position: relative;
        }

        .art-nav-item:hover {
            border-color: var(--border-gold);
            background-color: var(--bg-card-hover);
        }

        .art-nav-item.active {
            border-color: var(--color-gold);
            background: rgba(179, 138, 84, 0.14);
            box-shadow: 0 0 12px rgba(179, 138, 84, 0.15);
        }

        .art-nav-thumb {
            width: 48px;
            height: 48px;
            border-radius: 4px;
            object-fit: cover;
            background: #000;
            flex-shrink: 0;
            border: 1px solid var(--border-color);
        }

        .art-nav-name {
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .art-nav-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        .art-nav-meta-row {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.3rem;
        }

        /* Hero Summary da Obra Selecionada */
        .artwork-hero-summary {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .art-summary-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 2rem;
            color: var(--text-main);
            line-height: 1.15;
            margin-bottom: 0.4rem;
        }

        .art-summary-meta {
            font-size: 0.8125rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        .artwork-quick-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .status-select-form {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }

        .status-select-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--color-gold);
            text-transform: uppercase;
        }

        .status-select {
            background: transparent;
            color: var(--text-main);
            border: none;
            font-family: inherit;
            font-size: 0.8125rem;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .btn-featured-toggle {
            background: transparent;
            border: 1px solid var(--border-gold);
            color: var(--color-gold);
            padding: 0.45rem 0.85rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-featured-toggle.is-active {
            background: var(--color-gold);
            color: #ffffff;
        }

        .btn-featured-toggle:hover {
            background: var(--color-gold-hover);
            color: #ffffff;
        }

        .btn-edit-artwork {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.45rem 0.85rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit-artwork:hover {
            color: var(--text-main);
            border-color: var(--color-gold);
        }

        /* Grade de Fotografias */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .photo-card {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s;
            position: relative;
        }

        .photo-card:hover {
            border-color: var(--border-gold);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
        }

        .photo-card.is-primary {
            border-color: var(--color-gold);
            box-shadow: 0 0 20px rgba(179, 138, 84, 0.25);
        }

        .photo-stage {
            width: 100%;
            height: 180px;
            background-color: #080706;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
        }

        /* Visualização sem distorção */
        .photo-stage img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s;
        }

        .photo-card:hover .photo-stage img {
            transform: scale(1.03);
        }

        .photo-stage-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .photo-stage:hover .photo-stage-overlay {
            opacity: 1;
        }

        .btn-stage-zoom {
            background: rgba(14, 13, 11, 0.9);
            color: var(--color-gold);
            border: 1px solid var(--color-gold);
            padding: 0.45rem 0.85rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-primary-star {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--color-gold);
            color: #ffffff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.25rem 0.55rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
            z-index: 2;
        }

        .badge-type-label {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(14, 13, 11, 0.9);
            color: #ffffff;
            font-size: 0.6875rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 2;
        }

        .photo-meta-box {
            padding: 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            flex-grow: 1;
        }

        .photo-type-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .photo-storage-path {
            font-size: 0.6875rem;
            color: var(--text-muted);
            word-break: break-all;
            background: rgba(0, 0, 0, 0.25);
            padding: 0.3rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
        }

        .photo-copy-links-row {
            display: flex;
            gap: 0.4rem;
            margin-top: 0.35rem;
        }

        .btn-copy-chip {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.6875rem;
            padding: 0.25rem 0.45rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            text-align: center;
        }

        .btn-copy-chip:hover {
            color: var(--color-gold);
            border-color: var(--border-gold);
            background: var(--color-gold-subtle);
        }

        .photo-actions-row {
            margin-top: auto;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
        }

        .btn-make-primary {
            flex: 1;
            background: transparent;
            border: 1px solid var(--border-gold);
            color: var(--color-gold);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.45rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-make-primary:hover {
            background: var(--color-gold);
            color: #ffffff;
        }

        .btn-del-photo {
            background: transparent;
            border: 1px solid rgba(201, 59, 59, 0.35);
            color: #ff9999;
            font-size: 0.72rem;
            padding: 0.45rem 0.65rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-del-photo:hover {
            background: var(--color-danger);
            color: #ffffff;
        }

        /* Formulário de Envio para o Storage */
        .upload-card-box {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-gold);
            border-radius: 8px;
            padding: 1.85rem;
            position: relative;
        }

        .upload-header-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-gold);
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-header-sub {
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 600px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }

        .f-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .f-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--color-gold);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .f-input {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .f-input:focus {
            border-color: var(--color-gold);
            box-shadow: 0 0 0 3px var(--color-gold-glow);
        }

        .f-checkbox-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.8125rem;
            color: var(--text-main);
            cursor: pointer;
            margin-top: 0.75rem;
        }

        .f-checkbox-row input {
            accent-color: var(--color-gold);
            width: 16px;
            height: 16px;
        }

        .btn-upload-submit {
            background-color: var(--color-gold);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            padding: 0.85rem 1.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(179, 138, 84, 0.25);
        }

        .btn-upload-submit:hover {
            background-color: var(--color-gold-hover);
            box-shadow: 0 6px 20px rgba(179, 138, 84, 0.4);
        }

        /* Botão Novo Cadastro */
        .btn-new-item {
            background: var(--color-gold);
            color: #ffffff;
            border: none;
            padding: 0.6rem 1.15rem;
            border-radius: 4px;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-new-item:hover {
            background: var(--color-gold-hover);
        }

        /* Botão Ação WhatsApp */
        .btn-whatsapp-action {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(46, 125, 50, 0.15);
            color: #81c784;
            border: 1px solid rgba(46, 125, 50, 0.35);
            padding: 0.35rem 0.65rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-whatsapp-action:hover {
            background: #2e7d32;
            color: #ffffff;
        }

        /* Modais */
        .admin-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .admin-modal-backdrop.is-open {
            display: flex;
        }

        .admin-modal-content {
            background: #181613;
            border: 1px solid var(--border-gold);
            border-radius: 8px;
            max-width: 680px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
            position: relative;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
        }

        .admin-modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }

        .admin-modal-close:hover {
            color: #ffffff;
        }

        /* Lightbox Específico para Alta Resolução */
        .lightbox-modal {
            max-width: 1000px;
            padding: 1.5rem;
            text-align: center;
        }

        .lightbox-stage {
            max-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000000;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 1.25rem;
        }

        .lightbox-stage img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
        }

        .lightbox-meta {
            text-align: left;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Toast de Notificação */
        .admin-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e1b17;
            border: 1px solid var(--color-gold);
            color: #ffffff;
            padding: 0.85rem 1.4rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        .admin-toast.show {
            transform: translateY(0);
            opacity: 1;
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
            <span class="badge-role">Admin • Supabase Auth</span>
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
        <a href="index.php?tab=dashboard" class="tab-btn <?= $currentTab === 'dashboard' ? 'active' : '' ?>">
            <span>📊 Dashboard Geral</span>
        </a>
        <a href="obras.php" class="tab-btn">
            <span>🖼️ Catálogo de Obras</span>
            <span class="tab-count"><?= $metrics['obras_total'] ?></span>
        </a>
        <a href="hero-slides.php" class="tab-btn">
            <span>✨ Banners do Hero</span>
            <span class="tab-count">3</span>
        </a>
        <a href="categorias.php" class="tab-btn">
            <span>📁 Categorias</span>
            <span class="tab-count"><?= $metrics['categorias_total'] ?></span>
        </a>
        <a href="artistas.php" class="tab-btn">
            <span>🎨 Artistas</span>
            <span class="tab-count"><?= $metrics['artistas_total'] ?></span>
        </a>
        <a href="obra-nova.php" class="tab-btn">
            <span>+ Cadastrar Obra</span>
        </a>
        <a href="index.php?tab=inquiries" class="tab-btn <?= $currentTab === 'inquiries' ? 'active' : '' ?>">
            <span>📩 Consultas Recebidas</span>
            <span class="tab-count"><?= $metrics['consultas_total'] ?></span>
        </a>
        <a href="index.php?tab=contacts" class="tab-btn <?= $currentTab === 'contacts' ? 'active' : '' ?>">
            <span>💬 Contatos</span>
            <span class="tab-count"><?= $metrics['contatos_total'] ?></span>
        </a>
    </nav>

    <main class="admin-content">

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

        <!-- ========================================================
             ABA 1: DASHBOARD GERAL COM OS 8 INDICADORES EXIGIDOS
             ======================================================== -->
        <?php if ($currentTab === 'dashboard'): ?>
            
            <div class="header-title-box">
                <div>
                    <h1 class="section-headline">Visão Geral do Acervo</h1>
                    <p class="section-subhead">Indicadores consolidados em tempo real via Supabase</p>
                </div>
                <button type="button" class="btn-new-item" onclick="openModal('modalCreateArtwork')">
                    + Cadastrar Nova Obra
                </button>
            </div>

            <!-- Grade com os 8 Indicadores -->
            <div class="stats-grid">
                
                <!-- 1. Total de obras -->
                <a href="index.php?tab=artworks" class="stat-card gold-top" title="Ver todas as obras">
                    <div class="stat-header">
                        <span class="stat-label">Total de Obras</span>
                        <span class="stat-icon">🎨</span>
                    </div>
                    <div class="stat-number"><?= $metrics['obras_total'] ?></div>
                    <span class="stat-description">Obras cadastradas no acervo</span>
                </a>

                <!-- 2. Obras Disponíveis -->
                <a href="index.php?tab=artworks&status=available" class="stat-card green-top" title="Filtrar disponíveis">
                    <div class="stat-header">
                        <span class="stat-label">Disponíveis</span>
                        <span class="stat-icon">✓</span>
                    </div>
                    <div class="stat-number" style="color: #81c784;"><?= $metrics['obras_disponiveis'] ?></div>
                    <span class="stat-description">Prontas para aquisição</span>
                </a>

                <!-- 3. Obras Reservadas -->
                <a href="index.php?tab=artworks&status=reserved" class="stat-card orange-top" title="Filtrar reservadas">
                    <div class="stat-header">
                        <span class="stat-label">Reservadas</span>
                        <span class="stat-icon">⏳</span>
                    </div>
                    <div class="stat-number" style="color: #ffb74d;"><?= $metrics['obras_reservadas'] ?></div>
                    <span class="stat-description">Em negociação com colecionadores</span>
                </a>

                <!-- 4. Obras Vendidas -->
                <a href="index.php?tab=artworks&status=sold" class="stat-card blue-top" title="Filtrar vendidas">
                    <div class="stat-header">
                        <span class="stat-label">Vendidas</span>
                        <span class="stat-icon">🏛️</span>
                    </div>
                    <div class="stat-number" style="color: #64b5f6;"><?= $metrics['obras_vendidas'] ?></div>
                    <span class="stat-description">Acervos particulares transferidos</span>
                </a>

                <!-- 5. Categorias -->
                <a href="categorias.php" class="stat-card gold-top" title="Gerenciar Categorias">
                    <div class="stat-header">
                        <span class="stat-label">Categorias</span>
                        <span class="stat-icon">📁</span>
                    </div>
                    <div class="stat-number"><?= $metrics['categorias_total'] ?></div>
                    <span class="stat-description">Estilos e técnicas cadastradas</span>
                </a>

                <!-- 6. Artistas -->
                <a href="artistas.php" class="stat-card gold-top" title="Gerenciar Artistas">
                    <div class="stat-header">
                        <span class="stat-label">Artistas</span>
                        <span class="stat-icon">🖌️</span>
                    </div>
                    <div class="stat-number"><?= $metrics['artistas_total'] ?></div>
                    <span class="stat-description">Mestres e criadores no catálogo</span>
                </a>

                <!-- 7. Consultas Recebidas -->
                <a href="index.php?tab=inquiries" class="stat-card gold-top" title="Ver consultas recebidas">
                    <div class="stat-header">
                        <span class="stat-label">Consultas Recebidas</span>
                        <span class="stat-icon">📩</span>
                    </div>
                    <div class="stat-number"><?= $metrics['consultas_total'] ?></div>
                    <span class="stat-description">Propostas de interesse direto</span>
                </a>

                <!-- 8. Contatos Recebidos -->
                <a href="index.php?tab=contacts" class="stat-card gold-top" title="Ver mensagens de contato">
                    <div class="stat-header">
                        <span class="stat-label">Contatos Recebidos</span>
                        <span class="stat-icon">💬</span>
                    </div>
                    <div class="stat-number"><?= $metrics['contatos_total'] ?></div>
                    <span class="stat-description">Mensagens gerais de visitantes</span>
                </a>

            </div>

            <!-- Painel Especial de Banners do Hero -->
            <div style="background: linear-gradient(135deg, rgba(179, 138, 84, 0.12) 0%, rgba(200, 169, 110, 0.05) 100%); border: 1px solid var(--border-gold); border-radius: 10px; padding: 1.25rem 1.75rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="font-size: 2rem; background: var(--bg-card); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-gold);">
                        ✨
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem;">
                            Banners & Slides da Página Inicial (Hero Section)
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                            Altere as 3 imagens em destaque, frases editoriais, títulos e citações exibidas no carrossel de entrada da galeria.
                        </p>
                    </div>
                </div>
                <a href="hero-slides.php" class="btn-preset" style="padding: 0.7rem 1.35rem; font-size: 0.875rem; font-weight: 600; background-color: var(--color-gold); color: #ffffff; border: none; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span>Gerenciar 3 Banners</span> <span>→</span>
                </a>
            </div>

            <!-- Resumo das Atividades Recentes -->
            <div class="dash-tables-row">
                
                <!-- Últimas Consultas -->
                <div class="panel-box">
                    <div class="panel-header">
                        <h2 class="panel-title">📩 Últimas Consultas de Obras</h2>
                        <a href="index.php?tab=inquiries" class="panel-link">Ver todas →</a>
                    </div>

                    <?php if (empty($metrics['consultas_recentes'])): ?>
                        <p style="font-size: 0.8125rem; color: var(--text-muted); padding: 2rem 0; text-align: center;">
                            Nenhuma consulta de interesse recebida até o momento.
                        </p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Interessado</th>
                                    <th>Contato</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($metrics['consultas_recentes'], 0, 5) as $inq): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($inq['name']) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($inq['email']) ?><br>
                                            <span style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($inq['whatsapp'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <span class="status-pill status-<?= htmlspecialchars($inq['status'] ?? 'new') ?>">
                                                <?= htmlspecialchars($inq['status'] ?? 'new') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Últimas Mensagens de Contato -->
                <div class="panel-box">
                    <div class="panel-header">
                        <h2 class="panel-title">💬 Últimas Mensagens de Contato</h2>
                        <a href="index.php?tab=contacts" class="panel-link">Ver todas →</a>
                    </div>

                    <?php if (empty($metrics['contatos_recentes'])): ?>
                        <p style="font-size: 0.8125rem; color: var(--text-muted); padding: 2rem 0; text-align: center;">
                            Nenhuma mensagem de contato recebida até o momento.
                        </p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Assunto</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($metrics['contatos_recentes'], 0, 5) as $cont): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($cont['name']) ?></strong><br>
                                            <span style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($cont['email']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($cont['subject'] ?? 'Geral') ?></td>
                                        <td>
                                            <span class="status-pill status-<?= htmlspecialchars($cont['status'] ?? 'new') ?>">
                                                <?= htmlspecialchars($cont['status'] ?? 'new') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>

        <!-- ========================================================
             ABA 2: GESTÃO DE OBRAS & FOTOS NO SUPABASE STORAGE
             ======================================================== -->
        <?php elseif ($currentTab === 'artworks'): ?>

            <div class="header-title-box">
                <div>
                    <h1 class="section-headline">Armazenamento & Curadoria de Obras</h1>
                    <p class="section-subhead">
                        Bucket <code>artworks</code> • Pastas <code>{id}/principal/</code> e <code>{id}/gallery/</code> • 10 MB máx. (JPG, JPEG, PNG, WEBP)
                    </p>
                </div>
                <button type="button" class="btn-new-item" onclick="openModal('modalCreateArtwork')">
                    + Nova Obra
                </button>
            </div>

            <div class="storage-layout-grid">
                
                <!-- Coluna Lateral: Filtro e Lista de Obras -->
                <div class="panel-box" style="padding: 1.25rem;">
                    <div class="panel-header" style="margin-bottom: 0.85rem;">
                        <h2 class="panel-title" style="font-size: 1rem;">Obras do Catálogo (<?= count($obras) ?>)</h2>
                    </div>

                    <!-- Busca e Filtro Rápido -->
                    <div class="artwork-search-box">
                        <input 
                            type="text" 
                            id="artworkFilterInput" 
                            class="f-input" 
                            placeholder="🔍 Buscar por título, artista ou código..." 
                            style="width: 100%; font-size: 0.75rem; padding: 0.55rem 0.75rem;"
                        >
                    </div>

                    <div class="artwork-nav-list" id="artworksNavList">
                        <?php foreach ($obras as $item): 
                            $isActive = (string)$item['id'] === (string)$selectedArtworkId;
                            $st = strtolower($item['disponibilidade'] ?? 'available');
                            $isFeatured = !empty($item['destaque']);
                        ?>
                            <a 
                                href="index.php?tab=artworks&artwork_id=<?= $item['id'] ?>" 
                                class="art-nav-item <?= $isActive ? 'active' : '' ?>"
                                data-title="<?= htmlspecialchars(strtolower($item['titulo'] ?? '')) ?>"
                                data-artist="<?= htmlspecialchars(strtolower($item['artista'] ?? '')) ?>"
                                data-code="<?= htmlspecialchars(strtolower($item['codigo'] ?? '')) ?>"
                                data-status="<?= $st ?>"
                            >
                                <img 
                                    src="<?= htmlspecialchars(artsale_resolve_image_url($item['imagem'] ?? '', '../')) ?>" 
                                    alt="<?= htmlspecialchars($item['titulo'] ?? 'Miniatura') ?>" 
                                    class="art-nav-thumb"
                                    onerror="if(!this.src.endsWith('.svg')) this.src='../assets/images/obras/obra-horizontes-dourados.svg';"
                                >
                                <div style="overflow: hidden; flex: 1;">
                                    <div class="art-nav-name"><?= htmlspecialchars($item['titulo']) ?></div>
                                    <div class="art-nav-sub"><?= htmlspecialchars($item['artista']) ?></div>
                                    <div class="art-nav-meta-row">
                                        <span class="status-pill status-<?= $st ?>"><?= $st === 'available' ? 'Disponível' : ($st === 'reserved' ? 'Reservada' : 'Vendida') ?></span>
                                        <?php if ($isFeatured): ?>
                                            <span class="badge-destaque">★ Destaque</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coluna Principal: Detalhes e Fotografias no Storage -->
                <div class="panel-box">
                    
                    <!-- Resumo da Obra Selecionada -->
                    <div class="artwork-hero-summary">
                        <div>
                            <h2 class="art-summary-title"><?= htmlspecialchars($selectedArtwork['titulo'] ?? 'Obra') ?></h2>
                            <p class="art-summary-meta">
                                Artista: <strong><?= htmlspecialchars($selectedArtwork['artista'] ?? 'Artista') ?></strong> • 
                                Categoria: <strong><?= htmlspecialchars($selectedArtwork['categoria'] ?? 'Pinturas') ?></strong> • 
                                Código: <code><?= htmlspecialchars($selectedArtwork['codigo'] ?? 'ASF-0000') ?></code><br>
                                Técnica: <?= htmlspecialchars($selectedArtwork['tecnica'] ?? 'Óleo sobre tela') ?> • 
                                Dimensões: <?= htmlspecialchars($selectedArtwork['dimensoes'] ?? '80 × 120 cm') ?> • 
                                Ano: <?= htmlspecialchars($selectedArtwork['ano'] ?? 2024) ?>
                            </p>
                        </div>
                        
                        <div class="artwork-quick-actions">
                            
                            <!-- Alteração Rápida de Status de Disponibilidade -->
                            <form method="POST" class="status-select-form" title="Alterar status de disponibilidade">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_artwork_status">
                                <input type="hidden" name="artwork_id" value="<?= $selectedArtworkId ?>">
                                <span class="status-select-label">Status:</span>
                                <select name="availability" class="status-select" onchange="this.form.submit()">
                                    <?php 
                                    $curStatus = strtolower($selectedArtwork['disponibilidade'] ?? 'available');
                                    ?>
                                    <option value="available" <?= $curStatus === 'available' ? 'selected' : '' ?>>Disponível</option>
                                    <option value="reserved" <?= $curStatus === 'reserved' ? 'selected' : '' ?>>Reservada</option>
                                    <option value="sold" <?= $curStatus === 'sold' ? 'selected' : '' ?>>Vendida</option>
                                </select>
                            </form>

                            <!-- Alternador de Destaque -->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="artwork_id" value="<?= $selectedArtworkId ?>">
                                <input type="hidden" name="featured" value="<?= !empty($selectedArtwork['destaque']) ? '0' : '1' ?>">
                                <button type="submit" class="btn-featured-toggle <?= !empty($selectedArtwork['destaque']) ? 'is-active' : '' ?>">
                                    <?= !empty($selectedArtwork['destaque']) ? '★ Em Destaque na Home' : '☆ Marcar Destaque' ?>
                                </button>
                            </form>

                            <!-- Editar Metadados -->
                            <button type="button" class="btn-edit-artwork" onclick="openModal('modalEditArtwork')">
                                ✎ Editar Dados
                            </button>

                            <a href="../pages/obra.php?id=<?= $selectedArtworkId ?>" target="_blank" class="btn-link-site">
                                Ver no Site ↗
                            </a>
                        </div>
                    </div>

                    <!-- Fotografias no Storage -->
                    <div class="panel-header" style="margin-bottom: 1.5rem;">
                        <h3 class="panel-title" style="color: var(--text-main);">
                            Fotografias no Supabase Storage (<?= count($galeriaAtual) ?>)
                        </h3>
                        <span style="font-size: 0.75rem; color: var(--color-gold);">
                            * Exatamente 1 imagem principal é selecionada para os cards da galeria
                        </span>
                    </div>

                    <?php if (empty($galeriaAtual)): ?>
                        <div style="padding: 3rem 1.5rem; text-align: center; color: var(--text-muted); background: rgba(0,0,0,0.2); border-radius: 6px; margin-bottom: 2rem;">
                            <p style="font-size: 0.9375rem; margin-bottom: 0.5rem; color: var(--text-main);">
                                Nenhuma fotografia enviada para esta obra no Supabase Storage.
                            </p>
                            <p style="font-size: 0.8125rem;">
                                Utilize o formulário abaixo para fazer o upload da imagem principal e das vistas complementares.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="photos-grid">
                            <?php foreach ($galeriaAtual as $idx => $img): 
                                $isPrimary = !empty($img['is_primary']) || ($img['tipo'] ?? '') === 'principal';
                                $imgTipo = $img['tipo'] ?? 'principal';
                                $tipoNome = supabase_legenda_tipo_imagem($imgTipo);
                                $imgUrl = $img['imagem'];
                                $storagePath = $img['storage_path'] ?? '';
                            ?>
                                <div class="photo-card <?= $isPrimary ? 'is-primary' : '' ?>">
                                    
                                    <?php $resolvedImg = artsale_resolve_image_url($imgUrl, '../'); ?>
                                    <div class="photo-stage" onclick="openLightbox('<?= htmlspecialchars($resolvedImg) ?>', '<?= htmlspecialchars($tipoNome) ?>', '<?= htmlspecialchars($storagePath) ?>')">
                                        <img 
                                            src="<?= htmlspecialchars($resolvedImg) ?>" 
                                            alt="<?= htmlspecialchars($tipoNome) ?>" 
                                            loading="lazy"
                                            onerror="if(!this.src.endsWith('.svg')) this.src='../assets/images/obras/placeholder-obra.svg';"
                                        >
                                        
                                        <div class="photo-stage-overlay">
                                            <span class="btn-stage-zoom">🔍 Ver em Alta Resolução</span>
                                        </div>

                                        <?php if ($isPrimary): ?>
                                            <span class="badge-primary-star">★ Principal</span>
                                        <?php endif; ?>
                                        <span class="badge-type-label"><?= htmlspecialchars($tipoNome) ?></span>
                                    </div>

                                    <div class="photo-meta-box">
                                        <span class="photo-type-name"><?= htmlspecialchars($tipoNome) ?></span>
                                        
                                        <?php if (!empty($storagePath)): ?>
                                            <span class="photo-storage-path" title="<?= htmlspecialchars($storagePath) ?>">
                                                📁 <?= htmlspecialchars($storagePath) ?>
                                            </span>
                                        <?php endif; ?>

                                        <!-- Chips de Cópia Rápida -->
                                        <div class="photo-copy-links-row">
                                            <button 
                                                type="button" 
                                                class="btn-copy-chip" 
                                                onclick="copyToClipboard('<?= htmlspecialchars($imgUrl) ?>', 'URL pública da imagem copiada!')"
                                                title="Copiar URL pública direta da imagem"
                                            >
                                                🔗 Copiar URL
                                            </button>

                                            <?php if (!empty($storagePath)): ?>
                                                <button 
                                                    type="button" 
                                                    class="btn-copy-chip" 
                                                    onclick="copyToClipboard('<?= htmlspecialchars($storagePath) ?>', 'Caminho no Storage copiado!')"
                                                    title="Copiar caminho no bucket do Storage"
                                                >
                                                    📁 Caminho
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Ações da Foto -->
                                        <div class="photo-actions-row">
                                            <?php if (!$isPrimary && !empty($img['id'])): ?>
                                                <form method="POST" style="flex: 1;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="set_primary">
                                                    <input type="hidden" name="artwork_id" value="<?= $selectedArtworkId ?>">
                                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                    <button type="submit" class="btn-make-primary" title="Definir como a única imagem principal">
                                                        Tornar Principal
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (!empty($img['id'])): ?>
                                                <form method="POST" onsubmit="return confirm('Deseja realmente excluir esta imagem do Storage e da obra?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete_image">
                                                    <input type="hidden" name="artwork_id" value="<?= $selectedArtworkId ?>">
                                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                    <input type="hidden" name="storage_path" value="<?= htmlspecialchars($storagePath) ?>">
                                                    <button type="submit" class="btn-del-photo" title="Excluir do Storage">✕ Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulário de Upload para o Supabase Storage -->
                    <div class="upload-card-box">
                        <h3 class="upload-header-title">
                            <span>📤 Enviar Nova Fotografia para o Storage</span>
                        </h3>
                        <p class="upload-header-sub">
                            Destino: <code>artworks/<?= $selectedArtworkId ?>/[principal|gallery]/</code> • Limite: <strong>10 MB</strong> (JPG, JPEG, PNG, WEBP)
                        </p>

                        <form method="POST" enctype="multipart/form-data" id="adminUploadForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                            <input type="hidden" name="action" value="upload_image">
                            <input type="hidden" name="artwork_id" value="<?= $selectedArtworkId ?>">

                            <div class="form-grid-2">
                                <div class="f-group">
                                    <label for="artwork_file" class="f-label">Arquivo de Imagem *</label>
                                    <input 
                                        type="file" 
                                        name="artwork_file" 
                                        id="artwork_file" 
                                        class="f-input" 
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                                        required
                                    >
                                    <div id="fileUploadPreview" style="margin-top: 0.5rem; display: none; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--color-gold);">
                                        <span>Arquivo selecionado:</span> <strong id="previewFileName"></strong> (<span id="previewFileSize"></span>)
                                    </div>
                                </div>

                                <div class="f-group">
                                    <label for="image_type" class="f-label">Tipo de Vista da Obra *</label>
                                    <select name="image_type" id="image_type" class="f-input">
                                        <option value="principal">Imagem Principal (Capa dos Cards)</option>
                                        <option value="frontal">Fotografia Frontal</option>
                                        <option value="lateral">Fotografia Lateral</option>
                                        <option value="detalhe">Detalhe da Obra</option>
                                        <option value="textura">Textura e Relevo</option>
                                        <option value="ambiente">Obra em Ambiente</option>
                                        <option value="outras">Outras Imagens</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="f-group">
                                    <label for="sort_order" class="f-label">Ordem na Galeria</label>
                                    <input 
                                        type="number" 
                                        name="sort_order" 
                                        id="sort_order" 
                                        class="f-input" 
                                        value="<?= count($galeriaAtual) + 1 ?>" 
                                        min="0"
                                    >
                                </div>

                                <div class="f-group" style="justify-content: center;">
                                    <label class="f-checkbox-row">
                                        <input type="checkbox" name="is_primary" id="is_primary" value="1" <?= empty($galeriaAtual) ? 'checked' : '' ?>>
                                        <span>Definir como Imagem Principal da Obra (desmarca as demais)</span>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn-upload-submit">
                                Enviar Imagem para o Supabase Storage
                            </button>
                        </form>
                    </div>

                </div>

            </div>

        <!-- ========================================================
             ABA 3: CONSULTAS DE OBRAS (INQUIRIES)
             ======================================================== -->
        <?php elseif ($currentTab === 'inquiries'): ?>

            <div class="header-title-box">
                <div>
                    <h1 class="section-headline">Consultas de Interesse Recebidas</h1>
                    <p class="section-subhead">Interesses manifestados por visitantes no botão "Tenho interesse" das obras</p>
                </div>
            </div>

            <div class="panel-box">
                <?php if (empty($metrics['consultas_recentes'])): ?>
                    <p style="padding: 3.5rem; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
                        Nenhuma consulta registrada até o momento.
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Interessado</th>
                                    <th>Obra de Interesse</th>
                                    <th>Mensagem</th>
                                    <th>WhatsApp / Contato</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metrics['consultas_recentes'] as $inq): 
                                    $inqName = htmlspecialchars($inq['name'] ?? 'Visitante');
                                    $artworkTitle = htmlspecialchars($inq['artworks']['name'] ?? 'Obra de Arte');
                                    $rawWa = preg_replace('/\D/', '', $inq['whatsapp'] ?? '');
                                    $waGreeting = urlencode("Olá {$inqName}, sou da curadoria da Art For Sale a respeito do seu interesse na obra \"{$artworkTitle}\". Como posso lhe assessorar?");
                                    $waLink = !empty($rawWa) ? "https://wa.me/{$rawWa}?text={$waGreeting}" : '';
                                ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 0.75rem; white-space: nowrap;">
                                            <?= !empty($inq['created_at']) ? date('d/m/Y H:i', strtotime($inq['created_at'])) : '-' ?>
                                        </td>
                                        <td>
                                            <strong><?= $inqName ?></strong><br>
                                            <a href="mailto:<?= htmlspecialchars($inq['email']) ?>" style="color: var(--text-muted); font-size: 0.75rem; text-decoration: none;">
                                                <?= htmlspecialchars($inq['email']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <strong><?= $artworkTitle ?></strong>
                                            <?php if (!empty($inq['artwork_id'])): ?>
                                                <br><a href="../pages/obra.php?id=<?= $inq['artwork_id'] ?>" target="_blank" style="color: var(--color-gold); font-size: 0.72rem;">Ver Obra ↗</a>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 280px; line-height: 1.45; font-size: 0.8125rem;">
                                            <?= nl2br(htmlspecialchars($inq['message'] ?? 'Sem mensagem adicional.')) ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <?php if (!empty($waLink)): ?>
                                                <a href="<?= $waLink ?>" target="_blank" class="btn-whatsapp-action" title="Iniciar atendimento no WhatsApp">
                                                    💬 <?= htmlspecialchars($inq['whatsapp']) ?> ↗
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.75rem;">Não informado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_inquiry_status">
                                                <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" style="background: #111; color: #fff; border: 1px solid #333; padding: 0.35rem 0.5rem; border-radius: 4px; font-size: 0.75rem; outline: none;">
                                                    <option value="new" <?= ($inq['status'] ?? '') === 'new' ? 'selected' : '' ?>>Novo</option>
                                                    <option value="in_progress" <?= ($inq['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>Em Atendimento</option>
                                                    <option value="completed" <?= ($inq['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Concluído</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Deseja realmente excluir este registro de consulta?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_inquiry">
                                                <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                <button type="submit" class="btn-del-photo" title="Excluir">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <!-- ========================================================
             ABA 4: CONTATOS GERAIS
             ======================================================== -->
        <?php elseif ($currentTab === 'contacts'): ?>

            <div class="header-title-box">
                <div>
                    <h1 class="section-headline">Mensagens de Contato</h1>
                    <p class="section-subhead">Mensagens recebidas pelo canal de contato geral da galeria</p>
                </div>
            </div>

            <div class="panel-box">
                <?php if (empty($metrics['contatos_recentes'])): ?>
                    <p style="padding: 3.5rem; text-align: center; color: var(--text-muted); font-size: 0.875rem;">
                        Nenhuma mensagem de contato registrada até o momento.
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Remetente</th>
                                    <th>Assunto</th>
                                    <th>Mensagem</th>
                                    <th>WhatsApp</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metrics['contatos_recentes'] as $cont): 
                                    $contName = htmlspecialchars($cont['name'] ?? 'Visitante');
                                    $rawWa = preg_replace('/\D/', '', $cont['whatsapp'] ?? '');
                                    $waGreeting = urlencode("Olá {$contName}, recebemos sua mensagem na Art For Sale. Como podemos ajudá-lo?");
                                    $waLink = !empty($rawWa) ? "https://wa.me/{$rawWa}?text={$waGreeting}" : '';
                                ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 0.75rem; white-space: nowrap;">
                                            <?= !empty($cont['created_at']) ? date('d/m/Y H:i', strtotime($cont['created_at'])) : '-' ?>
                                        </td>
                                        <td>
                                            <strong><?= $contName ?></strong><br>
                                            <a href="mailto:<?= htmlspecialchars($cont['email']) ?>" style="color: var(--text-muted); font-size: 0.75rem; text-decoration: none;">
                                                <?= htmlspecialchars($cont['email']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($cont['subject'] ?? 'Contato') ?></td>
                                        <td style="max-width: 280px; line-height: 1.45; font-size: 0.8125rem;">
                                            <?= nl2br(htmlspecialchars($cont['message'] ?? '')) ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <?php if (!empty($waLink)): ?>
                                                <a href="<?= $waLink ?>" target="_blank" class="btn-whatsapp-action">
                                                    💬 WhatsApp ↗
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.75rem;">Não informado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_contact_status">
                                                <input type="hidden" name="contact_id" value="<?= $cont['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" style="background: #111; color: #fff; border: 1px solid #333; padding: 0.35rem 0.5rem; border-radius: 4px; font-size: 0.75rem; outline: none;">
                                                    <option value="new" <?= ($cont['status'] ?? '') === 'new' ? 'selected' : '' ?>>Novo</option>
                                                    <option value="viewed" <?= ($cont['status'] ?? '') === 'viewed' ? 'selected' : '' ?>>Visualizado</option>
                                                    <option value="answered" <?= ($cont['status'] ?? '') === 'answered' ? 'selected' : '' ?>>Respondido</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Deseja realmente excluir esta mensagem?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_contact">
                                                <input type="hidden" name="contact_id" value="<?= $cont['id'] ?>">
                                                <button type="submit" class="btn-del-photo" title="Excluir">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- ========================================================
         MODAL 1: CADASTRAR NOVA OBRA NO SUPABASE
         ======================================================== -->
    <div class="admin-modal-backdrop" id="modalCreateArtwork">
        <div class="admin-modal-content">
            <button type="button" class="admin-modal-close" onclick="closeModal('modalCreateArtwork')">✕</button>
            
            <h2 style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 2rem; color: var(--color-gold); margin-bottom: 0.5rem;">
                Cadastrar Nova Obra
            </h2>
            <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1.75rem;">
                Preencha os metadados da obra. Após salvar, você poderá enviar as fotografias para o Supabase Storage.
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                <input type="hidden" name="action" value="create_artwork">

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Título da Obra *</label>
                        <input type="text" name="name" class="f-input" placeholder="Ex: Crepúsculo Dourado" required>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Código no Acervo</label>
                        <input type="text" name="code" class="f-input" placeholder="Ex: ASF-2045">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Artista Cadastrado <small>(seleção)</small></label>
                        <select name="artist_id" class="f-input">
                            <option value="">Selecione um Artista...</option>
                            <?php foreach ($allArtists as $art): ?>
                                <option value="<?= $art['id'] ?>"><?= htmlspecialchars($art['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Nome do Artista <small>(opcional)</small></label>
                        <input type="text" name="artist_name" class="f-input" placeholder="Ou digite o nome do artista">
                    </div>
                </div>

                <div class="form-grid-2">

                    <div class="f-group">
                        <label class="f-label">Categoria Cadastrada <small>(seleção)</small></label>
                        <?php if (!empty($allCategories)): ?>
                            <select name="category_id" class="f-input">
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'] ?? $cat['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Nova Categoria <small>(opcional)</small></label>
                        <input type="text" name="category_name" class="f-input" placeholder="Ou digite o nome da categoria">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Técnica</label>
                        <input type="text" name="technique" class="f-input" placeholder="Ex: Óleo sobre tela" value="Óleo sobre tela">
                    </div>

                    <div class="f-group">
                        <label class="f-label">Ano de Criação</label>
                        <input type="number" name="year" class="f-input" value="<?= date('Y') ?>" min="1800" max="2100">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Unidade de Medida *</label>
                        <select name="dimension_unit" id="modal_dimension_unit" class="f-input">
                            <option value="cm">Centímetros (cm)</option>
                            <option value="m">Metros (m)</option>
                        </select>
                    </div>

                    <div class="f-group">
                        <label class="f-label" id="lblModalDepth">Profundidade (cm)</label>
                        <input type="number" step="0.01" name="depth" id="modal_depth" class="f-input" placeholder="Ex: 5 (opcional)">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label" id="lblModalHeight">Altura (cm) *</label>
                        <input type="number" step="0.01" name="height" id="modal_height" class="f-input" placeholder="Ex: 100" required>
                    </div>

                    <div class="f-group">
                        <label class="f-label" id="lblModalWidth">Largura (cm) *</label>
                        <input type="number" step="0.01" name="width" id="modal_width" class="f-input" placeholder="Ex: 80" required>
                    </div>
                </div>

                    <div class="f-group">
                        <label class="f-label">Disponibilidade</label>
                        <select name="availability" class="f-input">
                            <option value="available">Disponível</option>
                            <option value="reserved">Reservada</option>
                            <option value="sold">Vendida</option>
                        </select>
                    </div>
                </div>

                <div class="f-group" style="margin-bottom: 1.25rem;">
                    <label class="f-label">Descrição / Ensaio Curatorial</label>
                    <textarea name="description" class="f-input" rows="3" placeholder="Contexto histórico, técnica e relevância artística..."></textarea>
                </div>

                <div class="f-group" style="margin-bottom: 1.75rem;">
                    <label class="f-checkbox-row">
                        <input type="checkbox" name="featured" value="1">
                        <span>Destacar esta obra na página inicial</span>
                    </label>
                </div>

                <button type="submit" class="btn-upload-submit" style="width: 100%;">
                    Cadastrar Obra no Acervo
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================================
         MODAL 2: EDITAR DADOS DA OBRA SELECIONADA
         ======================================================== -->
    <?php if ($selectedArtwork): ?>
    <div class="admin-modal-backdrop" id="modalEditArtwork">
        <div class="admin-modal-content">
            <button type="button" class="admin-modal-close" onclick="closeModal('modalEditArtwork')">✕</button>
            
            <h2 style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 2rem; color: var(--color-gold); margin-bottom: 0.5rem;">
                Editar Dados da Obra
            </h2>
            <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1.75rem;">
                Editando: <strong><?= htmlspecialchars($selectedArtwork['titulo'] ?? '') ?></strong>
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_artwork_details">
                <input type="hidden" name="artwork_id" value="<?= $selectedArtworkId ?>">

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Título da Obra</label>
                        <input type="text" name="name" class="f-input" value="<?= htmlspecialchars($selectedArtwork['titulo'] ?? '') ?>" required>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Código no Acervo</label>
                        <input type="text" name="code" class="f-input" value="<?= htmlspecialchars($selectedArtwork['codigo'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Técnica</label>
                        <input type="text" name="technique" class="f-input" value="<?= htmlspecialchars($selectedArtwork['tecnica'] ?? '') ?>">
                    </div>

                    <div class="f-group">
                        <label class="f-label">Ano de Criação</label>
                        <input type="number" name="year" class="f-input" value="<?= htmlspecialchars($selectedArtwork['ano'] ?? 2024) ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Disponibilidade</label>
                        <select name="availability" class="f-input">
                            <?php $curAv = strtolower($selectedArtwork['disponibilidade'] ?? 'available'); ?>
                            <option value="available" <?= $curAv === 'available' ? 'selected' : '' ?>>Disponível</option>
                            <option value="reserved" <?= $curAv === 'reserved' ? 'selected' : '' ?>>Reservada</option>
                            <option value="sold" <?= $curAv === 'sold' ? 'selected' : '' ?>>Vendida</option>
                        </select>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Estado de Conservação</label>
                        <input type="text" name="conservation_state" class="f-input" value="<?= htmlspecialchars($selectedArtwork['estado_conservacao'] ?? 'Excelente') ?>">
                    </div>
                </div>

                <div class="f-group" style="margin-bottom: 1.25rem;">
                    <label class="f-label">Descrição / Ensaio Curatorial</label>
                    <textarea name="description" class="f-input" rows="3"><?= htmlspecialchars($selectedArtwork['descricao'] ?? '') ?></textarea>
                </div>

                <div class="f-group" style="margin-bottom: 1.75rem;">
                    <label class="f-checkbox-row">
                        <input type="checkbox" name="featured" value="1" <?= !empty($selectedArtwork['destaque']) ? 'checked' : '' ?>>
                        <span>Exibir em Destaque na Página Inicial</span>
                    </label>
                </div>

                <button type="submit" class="btn-upload-submit" style="width: 100%;">
                    Salvar Alterações
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================
         MODAL 3: LIGHTBOX DE ALTA RESOLUÇÃO PARA FOTOS
         ======================================================== -->
    <div class="admin-modal-backdrop" id="modalLightbox">
        <div class="admin-modal-content lightbox-modal">
            <button type="button" class="admin-modal-close" onclick="closeModal('modalLightbox')">✕</button>
            
            <div class="lightbox-stage">
                <img id="lightboxImg" src="" alt="Alta Resolução">
            </div>

            <div class="lightbox-meta">
                <div>
                    <strong id="lightboxTitle" style="color: var(--color-gold); font-size: 1rem;">Vista</strong><br>
                    <span id="lightboxPath" style="font-family: monospace; font-size: 0.75rem;">artworks/...</span>
                </div>
                <div>
                    <button type="button" class="btn-copy-chip" id="btnLightboxCopy" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        🔗 Copiar URL da Foto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast de Notificação -->
    <div class="admin-toast" id="adminToast">
        <span>✓</span>
        <span id="adminToastText">Ação executada com sucesso!</span>
    </div>

    <!-- Scripts de Interação -->
    <script src="../assets/js/supabase-config.js"></script>
    <script src="../assets/js/supabase-client.js"></script>
    <script>
    // Controle de Modais
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    // Fechar modal ao clicar fora ou com tecla ESC
    document.querySelectorAll('.admin-modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                backdrop.classList.remove('is-open');
                document.body.style.overflow = '';
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.admin-modal-backdrop.is-open').forEach(m => {
                m.classList.remove('is-open');
            });
            document.body.style.overflow = '';
        }
    });

    // Lightbox de Imagem
    let currentLightboxUrl = '';
    function openLightbox(url, title, path) {
        currentLightboxUrl = url;
        const img = document.getElementById('lightboxImg');
        const t = document.getElementById('lightboxTitle');
        const p = document.getElementById('lightboxPath');
        if (img) img.src = url;
        if (t) t.textContent = title;
        if (p) p.textContent = path || url;
        openModal('modalLightbox');
    }

    const btnLightboxCopy = document.getElementById('btnLightboxCopy');
    if (btnLightboxCopy) {
        btnLightboxCopy.addEventListener('click', () => {
            if (currentLightboxUrl) {
                copyToClipboard(currentLightboxUrl, 'URL pública da imagem copiada!');
            }
        });
    }

    // Cópia para a Área de Transferência com Toast
    function copyToClipboard(text, message) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(message || 'Copiado para a área de transferência!');
        }).catch(() => {
            prompt('Copie o link abaixo:', text);
        });
    }

    function showToast(msg) {
        const toast = document.getElementById('adminToast');
        const text = document.getElementById('adminToastText');
        if (toast && text) {
            text.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    }

    // Filtro em tempo real da lista de obras na barra lateral
    const filterInput = document.getElementById('artworkFilterInput');
    const navList = document.getElementById('artworksNavList');
    if (filterInput && navList) {
        filterInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const items = navList.querySelectorAll('.art-nav-item');
            items.forEach(item => {
                const title = item.getAttribute('data-title') || '';
                const artist = item.getAttribute('data-artist') || '';
                const code = item.getAttribute('data-code') || '';
                if (!query || title.includes(query) || artist.includes(query) || code.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Preview e Validação do Upload de Foto
    const fileInput = document.getElementById('artwork_file');
    const typeSelect = document.getElementById('image_type');
    const isPrimaryBox = document.getElementById('is_primary');
    const previewContainer = document.getElementById('fileUploadPreview');
    const previewFileName = document.getElementById('previewFileName');
    const previewFileSize = document.getElementById('previewFileSize');

    if (typeSelect && isPrimaryBox) {
        typeSelect.addEventListener('change', () => {
            if (typeSelect.value === 'principal') {
                isPrimaryBox.checked = true;
            }
        });
    }

    if (fileInput && previewContainer) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                previewContainer.style.display = 'flex';
                previewFileName.textContent = file.name;
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                previewFileSize.textContent = sizeMB + ' MB';
                if (file.size > 10 * 1024 * 1024) {
                    alert('Atenção: O arquivo possui ' + sizeMB + ' MB e excede o limite máximo permitido de 10 MB.');
                    fileInput.value = '';
                    previewContainer.style.display = 'none';
                }
            } else {
                previewContainer.style.display = 'none';
            }
        });
    }

    const uploadForm = document.getElementById('adminUploadForm');
    if (uploadForm && fileInput) {
        uploadForm.addEventListener('submit', (e) => {
            const file = fileInput.files[0];
            if (!file) return;

            const api = window.ArtSaleAPI || window.ArtSellAPI;
            if (api && typeof api.validateImageFile === 'function') {
                const val = api.validateImageFile(file);
                if (!val.valid) {
                    e.preventDefault();
                    alert(val.error);
                }
            }
        });
    }

    // Alternância dinâmica de Unidade de Medida no modal de nova obra
    const modalUnitSelect = document.getElementById('modal_dimension_unit');
    const lblModalHeight = document.getElementById('lblModalHeight');
    const lblModalWidth = document.getElementById('lblModalWidth');
    const lblModalDepth = document.getElementById('lblModalDepth');
    const inputModalHeight = document.getElementById('modal_height');
    const inputModalWidth = document.getElementById('modal_width');
    const inputModalDepth = document.getElementById('modal_depth');

    if (modalUnitSelect) {
        modalUnitSelect.addEventListener('change', function() {
            const u = this.value;
            if (lblModalHeight) lblModalHeight.textContent = `Altura (${u}) *`;
            if (lblModalWidth) lblModalWidth.textContent = `Largura (${u}) *`;
            if (lblModalDepth) lblModalDepth.textContent = `Profundidade (${u})`;
            if (u === 'm') {
                if (inputModalHeight) inputModalHeight.placeholder = 'Ex: 2.30';
                if (inputModalWidth) inputModalWidth.placeholder = 'Ex: 1.70';
                if (inputModalDepth) inputModalDepth.placeholder = 'Ex: 0.15 (opcional)';
            } else {
                if (inputModalHeight) inputModalHeight.placeholder = 'Ex: 100';
                if (inputModalWidth) inputModalWidth.placeholder = 'Ex: 80';
                if (inputModalDepth) inputModalDepth.placeholder = 'Ex: 5 (opcional)';
            }
        });
    }
    </script>
</body>
</html>
