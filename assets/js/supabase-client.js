/**
 * ==============================================================================
 * ART FOR SALE - Camada de Acesso a Dados e Supabase Storage (supabase-client.js)
 * Arquivo: /assets/js/supabase-client.js
 * Tecnologias: JavaScript Vanilla, Fetch API, Supabase Storage, PostgREST
 * Slogan: "Arte que transforma espaços."
 * 
 * PADRÕES:
 * - Separação estrita de lógica de acesso aos dados e storage.
 * - Respeita o Row Level Security (RLS) via chave pública ANON ou token Auth.
 * - Organização do Storage:
 *     artworks/{artwork_id}/principal/
 *     artworks/{artwork_id}/gallery/
 * - Tipos de imagem suportados:
 *     principal, frontal, lateral, detalhe, textura, ambiente, outras
 * - Validações estritas:
 *     Extensões: JPG, JPEG, PNG, WEBP
 *     Tamanho máximo: 10 MB (10.485.760 bytes)
 * - Garante uma única imagem principal nos cards e todas as fotos na página da obra.
 * ==============================================================================
 */

(function () {
    'use strict';

    const ALLOWED_TYPES = ['principal', 'frontal', 'lateral', 'detalhe', 'textura', 'ambiente', 'outras'];
    const ALLOWED_MIME = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp'];
    const MAX_SIZE = 10485760; // 10 MB

    /**
     * Valida se o Supabase foi configurado com credenciais válidas pelo desenvolvedor.
     */
    function isConfigured() {
        const cfg = window.ARTSELL_CONFIG;
        if (!cfg || !cfg.supabaseUrl || !cfg.supabaseAnonKey) return false;
        if (cfg.supabaseUrl.includes('SEU_PROJETO') || cfg.supabaseAnonKey.includes('SUA_CHAVE_ANON')) {
            return false;
        }
        try {
            const url = new URL(cfg.supabaseUrl);
            return Boolean(url.hostname && url.hostname.includes('.supabase.co'));
        } catch (e) {
            return false;
        }
    }

    const clientQueryCache = new Map();
    const QUERY_CACHE_TTL_MS = 60000; // 60 segundos de cache para consultas públicas

    /**
     * Limpa o cache de consultas do cliente
     */
    function clearQueryCache() {
        clientQueryCache.clear();
    }

    /**
     * Helper genérico para chamadas à API PostgREST do Supabase com cache em memória
     */
    async function request(endpoint, options = {}) {
        if (!isConfigured()) {
            return {
                data: null,
                count: null,
                error: new Error('Supabase não configurado. Utilizando dados locais.')
            };
        }

        const method = (options.method || 'GET').toUpperCase();
        const isPublicGet = method === 'GET' && !options.token && !options.body;
        const cacheKey = `${endpoint}`;

        // Limpa o cache automaticamente caso seja uma mutação (POST, PATCH, DELETE, PUT)
        if (['POST', 'PATCH', 'DELETE', 'PUT'].includes(method)) {
            clearQueryCache();
        }

        // Retorna do cache em memória se válido
        if (isPublicGet && clientQueryCache.has(cacheKey)) {
            const cached = clientQueryCache.get(cacheKey);
            if (Date.now() - cached.timestamp < QUERY_CACHE_TTL_MS) {
                return { data: cached.data, count: cached.count, error: null };
            } else {
                clientQueryCache.delete(cacheKey);
            }
        }

        const cfg = window.ARTFORSALE_CONFIG || window.ARTSALE_CONFIG || window.ARTSELL_CONFIG;
        const cleanBaseUrl = cfg.supabaseUrl.replace(/\/+$/, '').replace(/\/rest\/v1\/?$/i, '');
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/' + endpoint;
        const fullUrl = `${cleanBaseUrl}/rest/v1${cleanEndpoint}`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), cfg.timeoutMs || 9000);

        const bearerToken = options.token || cfg.supabaseAnonKey;
        const headers = {
            'apikey': cfg.supabaseAnonKey,
            'Authorization': `Bearer ${bearerToken}`,
            'Content-Type': 'application/json',
            ...(options.headers || {})
        };

        if (options.prefer) {
            headers['Prefer'] = options.prefer;
        }

        try {
            const fetchOptions = {
                method: method,
                headers: headers,
                signal: controller.signal
            };

            if (options.body) {
                fetchOptions.body = JSON.stringify(options.body);
            }

            const response = await fetch(fullUrl, fetchOptions);
            clearTimeout(timeoutId);

            if (!response.ok) {
                let errDetail = `HTTP ${response.status} ${response.statusText}`;
                try {
                    const errJson = await response.json();
                    if (errJson && errJson.message) errDetail = errJson.message;
                } catch (_) {}
                return { data: null, count: null, error: new Error(errDetail) };
            }

            let count = null;
            const contentRange = response.headers.get('content-range');
            if (contentRange && contentRange.includes('/')) {
                const totalStr = contentRange.split('/')[1];
                if (totalStr !== '*') count = parseInt(totalStr, 10);
            }

            if (response.status === 204) {
                return { data: true, count, error: null };
            }

            const data = await response.json();
            if (isPublicGet && data) {
                clientQueryCache.set(cacheKey, { data, count, timestamp: Date.now() });
            }
            return { data, count, error: null };
        } catch (err) {
            clearTimeout(timeoutId);
            const msg = err.name === 'AbortError'
                ? 'Tempo limite de conexão excedido ao consultar o acervo.'
                : (err.message || 'Falha de comunicação com o servidor.');
            return { data: null, count: null, error: new Error(msg) };
        }
    }

    /**
     * Retorna a URL de miniatura otimizada para cards (reduzindo tráfego e evitando imagens gigantes)
     */
    function getThumbnailUrl(imageUrl, width = 480, height = 0, quality = 82) {
        if (!imageUrl) return '';
        const resolved = resolveImageUrl(imageUrl);
        if (!resolved || resolved.endsWith('.svg')) return resolved;

        if (resolved.includes('/storage/v1/object/public/')) {
            if (width > 0) {
                const renderUrl = resolved.replace('/storage/v1/object/public/', '/storage/v1/render/image/public/');
                const sep = renderUrl.includes('?') ? '&' : '?';
                const hParam = height > 0 ? `&height=${height}` : '';
                return `${renderUrl}${sep}width=${width}&quality=${quality}${hParam}&resize=contain`;
            }
            return resolved;
        }

        if (resolved.includes('images.unsplash.com')) {
            if (width > 0) {
                const baseUrl = resolved.split('?')[0];
                const hParam = height > 0 ? `&h=${height}` : '';
                return `${baseUrl}?w=${width}&auto=format&fit=crop&q=${quality}${hParam}`;
            }
            return resolved;
        }

        return resolved;
    }

    /**
     * Resolve a URL de exibição de uma imagem (remota, relativa local ou Supabase Storage)
     */
    function resolveImageUrl(imagePath) {
        if (!imagePath) return '';
        if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
            return imagePath;
        }
        if (imagePath.startsWith('assets/')) {
            const isInsidePages = window.location.pathname.includes('/pages/');
            return isInsidePages ? '../' + imagePath : imagePath;
        }
        if (isConfigured()) {
            const cfg = window.ARTFORSALE_CONFIG || window.ARTSALE_CONFIG || window.ARTSELL_CONFIG;
            const cleanBase = cfg.supabaseUrl.replace(/\/+$/, '').replace(/\/rest\/v1\/?$/i, '');
            const bucket = cfg.storageBucket || 'artworks';
            return `${cleanBase}/storage/v1/object/public/${bucket}/${imagePath.replace(/^\/+/, '')}`;
        }
        return imagePath;
    }

    /**
     * Legenda padronizada em português para os 7 tipos de imagens da obra
     */
    function formatImageCaption(type) {
        switch (type) {
            case 'principal': return 'Imagem principal';
            case 'frontal':   return 'Fotografia frontal';
            case 'lateral':   return 'Fotografia lateral';
            case 'detalhe':   return 'Detalhe da obra';
            case 'textura':   return 'Textura e relevo';
            case 'ambiente':  return 'Obra em ambiente';
            case 'outras':    return 'Vista adicional';
            default:          return 'Vista da obra';
        }
    }

    /**
     * Valida arquivo de imagem para upload
     * Regras: JPG, JPEG, PNG, WEBP e tamanho máximo de 10 MB
     */
    function validateImageFile(file) {
        if (!file) {
            return { valid: false, error: 'Nenhum arquivo de imagem foi selecionado.' };
        }

        const ext = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTS.includes(ext)) {
            return { 
                valid: false, 
                error: `Formato inválido (.${ext}). Apenas JPG, JPEG, PNG e WEBP são permitidos.` 
            };
        }

        if (file.type && !ALLOWED_MIME.includes(file.type.toLowerCase())) {
            return { 
                valid: false, 
                error: `Tipo MIME inválido (${file.type}). Apenas JPG, JPEG, PNG e WEBP são aceitos.` 
            };
        }

        if (file.size > MAX_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
            return { 
                valid: false, 
                error: `O arquivo tem ${sizeMB} MB. O limite máximo permitido é de 10 MB.` 
            };
        }

        return { valid: true, error: null };
    }

    /**
     * Monta o caminho de armazenamento no Supabase Storage:
     * artworks/{artwork_id}/principal/{nome} ou artworks/{artwork_id}/gallery/{nome}
     */
    function buildStoragePath(artworkId, imageType, originalName) {
        const folder = (imageType === 'principal') ? 'principal' : 'gallery';
        const cleanName = (originalName || 'image.jpg')
            .toLowerCase()
            .replace(/[^a-z0-9._-]/g, '_');
        const timestamp = Date.now();
        return `${artworkId}/${folder}/${timestamp}_${cleanName}`;
    }

    /**
     * Normaliza os dados brutos da obra vindos do Supabase PostgREST
     * Garante a seleção da imagem principal para cards e a galeria completa.
     */
    function normalizeArtwork(raw) {
        if (!raw) return null;

        const artistaObj = raw.artists || {};
        const categoriaObj = raw.categories || {};
        const imagensArr = Array.isArray(raw.artwork_images) ? [...raw.artwork_images] : [];

        // Ordena imagens: is_primary primeiro, depois tipo 'principal', depois sort_order
        imagensArr.sort((a, b) => {
            if (Boolean(a.is_primary) && !Boolean(b.is_primary)) return -1;
            if (!Boolean(a.is_primary) && Boolean(b.is_primary)) return 1;
            if (a.image_type === 'principal' && b.image_type !== 'principal') return -1;
            if (a.image_type !== 'principal' && b.image_type === 'principal') return 1;
            return (a.sort_order || 0) - (b.sort_order || 0);
        });

        // Resolve a imagem principal para os cards
        let primaryImgUrl = '';
        let primaryStoragePath = '';
        if (imagensArr.length > 0) {
            const primaryObj = imagensArr[0];
            primaryImgUrl = primaryObj.image_url;
            primaryStoragePath = primaryObj.storage_path || '';
        } else if (raw.image_url) {
            primaryImgUrl = raw.image_url;
        } else {
            primaryImgUrl = 'assets/images/obras/obra-horizontes-dourados.svg';
        }

        // Formatação das dimensões da obra
        let dimensoesStr = '';
        if (raw.height && raw.width) {
            const h = parseFloat(raw.height);
            const w = parseFloat(raw.width);
            const d = raw.depth ? ` × ${parseFloat(raw.depth)}` : '';
            dimensoesStr = `${h} × ${w}${d} cm`;
        } else {
            dimensoesStr = '80 × 120 cm';
        }

        // Monta a galeria completa com as fotografias
        const galeria = imagensArr.map(img => ({
            id: img.id,
            imagem: resolveImageUrl(img.image_url),
            storage_path: img.storage_path || '',
            tipo: img.image_type || 'principal',
            titulo: formatImageCaption(img.image_type),
            is_primary: Boolean(img.is_primary),
            sort_order: img.sort_order || 0
        }));

        return {
            id: raw.id,
            slug: raw.slug || '',
            titulo: raw.name || 'Obra de Arte',
            artista: artistaObj.name || 'Artista Convidado',
            artista_slug: artistaObj.slug || '',
            biografia_artista: artistaObj.biography || '',
            dimensoes: dimensoesStr,
            preco: 'Preço sob consulta',
            categoria: categoriaObj.name || 'Pinturas',
            categoria_slug: categoriaObj.slug || 'todas',
            imagem: resolveImageUrl(primaryImgUrl),
            imagem_storage_path: primaryStoragePath,
            tecnica: raw.technique || 'Óleo sobre tela',
            ano: raw.year || 2024,
            codigo: raw.code || 'ASF-0000',
            estado_conservacao: raw.conservation_state || 'Excelente',
            procedencia: raw.provenance || 'Coleção Particular',
            localizacao: raw.location || 'Brasil',
            descricao: raw.description || '',
            disponibilidade: raw.availability || 'available',
            destaque: Boolean(raw.featured),
            ativo: Boolean(raw.active),
            galeria: galeria
        };
    }

    /**
     * Consulta pública: Busca obras ativas com imagem principal para os cards
     */
    async function buscarObras(params = {}) {
        const {
            categoria = 'all',
            termo = '',
            ordenacao = 'recente',
            limite = 50,
            offset = 0
        } = params;

        let query = 'artworks?select=id,name,slug,technique,year,width,height,depth,code,availability,featured,active,created_at,artists(id,name,slug),categories(id,name,slug),artwork_images(id,image_url,storage_path,image_type,sort_order,is_primary)&active=eq.true';

        if (categoria && categoria !== 'all' && categoria !== 'todas') {
            query += `&categories.slug=eq.${encodeURIComponent(categoria)}&categories=not.is.null`;
        }

        if (termo && termo.trim()) {
            const clean = encodeURIComponent(termo.trim());
            query += `&or=(name.ilike.*${clean}*,technique.ilike.*${clean}*,code.ilike.*${clean}*)`;
        }

        if (ordenacao === 'az') {
            query += '&order=name.asc';
        } else if (ordenacao === 'za') {
            query += '&order=name.desc';
        } else {
            query += '&order=created_at.desc';
        }

        if (limite) query += `&limit=${parseInt(limite, 10)}`;
        if (offset) query += `&offset=${parseInt(offset, 10)}`;

        const { data, count, error } = await request(query, {
            prefer: 'count=exact'
        });

        if (error) {
            return { data: null, count: 0, error };
        }

        const normalized = (data || []).map(normalizeArtwork);
        return { data: normalized, count: count || normalized.length, error: null };
    }

    /**
     * Consulta pública: Busca uma única obra e TODAS as suas imagens da galeria
     */
    async function buscarObraPorSlug(slugOrId) {
        if (!slugOrId) {
            return { data: null, error: new Error('Slug ou ID da obra não informado.') };
        }

        const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(slugOrId);
        const filter = isUuid ? `id=eq.${slugOrId}` : `slug=eq.${encodeURIComponent(slugOrId)}`;

        const query = `artworks?select=id,name,slug,description,technique,year,width,height,depth,conservation_state,code,availability,provenance,location,featured,active,artists(id,name,slug,biography),categories(id,name,slug),artwork_images(id,image_url,storage_path,image_type,sort_order,is_primary)&${filter}&limit=1`;

        const { data, error } = await request(query);
        if (error) return { data: null, error };
        if (!data || data.length === 0) {
            return { data: null, error: new Error('Obra não encontrada.') };
        }

        return { data: normalizeArtwork(data[0]), error: null };
    }

    /**
     * Consulta todas as imagens avulsas vinculadas a uma obra
     */
    async function buscarImagensObra(artworkId) {
        if (!artworkId) {
            return { data: [], error: new Error('artworkId não informado.') };
        }

        const query = `artwork_images?select=id,artwork_id,image_url,storage_path,image_type,sort_order,is_primary&artwork_id=eq.${encodeURIComponent(artworkId)}&order=is_primary.desc,sort_order.asc`;
        const { data, error } = await request(query);

        if (error) return { data: [], error };

        const normalized = (data || []).map(img => ({
            id: img.id,
            artwork_id: img.artwork_id,
            imagem: resolveImageUrl(img.image_url),
            storage_path: img.storage_path,
            tipo: img.image_type,
            titulo: formatImageCaption(img.image_type),
            is_primary: Boolean(img.is_primary),
            sort_order: img.sort_order || 0
        }));

        return { data: normalized, error: null };
    }

    /**
     * Busca obras em destaque para a home
     */
    async function buscarObrasEmDestaque(limit = 4) {
        const query = `artworks?select=id,name,slug,technique,year,width,height,depth,code,availability,featured,active,artists(id,name,slug),categories(id,name,slug),artwork_images(id,image_url,storage_path,image_type,sort_order,is_primary)&featured=eq.true&active=eq.true&order=created_at.desc&limit=${limit}`;

        const { data, error } = await request(query);
        if (error) return { data: null, error };

        const normalized = (data || []).map(normalizeArtwork);
        return { data: normalized, error: null };
    }

    /**
     * ==========================================================================
     * OPERAÇÕES ADMINISTRATIVAS DE STORAGE E IMAGENS
     * Requer token de autenticação de administrador (public.is_admin() = true)
     * ==========================================================================
     */

    /**
     * Envia imagem de uma obra para o Supabase Storage e salva o registro no banco
     * Organização:
     *   artworks/{artwork_id}/principal/{filename} (se is_primary ou type=principal)
     *   artworks/{artwork_id}/gallery/{filename}   (se for frontal, lateral, detalhe, etc.)
     */
    async function uploadArtworkImage(params = {}) {
        const {
            file,
            artworkId,
            imageType = 'principal',
            isPrimary = false,
            sortOrder = 0,
            token = null
        } = params;

        if (!token) {
            return { 
                data: null, 
                error: new Error('Não autenticado. Upload restrito a administradores autorizados.') 
            };
        }

        if (!artworkId) {
            return { data: null, error: new Error('ID da obra não informado para o upload.') };
        }

        if (!ALLOWED_TYPES.includes(imageType)) {
            return { 
                data: null, 
                error: `Tipo de imagem "${imageType}" inválido. Permitidos: ${ALLOWED_TYPES.join(', ')}` 
            };
        }

        // Validação estrita do arquivo (tipo JPG/JPEG/PNG/WEBP e tamanho máx 10MB)
        const val = validateImageFile(file);
        if (!val.valid) {
            return { data: null, error: new Error(val.error) };
        }

        const shouldBePrimary = isPrimary || (imageType === 'principal');
        const storagePath = buildStoragePath(artworkId, imageType, file.name);

        const cfg = window.ARTSELL_CONFIG;
        const cleanBaseUrl = cfg.supabaseUrl.replace(/\/+$/, '');
        const bucket = cfg.storageBucket || 'artworks';
        const storageUploadUrl = `${cleanBaseUrl}/storage/v1/object/${bucket}/${storagePath}`;

        try {
            // 1. Upload do arquivo binário para o Supabase Storage
            const uploadResp = await fetch(storageUploadUrl, {
                method: 'POST',
                headers: {
                    'apikey': cfg.supabaseAnonKey,
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': file.type || 'image/jpeg',
                    'x-upsert': 'true'
                },
                body: file
            });

            if (!uploadResp.ok) {
                let errText = `Falha no upload (${uploadResp.status})`;
                try {
                    const errObj = await uploadResp.json();
                    if (errObj && (errObj.message || errObj.error)) {
                        errText = errObj.message || errObj.error;
                    }
                } catch (_) {}
                return { data: null, error: new Error(errText) };
            }

            // 2. URL pública definitiva do arquivo
            const publicUrl = `${cleanBaseUrl}/storage/v1/object/public/${bucket}/${storagePath}`;

            // 3. Salva os metadados na tabela public.artwork_images
            // O trigger no PostgreSQL garantirá que apenas uma imagem permaneça como is_primary=true
            const insertPayload = {
                artwork_id: artworkId,
                image_url: publicUrl,
                storage_path: storagePath,
                image_type: imageType,
                sort_order: parseInt(sortOrder, 10) || 0,
                is_primary: shouldBePrimary
            };

            const dbRes = await request('/artwork_images', {
                method: 'POST',
                token: token,
                body: insertPayload,
                prefer: 'return=representation'
            });

            if (dbRes.error) {
                return { data: null, error: dbRes.error };
            }

            const savedRecord = Array.isArray(dbRes.data) ? dbRes.data[0] : dbRes.data;
            return {
                data: {
                    ...savedRecord,
                    public_url: publicUrl,
                    storage_path: storagePath
                },
                error: null
            };

        } catch (err) {
            return { 
                data: null, 
                error: new Error(`Erro inesperado ao realizar upload: ${err.message}`) 
            };
        }
    }

    /**
     * Exclui uma imagem do Supabase Storage e seu respectivo registro no banco
     */
    async function deleteArtworkImage(params = {}) {
        const { imageId, storagePath, token = null } = params;

        if (!token) {
            return { data: null, error: new Error('Não autenticado. Exclusão restrita a administradores.') };
        }

        if (!imageId) {
            return { data: null, error: new Error('ID do registro da imagem não informado.') };
        }

        const cfg = window.ARTSELL_CONFIG;
        const cleanBaseUrl = cfg.supabaseUrl.replace(/\/+$/, '');
        const bucket = cfg.storageBucket || 'artworks';

        try {
            // 1. Remove arquivo do bucket no Supabase Storage se houver storagePath
            if (storagePath) {
                const deleteUrl = `${cleanBaseUrl}/storage/v1/object/${bucket}`;
                await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'apikey': cfg.supabaseAnonKey,
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ prefixes: [storagePath] })
                });
            }

            // 2. Remove registro da tabela public.artwork_images
            const dbRes = await request(`/artwork_images?id=eq.${encodeURIComponent(imageId)}`, {
                method: 'DELETE',
                token: token
            });

            if (dbRes.error) {
                return { data: null, error: dbRes.error };
            }

            return { data: true, error: null };
        } catch (err) {
            return { data: null, error: new Error(`Erro ao excluir imagem: ${err.message}`) };
        }
    }

    /**
     * Define uma imagem como principal de uma obra.
     * O trigger de banco 'trigger_single_primary_artwork_image' automaticamente
     * desmarcará todas as outras imagens da mesma obra.
     */
    async function setPrimaryArtworkImage(params = {}) {
        const { artworkId, imageId, token = null } = params;

        if (!token) {
            return { data: null, error: new Error('Não autenticado. Ação restrita a administradores.') };
        }

        if (!imageId) {
            return { data: null, error: new Error('ID da imagem não informado.') };
        }

        const dbRes = await request(`/artwork_images?id=eq.${encodeURIComponent(imageId)}`, {
            method: 'PATCH',
            token: token,
            body: { is_primary: true },
            prefer: 'return=representation'
        });

        if (dbRes.error) {
            return { data: null, error: dbRes.error };
        }

        return { data: Array.isArray(dbRes.data) ? dbRes.data[0] : dbRes.data, error: null };
    }

    /**
     * Autenticação de Administrador via Supabase Auth
     */
    async function loginAdmin(email, password) {
        if (!isConfigured()) {
            return { data: null, error: new Error('Supabase não configurado.') };
        }

        const cfg = window.ARTSELL_CONFIG;
        const cleanBaseUrl = cfg.supabaseUrl.replace(/\/+$/, '');
        const authUrl = `${cleanBaseUrl}/auth/v1/token?grant_type=password`;

        try {
            const resp = await fetch(authUrl, {
                method: 'POST',
                headers: {
                    'apikey': cfg.supabaseAnonKey,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: (email || '').trim(),
                    password: password || ''
                })
            });

            const data = await resp.json();
            if (!resp.ok) {
                const msg = data.error_description || data.msg || data.message || 'Falha de autenticação.';
                return { data: null, error: new Error(msg) };
            }

            // Armazena sessão localmente
            if (data.access_token) {
                sessionStorage.setItem('artsell_admin_token', data.access_token);
                sessionStorage.setItem('artsell_admin_user', JSON.stringify(data.user || {}));
            }

            return { data, error: null };
        } catch (err) {
            return { data: null, error: new Error(`Erro na conexão de autenticação: ${err.message}`) };
        }
    }

    /**
     * Retorna a sessão administrativa ativa
     */
    function getAdminSession() {
        const token = sessionStorage.getItem('artsell_admin_token');
        const userStr = sessionStorage.getItem('artsell_admin_user');
        let user = null;
        if (userStr) {
            try { user = JSON.parse(userStr); } catch (_) {}
        }
        return { token, user, isAuthenticated: Boolean(token) };
    }

    /**
     * Encerra a sessão administrativa
     */
    function logoutAdmin() {
        sessionStorage.removeItem('artsell_admin_token');
        sessionStorage.removeItem('artsell_admin_user');
    }

    /**
     * Formulário de interesse de compra em uma obra
     */
    async function enviarInteresseObra(payload) {
        const body = {
            artwork_id: payload.artwork_id || null,
            name: (payload.name || '').trim(),
            email: (payload.email || '').trim(),
            whatsapp: (payload.whatsapp || '').trim(),
            message: (payload.message || '').trim(),
            status: 'new'
        };

        if (!body.name || !body.email) {
            return { data: null, error: new Error('Nome e e-mail são obrigatórios.') };
        }

        const { data, error } = await request('/inquiries', {
            method: 'POST',
            body: body,
            prefer: 'return=minimal'
        });

        return { data: error ? null : true, error };
    }

    /**
     * Formulário geral de contato
     */
    async function enviarMensagemContato(payload) {
        const body = {
            name: (payload.name || '').trim(),
            email: (payload.email || '').trim(),
            whatsapp: (payload.whatsapp || '').trim(),
            subject: (payload.subject || 'Contato pelo site').trim(),
            message: (payload.message || '').trim(),
            status: 'new'
        };

        if (!body.name || !body.email || !body.message) {
            return { data: null, error: new Error('Nome, e-mail e mensagem são obrigatórios.') };
        }

        const { data, error } = await request('/contacts', {
            method: 'POST',
            body: body,
            prefer: 'return=minimal'
        });

        return { data: error ? null : true, error };
    }

    // Exportação da API no namespace global da galeria
    const artSaleApiInstance = Object.freeze({
        isConfigured,
        resolveImageUrl,
        getThumbnailUrl,
        formatImageCaption,
        validateImageFile,
        buildStoragePath,
        normalizeArtwork,
        buscarObras,
        buscarObraPorSlug,
        buscarImagensObra,
        buscarObrasEmDestaque,
        uploadArtworkImage,
        deleteArtworkImage,
        setPrimaryArtworkImage,
        loginAdmin,
        getAdminSession,
        logoutAdmin,
        enviarInteresseObra,
        enviarMensagemContato,
        ALLOWED_TYPES,
        ALLOWED_MIME,
        MAX_SIZE
    });

    window.ArtForSaleAPI = artSaleApiInstance;
    window.ArtSaleAPI = artSaleApiInstance;
    window.ArtSellAPI = artSaleApiInstance; // Mantido para compatibilidade retroativa

})();
