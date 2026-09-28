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

    /**
     * ==============================================================================
     * OTIMIZADOR DE IMAGENS & PROTEÇÃO CONTRA ERRO 413 (FUNCTION_PAYLOAD_TOO_LARGE)
     * ==============================================================================
     * A Vercel impõe um limite estrito de 4.5 MB no corpo da requisição HTTP.
     * Esta rotina:
     * 1. Redimensiona fotos pesadas de celulares/câmeras (até 1800px no maior lado).
     * 2. Comprime via Canvas HTML5 para JPEG de alta fidelidade (~300KB a ~700KB).
     * 3. Substitui o arquivo no FileList via DataTransfer e sincroniza campos Base64.
     * 4. Valida previamente o peso total no evento submit impedindo erro 413.
     */

    /**
     * Comprime e redimensiona um arquivo de imagem client-side
     * @param {File|Blob} file
     * @param {Object} [options]
     * @returns {Promise<{file: File, blob: Blob, dataUrl: string, origSize: number, newSize: number, width: number, height: number, isSvg: boolean}>}
     */
    window.artSaleCompressImage = function(file, options) {
        options = options || {};
        const maxDim = options.maxDimension || 1800;
        const quality = options.quality || 0.85;

        return new Promise(function(resolve, reject) {
            if (!file) {
                reject(new Error('Nenhum arquivo fornecido.'));
                return;
            }

            // Preserva arquivos SVG intactos
            if (file.type === 'image/svg+xml' || (file.name && file.name.toLowerCase().endsWith('.svg'))) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    resolve({
                        file: file,
                        blob: file,
                        dataUrl: e.target.result,
                        origSize: file.size,
                        newSize: file.size,
                        width: 0,
                        height: 0,
                        isSvg: true
                    });
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
                return;
            }

            // Não comprime se já for pequeno (< 450 KB) e não for imagem gigantesca em pixels
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);

            img.onload = function() {
                URL.revokeObjectURL(objectUrl);

                let width = img.naturalWidth || img.width;
                let height = img.naturalHeight || img.height;

                // Se a imagem estiver dentro das dimensões e peso ideal (< 600 KB), mantemos
                const needsResize = (width > maxDim || height > maxDim);
                const needsCompression = (file.size > 800 * 1024);

                if (!needsResize && !needsCompression && file.type === 'image/jpeg') {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        resolve({
                            file: file,
                            blob: file,
                            dataUrl: e.target.result,
                            origSize: file.size,
                            newSize: file.size,
                            width: width,
                            height: height,
                            isSvg: false
                        });
                    };
                    reader.readAsDataURL(file);
                    return;
                }

                // Calcula dimensões proporcionais
                if (width > maxDim || height > maxDim) {
                    if (width > height) {
                        height = Math.round((height * maxDim) / width);
                        width = maxDim;
                    } else {
                        width = Math.round((width * maxDim) / height);
                        height = maxDim;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    reject(new Error('Contexto 2D do Canvas indisponível'));
                    return;
                }

                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, width, height);

                // Determina qualidade adaptativa (fotos acima de 5MB usam 0.82 para garantir peso < 700KB)
                const finalQuality = (file.size > 5 * 1024 * 1024) ? Math.min(quality, 0.82) : quality;

                canvas.toBlob(function(blob) {
                    if (!blob) {
                        reject(new Error('Falha ao gerar blob comprimido no Canvas'));
                        return;
                    }

                    let cleanName = (file.name || 'imagem.jpg').replace(/\.[^.]+$/, '') + '.jpg';
                    let compressedFile;
                    try {
                        compressedFile = new File([blob], cleanName, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                    } catch (_) {
                        compressedFile = blob;
                        compressedFile.name = cleanName;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        resolve({
                            file: compressedFile,
                            blob: blob,
                            dataUrl: e.target.result,
                            origSize: file.size,
                            newSize: blob.size,
                            width: width,
                            height: height,
                            isSvg: false
                        });
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);

                }, 'image/jpeg', finalQuality);
            };

            img.onerror = function() {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('Não foi possível carregar a imagem selecionada.'));
            };

            img.src = objectUrl;
        });
    };

    /**
     * Acopla o otimizador inteligente a um elemento de input de arquivo
     * @param {HTMLInputElement} inputEl
     * @param {Object} [customOpts]
     */
    window.artSaleAttachOptimizer = function(inputEl, customOpts) {
        if (!inputEl || inputEl.dataset.optimizerBound === 'true') return;
        inputEl.dataset.optimizerBound = 'true';

        const opts = customOpts || {};
        const formEl = inputEl.closest('form');

        inputEl.addEventListener('change', async function() {
            if (!inputEl.files || !inputEl.files[0]) return;
            const origFile = inputEl.files[0];

            // Localiza elementos auxiliares relacionados
            const submitBtns = formEl ? formEl.querySelectorAll('button[type="submit"], input[type="submit"]') : [];

            let feedbackEl = opts.statusEl || 
                             formEl?.querySelector('#fileNameTxt') || 
                             formEl?.querySelector('#imagePreviewName') || 
                             formEl?.querySelector('#editImagePreviewName') ||
                             formEl?.querySelector('#previewFileName') ||
                             formEl?.querySelector('[id^="file-name-"]');

            let previewEl = opts.previewImg ||
                            formEl?.querySelector('#previewImg') ||
                            formEl?.querySelector('#imagePreviewThumb') ||
                            formEl?.querySelector('#editImagePreviewImg') ||
                            formEl?.querySelector('.current-image-preview-box img');

            let b64DataEl = opts.b64Data || formEl?.querySelector('input[name*="base64"], #base64_image_data, #primary_image_base64, #artwork_file_base64');
            let b64NameEl = opts.b64Name || formEl?.querySelector('input[name*="base64_image_name"], #base64_image_name, #primary_image_filename, #artwork_file_filename');

            // Feedback imediato e bloqueio temporário dos botões durante a compressão
            submitBtns.forEach(btn => {
                btn.disabled = true;
                if (!btn.dataset.originalHtml) {
                    btn.dataset.originalHtml = btn.innerHTML;
                }
                btn.innerHTML = '⏳ Otimizando fotografia...';
            });

            if (feedbackEl) {
                feedbackEl.style.display = 'block';
                feedbackEl.style.color = 'var(--color-gold, #b38a54)';
                feedbackEl.textContent = `Otimizando "${origFile.name}" (${(origFile.size / (1024 * 1024)).toFixed(1)} MB)...`;
            }

            try {
                const res = await window.artSaleCompressImage(origFile, opts);

                // 1. Atualiza o input file com o arquivo otimizado
                try {
                    const dt = new DataTransfer();
                    dt.items.add(res.file);
                    inputEl.files = dt.files;
                } catch (dtErr) {
                    console.warn('DataTransfer não suportado pelo navegador, utilizando envio Base64:', dtErr);
                }

                // 2. Atualiza prévia visual
                if (previewEl && res.dataUrl) {
                    previewEl.src = res.dataUrl;
                    const stage = formEl?.querySelector('#previewStage') || 
                                  formEl?.querySelector('#editImagePreviewBox') || 
                                  formEl?.querySelector('#previewContainer') ||
                                  formEl?.querySelector('#fileUploadPreview');
                    if (stage) stage.style.display = 'block';
                }

                // 3. Atualiza campos Base64 leves caso existam
                if (b64DataEl) {
                    b64DataEl.value = res.dataUrl;
                }
                if (b64NameEl) {
                    b64NameEl.value = res.file.name || origFile.name;
                }

                // 4. Mensagem de sucesso
                if (feedbackEl) {
                    feedbackEl.style.display = 'block';
                    feedbackEl.style.color = '#2e7d32'; // verde elegante
                    const origKb = (res.origSize / 1024).toFixed(0);
                    const newKb = (res.newSize / 1024).toFixed(0);
                    if (res.origSize > res.newSize) {
                        feedbackEl.textContent = `✓ Imagem otimizada: ${res.file.name} (${origKb} KB → ${newKb} KB - Pronta para salvar!)`;
                    } else {
                        feedbackEl.textContent = `✓ Imagem pronta: ${res.file.name} (${newKb} KB)`;
                    }
                }
            } catch (err) {
                console.error('Falha na compressão da imagem:', err);
                if (feedbackEl) {
                    feedbackEl.style.color = '#c93b3b';
                    feedbackEl.textContent = `Foto selecionada: ${origFile.name} (${(origFile.size / 1024).toFixed(0)} KB)`;
                }
            } finally {
                // Restaura botões
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    if (btn.dataset.originalHtml) {
                        btn.innerHTML = btn.dataset.originalHtml;
                    }
                });
            }
        });
    };

    /**
     * Inicializa observação e proteção contra 413 em todos os formulários administrativos
     */
    function initImageOptimizers() {
        // Vincula a todos os inputs de arquivo de imagem do admin
        document.querySelectorAll('input[type="file"]').forEach(input => {
            const accept = (input.getAttribute('accept') || '').toLowerCase();
            const name = (input.getAttribute('name') || '').toLowerCase();
            const id = (input.getAttribute('id') || '').toLowerCase();

            if (accept.includes('image') || name.includes('image') || name.includes('foto') || id.includes('image') || id.includes('slide_file') || id.includes('artwork_file')) {
                window.artSaleAttachOptimizer(input);
            }
        });

        // Intercepta todos os formulários para validação pre-flight contra erro 413
        document.querySelectorAll('form').forEach(form => {
            if (form.dataset.preflightBound === 'true') return;
            form.dataset.preflightBound = 'true';

            form.addEventListener('submit', function(e) {
                let totalPayload = 0;

                // 1. Soma tamanho dos arquivos no FileList
                form.querySelectorAll('input[type="file"]').forEach(f => {
                    if (f.files && f.files.length) {
                        for (let i = 0; i < f.files.length; i++) {
                            totalPayload += f.files[i].size;
                        }
                    }
                });

                // 2. Se o arquivo estiver presente e otimizado no input[type=file], 
                // e o formulário tiver input hidden base64 preenchido, limpamos o base64 para NÃO duplicar o envio!
                // Isso economiza metade do payload da requisição!
                const fileInputsWithFiles = form.querySelectorAll('input[type="file"]');
                let hasRealFiles = false;
                fileInputsWithFiles.forEach(fi => {
                    if (fi.files && fi.files.length > 0 && fi.files[0].size > 0) {
                        hasRealFiles = true;
                    }
                });

                if (hasRealFiles) {
                    const b64Inputs = form.querySelectorAll('input[type="hidden"][name*="base64"]');
                    b64Inputs.forEach(b => {
                        // Se temos o arquivo no input file, podemos limpar o base64 duplicado antes do submit
                        // mantendo o payload ultraleve (< 600 KB total!)
                        b.value = '';
                    });
                } else {
                    // Se não tiver no input file, soma o tamanho do base64
                    form.querySelectorAll('input[type="hidden"][name*="base64"]').forEach(b => {
                        if (b.value) totalPayload += b.value.length;
                    });
                }

                // Limite de segurança: 4.0 MB (a Vercel derruba com 413 acima de 4.5 MB)
                const MAX_LIMIT = 4.0 * 1024 * 1024;
                if (totalPayload > MAX_LIMIT) {
                    e.preventDefault();
                    const mb = (totalPayload / (1024 * 1024)).toFixed(1);
                    alert(`⚠️ Atenção: Os arquivos selecionados somam ${mb} MB e ultrapassam o limite máximo de envio de 4,0 MB do servidor.\n\nPor favor, escolha uma imagem com menor tamanho ou resolução antes de salvar.`);
                    return false;
                }
            });
        });
    }

    /**
     * ==============================================================================
     * HEARTBEAT CSRF & PRESERVAÇÃO DE SESSÃO ADMINISTRATIVA (30 DIAS)
     * ==============================================================================
     * - Garante que o curador pode manter a página aberta por horas ou dias editando.
     * - Sincroniza em background o token CSRF a cada 5 minutos e quando a aba ganha foco.
     * - Atualiza silenciosamente todos os campos hidden csrf_token do documento.
     */
    function initCsrfHeartbeat() {
        let lastSyncTime = Date.now();

        async function syncCsrfToken() {
            try {
                const endpoint = (window.location.pathname.includes('/admin/')) ? 'csrf-token.php' : 'admin/csrf-token.php';
                const resp = await fetch(endpoint, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (resp.ok) {
                    const data = await resp.json();
                    if (data && data.csrf_token) {
                        lastSyncTime = Date.now();
                        document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                            input.value = data.csrf_token;
                        });
                    }
                }
            } catch (_) {
                // Falha silenciosa de rede
            }
        }

        // Sincroniza periodicamente a cada 5 minutos
        setInterval(syncCsrfToken, 5 * 60 * 1000);

        // Sincroniza ao retornar o foco para a aba caso tenham se passado mais de 3 minutos
        window.addEventListener('focus', function() {
            if (Date.now() - lastSyncTime > 3 * 60 * 1000) {
                syncCsrfToken();
            }
        });

        // Sincronização inicial
        setTimeout(syncCsrfToken, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initImageOptimizers();
            initCsrfHeartbeat();
        });
    } else {
        initImageOptimizers();
        initCsrfHeartbeat();
    }

})(window, document);
