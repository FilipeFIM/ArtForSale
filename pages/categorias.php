<?php
/**
 * ART FOR SALE - Coleções & Categorias do Acervo
 * Página: /pages/categorias.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Requisitos Implementados:
 * - Leitura dinâmica via ?slug={slug} (ex: /pages/categorias.php?slug=paisagens)
 * - Breadcrumb: Início > Categorias > [Nome da Categoria]
 * - Nome e Descrição curatorial detalhada da categoria
 * - Grade dinâmica de obras da categoria com cards de luxo Art For Sale
 * - Preço sempre "Preço sob consulta"
 * - Barra de navegação rápida entre todas as categorias ativas
 * - Suporte a fallback seguro e persistência integrada
 */

$pathPrefix = '../';
$currentPage = 'categorias';

// Redireciona para o catálogo de obras (categorias desativadas na interface)
header('Location: ' . ($pathPrefix ?? '') . 'pages/obras.php', true, 302);
exit;

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/supabase.php';

// 1. Busca todas as categorias ativas
$categorias = supabase_buscar_categorias(true);
if (empty($categorias) && function_exists('artsale_get_local_categories')) {
    $categorias = artsale_get_local_categories();
}

// 2. Determina o slug solicitado
$requestedSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// 3. Localiza a categoria selecionada
$categoriaAtual = null;
if (!empty($requestedSlug)) {
    foreach ($categorias as $cat) {
        if (strtolower($cat['slug'] ?? '') === strtolower($requestedSlug) || (string)($cat['id'] ?? '') === $requestedSlug) {
            $categoriaAtual = $cat;
            break;
        }
    }
}

// Se não encontrou pelo slug ou não foi informado, utiliza a primeira categoria ativa ou um objeto padrão
if (!$categoriaAtual) {
    if (!empty($categorias)) {
        $categoriaAtual = $categorias[0];
    } else {
        $categoriaAtual = [
            'id'          => 'paisagens',
            'name'        => 'Paisagens',
            'slug'        => 'paisagens',
            'description' => 'Horizontes lumínicos, estudos atmosféricos e contemplação lírica da natureza em grandes formatos.',
            'image_url'   => 'assets/images/obras/cat-paisagens.jpg',
            'active'      => true
        ];
    }
}

$catNome = $categoriaAtual['name'] ?? 'Categoria';
$catSlug = $categoriaAtual['slug'] ?? 'paisagens';
$catDesc = $categoriaAtual['description'] ?? 'Curadoria exclusiva de obras originais com alto rigor técnico e expressividade estética.';
$catImg  = !empty($categoriaAtual['image_url']) ? artsale_resolve_image_url($categoriaAtual['image_url'], '../') : '';

// 4. Busca obras ativas do acervo
$todasObras = function_exists('supabase_buscar_obras') ? supabase_buscar_obras(null, null, 150, true) : [];
if (!is_array($todasObras)) {
    $todasObras = [];
}
$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
if (!is_array($deletedIds)) {
    $deletedIds = [];
}
$todasObras = array_values(array_filter($todasObras, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

// 5. Filtra obras pertencentes a esta categoria
$obrasCategoria = array_values(array_filter($todasObras, function($o) use ($categoriaAtual, $catSlug, $catNome) {
    $itemSlug = strtolower(trim($o['categoria_slug'] ?? ''));
    $itemNome = strtolower(trim($o['categoria'] ?? ''));
    $targetSlug = strtolower(trim($catSlug));
    $targetNome = strtolower(trim($catNome));

    if ($itemSlug === $targetSlug) return true;
    if ($itemNome === $targetNome) return true;

    if (!empty($o['categoria_id']) && !empty($categoriaAtual['id']) && (string)$o['categoria_id'] === (string)$categoriaAtual['id']) {
        return true;
    }

    $cleanTarget = str_replace('-', ' ', $targetSlug);
    if (!empty($cleanTarget) && (stripos($itemNome, $cleanTarget) !== false || stripos($itemSlug, $targetSlug) !== false)) {
        return true;
    }

    return false;
}));

// ==============================================================================
// SEO Dinâmico de Categorias Art For Sale
// ==============================================================================
$catCanonical = function_exists('artsale_absolute_url')
    ? artsale_absolute_url('pages/categorias.php?slug=' . urlencode($catSlug))
    : "http://localhost:8000/pages/categorias.php?slug={$catSlug}";
$catOgImage = !empty($catImg) ? (function_exists('artsale_absolute_url') ? artsale_absolute_url($catImg) : $catImg) : (function_exists('artsale_absolute_url') ? artsale_absolute_url('assets/images/site/about-art-sell.jpg') : '');

$seoMeta = [
    'title'          => "{$catNome} | Coleções Art For Sale",
    'description'    => "Conheça as obras de {$catNome} na Art For Sale. {$catDesc}",
    'canonical'      => $catCanonical,
    'og_type'        => 'website',
    'og_title'       => "{$catNome} | Coleções Art For Sale",
    'og_description' => "Conheça as obras de {$catNome} na Art For Sale. {$catDesc}",
    'og_image'       => $catOgImage,
    'og_url'         => $catCanonical,
    'schemas'        => [
        function_exists('artsale_schema_breadcrumb') ? artsale_schema_breadcrumb([
            ['name' => 'Início', 'url' => artsale_absolute_url('index.php')],
            ['name' => 'Categorias', 'url' => artsale_absolute_url('pages/categorias.php')],
            ['name' => $catNome, 'url' => $catCanonical]
        ]) : []
    ]
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="site-main categoria-page">

    <!-- ========================================================
         BREADCRUMB & CABEÇALHO EDITORIAL DA CATEGORIA
         ======================================================== -->
    <section class="categoria-hero-section">
        <div class="section-container">
            
            <!-- Breadcrumb Conforme Requisito: Início > Categorias > [Nome] -->
            <nav class="catalogo-breadcrumb" aria-label="Navegação estrutural">
                <a href="../index.php" class="breadcrumb-link">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="../index.php#categorias" class="breadcrumb-link">Categorias</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current"><?= htmlspecialchars($catNome) ?></span>
            </nav>

            <div class="categoria-header-grid">
                <div class="categoria-header-text">
                    <span class="categoria-eyebrow">COLEÇÃO CURATORIAL • CATEGORIA</span>
                    <h1 class="categoria-title"><?= htmlspecialchars($catNome) ?></h1>
                    
                    <?php if (!empty($catDesc)): ?>
                        <p class="categoria-description">
                            <?= nl2br(htmlspecialchars($catDesc)) ?>
                        </p>
                    <?php endif; ?>

                    <div class="categoria-meta-badge">
                        <span>Acervo Disponível:</span>
                        <strong><?= count($obrasCategoria) ?> <?= count($obrasCategoria) === 1 ? 'obra original' : 'obras originais' ?></strong>
                    </div>
                </div>

                <?php if (!empty($catImg)): ?>
                <div class="categoria-header-image-box">
                    <img 
                        src="<?= htmlspecialchars(artsale_get_thumbnail_url($catImg, 640, 420, 85, '../')) ?>" 
                        alt="<?= htmlspecialchars($catNome) ?>" 
                        class="categoria-cover-image"
                        width="480"
                        height="320"
                        loading="lazy"
                        decoding="async"
                        onerror="this.style.display='none';"
                    >
                </div>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- Barra de Navegação Horizontal entre Categorias -->
    <section class="categoria-nav-bar">
        <div class="section-container">
            <div class="categoria-pills-scroll" role="tablist" aria-label="Todas as Categorias">
                <?php foreach ($categorias as $cat): 
                    $cSlug = $cat['slug'] ?? '';
                    $cNome = $cat['name'] ?? '';
                    $isActive = (strtolower($cSlug) === strtolower($catSlug));
                ?>
                    <a 
                        href="categorias.php?slug=<?= urlencode($cSlug) ?>" 
                        class="categoria-nav-pill <?= $isActive ? 'active' : '' ?>"
                        role="tab"
                        aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                    >
                        <?= htmlspecialchars($cNome) ?>
                    </a>
                <?php endforeach; ?>
                <a href="obras.php" class="categoria-nav-pill-all">
                    Ver Todo o Acervo →
                </a>
            </div>
        </div>
    </section>

    <!-- Transição Estética -->
    <div class="brush-transition-accent" aria-hidden="true"></div>

    <!-- ========================================================
         GRADE DE OBRAS DA CATEGORIA
         ======================================================== -->
    <section class="categoria-artworks-section">
        <div class="section-container">

            <div class="categoria-section-header">
                <div>
                    <h2 class="section-title">Obras em <?= htmlspecialchars($catNome) ?></h2>
                    <p class="section-subtitle">Peças selecionadas com rigor técnico e procedência certificada.</p>
                </div>
            </div>

            <?php if (!empty($obrasCategoria)): ?>
                <div class="catalogo-grid">
                    <?php foreach ($obrasCategoria as $obra): 
                        $imgObra = artsale_resolve_image_url($obra['imagem'] ?? '', '../');
                        $dimensoes = $obra['dimensoes'] ?? '80 × 120 cm';
                    ?>
                        <article 
                            class="artwork-card" 
                            data-id="<?= htmlspecialchars((string)$obra['id']) ?>"
                            data-category="<?= htmlspecialchars($obra['categoria_slug'] ?? $catSlug) ?>"
                        >
                            <div class="artwork-image-container">
                                <a href="obra.php?id=<?= urlencode((string)$obra['id']) ?>" class="artwork-image-link" aria-label="Ver detalhes de <?= htmlspecialchars($obra['titulo']) ?>">
                                    <img 
                                        src="<?= htmlspecialchars(artsale_get_thumbnail_url($obra['imagem'] ?? '', 480, 300, 82, '../')) ?>" 
                                        alt="<?= htmlspecialchars($obra['titulo']) ?> por <?= htmlspecialchars($obra['artista']) ?>" 
                                        class="artwork-image"
                                        width="400"
                                        height="250"
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.onerror=null; this.src='../assets/images/obras/placeholder-obra.svg';"
                                    >
                                </a>
                                <span class="artwork-category-tag"><?= htmlspecialchars($obra['categoria'] ?? $catNome) ?></span>
                                
                                <!-- Botão de Favoritar -->
                                <button type="button" class="btn-favorite" title="Adicionar aos favoritos" aria-label="Favoritar <?= htmlspecialchars($obra['titulo']) ?>" data-id="<?= htmlspecialchars((string)$obra['id']) ?>">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="artwork-details">
                                <h3 class="artwork-title">
                                    <a href="obra.php?id=<?= urlencode((string)$obra['id']) ?>">
                                        <?= htmlspecialchars($obra['titulo']) ?>
                                    </a>
                                </h3>
                                <p class="artwork-artist"><?= htmlspecialchars($obra['artista']) ?></p>
                                <p class="artwork-dimensions"><?= htmlspecialchars($dimensoes) ?></p>
                                
                                <div class="artwork-footer-row">
                                    <span class="artwork-price">Preço sob consulta</span>
                                    <a href="obra.php?id=<?= urlencode((string)$obra['id']) ?>" class="btn-ver-detalhes" data-id="<?= htmlspecialchars((string)$obra['id']) ?>">
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Estado Vazio Amigável -->
                <div class="categoria-empty-box">
                    <div class="empty-icon-circle">🎨</div>
                    <h3 class="empty-title">Nenhuma obra disponível no momento em <?= htmlspecialchars($catNome) ?></h3>
                    <p class="empty-text">
                        Todas as peças desta coleção encontram-se atualmente em acervos particulares ou em processo de certificação curatorial.
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem;">
                        <a href="obras.php" class="btn-hero-primary" style="padding: 0.85rem 1.75rem; font-size: 0.875rem;">
                            Explorar Todas as Obras
                        </a>
                        <a href="../index.php#contato" class="btn-hero-secondary" style="padding: 0.85rem 1.75rem; font-size: 0.875rem;">
                            Consultar Curadoria
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

</main>

<style>
/* Estilos Específicos da Página de Categorias */
.categoria-hero-section {
    padding: 3rem 0 2.5rem;
    background: radial-gradient(circle at top right, rgba(179, 138, 84, 0.08) 0%, rgba(14, 13, 11, 0) 65%);
    border-bottom: 1px solid var(--color-border);
}

.categoria-header-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 3rem;
    align-items: center;
    margin-top: 1.5rem;
}

@media (max-width: 900px) {
    .categoria-header-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}

.categoria-eyebrow {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--color-gold);
    display: block;
    margin-bottom: 0.5rem;
}

.categoria-title {
    font-family: var(--font-serif);
    font-size: clamp(2.4rem, 4.5vw, 3.6rem);
    font-weight: 500;
    color: var(--color-text-main);
    line-height: 1.1;
    letter-spacing: -0.01em;
    margin-bottom: 1.25rem;
}

.categoria-description {
    font-size: 1.05rem;
    line-height: 1.7;
    color: var(--color-text-muted);
    max-width: 680px;
    margin-bottom: 1.5rem;
}

.categoria-meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(179, 138, 84, 0.08);
    border: 1px solid rgba(179, 138, 84, 0.25);
    border-radius: 20px;
    padding: 0.4rem 1rem;
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}

.categoria-meta-badge strong {
    color: var(--color-gold);
}

.categoria-header-image-box {
    width: 100%;
    height: 220px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(179, 138, 84, 0.3);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5);
}

.categoria-cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.categoria-cover-image:hover {
    transform: scale(1.03);
}

/* Barra de Navegação Horizontal */
.categoria-nav-bar {
    background-color: #12110e;
    border-bottom: 1px solid var(--color-border);
    padding: 0.9rem 0;
}

.categoria-pills-scroll {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    padding: 0.25rem 0;
}

.categoria-pills-scroll::-webkit-scrollbar {
    display: none;
}

.categoria-nav-pill {
    padding: 0.5rem 1.15rem;
    border-radius: 20px;
    border: 1px solid var(--color-border);
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-text-muted);
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s;
    background: transparent;
}

.categoria-nav-pill:hover {
    color: var(--color-text-main);
    border-color: var(--color-gold);
}

.categoria-nav-pill.active {
    background-color: var(--color-gold);
    color: #ffffff;
    border-color: var(--color-gold);
    font-weight: 600;
}

.categoria-nav-pill-all {
    margin-left: auto;
    font-size: 0.8125rem;
    color: var(--color-gold);
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
    padding: 0.5rem 0.75rem;
    transition: color 0.2s;
}

.categoria-nav-pill-all:hover {
    color: var(--color-gold-hover);
}

.categoria-artworks-section {
    padding: 3.5rem 0 5rem;
}

.categoria-section-header {
    margin-bottom: 2.5rem;
}

.categoria-empty-box {
    background-color: var(--color-bg-card);
    border: 1px dashed rgba(179, 138, 84, 0.3);
    border-radius: 8px;
    padding: 4rem 2rem;
    text-align: center;
    max-width: 640px;
    margin: 2rem auto;
}

.empty-icon-circle {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.empty-title {
    font-family: var(--font-serif);
    font-size: 1.6rem;
    font-weight: 500;
    color: var(--color-text-main);
    margin-bottom: 0.5rem;
}

.empty-text {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    line-height: 1.6;
}
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
