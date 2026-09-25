/**
 * ART FOR SALE - Funções Globais do Painel Administrativo
 * Gerenciamento de Tema Visual (Claro / Escuro) & Curadoria
 */

(function(window, document) {
    'use strict';

    // Alternador de Tema Visual (Light / Dark)
    window.toggleAdminTheme = function() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = (current === 'dark') ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', next);
        if (document.body) {
            document.body.setAttribute('data-theme', next);
        }

        try {
            localStorage.setItem('artforsale_admin_theme', next);
        } catch (e) {
            console.warn('Não foi possível salvar tema no localStorage:', e);
        }

        updateThemeToggleUI(next);
    };

    window.updateThemeToggleUI = function(theme) {
        const isDark = (theme === 'dark');

        // Atualiza elementos por ID
        const icon = document.getElementById('themeToggleIcon');
        const label = document.getElementById('themeToggleLabel');
        if (icon) icon.textContent = isDark ? '☀️' : '🌙';
        if (label) label.textContent = isDark ? 'Claro' : 'Escuro';

        // Atualiza elementos por classe (garante atualização em múltiplos botões)
        document.querySelectorAll('.theme-toggle-icon').forEach(el => {
            el.textContent = isDark ? '☀️' : '🌙';
        });
        document.querySelectorAll('.theme-toggle-label').forEach(el => {
            el.textContent = isDark ? 'Claro' : 'Escuro';
        });

        // Alterna logos da topbar se houver classes específicas
        document.querySelectorAll('.admin-logo-light').forEach(el => {
            el.style.display = isDark ? 'none' : 'block';
        });
        document.querySelectorAll('.admin-logo-dark').forEach(el => {
            el.style.display = isDark ? 'block' : 'none';
        });
    };

    function initTheme() {
        let savedTheme = 'light';
        try {
            savedTheme = localStorage.getItem('artforsale_admin_theme') || 'light';
        } catch (_) {}

        document.documentElement.setAttribute('data-theme', savedTheme);
        if (document.body) {
            document.body.setAttribute('data-theme', savedTheme);
        }
        updateThemeToggleUI(savedTheme);

        // Adiciona listener resiliente a todos os botões de alternância
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            if (!btn.dataset.themeBound) {
                btn.dataset.themeBound = 'true';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.toggleAdminTheme();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }

})(window, document);
