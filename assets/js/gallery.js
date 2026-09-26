/**
 * ART FOR SALE - JavaScript de Galeria e Carrossel (gallery.js)
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
       2. CONTROLES DE SLIDE DO HERO (SLIDER REAL COM 3 SLIDES & ADMIN)
       ========================================================== */
    /**
     * CONFIGURAÇÃO DOS 3 SLIDES DO HERO:
     * Carrega dinamicamente a configuração definida no Painel Administrativo (/admin/hero-slides.php).
     * Fallback gracioso com imagens e textos padrão da curadoria Art For Sale.
     */
    let heroSlides = (window.HERO_SLIDES_CONFIG && Array.isArray(window.HERO_SLIDES_CONFIG) && window.HERO_SLIDES_CONFIG.length >= 3)
        ? window.HERO_SLIDES_CONFIG
        : [
            {
                // Slide 1
                image: 'assets/images/site/hero-bg.jpg',
                eyebrow: 'GALERIA DE ARTE',
                title: 'Arte que<br>transforma<br>espaços.',
                description: 'Descubra obras únicas e cuidadosamente selecionadas para colecionadores, apreciadores e ambientes que merecem personalidade.',
                quote: 'Mais que quadros, histórias que ganham vida no seu espaço.'
            },
            {
                // Slide 2
                image: 'assets/images/site/about-art-gallery.jpg',
                eyebrow: 'CURADORIA EXCLUSIVA',
                title: 'Coleções<br>únicas com<br>personalidade.',
                description: 'Pinturas a óleo, gravuras históricas e esculturas nobres selecionadas para elevar o design de interiores a outro patamar.',
                quote: 'A beleza clássica e contemporânea em perfeita harmonia.'
            },
            {
                // Slide 3
                image: 'assets/images/site/about-art-sell.jpg',
                eyebrow: 'ACERVO PRIVADO',
                title: 'Obras de arte<br>que contam<br>histórias.',
                description: 'Atendimento e consultoria especializada para encontrar a peça perfeita para sua residência, escritório ou coleção particular.',
                quote: 'Cada pincelada carrega uma emoção eterna e autêntica.'
            }
        ];

    const heroSection = document.getElementById('hero');
    const heroPrevBtn = document.getElementById('heroPrevBtn');
    const heroNextBtn = document.getElementById('heroNextBtn');
    const heroPageNumbers = document.querySelectorAll('.hero-pagination .page-number');
    const heroBgSlides = document.querySelectorAll('.hero-background .hero-bg-slide');
    const heroEyebrowEl = document.querySelector('.hero-content .hero-eyebrow');
    const heroTitleEl = document.querySelector('.hero-content .hero-title');
    const heroDescEl = document.querySelector('.hero-content .hero-description');
    const heroQuoteEl = document.querySelector('.hero-quote-box .quote-text');
    const heroCtaPrimaryEl = document.getElementById('heroCtaPrimary');
    const heroCtaSecondaryEl = document.getElementById('heroCtaSecondary');
    const heroTextElements = document.querySelectorAll('.hero-text-fade');

    let currentHeroIndex = 0;
    let isHeroTransitioning = false;
    let heroAutoplayTimer = null;
    const HERO_AUTOPLAY_DELAY = 6000; // Autoplay a cada 6 segundos

    function resolveHeroImgUrl(imgPath) {
        if (!imgPath) return '';
        if (imgPath.startsWith('http://') || imgPath.startsWith('https://') || imgPath.startsWith('data:') || imgPath.startsWith('//')) {
            return imgPath;
        }
        const prefix = window.location.pathname.includes('/pages/') ? '../' : '';
        return prefix + imgPath.replace(/^(\.\.\/|\.\/|\/)+/, '');
    }

    function syncHeroBgLayers() {
        if (heroBgSlides.length > 0) {
            heroBgSlides.forEach((slideEl, idx) => {
                if (heroSlides[idx] && heroSlides[idx].image) {
                    const resolved = resolveHeroImgUrl(heroSlides[idx].image);
                    slideEl.style.backgroundImage = `url('${resolved}')`;
                }
            });
        }
    }

    // Sincroniza dinamicamente as URLs das imagens definidas com as camadas do DOM
    syncHeroBgLayers();

    // Fallback assíncrono para ambientes estáticos (ex: Vercel estático / index.html)
    if (!window.HERO_SLIDES_CONFIG) {
        const fetchPrefix = window.location.pathname.includes('/pages/') ? '../' : '';
        fetch(fetchPrefix + 'api/hero-slides.php')
            .then(res => res.ok ? res.json() : fetch(fetchPrefix + 'database/hero_slides.json').then(r => r.ok ? r.json() : null))
            .then(data => {
                if (Array.isArray(data) && data.length >= 3) {
                    heroSlides = data;
                    window.HERO_SLIDES_CONFIG = data;
                    syncHeroBgLayers();
                    // Atualiza textos do slide atual se estiver no primeiro
                    if (currentHeroIndex === 0 && heroSlides[0]) {
                        if (heroEyebrowEl && heroSlides[0].eyebrow) heroEyebrowEl.innerHTML = heroSlides[0].eyebrow;
                        if (heroTitleEl && heroSlides[0].title) heroTitleEl.innerHTML = heroSlides[0].title;
                        if (heroDescEl && heroSlides[0].description) heroDescEl.innerHTML = heroSlides[0].description;
                        if (heroQuoteEl && heroSlides[0].quote) heroQuoteEl.innerHTML = heroSlides[0].quote;
                    }
                }
            })
            .catch(() => {});
    }

    /**
     * Transiciona suavemente para o slide indicado por targetIndex (0, 1 ou 2)
     */
    function goToHeroSlide(targetIndex) {
        // Envolve circularmente: 0 -> 2, 2 -> 0
        if (targetIndex < 0) {
            targetIndex = heroSlides.length - 1;
        } else if (targetIndex >= heroSlides.length) {
            targetIndex = 0;
        }

        // Se já está no slide solicitado e não está em animação
        if (targetIndex === currentHeroIndex && heroBgSlides.length > 0 && heroBgSlides[targetIndex].classList.contains('active')) {
            return;
        }

        if (isHeroTransitioning) return;
        isHeroTransitioning = true;

        currentHeroIndex = targetIndex;
        const currentData = heroSlides[currentHeroIndex];

        // 1. Atualização imediata dos indicadores numéricos (01, 02, 03)
        heroPageNumbers.forEach((el, idx) => {
            if (idx === currentHeroIndex) {
                el.classList.add('active');
                el.setAttribute('aria-current', 'true');
            } else {
                el.classList.remove('active');
                el.removeAttribute('aria-current');
            }
        });

        // 2. Transição suave de fade e zoom cinemático nas camadas de fundo
        if (heroBgSlides.length > 0) {
            heroBgSlides.forEach((slideEl, idx) => {
                if (idx === currentHeroIndex) {
                    slideEl.classList.add('active');
                } else {
                    slideEl.classList.remove('active');
                }
            });
        }

        // 3. Transição suave dos textos (fade out -> substituição -> fade in)
        heroTextElements.forEach(el => el.classList.add('is-transitioning'));

        setTimeout(() => {
            if (currentData) {
                if (heroEyebrowEl && currentData.eyebrow) {
                    heroEyebrowEl.innerHTML = currentData.eyebrow;
                }
                if (heroTitleEl && currentData.title) {
                    heroTitleEl.innerHTML = currentData.title;
                }
                if (heroDescEl && currentData.description) {
                    heroDescEl.innerHTML = currentData.description;
                }
                if (heroQuoteEl && currentData.quote) {
                    heroQuoteEl.innerHTML = currentData.quote;
                }
                if (heroCtaPrimaryEl && currentData.cta_primary_text) {
                    heroCtaPrimaryEl.innerHTML = currentData.cta_primary_text + ' <span class="arrow">→</span>';
                    if (currentData.cta_primary_url) heroCtaPrimaryEl.href = currentData.cta_primary_url;
                }
                if (heroCtaSecondaryEl && currentData.cta_secondary_text) {
                    heroCtaSecondaryEl.textContent = currentData.cta_secondary_text;
                    if (currentData.cta_secondary_url) heroCtaSecondaryEl.href = currentData.cta_secondary_url;
                }
            }
            heroTextElements.forEach(el => el.classList.remove('is-transitioning'));
        }, 280);

        // Libera nova transição após 700ms
        setTimeout(() => {
            isHeroTransitioning = false;
        }, 700);
    }

    function startHeroAutoplay() {
        stopHeroAutoplay();
        heroAutoplayTimer = setInterval(() => {
            goToHeroSlide(currentHeroIndex + 1);
        }, HERO_AUTOPLAY_DELAY);
    }

    function stopHeroAutoplay() {
        if (heroAutoplayTimer) {
            clearInterval(heroAutoplayTimer);
            heroAutoplayTimer = null;
        }
    }

    function restartHeroAutoplay() {
        startHeroAutoplay();
    }

    // Setas de Navegação (← e →)
    if (heroPrevBtn) {
        heroPrevBtn.addEventListener('click', (e) => {
            e.preventDefault();
            goToHeroSlide(currentHeroIndex - 1);
            restartHeroAutoplay();
        });
    }

    if (heroNextBtn) {
        heroNextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            goToHeroSlide(currentHeroIndex + 1);
            restartHeroAutoplay();
        });
    }

    // Indicadores 01, 02, 03 Clicáveis
    heroPageNumbers.forEach((pageEl, idx) => {
        pageEl.addEventListener('click', () => {
            goToHeroSlide(idx);
            restartHeroAutoplay();
        });

        pageEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                goToHeroSlide(idx);
                restartHeroAutoplay();
            }
        });
    });

    // Suporte a swipe no mobile
    if (heroSection) {
        let touchStartX = 0;
        let touchEndX = 0;

        heroSection.addEventListener('touchstart', (e) => {
            if (e.changedTouches && e.changedTouches.length > 0) {
                touchStartX = e.changedTouches[0].screenX;
            }
        }, { passive: true });

        heroSection.addEventListener('touchend', (e) => {
            if (e.changedTouches && e.changedTouches.length > 0) {
                touchEndX = e.changedTouches[0].screenX;
                const diffX = touchStartX - touchEndX;
                if (Math.abs(diffX) > 45) {
                    if (diffX > 0) {
                        // Swipe para a esquerda -> próximo slide
                        goToHeroSlide(currentHeroIndex + 1);
                    } else {
                        // Swipe para a direita -> slide anterior
                        goToHeroSlide(currentHeroIndex - 1);
                    }
                    restartHeroAutoplay();
                }
            }
        }, { passive: true });
    }

    // Pausar autoplay quando aba não estiver ativa
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopHeroAutoplay();
        } else {
            startHeroAutoplay();
        }
    });

    // Inicia autoplay automático
    startHeroAutoplay();

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

                // Testa se a imagem já carregou normalmente como imagem real (não SVG)
                if (imgEl.complete && imgEl.naturalWidth > 50 && !imgEl.src.toLowerCase().includes('.svg')) {
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
                        const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.92);
                        heroBg.style.backgroundImage = `url(${croppedDataUrl})`;
                        const firstBg = heroBg.querySelector('.hero-bg-slide');
                        if (firstBg) firstBg.style.backgroundImage = `url(${croppedDataUrl})`;
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

        // Caso ocorra qualquer erro de carregamento, aplica o recorte diretamente do mockup original (nunca troca para SVG)
        cropElements.forEach(imgEl => {
            imgEl.addEventListener('error', () => {
                if (isLoaded) {
                    applyCrops();
                }
            });
        });
    }

    setupCanvasCropFallback();
});

