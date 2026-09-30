<?php
/**
 * ART FOR SALE - Configuração Global
 * Identidade: Galeria de Arte Premium
 * Slogan: Arte que transforma espaços.
 */

// Informações Gerais do Site
define('SITE_NAME', 'Art For Sale');
define('SITE_SLOGAN', 'Arte que transforma espaços.');
define('SITE_YEAR', 2026);
define('SITE_CONTACT_EMAIL', 'artforsale1944@gmail.com');

// Caminhos base
define('BASE_PATH', dirname(__DIR__));
define('ASSETS_PATH', BASE_PATH . '/assets');
define('IMAGES_PATH', ASSETS_PATH . '/images');
define('OBRAS_PATH', IMAGES_PATH . '/obras');
define('SITE_IMG_PATH', IMAGES_PATH . '/site');

// Dados de Atendimento / Contatos Oficiais
$contatos = [
    'elisabeth' => [
        'nome' => 'Sra. Elisabeth',
        'cargo' => 'Curadoria & Atendimento',
        'telefone' => '(51) 99114-0044',
        'whatsapp' => '5551991140044',
        'whatsapp_link' => 'https://wa.me/5551991140044?text=' . urlencode('Olá, Sra. Elisabeth. Gostaria de mais informações sobre as obras da Art For Sale.')
    ],
    'felipe' => [
        'nome' => 'Felipe C',
        'cargo' => 'Atendimento & Vendas',
        'telefone' => '(51) 99126-6414',
        'whatsapp' => '5551991266414',
        'whatsapp_link' => 'https://wa.me/5551991266414?text=' . urlencode('Olá, Felipe. Gostaria de consultar uma obra na Art For Sale.')
    ],
    'email' => [
        'endereco' => defined('SITE_CONTACT_EMAIL') ? SITE_CONTACT_EMAIL : 'artforsale1944@gmail.com',
        'link' => 'mailto:' . (defined('SITE_CONTACT_EMAIL') ? SITE_CONTACT_EMAIL : 'artforsale1944@gmail.com') . '?subject=' . urlencode('Consulta de Obras - Art For Sale')
    ]
];

if (!isset($pathPrefix)) {
    $pathPrefix = '';
}

if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Sessão persistente de longa duração (30 dias = 2.592.000 segundos)
    $lifetime = 60 * 60 * 24 * 30;
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Polyfills para ambientes PHP sem a extensão mbstring habilitada
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $str, ?string $encoding = null): string {
        return strtolower($str);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $str, ?string $encoding = null): string {
        return strtoupper($str);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $str, ?string $encoding = null): int {
        return strlen($str);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $str, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}

/**
 * Retorna lista de IDs de obras que foram excluídas permanentemente pelo curador.
 * Previne que obras excluídas reapareçam mesmo se houver fallback de catálogo seed.
 */
function artsale_get_deleted_artwork_ids(): array {
    static $memoryCache = null;
    if ($memoryCache !== null) {
        return $memoryCache;
    }

    $file = BASE_PATH . '/database/deleted_artworks.json';
    $ids = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $decoded = json_decode($content ?: '[]', true);
        if (is_array($decoded)) {
            $ids = $decoded;
        }
    }

    // Persistência em Sessão PHP
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['deleted_artworks']) && is_array($_SESSION['deleted_artworks'])) {
        $ids = array_merge($ids, $_SESSION['deleted_artworks']);
    }

    // Persistência em Cookies HTTP (resiliente para ambientes serverless como Vercel)
    if (!empty($_COOKIE['artsale_deleted_ids'])) {
        $cookieRaw = trim($_COOKIE['artsale_deleted_ids']);
        $cookieDecoded = @json_decode($cookieRaw, true);
        if (is_array($cookieDecoded)) {
            $ids = array_merge($ids, $cookieDecoded);
        } else {
            $parts = explode(',', $cookieRaw);
            $ids = array_merge($ids, array_map('trim', $parts));
        }
    }

    $unique = array_values(array_unique(array_filter(array_map('strval', $ids))));
    $memoryCache = $unique;
    return $unique;
}

/**
 * Marca uma obra como permanentemente excluída em arquivo, cookie e sessão
 */
function artsale_mark_artwork_deleted(string $artworkId): void {
    if (empty($artworkId)) return;
    $idStr = (string)$artworkId;

    $current = artsale_get_deleted_artwork_ids();
    if (!in_array($idStr, $current, true)) {
        $current[] = $idStr;
    }

    // 1. Persistência em arquivo físico (quando diretório tem permissão de escrita)
    $dir = BASE_PATH . '/database';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $file = $dir . '/deleted_artworks.json';
    @file_put_contents($file, json_encode(array_values($current), JSON_PRETTY_PRINT));

    // 2. Persistência em sessão PHP ativa
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['deleted_artworks'] = $current;
    }

    // 3. Persistência em cookie HTTP (365 dias) - fundamental para Vercel Serverless
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $jsonCurrent = json_encode(array_values($current));
    @setcookie('artsale_deleted_ids', $jsonCurrent, [
        'expires'  => time() + (365 * 24 * 60 * 60),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['artsale_deleted_ids'] = $jsonCurrent;

    // 4. Remove da persistência local
    if (function_exists('artsale_remove_local_artwork')) {
        artsale_remove_local_artwork($artworkId);
    }

    // 5. Purga o cache de consultas
    if (function_exists('supabase_purge_cache')) {
        supabase_purge_cache();
    }
}

/**
 * Retorna as obras cadastradas localmente pelo curador (persistência garantida)
 */
function artsale_get_local_artworks(): array {
    $file = BASE_PATH . '/database/local_artworks.json';
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    $data = json_decode($content ?: '[]', true);
    if (!is_array($data)) return [];
    $deletedIds = artsale_get_deleted_artwork_ids();
    return array_values(array_filter($data, function($item) use ($deletedIds) {
        $id = (string)($item['id'] ?? '');
        if (empty($id)) return false;
        if (in_array($id, $deletedIds, true)) return false;
        if (isset($item['ativo']) && $item['ativo'] === false) return false;
        return true;
    }));
}

/**
 * Salva ou atualiza uma obra no arquivo local de persistência
 */
function artsale_save_local_artwork(array $artwork): void {
    if (empty($artwork['id'])) return;
    $deletedIds = artsale_get_deleted_artwork_ids();
    if (in_array((string)$artwork['id'], $deletedIds, true)) return;

    $dir = BASE_PATH . '/database';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $file = $dir . '/local_artworks.json';
    $current = artsale_get_local_artworks();
    $idStr = (string)$artwork['id'];

    $found = false;
    foreach ($current as $idx => $item) {
        if ((string)($item['id'] ?? '') === $idStr) {
            $current[$idx] = array_merge($item, $artwork);
            $found = true;
            break;
        }
    }
    if (!$found) {
        array_unshift($current, $artwork);
    }
    @file_put_contents($file, json_encode(array_values($current), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Remove uma obra da persistência local
 */
function artsale_remove_local_artwork(string $artworkId): void {
    if (empty($artworkId)) return;
    $file = BASE_PATH . '/database/local_artworks.json';
    if (!file_exists($file)) return;
    $current = artsale_get_local_artworks();
    $idStr = (string)$artworkId;
    $filtered = array_values(array_filter($current, fn($item) => (string)($item['id'] ?? '') !== $idStr));
    @file_put_contents($file, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Retorna lista de categorias locais
 */
function artsale_get_local_categories(): array {
    $file = BASE_PATH . '/database/local_categories.json';
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    $data = json_decode($content ?: '[]', true);
    return is_array($data) ? $data : [];
}

/**
 * Salva ou atualiza uma categoria na persistência local
 */
function artsale_save_local_category(array $categoria): void {
    if (empty($categoria['id']) && empty($categoria['slug'])) return;
    $dir = BASE_PATH . '/database';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/local_categories.json';
    $current = artsale_get_local_categories();
    $idStr = (string)($categoria['id'] ?? '');
    $slugStr = (string)($categoria['slug'] ?? '');

    $found = false;
    foreach ($current as $idx => $item) {
        if ((!empty($idStr) && (string)($item['id'] ?? '') === $idStr) ||
            (!empty($slugStr) && (string)($item['slug'] ?? '') === $slugStr)) {
            $current[$idx] = array_merge($item, $categoria);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $current[] = $categoria;
    }
    @file_put_contents($file, json_encode(array_values($current), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Retorna lista de artistas locais
 */
function artsale_get_local_artists(): array {
    $file = BASE_PATH . '/database/local_artists.json';
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    $data = json_decode($content ?: '[]', true);
    return is_array($data) ? $data : [];
}

/**
 * Salva ou atualiza um artista na persistência local
 */
function artsale_save_local_artist(array $artista): void {
    if (empty($artista['id']) && empty($artista['slug'])) return;
    $dir = BASE_PATH . '/database';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/local_artists.json';
    $current = artsale_get_local_artists();
    $idStr = (string)($artista['id'] ?? '');
    $slugStr = (string)($artista['slug'] ?? '');

    $found = false;
    foreach ($current as $idx => $item) {
        if ((!empty($idStr) && (string)($item['id'] ?? '') === $idStr) ||
            (!empty($slugStr) && (string)($item['slug'] ?? '') === $slugStr)) {
            $current[$idx] = array_merge($item, $artista);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $current[] = $artista;
    }
    @file_put_contents($file, json_encode(array_values($current), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Mescla obras prioritárias (reais/cadastradas) no início do catálogo base,
 * garantindo que as obras recém-cadastradas apareçam em primeiro lugar.
 */
function artsale_merge_catalogo(array $prioritarias, array $catalogoBase): array {
    $deletedIds = artsale_get_deleted_artwork_ids();
    $seenIds = [];
    $resultado = [];

    // 1. Obras prioritárias (vindas do Supabase ou persistência local)
    foreach ($prioritarias as $obra) {
        $id = (string)($obra['id'] ?? '');
        if (empty($id) || in_array($id, $deletedIds, true) || isset($seenIds[$id])) {
            continue;
        }
        $seenIds[$id] = true;
        $resultado[] = $obra;
    }

    // 2. Obras do catálogo base/seed para manter a galeria completa
    foreach ($catalogoBase as $obra) {
        $id = (string)($obra['id'] ?? '');
        if (empty($id) || in_array($id, $deletedIds, true) || isset($seenIds[$id])) {
            continue;
        }
        $seenIds[$id] = true;
        $resultado[] = $obra;
    }

    return $resultado;
}

/**
 * Retorna os 3 slides do Hero da página inicial.
 * Prioridade:
 * 1. Arquivo database/hero_slides.json
 * 2. Sessão PHP ativa
 * 3. Cookie HTTP artsale_hero_slides (para ambientes serverless)
 * 4. Fallback padrão da curadoria Art For Sale
 */
function artsale_get_hero_slides(bool $forceRefresh = false): array {
    static $slidesCache = null;
    if (!$forceRefresh && $slidesCache !== null) {
        return $slidesCache;
    }

    $defaultSlides = [
        [
            'id' => 1,
            'image' => 'assets/images/site/hero-bg.jpg',
            'eyebrow' => 'GALERIA DE ARTE',
            'title' => 'Arte que<br>transforma<br>espaços.',
            'description' => 'Descubra obras únicas e cuidadosamente selecionadas para colecionadores, apreciadores e ambientes que merecem personalidade.',
            'quote' => 'Mais que quadros, histórias que ganham vida no seu espaço.',
            'cta_primary_text' => 'Explorar obras',
            'cta_primary_url' => 'pages/obras.php',
            'cta_secondary_text' => 'Conhecer a Galeria',
            'cta_secondary_url' => '#sobre'
        ],
        [
            'id' => 2,
            'image' => 'assets/images/site/about-art-gallery.jpg',
            'eyebrow' => 'CURADORIA EXCLUSIVA',
            'title' => 'Coleções<br>únicas com<br>personalidade.',
            'description' => 'Pinturas a óleo, gravuras históricas e esculturas nobres selecionadas para elevar o design de interiores a outro patamar.',
            'quote' => 'A beleza clássica e contemporânea em perfeita harmonia.',
            'cta_primary_text' => 'Explorar obras',
            'cta_primary_url' => 'pages/obras.php',
            'cta_secondary_text' => 'Conhecer a Galeria',
            'cta_secondary_url' => '#sobre'
        ],
        [
            'id' => 3,
            'image' => 'assets/images/site/about-art-sell.jpg',
            'eyebrow' => 'ACERVO PRIVADO',
            'title' => 'Obras de arte<br>que contam<br>histórias.',
            'description' => 'Atendimento e consultoria especializada para encontrar a peça perfeita para sua residência, escritório ou coleção particular.',
            'quote' => 'Cada pincelada carrega uma emoção eterna e autêntica.',
            'cta_primary_text' => 'Explorar obras',
            'cta_primary_url' => 'pages/obras.php',
            'cta_secondary_text' => 'Conhecer a Galeria',
            'cta_secondary_url' => '#sobre'
        ]
    ];

    $loaded = null;
    $loadedTimestamp = 0;

    // 1. Cookie HTTP (resposta instantânea para o curador/administrador que salvou no navegador)
    if (!empty($_COOKIE['artsale_hero_slides'])) {
        $cookieDecoded = @json_decode($_COOKIE['artsale_hero_slides'], true);
        if (is_array($cookieDecoded) && count($cookieDecoded) >= 3) {
            $loaded = $cookieDecoded;
            $loadedTimestamp = (int)($cookieDecoded[0]['updated_at'] ?? 0);
        }
    }

    // 2. Sessão PHP
    if (!$loaded && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['hero_slides']) && is_array($_SESSION['hero_slides']) && count($_SESSION['hero_slides']) >= 3) {
        $loaded = $_SESSION['hero_slides'];
        $loadedTimestamp = (int)($_SESSION['hero_slides'][0]['updated_at'] ?? 0);
    }

    // 3. Cache em /tmp do servidor (instantâneo e compartilhado nas requisições)
    $tempFile = sys_get_temp_dir() . '/artsale_hero_slides.json';
    $tempSlides = null;
    $tempTimestamp = 0;
    if (file_exists($tempFile)) {
        $tempContent = @file_get_contents($tempFile);
        $tempDecoded = @json_decode($tempContent ?: '[]', true);
        if (is_array($tempDecoded) && count($tempDecoded) >= 3) {
            $tempSlides = $tempDecoded;
            $tempTimestamp = (int)($tempDecoded[0]['updated_at'] ?? filemtime($tempFile));
        }
    }

    if ($tempSlides && $tempTimestamp > $loadedTimestamp) {
        $loaded = $tempSlides;
        $loadedTimestamp = $tempTimestamp;
    }

    // 4. Supabase Storage na nuvem (sincronização global para qualquer visitante)
    $shouldCheckCloud = $forceRefresh || !$loaded || ($tempSlides === null) || (time() - $tempTimestamp > 120);
    if ($shouldCheckCloud) {
        if (!function_exists('supabase_is_configured') && file_exists(__DIR__ . '/supabase.php')) {
            @include_once __DIR__ . '/supabase.php';
        }
        if (function_exists('supabase_is_configured') && supabase_is_configured()) {
            try {
                $baseUrl = rtrim(SUPABASE_URL, '/');
                $bucket = defined('SUPABASE_STORAGE_BUCKET') ? SUPABASE_STORAGE_BUCKET : 'artworks';
                $cloudUrl = "{$baseUrl}/storage/v1/object/public/{$bucket}/site/hero_slides_config.png?t=" . time();
                $res = supabase_http_call($cloudUrl, 'GET', [], null, 3);
                if (!empty($res['response']) && in_array((int)($res['status'] ?? 0), [200, 201], true)) {
                    $cloudDecoded = @json_decode($res['response'], true);
                    if (is_array($cloudDecoded) && count($cloudDecoded) >= 3) {
                        $cloudTimestamp = (int)($cloudDecoded[0]['updated_at'] ?? 0);
                        @file_put_contents($tempFile, $res['response']);
                        if ($cloudTimestamp >= $loadedTimestamp || empty($loaded)) {
                            $loaded = $cloudDecoded;
                            $loadedTimestamp = $cloudTimestamp;
                        }
                    }
                }
            } catch (Throwable $e) {
                // Silencioso em caso de falha de conexão com a nuvem
            }
        }
    }

    // 5. Arquivo físico database/hero_slides.json
    if (!$loaded) {
        $file = BASE_PATH . '/database/hero_slides.json';
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $decoded = @json_decode($content ?: '[]', true);
            if (is_array($decoded) && count($decoded) >= 3) {
                $loaded = $decoded;
            }
        }
    }

    if (!$loaded) {
        $loaded = $defaultSlides;
    }

    // Garante que cada slide tenha todos os campos esperados
    $result = [];
    for ($i = 0; $i < 3; $i++) {
        $def = $defaultSlides[$i];
        $curr = $loaded[$i] ?? [];
        $secText = !empty($curr['cta_secondary_text']) ? $curr['cta_secondary_text'] : $def['cta_secondary_text'];
        $secUrl = !empty($curr['cta_secondary_url']) ? $curr['cta_secondary_url'] : $def['cta_secondary_url'];
        if (stripos($secText, 'categoria') !== false) {
            $secText = 'Conhecer a Galeria';
        }
        if (stripos($secUrl, 'categoria') !== false) {
            $secUrl = '#sobre';
        }

        $result[] = [
            'id' => $i + 1,
            'image' => !empty($curr['image']) ? $curr['image'] : $def['image'],
            'eyebrow' => !empty($curr['eyebrow']) ? $curr['eyebrow'] : $def['eyebrow'],
            'title' => !empty($curr['title']) ? $curr['title'] : $def['title'],
            'description' => !empty($curr['description']) ? $curr['description'] : $def['description'],
            'quote' => !empty($curr['quote']) ? $curr['quote'] : $def['quote'],
            'cta_primary_text' => !empty($curr['cta_primary_text']) ? $curr['cta_primary_text'] : $def['cta_primary_text'],
            'cta_primary_url' => !empty($curr['cta_primary_url']) ? $curr['cta_primary_url'] : $def['cta_primary_url'],
            'cta_secondary_text' => $secText,
            'cta_secondary_url' => $secUrl,
            'updated_at' => $curr['updated_at'] ?? 0
        ];
    }

    $slidesCache = $result;
    return $result;
}

/**
 * Salva a configuração dos 3 slides do Hero em nuvem, arquivo, sessão e cookie
 */
function artsale_save_hero_slides(array $slides): bool {
    if (count($slides) < 3) return false;

    $now = time();
    $normalized = [];
    for ($i = 0; $i < 3; $i++) {
        $s = $slides[$i] ?? [];
        $normalized[] = [
            'id' => $i + 1,
            'image' => trim($s['image'] ?? ''),
            'eyebrow' => trim($s['eyebrow'] ?? ''),
            'title' => trim($s['title'] ?? ''),
            'description' => trim($s['description'] ?? ''),
            'quote' => trim($s['quote'] ?? ''),
            'cta_primary_text' => trim($s['cta_primary_text'] ?? 'Explorar obras'),
            'cta_primary_url' => trim($s['cta_primary_url'] ?? 'pages/obras.php'),
            'cta_secondary_text' => trim($s['cta_secondary_text'] ?? 'Conhecer a Galeria'),
            'cta_secondary_url' => trim($s['cta_secondary_url'] ?? '#sobre'),
            'updated_at' => $now
        ];
    }

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // 1. Grava no cache /tmp (sempre gravável tanto em servidor Vercel serverless quanto local)
    $tempFile = sys_get_temp_dir() . '/artsale_hero_slides.json';
    @file_put_contents($tempFile, $json);

    // 2. Arquivo físico local database/hero_slides.json (se diretório for gravável)
    $dir = BASE_PATH . '/database';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $file = $dir . '/hero_slides.json';
    @file_put_contents($file, $json);

    // 3. Sincroniza com Supabase Storage na nuvem (persiste para todos os usuários no Vercel)
    if (!function_exists('supabase_is_configured') && file_exists(__DIR__ . '/supabase.php')) {
        @include_once __DIR__ . '/supabase.php';
    }
    if (function_exists('supabase_is_configured') && supabase_is_configured()) {
        try {
            $baseUrl = rtrim(SUPABASE_URL, '/');
            $bucket = defined('SUPABASE_STORAGE_BUCKET') ? SUPABASE_STORAGE_BUCKET : 'artworks';
            $token = defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
            $cloudUrl = "{$baseUrl}/storage/v1/object/{$bucket}/site/hero_slides_config.png";
            $headers = [
                'apikey: ' . SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . $token,
                'Content-Type: image/png',
                'x-upsert: true'
            ];
            supabase_http_call($cloudUrl, 'POST', $headers, $json, 5);
        } catch (Throwable $e) {
            // Silencioso se houver falha temporária de rede
        }
    }

    // 4. Sessão PHP
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['hero_slides'] = $normalized;
    }

    // 5. Cookie HTTP (365 dias)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $cookieJson = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @setcookie('artsale_hero_slides', $cookieJson, [
        'expires'  => time() + (365 * 24 * 60 * 60),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['artsale_hero_slides'] = $cookieJson;

    // Invalida cache estático em memória
    artsale_get_hero_slides(true);

    return true;
}

/**
 * Resolve com segurança a URL de exibição de qualquer imagem da galeria/acervo.
 * Suporta:
 * - URLs absolutas do Supabase Storage (https://...supabase.co/...)
 * - URLs de upload local (/assets/images/obras/uploads/...)
 * - URLs relativas da pasta de assets com ajuste automático de pathPrefix (../ ou vazio)
 * - Fallback neutro exclusivo "placeholder-obra.svg" caso nenhuma imagem tenha sido fornecida
 */
function artsale_resolve_image_url(?string $url, string $prefix = ''): string {
    $clean = trim($url ?? '');
    
    // Se vazio, usa o SVG neutro elegante de curadoria (nunca uma foto aleatória de outra obra)
    if (empty($clean)) {
        return $prefix . 'assets/images/obras/placeholder-obra.svg';
    }
    
    // Se for URL externa completa (Supabase Storage, CDN, http/https, data URI)
    if (preg_match('#^(https?:)?//#i', $clean) || str_starts_with($clean, 'data:')) {
        return $clean;
    }
    
    // Normaliza removendo prefixos relativos pré-existentes
    $normalized = preg_replace('#^(\.\./|\./|/)+#', '', $clean);
    
    // Se o arquivo local original não existir mas existir versão .svg
    $fullDiskPath = BASE_PATH . '/' . $normalized;
    if (!file_exists($fullDiskPath)) {
        $svgCandidate = preg_replace('/\.(jpg|jpeg|png)$/i', '.svg', $fullDiskPath);
        if (file_exists($svgCandidate)) {
            $normalized = preg_replace('/\.(jpg|jpeg|png)$/i', '.svg', $normalized);
        } else {
            // Se o arquivo realmente não existe no disco, usa o placeholder elegante
            if (!str_contains($normalized, 'placeholder-obra.svg')) {
                return $prefix . 'assets/images/obras/placeholder-obra.svg';
            }
        }
    }
    
    return $prefix . $normalized;
}

// Função auxiliar que resolve o arquivo da imagem (.jpg ou .svg de fallback) com suporte a pathPrefix
function get_image_url(string $path): string {
    global $pathPrefix;
    return artsale_resolve_image_url($path, $pathPrefix ?? '');
}

/**
 * Retorna a URL de miniatura otimizada para cards e listas, reduzindo tráfego e acelerando renderização.
 * - Prioriza imagens do Supabase Storage com parâmetros de transformação quando disponível.
 * - Suporta parâmetros de redimensionamento e WebP automático em serviços de mídia e CDN.
 * - Evita o download de arquivos pesados (ex: 5MB a 10MB) dentro de cards pequenos.
 */
function artsale_get_thumbnail_url(?string $url, int $width = 480, int $height = 0, int $quality = 82, string $prefix = ''): string {
    $resolved = artsale_resolve_image_url($url, $prefix);
    if (empty($resolved) || str_ends_with($resolved, '.svg')) {
        return $resolved;
    }

    // 1. Supabase Storage: utiliza o endpoint de renderização com dimensões exatas e qualidade
    if (str_contains($resolved, '/storage/v1/object/public/')) {
        if ($width > 0) {
            $renderUrl = str_replace('/storage/v1/object/public/', '/storage/v1/render/image/public/', $resolved);
            $params = ['width=' . $width, 'quality=' . $quality];
            if ($height > 0) $params[] = 'height=' . $height;
            $params[] = 'resize=contain';
            $sep = str_contains($renderUrl, '?') ? '&' : '?';
            return $renderUrl . $sep . implode('&', $params);
        }
        return $resolved;
    }

    // 2. Unsplash CDN: aplica redimensionamento exato, auto=format (WebP automático) e qualidade otimizada
    if (str_contains($resolved, 'images.unsplash.com')) {
        if ($width > 0) {
            $baseUrl = preg_replace('/\?(.*)$/', '', $resolved);
            $params = [
                'w=' . $width,
                'auto=format',
                'fit=crop',
                'q=' . $quality
            ];
            if ($height > 0) {
                $params[] = 'h=' . $height;
            }
            return $baseUrl . '?' . implode('&', $params);
        }
        return $resolved;
    }

    // 3. Imagem local: verifica se existe versão .webp equivalente no disco
    if (!preg_match('#^(https?:)?//#i', $resolved)) {
        $cleanPath = preg_replace('#^(\.\./|\./|/)+#', '', $resolved);
        $fullDiskPath = BASE_PATH . '/' . $cleanPath;
        $webpDiskCandidate = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $fullDiskPath);
        if (file_exists($webpDiskCandidate)) {
            return $prefix . preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $cleanPath);
        }
    }

    return $resolved;
}

// Categorias Oficiais do Mockup
$categorias = [
    [
        'id' => 1,
        'slug' => 'arte-classica',
        'nome' => 'Arte Clássica',
        'imagem' => get_image_url('assets/images/obras/cat-arte-classica.jpg'),
        'crop_key' => 'cat_classica'
    ],
    [
        'id' => 2,
        'slug' => 'arte-moderna',
        'nome' => 'Arte Moderna',
        'imagem' => get_image_url('assets/images/obras/cat-arte-moderna.jpg'),
        'crop_key' => 'cat_moderna'
    ],
    [
        'id' => 3,
        'slug' => 'arte-contemporanea',
        'nome' => 'Arte Contemporânea',
        'imagem' => get_image_url('assets/images/obras/cat-arte-contemporanea.jpg'),
        'crop_key' => 'cat_contemporanea'
    ],
    [
        'id' => 4,
        'slug' => 'paisagens',
        'nome' => 'Paisagens',
        'imagem' => get_image_url('assets/images/obras/cat-paisagens.jpg'),
        'crop_key' => 'cat_paisagens'
    ],
    [
        'id' => 5,
        'slug' => 'retratos',
        'nome' => 'Retratos',
        'imagem' => get_image_url('assets/images/obras/cat-retratos.jpg'),
        'crop_key' => 'cat_retratos'
    ],
    [
        'id' => 6,
        'slug' => 'abstrato',
        'nome' => 'Abstrato',
        'imagem' => get_image_url('assets/images/obras/cat-abstrato.jpg'),
        'crop_key' => 'cat_abstrato'
    ],
    [
        'id' => 7,
        'slug' => 'gravuras',
        'nome' => 'Gravuras',
        'imagem' => get_image_url('assets/images/obras/cat-gravuras.jpg'),
        'crop_key' => 'cat_gravuras'
    ],
    [
        'id' => 8,
        'slug' => 'esculturas',
        'nome' => 'Esculturas',
        'imagem' => get_image_url('assets/images/obras/cat-esculturas.jpg'),
        'crop_key' => 'cat_esculturas'
    ]
];

// Catálogo de Obras: sincronizado dinamicamente com a persistência local de obras reais
$catalogoObras = artsale_get_local_artworks();

// Obras em Destaque (obtidas das obras ativas e marcadas como destaque)
$obrasDestaque = array_values(array_filter($catalogoObras, fn($item) => !empty($item['destaque'])));
if (empty($obrasDestaque)) {
    $obrasDestaque = $catalogoObras;
}

// Função auxiliar para resgatar uma obra pelo ID ou slug
function get_obra_by_id($id) {
    global $catalogoObras;
    $idStr = (string)$id;
    $deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
    
    // Se o próprio ID solicitado estiver na lista de excluídos ou for mock, retorna null
    if (empty($idStr) || in_array($idStr, $deletedIds, true) || str_starts_with($idStr, 'b1000') || (is_numeric($idStr) && (int)$idStr >= 101 && (int)$idStr <= 112)) {
        return null;
    }

    foreach ($catalogoObras as $obra) {
        if ((string)($obra['id'] ?? '') === $idStr || (string)($obra['slug'] ?? '') === $idStr) {
            return $obra;
        }
    }
    return null;
}


// Diferenciais da Galeria (A Art For Sale)
$diferenciais = [
    [
        'icone' => 'shield',
        'titulo' => 'Curadoria Especializada'
    ],
    [
        'icone' => 'award',
        'titulo' => 'Obras com Procedência'
    ],
    [
        'icone' => 'user-check',
        'titulo' => 'Atendimento Personalizado'
    ],
    [
        'icone' => 'star',
        'titulo' => 'Qualidade e Confiança'
    ]
];

// Inclui o manipulador/extrator de imagens do mockup
require_once __DIR__ . '/setup_images.php';

