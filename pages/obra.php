<?php
/**
 * ART FOR SALE - Detalhes da Obra
 * Página: /pages/obra.php
 * Tecnologias: PHP 8+, HTML5, CSS3, JavaScript Vanilla
 * Fidelidade máxima à identidade visual premium e refinamento da galeria.
 */

$pathPrefix = '../';
$currentPage = 'obras';

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/supabase.php';

// Resgata obra solicitada via ?id=... ou ?slug=...
$obraId = isset($_GET['id']) ? trim($_GET['id']) : (isset($_GET['slug']) ? trim($_GET['slug']) : '');
$obra = null;

if (!empty($obraId) && function_exists('supabase_buscar_obra')) {
    $obra = supabase_buscar_obra($obraId);
}
$catalogoObras = [];
if (function_exists('supabase_buscar_obras')) {
    $catalogoObras = supabase_buscar_obras(null, null, 24);
}
if (!is_array($catalogoObras)) {
    $catalogoObras = [];
}

if (!$obra && function_exists('artsale_get_local_artworks')) {
    $locais = artsale_get_local_artworks();
    foreach ($locais as $loc) {
        if ((string)($loc['id'] ?? '') === (string)$obraId || (string)($loc['slug'] ?? '') === (string)$obraId) {
            $obra = $loc;
            break;
        }
    }
}

if (!$obra && !empty($obraId)) {
    $obra = get_obra_by_id($obraId);
}

$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
if (!is_array($deletedIds)) {
    $deletedIds = [];
}
if ($obra && in_array((string)($obra['id'] ?? ''), $deletedIds, true)) {
    $obra = null;
}
$catalogoObras = array_values(array_filter($catalogoObras, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

if (!$obra) {
    if (!empty($catalogoObras)) {
        $obra = $catalogoObras[0];
    } else {
        header('Location: obras.php');
        exit;
    }
}

// Normaliza imagem principal da obra
$obra['imagem'] = artsale_resolve_image_url($obra['imagem'] ?? '', '../');

// Obter TODAS as fotos da galeria da obra (com a imagem principal em 1º lugar)
$galeria = !empty($obra['galeria']) ? $obra['galeria'] : [
    ['tipo' => 'principal', 'titulo' => 'Fotografia Principal', 'imagem' => $obra['imagem']]
];

// Garantir que cada foto da galeria esteja devidamente resolvida para o caminho ../ ou Supabase
$galeria = array_map(function($g) {
    if (is_array($g)) {
        $g['imagem'] = artsale_resolve_image_url($g['imagem'] ?? '', '../');
    }
    return $g;
}, $galeria);

// Obras para a seção de recomendações no rodapé (outras obras do acervo)
$outrasObras = array_filter($catalogoObras, function($item) use ($obra) {
    return (string)$item['id'] !== (string)$obra['id'];
});
$obrasRelacionadas = array_slice($outrasObras, 0, 4);
foreach ($obrasRelacionadas as &$rel) {
    $rel['imagem'] = artsale_resolve_image_url($rel['imagem'] ?? '', '../');
}
unset($rel);

// ==============================================================================
// SEO Dinâmico Art For Sale (Supabase, Open Graph & Schema.org)
// ==============================================================================
$obraTitulo = $obra['titulo'] ?? $obra['name'] ?? 'Obra de Arte';
$obraArtista = $obra['artista'] ?? 'Artista Convidado';
$obraCanonical = function_exists('artsale_absolute_url') 
    ? artsale_absolute_url('pages/obra.php?id=' . urlencode((string)$obra['id'])) 
    : "http://localhost:8000/pages/obra.php?id={$obra['id']}";
$obraOgImage = function_exists('artsale_absolute_url') 
    ? artsale_absolute_url($obra['imagem']) 
    : $obra['imagem'];

$seoMeta = [
    'title'          => "{$obraTitulo} | Art For Sale",
    'description'    => "{$obraTitulo}, de {$obraArtista}. Conheça esta obra na Art For Sale.",
    'canonical'      => $obraCanonical,
    'og_type'        => 'article',
    'og_title'       => "{$obraTitulo} | Art For Sale",
    'og_description' => "{$obraTitulo}, de {$obraArtista}. Conheça esta obra na Art For Sale.",
    'og_image'       => $obraOgImage,
    'og_url'         => $obraCanonical,
    'schemas'        => [
        function_exists('artsale_schema_visual_artwork') 
            ? artsale_schema_visual_artwork($obra, $obraCanonical, $obraOgImage) 
            : [],
        function_exists('artsale_schema_breadcrumb') ? artsale_schema_breadcrumb([
            ['name' => 'Início', 'url' => artsale_absolute_url('index.php')],
            ['name' => 'Obras', 'url' => artsale_absolute_url('pages/obras.php')],
            ['name' => $obraTitulo, 'url' => $obraCanonical]
        ]) : []
    ]
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="site-main obra-detail-page">

    <!-- ========================================================
         BREADCRUMB / NAVEGAÇÃO ESTRUTURAL
         ======================================================== -->
    <div class="obra-breadcrumb-bar">
        <div class="section-container">
            <nav class="catalogo-breadcrumb" aria-label="Navegação estrutural">
                <a href="../index.php" class="breadcrumb-link">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="obras.php" class="breadcrumb-link">Obras</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current"><?= htmlspecialchars($obra['titulo']) ?></span>
            </nav>
        </div>
    </div>

    <!-- ========================================================
         SEÇÃO PRINCIPAL DA OBRA (LAYOUT 2 COLUNAS DESKTOP)
         ======================================================== -->
    <section class="obra-main-section">
        <div class="section-container">
            <div class="obra-layout-grid">

                <!-- ====================================================
                     COLUNA ESQUERDA: GALERIA DE IMAGENS E ZOOM
                     ==================================================== -->
                <div class="obra-gallery-col">
                    
                    <!-- Container da Imagem Principal Grande -->
                    <div class="obra-stage-wrapper">
                        <div class="obra-stage-inner" id="obraStage">
                            
                            <!-- Imagem Principal Ativa -->
                            <img 
                                id="obraMainImage" 
                                src="<?= htmlspecialchars($galeria[0]['imagem']) ?>" 
                                alt="<?= htmlspecialchars($obra['titulo']) ?> - <?= htmlspecialchars($galeria[0]['titulo']) ?>" 
                                class="obra-main-img"
                                data-current-index="0"
                                width="800"
                                height="600"
                                loading="eager"
                                fetchpriority="high"
                                decoding="sync"
                                onerror="if(!this.src.endsWith('.svg')) this.src=this.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');"
                            >

                            <!-- Botão Ampliar (Zoom / Lightbox) -->
                            <button type="button" class="btn-zoom-trigger" id="btnOpenZoom" title="Ampliar imagem em tela cheia" aria-label="Ampliar imagem">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    <line x1="11" y1="8" x2="11" y2="14"></line>
                                    <line x1="8" y1="11" x2="14" y2="11"></line>
                                </svg>
                                <span class="zoom-btn-text">Ampliar</span>
                            </button>

                            <!-- Setas Anterior / Próxima Imagem da Galeria -->
                            <button type="button" class="gallery-nav-arrow gallery-prev" id="galleryPrevBtn" aria-label="Foto anterior da obra">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </button>
                            <button type="button" class="gallery-nav-arrow gallery-next" id="galleryNextBtn" aria-label="Próxima foto da obra">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>

                            <!-- Legenda / Badge da Foto Atual -->
                            <div class="gallery-caption-badge" id="galleryCaptionBadge">
                                <span id="galleryCaptionText"><?= htmlspecialchars($galeria[0]['titulo']) ?></span>
                                <span class="gallery-caption-counter" id="galleryCounter">1 / <?= count($galeria) ?></span>
                            </div>

                        </div>
                    </div>

                    <!-- Miniaturas Abaixo da Imagem Principal -->
                    <div class="obra-thumbnails-wrapper">
                        <div class="obra-thumbnails-track" id="obraThumbnails" role="tablist" aria-label="Vistas fotográficas da obra">
                            <?php foreach ($galeria as $index => $item): ?>
                                <button 
                                    type="button" 
                                    class="thumb-btn <?= $index === 0 ? 'active' : '' ?>" 
                                    data-index="<?= $index ?>" 
                                    data-img="<?= $item['imagem'] ?>" 
                                    data-caption="<?= htmlspecialchars($item['titulo']) ?>"
                                    role="tab"
                                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                                    aria-label="Ver <?= htmlspecialchars($item['titulo']) ?>"
                                >
                                    <div class="thumb-img-wrapper">
                                        <img 
                                            src="<?= htmlspecialchars(artsale_get_thumbnail_url($item['imagem'], 160, 120, 80, '../')) ?>" 
                                            alt="Miniatura <?= htmlspecialchars($item['titulo']) ?>" 
                                            class="thumb-img" 
                                            width="80" 
                                            height="60" 
                                            loading="lazy" 
                                            decoding="async" 
                                            onerror="if(!this.src.endsWith('.svg')) this.src=this.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');"
                                        >
                                    </div>
                                    <span class="thumb-label"><?= htmlspecialchars($item['titulo']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Garantia de Procedência e Embalagem Especializada -->
                    <div class="obra-assurance-box">
                        <div class="assurance-item">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            <span>Certificado de autenticidade assinado e registrado</span>
                        </div>
                        <div class="assurance-item">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                            <span>Embalagem museológica climatizada para transporte seguro</span>
                        </div>
                    </div>

                </div>

                <!-- ====================================================
                     COLUNA DIREITA: DETALHES, INFORMAÇÕES E BOTÃO DE INTERESSE
                     ==================================================== -->
                <div class="obra-info-col">
                    
                    <!-- Cabeçalho da Obra -->
                    <div class="obra-header">
                        <div class="obra-type-row">
                            <span class="obra-eyebrow"><?= htmlspecialchars($obra['tipo'] ?? 'OBRA ORIGINAL') ?></span>
                            <!-- Botão de Favoritar no Topo -->
                            <button 
                                type="button" 
                                class="btn-favorite btn-favorite-detail" 
                                title="Salvar nos favoritos" 
                                aria-label="Favoritar <?= htmlspecialchars($obra['titulo']) ?>" 
                                data-id="<?= $obra['id'] ?>"
                            >
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                                <span class="favorite-label">Favoritar obra</span>
                            </button>
                        </div>

                        <h1 class="obra-page-title"><?= htmlspecialchars($obra['titulo']) ?></h1>
                        <p class="obra-page-artist">Por <strong><?= htmlspecialchars($obra['artista']) ?></strong></p>
                    </div>

                    <!-- Divisor Sutil -->
                    <div class="obra-divider"></div>

                    <!-- Descrição Curatorial da Obra -->
                    <div class="obra-description-block">
                        <h2 class="obra-section-heading">Sobre a obra</h2>
                        <p class="obra-description-text">
                            <?= htmlspecialchars($obra['descricao']) ?>
                        </p>
                    </div>

                    <!-- Informações Organizadas em Duas Colunas -->
                    <div class="obra-specs-block">
                        <h2 class="obra-section-heading">Ficha Técnica & Procedência</h2>
                        
                        <div class="obra-specs-grid">
                            <!-- Coluna 1 -->
                            <div class="spec-item">
                                <span class="spec-label">Técnica:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['tecnica']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Ano:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['ano']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Dimensões:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['dimensoes']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Estado de conservação:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['estado_conservacao']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Código:</span>
                                <span class="spec-value spec-code"><?= htmlspecialchars($obra['codigo']) ?></span>
                            </div>

                            <!-- Coluna 2 -->
                            <div class="spec-item">
                                <span class="spec-label">Categoria:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['categoria']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Disponibilidade:</span>
                                <span class="spec-value spec-avail"><?= htmlspecialchars($obra['disponibilidade']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Procedência:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['procedencia']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Artista:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['artista']) ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Localização:</span>
                                <span class="spec-value"><?= htmlspecialchars($obra['localizacao']) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($obra['biografia_artista'])): ?>
                    <div class="obra-artist-card-block" style="margin-bottom: 2rem; padding: 1.25rem 1.5rem; background: rgba(179,138,84,0.05); border: 1px solid rgba(179,138,84,0.25); border-radius: 6px;">
                        <h3 style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.35rem; color: #b38a54; margin-bottom: 0.5rem;">Sobre o Artista: <?= htmlspecialchars($obra['artista']) ?></h3>
                        <p style="font-size: 0.875rem; color: #d4cfc5; line-height: 1.65; margin: 0;"><?= nl2br(htmlspecialchars($obra['biografia_artista'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- ================================================
                         BLOCO DE PREÇO SOB CONSULTA E BOTÃO DE INTERESSE
                         (Regra: Somente UM botão principal, sem WhatsApp)
                         ================================================ -->
                    <div class="obra-inquiry-action-card">
                        <div class="inquiry-card-header">
                            <span class="inquiry-card-badge">CONSULTORIA PRIVADA</span>
                            <h3 class="inquiry-card-price">Preço sob consulta</h3>
                            <p class="inquiry-card-subtext">
                                Entre em contato para mais informações sobre esta obra.
                            </p>
                        </div>

                        <!-- Único Botão Principal Conforme Requisito -->
                        <div class="inquiry-card-cta">
                            <button 
                                type="button" 
                                class="btn-primary-interest btn-block" 
                                id="btnTenhoInteresse"
                                data-artwork-id="<?= $obra['id'] ?>"
                                data-artwork-title="<?= htmlspecialchars($obra['titulo']) ?>"
                                data-artwork-artist="<?= htmlspecialchars($obra['artista']) ?>"
                                data-artwork-dimensions="<?= htmlspecialchars($obra['dimensoes']) ?>"
                                data-artwork-image="<?= $obra['imagem'] ?>"
                            >
                                Tenho interesse nesta obra <span class="arrow">→</span>
                            </button>
                        </div>

                        <div class="inquiry-card-footer">
                            <span class="security-text">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Resposta reservada em até 2 horas por consultores sêniores da galeria.
                            </span>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- Transição com detalhe sutil -->
    <div class="brush-transition-accent" aria-hidden="true"></div>

    <!-- ========================================================
         SEÇÃO DE OBRAS RELACIONADAS / MAIS OBRAS DO ACERVO
         ======================================================== -->
    <section class="section-related-artworks">
        <div class="section-container">
            <div class="section-header">
                <div class="header-titles">
                    <span class="section-eyebrow">CURADORIA SELECIONADA</span>
                    <h2 class="section-title">Obras Relacionadas</h2>
                </div>
                <a href="obras.php" class="section-link-all">
                    Ver catálogo completo <span class="arrow">→</span>
                </a>
            </div>

            <!-- Grade com 4 Obras Relacionadas -->
            <div class="catalogo-grid">
                <?php foreach ($obrasRelacionadas as $rel): ?>
                    <article class="artwork-card" data-id="<?= $rel['id'] ?>" data-title="<?= htmlspecialchars($rel['titulo']) ?>" data-artist="<?= htmlspecialchars($rel['artista']) ?>" data-dimensions="<?= htmlspecialchars($rel['dimensoes']) ?>" data-image="<?= $rel['imagem'] ?>">
                        <div class="artwork-image-container">
                            <img 
                                src="<?= htmlspecialchars(artsale_get_thumbnail_url($rel['imagem'], 480, 300, 82, '../')) ?>" 
                                alt="<?= htmlspecialchars($rel['titulo']) ?> por <?= htmlspecialchars($rel['artista']) ?>" 
                                class="artwork-image" 
                                width="400"
                                height="250"
                                loading="lazy"
                                decoding="async"
                                onerror="if(!this.src.endsWith('.svg')) this.src=this.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');"
                            >
                            <span class="artwork-category-tag"><?= htmlspecialchars($rel['categoria']) ?></span>
                            <button type="button" class="btn-favorite" title="Adicionar aos favoritos" aria-label="Favoritar <?= htmlspecialchars($rel['titulo']) ?>" data-id="<?= $rel['id'] ?>">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="artwork-details">
                            <h3 class="artwork-title"><?= htmlspecialchars($rel['titulo']) ?></h3>
                            <p class="artwork-artist"><?= htmlspecialchars($rel['artista']) ?></p>
                            <p class="artwork-dimensions"><?= htmlspecialchars($rel['dimensoes']) ?></p>
                            <div class="artwork-footer-row">
                                <span class="artwork-price">Preço sob consulta</span>
                                <a href="obra.php?id=<?= $rel['id'] ?>" class="btn-ver-detalhes" data-id="<?= $rel['id'] ?>">
                                    Ver detalhes
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</main>

<!-- ========================================================
     MODAL DE ZOOM / AMPLIAÇÃO EM TELA CHEIA (LIGHTBOX)
     ======================================================== -->
<div class="obra-zoom-modal-backdrop" id="obraZoomModal" role="dialog" aria-modal="true" aria-label="Ampliação da obra">
    <div class="zoom-modal-container">
        
        <!-- Barra Superior do Zoom -->
        <div class="zoom-modal-toolbar">
            <div class="zoom-title-meta">
                <span class="zoom-artwork-name"><?= htmlspecialchars($obra['titulo']) ?></span>
                <span class="zoom-artwork-artist">por <?= htmlspecialchars($obra['artista']) ?></span>
                <span class="zoom-view-caption" id="zoomCaptionText"><?= htmlspecialchars($galeria[0]['titulo']) ?></span>
            </div>
            
            <div class="zoom-actions">
                <button type="button" class="zoom-btn-tool" id="btnToggleZoomLevel" title="Alternar escala de ampliação" aria-label="Alternar zoom">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="11" y1="8" x2="11" y2="14"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                </button>
                <button type="button" class="zoom-close-btn" id="btnCloseZoom" aria-label="Fechar ampliação">✕</button>
            </div>
        </div>

        <!-- Área Central com a Imagem Ampliada -->
        <div class="zoom-image-stage" id="zoomImageStage">
            <button type="button" class="zoom-arrow zoom-prev" id="zoomPrevBtn" aria-label="Imagem anterior">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <div class="zoom-image-scroll-wrapper">
                <img id="zoomMainImg" src="<?= $galeria[0]['imagem'] ?>" alt="Ampliação de <?= htmlspecialchars($obra['titulo']) ?>" class="zoom-img" onerror="if(!this.src.endsWith('.svg')) this.src=this.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');">
            </div>

            <button type="button" class="zoom-arrow zoom-next" id="zoomNextBtn" aria-label="Próxima imagem">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>

        <!-- Barra Inferior de Miniaturas no Zoom -->
        <div class="zoom-modal-footer">
            <div class="zoom-thumbs-row">
                <?php foreach ($galeria as $idx => $item): ?>
                    <button type="button" class="zoom-thumb-btn <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>">
                        <img src="<?= $item['imagem'] ?>" alt="Miniatura <?= htmlspecialchars($item['titulo']) ?>" onerror="if(!this.src.endsWith('.svg')) this.src=this.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');">
                    </button>
                <?php endforeach; ?>
            </div>
            <span class="zoom-help-hint">Pressione ESC para fechar • Clique na imagem para alternar zoom</span>
        </div>

    </div>
</div>

<!-- ========================================================
     MODAL DE ATENDIMENTO / TENHO INTERESSE NESTA OBRA
     ======================================================== -->
<div class="inquiry-modal-backdrop" id="inquiryModal">
    <div class="inquiry-modal-content">
        <button type="button" class="inquiry-modal-close" id="btnCloseInquiry" aria-label="Fechar janela">✕</button>
        <div class="inquiry-modal-header">
            <span class="modal-eyebrow">CONSULTORIA ESPECIALIZADA</span>
            <h3 class="modal-title" id="modalArtworkTitle">Tenho interesse nesta obra</h3>
            <p class="modal-subtitle" id="modalArtworkSubtitle">Atendimento reservado para colecionadores e apreciadores.</p>
        </div>
        
        <div class="inquiry-modal-body">
            <div class="inquiry-preview-box" id="modalPreviewBox">
                <img id="modalArtworkImg" src="<?= $obra['imagem'] ?>" alt="<?= htmlspecialchars($obra['titulo']) ?>" class="inquiry-preview-img" onerror="if(!this.src.endsWith('.svg')) this.src=this.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');">
                <div class="inquiry-preview-meta">
                    <h4 id="modalMetaTitle" class="inquiry-meta-title"><?= htmlspecialchars($obra['titulo']) ?></h4>
                    <p id="modalMetaArtist" class="inquiry-meta-artist"><?= htmlspecialchars($obra['artista']) ?></p>
                    <p id="modalMetaDimensions" class="inquiry-meta-dim"><?= htmlspecialchars($obra['dimensoes']) ?></p>
                    <span class="inquiry-meta-price">Preço sob consulta</span>
                </div>
            </div>

            <p class="inquiry-instruction">
                Escolha o canal de sua preferência para falar diretamente com nossos consultores sobre esta obra:
            </p>

            <div class="inquiry-options-grid">
                <a 
                    href="<?= $contatos['elisabeth']['whatsapp_link'] ?>" 
                    id="btnModalWhatsElisabeth" 
                    target="_blank" 
                    rel="noopener noreferrer" 
                    class="inquiry-channel-btn whatsapp"
                >
                    <div class="channel-info">
                        <strong>WhatsApp Sra. Elisabeth</strong>
                        <span>Curadoria & Atendimento</span>
                    </div>
                    <span class="channel-arrow">→</span>
                </a>
                <a 
                    href="<?= $contatos['felipe']['whatsapp_link'] ?>" 
                    id="btnModalWhatsFelipe" 
                    target="_blank" 
                    rel="noopener noreferrer" 
                    class="inquiry-channel-btn whatsapp"
                >
                    <div class="channel-info">
                        <strong>WhatsApp Felipe C</strong>
                        <span>Atendimento & Vendas</span>
                    </div>
                    <span class="channel-arrow">→</span>
                </a>
                <a 
                    href="<?= $contatos['email']['link'] ?>" 
                    id="btnModalEmail" 
                    class="inquiry-channel-btn email"
                >
                    <div class="channel-info">
                        <strong>Enviar E-mail Formal</strong>
                        <span><?= $contatos['email']['endereco'] ?></span>
                    </div>
                    <span class="channel-arrow">→</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$hasInquiryModal = true;
require_once dirname(__DIR__) . '/includes/footer.php';
?>

