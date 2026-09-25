<?php
/**
 * ART FOR SALE - Cadastrar Nova Obra (Painel Administrativo)
 * Arquivo: /admin/obra-nova.php
 * Slogan: "Arte que transforma espaços."
 * 
 * REGRAS IMPLEMENTADAS:
 * - Proteção server-side com Supabase Auth (profiles.role = 'admin').
 * - Todos os 17 campos requeridos contemplados:
 *     Nome, Slug, Artista, Descrição, Técnica, Ano, Largura, Altura,
 *     Profundidade, Estado de conservação, Código, Categoria, Disponibilidade,
 *     Procedência, Localização, Destaque e Ativo.
 * - Disponibilidade suportada:
 *     Disponível (available), Reservada (reserved), Vendida (sold), Indisponível (unavailable).
 * - Imagem inicial: permite upload imediato da imagem principal para o Supabase Storage
 *   na pasta artworks/{id}/principal/.
 * - Regra estrita: NUNCA colocar preço numérico (apresentado como "Preço sob consulta").
 * - Validações e feedback de erro/sucesso.
 * - Aparece automaticamente no site público se active=true.
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$msgErro = '';
$msgSucesso = '';

// Listas para os seletores
$allCategories = supabase_buscar_categorias_list($adminToken);
$allArtists = supabase_buscar_artistas_list($adminToken);

// Processamento de Criação da Obra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $nome = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $technique = trim($_POST['technique'] ?? '');
    $year = (int)($_POST['year'] ?? date('Y'));
    $width = (float)($_POST['width'] ?? 0);
    $height = (float)($_POST['height'] ?? 0);
    $depth = !empty($_POST['depth']) ? (float)$_POST['depth'] : null;
    $conservation = trim($_POST['conservation_state'] ?? 'Excelente');
    $provenance = trim($_POST['provenance'] ?? 'Acervo Particular');
    $location = trim($_POST['location'] ?? 'Brasil');
    $description = trim($_POST['description'] ?? '');
    $availability = trim($_POST['availability'] ?? 'available');
    $featured = !empty($_POST['featured']);
    $active = !empty($_POST['active']);
    $artistId = !empty($_POST['artist_id']) ? $_POST['artist_id'] : null;
    $artistName = trim($_POST['artist_name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $categoryName = trim($_POST['category_name'] ?? '');

    // Validações obrigatórias
    if (empty($nome)) {
        $msgErro = 'O Nome da obra é obrigatório.';
    } elseif ($width <= 0 || $height <= 0) {
        $msgErro = 'Informe as dimensões válidas de Altura e Largura (em centímetros).';
    } else {
        // Processamento da imagem: via $_FILES ou via Base64 fallback (imune a limites rígidos de upload_max_filesize)
        $uploadFile = $_FILES['primary_image'] ?? null;
        $base64Data = trim($_POST['primary_image_base64'] ?? '');
        $base64Name = trim($_POST['primary_image_filename'] ?? 'fotografia.jpg');

        $hasImage = false;
        $tmpImagePath = null;
        $imageOriginalName = null;
        $isTemporaryCreated = false;

        if (!empty($uploadFile) && $uploadFile['error'] === UPLOAD_ERR_OK && !empty($uploadFile['tmp_name'])) {
            $val = supabase_validar_arquivo_imagem($uploadFile);
            if (!$val['valido']) {
                $msgErro = 'Erro na fotografia: ' . $val['erro'];
            } else {
                $hasImage = true;
                $tmpImagePath = $uploadFile['tmp_name'];
                $imageOriginalName = $uploadFile['name'];
            }
        } elseif (!empty($uploadFile) && $uploadFile['error'] !== UPLOAD_ERR_NO_FILE) {
            // Se o upload direto pelo PHP falhou (ex: arquivo maior que upload_max_filesize), usa o Base64 se capturado pelo navegador
            if (!empty($base64Data) && str_starts_with($base64Data, 'data:image/')) {
                $parts = explode(',', $base64Data, 2);
                if (count($parts) === 2) {
                    $bin = base64_decode($parts[1]);
                    if (!empty($bin)) {
                        $tmpFile = tempnam(sys_get_temp_dir(), 'obra_img_');
                        file_put_contents($tmpFile, $bin);
                        $val = supabase_validar_arquivo_imagem(['tmp_name' => $tmpFile, 'name' => $base64Name, 'size' => strlen($bin)]);
                        if (!$val['valido']) {
                            $msgErro = 'Erro na fotografia (Base64): ' . $val['erro'];
                            @unlink($tmpFile);
                        } else {
                            $hasImage = true;
                            $tmpImagePath = $tmpFile;
                            $imageOriginalName = $base64Name;
                            $isTemporaryCreated = true;
                        }
                    }
                }
            } else {
                switch ($uploadFile['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $msgErro = 'A fotografia enviada excede o limite máximo permitido pelo servidor PHP (tente uma imagem de até 8MB ou JPG comprimido).';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $msgErro = 'O envio da fotografia foi interrompido antes de concluir. Tente novamente.';
                        break;
                    default:
                        $msgErro = 'Não foi possível carregar o arquivo da fotografia (código: ' . $uploadFile['error'] . ').';
                        break;
                }
            }
        } elseif (!empty($base64Data) && str_starts_with($base64Data, 'data:image/')) {
            // Caso o arquivo tenha sido enviado pelo Base64
            $parts = explode(',', $base64Data, 2);
            if (count($parts) === 2) {
                $bin = base64_decode($parts[1]);
                if (!empty($bin)) {
                    $tmpFile = tempnam(sys_get_temp_dir(), 'obra_img_');
                    file_put_contents($tmpFile, $bin);
                    $val = supabase_validar_arquivo_imagem(['tmp_name' => $tmpFile, 'name' => $base64Name, 'size' => strlen($bin)]);
                    if (!$val['valido']) {
                        $msgErro = 'Erro na fotografia (Base64): ' . $val['erro'];
                        @unlink($tmpFile);
                    } else {
                        $hasImage = true;
                        $tmpImagePath = $tmpFile;
                        $imageOriginalName = $base64Name;
                        $isTemporaryCreated = true;
                    }
                }
            }
        }

        if (empty($msgErro)) {
            // Monta payload
            $dados = [
                'name'               => $nome,
                'slug'               => $slug,
                'code'               => $code,
                'technique'          => $technique,
                'year'               => $year,
                'width'              => $width,
                'height'             => $height,
                'depth'              => $depth,
                'conservation_state' => $conservation,
                'provenance'         => $provenance,
                'location'           => $location,
                'description'        => $description,
                'availability'       => $availability,
                'featured'           => $featured,
                'active'             => $active,
                'artist_id'          => $artistId,
                'artist_name'        => $artistName,
                'category_id'        => $categoryId,
                'category_name'      => $categoryName
            ];

            $res = supabase_criar_obra($dados, $adminToken);

            if (!empty($res['error'])) {
                $msgErro = 'Erro ao cadastrar obra no Supabase: ' . $res['error'];
            } elseif (!empty($res['data'])) {
                $createdRow = is_array($res['data']) ? ($res['data'][0] ?? $res['data']) : $res['data'];
                $newId = $createdRow['id'] ?? '';

                // Se houver imagem enviada, faz o upload para o Supabase Storage e registro da imagem
                if ($hasImage && !empty($newId) && !empty($tmpImagePath)) {
                    $upRes = supabase_upload_imagem_obra(
                        $tmpImagePath,
                        $newId,
                        $imageOriginalName ?: 'fotografia.jpg',
                        'principal',
                        true,
                        1,
                        $adminToken
                    );

                    if ($isTemporaryCreated && file_exists($tmpImagePath)) {
                        @unlink($tmpImagePath);
                    }

                    if (empty($upRes['sucesso'])) {
                        header("Location: obras.php?msg=created&upload_error=" . urlencode($upRes['erro'] ?? 'Falha no envio da foto para o Storage'));
                        exit;
                    }
                }

                header("Location: obras.php?msg=created");
                exit;
            } else {
                $msgErro = 'Não foi possível confirmar o cadastro da obra no Supabase.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Nova Obra — Painel Art For Sale</title>
    
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
            max-width: 1040px;
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

        .btn-back {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 0.6rem 1.1rem;
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
        .alert-error {
            background: rgba(201, 59, 59, 0.15);
            border: 1px solid #c93b3b;
            color: #ff9999;
            padding: 1rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        /* Card do Formulário */
        .form-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 2.25rem;
            margin-bottom: 2.5rem;
        }

        .form-section-title {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.45rem;
            color: var(--color-gold);
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        }

        .f-group {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .f-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--color-gold);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .f-label small {
            color: var(--text-muted);
            text-transform: none;
            font-weight: 400;
        }

        .f-input {
            background-color: #0b0a09;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.85rem 1.1rem;
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

        .f-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .f-checkbox-card {
            background-color: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .f-checkbox-card:hover {
            border-color: var(--border-gold);
        }

        .f-checkbox-card input {
            accent-color: var(--color-gold);
            width: 18px;
            height: 18px;
        }

        .f-checkbox-text strong {
            display: block;
            font-size: 0.85rem;
            color: var(--text-main);
            margin-bottom: 0.15rem;
        }

        .f-checkbox-text span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Banner de Preço Sob Consulta */
        .banner-price-policy {
            background: rgba(179, 138, 84, 0.08);
            border: 1px solid var(--border-gold);
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .banner-price-policy strong {
            color: var(--color-gold);
        }

        .btn-submit-save {
            background-color: var(--color-gold);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            padding: 1rem 2.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(179, 138, 84, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit-save:hover {
            background-color: var(--color-gold-hover);
            box-shadow: 0 6px 25px rgba(179, 138, 84, 0.45);
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
            <span class="badge-role">Admin • Nova Obra</span>
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
        <a href="obra-nova.php" class="tab-btn active">
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

        <div class="page-header-row">
            <div>
                <h1 class="section-headline">Cadastrar Nova Obra</h1>
                <p class="section-subhead">
                    Preencha as informações do acervo. A obra será publicada automaticamente no site público quando estiver marcada como ativa.
                </p>
            </div>
            <a href="obras.php" class="btn-back">
                ← Voltar ao Catálogo
            </a>
        </div>

        <?php if (!empty($msgErro)): ?>
            <div class="alert-error">
                ✕ <?= htmlspecialchars($msgErro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="formNovaObra">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
            
            <!-- Seção 1: Identificação Básica -->
            <div class="form-card">
                <h2 class="form-section-title">
                    <span>1. Identificação da Obra</span>
                </h2>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="name" class="f-label">Nome da Obra *</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="f-input" 
                            placeholder="Ex: Crepúsculo dos Deuses" 
                            required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="f-group">
                        <label for="code" class="f-label">Código no Catálogo <small>(opcional)</small></label>
                        <input 
                            type="text" 
                            name="code" 
                            id="code" 
                            class="f-input" 
                            placeholder="Ex: ASF-1048"
                            value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                        >
                        <span class="f-hint">Se deixado em branco, será gerado automaticamente (ex: ASF-XXXX).</span>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="slug" class="f-label">Slug da URL <small>(identificador público)</small></label>
                        <input 
                            type="text" 
                            name="slug" 
                            id="slug" 
                            class="f-input" 
                            placeholder="crepusculo-dos-deuses"
                            value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>"
                        >
                        <span class="f-hint">Gerado automaticamente a partir do nome se não informado.</span>
                    </div>

                    <div class="f-group">
                        <label for="technique" class="f-label">Técnica *</label>
                        <input 
                            type="text" 
                            name="technique" 
                            id="technique" 
                            class="f-input" 
                            placeholder="Ex: Óleo sobre tela" 
                            required
                            value="<?= htmlspecialchars($_POST['technique'] ?? 'Óleo sobre tela') ?>"
                        >
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="artist_id" class="f-label">Artista Cadastrado <small>(seleção)</small></label>
                        <select name="artist_id" id="artist_id" class="f-input">
                            <option value="">Selecione o Artista...</option>
                            <?php foreach ($allArtists as $art): ?>
                                <option value="<?= $art['id'] ?>" <?= (($_POST['artist_id'] ?? '') === $art['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($art['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="f-hint">Selecione um artista cadastrado no acervo.</span>
                    </div>

                    <div class="f-group">
                        <label for="artist_name" class="f-label">Nome do Artista <small>(opcional)</small></label>
                        <input 
                            type="text" 
                            name="artist_name" 
                            id="artist_name" 
                            class="f-input" 
                            placeholder="Ou digite o nome (ex: Di Cavalcanti, Anita Malfatti...)"
                            value="<?= htmlspecialchars($_POST['artist_name'] ?? '') ?>"
                        >
                        <span class="f-hint">Se preenchido, vincula ou cadastra automaticamente o artista.</span>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="category_id" class="f-label">Categoria / Estilo *</label>
                        <select name="category_id" id="category_id" class="f-input">
                            <option value="">Selecione a Categoria...</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? '') === $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name'] ?? $cat['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="f-hint">Selecione o estilo ou categoria da obra.</span>
                    </div>

                    <div class="f-group">
                        <label for="category_name" class="f-label">Nova Categoria <small>(opcional)</small></label>
                        <input 
                            type="text" 
                            name="category_name" 
                            id="category_name" 
                            class="f-input" 
                            placeholder="Ou digite o nome de uma nova categoria"
                            value="<?= htmlspecialchars($_POST['category_name'] ?? '') ?>"
                        >
                        <span class="f-hint">Se deixado em branco, utiliza a categoria selecionada.</span>
                    </div>
                </div>
            </div>

            <!-- Seção 2: Dimensões e Detalhes Físicos -->
            <div class="form-card">
                <h2 class="form-section-title">
                    <span>2. Especificações Físicas & Conservação</span>
                </h2>

                <div class="form-grid-3">
                    <div class="f-group">
                        <label for="height" class="f-label">Altura (cm) *</label>
                        <input 
                            type="number" 
                            step="0.1" 
                            name="height" 
                            id="height" 
                            class="f-input" 
                            placeholder="Ex: 100" 
                            required
                            value="<?= htmlspecialchars($_POST['height'] ?? '') ?>"
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
                            placeholder="Ex: 80" 
                            required
                            value="<?= htmlspecialchars($_POST['width'] ?? '') ?>"
                        >
                    </div>

                    <div class="f-group">
                        <label for="depth" class="f-label">Profundidade (cm) <small>(esculturas/relevos)</small></label>
                        <input 
                            type="number" 
                            step="0.1" 
                            name="depth" 
                            id="depth" 
                            class="f-input" 
                            placeholder="Ex: 5"
                            value="<?= htmlspecialchars($_POST['depth'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="f-group">
                        <label for="year" class="f-label">Ano de Criação *</label>
                        <input 
                            type="number" 
                            name="year" 
                            id="year" 
                            class="f-input" 
                            min="1700" 
                            max="2100" 
                            required
                            value="<?= htmlspecialchars($_POST['year'] ?? date('Y')) ?>"
                        >
                    </div>

                    <div class="f-group">
                        <label for="conservation_state" class="f-label">Estado de Conservação</label>
                        <input 
                            type="text" 
                            name="conservation_state" 
                            id="conservation_state" 
                            class="f-input" 
                            placeholder="Ex: Excelente"
                            value="<?= htmlspecialchars($_POST['conservation_state'] ?? 'Excelente') ?>"
                        >
                    </div>

                    <div class="f-group">
                        <label for="location" class="f-label">Localização Atual</label>
                        <input 
                            type="text" 
                            name="location" 
                            id="location" 
                            class="f-input" 
                            placeholder="Ex: São Paulo - SP, Brasil"
                            value="<?= htmlspecialchars($_POST['location'] ?? 'Brasil') ?>"
                        >
                    </div>
                </div>

                <div class="f-group">
                    <label for="provenance" class="f-label">Procedência do Acervo</label>
                    <input 
                        type="text" 
                        name="provenance" 
                        id="provenance" 
                        class="f-input" 
                        placeholder="Ex: Acervo Particular do Artista / Coleção Particular"
                        value="<?= htmlspecialchars($_POST['provenance'] ?? 'Acervo Particular') ?>"
                    >
                </div>
            </div>

            <!-- Seção 3: Ensaio Curatorial e Disponibilidade -->
            <div class="form-card">
                <h2 class="form-section-title">
                    <span>3. Ensaio Curatorial & Disponibilidade</span>
                </h2>

                <!-- Política de Preço Sob Consulta -->
                <div class="banner-price-policy">
                    <span style="font-size: 1.25rem;">💎</span>
                    <div>
                        <strong>Política Editorial Art For Sale:</strong> As obras cadastradas nunca possuem preço numérico.
                        Nas páginas públicas, o valor é apresentado invariavelmente como <em>"Preço sob consulta"</em>.
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="availability" class="f-label">Disponibilidade da Obra *</label>
                        <select name="availability" id="availability" class="f-input">
                            <option value="available" <?= (($_POST['availability'] ?? 'available') === 'available') ? 'selected' : '' ?>>Disponível (Pronta para aquisição)</option>
                            <option value="reserved" <?= (($_POST['availability'] ?? '') === 'reserved') ? 'selected' : '' ?>>Reservada (Em negociação)</option>
                            <option value="sold" <?= (($_POST['availability'] ?? '') === 'sold') ? 'selected' : '' ?>>Vendida (Acervo particular)</option>
                            <option value="unavailable" <?= (($_POST['availability'] ?? '') === 'unavailable') ? 'selected' : '' ?>>Indisponível (Em restauração / Fora de catálogo)</option>
                        </select>
                    </div>

                    <div class="f-group">
                        <label class="f-label">Status de Publicação no Site</label>
                        <label class="f-checkbox-card">
                            <input type="checkbox" name="active" value="1" <?= (!isset($_POST['name']) || !empty($_POST['active'])) ? 'checked' : '' ?>>
                            <div class="f-checkbox-text">
                                <strong>Obra Ativa no Catálogo Público</strong>
                                <span>Aparece automaticamente nas páginas públicas e buscas do site.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="f-group">
                        <label class="f-label">Destaque na Página Inicial</label>
                        <label class="f-checkbox-card">
                            <input type="checkbox" name="featured" value="1" <?= !empty($_POST['featured']) ? 'checked' : '' ?>>
                            <div class="f-checkbox-text">
                                <strong>Destacar na Home</strong>
                                <span>Exibe esta obra na seção nobre de destaques da página principal.</span>
                            </div>
                        </label>
                    </div>

                    <div class="f-group">
                        <label for="primary_image" class="f-label">Fotografia Principal <small>(Supabase Storage)</small></label>
                        <input 
                            type="file" 
                            name="primary_image" 
                            id="primary_image" 
                            class="f-input"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >
                        <input type="hidden" name="primary_image_base64" id="primary_image_base64" value="">
                        <input type="hidden" name="primary_image_filename" id="primary_image_filename" value="">
                        <span class="f-hint">JPG, JPEG, PNG, WEBP até 10 MB. Você também poderá enviar fotos adicionais de detalhes após salvar.</span>
                        
                        <!-- Miniatura de Preview em Tempo Real -->
                        <div id="imagePreviewContainer" style="display: none; margin-top: 0.75rem; align-items: center; gap: 1rem; background: #0c0b0a; padding: 0.75rem 1rem; border: 1px solid var(--border-gold); border-radius: 6px;">
                            <img id="imagePreviewThumb" src="" alt="Prévia da Obra" style="width: 58px; height: 58px; object-fit: cover; border-radius: 4px; border: 1px solid var(--color-gold);">
                            <div>
                                <span id="imagePreviewName" style="display: block; font-size: 0.8125rem; color: var(--text-main); font-weight: 600;"></span>
                                <span id="imagePreviewMeta" style="font-size: 0.75rem; color: var(--color-gold);">✓ Fotografia pronta para ser salva no acervo</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="f-group" style="margin-top: 1.25rem;">
                    <label for="description" class="f-label">Descrição / Ensaio Curatorial</label>
                    <textarea 
                        name="description" 
                        id="description" 
                        class="f-input" 
                        rows="4" 
                        placeholder="Contexto histórico, nuances da técnica, significado estético e relevância para colecionadores..."
                    ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Botão de Submissão -->
            <div style="text-align: right; margin-bottom: 3rem;">
                <button type="submit" class="btn-submit-save" id="btnSubmitForm">
                    ✓ Cadastrar Obra no Acervo
                </button>
            </div>

        </form>

    </main>

    <script>
    // Gerador de Slug Automático a partir do Nome
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');

    if (nameInput && slugInput) {
        nameInput.addEventListener('input', () => {
            if (!slugInput.dataset.manual) {
                const slug = nameInput.value
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)/g, '');
                slugInput.value = slug;
            }
        });

        slugInput.addEventListener('input', () => {
            slugInput.dataset.manual = 'true';
        });
    }

    // Leitura, validação e preview imediato da fotografia
    const imgInput = document.getElementById('primary_image');
    const b64Input = document.getElementById('primary_image_base64');
    const fnInput = document.getElementById('primary_image_filename');
    const prevCont = document.getElementById('imagePreviewContainer');
    const prevImg = document.getElementById('imagePreviewThumb');
    const prevName = document.getElementById('imagePreviewName');

    if (imgInput) {
        imgInput.addEventListener('change', () => {
            const file = imgInput.files[0];
            if (file) {
                if (file.size > 15 * 1024 * 1024) {
                    alert('A fotografia selecionada excede o limite de 15 MB.');
                    imgInput.value = '';
                    if (b64Input) b64Input.value = '';
                    if (prevCont) prevCont.style.display = 'none';
                    return;
                }

                // Carrega em Base64 para garantir envio mesmo com upload_max_filesize restrito no PHP local
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (b64Input) b64Input.value = e.target.result;
                    if (fnInput) fnInput.value = file.name;
                    if (prevImg) prevImg.src = e.target.result;
                    if (prevName) prevName.textContent = file.name + ' (' + (file.size / (1024*1024)).toFixed(2) + ' MB)';
                    if (prevCont) prevCont.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            } else {
                if (b64Input) b64Input.value = '';
                if (prevCont) prevCont.style.display = 'none';
            }
        });
    }
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
