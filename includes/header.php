<?php
/**
 * ART SELL - Header
 * Estrutura visual sofisticada, minimalista e fiel ao mockup
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — <?= SITE_SLOGAN ?></title>
    
    <!-- Meta tags SEO e Compartilhamento -->
    <meta name="description" content="Descubra obras únicas e cuidadosamente selecionadas para colecionadores, apreciadores e ambientes que merecem personalidade.">
    <meta name="theme-color" content="#b38a54">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= $pathPrefix ?? '' ?>assets/images/site/logo-icon.svg">

    <!-- Tipografia Editorial Premium Google Fonts (Cormorant Garamond + Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Folhas de Estilo -->
    <link rel="stylesheet" href="<?= $pathPrefix ?? '' ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $pathPrefix ?? '' ?>assets/css/responsive.css">
</head>
<body>

    <!-- Header Principal -->
    <header class="site-header" id="siteHeader">
        <div class="header-container">
            <!-- Logo ART SELL -->
            <a href="<?= $pathPrefix ?? '' ?>index.php" class="header-logo" title="<?= SITE_NAME ?> - <?= SITE_SLOGAN ?>">
                <img src="<?= $pathPrefix ?? '' ?>assets/images/site/logo.svg" alt="<?= SITE_NAME ?>" class="logo-image" width="220" height="48">
            </a>

            <!-- Menu de Navegação Desktop -->
            <nav class="header-nav" aria-label="Navegação Principal">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="<?= $pathPrefix ?? '' ?>index.php" class="nav-link <?= ($currentPage ?? '') === 'inicio' ? 'active' : '' ?>">Início</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $pathPrefix ?? '' ?>pages/obras.php" class="nav-link <?= ($currentPage ?? '') === 'obras' ? 'active' : '' ?>">Obras</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= ($currentPage ?? '') === 'inicio' ? '#categorias' : ($pathPrefix ?? '') . 'index.php#categorias' ?>" class="nav-link">Categorias</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= ($currentPage ?? '') === 'inicio' ? '#sobre' : ($pathPrefix ?? '') . 'index.php#sobre' ?>" class="nav-link">Sobre</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= ($currentPage ?? '') === 'inicio' ? '#contato' : ($pathPrefix ?? '') . 'index.php#contato' ?>" class="nav-link">Contato</a>
                    </li>
                </ul>
            </nav>

            <!-- Ações do Header (Pesquisa, Favoritos, Botão Consultar Obra) -->
            <div class="header-actions">
                <!-- Ícone de Pesquisa -->
                <button type="button" class="action-btn" id="btnOpenSearch" title="Pesquisar obras" aria-label="Pesquisar">
                    <svg class="action-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>

                <!-- Favoritos -->
                <button type="button" class="action-btn" id="btnOpenFavorites" title="Obras favoritas" aria-label="Favoritos">
                    <svg class="action-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="favorites-badge" id="favoritesCount">0</span>
                </button>

                <!-- Botão Consultar Obra -->
                <a href="<?= ($currentPage ?? '') === 'inicio' ? '#contato' : ($pathPrefix ?? '') . 'index.php#contato' ?>" class="btn-consultar-obra" data-modal="consultar">
                    Consultar obra
                </a>

                <!-- Botão Menu Mobile (Hambúrguer) -->
                <button type="button" class="mobile-toggle-btn" id="btnMobileMenu" aria-label="Abrir menu" aria-expanded="false">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
            </div>
        </div>

        <!-- Menu Drawer Mobile -->
        <div class="mobile-drawer" id="mobileDrawer">
            <div class="drawer-header">
                <img src="<?= $pathPrefix ?? '' ?>assets/images/site/logo.svg" alt="<?= SITE_NAME ?>" width="180">
                <button type="button" class="drawer-close-btn" id="btnCloseDrawer" aria-label="Fechar menu">✕</button>
            </div>
            <nav class="mobile-nav">
                <a href="<?= $pathPrefix ?? '' ?>index.php" class="mobile-nav-link <?= ($currentPage ?? '') === 'inicio' ? 'active' : '' ?>">Início</a>
                <a href="<?= $pathPrefix ?? '' ?>pages/obras.php" class="mobile-nav-link <?= ($currentPage ?? '') === 'obras' ? 'active' : '' ?>">Obras</a>
                <a href="<?= ($currentPage ?? '') === 'inicio' ? '#categorias' : ($pathPrefix ?? '') . 'index.php#categorias' ?>" class="mobile-nav-link">Categorias</a>
                <a href="<?= ($currentPage ?? '') === 'inicio' ? '#sobre' : ($pathPrefix ?? '') . 'index.php#sobre' ?>" class="mobile-nav-link">Sobre</a>
                <a href="<?= ($currentPage ?? '') === 'inicio' ? '#contato' : ($pathPrefix ?? '') . 'index.php#contato' ?>" class="mobile-nav-link">Contato</a>
            </nav>
            <div class="drawer-footer">
                <a href="<?= ($currentPage ?? '') === 'inicio' ? '#contato' : ($pathPrefix ?? '') . 'index.php#contato' ?>" class="btn-consultar-obra btn-block" data-modal="consultar">
                    Consultar obra
                </a>
            </div>
        </div>
        <div class="drawer-backdrop" id="drawerBackdrop"></div>
    </header>

    <!-- Overlay de Pesquisa Rápida -->
    <div class="search-overlay" id="searchOverlay">
        <div class="search-modal">
            <div class="search-modal-header">
                <h3 class="search-modal-title">Pesquisar Obras de Arte</h3>
                <button type="button" class="search-close-btn" id="btnCloseSearch">✕</button>
            </div>
            <div class="search-input-wrapper">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="searchInput" placeholder="Digite o nome da obra, artista ou estilo..." autocomplete="off">
            </div>
            <div class="search-quick-tags">
                <span class="quick-tag-label">Sugestões:</span>
                <button type="button" class="quick-tag" data-tag="Horizontes Dourados">Horizontes Dourados</button>
                <button type="button" class="quick-tag" data-tag="Silêncio Urbano">Silêncio Urbano</button>
                <button type="button" class="quick-tag" data-tag="Paisagens">Paisagens</button>
                <button type="button" class="quick-tag" data-tag="Abstrato">Abstrato</button>
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

