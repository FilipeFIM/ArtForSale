<?php
/**
 * ART FOR SALE - Script de Inicialização e Diagnóstico
 * Executa a cópia do mockup e extração das imagens para /assets/images/
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>ART FOR SALE — Diagnóstico de Inicialização</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #faf8f5; color: #1c1b18; padding: 2rem; }
        .card { max-width: 680px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e8e3d8; }
        h1 { font-family: Georgia, serif; color: #b38a54; margin-bottom: 0.5rem; }
        .status-item { padding: 0.75rem 0; border-bottom: 1px solid #f0ece3; display: flex; justify-content: space-between; align-items: center; }
        .status-ok { color: #2e7d32; font-weight: bold; }
        .status-warn { color: #f57c00; font-weight: bold; }
        .btn { display: inline-block; background: #b38a54; color: #fff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 4px; margin-top: 1.5rem; font-weight: 600; }
        .preview-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 1.5rem; }
        .preview-thumb { width: 100%; border-radius: 4px; border: 1px solid #ddd; aspect-ratio: 1; object-fit: cover; }
    </style>
</head>
<body>
    <div class="card">
        <h1>ART FOR SALE — Diagnóstico de Assets</h1>
        <p>Verificação do ambiente PHP e extração automática das imagens do mockup.</p>
        
        <div class="status-item">
            <span>Versão do PHP:</span>
            <span class="status-ok"><?= phpversion(); ?></span>
        </div>
        
        <div class="status-item">
            <span>Extensão GD (Processamento de Imagens):</span>
            <span class="<?= extension_loaded('gd') ? 'status-ok' : 'status-warn' ?>">
                <?= extension_loaded('gd') ? 'Ativada (Pronta para recortar)' : 'Desativada (Fallback via Canvas ativo)' ?>
            </span>
        </div>

        <div class="status-item">
            <span>Mockup Original de Referência:</span>
            <span class="<?= file_exists($localMockupPath) ? 'status-ok' : 'status-warn' ?>">
                <?= file_exists($localMockupPath) ? 'Presente em /assets/images/site/' : 'Pendente de cópia' ?>
            </span>
        </div>

        <h3 style="margin-top: 1.5rem; font-size: 1.1rem;">Galeria de Imagens Recortadas:</h3>
        <div class="preview-grid">
            <?php foreach ($cropDefinitions as $path => $coords): 
                $rel = str_replace(BASE_PATH . '/', '', $path);
                $activePath = get_image_url($rel);
                $exists = file_exists(BASE_PATH . '/' . $activePath);
            ?>
                <div style="text-align: center;">
                    <?php if ($exists): ?>
                        <img src="<?= $activePath ?>" class="preview-thumb" alt="Preview">
                        <span style="font-size: 10px; color: #2e7d32;">✓ Ativo (<?= strtoupper(pathinfo($activePath, PATHINFO_EXTENSION)) ?>)</span>
                    <?php else: ?>
                        <div style="background: #eee; height: 70px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #888;">Pendente</div>
                        <span style="font-size: 10px; color: #888;"><?= basename($path) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="btn">Acessar a Home da ART FOR SALE →</a>
        </div>
    </div>
</body>
</html>

