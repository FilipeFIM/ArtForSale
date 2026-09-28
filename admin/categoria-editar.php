<?php
/**
 * ART FOR SALE - Editar Categoria (Painel Administrativo)
 * Arquivo: /admin/categoria-editar.php
 * Slogan: "Arte que transforma espaços."
 */

$pathPrefix = '../';
require_once __DIR__ . '/auth_check.php';
require_admin_auth();

$user = get_admin_user();
$adminToken = get_admin_token();

$categoryId = trim($_GET['id'] ?? '');
if (empty($categoryId)) {
    header('Location: categorias.php');
    exit;
}

$categoria = supabase_buscar_categoria($categoryId, $adminToken);
if (!$categoria) {
    header('Location: categorias.php?msg=not_found');
    exit;
}

$msgErro = '';
$msgSucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $nome = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $active = !empty($_POST['active']);

    if (empty($nome)) {
        $msgErro = 'O Nome da categoria é obrigatório.';
    } else {
        $hasNewImage = false;
        $tmpImagePath = '';
        $imageOriginalName = '';
        $isTemporaryCreated = false;

        $file = $_FILES['category_image'] ?? null;
        $base64Data = $_POST['base64_image_data'] ?? '';
        $base64Name = $_POST['base64_image_name'] ?? 'categoria.jpg';

        if (!empty($file) && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $val = supabase_validar_arquivo_imagem($file);
                if (!$val['valido']) {
                    $msgErro = $val['erro'];
                } else {
                    $hasNewImage = true;
                    $tmpImagePath = $file['tmp_name'];
                    $imageOriginalName = $file['name'];
                }
            }
        } elseif (!empty($base64Data) && str_starts_with($base64Data, 'data:image/')) {
            $parts = explode(',', $base64Data, 2);
            if (count($parts) === 2) {
                $bin = base64_decode($parts[1]);
                if (!empty($bin)) {
                    $tmpFile = tempnam(sys_get_temp_dir(), 'cat_edit_img_');
                    file_put_contents($tmpFile, $bin);
                    $val = supabase_validar_arquivo_imagem(['tmp_name' => $tmpFile, 'name' => $base64Name, 'size' => strlen($bin)]);
                    if (!$val['valido']) {
                        $msgErro = 'Erro na imagem (Base64): ' . $val['erro'];
                        @unlink($tmpFile);
                    } else {
                        $hasNewImage = true;
                        $tmpImagePath = $tmpFile;
                        $imageOriginalName = $base64Name;
                        $isTemporaryCreated = true;
                    }
                }
            }
        }

        if (empty($msgErro)) {
            $dados = [
                'name'        => $nome,
                'slug'        => $slug ?: $categoria['slug'],
                'description' => $description,
                'active'      => $active
            ];

            $res = supabase_atualizar_categoria($categoria['id'], $dados, $adminToken);

            if ($hasNewImage && !empty($tmpImagePath)) {
                $upRes = supabase_upload_imagem_categoria(
                    $tmpImagePath,
                    $categoria['id'],
                    $imageOriginalName ?: 'categoria.jpg',
                    $adminToken
                );

                if ($isTemporaryCreated && file_exists($tmpImagePath)) {
                    @unlink($tmpImagePath);
                }

                if (empty($upRes['sucesso'])) {
                    $msgErro = 'Erro no upload da imagem: ' . ($upRes['erro'] ?? 'Falha de envio.');
                }
            }

            if (empty($msgErro)) {
                header("Location: categorias.php?msg=updated");
                exit;
            }
        }
    }
}

// Recarrega categoria atualizada
$categoria = supabase_buscar_categoria($categoryId, $adminToken);
$currentImg = artsale_resolve_image_url($categoria['image_url'] ?? '', '../');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoria — Painel Art For Sale</title>
    
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
        :root {
            --bg-dark: #faf8f5;
            --bg-topbar: #ffffff;
            --bg-tabs: #ffffff;
            --bg-card: #ffffff;
            --bg-card-alt: #f4ede2;
            --color-gold: #b38a54;
            --color-gold-hover: #9c733e;
            --color-gold-subtle: rgba(179, 138, 84, 0.08);
            --text-main: #1c1b18;
            --text-muted: #6e675f;
            --border-color: #e8e2d8;
            --border-gold: rgba(179, 138, 84, 0.4);
            --color-danger: #c93b3b;
            --color-success: #2e7d32;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

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

        .brand-box { display: flex; align-items: center; gap: 1rem; }
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

        .user-nav { display: flex; align-items: center; gap: 1.5rem; }
        .user-email-label { font-size: 0.8125rem; color: var(--text-muted); }
        .btn-link-site { color: var(--color-gold); text-decoration: none; font-size: 0.8125rem; font-weight: 600; }
        .btn-logout-link { color: var(--text-muted); text-decoration: none; font-size: 0.8125rem; }

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

        .tab-btn:hover { color: var(--text-main); }
        .tab-btn.active { color: var(--color-gold); border-bottom-color: var(--color-gold); background: rgba(179, 138, 84, 0.04); }

        .admin-content {
            padding: 2.25rem 2rem;
            flex-grow: 1;
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
            line-height: 1.15;
            margin-bottom: 0.35rem;
        }

        .section-subhead { font-size: 0.875rem; color: var(--text-muted); }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8125rem;
            border: 1px solid var(--border-color);
            padding: 0.55rem 1rem;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-back:hover { color: #ffffff; border-color: var(--border-gold); }

        .alert-error {
            background: rgba(201, 59, 59, 0.15);
            border: 1px solid var(--color-danger);
            color: #ff9999;
            padding: 0.85rem 1.25rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.75rem;
        }

        .form-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 768px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }

        .f-group {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-bottom: 1.25rem;
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
            border-radius: 4px;
            padding: 0.85rem 1.1rem;
            color: var(--text-main);
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
            min-height: 110px;
            resize: vertical;
        }

        .f-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .current-image-preview-box {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1rem;
            background: var(--bg-card-alt);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .current-image-preview-box img {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border-gold);
        }

        .upload-dropzone {
            border: 2px dashed rgba(179, 138, 84, 0.4);
            border-radius: 8px;
            padding: 1.75rem;
            text-align: center;
            background: var(--bg-card-alt);
            cursor: pointer;
            transition: all 0.2s;
        }

        .upload-dropzone:hover {
            border-color: var(--color-gold);
            background: rgba(179, 138, 84, 0.08);
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
            margin-bottom: 1.5rem;
        }

        .f-checkbox-card:hover { border-color: var(--border-gold); }
        .f-checkbox-card input { width: 18px; height: 18px; accent-color: var(--color-gold); }

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
            <span class="badge-role">Admin • Editar Categoria</span>
        </div>

        <nav class="user-nav">
            <span class="user-email-label">
                Curador: <strong><?= htmlspecialchars($user['email']) ?></strong>
            </span>
            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleAdminTheme()" title="Alternar tema visual (Claro / Escuro)">
                <span id="themeToggleIcon">🌙</span>
                <span id="themeToggleLabel">Escuro</span>
            </button>
            <a href="../pages/categorias.php?slug=<?= urlencode($categoria['slug']) ?>" target="_blank" class="btn-link-site">Ver no Site ↗</a>
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
        <a href="categorias.php" class="tab-btn active">
            <span>📁 Categorias</span>
        </a>
        <a href="artistas.php" class="tab-btn">
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
                <h1 class="section-headline">Editar Categoria: <?= htmlspecialchars($categoria['name']) ?></h1>
                <p class="section-subhead">
                    Atualize os dados, a fotografia representativa e a publicação da categoria.
                </p>
            </div>
            <a href="categorias.php" class="btn-back">
                ← Voltar a Categorias
            </a>
        </div>

        <?php if (!empty($msgErro)): ?>
            <div class="alert-error">
                ✕ <?= htmlspecialchars($msgErro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="formEditarCategoria">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(artsale_get_csrf_token()) ?>">
            <input type="hidden" name="base64_image_data" id="base64_image_data">
            <input type="hidden" name="base64_image_name" id="base64_image_name">

            <div class="form-card">
                <div class="form-grid-2">
                    <div class="f-group">
                        <label for="name" class="f-label">Nome da Categoria *</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="f-input" 
                            required
                            value="<?= htmlspecialchars($categoria['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="f-group">
                        <label for="slug" class="f-label">Slug</label>
                        <input 
                            type="text" 
                            name="slug" 
                            id="slug" 
                            class="f-input" 
                            value="<?= htmlspecialchars($categoria['slug'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="f-group">
                    <label for="description" class="f-label">Descrição Curatorial</label>
                    <textarea 
                        name="description" 
                        id="description" 
                        class="f-input f-textarea"
                    ><?= htmlspecialchars($categoria['description'] ?? '') ?></textarea>
                </div>

                <!-- Imagem Atual e Substituição -->
                <div class="f-group">
                    <label class="f-label">Fotografia da Categoria</label>
                    
                    <?php if (!empty($categoria['image_url'])): ?>
                        <div class="current-image-preview-box">
                            <img src="<?= htmlspecialchars($currentImg) ?>" alt="<?= htmlspecialchars($categoria['name']) ?>" id="catCurrentImg">
                            <div>
                                <strong style="font-size: 0.8125rem; color: var(--text-main);">Fotografia Atual</strong>
                                <p style="font-size: 0.72rem; color: var(--text-muted); word-break: break-all;">
                                    <?= htmlspecialchars($categoria['image_url']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="upload-dropzone" onclick="document.getElementById('category_image').click()">
                        <span style="font-size: 1.5rem; display: block; margin-bottom: 0.25rem;">🔄</span>
                        <strong style="color: var(--color-gold);">Clique para substituir a imagem</strong>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                            Envie JPG, PNG ou WEBP se desejar alterar a imagem atual
                        </p>
                        <input 
                            type="file" 
                            name="category_image" 
                            id="category_image" 
                            accept="image/jpeg,image/png,image/webp" 
                            style="display: none;"
                        >
                    </div>
                    <p style="font-size: 0.75rem; color: #81c784; margin-top: 0.5rem; display:none;" id="fileNameTxt"></p>
                </div>

                <label class="f-checkbox-card">
                    <input type="checkbox" name="active" value="1" <?= (!isset($categoria['active']) || !empty($categoria['active'])) ? 'checked' : '' ?>>
                    <div>
                        <strong style="font-size: 0.85rem; color: var(--text-main);">Categoria Ativa</strong>
                        <p style="font-size: 0.75rem; color: var(--text-muted);">
                            Visível na navegação do site e disponível para associação de obras.
                        </p>
                    </div>
                </label>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-submit">
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </form>

    </main>

    <script>
        const fileInput = document.getElementById('category_image');
        const fileNameTxt = document.getElementById('fileNameTxt');
        const previewImg = document.getElementById('catCurrentImg');
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
