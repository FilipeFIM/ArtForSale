<?php
/**
 * ART SELL - Footer
 * Fiel ao mockup de referência.
 * Sem newsletter, sem cadastro de email, sem carrinho/checkout/login.
 */
?>
    <!-- Rodapé Principal -->
    <footer class="site-footer">
        <div class="footer-container">
            <!-- Coluna 1: Identidade e Redes Sociais -->
            <div class="footer-col footer-col-brand">
                <a href="<?= $pathPrefix ?? '' ?>index.php" class="footer-logo">
                    <img src="<?= $pathPrefix ?? '' ?>assets/images/site/logo.svg" alt="<?= SITE_NAME ?>" width="210" height="46">
                </a>
                <p class="footer-slogan"><?= SITE_SLOGAN ?></p>
                <div class="footer-social-icons">
                    <!-- Instagram -->
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="social-icon-btn" title="Instagram" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </a>
                    <!-- WhatsApp -->
                    <a href="<?= $contatos['elisabeth']['whatsapp_link'] ?>" target="_blank" rel="noopener noreferrer" class="social-icon-btn" title="WhatsApp" aria-label="WhatsApp">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </a>
                    <!-- Email -->
                    <a href="<?= $contatos['email']['link'] ?>" class="social-icon-btn" title="E-mail" aria-label="E-mail">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Coluna 2: Navegação -->
            <div class="footer-col footer-col-nav">
                <h4 class="footer-heading">Navegação</h4>
                <ul class="footer-link-list">
                    <li><a href="<?= $pathPrefix ?? '' ?>index.php">Início</a></li>
                    <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php">Obras</a></li>
                    <li><a href="<?= ($currentPage ?? '') === 'inicio' ? '#categorias' : ($pathPrefix ?? '') . 'index.php#categorias' ?>">Categorias</a></li>
                    <li><a href="<?= ($currentPage ?? '') === 'inicio' ? '#sobre' : ($pathPrefix ?? '') . 'index.php#sobre' ?>">Sobre</a></li>
                    <li><a href="<?= ($currentPage ?? '') === 'inicio' ? '#contato' : ($pathPrefix ?? '') . 'index.php#contato' ?>">Contato</a></li>
                </ul>
            </div>

            <!-- Coluna 3: Categorias (subdivididas como no mockup) -->
            <div class="footer-col footer-col-cats">
                <h4 class="footer-heading">Categorias</h4>
                <div class="footer-cats-columns">
                    <ul class="footer-link-list">
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php">Pinturas</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=arte-classica">Arte Clássica</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=arte-moderna">Arte Moderna</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=arte-contemporanea">Arte Contemporânea</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=paisagens">Paisagens</a></li>
                    </ul>
                    <ul class="footer-link-list">
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=retratos">Retratos</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=abstrato">Abstrato</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=gravuras">Gravuras</a></li>
                        <li><a href="<?= $pathPrefix ?? '' ?>pages/obras.php?categoria=esculturas">Esculturas</a></li>
                    </ul>
                </div>
            </div>

            <!-- Coluna 4: Atendimento -->
            <div class="footer-col footer-col-contact">
                <h4 class="footer-heading">Atendimento</h4>
                <div class="footer-contact-items">
                    <!-- Elisabeth -->
                    <div class="footer-contact-row">
                        <div class="contact-row-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="contact-row-info">
                            <span class="contact-name"><?= $contatos['elisabeth']['nome'] ?></span>
                            <a href="tel:5191140044" class="contact-value"><?= $contatos['elisabeth']['telefone'] ?></a>
                        </div>
                    </div>

                    <!-- Felipe -->
                    <div class="footer-contact-row">
                        <div class="contact-row-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="contact-row-info">
                            <span class="contact-name"><?= $contatos['felipe']['nome'] ?></span>
                            <a href="tel:51991266414" class="contact-value"><?= $contatos['felipe']['telefone'] ?></a>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="footer-contact-row">
                        <div class="contact-row-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <div class="contact-row-info">
                            <a href="<?= $contatos['email']['link'] ?>" class="contact-value"><?= $contatos['email']['endereco'] ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra Inferior de Direitos e Links Legais -->
        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <p class="copyright-text">
                    &copy; <?= SITE_YEAR ?> <?= SITE_NAME ?>. Todos os direitos reservados.
                </p>
                <div class="footer-legal-links">
                    <a href="#privacidade" class="legal-link">Política de Privacidade</a>
                    <span class="divider">|</span>
                    <a href="#termos" class="legal-link">Termos de Uso</a>
                    <span class="divider">|</span>
                    <a href="#mapa" class="legal-link">Mapa do Site</a>
                </div>
                <button type="button" class="back-to-top" id="btnBackToTop" aria-label="Voltar ao topo">
                    <span class="arrow-up">⌃</span> Voltar ao topo
                </button>
            </div>
        </div>
    </footer>

    <!-- Scripts JavaScript Vanilla Obrigatórios -->
    <script src="<?= $pathPrefix ?? '' ?>assets/js/main.js"></script>
    <script src="<?= $pathPrefix ?? '' ?>assets/js/gallery.js"></script>
</body>
</html>

