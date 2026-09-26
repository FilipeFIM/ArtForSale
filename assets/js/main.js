/**
 * ART FOR SALE - JavaScript Vanilla Principal (main.js)
 * Interações de interface: Header, Drawer Mobile, Favoritos, Modal de Consulta, Pesquisa e Rolagem
 */

document.addEventListener('DOMContentLoaded', () => {

    /* ==========================================================
       1. CONTROLE DO HEADER STICKY
       ========================================================== */
    const siteHeader = document.getElementById('siteHeader');
    const btnBackToTop = document.getElementById('btnBackToTop');

    window.addEventListener('scroll', () => {
        const scrollPos = window.scrollY;

        // Adiciona classe de sombra e desfoque no header ao rolar
        if (scrollPos > 40) {
            siteHeader.classList.add('scrolled');
        } else {
            siteHeader.classList.remove('scrolled');
        }

        // Exibe ou oculta botão voltar ao topo
        if (btnBackToTop) {
            if (scrollPos > 400) {
                btnBackToTop.style.opacity = '1';
                btnBackToTop.style.pointerEvents = 'auto';
            } else {
                btnBackToTop.style.opacity = '0.7';
            }
        }
    });

    if (btnBackToTop) {
        btnBackToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /* ==========================================================
       2. MENU MOBILE DRAWER
       ========================================================== */
    const btnMobileMenu = document.getElementById('btnMobileMenu');
    const mobileDrawer = document.getElementById('mobileDrawer');
    const btnCloseDrawer = document.getElementById('btnCloseDrawer');
    const drawerBackdrop = document.getElementById('drawerBackdrop');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

    function openDrawer() {
        mobileDrawer.classList.add('open');
        drawerBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        mobileDrawer.classList.remove('open');
        drawerBackdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (btnMobileMenu) btnMobileMenu.addEventListener('click', openDrawer);
    if (btnCloseDrawer) btnCloseDrawer.addEventListener('click', closeDrawer);
    if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);

    mobileNavLinks.forEach(link => {
        link.addEventListener('click', closeDrawer);
    });

    /* ==========================================================
       3. SISTEMA DE FAVORITOS (LOCALSTORAGE + BADGE)
       ========================================================== */
    const favoritesCountEl = document.getElementById('favoritesCount');
    const favoriteBtns = document.querySelectorAll('.btn-favorite');
    const btnOpenFavorites = document.getElementById('btnOpenFavorites');

    function getFavorites() {
        try {
            return JSON.parse(localStorage.getItem('artsell_favorites')) || [];
        } catch (e) {
            return [];
        }
    }

    function saveFavorites(favorites) {
        try {
            localStorage.setItem('artsell_favorites', JSON.stringify(favorites));
        } catch (e) {}
        updateFavoritesUI();
    }

    function updateFavoritesUI() {
        const favorites = getFavorites();
        if (favoritesCountEl) {
            favoritesCountEl.textContent = favorites.length;
        }

        const currentFavoriteBtns = document.querySelectorAll('.btn-favorite');
        currentFavoriteBtns.forEach(btn => {
            const cardId = parseInt(btn.dataset.id, 10);
            if (favorites.includes(cardId)) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    // Delegação de evento para Favoritos (funciona tanto na Home quanto no Catálogo filtrado)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-favorite');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();
        const cardId = parseInt(btn.dataset.id, 10);
        let favorites = getFavorites();

        if (favorites.includes(cardId)) {
            favorites = favorites.filter(id => id !== cardId);
        } else {
            favorites.push(cardId);
        }

        saveFavorites(favorites);
    });

    if (btnOpenFavorites) {
        btnOpenFavorites.addEventListener('click', () => {
            const sectionFeatured = document.getElementById('obras-destaque');
            if (sectionFeatured) {
                sectionFeatured.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    // Inicializa estado dos favoritos
    updateFavoritesUI();

    /* ==========================================================
       4. MODAL DE CONSULTA DE OBRA / VER DETALHES
       ========================================================== */
    const inquiryModal = document.getElementById('inquiryModal');
    const btnCloseInquiry = document.getElementById('btnCloseInquiry');
    const modalArtworkTitle = document.getElementById('modalArtworkTitle');
    const modalArtworkSubtitle = document.getElementById('modalArtworkSubtitle');
    const modalPreviewBox = document.getElementById('modalPreviewBox');
    const modalArtworkImg = document.getElementById('modalArtworkImg');
    const modalMetaTitle = document.getElementById('modalMetaTitle');
    const modalMetaArtist = document.getElementById('modalMetaArtist');
    const modalMetaDimensions = document.getElementById('modalMetaDimensions');
    const btnModalWhatsElisabeth = document.getElementById('btnModalWhatsElisabeth');
    const btnModalWhatsFelipe = document.getElementById('btnModalWhatsFelipe');
    const btnModalEmail = document.getElementById('btnModalEmail');

    const defaultElisabethUrl = btnModalWhatsElisabeth ? btnModalWhatsElisabeth.href : '#';
    const defaultFelipeUrl = btnModalWhatsFelipe ? btnModalWhatsFelipe.href : '#';
    const defaultEmailUrl = btnModalEmail ? btnModalEmail.href : '#';

    function openInquiryModal(artworkData = null) {
        if (!inquiryModal) return;

        if (artworkData) {
            modalArtworkTitle.textContent = `Consultar: ${artworkData.title}`;
            modalArtworkSubtitle.textContent = `Informações exclusivas sobre procedência, valor e envio.`;
            modalPreviewBox.style.display = 'flex';
            modalArtworkImg.src = artworkData.image;
            modalArtworkImg.alt = artworkData.title;
            modalMetaTitle.textContent = artworkData.title;
            modalMetaArtist.textContent = artworkData.artist;
            modalMetaDimensions.textContent = artworkData.dimensions;

            const textElisabeth = encodeURIComponent(`Olá, Sra. Elisabeth. Gostaria de saber mais sobre a obra "${artworkData.title}" (${artworkData.artist}, ${artworkData.dimensions}) da Art For Sale.`);
            const textFelipe = encodeURIComponent(`Olá, Felipe. Gostaria de consultar a obra "${artworkData.title}" (${artworkData.artist}, ${artworkData.dimensions}) da Art For Sale.`);
            const mailSubject = encodeURIComponent(`Consulta: Obra ${artworkData.title} - Art For Sale`);

            if (btnModalWhatsElisabeth) btnModalWhatsElisabeth.href = `https://wa.me/555191140044?text=${textElisabeth}`;
            if (btnModalWhatsFelipe) btnModalWhatsFelipe.href = `https://wa.me/5551991266414?text=${textFelipe}`;
            if (btnModalEmail) btnModalEmail.href = `mailto:artforsale1944@gmail.com?subject=${mailSubject}`;
        } else {
            modalArtworkTitle.textContent = 'Consultar Obra';
            modalArtworkSubtitle.textContent = 'Atendimento reservado para colecionadores e apreciadores.';
            modalPreviewBox.style.display = 'none';

            if (btnModalWhatsElisabeth) btnModalWhatsElisabeth.href = defaultElisabethUrl;
            if (btnModalWhatsFelipe) btnModalWhatsFelipe.href = defaultFelipeUrl;
            if (btnModalEmail) btnModalEmail.href = defaultEmailUrl;
        }

        inquiryModal.classList.add('open');
        inquiryModal.scrollTop = 0;
        const modalContent = inquiryModal.querySelector('.inquiry-modal-content');
        if (modalContent) modalContent.scrollTop = 0;
        document.body.style.overflow = 'hidden';
    }

    function closeInquiryModal() {
        if (!inquiryModal) return;
        inquiryModal.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (btnCloseInquiry) btnCloseInquiry.addEventListener('click', closeInquiryModal);
    if (inquiryModal) {
        inquiryModal.addEventListener('click', (e) => {
            if (e.target === inquiryModal) closeInquiryModal();
        });
    }

    // Gatilhos para "Consultar obra" e abertura da obra ao clicar em qualquer parte do card (Desktop e Mobile)
    document.addEventListener('click', (e) => {
        // Se clicou no botão "Consultar obra" (header, banner, etc.)
        const consultBtn = e.target.closest('[data-modal="consultar"]');
        if (consultBtn) {
            e.preventDefault();
            openInquiryModal();
            return;
        }

        // Se clicou no botão de favoritos, deixa o evento de favoritos tratar sem abrir a obra
        if (e.target.closest('.btn-favorite')) {
            return;
        }

        // Se clicou em qualquer botão de ação ou controle interativo específico (exceto ver detalhes)
        if (e.target.closest('button:not(.btn-ver-detalhes)')) {
            return;
        }

        // Verifica se clicou dentro de um card de obra
        const card = e.target.closest('.artwork-card');
        if (card) {
            // Se o clique foi diretamente no link <a> com href válido para a obra
            const clickedLink = e.target.closest('a');
            if (clickedLink && clickedLink.getAttribute('href') && !clickedLink.getAttribute('href').startsWith('#')) {
                return; // O navegador segue naturalmente o link
            }

            // Procura o link de detalhes da obra dentro do card
            const detailLink = card.querySelector('a.btn-ver-detalhes, a.artwork-image-link, a[href*="obra.php"]');
            if (detailLink && detailLink.getAttribute('href')) {
                window.location.href = detailLink.getAttribute('href');
                return;
            }

            // Fallback usando data-id do card
            const artworkId = card.dataset.id;
            if (artworkId) {
                const isPages = window.location.pathname.includes('/pages/');
                const targetUrl = (isPages ? '' : 'pages/') + 'obra.php?id=' + encodeURIComponent(artworkId);
                window.location.href = targetUrl;
            }
        }
    });

    /* ==========================================================
       5. MODAL DE PESQUISA RÁPIDA
       ========================================================== */
    const btnOpenSearch = document.getElementById('btnOpenSearch');
    const searchOverlay = document.getElementById('searchOverlay');
    const btnCloseSearch = document.getElementById('btnCloseSearch');
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const quickTags = document.querySelectorAll('.quick-tag');

    function openSearch() {
        if (!searchOverlay) return;
        searchOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (searchInput) {
            setTimeout(() => searchInput.focus(), 150);
        }
    }

    function closeSearch() {
        if (!searchOverlay) return;
        searchOverlay.classList.remove('open');
        document.body.style.overflow = '';
        if (searchInput) searchInput.value = '';
        if (searchResults) searchResults.innerHTML = '';
    }

    if (btnOpenSearch) btnOpenSearch.addEventListener('click', openSearch);
    if (btnCloseSearch) btnCloseSearch.addEventListener('click', closeSearch);
    if (searchOverlay) {
        searchOverlay.addEventListener('click', (e) => {
            if (e.target === searchOverlay) closeSearch();
        });
    }

    // Função de sanitização contra XSS no DOM
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Busca instantânea no acervo da página
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            if (!query) {
                searchResults.innerHTML = '';
                return;
            }

            const cards = document.querySelectorAll('.artwork-card');
            let resultsHtml = '';
            let count = 0;
            const isInsidePages = window.location.pathname.includes('/pages/');
            const detailPathPrefix = isInsidePages ? 'obra.php?id=' : 'pages/obra.php?id=';

            cards.forEach(card => {
                const title = (card.dataset.title || '').toLowerCase();
                const artist = (card.dataset.artist || '').toLowerCase();

                if (title.includes(query) || artist.includes(query)) {
                    count++;
                    const safeTitle = escapeHtml(card.dataset.title || 'Obra');
                    const safeArtist = escapeHtml(card.dataset.artist || 'Artista');
                    const safeId = encodeURIComponent(card.dataset.id || '');
                    const safeImage = escapeHtml(card.dataset.image || '');

                    resultsHtml += `
                        <a href="${detailPathPrefix}${safeId}" class="inquiry-preview-box" style="text-decoration: none; color: inherit; cursor: pointer; display: flex;">
                            <img src="${safeImage}" alt="${safeTitle}" class="inquiry-preview-img">
                            <div class="inquiry-preview-meta">
                                <h4 class="inquiry-meta-title">${safeTitle}</h4>
                                <p class="inquiry-meta-artist">${safeArtist}</p>
                                <span class="inquiry-meta-price">Ver detalhes →</span>
                            </div>
                        </a>
                    `;
                }
            });

            if (count === 0) {
                const safeQuery = escapeHtml(e.target.value);
                resultsHtml = `<p style="font-size: 0.85rem; color: #888; padding: 1rem 0;">Nenhuma obra encontrada para "${safeQuery}".</p>`;
            }

            searchResults.innerHTML = resultsHtml;
        });
    }

    quickTags.forEach(tag => {
        tag.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = tag.dataset.tag;
                searchInput.dispatchEvent(new Event('input'));
            }
        });
    });

    // Tecla ESC para fechar modais
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeInquiryModal();
            closeSearch();
            closeDrawer();
        }
    });

    /* ==========================================================
       6. CONTROLADOR DO CATÁLOGO DE OBRAS (/pages/obras.php)
       Pesquisa em tempo real, filtros por categoria, ordenação e paginação visual
       ========================================================== */
    const catalogoGrid = document.getElementById('catalogoGrid');
    if (catalogoGrid) {
        const catalogoSearch = document.getElementById('catalogoSearch');
        const catalogoSearchClear = document.getElementById('catalogoSearchClear');
        const filterPills = document.querySelectorAll('.filter-pill');
        const catalogoSort = document.getElementById('catalogoSort');
        const catalogoCount = document.getElementById('catalogoCount');
        const catalogoActiveBadge = document.getElementById('catalogoActiveBadge');
        const catalogoPagination = document.getElementById('catalogoPagination');
        const catalogoLoading = document.getElementById('catalogoLoading');
        const catalogoNoResults = document.getElementById('catalogoNoResults');
        const catalogoEmptyCategory = document.getElementById('catalogoEmptyCategory');
        const stateNoResultsMsg = document.getElementById('stateNoResultsMsg');
        const btnResetSearch = document.getElementById('btnResetSearch');
        const btnResetFilter = document.getElementById('btnResetFilter');

        // Coleta todos os cards originais presentes no DOM
        const originalCards = Array.from(catalogoGrid.querySelectorAll('.artwork-card'));

        const catalogoState = {
            searchQuery: '',
            selectedCategory: 'all',
            sortBy: 'recente',
            currentPage: 1,
            itemsPerPage: 8 // 8 itens por página para demonstrar paginação rica e fluida
        };

        // Função para remover acentos e normalizar buscas
        function normalizeText(str) {
            return (str || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        }

        // Retorna a lista de cards filtrados e ordenados
        function getFilteredAndSortedCards() {
            const queryNorm = normalizeText(catalogoState.searchQuery);
            const category = catalogoState.selectedCategory;

            // Filtro
            let filtered = originalCards.filter(card => {
                const cardCategory = card.dataset.category || '';
                const cardTitleNorm = normalizeText(card.dataset.title);
                const cardArtistNorm = normalizeText(card.dataset.artist);
                const cardCategoryNameNorm = normalizeText(card.dataset.categoryName);

                // Categoria
                const matchesCategory = (category === 'all' || cardCategory === category);
                if (!matchesCategory) return false;

                // Termo de busca (nome, artista ou categoria)
                if (!queryNorm) return true;

                return cardTitleNorm.includes(queryNorm) ||
                       cardArtistNorm.includes(queryNorm) ||
                       cardCategoryNameNorm.includes(queryNorm);
            });

            // Ordenação
            filtered.sort((a, b) => {
                if (catalogoState.sortBy === 'az') {
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '', 'pt-BR');
                } else if (catalogoState.sortBy === 'za') {
                    return (b.dataset.title || '').localeCompare(a.dataset.title || '', 'pt-BR');
                } else {
                    // Mais recentes: preserva a ordem do DOM renderizada pelo PHP (novas obras no topo)
                    const orderA = isNaN(parseInt(a.dataset.order, 10)) ? 0 : parseInt(a.dataset.order, 10);
                    const orderB = isNaN(parseInt(b.dataset.order, 10)) ? 0 : parseInt(b.dataset.order, 10);
                    if (orderA !== orderB) return orderA - orderB;
                    return originalCards.indexOf(a) - originalCards.indexOf(b);
                }
            });

            return filtered;
        }

        // Renderiza cards em estado skeleton dourado/museu
        function renderSkeletonLoading(count = 8) {
            let html = '';
            for (let i = 0; i < count; i++) {
                html += `
                    <article class="artwork-card skeleton-card" aria-hidden="true">
                        <div class="artwork-image-container">
                            <div class="skeleton-image skeleton-shimmer"></div>
                            <div class="skeleton-badge skeleton-shimmer"></div>
                        </div>
                        <div class="skeleton-content">
                            <div class="skeleton-line skeleton-title skeleton-shimmer"></div>
                            <div class="skeleton-line skeleton-artist skeleton-shimmer"></div>
                            <div class="skeleton-line skeleton-meta skeleton-shimmer"></div>
                            <div class="skeleton-footer">
                                <div class="skeleton-line skeleton-price skeleton-shimmer"></div>
                                <div class="skeleton-btn skeleton-shimmer"></div>
                            </div>
                        </div>
                    </article>
                `;
            }
            catalogoGrid.innerHTML = html;
            catalogoGrid.style.display = 'grid';
            catalogoGrid.style.opacity = '1';
        }

        // Renderiza o catálogo na tela
        function renderCatalogo(withLoading = false) {
            if (withLoading) {
                renderSkeletonLoading(Math.min(catalogoState.itemsPerPage, originalCards.length || 8));
            }

            const executeRender = () => {
                const filteredCards = getFilteredAndSortedCards();
                const totalFiltered = filteredCards.length;

                // Contagem total da categoria (para detectar estado "categoria vazia")
                const totalInCategory = catalogoState.selectedCategory === 'all' 
                    ? originalCards.length 
                    : originalCards.filter(c => c.dataset.category === catalogoState.selectedCategory).length;

                if (catalogoLoading) catalogoLoading.style.display = 'none';
                catalogoGrid.style.opacity = '1';

                // 1. Estado: Categoria Vazia (se a categoria selecionada não tiver nenhuma obra cadastrada)
                if (totalInCategory === 0 && catalogoState.selectedCategory !== 'all') {
                    catalogoGrid.style.display = 'none';
                    if (catalogoNoResults) catalogoNoResults.style.display = 'none';
                    if (catalogoEmptyCategory) catalogoEmptyCategory.style.display = 'block';
                    if (catalogoPagination) catalogoPagination.innerHTML = '';
                    updateMetaBar(0);
                    return;
                }

                // 2. Estado: Nenhum Resultado Encontrado (busca sem correspondência)
                if (totalFiltered === 0) {
                    catalogoGrid.style.display = 'none';
                    if (catalogoEmptyCategory) catalogoEmptyCategory.style.display = 'none';
                    if (catalogoNoResults) {
                        catalogoNoResults.style.display = 'block';
                        if (stateNoResultsMsg) {
                            if (catalogoState.searchQuery) {
                                stateNoResultsMsg.textContent = `Não encontramos obras para "${catalogoState.searchQuery}". Tente verificar a ortografia ou buscar por outros artistas/estilos.`;
                            } else {
                                stateNoResultsMsg.textContent = 'Não encontramos nenhuma obra com os filtros selecionados.';
                            }
                        }
                    }
                    if (catalogoPagination) catalogoPagination.innerHTML = '';
                    updateMetaBar(0);
                    return;
                }

                // Há resultados: esconde estados vazios e exibe a grade
                catalogoGrid.style.display = 'grid';
                if (catalogoNoResults) catalogoNoResults.style.display = 'none';
                if (catalogoEmptyCategory) catalogoEmptyCategory.style.display = 'none';

                // Paginação
                const totalPages = Math.ceil(totalFiltered / catalogoState.itemsPerPage) || 1;
                if (catalogoState.currentPage > totalPages) {
                    catalogoState.currentPage = totalPages;
                }
                if (catalogoState.currentPage < 1) {
                    catalogoState.currentPage = 1;
                }

                const startIndex = (catalogoState.currentPage - 1) * catalogoState.itemsPerPage;
                const endIndex = startIndex + catalogoState.itemsPerPage;
                const currentSlice = filteredCards.slice(startIndex, endIndex);

                // Atualiza o DOM do grid
                catalogoGrid.innerHTML = '';
                currentSlice.forEach(card => {
                    catalogoGrid.appendChild(card);
                });

                // Atualiza contadores, paginação e botões de favorito
                updateMetaBar(totalFiltered, startIndex + 1, Math.min(endIndex, totalFiltered));
                renderPagination(totalPages);
                updateFavoritesUI();
            };

            if (withLoading) {
                setTimeout(executeRender, 140);
            } else {
                executeRender();
            }
        }

        // Atualiza a barra de contagem e badge de filtro ativo
        function updateMetaBar(total, start = 0, end = 0) {
            if (!catalogoCount) return;

            if (total === 0) {
                catalogoCount.innerHTML = 'Nenhuma obra encontrada';
            } else if (total <= catalogoState.itemsPerPage) {
                catalogoCount.innerHTML = `Mostrando <strong>${total}</strong> de <strong>${total}</strong> obras`;
            } else {
                catalogoCount.innerHTML = `Mostrando <strong>${start}–${end}</strong> de <strong>${total}</strong> obras`;
            }

            if (catalogoActiveBadge) {
                if (catalogoState.selectedCategory !== 'all') {
                    const activePill = document.querySelector(`.filter-pill[data-category="${catalogoState.selectedCategory}"]`);
                    const catName = activePill ? activePill.textContent.trim() : catalogoState.selectedCategory;
                    catalogoActiveBadge.textContent = catName;
                    catalogoActiveBadge.style.display = 'inline-block';
                } else {
                    catalogoActiveBadge.style.display = 'none';
                }
            }
        }

        // Renderiza paginação visual: ← 1 2 3 ... →
        function renderPagination(totalPages) {
            if (!catalogoPagination) return;

            if (totalPages <= 1) {
                catalogoPagination.innerHTML = '';
                return;
            }

            const current = catalogoState.currentPage;
            let html = '';

            // Seta Anterior ←
            const prevDisabled = current === 1 ? 'disabled aria-disabled="true"' : '';
            html += `<button type="button" class="page-btn page-arrow" data-page="${current - 1}" ${prevDisabled} aria-label="Página anterior">←</button>`;

            // Lógica com reticências
            if (totalPages <= 5) {
                for (let i = 1; i <= totalPages; i++) {
                    const activeClass = i === current ? 'active' : '';
                    html += `<button type="button" class="page-btn ${activeClass}" data-page="${i}" aria-label="Página ${i}">${i}</button>`;
                }
            } else {
                // Primeira página sempre
                html += `<button type="button" class="page-btn ${current === 1 ? 'active' : ''}" data-page="1" aria-label="Página 1">1</button>`;

                if (current > 3) {
                    html += `<span class="page-ellipsis">…</span>`;
                }

                // Páginas vizinhas
                const startRange = Math.max(2, current - 1);
                const endRange = Math.min(totalPages - 1, current + 1);

                for (let i = startRange; i <= endRange; i++) {
                    const activeClass = i === current ? 'active' : '';
                    html += `<button type="button" class="page-btn ${activeClass}" data-page="${i}" aria-label="Página ${i}">${i}</button>`;
                }

                if (current < totalPages - 2) {
                    html += `<span class="page-ellipsis">…</span>`;
                }

                // Última página sempre
                html += `<button type="button" class="page-btn ${current === totalPages ? 'active' : ''}" data-page="${totalPages}" aria-label="Página ${totalPages}">${totalPages}</button>`;
            }

            // Seta Próxima →
            const nextDisabled = current === totalPages ? 'disabled aria-disabled="true"' : '';
            html += `<button type="button" class="page-btn page-arrow" data-page="${current + 1}" ${nextDisabled} aria-label="Próxima página">→</button>`;

            catalogoPagination.innerHTML = html;
        }

        // Listener da Paginação
        if (catalogoPagination) {
            catalogoPagination.addEventListener('click', (e) => {
                const btn = e.target.closest('.page-btn');
                if (!btn || btn.disabled || btn.classList.contains('active')) return;

                const targetPage = parseInt(btn.dataset.page, 10);
                if (targetPage && !isNaN(targetPage)) {
                    catalogoState.currentPage = targetPage;
                    renderCatalogo(true);

                    // Rolagem suave até o início do catálogo
                    const catalogoSection = document.getElementById('catalogoSection');
                    if (catalogoSection) {
                        const topPos = catalogoSection.getBoundingClientRect().top + window.pageYOffset - 90;
                        window.scrollTo({ top: topPos, behavior: 'smooth' });
                    }
                }
            });
        }

        // Listener da Pesquisa em Tempo Real
        if (catalogoSearch) {
            let debounceTimer;
            catalogoSearch.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                const val = e.target.value;
                catalogoState.searchQuery = val;
                catalogoState.currentPage = 1;

                if (catalogoSearchClear) {
                    catalogoSearchClear.style.display = val.trim() ? 'flex' : 'none';
                }

                debounceTimer = setTimeout(() => {
                    renderCatalogo(true);
                }, 120);
            });
        }

        // Limpar Pesquisa
        if (catalogoSearchClear) {
            catalogoSearchClear.addEventListener('click', () => {
                if (catalogoSearch) {
                    catalogoSearch.value = '';
                    catalogoState.searchQuery = '';
                    catalogoState.currentPage = 1;
                    catalogoSearchClear.style.display = 'none';
                    catalogoSearch.focus();
                    renderCatalogo(false);
                }
            });
        }

        // Listener dos Filtros de Categoria (Pills)
        filterPills.forEach(pill => {
            pill.addEventListener('click', () => {
                filterPills.forEach(p => {
                    p.classList.remove('active');
                    p.setAttribute('aria-selected', 'false');
                });
                pill.classList.add('active');
                pill.setAttribute('aria-selected', 'true');

                catalogoState.selectedCategory = pill.dataset.category || 'all';
                catalogoState.currentPage = 1;
                renderCatalogo(true);
            });
        });

        // Listener da Ordenação
        if (catalogoSort) {
            catalogoSort.addEventListener('change', (e) => {
                catalogoState.sortBy = e.target.value;
                renderCatalogo(true);
            });
        }

        // Botões de Reset dos Estados
        if (btnResetSearch) {
            btnResetSearch.addEventListener('click', () => {
                if (catalogoSearch) catalogoSearch.value = '';
                catalogoState.searchQuery = '';
                if (catalogoSearchClear) catalogoSearchClear.style.display = 'none';
                catalogoState.currentPage = 1;
                renderCatalogo(false);
            });
        }

        if (btnResetFilter) {
            btnResetFilter.addEventListener('click', () => {
                const allPill = document.querySelector('.filter-pill[data-category="all"]');
                if (allPill) allPill.click();
            });
        }

        // Leitura de parâmetros de URL para filtros automáticos (?categoria=xxx ou ?q=xxx)
        const urlParams = new URLSearchParams(window.location.search);
        const catParam = urlParams.get('categoria');
        const qParam = urlParams.get('q');

        if (catParam) {
            const targetPill = document.querySelector(`.filter-pill[data-category="${catParam}"]`);
            if (targetPill) {
                filterPills.forEach(p => p.classList.remove('active'));
                targetPill.classList.add('active');
                catalogoState.selectedCategory = catParam;
            }
        }

        if (qParam) {
            catalogoState.searchQuery = qParam;
            if (catalogoSearch) catalogoSearch.value = qParam;
            if (catalogoSearchClear) catalogoSearchClear.style.display = 'flex';
        }

        // Renderização inicial
        renderCatalogo(false);
    }

    /* ==========================================================
       7. CONTROLADOR DE GALERIA E DETALHES DA OBRA (/pages/obra.php)
       Múltiplas fotos, miniaturas interativas, setas de navegação,
       modal de zoom/ampliação em tela cheia e botão único de interesse.
       ========================================================== */
    const obraMainImage = document.getElementById('obraMainImage');
    if (obraMainImage) {
        const obraThumbnails = document.querySelectorAll('.thumb-btn');
        const galleryPrevBtn = document.getElementById('galleryPrevBtn');
        const galleryNextBtn = document.getElementById('galleryNextBtn');
        const galleryCaptionText = document.getElementById('galleryCaptionText');
        const galleryCounter = document.getElementById('galleryCounter');
        const btnOpenZoom = document.getElementById('btnOpenZoom');

        // Modal de Zoom
        const obraZoomModal = document.getElementById('obraZoomModal');
        const btnCloseZoom = document.getElementById('btnCloseZoom');
        const zoomMainImg = document.getElementById('zoomMainImg');
        const zoomCaptionText = document.getElementById('zoomCaptionText');
        const zoomPrevBtn = document.getElementById('zoomPrevBtn');
        const zoomNextBtn = document.getElementById('zoomNextBtn');
        const btnToggleZoomLevel = document.getElementById('btnToggleZoomLevel');
        const zoomThumbBtns = document.querySelectorAll('.zoom-thumb-btn');
        const zoomImageStage = document.getElementById('zoomImageStage');

        // Botão Único de Interesse
        const btnTenhoInteresse = document.getElementById('btnTenhoInteresse');

        // Coleta itens da galeria a partir das miniaturas
        const galleryItems = Array.from(obraThumbnails).map(btn => ({
            img: btn.dataset.img,
            caption: btn.dataset.caption
        }));

        let currentIdx = 0;

        function updateGalleryView(newIndex) {
            if (!galleryItems.length) return;

            if (newIndex < 0) newIndex = galleryItems.length - 1;
            if (newIndex >= galleryItems.length) newIndex = 0;

            currentIdx = newIndex;
            const item = galleryItems[currentIdx];

            // 1. Atualiza imagem principal com transição suave
            obraMainImage.style.opacity = '0.3';
            setTimeout(() => {
                obraMainImage.src = item.img;
                obraMainImage.alt = item.caption;
                obraMainImage.dataset.currentIndex = currentIdx;
                obraMainImage.style.opacity = '1';
            }, 120);

            // 2. Atualiza legenda e contador
            if (galleryCaptionText) galleryCaptionText.textContent = item.caption;
            if (galleryCounter) galleryCounter.textContent = `${currentIdx + 1} / ${galleryItems.length}`;

            // 3. Atualiza estado das miniaturas na página
            obraThumbnails.forEach((btn, idx) => {
                if (idx === currentIdx) {
                    btn.classList.add('active');
                    btn.setAttribute('aria-selected', 'true');
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                }
            });

            // 4. Sincroniza imagem e legenda do modal de zoom
            if (zoomMainImg) {
                zoomMainImg.src = item.img;
                zoomMainImg.alt = item.caption;
                zoomMainImg.classList.remove('zoomed');
            }
            if (zoomCaptionText) zoomCaptionText.textContent = item.caption;

            // 5. Atualiza miniaturas dentro do modal de zoom
            zoomThumbBtns.forEach((btn, idx) => {
                if (idx === currentIdx) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }

        // Eventos das Miniaturas na Página
        obraThumbnails.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetIdx = parseInt(btn.dataset.index, 10);
                if (!isNaN(targetIdx)) {
                    updateGalleryView(targetIdx);
                }
            });
        });

        // Setas de navegação da galeria
        if (galleryPrevBtn) {
            galleryPrevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                updateGalleryView(currentIdx - 1);
            });
        }

        if (galleryNextBtn) {
            galleryNextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                updateGalleryView(currentIdx + 1);
            });
        }

        // ==========================================
        // Modal de Zoom / Lightbox
        // ==========================================
        function openZoomModal() {
            if (!obraZoomModal) return;
            updateGalleryView(currentIdx);
            obraZoomModal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeZoomModal() {
            if (!obraZoomModal) return;
            obraZoomModal.classList.remove('open');
            if (zoomMainImg) zoomMainImg.classList.remove('zoomed');
            document.body.style.overflow = '';
        }

        // Abrir ao clicar no botão "Ampliar" ou na imagem principal
        if (btnOpenZoom) btnOpenZoom.addEventListener('click', openZoomModal);
        obraMainImage.addEventListener('click', openZoomModal);

        if (btnCloseZoom) btnCloseZoom.addEventListener('click', closeZoomModal);

        // Setas dentro do modal de zoom
        if (zoomPrevBtn) {
            zoomPrevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                updateGalleryView(currentIdx - 1);
            });
        }

        if (zoomNextBtn) {
            zoomNextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                updateGalleryView(currentIdx + 1);
            });
        }

        // Miniaturas dentro do modal de zoom
        zoomThumbBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const targetIdx = parseInt(btn.dataset.index, 10);
                if (!isNaN(targetIdx)) {
                    updateGalleryView(targetIdx);
                }
            });
        });

        // Alternar escala de ampliação (Zoom in / Zoom out)
        function toggleZoomLevel() {
            if (zoomMainImg) {
                zoomMainImg.classList.toggle('zoomed');
            }
        }

        if (zoomMainImg) {
            zoomMainImg.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleZoomLevel();
            });
        }

        if (btnToggleZoomLevel) {
            btnToggleZoomLevel.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleZoomLevel();
            });
        }

        // Fechar ao clicar fora da imagem no backdrop
        if (obraZoomModal) {
            obraZoomModal.addEventListener('click', (e) => {
                if (e.target === obraZoomModal || e.target === zoomImageStage) {
                    closeZoomModal();
                }
            });
        }

        // Teclado: Navegação por setas e ESC para fechar
        document.addEventListener('keydown', (e) => {
            if (obraZoomModal && obraZoomModal.classList.contains('open')) {
                if (e.key === 'Escape') {
                    closeZoomModal();
                } else if (e.key === 'ArrowLeft') {
                    updateGalleryView(currentIdx - 1);
                } else if (e.key === 'ArrowRight') {
                    updateGalleryView(currentIdx + 1);
                }
            }
        });

        // ==========================================
        // Botão Único de Interesse: "Tenho interesse nesta obra →"
        // ==========================================
        if (btnTenhoInteresse) {
            btnTenhoInteresse.addEventListener('click', () => {
                const artworkData = {
                    title: btnTenhoInteresse.dataset.artworkTitle,
                    artist: btnTenhoInteresse.dataset.artworkArtist,
                    dimensions: btnTenhoInteresse.dataset.artworkDimensions,
                    image: btnTenhoInteresse.dataset.artworkImage
                };
                openInquiryModal(artworkData);
            });
        }
    }

    /* ==========================================================
       8. CONTROLADOR DO CARROSSEL DE OBRAS EM DESTAQUE (Home - #obras-destaque)
       Setas laterais de navegação suave, paginação visual por dots
       e compatibilidade touch/swipe responsivo.
       ========================================================== */
    const featuredTrack = document.getElementById('featuredTrack');
    if (featuredTrack) {
        const featuredPrevBtn = document.getElementById('featuredPrevBtn');
        const featuredNextBtn = document.getElementById('featuredNextBtn');
        const carouselDotsContainer = document.getElementById('carouselDots');

        function getItemsPerPage() {
            const width = window.innerWidth;
            if (width <= 600) return 1;
            if (width <= 992) return 2;
            return 4;
        }

        function getTotalPages() {
            const cards = featuredTrack.querySelectorAll('.artwork-card');
            const perPage = getItemsPerPage();
            return Math.max(1, Math.ceil(cards.length / perPage));
        }

        function updateDots(activePage) {
            if (!carouselDotsContainer) return;
            const dots = carouselDotsContainer.querySelectorAll('.dot');
            dots.forEach((dot, idx) => {
                if (idx === activePage) {
                    dot.classList.add('active');
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.classList.remove('active');
                    dot.removeAttribute('aria-current');
                }
            });
        }

        function renderDots() {
            if (!carouselDotsContainer) return;
            const totalPages = getTotalPages();
            if (totalPages <= 1) {
                carouselDotsContainer.style.display = 'none';
                return;
            }
            carouselDotsContainer.style.display = 'flex';
            let dotsHtml = '';
            for (let i = 0; i < totalPages; i++) {
                dotsHtml += `<button type="button" class="dot ${i === 0 ? 'active' : ''}" data-index="${i}" aria-label="Ir para página ${i + 1} dos destaques"></button>`;
            }
            carouselDotsContainer.innerHTML = dotsHtml;
        }

        function getCurrentPage() {
            const scrollLeft = featuredTrack.scrollLeft;
            const pageWidth = featuredTrack.clientWidth;
            if (pageWidth === 0) return 0;
            return Math.min(getTotalPages() - 1, Math.max(0, Math.round(scrollLeft / pageWidth)));
        }

        function scrollToPage(pageIndex) {
            const totalPages = getTotalPages();
            let targetPage = pageIndex;
            if (targetPage < 0) targetPage = totalPages - 1;
            if (targetPage >= totalPages) targetPage = 0;

            const pageWidth = featuredTrack.clientWidth;
            featuredTrack.scrollTo({
                left: targetPage * pageWidth,
                behavior: 'smooth'
            });
            updateDots(targetPage);
        }

        if (featuredPrevBtn) {
            featuredPrevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const curr = getCurrentPage();
                scrollToPage(curr - 1);
            });
        }

        if (featuredNextBtn) {
            featuredNextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const curr = getCurrentPage();
                scrollToPage(curr + 1);
            });
        }

        if (carouselDotsContainer) {
            carouselDotsContainer.addEventListener('click', (e) => {
                const dot = e.target.closest('.dot');
                if (!dot) return;
                const targetIndex = parseInt(dot.dataset.index, 10);
                if (!isNaN(targetIndex)) {
                    scrollToPage(targetIndex);
                }
            });
        }

        let scrollTimeout;
        featuredTrack.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                const page = getCurrentPage();
                updateDots(page);
            }, 60);
        });

        // Inicializa dots dinamicamente de acordo com o tamanho da tela
        renderDots();
        window.addEventListener('resize', () => {
            renderDots();
            updateDots(getCurrentPage());
        });
    }

});

