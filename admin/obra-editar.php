<?php
/**
 * ART FOR SALE - Editar Obra & Gestão de Imagens no Storage (Painel Administrativo)
 * Arquivo: /admin/obra-editar.php
 * Slogan: "Arte que transforma espaços."
 * 
 * REGRAS IMPLEMENTADAS:
 * - Proteção server-side com Supabase Auth (profiles.role = 'admin').
 * - Edição de todos os campos da obra:
 *     Nome, Slug, Artista, Descrição, Técnica, Ano, Largura, Altura, Profundidade,
 *     Estado de conservação, Código, Categoria, Disponibilidade, Procedência,
 *     Localização, Destaque e Ativo.
 * - Disponibilidade suportada:
 *     Disponível (available), Reservada (reserved), Vendida (sold), Indisponível (unavailable).
 * - Gerenciamento completo de imagens com Supabase Storage:
 *     - Upload de novas fotografias (pastas {id}/principal/ e {id}/gallery/)
 *     - 7 tipos de vista (principal, frontal, lateral, detalhe, textura, ambiente, outras)
 *     - Definir imagem principal única
 *     - Alterar ordem de exibição (sort_order)
 *     - Excluir imagem do Storage e do banco
 *     - Copiar URL pública e caminho com 1 clique
 *     - Lightbox em alta resolução (object-fit: contain)
 * - Regra de preço: Preço numérico omitido; publicamente "Preço sob consulta".
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$artworkId = trim($_GET['id'] ?? '');
if (empty($artworkId)) {
    header('Location: obras.php');
    exit;
}

$msgSucesso = '';
$msgErro = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $msgSucesso = 'Obra cadastrada com sucesso! Você já pode gerenciar as fotografias adicionais no Storage.';
}
if (!empty($_GET['upload_error'])) {
    $msgErro = 'Aviso no upload da fotografia inicial: ' . htmlspecialchars($_GET['upload_error']) . '. Você pode tentar enviá-la novamente abaixo.';
}

// Processamento de Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $action = $_POST['action'] ?? '';

    // 1. Salvar Dados Cadastrais da Obra
    if ($action === 'update_details') {
        $dados = [
            'name'               => trim($_POST['name'] ?? ''),
            'slug'               => trim($_POST['slug'] ?? ''),
            'code'               => trim($_POST['code'] ?? ''),
            'technique'          => trim($_POST['technique'] ?? ''),
            'year'               => (int)($_POST['year'] ?? date('Y')),
            'width'              => (float)($_POST['width'] ?? 0),
            'height'             => (float)($_POST['height'] ?? 0),
            'depth'              => !empty($_POST['depth']) ? (float)$_POST['depth'] : null,
            'conservation_state' => trim($_POST['conservation_state'] ?? 'Excelente'),
            'provenance'         => trim($_POST['provenance'] ?? 'Acervo Particular'),
            'location'           => trim($_POST['location'] ?? 'Brasil'),
            'description'        => trim($_POST['description'] ?? ''),
            'availability'       => trim($_POST['availability'] ?? 'available'),
            'featured'           => !empty($_POST['featured']),
            'active'             => !empty($_POST['active']),
            'artist_id'          => !empty($_POST['artist_id']) ? $_POST['artist_id'] : null,
            'artist_name'        => trim($_POST['artist_name'] ?? ''),
            'category_id'        => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'category_name'      => trim($_POST['category_name'] ?? '')
        ];

        if (empty($dados['name'])) {
            $msgErro = 'O Nome da obra não pode ficar em branco.';
        } elseif ($dados['width'] <= 0 || $dados['height'] <= 0) {
            $msgErro = 'Informe dimensões válidas de Altura e Largura.';
        } else {
            $res = supabase_atualizar_dados_obra($artworkId, $dados, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Metadados da obra atualizados com sucesso no Supabase!';
            } else {
                $msgErro = 'Erro ao salvar alterações: ' . $res['error'];
            }
        }
    }

    // 2. Upload de Imagem para o Supabase Storage
    elseif ($action === 'upload_image') {
        $imageType = trim($_POST['image_type'] ?? 'principal');
        $isPrimary = !empty($_POST['is_primary']);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $file = $_FILES['artwork_file'] ?? null;
        $base64Data = trim($_POST['artwork_file_base64'] ?? '');
        $base64Name = trim($_POST['artwork_file_filename'] ?? 'fotografia.jpg');

        $tmpUploadPath = null;
        $origFileName = null;
        $isTempFile = false;

        if (!empty($file) && $file['error'] === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
            $val = supabase_validar_arquivo_imagem($file);
            if (!$val['valido']) {
                $msgErro = $val['erro'];
            } else {
                $tmpUploadPath = $file['tmp_name'];
                $origFileName = $file['name'];
            }
        } elseif (!empty($base64Data) && str_starts_with($base64Data, 'data:image/')) {
            $parts = explode(',', $base64Data, 2);
            if (count($parts) === 2) {
                $bin = base64_decode($parts[1]);
                if (!empty($bin)) {
                    $tmpFile = tempnam(sys_get_temp_dir(), 'obra_gal_');
                    file_put_contents($tmpFile, $bin);
                    $val = supabase_validar_arquivo_imagem(['tmp_name' => $tmpFile, 'name' => $base64Name, 'size' => strlen($bin)]);
                    if (!$val['valido']) {
                        $msgErro = 'Erro na fotografia (Base64): ' . $val['erro'];
                        @unlink($tmpFile);
                    } else {
                        $tmpUploadPath = $tmpFile;
                        $origFileName = $base64Name;
                        $isTempFile = true;
                    }
                }
            }
        } elseif (!empty($file) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $msgErro = 'A fotografia excede o limite máximo configurado no servidor (tente uma imagem de até 8MB ou formato JPG comprimido).';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $msgErro = 'O envio foi interrompido antes de ser concluído. Tente novamente.';
                    break;
                default:
                    $msgErro = 'Erro ao processar arquivo (código: ' . $file['error'] . ').';
                    break;
            }
        } else {
            $msgErro = 'Por favor, selecione um arquivo de imagem válido (JPG, PNG ou WEBP).';
        }

        if (empty($msgErro) && !empty($tmpUploadPath)) {
            $res = supabase_upload_imagem_obra(
                $tmpUploadPath,
                $artworkId,
                $origFileName ?: 'fotografia.jpg',
                $imageType,
                $isPrimary,
                $sortOrder,
                $adminToken
            );

            if ($isTempFile && file_exists($tmpUploadPath)) {
                @unlink($tmpUploadPath);
            }

            if (!empty($res['sucesso'])) {
                $msgSucesso = 'Imagem enviada, associada à obra e salva com sucesso!';
            } else {
                $msgErro = 'Erro no envio para o Storage: ' . ($res['erro'] ?? 'Falha desconhecida.');
            }
        }
    }

    // 3. Definir Imagem Principal Única
    elseif ($action === 'set_primary') {
        $imageId = trim($_POST['image_id'] ?? '');
        if (!empty($imageId)) {
            $res = supabase_definir_imagem_principal($artworkId, $imageId, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Imagem definida como Principal com sucesso!';
            } else {
                $msgErro = 'Erro ao definir principal: ' . $res['error'];
            }
        }
    }

    // 4. Alterar Ordem de Exibição da Imagem (sort_order)
    elseif ($action === 'update_image_order') {
        $imageId = trim($_POST['image_id'] ?? '');
        $newOrder = (int)($_POST['sort_order'] ?? 0);
        if (!empty($imageId)) {
            $res = supabase_atualizar_ordem_imagem($imageId, $newOrder, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Ordem da imagem atualizada com sucesso!';
            } else {
                $msgErro = 'Erro ao atualizar ordem: ' . $res['error'];
            }
        }
    }

    // 5. Excluir Imagem do Storage e Banco
    elseif ($action === 'delete_image') {
        $imageId = trim($_POST['image_id'] ?? '');
        $storagePath = trim($_POST['storage_path'] ?? '');
        if (!empty($imageId)) {
            $res = supabase_excluir_imagem_obra($imageId, $storagePath, $adminToken);
            if (empty($res['error'])) {
                $msgSucesso = 'Imagem excluída com sucesso do Storage e da galeria!';
            } else {
                $msgErro = 'Erro ao excluir imagem: ' . $res['error'];
            }
        }
    }
}

// Carrega os dados atualizados da obra
$obra = supabase_buscar_obra($artworkId, $adminToken);
if (!$obra) {
    header('Location: obras.php?msg=not_found');
    exit;
}

$galeriaAtual = !empty($obra['galeria']) ? $obra['galeria'] : [];
$allCategories = supabase_buscar_categorias_list($adminToken);
$allArtists = supabase_buscar_artistas_list($adminToken);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Obra: <?= htmlspecialchars($obra['titulo']) ?> — Art For Sale</title>
    
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

        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-back {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.55rem 1rem;
            border-radius: 4px;
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-back:hover {
            color: var(--text-main);
            border-color: var(--color-gold);
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

        .alert-success { background: rgba(46, 125, 50, 0.15); border: 1px solid #2e7d32; color: #81c784; }
        .alert-error { background: rgba(201, 59, 59, 0.15); border: 1px solid #c93b3b; color: #ff9999; }

        /* Layout Grid Duas Colunas */
        .edit-layout-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2.5rem;
            align-items: start;
        }

        @media (max-width: 1080px) {
            .edit-layout-grid { grid-template-columns: 1fr; }
        }

        .panel-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .panel-card-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.5rem;
            color: var(--color-gold);
            margin-bottom: 1.25rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 600px) {
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
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
            background-color: #0b0a09;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.8rem 1rem;
            color: #ffffff;
            font-family: inherit;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }

        .f-input:focus {
            border-color: var(--color-gold);
            box-shadow: 0 0 0 3px rgba(179, 138, 84, 0.2);
        }

        .f-checkbox-card {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .f-checkbox-card:hover { border-color: var(--border-gold); }

        .f-checkbox-card input {
            accent-color: var(--color-gold);
            width: 17px;
            height: 17px;
        }

        .f-checkbox-text strong {
            display: block;
            font-size: 0.8125rem;
            color: var(--text-main);
        }

        .f-checkbox-text span {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .banner-price-policy {
            background: rgba(179, 138, 84, 0.08);
            border: 1px solid var(--border-gold);
            border-radius: 6px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .banner-price-policy strong { color: var(--color-gold); }

        .btn-submit-save {
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

        .btn-submit-save:hover {
            background-color: var(--color-gold-hover);
        }

        /* Grade de Imagens no Storage */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.15rem;
            margin-bottom: 2rem;
        }

        .photo-card {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s;
            position: relative;
        }

        .photo-card.is-primary {
            border-color: var(--color-gold);
            box-shadow: 0 0 16px rgba(179, 138, 84, 0.25);
        }

        .photo-stage {
            width: 100%;
            height: 160px;
            background-color: #080706;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
        }

        .photo-stage img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.2s;
        }

        .photo-stage:hover img { transform: scale(1.04); }

        .badge-primary-star {
            position: absolute;
            top: 6px;
            left: 6px;
            background: var(--color-gold);
            color: #ffffff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            z-index: 2;
        }

        .badge-type-label {
            position: absolute;
            bottom: 6px;
            right: 6px;
            background: rgba(14, 13, 11, 0.9);
            color: #ffffff;
            font-size: 0.6875rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 2;
        }

        .photo-meta-box {
            padding: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            flex-grow: 1;
        }

        .photo-type-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .photo-storage-path {
            font-size: 0.6875rem;
            color: var(--text-muted);
            word-break: break-all;
            background: rgba(0,0,0,0.3);
            padding: 0.25rem 0.45rem;
            border-radius: 3px;
            font-family: monospace;
        }

        .photo-copy-links-row {
            display: flex;
            gap: 0.35rem;
            margin-top: 0.25rem;
        }

        .btn-copy-chip {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.6875rem;
            padding: 0.25rem 0.45rem;
            border-radius: 3px;
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

        .order-form-row {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.35rem;
            padding-top: 0.35rem;
            border-top: 1px dashed var(--border-color);
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .order-input {
            width: 45px;
            background: #000;
            color: #fff;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            padding: 0.2rem 0.35rem;
            font-size: 0.72rem;
            text-align: center;
        }

        .btn-order-save {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.2rem 0.45rem;
            border-radius: 3px;
            font-size: 0.6875rem;
            cursor: pointer;
        }

        .btn-order-save:hover {
            color: var(--color-gold);
            border-color: var(--border-gold);
        }

        .photo-actions-row {
            margin-top: auto;
            padding-top: 0.65rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.45rem;
        }

        .btn-make-primary {
            flex: 1;
            background: transparent;
            border: 1px solid var(--border-gold);
            color: var(--color-gold);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.4rem;
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
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-del-photo:hover {
            background: var(--color-danger);
            color: #ffffff;
        }

        /* Upload Box */
        .upload-card-box {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-gold);
            border-radius: 8px;
            padding: 1.75rem;
        }

        .upload-header-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--color-gold);
            margin-bottom: 0.25rem;
        }

        .upload-header-sub {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem;
        }

        /* Lightbox Modal */
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

        .admin-modal-backdrop.is-open { display: flex; }

        .lightbox-modal-content {
            background: #181613;
            border: 1px solid var(--border-gold);
            border-radius: 8px;
            max-width: 900px;
            width: 100%;
            padding: 1.5rem;
            position: relative;
        }

        .admin-modal-close {
            position: absolute;
            top: 1rem;
            right: 1.2rem;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .admin-modal-close:hover { color: #ffffff; }

        .lightbox-stage {
            max-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .lightbox-stage img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
        }

        /* Toast */
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

        .admin-toast.show { transform: translateY(0); opacity: 1; }
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
            <span class="badge-role">Admin • Editar Obra</span>
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

    <!-- Barra de Navegação -->
    <nav class="admin-tabs-bar">
        <a href="index.php?tab=dashboard" class="tab-btn">
            <span>📊 Dashboard Geral</span>
        </a>
        <a href="obras.php" class="tab-btn">
            <span>🖼️ Catálogo de Obras</span>
        </a>
        <a href="categorias.php" class="tab-btn">
            <span>📁 Categorias</span>
        </a>
        <a href="artistas.php" class="tab-btn">
            <span>🎨 Artistas</span>
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

        <!-- Cabeçalho -->
        <div class="page-header-row">
            <div>
                <h1 class="section-headline"><?= htmlspecialchars($obra['titulo']) ?></h1>
                <p class="section-subhead">
                    Código: <code><?= htmlspecialchars($obra['codigo'] ?? 'ASF-0000') ?></code> • 
                    Artista: <strong><?= htmlspecialchars($obra['artista']) ?></strong> • 
                    Status: <strong style="color: var(--color-gold);"><?= htmlspecialchars(ucfirst($obra['disponibilidade'] ?? 'available')) ?></strong>
                </p>
            </div>
            
            <div class="header-actions-group">
                <a href="obras.php" class="btn-back">← Catálogo de Obras</a>
                <a href="../pages/obra.php?id=<?= $obra['id'] ?>" target="_blank" class="btn-link-site">
                    Ver no Site Público ↗
                </a>
            </div>
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

        <div class="edit-layout-grid">
            
            <!-- Coluna 1: Formulário de Metadados -->
            <div class="panel-card">
                <h2 class="panel-card-title">
                    <span>Metadados da Obra</span>
                </h2>

                <div class="banner-price-policy">
                    <strong>Política de Preço:</strong> Esta obra é apresentada publicamente com a indicação <em>"Preço sob consulta"</em>, sem valor numérico.
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_details">

                    <div class="form-grid-2">
                        <div class="f-group">
                            <label for="name" class="f-label">Nome da Obra *</label>
                            <input 
                                type="text" 
                                name="name" 
                                id="name" 
                                class="f-input" 
                                required
                                value="<?= htmlspecialchars($obra['titulo']) ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="code" class="f-label">Código no Acervo</label>
                            <input 
                                type="text" 
                                name="code" 
                                id="code" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['codigo'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="f-group">
                            <label for="slug" class="f-label">Slug da URL</label>
                            <input 
                                type="text" 
                                name="slug" 
                                id="slug" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['slug'] ?? '') ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="technique" class="f-label">Técnica *</label>
                            <input 
                                type="text" 
                                name="technique" 
                                id="technique" 
                                class="f-input" 
                                required
                                value="<?= htmlspecialchars($obra['tecnica'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="f-group">
                            <label for="artist_id" class="f-label">Artista Cadastrado <small>(seleção)</small></label>
                            <select name="artist_id" id="artist_id" class="f-input">
                                <option value="">Selecione o Artista...</option>
                                <?php foreach ($allArtists as $art): ?>
                                    <option value="<?= $art['id'] ?>" <?= ((string)($obra['artist_id'] ?? '') === (string)$art['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($art['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="f-hint">Selecione da lista de mestres cadastrados.</span>
                        </div>

                        <div class="f-group">
                            <label for="artist_name" class="f-label">Alterar Nome do Artista <small>(opcional)</small></label>
                            <input 
                                type="text" 
                                name="artist_name" 
                                id="artist_name" 
                                class="f-input" 
                                placeholder="Ou digite novo nome de artista..."
                            >
                            <span class="f-hint">Atualiza ou cria novo artista no acervo se preenchido.</span>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="f-group">
                            <label for="category_id" class="f-label">Categoria / Estilo</label>
                            <select name="category_id" id="category_id" class="f-input">
                                <option value="">Selecione a Categoria...</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ((string)($obra['category_id'] ?? '') === (string)$cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name'] ?? $cat['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f-group">
                            <label for="category_name" class="f-label">Nova Categoria <small>(opcional)</small></label>
                            <input 
                                type="text" 
                                name="category_name" 
                                id="category_name" 
                                class="f-input" 
                                placeholder="Ou digite nova categoria..."
                            >
                            <span class="f-hint">Mantém a categoria atual se não informada.</span>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="f-group">
                            <label for="height" class="f-label">Altura (cm) *</label>
                            <input 
                                type="number" 
                                step="0.1" 
                                name="height" 
                                id="height" 
                                class="f-input" 
                                required
                                value="<?= htmlspecialchars($obra['altura'] ?? '') ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="width" class="f-label">Largura (cm) *</label>
                            <input 
                                type="number" 
                                step="0.1" 
                                name="width" 
                                id="width" 
                                class="f-input" 
                                required
                                value="<?= htmlspecialchars($obra['largura'] ?? '') ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="depth" class="f-label">Profundidade (cm)</label>
                            <input 
                                type="number" 
                                step="0.1" 
                                name="depth" 
                                id="depth" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['profundidade'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="f-group">
                            <label for="year" class="f-label">Ano de Criação</label>
                            <input 
                                type="number" 
                                name="year" 
                                id="year" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['ano'] ?? 2024) ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="conservation_state" class="f-label">Conservação</label>
                            <input 
                                type="text" 
                                name="conservation_state" 
                                id="conservation_state" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['estado_conservacao'] ?? 'Excelente') ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="availability" class="f-label">Disponibilidade</label>
                            <?php $curAv = strtolower($obra['disponibilidade'] ?? 'available'); ?>
                            <select name="availability" id="availability" class="f-input">
                                <option value="available" <?= ($curAv === 'available' || $curAv === 'disponível') ? 'selected' : '' ?>>Disponível</option>
                                <option value="reserved" <?= ($curAv === 'reserved' || $curAv === 'reservada') ? 'selected' : '' ?>>Reservada</option>
                                <option value="sold" <?= ($curAv === 'sold' || $curAv === 'vendida') ? 'selected' : '' ?>>Vendida</option>
                                <option value="unavailable" <?= ($curAv === 'unavailable' || $curAv === 'indisponível') ? 'selected' : '' ?>>Indisponível</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="f-group">
                            <label for="provenance" class="f-label">Procedência</label>
                            <input 
                                type="text" 
                                name="provenance" 
                                id="provenance" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['procedencia'] ?? 'Acervo Particular') ?>"
                            >
                        </div>

                        <div class="f-group">
                            <label for="location" class="f-label">Localização</label>
                            <input 
                                type="text" 
                                name="location" 
                                id="location" 
                                class="f-input" 
                                value="<?= htmlspecialchars($obra['localizacao'] ?? 'Brasil') ?>"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2" style="margin-top: 0.5rem; margin-bottom: 1.5rem;">
                        <label class="f-checkbox-card">
                            <input type="checkbox" name="active" value="1" <?= !empty($obra['ativo']) ? 'checked' : '' ?>>
                            <div class="f-checkbox-text">
                                <strong>Obra Ativa no Catálogo</strong>
                                <span>Publicada nas páginas do site.</span>
                            </div>
                        </label>

                        <label class="f-checkbox-card">
                            <input type="checkbox" name="featured" value="1" <?= !empty($obra['destaque']) ? 'checked' : '' ?>>
                            <div class="f-checkbox-text">
                                <strong>Destaque na Página Inicial</strong>
                                <span>Aparece no carrossel de destaques da home.</span>
                            </div>
                        </label>
                    </div>

                    <div class="f-group" style="margin-bottom: 1.75rem;">
                        <label for="description" class="f-label">Ensaio Curatorial / Descrição</label>
                        <textarea 
                            name="description" 
                            id="description" 
                            class="f-input" 
                            rows="4"
                        ><?= htmlspecialchars($obra['descricao'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit-save">
                        Salvar Alterações nos Metadados
                    </button>
                </form>
            </div>

            <!-- Coluna 2: Gerenciamento de Fotografias no Supabase Storage -->
            <div>
                
                <div class="panel-card">
                    <h2 class="panel-card-title">
                        <span>Fotografias no Storage (<?= count($galeriaAtual) ?>)</span>
                    </h2>

                    <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                        Bucket: <code>artworks</code> • Pastas: <code><?= $obra['id'] ?>/principal/</code> e <code><?= $obra['id'] ?>/gallery/</code>.<br>
                        A foto marcada como <strong style="color: var(--color-gold);">Principal</strong> será usada na capa dos cards.
                    </p>

                    <?php if (empty($galeriaAtual)): ?>
                        <div style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); background: rgba(0,0,0,0.25); border-radius: 6px; margin-bottom: 2rem;">
                            Nenhuma fotografia enviada para esta obra no Supabase Storage.
                        </div>
                    <?php else: ?>
                        <div class="photos-grid">
                            <?php foreach ($galeriaAtual as $img): 
                                $isPrimary = !empty($img['is_primary']) || ($img['tipo'] ?? '') === 'principal';
                                $imgTipo = $img['tipo'] ?? 'principal';
                                $tipoNome = supabase_legenda_tipo_imagem($imgTipo);
                                $imgUrl = $img['imagem'];
                                $storagePath = $img['storage_path'] ?? '';
                            ?>
                                <div class="photo-card <?= $isPrimary ? 'is-primary' : '' ?>">
                                    <?php 
                                    $resolvedImgUrl = artsale_resolve_image_url($imgUrl, '../'); 
                                    $resolvedThumbUrl = artsale_get_thumbnail_url($imgUrl, 320, 240, 80, '../');
                                    ?>
                                    <div class="photo-stage" onclick="openLightbox('<?= htmlspecialchars($resolvedImgUrl) ?>', '<?= htmlspecialchars($tipoNome) ?>', '<?= htmlspecialchars($storagePath) ?>')">
                                        <img 
                                            src="<?= htmlspecialchars($resolvedThumbUrl) ?>" 
                                            alt="<?= htmlspecialchars($tipoNome) ?>" 
                                            width="200"
                                            height="150"
                                            loading="lazy"
                                            decoding="async"
                                            onerror="if(!this.src.endsWith('.svg')) this.src='../assets/images/obras/placeholder-obra.svg';"
                                        >
                                        
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

                                        <!-- Chips para copiar link e path -->
                                        <div class="photo-copy-links-row">
                                            <button 
                                                type="button" 
                                                class="btn-copy-chip" 
                                                onclick="copyToClipboard('<?= htmlspecialchars($imgUrl) ?>', 'URL pública da imagem copiada!')"
                                                title="Copiar URL pública direta da foto"
                                            >
                                                🔗 Copiar URL
                                            </button>

                                            <?php if (!empty($storagePath)): ?>
                                                <button 
                                                    type="button" 
                                                    class="btn-copy-chip" 
                                                    onclick="copyToClipboard('<?= htmlspecialchars($storagePath) ?>', 'Caminho no Storage copiado!')"
                                                    title="Copiar caminho no bucket"
                                                >
                                                    📁 Caminho
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Alteração de Ordem de Exibição -->
                                        <?php if (!empty($img['id'])): ?>
                                            <form method="POST" class="order-form-row">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_image_order">
                                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                <span>Ordem:</span>
                                                <input type="number" name="sort_order" class="order-input" value="<?= (int)($img['sort_order'] ?? 0) ?>" min="0">
                                                <button type="submit" class="btn-order-save" title="Salvar nova ordem">OK</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Ações da Foto -->
                                        <div class="photo-actions-row">
                                            <?php if (!$isPrimary && !empty($img['id'])): ?>
                                                <form method="POST" style="flex: 1;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="set_primary">
                                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                    <button type="submit" class="btn-make-primary" title="Definir como a única imagem principal">
                                                        Tornar Principal
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (!empty($img['id'])): ?>
                                                <form method="POST" onsubmit="return confirm('Deseja realmente excluir esta fotografia do Storage?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete_image">
                                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                    <input type="hidden" name="storage_path" value="<?= htmlspecialchars($storagePath) ?>">
                                                    <button type="submit" class="btn-del-photo" title="Excluir">✕</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulário de Upload para o Storage -->
                    <div class="upload-card-box">
                        <h3 class="upload-header-title">Enviar Nova Imagem para o Storage</h3>
                        <p class="upload-header-sub">
                            Extensões: <strong>JPG, JPEG, PNG, WEBP</strong> • Limite máximo: <strong>10 MB</strong>
                        </p>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                            <input type="hidden" name="action" value="upload_image">

                            <div class="form-grid-2">
                                <div class="f-group">
                                    <label class="f-label">Arquivo de Fotografia *</label>
                                    <input 
                                        type="file" 
                                        name="artwork_file" 
                                        id="artwork_file"
                                        class="f-input" 
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                                        required
                                    >
                                    <input type="hidden" name="artwork_file_base64" id="artwork_file_base64" value="">
                                    <input type="hidden" name="artwork_file_filename" id="artwork_file_filename" value="">

                                    <div id="editImagePreviewBox" style="display: none; margin-top: 0.5rem; align-items: center; gap: 0.75rem; background: #0c0b0a; padding: 0.5rem 0.75rem; border: 1px solid var(--border-gold); border-radius: 4px;">
                                        <img id="editImagePreviewImg" src="" alt="Prévia" style="width: 44px; height: 44px; object-fit: cover; border-radius: 3px; border: 1px solid var(--color-gold);">
                                        <div>
                                            <span id="editImagePreviewName" style="display: block; font-size: 0.75rem; color: var(--text-main); font-weight: 600;"></span>
                                            <span style="font-size: 0.6875rem; color: var(--color-gold);">✓ Fotografia pronta para envio</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="f-group">
                                    <label class="f-label">Tipo de Vista *</label>
                                    <select name="image_type" class="f-input">
                                        <option value="principal">Imagem Principal (Capa)</option>
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
                                    <label class="f-label">Ordem de Exibição</label>
                                    <input 
                                        type="number" 
                                        name="sort_order" 
                                        class="f-input" 
                                        value="<?= count($galeriaAtual) + 1 ?>" 
                                        min="0"
                                    >
                                </div>

                                <div class="f-group" style="justify-content: center;">
                                    <label class="f-checkbox-card" style="padding: 0.65rem 0.85rem;">
                                        <input type="checkbox" name="is_primary" value="1" <?= empty($galeriaAtual) ? 'checked' : '' ?>>
                                        <div class="f-checkbox-text">
                                            <strong>Definir como Principal</strong>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit-save" style="width: 100%;">
                                Enviar Fotografia para o Storage
                            </button>
                        </form>
                    </div>

                </div>

            </div>

        </div>

    </main>

    <!-- Modal Lightbox -->
    <div class="admin-modal-backdrop" id="modalLightbox">
        <div class="lightbox-modal-content">
            <button type="button" class="admin-modal-close" onclick="closeLightbox()">✕</button>
            <div class="lightbox-stage">
                <img id="lightboxImg" src="" alt="Alta Resolução">
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; color: var(--text-muted);">
                <div>
                    <strong id="lightboxTitle" style="color: var(--color-gold);">Vista</strong><br>
                    <span id="lightboxPath" style="font-family: monospace; font-size: 0.72rem;"></span>
                </div>
                <button type="button" class="btn-copy-chip" id="btnLightboxCopy" style="padding: 0.4rem 0.8rem;">
                    🔗 Copiar URL da Foto
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="admin-toast" id="adminToast">
        <span>✓</span>
        <span id="adminToastText">Ação realizada com sucesso!</span>
    </div>

    <script>
    // Lightbox Modal
    let currentLightboxUrl = '';
    function openLightbox(url, title, path) {
        currentLightboxUrl = url;
        document.getElementById('lightboxImg').src = url;
        document.getElementById('lightboxTitle').textContent = title;
        document.getElementById('lightboxPath').textContent = path || url;
        document.getElementById('modalLightbox').classList.add('is-open');
    }

    function closeLightbox() {
        document.getElementById('modalLightbox').classList.remove('is-open');
    }

    document.getElementById('modalLightbox').addEventListener('click', (e) => {
        if (e.target.id === 'modalLightbox') closeLightbox();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
    });

    document.getElementById('btnLightboxCopy').addEventListener('click', () => {
        if (currentLightboxUrl) copyToClipboard(currentLightboxUrl, 'URL pública da imagem copiada!');
    });

    // Copiar para a Área de Transferência com Toast
    function copyToClipboard(text, msg) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(msg || 'Copiado para a área de transferência!');
        }).catch(() => {
            prompt('Copie o endereço abaixo:', text);
        });
    }

    function showToast(text) {
        const toast = document.getElementById('adminToast');
        document.getElementById('adminToastText').textContent = text;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // Leitura, validação e preview imediato da fotografia da galeria
    const artFileInput = document.getElementById('artwork_file');
    const artFileB64 = document.getElementById('artwork_file_base64');
    const artFileFn = document.getElementById('artwork_file_filename');
    const artFileBox = document.getElementById('editImagePreviewBox');
    const artFileImg = document.getElementById('editImagePreviewImg');
    const artFileName = document.getElementById('editImagePreviewName');

    if (artFileInput) {
        artFileInput.addEventListener('change', () => {
            const file = artFileInput.files[0];
            if (file) {
                if (file.size > 15 * 1024 * 1024) {
                    alert('A fotografia selecionada excede o limite de 15 MB.');
                    artFileInput.value = '';
                    if (artFileB64) artFileB64.value = '';
                    if (artFileBox) artFileBox.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    if (artFileB64) artFileB64.value = e.target.result;
                    if (artFileFn) artFileFn.value = file.name;
                    if (artFileImg) artFileImg.src = e.target.result;
                    if (artFileName) artFileName.textContent = file.name + ' (' + (file.size / (1024*1024)).toFixed(2) + ' MB)';
                    if (artFileBox) artFileBox.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            } else {
                if (artFileB64) artFileB64.value = '';
                if (artFileBox) artFileBox.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>
