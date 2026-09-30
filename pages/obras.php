<?php
/**
 * ART FOR SALE - Catálogo Geral de Obras de Arte
 * Página: /pages/obras.php
 * Tecnologias: PHP 8+, HTML5, CSS3, JavaScript Vanilla
 * Fidelidade máxima à identidade visual premium e refinamento da Home.
 */

$pathPrefix = '../';
$currentPage = 'obras';

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/supabase.php';

$catalogoObras = [];
if (function_exists('supabase_buscar_obras')) {
    $catalogoObras = supabase_buscar_obras(null, null, 100);
}
if (!is_array($catalogoObras)) {
    $catalogoObras = [];
}

$deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
if (!is_array($deletedIds)) {
    $deletedIds = [];
}
$catalogoObras = array_values(array_filter($catalogoObras, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

// ==============================================================================
// SEO Dinâmico do Catálogo de Obras Art For Sale
// ==============================================================================
$seoMeta = [
    'title'          => 'Obras de Arte | Art For Sale',
    'description'    => 'Descubra nossa curadoria de obras originais, com procedência garantida e estética atemporal para transformar espaços com personalidade.',
    'canonical'      => function_exists('artsale_absolute_url') ? artsale_absolute_url('pages/obras.php') : '',
    'og_type'        => 'website',
    'og_title'       => 'Obras de Arte | Art For Sale',
    'og_description' => 'Descubra nossa curadoria de obras originais, com procedência garantida e estética atemporal para transformar espaços com personalidade.',
    'og_image'       => function_exists('artsale_absolute_url') ? artsale_absolute_url('assets/images/site/about-art-sell.jpg') : '',
    'og_url'         => function_exists('artsale_absolute_url') ? artsale_absolute_url('pages/obras.php') : '',
    'schemas'        => [
        function_exists('artsale_schema_breadcrumb') ? artsale_schema_breadcrumb([
            ['name' => 'Início', 'url' => artsale_absolute_url('index.php')],
            ['name' => 'Obras', 'url' => artsale_absolute_url('pages/obras.php')]
        ]) : []
    ]
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="site-main catalogo-page">

    <!-- ========================================================
         CABEÇALHO DA PÁGINA (EDITORIAL / HERO SUTIL)
         ======================================================== -->
    <section class="catalogo-header-section">
        <div class="section-container">
            <!-- Breadcrumb Sofisticado -->
            <nav class="catalogo-breadcrumb" aria-label="Navegação estrutural">
                <a href="../index.php" class="breadcrumb-link">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">Nossas Obras</span>
            </nav>

            <div class="catalogo-header-content">
                <span class="catalogo-eyebrow">NOSSAS OBRAS</span>
                <h1 class="catalogo-title">Obras de Arte</h1>
                <p class="catalogo-description">
                    Descubra nossa curadoria de obras originais, com procedência garantida e estética atemporal para transformar e valorizar o seu espaço com personalidade e elegância.
                </p>
            </div>
        </div>
    </section>

    <!-- Transição com detalhe sutil -->
    <div class="brush-transition-accent" aria-hidden="true"></div>

    <!-- ========================================================
         SEÇÃO PRINCIPAL DO CATÁLOGO: BUSCA, FILTROS, GRADE E PAGINAÇÃO
         ======================================================== -->
    <section class="catalogo-content-section" id="catalogoSection">
        <div class="section-container">

            <!-- Painel de Controles: Pesquisa e Filtros de Categoria -->
            <div class="catalogo-controls">
                
                <!-- Barra de Pesquisa em Tempo Real -->
                <div class="catalogo-search-wrapper">
                    <div class="catalogo-search-input-box">
                        <svg class="search-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input 
                            type="text" 
                            id="catalogoSearch" 
                            class="catalogo-search-input" 
                            placeholder="Pesquisar obras ou artistas..." 
                            autocomplete="off"
                            aria-label="Pesquisar obras ou artistas"
                        >
                        <button type="button" id="catalogoSearchClear" class="catalogo-search-clear" aria-label="Limpar pesquisa" title="Limpar pesquisa">✕</button>
                    </div>
                </div>

                <!-- Filtros de Categorias (Ocultado a pedido do cliente) -->
                <div class="catalogo-filters-bar" style="display: none !important;">
                    <div class="catalogo-filters-scroll" id="catalogoFilters" role="tablist" aria-label="Filtro de Categorias">
                        <button type="button" class="filter-pill active" data-category="all" role="tab" aria-selected="true">
                            Todas
                        </button>
                    </div>
                </div>

                <!-- Barra de Metadados: Contador de Obras e Ordenação -->
                <div class="catalogo-meta-bar">
                    <div class="catalogo-count-box">
                        <span id="catalogoCount" class="catalogo-count-text">
                            Mostrando <strong><?= count($catalogoObras) ?></strong> obras
                        </span>
                        <span id="catalogoActiveBadge" class="catalogo-active-badge" style="display: none;"></span>
                    </div>

                    <div class="catalogo-sort-box">
                        <label for="catalogoSort" class="catalogo-sort-label">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 6h18M6 12h12m-9 6h6"/>
                            </svg>
                            <span>Ordenar por:</span>
                        </label>
                        <div class="sort-select-wrapper">
                            <select id="catalogoSort" class="catalogo-sort-select" aria-label="Ordenar obras por">
                                <option value="recente">Mais recentes</option>
                                <option value="az">Nome A-Z</option>
                                <option value="za">Nome Z-A</option>
                            </select>
                            <svg class="select-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ====================================================
                 ESTADOS VISUAIS DO CATÁLOGO
                 ==================================================== -->

            <!-- 1. Estado de Carregamento (Loading) -->
            <div id="catalogoLoading" class="catalogo-state-box catalogo-loading-state" style="display: none;">
                <div class="gold-loading-spinner" aria-hidden="true"></div>
                <p class="state-loading-text">Atualizando curadoria...</p>
            </div>

            <!-- 2. Estado: Nenhum Resultado Encontrado -->
            <div id="catalogoNoResults" class="catalogo-state-box catalogo-no-results-state" style="display: none;">
                <div class="state-icon-circle">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="8" y1="11" x2="14" y2="11" stroke-dasharray="2 2"></line>
                    </svg>
                </div>
                <h3 class="state-title">Nenhum resultado encontrado</h3>
                <p class="state-description" id="stateNoResultsMsg">
                    Não encontramos nenhuma obra que corresponda aos termos pesquisados.
                </p>
                <button type="button" class="btn-state-reset" id="btnResetSearch">
                    Limpar pesquisa e ver todas
                </button>
            </div>

            <!-- 3. Estado: Categoria Vazia -->
            <div id="catalogoEmptyCategory" class="catalogo-state-box catalogo-empty-cat-state" style="display: none;">
                <div class="state-icon-circle">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
                <h3 class="state-title">Nenhuma obra encontrada</h3>
                <p class="state-description">
                    No momento, todas as peças correspondentes encontram-se reservadas ou em acervo particular. Fale com nossos curadores para encomendas e consultas de procedência.
                </p>
                <div class="state-buttons-row">
                    <button type="button" class="btn-state-reset" id="btnResetFilter">
                        Ver todo o acervo
                    </button>
                    <a href="#contato" class="btn-state-outline" data-modal="consultar">
                        Consultar curadoria
                    </a>
                </div>
            </div>

            <!-- ====================================================
                 GRADE DO CATÁLOGO DE OBRAS (4 Desktop / 2 Tablet / 1 Mobile)
                 ==================================================== -->
            <div class="catalogo-grid" id="catalogoGrid">
                <?php foreach ($catalogoObras as $obra): ?>
                    <article 
                        class="artwork-card" 
                        data-id="<?= htmlspecialchars((string)$obra['id']) ?>" 
                        data-title="<?= htmlspecialchars($obra['titulo']) ?>" 
                        data-artist="<?= htmlspecialchars($obra['artista']) ?>" 
                        data-dimensions="<?= htmlspecialchars($obra['dimensoes']) ?>" 
                        data-price="<?= htmlspecialchars($obra['preco']) ?>"
                        data-category="<?= htmlspecialchars($obra['categoria_slug'] ?? 'pinturas') ?>"
                        data-category-name="<?= htmlspecialchars($obra['categoria']) ?>"
                        data-image="<?= htmlspecialchars($obra['imagem']) ?>"
                        data-year="<?= htmlspecialchars((string)($obra['ano_numerico'] ?? $obra['ano'] ?? '')) ?>"
                        data-order="<?= (int)($obra['ordem'] ?? 0) ?>"
                    >
                        <div class="artwork-image-container">
                            <img 
                                src="<?= htmlspecialchars(artsale_get_thumbnail_url($obra['imagem'], 480, 300, 82, '../')) ?>" 
                                alt="<?= htmlspecialchars($obra['titulo']) ?> por <?= htmlspecialchars($obra['artista']) ?>" 
                                class="artwork-image"
                                width="400"
                                height="250"
                                loading="lazy"
                                decoding="async"
                                onerror="this.onerror=null; this.src='../assets/images/obras/placeholder-obra.svg';"
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
                                <span class="artwork-price"><?= htmlspecialchars($obra['preco']) ?></span>
                                <a href="obra.php?id=<?= urlencode((string)$obra['id']) ?>" class="btn-ver-detalhes" data-id="<?= htmlspecialchars((string)$obra['id']) ?>">
                                    Ver detalhes
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- ====================================================
                 PAGINAÇÃO VISUAL (← 1 2 3 ... →)
                 ==================================================== -->
            <div class="catalogo-pagination-container">
                <nav class="catalogo-pagination" id="catalogoPagination" aria-label="Navegação entre páginas do catálogo">
                    <!-- Construído interativamente via JavaScript -->
                </nav>
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
                <img id="modalArtworkImg" src="" alt="Obra selecionada" class="inquiry-preview-img">
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
require_once dirname(__DIR__) . '/includes/footer.php';
?>

