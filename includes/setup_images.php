<?php
/**
 * ART FOR SALE - Processador e Extrator de Imagens do Mockup
 * Garante que todas as imagens do mockup de referência sejam extraídas e disponibilizadas
 * nas pastas /assets/images/site/ e /assets/images/obras/
 */

// Criar pastas de imagens se não existirem
$dirsToCreate = [
    ASSETS_PATH . '/images',
    IMAGES_PATH . '/site',
    IMAGES_PATH . '/obras',
    BASE_PATH . '/pages'
];

foreach ($dirsToCreate as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Caminho do mockup original (uploaded) e destino local
$sourceUploadPaths = [
    'C:/Users/Enio/.gemini/antigravity/brain/8fe1b026-5aa9-4960-9ace-f628732a93b5/.user_uploaded/media_1790300585674.jpg',
    'C:/Users/Enio/.gemini/antigravity/brain/ce408758-2b8a-4827-8ad5-6d6ab8b059a3/.user_uploaded/media_1790217196741.jpg'
];
$localMockupPath = SITE_IMG_PATH . '/mockup_original.jpg';

// Copia o arquivo original mais recente para dentro do projeto se ainda não existir
foreach ($sourceUploadPaths as $sup) {
    if (file_exists($sup)) {
        if (!file_exists($localMockupPath) || filesize($localMockupPath) < 10000) {
            @copy($sup, $localMockupPath);
        }
        break;
    }
}

// Coordenadas relativas de corte (x%, y%, w%, h%) baseadas na imagem de referência
$cropDefinitions = [
    // Banner Hero
    SITE_IMG_PATH . '/hero-bg.jpg' => [
        'x' => 0.0, 'y' => 0.038, 'w' => 1.0, 'h' => 0.280
    ],
    // 8 Categorias
    OBRAS_PATH . '/cat-arte-classica.jpg' => [
        'x' => 0.146, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-arte-moderna.jpg' => [
        'x' => 0.235, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-arte-contemporanea.jpg' => [
        'x' => 0.324, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-paisagens.jpg' => [
        'x' => 0.413, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-retratos.jpg' => [
        'x' => 0.503, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-abstrato.jpg' => [
        'x' => 0.593, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-gravuras.jpg' => [
        'x' => 0.683, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    OBRAS_PATH . '/cat-esculturas.jpg' => [
        'x' => 0.772, 'y' => 0.369, 'w' => 0.081, 'h' => 0.072
    ],
    // 4 Obras em Destaque
    OBRAS_PATH . '/obra-horizontes-dourados.jpg' => [
        'x' => 0.146, 'y' => 0.514, 'w' => 0.170, 'h' => 0.087
    ],
    OBRAS_PATH . '/obra-silencio-urbano.jpg' => [
        'x' => 0.325, 'y' => 0.514, 'w' => 0.170, 'h' => 0.087
    ],
    OBRAS_PATH . '/obra-essencia.jpg' => [
        'x' => 0.504, 'y' => 0.514, 'w' => 0.170, 'h' => 0.087
    ],
    OBRAS_PATH . '/obra-azul-profundo.jpg' => [
        'x' => 0.683, 'y' => 0.514, 'w' => 0.170, 'h' => 0.087
    ],
    // Seção A Art For Sale (foto lateral esquerda)
    SITE_IMG_PATH . '/about-art-sell.jpg' => [
        'x' => 0.0, 'y' => 0.684, 'w' => 0.395, 'h' => 0.129
    ],
    // Banner Fale com nossa equipe (fundo escuro com escultura à esquerda)
    SITE_IMG_PATH . '/team-banner-bg.jpg' => [
        'x' => 0.0, 'y' => 0.813, 'w' => 0.440, 'h' => 0.100
    ]
];

// Se tiver a extensão GD e a imagem base existir, corta as imagens automaticamente
$baseSourceFile = file_exists($localMockupPath) ? $localMockupPath : (file_exists($sourceUploadPath) ? $sourceUploadPath : null);

if ($baseSourceFile && extension_loaded('gd')) {
    $needsCropping = false;
    foreach ($cropDefinitions as $targetPath => $coords) {
        if (!file_exists($targetPath)) {
            $needsCropping = true;
            break;
        }
    }

    if ($needsCropping) {
        $sourceImage = @imagecreatefromjpeg($baseSourceFile);
        if ($sourceImage) {
            $origW = imagesx($sourceImage);
            $origH = imagesy($sourceImage);

            foreach ($cropDefinitions as $targetPath => $coords) {
                if (!file_exists($targetPath)) {
                    $cropX = (int) round($coords['x'] * $origW);
                    $cropY = (int) round($coords['y'] * $origH);
                    $cropW = (int) round($coords['w'] * $origW);
                    $cropH = (int) round($coords['h'] * $origH);

                    // Garante que não ultrapasse as dimensões
                    if ($cropX + $cropW > $origW) $cropW = $origW - $cropX;
                    if ($cropY + $cropH > $origH) $cropH = $origH - $cropY;

                    $cropRect = ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH];
                    $croppedImg = @imagecrop($sourceImage, $cropRect);

                    if ($croppedImg) {
                        @imagejpeg($croppedImg, $targetPath, 92);
                        imagedestroy($croppedImg);
                    }
                }
            }
            imagedestroy($sourceImage);
        }
    }
}

