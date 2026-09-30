<?php
/**
 * ART FOR SALE - Gestão dos Banners do Hero (Painel Administrativo)
 * Arquivo: /admin/hero-slides.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Permite ao curador/administrador:
 * - Trocar as 3 imagens dos slides do Hero da página inicial
 * - Fazer upload de novas imagens do computador (JPG, PNG, WEBP até 10 MB)
 * - Informar URL de imagem externa ou Supabase Storage
 * - Escolher imagens pré-existentes da galeria
 * - Editar títulos, subtítulos (eyebrows), descrições e citações
 * - Visualizar preview ao vivo com o gradiente do Hero
 * - Restaurar os padrões originais com 1 clique
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$msgSucesso = '';
$msgErro = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $msgSucesso = 'Banners e conteúdos do Hero atualizados com sucesso!';
    } elseif ($_GET['msg'] === 'reset') {
        $msgSucesso = 'Banners do Hero restaurados para os padrões originais da galeria!';
    }
}

/**
 * Função de upload de imagem para o banner do Hero
 */
function admin_upload_hero_banner(array $file, int $slideId, ?string $token = null): array {
    if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return ['sucesso' => false, 'erro' => 'Nenhum arquivo enviado.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'erro' => 'Erro no envio do arquivo: código ' . $file['error']];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['sucesso' => false, 'erro' => 'O arquivo excede o limite máximo permitido de 10 MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas, true)) {
        return ['sucesso' => false, 'erro' => 'Extensão não permitida. Use JPG, JPEG, PNG ou WEBP.'];
    }

    if (function_exists('getimagesize')) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo === false) {
            return ['sucesso' => false, 'erro' => 'O arquivo enviado não é uma imagem válida.'];
        }
    }

    $timestamp = time();
    $rawName = pathinfo($file['name'], PATHINFO_FILENAME);
    $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($rawName));
    $finalFilename = "hero-slide-{$slideId}_{$timestamp}_{$cleanName}.{$ext}";

    // Lê os dados do arquivo a partir de tmp_name
    $fileData = @file_get_contents($file['tmp_name']);
    if (empty($fileData)) {
        return ['sucesso' => false, 'erro' => 'Não foi possível ler o arquivo temporário.'];
    }

    $mimeType = 'image/jpeg';
    if ($ext === 'png') $mimeType = 'image/png';
    elseif ($ext === 'webp') $mimeType = 'image/webp';

    // 1. Se Supabase Storage estiver ativo, envia prioritariamente para a nuvem (persistente no Vercel)
    if (function_exists('supabase_is_configured') && supabase_is_configured()) {
        try {
            $authToken = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
            $baseUrl = rtrim(SUPABASE_URL, '/');
            $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
            $bucket = defined('SUPABASE_STORAGE_BUCKET') ? SUPABASE_STORAGE_BUCKET : 'artworks';
            $storagePath = "site/{$finalFilename}";
            $uploadUrl = "{$baseUrl}/storage/v1/object/{$bucket}/{$storagePath}";

            $headers = [
                'apikey: ' . SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . $authToken,
                'Content-Type: ' . $mimeType,
                'x-upsert: true'
            ];

            $upRes = supabase_http_call($uploadUrl, 'POST', $headers, $fileData);

            if (empty($upRes['error']) && in_array((int)($upRes['status'] ?? 0), [200, 201], true)) {
                $publicUrl = "{$baseUrl}/storage/v1/object/public/{$bucket}/{$storagePath}";

                // Tenta gravar também no disco local se houver permissão
                $targetDir = BASE_PATH . '/assets/images/site';
                if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);
                @file_put_contents($targetDir . '/' . $finalFilename, $fileData);

                return ['sucesso' => true, 'url' => $publicUrl, 'local_path' => 'assets/images/site/' . $finalFilename];
            }
        } catch (Throwable $e) {
            // Continua para gravação local
        }
    }

    // 2. Gravação local como fallback (ambientes com disco gravável)
    $targetDir = BASE_PATH . '/assets/images/site';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }
    $targetPath = $targetDir . '/' . $finalFilename;

    if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
        if (!@copy($file['tmp_name'], $targetPath)) {
            if (!@file_put_contents($targetPath, $fileData)) {
                return ['sucesso' => false, 'erro' => 'Falha ao gravar arquivo em assets/images/site.'];
            }
        }
    }

    return ['sucesso' => true, 'url' => 'assets/images/site/' . $finalFilename, 'local_path' => 'assets/images/site/' . $finalFilename];
}

// Processamento de Ações POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // Ação: Restaurar Padrões
    if ($action === 'reset_defaults') {
        $defaultSlides = [
            [
                'id' => 1,
                'image' => 'assets/images/site/hero-bg.jpg',
                'eyebrow' => 'GALERIA DE ARTE',
                'title' => 'Arte que<br>transforma<br>espaços.',
                'description' => 'Descubra obras únicas e cuidadosamente selecionadas para colecionadores, apreciadores e ambientes que merecem personalidade.',
                'quote' => 'Mais que quadros, histórias que ganham vida no seu espaço.',
                'cta_primary_text' => 'Explorar obras',
                'cta_primary_url' => 'pages/obras.php',
                'cta_secondary_text' => 'Conhecer a Galeria',
                'cta_secondary_url' => '#sobre'
            ],
            [
                'id' => 2,
                'image' => 'assets/images/site/about-art-gallery.jpg',
                'eyebrow' => 'CURADORIA EXCLUSIVA',
                'title' => 'Coleções<br>únicas com<br>personalidade.',
                'description' => 'Pinturas a óleo, gravuras históricas e esculturas nobres selecionadas para elevar o design de interiores a outro patamar.',
                'quote' => 'A beleza clássica e contemporânea em perfeita harmonia.',
                'cta_primary_text' => 'Explorar obras',
                'cta_primary_url' => 'pages/obras.php',
                'cta_secondary_text' => 'Conhecer a Galeria',
                'cta_secondary_url' => '#sobre'
            ],
            [
                'id' => 3,
                'image' => 'assets/images/site/about-art-sell.jpg',
                'eyebrow' => 'ACERVO PRIVADO',
                'title' => 'Obras de arte<br>que contam<br>histórias.',
                'description' => 'Atendimento e consultoria especializada para encontrar a peça perfeita para sua residência, escritório ou coleção particular.',
                'quote' => 'Cada pincelada carrega uma emoção eterna e autêntica.',
                'cta_primary_text' => 'Explorar obras',
                'cta_primary_url' => 'pages/obras.php',
                'cta_secondary_text' => 'Conhecer a Galeria',
                'cta_secondary_url' => '#sobre'
            ]
        ];

        artsale_save_hero_slides($defaultSlides);
        header('Location: hero-slides.php?msg=reset');
        exit;
    }

    // Ação: Salvar Banners (todos ou individual)
    if ($action === 'save_slides') {
        $currentSlides = artsale_get_hero_slides(true);
        $updatedSlides = [];

        for ($i = 0; $i < 3; $i++) {
            $slideNum = $i + 1;
            $oldSlide = $currentSlides[$i] ?? [];

            // Resgata possíveis fontes de imagem (select de obra, url digitada ou upload)
            $artworkSelectVal = trim($_POST["artwork_select_{$slideNum}"] ?? '');
            $imageUrlVal = trim($_POST["image_url_{$slideNum}"] ?? '');

            $finalImage = '';
            // Se o usuário selecionou uma obra do acervo
            if (!empty($artworkSelectVal)) {
                $finalImage = $artworkSelectVal;
            } elseif (!empty($imageUrlVal)) {
                $finalImage = $imageUrlVal;
            } else {
                $finalImage = $oldSlide['image'] ?? 'assets/images/site/hero-bg.jpg';
            }

            if (!empty($_FILES["slide_file_{$slideNum}"]) && !empty($_FILES["slide_file_{$slideNum}"]['tmp_name'])) {
                $uploadRes = admin_upload_hero_banner($_FILES["slide_file_{$slideNum}"], $slideNum, $adminToken);
                if (!empty($uploadRes['sucesso'])) {
                    $finalImage = $uploadRes['url'];
                } else {
                    $msgErro .= "Slide {$slideNum}: " . ($uploadRes['erro'] ?? 'Erro no upload') . " | ";
                }
            }

            // Fallback caso a imagem fique vazia
            if (empty($finalImage)) {
                $finalImage = $oldSlide['image'] ?? 'assets/images/site/hero-bg.jpg';
            }

            $secText = trim($_POST["cta_secondary_text_{$slideNum}"] ?? ($oldSlide['cta_secondary_text'] ?? 'Conhecer a Galeria'));
            $secUrl = trim($_POST["cta_secondary_url_{$slideNum}"] ?? ($oldSlide['cta_secondary_url'] ?? '#sobre'));
            if (stripos($secText, 'categoria') !== false) {
                $secText = 'Conhecer a Galeria';
            }
            if (stripos($secUrl, 'categoria') !== false) {
                $secUrl = '#sobre';
            }

            $updatedSlides[] = [
                'id' => $slideNum,
                'image' => $finalImage,
                'eyebrow' => trim($_POST["eyebrow_{$slideNum}"] ?? ($oldSlide['eyebrow'] ?? 'GALERIA DE ARTE')),
                'title' => trim($_POST["title_{$slideNum}"] ?? ($oldSlide['title'] ?? 'Arte que<br>transforma<br>espaços.')),
                'description' => trim($_POST["description_{$slideNum}"] ?? ($oldSlide['description'] ?? '')),
                'quote' => trim($_POST["quote_{$slideNum}"] ?? ($oldSlide['quote'] ?? '')),
                'cta_primary_text' => trim($_POST["cta_primary_text_{$slideNum}"] ?? ($oldSlide['cta_primary_text'] ?? 'Explorar obras')),
                'cta_primary_url' => trim($_POST["cta_primary_url_{$slideNum}"] ?? ($oldSlide['cta_primary_url'] ?? 'pages/obras.php')),
                'cta_secondary_text' => $secText,
                'cta_secondary_url' => $secUrl,
            ];
        }

        if (empty($msgErro)) {
            artsale_save_hero_slides($updatedSlides);
            header('Location: hero-slides.php?msg=saved');
            exit;
        }
    }
}

// Carrega os 3 slides atuais
$slides = artsale_get_hero_slides(true);
$currentTab = 'hero_slides';

// Carrega obras ativas do catálogo do site para seleção no Hero
$artworksList = [];
if (function_exists('supabase_is_configured') && supabase_is_configured()) {
    $artworksList = supabase_buscar_obras(null, null, 250, false, $adminToken);
}
if (empty($artworksList)) {
    global $catalogoObras;
    $artworksList = $catalogoObras ?? [];
}
$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
if (!empty($deletedIds)) {
    $artworksList = array_values(array_filter($artworksList, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));
}

$siteArtworks = [];
foreach ($artworksList as $item) {
    $imgRaw = $item['imagem'] ?? $item['image_url'] ?? $item['foto'] ?? '';
    if (empty($imgRaw)) continue;
    $titulo = $item['titulo'] ?? $item['title'] ?? 'Sem título';
    $artista = $item['artista'] ?? $item['artist'] ?? 'Artista não informado';
    $categoria = $item['categoria'] ?? $item['category'] ?? '';
    $id = (string)($item['id'] ?? '');

    $previewUrl = artsale_resolve_image_url($imgRaw, '../');
    $thumbUrl = artsale_get_thumbnail_url($imgRaw, 320, 240, 80, '../');

    $siteArtworks[] = [
        'id' => $id,
        'titulo' => $titulo,
        'artista' => $artista,
        'categoria' => $categoria,
        'raw_url' => $imgRaw,
        'preview_url' => $previewUrl,
        'thumb_url' => $thumbUrl
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banners do Hero (Carrossel) — Painel Art For Sale</title>
    
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
    <script src="../assets/js/admin.js"></script>

    <style>
        .hero-banner-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            transition: all 0.25s ease;
        }

        .hero-banner-card:hover {
            border-color: var(--border-gold);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .banner-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .banner-num-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--color-gold);
            background: var(--color-gold-subtle);
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            border: 1px solid rgba(179, 138, 84, 0.3);
            letter-spacing: 0.05em;
        }

        .banner-preview-box {
            position: relative;
            width: 100%;
            height: 240px;
            border-radius: 8px;
            overflow: hidden;
            background-color: #12110f;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            padding: 1.5rem 2rem;
            box-shadow: inset 0 0 100px rgba(0, 0, 0, 0.5);
        }

        .banner-preview-backdrop {
            position: absolute;
            inset: -20px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(25px) brightness(0.32) saturate(1.2);
            transform: scale(1.1);
            pointer-events: none;
            z-index: 1;
        }

        .banner-preview-artwork {
            position: absolute;
            inset: 0;
            background-size: contain;
            background-position: center center;
            background-repeat: no-repeat;
            pointer-events: none;
            z-index: 2;
            transform: scale(0.85);
        }

        .banner-preview-overlay {
            position: absolute;
            inset: 0;
            z-index: 3;
            background: linear-gradient(
                to right,
                rgba(10, 9, 8, 0.90) 0%,
                rgba(10, 9, 8, 0.75) 45%,
                rgba(10, 9, 8, 0.40) 75%,
                rgba(10, 9, 8, 0.70) 100%
            );
            pointer-events: none;
        }

        .banner-preview-content {
            position: relative;
            z-index: 4;
            color: #ffffff;
            max-width: 60%;
        }

        .preview-eyebrow {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            color: #c8a96e;
            text-transform: uppercase;
            display: block;
            margin-bottom: 0.35rem;
        }

        .preview-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.65rem;
            font-weight: 500;
            line-height: 1.15;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .preview-desc {
            font-size: 0.8rem;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.8);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .banner-preview-quote {
            position: absolute;
            right: 2rem;
            bottom: 1.5rem;
            max-width: 200px;
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 0.85rem;
            font-style: italic;
            color: #eae5dc;
            border-left: 2px solid #c8a96e;
            padding-left: 0.65rem;
            z-index: 4;
        }

        .grid-fields-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.45rem;
            color: var(--text-main);
        }

        .form-group .form-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: var(--bg-card);
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-gold);
            box-shadow: 0 0 0 3px var(--color-gold-subtle);
        }

        .file-upload-dropzone {
            border: 2px dashed var(--border-gold);
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            background-color: var(--bg-card-alt);
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.75rem;
        }

        .file-upload-dropzone:hover {
            border-color: var(--color-gold);
            background-color: var(--color-gold-subtle);
        }

        .preset-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .btn-preset {
            background: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.35rem 0.65rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-preset:hover {
            border-color: var(--color-gold);
            color: var(--color-gold);
        }

        .actions-bottom-bar {
            position: sticky;
            bottom: 0;
            background-color: var(--bg-topbar);
            border-top: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05);
            z-index: 50;
            border-radius: 8px;
            margin-top: 2rem;
        }

        .btn-gold {
            background-color: var(--color-gold);
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-gold:hover {
            background-color: var(--color-gold-hover);
        }

        .btn-outline-danger {
            background: transparent;
            color: var(--color-danger);
            border: 1px solid var(--color-danger);
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-outline-danger:hover {
            background: rgba(201, 59, 59, 0.1);
        }

        .image-sources-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-top: 0.75rem;
        }

        .image-source-col {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.15rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .image-source-col.highlight-source {
            border-color: rgba(179, 138, 84, 0.45);
            background: linear-gradient(to bottom, var(--color-gold-subtle), var(--bg-card));
        }

        .source-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-gold);
            margin-bottom: 0.65rem;
        }

        .btn-artwork-catalog {
            width: 100%;
            background: var(--bg-card-alt);
            border: 1px solid var(--border-gold);
            color: var(--color-gold);
            padding: 0.6rem 0.75rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-artwork-catalog:hover {
            background: var(--color-gold);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(179, 138, 84, 0.25);
        }

        .selected-artwork-indicator {
            font-size: 0.78rem;
            color: var(--color-gold);
            margin-top: 0.5rem;
            padding: 0.45rem 0.65rem;
            background: var(--color-gold-subtle);
            border-radius: 6px;
            border: 1px solid rgba(179, 138, 84, 0.3);
            line-height: 1.35;
            word-break: break-word;
        }

        /* Modal Visual de Seleção de Obras */
        .modal-artwork-picker-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(6px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .modal-artwork-picker-backdrop.active {
            display: flex;
        }

        .modal-artwork-picker-container {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 12px;
            width: 100%;
            max-width: 960px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            overflow: hidden;
            animation: modalFadeIn 0.22s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-artwork-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-close-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            line-height: 1;
            transition: all 0.2s;
        }

        .modal-close-btn:hover {
            border-color: var(--color-danger);
            color: var(--color-danger);
            transform: scale(1.05);
        }

        .modal-artwork-body {
            padding: 1.25rem 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-artwork-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 1rem;
        }

        .artwork-pick-card {
            background: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .artwork-pick-card:hover {
            border-color: var(--color-gold);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .artwork-pick-thumb {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background: #111;
            display: block;
        }

        .artwork-pick-badge {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            background: rgba(0, 0, 0, 0.75);
            color: #fff;
            border-radius: 4px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .artwork-pick-info {
            padding: 0.75rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .artwork-pick-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-main);
            line-height: 1.3;
            margin-bottom: 0.25rem;
        }

        .artwork-pick-artist {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .btn-use-artwork {
            width: 100%;
            background: var(--color-gold-subtle);
            border: 1px solid var(--border-gold);
            color: var(--color-gold);
            padding: 0.45rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .artwork-pick-card:hover .btn-use-artwork {
            background: var(--color-gold);
            color: #ffffff;
        }

        @media (max-width: 1024px) {
            .image-sources-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .grid-fields-2 { grid-template-columns: 1fr; }
            .banner-preview-box { height: 180px; }
            .banner-preview-content { max-width: 100%; }
            .banner-preview-quote { display: none; }
        }
    </style>
</head>
<body>

    <!-- Topbar Oficial da Art For Sale -->
    <header class="admin-topbar">
        <div class="logo-area">
            <a href="index.php" title="Voltar ao Dashboard">
                <img src="../assets/images/site/logo.svg" alt="Art For Sale" class="admin-logo-light" width="160" height="36">
                <img src="../assets/images/site/logo-white.svg" alt="Art For Sale" class="admin-logo-dark" width="160" height="36">
            </a>
            <span class="badge-role">Admin • Curadoria</span>
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

    <!-- Barra de Abas do Painel -->
    <nav class="admin-tabs-bar">
        <a href="index.php?tab=dashboard" class="tab-btn">
            <span>📊 Dashboard Geral</span>
        </a>
        <a href="obras.php" class="tab-btn">
            <span>🖼️ Catálogo de Obras</span>
        </a>
        <a href="hero-slides.php" class="tab-btn active">
            <span>✨ Banners do Hero</span>
            <span class="tab-count">3</span>
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

    <main class="admin-content" style="max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; width: 100%;">

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 2.2rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.35rem;">
                    Banners do Hero Section
                </h1>
                <p style="color: var(--text-muted); font-size: 0.95rem;">
                    Personalize as 3 imagens, títulos, frases e citações do carrossel principal da página inicial.
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="../index.php#hero" target="_blank" class="btn-preset" style="padding: 0.65rem 1rem; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;">
                    Ver Hero no Site ↗
                </a>
                <form method="POST" onsubmit="return confirm('Deseja realmente restaurar os 3 banners para as fotos e textos originais da galeria?');" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset_defaults">
                    <button type="submit" class="btn-outline-danger">Restaurar Padrões</button>
                </form>
            </div>
        </div>

        <?php if (!empty($msgSucesso)): ?>
            <div class="alert alert-success" style="padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; background-color: rgba(46, 125, 50, 0.1); border: 1px solid rgba(46, 125, 50, 0.3); color: var(--color-success); display: flex; justify-content: space-between; align-items: center;">
                <span>✓ <?= htmlspecialchars($msgSucesso) ?></span>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.1rem;">✕</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($msgErro)): ?>
            <div class="alert alert-error" style="padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; background-color: rgba(201, 59, 59, 0.1); border: 1px solid rgba(201, 59, 59, 0.3); color: var(--color-danger); display: flex; justify-content: space-between; align-items: center;">
                <span>✕ <?= htmlspecialchars($msgErro) ?></span>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.1rem;">✕</button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="heroBannersForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
            <input type="hidden" name="action" value="save_slides">

            <?php for ($i = 0; $i < 3; $i++): 
                $slideNum = $i + 1;
                $slide = $slides[$i] ?? [];
                $currentImageResolved = artsale_resolve_image_url($slide['image'] ?? '', '../');
            ?>
                <section class="hero-banner-card" id="card-slide-<?= $slideNum ?>">
                    <div class="banner-header">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span class="banner-num-badge">
                                <span>✦</span> Slide 0<?= $slideNum ?> <?= $slideNum === 1 ? '— (Inicial)' : '' ?>
                            </span>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">
                                Indicador: <strong>0<?= $slideNum ?></strong> no carrossel
                            </span>
                        </div>
                        <span style="font-size: 0.8rem; color: var(--color-gold);">
                            Transição suave com zoom cinemático
                        </span>
                    </div>

                    <!-- Preview ao Vivo com Estilo do Hero -->
                    <div class="banner-preview-box" id="preview-box-<?= $slideNum ?>">
                        <div class="banner-preview-backdrop" id="preview-backdrop-<?= $slideNum ?>" style="background-image: url('<?= htmlspecialchars($currentImageResolved) ?>');"></div>
                        <div class="banner-preview-artwork" id="preview-artwork-<?= $slideNum ?>" style="background-image: url('<?= htmlspecialchars($currentImageResolved) ?>');"></div>
                        <div class="banner-preview-overlay"></div>
                        <div class="banner-preview-content">
                            <span class="preview-eyebrow" id="preview-eyebrow-<?= $slideNum ?>"><?= htmlspecialchars($slide['eyebrow'] ?? 'GALERIA DE ARTE') ?></span>
                            <h3 class="preview-title" id="preview-title-<?= $slideNum ?>"><?= $slide['title'] ?? 'Arte que<br>transforma<br>espaços.' ?></h3>
                            <p class="preview-desc" id="preview-desc-<?= $slideNum ?>"><?= htmlspecialchars($slide['description'] ?? '') ?></p>
                        </div>
                        <div class="banner-preview-quote" id="preview-quote-<?= $slideNum ?>">
                            “<?= htmlspecialchars($slide['quote'] ?? 'Mais que quadros, histórias que ganham vida no seu espaço.') ?>”
                        </div>
                    </div>

                    <!-- Configuração da Imagem do Banner -->
                    <div style="background-color: var(--bg-card-alt); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                            <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                                <span>📷</span> Imagem de Fundo do Slide <?= $slideNum ?>
                            </h4>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">
                                Escolha uma obra já publicada no site OU envie uma nova imagem
                            </span>
                        </div>

                        <div class="image-sources-grid">
                            <!-- Opção 1: Selecionar Obra do Acervo (Site) -->
                            <div class="image-source-col highlight-source">
                                <div>
                                    <div class="source-badge">
                                        <span>🏛️</span> Obra do Acervo (Site)
                                    </div>
                                    <label for="artwork_select_<?= $slideNum ?>" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.45rem; display: block;">
                                        Escolher Obra do Site:
                                    </label>
                                    <select name="artwork_select_<?= $slideNum ?>" id="artwork_select_<?= $slideNum ?>" class="form-control" onchange="handleArtworkSelect(this, <?= $slideNum ?>)" style="margin-bottom: 0.65rem;">
                                        <option value="">-- Selecione uma obra existente --</option>
                                        <?php 
                                        $initialSelectedArt = null;
                                        foreach ($siteArtworks as $art): 
                                            $isArtSelected = !empty($slide['image']) && ($slide['image'] === $art['raw_url'] || $slide['image'] === $art['preview_url']);
                                            if ($isArtSelected) $initialSelectedArt = $art;
                                        ?>
                                            <option value="<?= htmlspecialchars($art['raw_url']) ?>" 
                                                    data-title="<?= htmlspecialchars($art['titulo']) ?>"
                                                    data-artist="<?= htmlspecialchars($art['artista']) ?>"
                                                    data-preview="<?= htmlspecialchars($art['preview_url']) ?>"
                                                    <?= $isArtSelected ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($art['titulo']) ?> (<?= htmlspecialchars($art['artista']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <button type="button" class="btn-artwork-catalog" onclick="openArtworkModal(<?= $slideNum ?>)">
                                        <span>🖼️</span> Ver Catálogo Visual (Miniaturas)
                                    </button>
                                    <div id="artwork-badge-<?= $slideNum ?>" class="selected-artwork-indicator" style="<?= $initialSelectedArt ? '' : 'display: none;' ?>">
                                        <?php if ($initialSelectedArt): ?>
                                            ✓ Obra vinculada: <strong><?= htmlspecialchars($initialSelectedArt['titulo']) ?></strong> (<?= htmlspecialchars($initialSelectedArt['artista']) ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Opção 2: Upload de Arquivo do Computador -->
                            <div class="image-source-col">
                                <div>
                                    <div class="source-badge">
                                        <span>📤</span> Enviar Nova Imagem
                                    </div>
                                    <label style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.45rem; display: block;">
                                        Do seu Computador ou Celular:
                                    </label>
                                    <div class="file-upload-dropzone" onclick="document.getElementById('slide_file_<?= $slideNum ?>').click()">
                                        <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">📁</div>
                                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--color-gold);">
                                            Clique para selecionar arquivo
                                        </div>
                                        <div style="font-size: 0.73rem; color: var(--text-muted); margin-top: 0.2rem;">
                                            JPG, JPEG, PNG ou WEBP até 10 MB
                                        </div>
                                        <div id="file-name-<?= $slideNum ?>" style="font-size: 0.8rem; color: var(--color-success); font-weight: 600; margin-top: 0.4rem; display: none;"></div>
                                    </div>
                                    <input type="file" name="slide_file_<?= $slideNum ?>" id="slide_file_<?= $slideNum ?>" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleFileSelected(this, <?= $slideNum ?>)">
                                </div>
                            </div>

                            <!-- Opção 3: URL Direta ou Atalho da Galeria -->
                            <div class="image-source-col">
                                <div>
                                    <div class="source-badge">
                                        <span>🔗</span> URL ou Ambientes
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0.65rem;">
                                        <label for="image_url_<?= $slideNum ?>">Caminho / Link da Imagem:</label>
                                        <input type="text" name="image_url_<?= $slideNum ?>" id="image_url_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['image'] ?? '') ?>" placeholder="assets/images/site/... ou https://..." oninput="handleUrlChanged(this.value, <?= $slideNum ?>)">
                                        <div class="form-hint">Caminho relativo ou link do Supabase / CDN.</div>
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                                        Fotos clássicas da galeria:
                                    </label>
                                    <div class="preset-buttons">
                                        <button type="button" class="btn-preset" onclick="setPresetImage(<?= $slideNum ?>, 'assets/images/site/hero-bg.jpg')">Sala Galeria</button>
                                        <button type="button" class="btn-preset" onclick="setPresetImage(<?= $slideNum ?>, 'assets/images/site/about-art-gallery.jpg')">Salão Exposição</button>
                                        <button type="button" class="btn-preset" onclick="setPresetImage(<?= $slideNum ?>, 'assets/images/site/about-art-sell.jpg')">Ateliê Privado</button>
                                        <button type="button" class="btn-preset" onclick="setPresetImage(<?= $slideNum ?>, 'assets/images/site/footer-sculpture.jpg')">Escultura Luxo</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuração dos Textos Editoriais -->
                    <div class="grid-fields-2">
                        <div class="form-group">
                            <label for="eyebrow_<?= $slideNum ?>">Subtítulo Superior (Eyebrow):</label>
                            <input type="text" name="eyebrow_<?= $slideNum ?>" id="eyebrow_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['eyebrow'] ?? 'GALERIA DE ARTE') ?>" oninput="updateTextPreview('preview-eyebrow-<?= $slideNum ?>', this.value)">
                            <div class="form-hint">Ex: GALERIA DE ARTE, CURADORIA EXCLUSIVA, ACERVO PRIVADO.</div>
                        </div>

                        <div class="form-group">
                            <label for="title_<?= $slideNum ?>">Título Principal:</label>
                            <input type="text" name="title_<?= $slideNum ?>" id="title_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['title'] ?? '') ?>" oninput="updateHtmlPreview('preview-title-<?= $slideNum ?>', this.value)">
                            <div class="form-hint">Dica: Use <code>&lt;br&gt;</code> para quebras editoriais de linha harmoniosas.</div>
                        </div>
                    </div>

                    <div class="grid-fields-2">
                        <div class="form-group">
                            <label for="description_<?= $slideNum ?>">Descrição do Slide:</label>
                            <textarea name="description_<?= $slideNum ?>" id="description_<?= $slideNum ?>" class="form-control" rows="2" oninput="updateTextPreview('preview-desc-<?= $slideNum ?>', this.value)"><?= htmlspecialchars($slide['description'] ?? '') ?></textarea>
                            <div class="form-hint">Texto explicativo curto e acolhedor para colecionadores.</div>
                        </div>

                        <div class="form-group">
                            <label for="quote_<?= $slideNum ?>">Citação Editorial (Lado Direito):</label>
                            <textarea name="quote_<?= $slideNum ?>" id="quote_<?= $slideNum ?>" class="form-control" rows="2" oninput="updateQuotePreview('preview-quote-<?= $slideNum ?>', this.value)"><?= htmlspecialchars($slide['quote'] ?? '') ?></textarea>
                            <div class="form-hint">Frase poética ou reflexiva exibida entre aspas no box lateral.</div>
                        </div>
                    </div>

                    <!-- Links dos Botões (Opcional) -->
                    <details style="margin-top: 0.5rem; font-size: 0.85rem;">
                        <summary style="cursor: pointer; color: var(--color-gold); font-weight: 600; margin-bottom: 0.75rem;">
                            ⚙️ Personalizar Botões de Ação (CTAs) deste Slide
                        </summary>
                        <div class="grid-fields-2" style="background-color: var(--bg-card-alt); padding: 1rem; border-radius: 6px; border: 1px solid var(--border-color); margin-top: 0.5rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Botão Primário (Destaque):</label>
                                <input type="text" name="cta_primary_text_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['cta_primary_text'] ?? 'Explorar obras') ?>" placeholder="Texto do botão" style="margin-bottom: 0.35rem;">
                                <input type="text" name="cta_primary_url_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['cta_primary_url'] ?? 'pages/obras.php') ?>" placeholder="Link (ex: pages/obras.php)">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Botão Secundário (Borda):</label>
                                <input type="text" name="cta_secondary_text_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['cta_secondary_text'] ?? 'Conhecer a Galeria') ?>" placeholder="Texto do botão" style="margin-bottom: 0.35rem;">
                                <input type="text" name="cta_secondary_url_<?= $slideNum ?>" class="form-control" value="<?= htmlspecialchars($slide['cta_secondary_url'] ?? '#sobre') ?>" placeholder="Link (ex: #sobre)">
                            </div>
                        </div>
                    </details>
                </section>
            <?php endfor; ?>

            <!-- Barra Fixa de Salvamento -->
            <div class="actions-bottom-bar">
                <div>
                    <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-main);">
                        ✦ Configuração dos 3 Slides Pronta
                    </span>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                        Ao salvar, as imagens e textos entrarão no carrossel da página inicial imediatamente.
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="index.php" class="btn-preset" style="padding: 0.75rem 1.25rem; font-size: 0.9rem; text-decoration: none;">Cancelar</a>
                    <button type="submit" class="btn-gold">
                        <span>💾 Salvar Todos os Banners</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Modal Visual de Seleção de Obras do Acervo -->
        <div class="modal-artwork-picker-backdrop" id="modalArtworkPicker" onclick="if(event.target === this) closeArtworkModal();">
            <div class="modal-artwork-picker-container" role="dialog" aria-modal="true" aria-labelledby="modalArtworkTitle">
                <div class="modal-artwork-header">
                    <div>
                        <h3 id="modalArtworkTitle" style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.45rem; color: var(--text-main); margin: 0 0 0.25rem 0;">
                            Selecionar Obra do Acervo para o Slide <span id="modalTargetSlideNum" style="color: var(--color-gold);">01</span>
                        </h3>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                            Clique em qualquer obra abaixo para aplicá-la como imagem de fundo deste banner do Hero.
                        </p>
                    </div>
                    <button type="button" class="modal-close-btn" onclick="closeArtworkModal()" aria-label="Fechar modal" title="Fechar">✕</button>
                </div>

                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--bg-card-alt);">
                    <div style="position: relative;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); font-size: 0.95rem; opacity: 0.6;">🔍</span>
                        <input type="text" id="artworkSearchInput" class="form-control" style="padding-left: 2.3rem;" placeholder="Buscar obra por título ou artista..." oninput="filterModalArtworks(this.value)">
                    </div>
                </div>

                <div class="modal-artwork-body">
                    <?php if (empty($siteArtworks)): ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            <p style="font-size: 2rem; margin-bottom: 0.5rem;">🏛️</p>
                            <p style="font-weight: 600;">Nenhuma obra ativa encontrada no momento.</p>
                            <p style="font-size: 0.85rem;">Você pode cadastrar novas obras em <a href="obra-nova.php" style="color: var(--color-gold);">Obras > Nova Obra</a> ou fazer upload de imagem diretamente.</p>
                        </div>
                    <?php else: ?>
                        <div class="modal-artwork-grid" id="modalArtworksGrid">
                            <?php foreach ($siteArtworks as $art): ?>
                                <div class="artwork-pick-card" 
                                     data-title="<?= htmlspecialchars(mb_strtolower($art['titulo'])) ?>" 
                                     data-artist="<?= htmlspecialchars(mb_strtolower($art['artista'])) ?>"
                                     data-cat="<?= htmlspecialchars(mb_strtolower($art['categoria'])) ?>"
                                     onclick="applyArtworkPick(currentModalSlide, '<?= htmlspecialchars(addslashes($art['raw_url'])) ?>', '<?= htmlspecialchars(addslashes($art['titulo'])) ?>', '<?= htmlspecialchars(addslashes($art['artista'])) ?>', '<?= htmlspecialchars(addslashes($art['preview_url'])) ?>')">
                                    <div style="position: relative;">
                                        <img src="<?= htmlspecialchars($art['thumb_url']) ?>" alt="<?= htmlspecialchars($art['titulo']) ?>" class="artwork-pick-thumb" loading="lazy" onerror="this.src='../assets/images/obras/placeholder-obra.svg'">
                                        <?php if (!empty($art['categoria'])): ?>
                                            <span class="artwork-pick-badge"><?= htmlspecialchars($art['categoria']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="artwork-pick-info">
                                        <div>
                                            <div class="artwork-pick-title"><?= htmlspecialchars($art['titulo']) ?></div>
                                            <div class="artwork-pick-artist"><?= htmlspecialchars($art['artista']) ?></div>
                                        </div>
                                        <button type="button" class="btn-use-artwork">
                                            <span>✓ Usar esta Obra</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="modalNoResults" style="display: none; text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                            <p>Nenhuma obra corresponde aos termos pesquisados.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="padding: 0.85rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: var(--bg-card-alt);">
                    <button type="button" class="btn-preset" style="padding: 0.5rem 1.25rem;" onclick="closeArtworkModal()">Fechar</button>
                </div>
            </div>
        </div>

    </main>

    <script>
        let currentModalSlide = 1;

        // Abre o modal de escolha de obras do site
        function openArtworkModal(slideNum) {
            currentModalSlide = slideNum;
            const modal = document.getElementById('modalArtworkPicker');
            const targetBadge = document.getElementById('modalTargetSlideNum');
            if (targetBadge) {
                targetBadge.textContent = '0' + slideNum;
            }
            const searchInput = document.getElementById('artworkSearchInput');
            if (searchInput) {
                searchInput.value = '';
                filterModalArtworks('');
            }
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        // Fecha o modal de escolha de obras
        function closeArtworkModal() {
            const modal = document.getElementById('modalArtworkPicker');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // Filtro em tempo real no modal
        function filterModalArtworks(query) {
            const q = (query || '').toLowerCase().trim();
            const cards = document.querySelectorAll('.artwork-pick-card');
            let visibleCount = 0;
            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const artist = card.getAttribute('data-artist') || '';
                const cat = card.getAttribute('data-cat') || '';
                if (!q || title.includes(q) || artist.includes(q) || cat.includes(q)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            const noResults = document.getElementById('modalNoResults');
            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        // Aplica a obra escolhida ao slide especificado
        function applyArtworkPick(slideNum, rawUrl, title, artist, previewUrl) {
            // 1. Atualiza campo de URL
            const urlInput = document.getElementById('image_url_' + slideNum);
            if (urlInput) {
                urlInput.value = rawUrl;
            }

            // 2. Atualiza preview visual do Hero
            updateSlidePreviewImages(slideNum, previewUrl);

            // 3. Atualiza o dropdown select do slide
            const select = document.getElementById('artwork_select_' + slideNum);
            if (select) {
                select.value = rawUrl;
            }

            // 4. Limpa arquivo local caso tivesse selecionado no input file
            const fileInput = document.getElementById('slide_file_' + slideNum);
            if (fileInput) {
                fileInput.value = '';
            }
            const fileNameDiv = document.getElementById('file-name-' + slideNum);
            if (fileNameDiv) {
                fileNameDiv.style.display = 'none';
            }

            // 5. Exibe indicador visual de obra selecionada
            const badge = document.getElementById('artwork-badge-' + slideNum);
            if (badge) {
                badge.innerHTML = '✓ Obra vinculada: <strong>' + title + '</strong>' + (artist ? ' (' + artist + ')' : '');
                badge.style.display = 'block';
            }

            // 6. Fecha o modal
            closeArtworkModal();
        }

        // Atualiza as camadas do preview visual (fundo ambiente + obra centralizada)
        function updateSlidePreviewImages(slideNum, imgUrl) {
            const previewBox = document.getElementById('preview-box-' + slideNum);
            if (previewBox) previewBox.style.backgroundImage = 'url(' + imgUrl + ')';
            const backdrop = document.getElementById('preview-backdrop-' + slideNum);
            if (backdrop) backdrop.style.backgroundImage = 'url(' + imgUrl + ')';
            const artwork = document.getElementById('preview-artwork-' + slideNum);
            if (artwork) artwork.style.backgroundImage = 'url(' + imgUrl + ')';
        }

        // Trata a seleção direta pelo dropdown <select>
        function handleArtworkSelect(selectEl, slideNum) {
            const val = selectEl.value;
            if (!val) {
                const badge = document.getElementById('artwork-badge-' + slideNum);
                if (badge) badge.style.display = 'none';
                return;
            }

            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const title = selectedOption.getAttribute('data-title') || '';
            const artist = selectedOption.getAttribute('data-artist') || '';
            const previewUrl = selectedOption.getAttribute('data-preview') || val;

            applyArtworkPick(slideNum, val, title, artist, previewUrl);
        }

        // Atualiza preview ao selecionar arquivo do disco
        function handleFileSelected(input, slideNum) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileNameDiv = document.getElementById('file-name-' + slideNum);
                if (fileNameDiv) {
                    fileNameDiv.textContent = '✓ Selecionado: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                    fileNameDiv.style.display = 'block';
                }

                // Reseta dropdown de obra do site para evitar ambiguidade
                const select = document.getElementById('artwork_select_' + slideNum);
                if (select) select.value = '';
                const badge = document.getElementById('artwork-badge-' + slideNum);
                if (badge) badge.style.display = 'none';

                const reader = new FileReader();
                reader.onload = function(e) {
                    updateSlidePreviewImages(slideNum, e.target.result);
                };
                reader.readAsDataURL(file);
            }
        }

        // Atualiza preview ao digitar URL
        function handleUrlChanged(url, slideNum) {
            if (!url) return;
            const resolvedUrl = url.startsWith('http') || url.startsWith('data:') ? url : '../' + url.replace(/^\/+/, '');
            updateSlidePreviewImages(slideNum, resolvedUrl);
            // Se a URL digitada não bater com o select, desmarca o select
            const select = document.getElementById('artwork_select_' + slideNum);
            if (select && select.value !== url) {
                select.value = '';
                const badge = document.getElementById('artwork-badge-' + slideNum);
                if (badge) badge.style.display = 'none';
            }
        }

        // Aplica imagem pré-definida de ambiente
        function setPresetImage(slideNum, presetPath) {
            const inputUrl = document.getElementById('image_url_' + slideNum);
            if (inputUrl) {
                inputUrl.value = presetPath;
                handleUrlChanged(presetPath, slideNum);
            }
            const fileInput = document.getElementById('slide_file_' + slideNum);
            if (fileInput) {
                fileInput.value = '';
            }
            const fileNameDiv = document.getElementById('file-name-' + slideNum);
            if (fileNameDiv) {
                fileNameDiv.style.display = 'none';
            }
            const select = document.getElementById('artwork_select_' + slideNum);
            if (select) select.value = '';
            const badge = document.getElementById('artwork-badge-' + slideNum);
            if (badge) badge.style.display = 'none';
        }

        // Fecha modal ao teclar ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeArtworkModal();
            }
        });

        // Funções para atualizar texto ao vivo no card
        function updateTextPreview(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        function updateHtmlPreview(id, val) {
            const el = document.getElementById(id);
            if (el) el.innerHTML = val;
        }

        function updateQuotePreview(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = '“' + val + '”';
        }
    </script>
</body>
</html>
