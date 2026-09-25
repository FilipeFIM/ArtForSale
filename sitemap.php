<?php
/**
 * ART FOR SALE - Sitemap XML Dinâmico com Extensão de Imagens Google
 * Arquivo: /sitemap.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Gera dinamicamente o mapa do site a partir do acervo do Supabase e persistência local:
 * - Página Inicial (Home)
 * - Catálogo Geral de Obras
 * - Categorias Ativas (/pages/categorias.php?slug=...)
 * - Obras Ativas com Imagens do Supabase Storage (/pages/obra.php?id=...)
 */

header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/seo_helper.php';

$baseUrl = function_exists('artsale_get_base_url') ? artsale_get_base_url() : 'http://localhost:8000';
$nowIso = date('Y-m-d');

// 1. Busca categorias ativas
$categorias = function_exists('supabase_buscar_categorias') ? supabase_buscar_categorias(true) : [];
if (empty($categorias) && function_exists('artsale_get_local_categories')) {
    $categorias = artsale_get_local_categories();
}

// 2. Busca obras ativas
$obras = function_exists('supabase_buscar_obras') ? supabase_buscar_obras(null, null, 250, true) : [];
$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
$obras = array_values(array_filter($obras, fn($item) => !in_array((string)$item['id'], $deletedIds, true)));

// Inicia buffer para geração de XML
ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <!-- 1. Página Inicial (Home) -->
    <url>
        <loc><?= htmlspecialchars($baseUrl . '/index.php') ?></loc>
        <lastmod><?= $nowIso ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
        <image:image>
            <image:loc><?= htmlspecialchars(artsale_absolute_url('assets/images/site/about-art-sell.jpg')) ?></image:loc>
            <image:title>Art For Sale | Galeria de Arte</image:title>
            <image:caption>Arte que transforma espaços.</image:caption>
        </image:image>
    </url>

    <!-- 2. Catálogo Geral de Obras -->
    <url>
        <loc><?= htmlspecialchars($baseUrl . '/pages/obras.php') ?></loc>
        <lastmod><?= $nowIso ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- 3. Categorias Ativas do Supabase -->
    <?php foreach ($categorias as $cat): 
        $slug = $cat['slug'] ?? '';
        if (empty($slug)) continue;
        $catNome = $cat['name'] ?? 'Categoria';
        $catImg = !empty($cat['image_url']) ? artsale_absolute_url($cat['image_url']) : '';
    ?>
    <url>
        <loc><?= htmlspecialchars($baseUrl . '/pages/categorias.php?slug=' . urlencode($slug)) ?></loc>
        <lastmod><?= $nowIso ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
        <?php if (!empty($catImg)): ?>
        <image:image>
            <image:loc><?= htmlspecialchars($catImg) ?></image:loc>
            <image:title><?= htmlspecialchars($catNome . ' | Art For Sale') ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>

    <!-- 4. Obras Ativas do Catálogo com Fotos do Supabase Storage -->
    <?php foreach ($obras as $obra): 
        $obraId = (string)($obra['id'] ?? '');
        if (empty($obraId)) continue;
        $obraTitulo = $obra['titulo'] ?? ($obra['name'] ?? 'Obra de Arte');
        $obraArtista = $obra['artista'] ?? 'Artista Convidado';
        $imgObra = !empty($obra['imagem']) ? artsale_absolute_url($obra['imagem']) : '';
    ?>
    <url>
        <loc><?= htmlspecialchars($baseUrl . '/pages/obra.php?id=' . urlencode($obraId)) ?></loc>
        <lastmod><?= $nowIso ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.85</priority>
        <?php if (!empty($imgObra)): ?>
        <image:image>
            <image:loc><?= htmlspecialchars($imgObra) ?></image:loc>
            <image:title><?= htmlspecialchars($obraTitulo . ' - ' . $obraArtista . ' | Art For Sale') ?></image:title>
            <image:caption><?= htmlspecialchars($obraTitulo . ', de ' . $obraArtista . '. Conheça esta obra na Art For Sale.') ?></image:caption>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>

</urlset>
<?php
$xmlOutput = ob_get_clean();

// Salva cópia física no sitemap.xml da raiz para servidores estáticos
@file_put_contents(__DIR__ . '/sitemap.xml', $xmlOutput);

echo $xmlOutput;
