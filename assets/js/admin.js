/**
 * ART FOR SALE - Funções Globais do Painel Administrativo
 * Gerenciamento de Tema Visual (Claro / Escuro) & Curadoria
 */

(function(window, document) {
    'use strict';

    // Trava para evitar disparos duplos simultâneos (inline onclick + addEventListener)
    let lastToggleTime = 0;
    let isToggling = false;

    /**
     * Aplica o tema visual no documento e na interface
     * @param {'light'|'dark'} theme
     */
    function applyTheme(theme) {
        const isDark = (theme === 'dark');
        const themeValue = isDark ? 'dark' : 'light';

        // 1. Aplica nos elementos raiz
        document.documentElement.setAttribute('data-theme', themeValue);
        document.documentElement.classList.toggle('dark-theme', isDark);
        document.documentElement.classList.toggle('light-theme', !isDark);

        if (document.body) {
            document.body.setAttribute('data-theme', themeValue);
            document.body.classList.toggle('dark-theme', isDark);
            document.body.classList.toggle('light-theme', !isDark);
        }

        // 2. Persiste no localStorage
        try {
            localStorage.setItem('artforsale_admin_theme', themeValue);
        } catch (e) {
            console.warn('Não foi possível salvar tema no localStorage:', e);
        }

        // 3. Atualiza os botões e interface visual
        window.updateThemeToggleUI(themeValue);

        // 4. Notifica ouvintes customizados caso necessário
        try {
            window.dispatchEvent(new CustomEvent('artforsale:themechange', {
                detail: { theme: themeValue, isDark: isDark }
            }));
        } catch (_) {}
    }

    /**
     * Alternador de Tema Visual (Light / Dark) com proteção contra duplo clique/duplo disparo
     */
    window.toggleAdminTheme = function(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }

        const now = Date.now();
        // Se chamado duas vezes em menos de 300ms (ex: inline onclick + addEventListener no mesmo clique), ignora o segundo disparo!
        if (isToggling || (now - lastToggleTime < 300)) {
            return;
        }
        isToggling = true;
        lastToggleTime = now;
        setTimeout(function() { isToggling = false; }, 300);

        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = (current === 'dark') ? 'light' : 'dark';

        applyTheme(next);
    };

    /**
     * Sincroniza ícones, textos e atributos acessíveis de todos os botões de alternância de tema
     * @param {'light'|'dark'} theme
     */
    window.updateThemeToggleUI = function(theme) {
        const isDark = (theme === 'dark');
        const iconChar = isDark ? '☀️' : '🌙';
        const labelText = isDark ? 'Claro' : 'Escuro';
        const titleText = isDark ? 'Alternar para tema claro' : 'Alternar para tema escuro';

        // 1. Atualiza botões por classe e seus elementos internos
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            btn.setAttribute('title', titleText);
            btn.setAttribute('aria-label', titleText);
            btn.setAttribute('data-current-theme', isDark ? 'dark' : 'light');

            const iconEl = btn.querySelector('#themeToggleIcon, .theme-toggle-icon');
            if (iconEl) {
                iconEl.textContent = iconChar;
            }
            const labelEl = btn.querySelector('#themeToggleLabel, .theme-toggle-label');
            if (labelEl) {
                labelEl.textContent = labelText;
            }
        });

        // 2. Fallback direto por ID caso existam fora de .theme-toggle-btn
        const directIcon = document.getElementById('themeToggleIcon');
        const directLabel = document.getElementById('themeToggleLabel');
        if (directIcon) directIcon.textContent = iconChar;
        if (directLabel) directLabel.textContent = labelText;

        // 3. Alterna logotipos claro/escuro
        document.querySelectorAll('.admin-logo-light').forEach(el => {
            el.style.display = isDark ? 'none' : 'block';
        });
        document.querySelectorAll('.admin-logo-dark').forEach(el => {
            el.style.display = isDark ? 'block' : 'none';
        });
    };

    /**
     * Inicializa o tema salvo e vincula os botões
     */
    function initTheme() {
        let savedTheme = 'light';
        try {
            savedTheme = localStorage.getItem('artforsale_admin_theme') || 'light';
        } catch (_) {}

        applyTheme(savedTheme);

        // Previne múltiplos listeners limpando onclick inline redundante e registrando listener único
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            // Remove o atributo onclick inline para evitar duplo disparo com o addEventListener
            if (btn.hasAttribute('onclick')) {
                btn.removeAttribute('onclick');
            }
            if (!btn.dataset.themeBound) {
                btn.dataset.themeBound = 'true';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.toggleAdminTheme(e);
                });
            }
        });
    }

    // Executa imediatamente e no DOMContentLoaded para máxima garantia
    initTheme();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    }

    // Delegação global de clique como camada extra de garantia
    document.addEventListener('click', function(e) {
        const btn = e.target.closest && e.target.closest('.theme-toggle-btn');
        if (btn) {
            e.preventDefault();
            window.toggleAdminTheme(e);
        }
    });

})(window, document);
