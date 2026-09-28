<?php
/**
 * ART FOR SALE - Cadastrar Novo Artista (Painel Administrativo)
 * Arquivo: /admin/artista-novo.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Campos contemplados:
 * - Nome (obrigatório)
 * - Slug (opcional, gerado automaticamente a partir do nome)
 * - Biografia (trajetória artística, influências e exposições)
 * - Fotografia (upload com live preview e Base64 streaming fail-safe)
 * - Ativo (publicação e disponibilidade para associação de obras)
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$msgErro = '';
$msgSucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $nome = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $biography = trim($_POST['biography'] ?? '');
    $active = !empty($_POST['active']);

    if (empty($nome)) {
        $msgErro = 'O Nome do artista é obrigatório.';
    } else {
        // Validação da imagem enviada
        $hasImage = false;
        $tmpImagePath = '';
        $imageOriginalName = '';
        $isTemporaryCreated = false;

        $file = $_FILES['artist_image'] ?? null;
        $base64Data = $_POST['base64_image_data'] ?? '';
        $base64Name = $_POST['base64_image_name'] ?? 'artista.jpg';

        if (!empty($file) && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $val = supabase_validar_arquivo_imagem($file);
                if (!$val['valido']) {
                    $msgErro = $val['erro'];
                } else {
                    $hasImage = true;
                    $tmpImagePath = $file['tmp_name'];
                    $imageOriginalName = $file['name'];
                }
            }
        } elseif (!empty($base64Data) && str_starts_with($base64Data, 'data:image/')) {
            $parts = explode(',', $base64Data, 2);
            if (count($parts) === 2) {
                $bin = base64_decode($parts[1]);
                if (!empty($bin)) {
                    $tmpFile = tempnam(sys_get_temp_dir(), 'art_img_');
                    file_put_contents($tmpFile, $bin);
                    $val = supabase_validar_arquivo_imagem(['tmp_name' => $tmpFile, 'name' => $base64Name, 'size' => strlen($bin)]);
                    if (!$val['valido']) {
                        $msgErro = 'Erro na imagem (Base64): ' . $val['erro'];
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
            $dados = [
                'name'      => $nome,
                'slug'      => $slug,
                'biography' => $biography,
                'active'    => $active
            ];

            $res = supabase_criar_artista($dados, $adminToken);

            if (!empty($res['error'])) {
                $msgErro = 'Erro ao cadastrar artista: ' . $res['error'];
            } elseif (!empty($res['data'])) {
                $createdRow = is_array($res['data']) ? ($res['data'][0] ?? $res['data']) : $res['data'];
                $newId = $createdRow['id'] ?? '';

                if ($hasImage && !empty($newId) && !empty($tmpImagePath)) {
                    $upRes = supabase_upload_imagem_artista(
                        $tmpImagePath,
                        $newId,
                        $imageOriginalName ?: 'artista.jpg',
                        $adminToken
                    );

                    if ($isTemporaryCreated && file_exists($tmpImagePath)) {
                        @unlink($tmpImagePath);
                    }

                    if (empty($upRes['sucesso'])) {
                        $msgErro = 'Erro no upload da fotografia do artista: ' . ($upRes['erro'] ?? 'Falha de envio.');
                    }
                }

                if (empty($msgErro)) {
                    header("Location: artistas.php?msg=created");
                    exit;
                }
            } else {
                $msgErro = 'Não foi possível confirmar o cadastro do artista.';
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
    <title>Cadastrar Novo Artista — Painel Art For Sale</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/site/logo-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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

        .tab-btn:hover { color: var(--text-main); border-bottom-color: rgba(179, 138, 84, 0.4); }
        .tab-btn.active {
            color: var(--color-gold);
            border-bottom-color: var(--color-gold);
            background: rgba(179, 138, 84, 0.04);
        }

        /* Conteúdo Principal */
        .admin-content {
            padding: 2.25rem 2rem;
            max-width: 900px;
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
            font-size: 2.2rem;
            font-weight: 500;
            color: var(--text-main);
            letter-spacing: -0.01em;
            margin-bottom: 0.25rem;
        }

        .section-subhead { font-size: 0.875rem; color: var(--text-muted); }

        .btn-back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8125rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: color 0.2s;
        }

        .btn-back:hover { color: var(--color-gold); }

        .alert-error {
            background: rgba(201, 59, 59, 0.15);
            border: 1px solid rgba(201, 59, 59, 0.4);
            color: #ff9999;
            padding: 0.9rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.75rem;
            font-size: 0.875rem;
        }

        /* Cartão do Formulário */
        .form-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 2.25rem;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 680px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }

        .f-group {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-bottom: 1.5rem;
        }

        .f-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #e6e0d4;
        }

        .f-label small { color: var(--text-muted); font-weight: normal; }

        .f-input {
            width: 100%;
            background-color: #0b0a09;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.75rem 1rem;
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

        .f-textarea {
            min-height: 120px;
            resize: vertical;
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
            margin-bottom: 1.75rem;
        }

        .f-checkbox-card:hover { border-color: var(--border-gold); }
        .f-checkbox-card input { width: 18px; height: 18px; accent-color: var(--color-gold); }

        /* Stage de Upload de Fotografia */
        .upload-dropzone {
            border: 2px dashed rgba(179, 138, 84, 0.4);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #11100e;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .upload-dropzone:hover {
            border-color: var(--color-gold);
            background: #161411;
        }

        .upload-preview-stage {
            display: none;
            margin-top: 1.25rem;
            text-align: center;
        }

        .upload-preview-stage img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 2px solid var(--color-gold);
            object-fit: cover;
            box-shadow: 0 4px 14px rgba(0,0,0,0.5);
        }

        .btn-submit {
            background-color: var(--color-gold);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            padding: 0.95rem 2.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(179, 138, 84, 0.3);
        }

        .btn-submit:hover {
            background-color: var(--color-gold-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 22px rgba(179, 138, 84, 0.45);
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
            <span class="badge-role">Admin • Novo Artista</span>
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
        </a>
        <a href="categorias.php" class="tab-btn">
            <span>📁 Categorias</span>
        </a>
        <a href="artistas.php" class="tab-btn active">
            <span>🎨 Artistas</span>
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
                <h1 class="section-headline">Cadastrar Novo Artista</h1>
                <p class="section-subhead">
                    Cadastre o perfil do artista, sua biografia curatorial e fotografia representativa para o catálogo.
                </p>
            </div>
            <a href="artistas.php" class="btn-back">
                ← Voltar a Artistas
            </a>
        </div>

        <?php if (!empty($msgErro)): ?>
            <div class="alert-error">
                ✕ <?= htmlspecialchars($msgErro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="formNovoArtista">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
            <input type="hidden" name="base64_image_data" id="base64_image_data">
            <input type="hidden" name="base64_image_name" id="base64_image_name">

            <div class="form-card">
                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="name" class="f-label">Nome do Artista *</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="f-input" 
                            placeholder="Ex: Candido Portinari, Tarsila do Amaral..." 
                            required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="f-group">
                        <label for="slug" class="f-label">Slug <small>(opcional)</small></label>
                        <input 
                            type="text" 
                            name="slug" 
                            id="slug" 
                            class="f-input" 
                            placeholder="ex: candido-portinari"
                            value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>"
                        >
                        <span class="f-hint">Se deixado em branco, será gerado automaticamente a partir do nome.</span>
                    </div>
                </div>

                <div class="f-group">
                    <label for="biography" class="f-label">Biografia Curatorial</label>
                    <textarea 
                        name="biography" 
                        id="biography" 
                        class="f-input f-textarea" 
                        placeholder="Descreva a formação do artista, período de atuação, linguagens estéticas, exposições e relevância no acervo..."
                    ><?= htmlspecialchars($_POST['biography'] ?? '') ?></textarea>
                </div>

                <!-- Upload de Fotografia do Artista -->
                <div class="f-group">
                    <label class="f-label">Fotografia do Artista <small>(JPG, PNG, WEBP até 10 MB)</small></label>
                    <div class="upload-dropzone" onclick="document.getElementById('artist_image').click()">
                        <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">📷</span>
                        <strong style="color: var(--color-gold);">Clique para escolher o retrato ou foto do artista</strong>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">
                            Recomendado proporção quadrada ou retrato (ex: 600 × 600 px)
                        </p>
                        <input 
                            type="file" 
                            name="artist_image" 
                            id="artist_image" 
                            accept="image/jpeg,image/png,image/webp" 
                            style="display: none;"
                        >
                    </div>

                    <div class="upload-preview-stage" id="previewStage">
                        <img id="previewImg" src="" alt="Prévia da Fotografia">
                        <p style="font-size: 0.72rem; color: #81c784; margin-top: 0.5rem;" id="fileNameTxt">Arquivo selecionado</p>
                    </div>
                </div>

                <label class="f-checkbox-card">
                    <input type="checkbox" name="active" value="1" <?= (!isset($_POST['name']) || !empty($_POST['active'])) ? 'checked' : '' ?>>
                    <div>
                        <strong style="font-size: 0.85rem; color: var(--text-main);">Artista Ativo</strong>
                        <p style="font-size: 0.75rem; color: var(--text-muted);">
                            Visível na galeria e disponível para seleção no cadastro de novas obras.
                        </p>
                    </div>
                </label>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-submit">
                        Salvar Artista
                    </button>
                </div>
            </div>
        </form>

    </main>

    <script>
        const fileInput = document.getElementById('artist_image');
        const previewStage = document.getElementById('previewStage');
        const previewImg = document.getElementById('previewImg');
        const fileNameTxt = document.getElementById('fileNameTxt');
        const base64Data = document.getElementById('base64_image_data');
        const base64Name = document.getElementById('base64_image_name');

        if (fileInput && typeof window.artSaleAttachOptimizer === 'function') {
            window.artSaleAttachOptimizer(fileInput, {
                statusEl: fileNameTxt,
                previewImg: previewImg,
                b64Data: base64Data,
                b64Name: base64Name
            });
        }
    </script>
</body>
</html>
