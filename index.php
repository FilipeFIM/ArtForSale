<?php
/**
 * ART FOR SALE - Home Page
 * Tecnologias: PHP 8+, HTML5, CSS3, JavaScript Vanilla
 * Fidelidade máxima ao mockup de referência.
 */

$pathPrefix = '';
$currentPage = 'inicio';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/supabase.php';

if (function_exists('supabase_buscar_obras')) {
    $catalogoObras = supabase_buscar_obras(null, null, 24);
}
if (!isset($catalogoObras) || !is_array($catalogoObras)) {
    $catalogoObras = [];
}

// Carrega categorias dinâmicas do Supabase
$categoriasHome = function_exists('supabase_buscar_categorias') ? supabase_buscar_categorias(true) : [];
if (empty($categoriasHome) && function_exists('artsale_get_local_categories')) {
    $categoriasHome = artsale_get_local_categories();
}
if (empty($categoriasHome)) {
    global $categorias;
    $categoriasHome = $categorias ?? [];
}

$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
if (!is_array($deletedIds)) {
    $deletedIds = [];
}
$catalogoObras = array_values(array_filter($catalogoObras, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

// Seleção curatorial inteligente para Obras em Destaque na Home:
// 1. Prioriza obras novas criadas pelo curador/usuário no início
// 2. Inclui todas as obras ativas marcadas com destaque = true
// 3. Completa o carrossel com as demais obras ativas do acervo (até 12 obras para deslizar com setas)
$obrasDestaque = [];
$seenDestaqueIds = [];

// 1. Obras cadastradas pelo usuário (imagens reais)
foreach ($catalogoObras as $obra) {
    $idStr = (string)($obra['id'] ?? '');
    if (empty($idStr) || isset($seenDestaqueIds[$idStr])) continue;
    $isCustom = !empty($obra['imagem_storage_path']) 
        || str_contains($obra['imagem'] ?? '', 'uploads/') 
        || (!is_numeric($obra['id']) && !str_starts_with((string)$obra['id'], 'b1000'));
    if ($isCustom) {
        $seenDestaqueIds[$idStr] = true;
        $obrasDestaque[] = $obra;
    }
}

// 2. Obras marcadas como destaque = true no painel
foreach ($catalogoObras as $obra) {
    $idStr = (string)($obra['id'] ?? '');
    if (empty($idStr) || isset($seenDestaqueIds[$idStr])) continue;
    if (!empty($obra['destaque'])) {
        $seenDestaqueIds[$idStr] = true;
        $obrasDestaque[] = $obra;
    }
}

// 3. Completa com obras ativas para garantir carrossel com múltiplas páginas
foreach ($catalogoObras as $obra) {
    $idStr = (string)($obra['id'] ?? '');
    if (empty($idStr) || isset($seenDestaqueIds[$idStr])) continue;
    $seenDestaqueIds[$idStr] = true;
    $obrasDestaque[] = $obra;
    if (count($obrasDestaque) >= 12) break;
}

// ==============================================================================
// SEO Dinâmico Home Page Art For Sale
// ==============================================================================
$seoMeta = [
    'title'          => 'Art For Sale | Galeria de Arte',
    'description'    => 'Descubra obras selecionadas pela Art For Sale. Arte que transforma espaços.',
    'canonical'      => function_exists('artsale_absolute_url') ? artsale_absolute_url('index.php') : 'http://localhost:8000/index.php',
    'og_type'        => 'website',
    'og_title'       => 'Art For Sale | Galeria de Arte',
    'og_description' => 'Descubra obras selecionadas pela Art For Sale. Arte que transforma espaços.',
    'og_image'       => function_exists('artsale_absolute_url') ? artsale_absolute_url('assets/images/site/about-art-sell.jpg') : '',
    'og_url'         => function_exists('artsale_absolute_url') ? artsale_absolute_url('index.php') : 'http://localhost:8000/index.php',
    'schemas'        => function_exists('artsale_schema_gallery') ? [artsale_schema_gallery()] : []
];

require_once __DIR__ . '/includes/header.php';
?>

<main class="site-main">

    <!-- ========================================================
         SEÇÃO HERO
         ======================================================== -->
    <?php
    $heroSlides = function_exists('artsale_get_hero_slides') ? artsale_get_hero_slides() : [];
    $activeHeroSlide = $heroSlides[0] ?? [
        'image' => 'assets/images/site/hero-bg.jpg',
        'eyebrow' => 'GALERIA DE ARTE',
        'title' => 'Arte que<br>transforma<br>espaços.',
        'description' => 'Descubra obras únicas e cuidadosamente selecionadas para colecionadores, apreciadores e ambientes que merecem personalidade.',
        'quote' => 'Mais que quadros, histórias que ganham vida no seu espaço.',
        'cta_primary_text' => 'Explorar obras',
        'cta_primary_url' => 'pages/obras.php',
        'cta_secondary_text' => 'Conhecer a Galeria',
        'cta_secondary_url' => '#sobre'
    ];
    ?>
    <section class="hero-section" id="hero">
        <!-- Fundo com imagens em camadas configuráveis via Painel Admin -->
        <div class="hero-background" id="heroBackground" data-mockup-fallback="hero">
            <?php foreach ($heroSlides as $sIdx => $sData): 
                $bgImgUrl = (preg_match('#^(https?:)?//#i', $sData['image']) || str_starts_with($sData['image'], 'data:'))
                    ? $sData['image']
                    : get_image_url($sData['image']);
            ?>
                <div class="hero-bg-slide <?= $sIdx === 0 ? 'active' : '' ?>" style="background-image: url('<?= htmlspecialchars($bgImgUrl) ?>');" data-slide-index="<?= $sIdx ?>">
                    <div class="hero-slide-backdrop" style="background-image: url('<?= htmlspecialchars($bgImgUrl) ?>');"></div>
                    <div class="hero-slide-artwork" style="background-image: url('<?= htmlspecialchars($bgImgUrl) ?>');"></div>
                </div>
            <?php endforeach; ?>
            <div class="hero-overlay"></div>
        </div>

        <div class="hero-container">
            <!-- Conteúdo Principal do Hero (Esquerda) -->
            <div class="hero-content">
                <span class="hero-eyebrow hero-text-fade"><?= htmlspecialchars($activeHeroSlide['eyebrow'] ?? 'GALERIA DE ARTE') ?></span>
                <h1 class="hero-title hero-text-fade">
                    <?= $activeHeroSlide['title'] ?? 'Arte que<br>transforma<br>espaços.' ?>
                </h1>
                <p class="hero-description hero-text-fade">
                    <?= htmlspecialchars($activeHeroSlide['description'] ?? '') ?>
                </p>
                <div class="hero-cta-group">
                    <a href="<?= htmlspecialchars($activeHeroSlide['cta_primary_url'] ?? 'pages/obras.php') ?>" class="btn-primary" id="heroCtaPrimary">
                        <?= htmlspecialchars($activeHeroSlide['cta_primary_text'] ?? 'Explorar obras') ?> <span class="arrow">→</span>
                    </a>
                    <?php
                    $heroSecUrl = $activeHeroSlide['cta_secondary_url'] ?? '#sobre';
                    if ($heroSecUrl === '#categorias' || str_contains($heroSecUrl, 'categoria')) $heroSecUrl = '#sobre';
                    $heroSecText = $activeHeroSlide['cta_secondary_text'] ?? 'Conhecer a Galeria';
                    if ($heroSecText === 'Ver categorias' || str_contains(mb_strtolower($heroSecText), 'categoria')) $heroSecText = 'Conhecer a Galeria';
                    ?>
                    <a href="<?= htmlspecialchars($heroSecUrl) ?>" class="btn-outline-light" id="heroCtaSecondary">
                        <?= htmlspecialchars($heroSecText) ?>
                    </a>
                </div>

                <!-- Indicador de Slide do Hero -->
                <div class="hero-pagination">
                    <span class="page-number active" role="button" tabindex="0" aria-label="Ir para o slide 1" data-slide="0">01</span>
                    <span class="page-number" role="button" tabindex="0" aria-label="Ir para o slide 2" data-slide="1">02</span>
                    <span class="page-number" role="button" tabindex="0" aria-label="Ir para o slide 3" data-slide="2">03</span>
                    <span class="page-line"></span>
                </div>
            </div>

            <!-- Citação Editorial Sofisticada (Direita) -->
            <div class="hero-quote-box">
                <div class="quote-mark">“</div>
                <blockquote class="quote-text hero-text-fade">
                    <?= htmlspecialchars($activeHeroSlide['quote'] ?? 'Mais que quadros, histórias que ganham vida no seu espaço.') ?>
                </blockquote>
                
                <!-- Setas de Navegação do Hero -->
                <div class="hero-nav-arrows">
                    <button type="button" class="hero-arrow-btn" id="heroPrevBtn" aria-label="Slide anterior">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                    </button>
                    <button type="button" class="hero-arrow-btn" id="heroNextBtn" aria-label="Próximo slide">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SEÇÃO OBRAS EM DESTAQUE
         ======================================================== -->
    <section class="section-featured" id="obras-destaque">
        <div class="section-container">
            <!-- Cabeçalho da Seção -->
            <div class="section-header">
                <div class="header-titles">
                    <span class="section-eyebrow">OBRAS EM DESTAQUE</span>
                    <h2 class="section-title">Obras em Destaque</h2>
                </div>
                <a href="pages/obras.php" class="section-link-all">
                    Ver todas as obras <span class="arrow">→</span>
                </a>
            </div>

            <!-- Carrossel de Obras com Setas Laterais -->
            <div class="featured-carousel-wrapper">
                <!-- Seta Esquerda -->
                <button type="button" class="carousel-arrow prev" id="featuredPrevBtn" aria-label="Obra anterior">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>

                <!-- Grade/Track do Carrossel com 4 Obras -->
                <div class="featured-carousel-track" id="featuredTrack">
                    <?php foreach ($obrasDestaque as $obra): 
                        $resolvedImg = artsale_resolve_image_url($obra['imagem'] ?? '', '');
                    ?>
                        <article class="artwork-card" data-id="<?= htmlspecialchars((string)$obra['id']) ?>" data-title="<?= htmlspecialchars($obra['titulo']) ?>" data-artist="<?= htmlspecialchars($obra['artista']) ?>" data-dimensions="<?= htmlspecialchars($obra['dimensoes']) ?>" data-image="<?= $resolvedImg ?>">
                            <div class="artwork-image-container">
                                <img 
                                    src="<?= htmlspecialchars(artsale_get_thumbnail_url($resolvedImg, 480, 300)) ?>" 
                                    alt="<?= htmlspecialchars($obra['titulo']) ?> por <?= htmlspecialchars($obra['artista']) ?>" 
                                    class="artwork-image"
                                    width="400"
                                    height="250"
                                    loading="lazy"
                                    decoding="async"
                                    data-crop="<?= $obra['crop_key'] ?? '' ?>"
                                    onerror="this.onerror=null; this.src='assets/images/obras/placeholder-obra.svg';"
                                >
                                <!-- Botão de Favoritar (Coração) -->
                                <button type="button" class="btn-favorite" title="Adicionar aos favoritos" aria-label="Favoritar <?= htmlspecialchars($obra['titulo']) ?>" data-id="<?= htmlspecialchars((string)$obra['id']) ?>">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="artwork-details">
                                <h3 class="artwork-title"><?= htmlspecialchars($obra['titulo']) ?></h3>
                                <p class="artwork-artist"><?= htmlspecialchars($obra['artista']) ?></p>
                                <p class="artwork-dimensions"><?= htmlspecialchars($obra['dimensoes']) ?></p>
                                
                                <div class="artwork-footer-row">
                                    <span class="artwork-price">Preço sob consulta</span>
                                    <a href="pages/obra.php?id=<?= urlencode((string)$obra['id']) ?>" class="btn-ver-detalhes" data-id="<?= htmlspecialchars((string)$obra['id']) ?>">
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Seta Direita -->
                <button type="button" class="carousel-arrow next" id="featuredNextBtn" aria-label="Próxima obra">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>

            <!-- Indicadores de Pontos (Dots) -->
            <?php $totalDots = max(1, (int)ceil(count($obrasDestaque) / 4)); ?>
            <div class="carousel-dots" id="carouselDots" <?= $totalDots <= 1 ? 'style="display:none;"' : '' ?>>
                <?php for ($d = 0; $d < $totalDots; $d++): ?>
                    <button type="button" class="dot <?= $d === 0 ? 'active' : '' ?>" data-index="<?= $d ?>" aria-label="Página <?= $d + 1 ?>"></button>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SEÇÃO SOBRE: A ART FOR SALE
         ======================================================== -->
    <section class="section-about" id="sobre">
        <div class="about-container">
            <!-- Imagem da Galeria com Busto Clássico (Esquerda) -->
            <div class="about-media-col">
                <div class="about-image-wrapper">
                    <picture>
                        <source srcset="<?= get_image_url('assets/images/site/about-art-gallery.webp') ?>" type="image/webp">
                        <img 
                            src="<?= get_image_url('assets/images/site/about-art-gallery.jpg') ?>" 
                            alt="Galeria de Arte Art For Sale" 
                            class="about-image"
                            width="600"
                            height="353"
                            loading="lazy"
                            decoding="async"
                        >
                    </picture>
                </div>
            </div>

            <!-- Conteúdo e Diferenciais (Direita) -->
            <div class="about-content-col">
                <span class="section-eyebrow">SOBRE A ART FOR SALE</span>
                <h2 class="about-title">A Art For Sale</h2>
                <p class="about-paragraph">
                    A Art For Sale nasceu com o propósito de aproximar pessoas da arte, oferecendo uma seleção cuidadosa de obras capazes de transformar ambientes, despertar emoções e construir histórias.
                </p>
                <div class="about-cta">
                    <a href="#contato" class="btn-primary">
                        Conheça nossa história <span class="arrow">→</span>
                    </a>
                </div>

                <!-- Pilares Curatoriais da Galeria (Editorial Contemporâneo) -->
                <div class="curatorial-pillars-grid">
                    <div class="pillar-item">
                        <span class="pillar-number">01</span>
                        <h4 class="pillar-title">Curadoria Autoral</h4>
                        <p class="pillar-desc">Obras singulares selecionadas por artistas visuais contemporâneos, unindo sensibilidade estética à inteligência visual.</p>
                    </div>
                    <div class="pillar-item">
                        <span class="pillar-number">02</span>
                        <h4 class="pillar-title">Consultoria Dedicada</h4>
                        <p class="pillar-desc">Atendimento particular conduzido diretamente por nossos curadores para arquitetos, colecionadores e ambientes residenciais.</p>
                    </div>
                    <div class="pillar-item">
                        <span class="pillar-number">03</span>
                        <h4 class="pillar-title">Procedência & Autenticidade</h4>
                        <p class="pillar-desc">Cada peça conta com registro autoral, especificações técnicas detalhadas e tiragem rigorosamente controlada.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SEÇÃO FALE COM NOSSA EQUIPE (Banner Escuro Premium)
         ======================================================== -->
    <section class="section-team-banner" id="contato">
        <div class="team-banner-bg" style="background-image: url('<?= get_image_url('assets/images/site/team-banner-bg.jpg') ?>');" data-mockup-fallback="team">
            <div class="team-banner-overlay"></div>
        </div>

        <div class="team-banner-container">
            <!-- Texto Institucional da Equipe (Esquerda) -->
            <div class="team-intro-col">
                <span class="team-eyebrow">EM ATENDIMENTO</span>
                <h2 class="team-title">Fale com nossa equipe</h2>
                <p class="team-description">
                    Estamos à disposição para atender você, esclarecer suas dúvidas e ajudar na escolha da obra ideal.
                </p>
            </div>

            <!-- Cards dos Contatos Oficiais (Direita) -->
            <div class="team-cards-grid">
                <!-- Card Sra. Elisabeth -->
                <div class="team-card">
                    <div class="team-card-header">
                        <div class="team-avatar-icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="team-member-info">
                            <h4 class="team-member-name"><?= $contatos['elisabeth']['nome'] ?></h4>
                            <span class="team-member-phone"><?= $contatos['elisabeth']['telefone'] ?></span>
                        </div>
                    </div>
                    <div class="team-card-actions">
                        <a href="tel:51991140044" class="btn-contact-outline">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            Ligar
                        </a>
                        <a href="<?= $contatos['elisabeth']['whatsapp_link'] ?>" target="_blank" rel="noopener noreferrer" class="btn-contact-whatsapp">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                            </svg>
                            WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Card Felipe C -->
                <div class="team-card">
                    <div class="team-card-header">
                        <div class="team-avatar-icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="team-member-info">
                            <h4 class="team-member-name"><?= $contatos['felipe']['nome'] ?></h4>
                            <span class="team-member-phone"><?= $contatos['felipe']['telefone'] ?></span>
                        </div>
                    </div>
                    <div class="team-card-actions">
                        <a href="tel:51991266414" class="btn-contact-outline">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            Ligar
                        </a>
                        <a href="<?= $contatos['felipe']['whatsapp_link'] ?>" target="_blank" rel="noopener noreferrer" class="btn-contact-whatsapp">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                            </svg>
                            WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Card E-mail -->
                <div class="team-card team-card-email">
                    <div class="team-card-header">
                        <div class="team-avatar-icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <div class="team-member-info">
                            <h4 class="team-member-name">E-mail</h4>
                            <span class="team-member-phone"><?= $contatos['email']['endereco'] ?></span>
                        </div>
                    </div>
                    <div class="team-card-actions">
                        <a href="<?= $contatos['email']['link'] ?>" class="btn-contact-outline btn-full-width">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            Enviar e-mail
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- ========================================================
     MODAL DE CONSULTA DE OBRA / VER DETALHES
     ======================================================== -->
<div class="inquiry-modal-backdrop" id="inquiryModal">
    <div class="inquiry-modal-content">
        <button type="button" class="inquiry-modal-close" id="btnCloseInquiry" aria-label="Fechar janela">✕</button>
        <div class="inquiry-modal-header">
            <span class="modal-eyebrow">CONSULTORIA ESPECIALIZADA</span>
            <h3 class="modal-title" id="modalArtworkTitle">Consultar Obra</h3>
            <p class="modal-subtitle" id="modalArtworkSubtitle">Atendimento reservado para colecionadores e apreciadores.</p>
        </div>
        
        <div class="inquiry-modal-body">
            <div class="inquiry-preview-box" id="modalPreviewBox" style="display: none;">
                <img id="modalArtworkImg" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E" alt="Obra selecionada" class="inquiry-preview-img">
                <div class="inquiry-preview-meta">
                    <h4 id="modalMetaTitle" class="inquiry-meta-title"></h4>
                    <p id="modalMetaArtist" class="inquiry-meta-artist"></p>
                    <p id="modalMetaDimensions" class="inquiry-meta-dim"></p>
                    <span class="inquiry-meta-price">Preço sob consulta</span>
                </div>
            </div>

            <p class="inquiry-instruction">
                Escolha o canal de sua preferência para falar diretamente com nossos consultores de arte:
            </p>

            <div class="inquiry-options-grid">
                <a href="<?= $contatos['elisabeth']['whatsapp_link'] ?>" id="btnModalWhatsElisabeth" target="_blank" rel="noopener noreferrer" class="inquiry-channel-btn whatsapp">
                    <div class="channel-info">
                        <strong>WhatsApp Sra. Elisabeth</strong>
                        <span>Curadoria & Atendimento</span>
                    </div>
                    <span class="channel-arrow">→</span>
                </a>
                <a href="<?= $contatos['felipe']['whatsapp_link'] ?>" id="btnModalWhatsFelipe" target="_blank" rel="noopener noreferrer" class="inquiry-channel-btn whatsapp">
                    <div class="channel-info">
                        <strong>WhatsApp Felipe C</strong>
                        <span>Atendimento & Vendas</span>
                    </div>
                    <span class="channel-arrow">→</span>
                </a>
                <a href="<?= $contatos['email']['link'] ?>" id="btnModalEmail" class="inquiry-channel-btn email">
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
require_once __DIR__ . '/includes/footer.php';
?>

