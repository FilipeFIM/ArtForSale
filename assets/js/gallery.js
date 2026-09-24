/**
 * ART SELL - JavaScript de Galeria e Carrossel (gallery.js)
 * Gerencia o carrossel de obras em destaque, paginação e garantia de renderização das imagens do mockup
 */

document.addEventListener('DOMContentLoaded', () => {

    /* ==========================================================
       1. CARROSSEL DE OBRAS EM DESTAQUE
       ========================================================== */
    const featuredTrack = document.getElementById('featuredTrack');
    const featuredPrevBtn = document.getElementById('featuredPrevBtn');
    const featuredNextBtn = document.getElementById('featuredNextBtn');
    const carouselDots = document.querySelectorAll('#carouselDots .dot');
    const artworkCards = document.querySelectorAll('.artwork-card');

    let currentSlide = 0;
    const totalCards = artworkCards.length;

    function getVisibleCards() {
        const width = window.innerWidth;
        if (width <= 576) return 1;
        if (width <= 992) return 2;
        return 4;
    }

    function updateCarousel(index) {
        const visible = getVisibleCards();
        const maxIndex = Math.max(0, totalCards - visible);

        // Limita o índice dentro do intervalo válido
        currentSlide = Math.max(0, Math.min(index, maxIndex));

        // Atualiza estado visual dos pontos (dots)
        carouselDots.forEach((dot, i) => {
            if (i === currentSlide % carouselDots.length) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });

        // Efeito sutil de destaque no card ativo
        artworkCards.forEach((card, i) => {
            if (i >= currentSlide && i < currentSlide + visible) {
                card.style.opacity = '1';
            } else {
                card.style.opacity = '0.92';
            }
        });
    }

    if (featuredPrevBtn) {
        featuredPrevBtn.addEventListener('click', () => {
            updateCarousel(currentSlide - 1);
        });
    }

    if (featuredNextBtn) {
        featuredNextBtn.addEventListener('click', () => {
            updateCarousel(currentSlide + 1);
        });
    }

    carouselDots.forEach(dot => {
        dot.addEventListener('click', () => {
            const targetIdx = parseInt(dot.dataset.index, 10);
            updateCarousel(targetIdx);
        });
    });

    window.addEventListener('resize', () => {
        updateCarousel(currentSlide);
    });

    /* ==========================================================
       2. CONTROLES DE SLIDE DO HERO
       ========================================================== */
    const heroPrevBtn = document.getElementById('heroPrevBtn');
    const heroNextBtn = document.getElementById('heroNextBtn');
    const heroPageNumbers = document.querySelectorAll('.hero-pagination .page-number');
    let currentHeroSlide = 1;
    const totalHeroSlides = 3;

    function updateHeroSlide(slideNum) {
        if (slideNum < 1) slideNum = totalHeroSlides;
        if (slideNum > totalHeroSlides) slideNum = 1;
        currentHeroSlide = slideNum;

        heroPageNumbers.forEach((el, idx) => {
            if (idx + 1 === currentHeroSlide) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
    }

    if (heroPrevBtn) {
        heroPrevBtn.addEventListener('click', () => {
            updateHeroSlide(currentHeroSlide - 1);
        });
    }

    if (heroNextBtn) {
        heroNextBtn.addEventListener('click', () => {
            updateHeroSlide(currentHeroSlide + 1);
        });
    }

    /* ==========================================================
       3. FALLBACK INTELIGENTE DAS IMAGENS DO MOCKUP (CANVAS)
       Garante que se as imagens recortadas ainda não foram geradas pelo PHP,
       elas sejam recortadas em tempo real da imagem original do mockup via Canvas!
       ========================================================== */
    const cropCoordinates = {
        'cat_classica': { x: 0.146, y: 0.369, w: 0.081, h: 0.072 },
        'cat_moderna': { x: 0.235, y: 0.369, w: 0.081, h: 0.072 },
        'cat_contemporanea': { x: 0.324, y: 0.369, w: 0.081, h: 0.072 },
        'cat_paisagens': { x: 0.413, y: 0.369, w: 0.081, h: 0.072 },
        'cat_retratos': { x: 0.503, y: 0.369, w: 0.081, h: 0.072 },
        'cat_abstrato': { x: 0.593, y: 0.369, w: 0.081, h: 0.072 },
        'cat_gravuras': { x: 0.683, y: 0.369, w: 0.081, h: 0.072 },
        'cat_esculturas': { x: 0.772, y: 0.369, w: 0.081, h: 0.072 },
        'obra_horizontes_dourados': { x: 0.146, y: 0.514, w: 0.170, h: 0.087 },
        'obra_silencio_urbano': { x: 0.325, y: 0.514, w: 0.170, h: 0.087 },
        'obra_essencia': { x: 0.504, y: 0.514, w: 0.170, h: 0.087 },
        'obra_azul_profundo': { x: 0.683, y: 0.514, w: 0.170, h: 0.087 },
        'about_art_sell': { x: 0.0, y: 0.684, w: 0.395, h: 0.129 },
        'hero': { x: 0.0, y: 0.038, w: 1.0, h: 0.280 },
        'team': { x: 0.0, y: 0.813, w: 0.440, h: 0.100 }
    };

    const isSubpage = window.location.pathname.includes('/pages/');
    const basePathPrefix = isSubpage ? '../' : '';
    const mockupSourceUrl = basePathPrefix + 'assets/images/site/mockup_original.jpg';

    // Função que extrai o recorte e aplica na tag img
    function setupCanvasCropFallback() {
        const cropElements = document.querySelectorAll('[data-crop]');
        if (cropElements.length === 0) return;

        let baseImg = new Image();
        let isLoaded = false;

        baseImg.onload = () => {
            isLoaded = true;
            applyCrops();
        };

        baseImg.src = mockupSourceUrl;

        function applyCrops() {
            cropElements.forEach(imgEl => {
                const cropKey = imgEl.dataset.crop;
                const coords = cropCoordinates[cropKey];

                if (!coords) return;

                // Testa se a imagem já carregou normalmente
                if (imgEl.complete && imgEl.naturalWidth > 50) {
                    return; // Já carregou o JPG do disco com sucesso
                }

                try {
                    const canvas = document.createElement('canvas');
                    const sx = coords.x * baseImg.naturalWidth;
                    const sy = coords.y * baseImg.naturalHeight;
                    const sw = coords.w * baseImg.naturalWidth;
                    const sh = coords.h * baseImg.naturalHeight;

                    canvas.width = sw;
                    canvas.height = sh;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(baseImg, sx, sy, sw, sh, 0, 0, sw, sh);

                    imgEl.src = canvas.toDataURL('image/jpeg', 0.92);
                } catch (e) {
                    // Sem permissão de cross-origin ou canvas não suportado
                }
            });

            // Fundo do Hero e Banner Equipe
            const heroBg = document.querySelector('[data-mockup-fallback="hero"]');
            if (heroBg) {
                const testImg = new Image();
                testImg.onerror = () => {
                    try {
                        const coords = cropCoordinates['hero'];
                        const canvas = document.createElement('canvas');
                        const sx = coords.x * baseImg.naturalWidth;
                        const sy = coords.y * baseImg.naturalHeight;
                        const sw = coords.w * baseImg.naturalWidth;
                        const sh = coords.h * baseImg.naturalHeight;
                        canvas.width = sw;
                        canvas.height = sh;
                        canvas.getContext('2d').drawImage(baseImg, sx, sy, sw, sh, 0, 0, sw, sh);
                        heroBg.style.backgroundImage = `url(${canvas.toDataURL('image/jpeg', 0.92)})`;
                    } catch(e) {}
                };
                testImg.src = basePathPrefix + 'assets/images/site/hero-bg.jpg';
            }

            const teamBg = document.querySelector('[data-mockup-fallback="team"]');
            if (teamBg) {
                const testTeam = new Image();
                testTeam.onerror = () => {
                    try {
                        const coords = cropCoordinates['team'];
                        const canvas = document.createElement('canvas');
                        const sx = coords.x * baseImg.naturalWidth;
                        const sy = coords.y * baseImg.naturalHeight;
                        const sw = coords.w * baseImg.naturalWidth;
                        const sh = coords.h * baseImg.naturalHeight;
                        canvas.width = sw;
                        canvas.height = sh;
                        canvas.getContext('2d').drawImage(baseImg, sx, sy, sw, sh, 0, 0, sw, sh);
                        teamBg.style.backgroundImage = `url(${canvas.toDataURL('image/jpeg', 0.92)})`;
                    } catch(e) {}
                };
                testTeam.src = basePathPrefix + 'assets/images/site/team-banner-bg.jpg';
            }
        }

        // Tenta também caso a imagem falhe ao carregar (troca para .svg ou canvas)
        cropElements.forEach(imgEl => {
            imgEl.addEventListener('error', () => {
                if (!imgEl.src.endsWith('.svg')) {
                    imgEl.src = imgEl.src.replace(/\.(jpg|jpeg|png)$/i, '.svg');
                } else if (isLoaded) {
                    applyCrops();
                }
            });
        });
    }

    setupCanvasCropFallback();
});

