<?php
/**
 * ART FOR SALE - Rodapé de Alto Luxo (Editorial & Galeria de Arte)
 * Inspirado em museus, casas de leilão e galerias clássicas internacionais.
 * Preserva 100% de dados, links, modais e scripts existentes.
 */
?>
    <!-- Rodapé Principal de Alto Luxo - ART FOR SALE -->
    <footer class="site-footer" id="contato">
        <!-- Textura e Nuances Sutis de Mármore e Dourado -->
        <div class="footer-marble-texture" aria-hidden="true"></div>
        <div class="footer-gold-glow-left" aria-hidden="true"></div>

        <!-- Elemento Artístico Decorativo (Escultura Clássica com Kintsugi Dourado) -->
        <div class="footer-sculpture-wrap" aria-hidden="true">
            <picture>
                <source srcset="<?= $pathPrefix ?? '' ?>assets/images/site/footer-sculpture.webp" type="image/webp">
                <img src="<?= $pathPrefix ?? '' ?>assets/images/site/footer-sculpture.jpg" 
                     alt="" 
                     class="footer-sculpture-img" 
                     loading="lazy" 
                     width="640" 
                     height="858">
            </picture>
        </div>

        <div class="footer-container">
            <!-- 1. Coluna MARCA -->
            <div class="footer-col footer-col-brand">
                <a href="<?= $pathPrefix ?? '' ?>index.php" class="footer-brand-header" title="<?= SITE_NAME ?> - Início">
                    <div class="brand-monogram-box">
                        <svg viewBox="0 0 60 70" width="50" height="60" fill="none" class="brand-monogram-svg" aria-hidden="true">
                            <defs>
                                <linearGradient id="goldMonogramGrad" x1="15%" y1="0%" x2="85%" y2="100%">
                                    <stop offset="0%" stop-color="#dfc294" />
                                    <stop offset="35%" stop-color="#c69a55" />
                                    <stop offset="70%" stop-color="#9a7138" />
                                    <stop offset="100%" stop-color="#b98a45" />
                                </linearGradient>
                                <linearGradient id="goldMonogramLight" x1="0%" y1="100%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#a67c4f" />
                                    <stop offset="50%" stop-color="#faecc8" />
                                    <stop offset="100%" stop-color="#8e6538" />
                                </linearGradient>
                                <filter id="monogramDepth" x="-20%" y="-20%" width="140%" height="140%">
                                    <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#8a6332" flood-opacity="0.18" />
                                </filter>
                            </defs>
                            <g filter="url(#monogramDepth)">
                                <path d="M 28 4 L 7 60 L 16 60 L 32 16 Z" fill="url(#goldMonogramGrad)" />
                                <path d="M 27 4 L 52 60 L 43 60 L 25 16 Z" fill="url(#goldMonogramLight)" />
                                <path d="M 14 43 L 44 43 L 40 49 L 12 49 Z" fill="url(#goldMonogramGrad)" opacity="0.95" />
                                <polygon points="27.5,3 32,16 23,16" fill="#faecc8" />
                            </g>
                        </svg>
                    </div>
                    <div class="brand-identity-text">
                        <span class="brand-name">ART FOR SALE</span>
                        <span class="brand-tagline">ARTE QUE TRANSFORMA ESPAÇOS</span>
                    </div>
                </a>

                <div class="footer-gold-rule"></div>

                <p class="brand-mission-desc">
                    Conectando pessoas a obras que inspiram, valorizam e transformam ambientes.
                </p>

                <div class="footer-social-icons">
                    <!-- Instagram -->
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="social-icon-btn" title="Instagram" aria-label="Acompanhe a Art For Sale no Instagram">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </a>
                    <!-- WhatsApp -->
                    <a href="<?= $contatos['elisabeth']['whatsapp_link'] ?>" target="_blank" rel="noopener noreferrer" class="social-icon-btn" title="WhatsApp Sra. Elisabeth" aria-label="Fale conosco via WhatsApp">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </a>
                    <!-- Email -->
                    <a href="<?= $contatos['email']['link'] ?>" class="social-icon-btn" title="E-mail Curadoria" aria-label="Envie uma mensagem por e-mail">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- 2. Coluna NAVEGAÇÃO -->
            <div class="footer-col footer-col-nav">
                <h4 class="footer-heading">NAVEGAÇÃO</h4>
                <div class="footer-gold-rule"></div>
                <ul class="footer-link-list">
                    <li>
                        <a href="<?= $pathPrefix ?? '' ?>index.php">
                            <span class="nav-chevron" aria-hidden="true">›</span>
                            <span>Início</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $pathPrefix ?? '' ?>pages/obras.php">
                            <span class="nav-chevron" aria-hidden="true">›</span>
                            <span>Obras</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= ($currentPage ?? '') === 'inicio' ? '#categorias' : ($pathPrefix ?? '') . 'index.php#categorias' ?>">
                            <span class="nav-chevron" aria-hidden="true">›</span>
                            <span>Categorias</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= ($currentPage ?? '') === 'inicio' ? '#sobre' : ($pathPrefix ?? '') . 'index.php#sobre' ?>">
                            <span class="nav-chevron" aria-hidden="true">›</span>
                            <span>Sobre</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= ($currentPage ?? '') === 'inicio' ? '#contato' : ($pathPrefix ?? '') . 'index.php#contato' ?>">
                            <span class="nav-chevron" aria-hidden="true">›</span>
                            <span>Contato</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- 3. Coluna CATEGORIAS (2 subcolunas com ícones lineares dourados) -->
            <div class="footer-col footer-col-cats">
                <h4 class="footer-heading">CATEGORIAS</h4>
                <div class="footer-gold-rule"></div>
                <div class="footer-cats-columns">
                    <!-- Subcoluna 1 -->
                    <ul class="footer-link-list footer-cats-sublist">
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                                        <path d="m14 7 3 3"></path>
                                    </svg>
                                </span>
                                <span>Pinturas</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=arte-classica">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                </span>
                                <span>Arte Clássica</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=arte-moderna">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="9"></circle>
                                        <path d="M3.6 9h16.8M3.6 15h16.8"></path>
                                        <path d="M11.5 3a17 17 0 0 0 0 18M12.5 3a17 17 0 0 1 0 18"></path>
                                    </svg>
                                </span>
                                <span>Arte Moderna</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=arte-contemporanea">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                        <polyline points="2 17 12 22 22 17"></polyline>
                                        <polyline points="2 12 12 17 22 12"></polyline>
                                    </svg>
                                </span>
                                <span>Arte Contemporânea</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=paisagens">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path>
                                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path>
                                    </svg>
                                </span>
                                <span>Paisagens</span>
                            </a>
                        </li>
                    </ul>

                    <!-- Subcoluna 2 -->
                    <ul class="footer-link-list footer-cats-sublist">
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=retratos">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"></circle>
                                        <path d="M6 21v-2a6 6 0 0 1 12 0v2"></path>
                                    </svg>
                                </span>
                                <span>Retratos</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=abstrato">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                    </svg>
                                </span>
                                <span>Abstrato</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=gravuras">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                        <line x1="8" y1="16" x2="16" y2="8"></line>
                                        <polyline points="12 8 16 8 16 12"></polyline>
                                    </svg>
                                </span>
                                <span>Gravuras</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=esculturas">
                                <span class="cat-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="3" y1="21" x2="21" y2="21"></line>
                                        <line x1="4" y1="3" x2="20" y2="3"></line>
                                        <line x1="7" y1="7" x2="7" y2="17"></line>
                                        <line x1="12" y1="7" x2="12" y2="17"></line>
                                        <line x1="17" y1="7" x2="17" y2="17"></line>
                                        <rect x="5" y="3" width="14" height="4" rx="1"></rect>
                                        <rect x="5" y="17" width="14" height="4" rx="1"></rect>
                                    </svg>
                                </span>
                                <span>Esculturas</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 4. Coluna ATENDIMENTO -->
            <div class="footer-col footer-col-contact">
                <h4 class="footer-heading">ATENDIMENTO</h4>
                <div class="footer-gold-rule"></div>
                <div class="footer-contact-items">
                    <!-- Sra. Elisabeth -->
                    <div class="footer-contact-row">
                        <a href="<?= $contatos['elisabeth']['whatsapp_link'] ?>" target="_blank" rel="noopener noreferrer" class="contact-circ-btn" title="Falar com Sra. Elisabeth" aria-label="WhatsApp Sra. Elisabeth">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                            </svg>
                        </a>
                        <div class="contact-row-info">
                            <span class="contact-name"><?= $contatos['elisabeth']['nome'] ?></span>
                            <a href="tel:5191140044" class="contact-value"><?= $contatos['elisabeth']['telefone'] ?></a>
                        </div>
                    </div>

                    <!-- Felipe C -->
                    <div class="footer-contact-row">
                        <a href="<?= $contatos['felipe']['whatsapp_link'] ?>" target="_blank" rel="noopener noreferrer" class="contact-circ-btn" title="Falar com Felipe C" aria-label="WhatsApp Felipe C">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </a>
                        <div class="contact-row-info">
                            <span class="contact-name"><?= $contatos['felipe']['nome'] ?></span>
                            <a href="tel:51991266414" class="contact-value"><?= $contatos['felipe']['telefone'] ?></a>
                        </div>
                    </div>

                    <!-- E-mail -->
                    <div class="footer-contact-row">
                        <a href="<?= $contatos['email']['link'] ?>" class="contact-circ-btn" title="Enviar E-mail" aria-label="E-mail de Atendimento">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                            </svg>
                        </a>
                        <div class="contact-row-info">
                            <a href="<?= $contatos['email']['link'] ?>" class="contact-value email-link"><?= $contatos['email']['endereco'] ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra Inferior de Direitos e Links Legais -->
        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <div class="footer-bottom-left">
                    <p class="copyright-text">
                        &copy; <?= SITE_YEAR ?> <?= SITE_NAME ?>. Todos os direitos reservados.
                    </p>
                </div>

                <div class="footer-bottom-center">
                    <span class="ornament-line"></span>
                    <svg class="ornament-svg" viewBox="0 0 60 20" width="38" height="13" fill="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="ornamentGold" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#d0a65a"/>
                                <stop offset="50%" stop-color="#b98a45"/>
                                <stop offset="100%" stop-color="#c69a55"/>
                            </linearGradient>
                        </defs>
                        <path d="M30 2 C26 7 18 5 16 9 C14 13 20 16 23 12 C26 9 28 10 30 10 C32 10 34 9 37 12 C40 16 46 13 44 9 C42 5 34 7 30 2 Z" fill="url(#ornamentGold)"/>
                        <circle cx="30" cy="10" r="1.8" fill="#c69a55"/>
                    </svg>
                    <span class="ornament-line"></span>
                </div>

                <div class="footer-bottom-right">
                    <div class="footer-legal-links">
                        <a href="#privacidade" class="legal-link">Política de Privacidade</a>
                        <span class="divider">|</span>
                        <a href="#termos" class="legal-link">Termos de Uso</a>
                        <span class="divider">|</span>
                        <a href="<?= $pathPrefix ?? '' ?>admin/login.php" class="legal-link" title="Painel da Curadoria (Acesso Restrito)">Site por Art For Sale</a>
                    </div>
                    <button type="button" class="back-to-top" id="btnBackToTop" aria-label="Voltar ao topo da página">
                        <span class="arrow-up" aria-hidden="true">⌃</span>
                        <span>Voltar ao topo</span>
                    </button>
                </div>
            </div>
        </div>
    </footer>

    <?php if (empty($hasInquiryModal) && empty($obra)): ?>
    <!-- Modal Global de Atendimento / Consultar Obra -->
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
                    <a href="<?= $contatos['elisabeth']['whatsapp_link'] ?? '#' ?>" id="btnModalWhatsElisabeth" target="_blank" rel="noopener noreferrer" class="inquiry-channel-btn whatsapp">
                        <div class="channel-info">
                            <strong>WhatsApp Sra. Elisabeth</strong>
                            <span>Curadoria & Atendimento</span>
                        </div>
                        <span class="channel-arrow">→</span>
                    </a>
                    <a href="<?= $contatos['felipe']['whatsapp_link'] ?? '#' ?>" id="btnModalWhatsFelipe" target="_blank" rel="noopener noreferrer" class="inquiry-channel-btn whatsapp">
                        <div class="channel-info">
                            <strong>WhatsApp Felipe C</strong>
                            <span>Atendimento & Vendas</span>
                        </div>
                        <span class="channel-arrow">→</span>
                    </a>
                    <a href="<?= $contatos['email']['link'] ?? '#' ?>" id="btnModalEmail" class="inquiry-channel-btn email">
                        <div class="channel-info">
                            <strong>Enviar E-mail Formal</strong>
                            <span><?= $contatos['email']['endereco'] ?? 'artforsale1944@gmail.com' ?></span>
                        </div>
                        <span class="channel-arrow">→</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts JavaScript Vanilla Obrigatórios -->
    <script src="<?= $pathPrefix ?? '' ?>assets/js/supabase-config.js"></script>
    <script src="<?= $pathPrefix ?? '' ?>assets/js/supabase-client.js"></script>
    <script src="<?= $pathPrefix ?? '' ?>assets/js/main.js"></script>
    <script src="<?= $pathPrefix ?? '' ?>assets/js/gallery.js"></script>
</body>
</html>
