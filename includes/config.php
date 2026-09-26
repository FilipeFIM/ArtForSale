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
        'telefone' => '(51) 9114-0044',
        'whatsapp' => '555191140044',
        'whatsapp_link' => 'https://wa.me/555191140044?text=' . urlencode('Olá, Sra. Elisabeth. Gostaria de mais informações sobre as obras da Art For Sale.')
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

// 4 Obras em Destaque (Fictícias temporárias idênticas ao Mockup)
$obrasDestaque = [
    [
        'id' => 101,
        'titulo' => 'Horizontes Dourados',
        'artista' => 'Maria Fernandes',
        'dimensoes' => '80 × 120 cm',
        'preco' => 'Preço sob consulta',
        'categoria' => 'Paisagens',
        'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados.jpg'),
        'crop_key' => 'obra_horizontes_dourados'
    ],
    [
        'id' => 102,
        'titulo' => 'Silêncio Urbano',
        'artista' => 'Carlos Menezes',
        'dimensoes' => '100 × 100 cm',
        'preco' => 'Preço sob consulta',
        'categoria' => 'Arte Moderna',
        'imagem' => get_image_url('assets/images/obras/obra-silencio-urbano.jpg'),
        'crop_key' => 'obra_silencio_urbano'
    ],
    [
        'id' => 103,
        'titulo' => 'Essência',
        'artista' => 'Juliana Costa',
        'dimensoes' => '60 × 90 cm',
        'preco' => 'Preço sob consulta',
        'categoria' => 'Retratos',
        'imagem' => get_image_url('assets/images/obras/obra-essencia.jpg'),
        'crop_key' => 'obra_essencia'
    ],
    [
        'id' => 104,
        'titulo' => 'Azul Profundo',
        'artista' => 'Rafael Almeida',
        'dimensoes' => '120 × 150 cm',
        'preco' => 'Preço sob consulta',
        'categoria' => 'Arte Contemporânea',
        'imagem' => get_image_url('assets/images/obras/obra-azul-profundo.jpg'),
        'crop_key' => 'obra_azul_profundo'
    ]
];

// Catálogo Completo de Obras (12 obras com detalhes curatoriais e galeria completa)
$catalogoObras = [
    [
        'id' => 101,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Horizontes Dourados',
        'artista' => 'Maria Fernandes',
        'descricao' => 'Uma composição de expressivo lirismo visual onde a transição crepuscular irradia luminosidade sobre as águas serenas. O trabalho cromático em óleo sobre tela estabelece diálogos sutis entre o calor do ocre e a profundidade dos tons noturnos, conferindo ao ambiente uma presença imponente, contemplativa e acolhedora.',
        'tecnica' => 'Óleo sobre tela',
        'ano' => 2021,
        'dimensoes' => '80 × 120 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0012',
        'categoria' => 'Paisagens',
        'categoria_slug' => 'paisagens',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Coleção Particular',
        'localizacao' => 'Porto Alegre - RS',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados.jpg'),
        'galeria' => [
            [
                'tipo' => 'frontal',
                'titulo' => 'Fotografia frontal',
                'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados.jpg')
            ],
            [
                'tipo' => 'lateral',
                'titulo' => 'Fotografia lateral',
                'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')
            ],
            [
                'tipo' => 'detalhe',
                'titulo' => 'Detalhe / textura',
                'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')
            ],
            [
                'tipo' => 'ambiente',
                'titulo' => 'Obra em ambiente',
                'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')
            ]
        ],
        'ordem' => 1
    ],
    [
        'id' => 102,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Silêncio Urbano',
        'artista' => 'Carlos Menezes',
        'descricao' => 'Exploração geométrica das paisagens metropolitanas em silêncio. Através de tons monocromáticos e contrastes pontuais em ouro envelhecido, Menezes desconstrói a arquitetura das grandes cidades para evocar introspecção e equilíbrio estético.',
        'tecnica' => 'Acrílica e técnica mista sobre tela',
        'ano' => 2024,
        'dimensoes' => '100 × 100 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0013',
        'categoria' => 'Arte Moderna',
        'categoria_slug' => 'arte-moderna',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Acervo do Artista',
        'localizacao' => 'São Paulo - SP',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/obra-silencio-urbano.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/obra-silencio-urbano.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 2
    ],
    [
        'id' => 103,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Essência',
        'artista' => 'Juliana Costa',
        'descricao' => 'Um retrato de intensidade psicológica tocante. Com paleta intimista e jogos de luz e sombra de inspiração caravaggesca, Costa traduz a vulnerabilidade e a força interior humana com maestria singular.',
        'tecnica' => 'Óleo sobre linho belga',
        'ano' => 2024,
        'dimensoes' => '60 × 90 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0014',
        'categoria' => 'Retratos',
        'categoria_slug' => 'retratos',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Coleção Particular',
        'localizacao' => 'Rio de Janeiro - RJ',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/obra-essencia.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/obra-essencia.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 3
    ],
    [
        'id' => 104,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Azul Profundo',
        'artista' => 'Rafael Almeida',
        'descricao' => 'Imersão cromática em grandes dimensões que dialoga com as profundezas oceânicas e o infinito espacial. Camadas sucessivas de azul ultramarino e pigmentos minerais geram campos ópticos hipnóticos.',
        'tecnica' => 'Técnica mista e pigmentos minerais',
        'ano' => 2024,
        'dimensoes' => '120 × 150 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0015',
        'categoria' => 'Arte Contemporânea',
        'categoria_slug' => 'arte-contemporanea',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Galeria Art For Sale',
        'localizacao' => 'Belo Horizonte - MG',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/obra-azul-profundo.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/obra-azul-profundo.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 4
    ],
    [
        'id' => 105,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Aura Clássica',
        'artista' => 'Helena Vianna',
        'descricao' => 'Rigor acadêmico e estética renascentista reinterpretados para o colecionismo contemporâneo. Representação de virtudes atemporais através do estudo refinado da anatomia e draperie clássica.',
        'tecnica' => 'Óleo sobre tela',
        'ano' => 2023,
        'dimensoes' => '70 × 100 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0016',
        'categoria' => 'Arte Clássica',
        'categoria_slug' => 'arte-classica',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Coleção Particular',
        'localizacao' => 'Curitiba - PR',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-arte-classica.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-arte-classica.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 5
    ],
    [
        'id' => 106,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Ritmo Cromático',
        'artista' => 'Beatriz Drummond',
        'descricao' => 'Sinfonia visual construída através de acordes tonais vibrantes e traços dinâmicos. A artista propõe uma experiência sensorial onde cada cor atua como uma nota musical em perfeita ressonância.',
        'tecnica' => 'Acrílica sobre tela',
        'ano' => 2024,
        'dimensoes' => '90 × 90 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0017',
        'categoria' => 'Arte Moderna',
        'categoria_slug' => 'arte-moderna',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Acervo do Artista',
        'localizacao' => 'Salvador - BA',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-arte-moderna.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-arte-moderna.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 6
    ],
    [
        'id' => 107,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Terra e Memória',
        'artista' => 'Eduardo Fontes',
        'descricao' => 'Pesquisa matérica com texturas orgânicas e tons minerais que remetem à história geológica e à ancestralidade. Uma peça de presença sólida que harmoniza perfeitamente com projetos de arquitetura biofílica.',
        'tecnica' => 'Textura terrosa e polímeros sobre tela',
        'ano' => 2023,
        'dimensoes' => '110 × 140 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0018',
        'categoria' => 'Arte Contemporânea',
        'categoria_slug' => 'arte-contemporanea',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Coleção Particular',
        'localizacao' => 'Florianópolis - SC',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-arte-contemporanea.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-arte-contemporanea.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 7
    ],
    [
        'id' => 108,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Crepúsculo Sereno',
        'artista' => 'André Valente',
        'descricao' => 'Paisagem lírica que registra a quietude das montanhas sob a luz dourada do entardecer. Valente demonstra domínio impecável da perspectiva atmosférica e da suavidade das névoas serranas.',
        'tecnica' => 'Óleo sobre tela',
        'ano' => 2024,
        'dimensoes' => '75 × 110 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0019',
        'categoria' => 'Paisagens',
        'categoria_slug' => 'paisagens',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Galeria Art For Sale',
        'localizacao' => 'Gramado - RS',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-paisagens.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-paisagens.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 8
    ],
    [
        'id' => 109,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Olhar Silencioso',
        'artista' => 'Gabriela Siqueira',
        'descricao' => 'Retrato contemporâneo carregado de sutilezas emocionais. O traço delicado e a economia gestual colocam em evidência a narrativa velada no olhar da figura retratada.',
        'tecnica' => 'Óleo e têmpera sobre madeira nobre',
        'ano' => 2023,
        'dimensoes' => '50 × 75 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0020',
        'categoria' => 'Retratos',
        'categoria_slug' => 'retratos',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Acervo do Artista',
        'localizacao' => 'Brasília - DF',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-retratos.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-retratos.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 9
    ],
    [
        'id' => 110,
        'tipo' => 'OBRA ORIGINAL',
        'titulo' => 'Harmonia Geométrica',
        'artista' => 'Lucas Brandão',
        'descricao' => 'Diálogo entre proporção áurea, linhas ortogonais e aplicações delicadas de folha de ouro. Uma obra de rigor conceitual construtivista que ilumina ambientes de arquitetura minimalista.',
        'tecnica' => 'Óleo e folha de ouro sobre tela',
        'ano' => 2024,
        'dimensoes' => '100 × 120 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0021',
        'categoria' => 'Abstrato',
        'categoria_slug' => 'abstrato',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Coleção Particular',
        'localizacao' => 'São Paulo - SP',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-abstrato.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-abstrato.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 10
    ],
    [
        'id' => 111,
        'tipo' => 'GRAVURA ORIGINAL',
        'titulo' => 'Atelier Histórico',
        'artista' => 'Rodrigo Vasconcelos',
        'descricao' => 'Gravura original em metal executada nas técnicas de água-forte e ponta-seca sobre papel algodão artesanal 300g. Registro meticuloso da atmosfera de ateliês de mestres gravuristas do século XIX.',
        'tecnica' => 'Gravura em metal (água-forte e ponta-seca)',
        'ano' => 2022,
        'dimensoes' => '45 × 65 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0022',
        'categoria' => 'Gravuras',
        'categoria_slug' => 'gravuras',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Tiragem Especial de Autor (7/25)',
        'localizacao' => 'Petrópolis - RJ',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-gravuras.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-gravuras.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 11
    ],
    [
        'id' => 112,
        'tipo' => 'ESCULTURA ORIGINAL',
        'titulo' => 'Busto Imperial',
        'artista' => 'Marcelo Duarte',
        'descricao' => 'Escultura tridimensional em mármore nobre cinzelado à mão com pátina em bronze. Síntese imponente entre a solenidade clássica greco-romana e a fluidez orgânica da escultura contemporânea.',
        'tecnica' => 'Mármore esculpido e bronze com pátina',
        'ano' => 2023,
        'dimensoes' => '55 × 35 × 30 cm',
        'estado_conservacao' => 'Excelente',
        'codigo' => 'ASF-0023',
        'categoria' => 'Esculturas',
        'categoria_slug' => 'esculturas',
        'disponibilidade' => 'Disponível',
        'procedencia' => 'Acervo da Galeria Art For Sale',
        'localizacao' => 'Porto Alegre - RS',
        'preco' => 'Preço sob consulta',
        'imagem' => get_image_url('assets/images/obras/cat-esculturas.jpg'),
        'galeria' => [
            ['tipo' => 'frontal', 'titulo' => 'Fotografia frontal', 'imagem' => get_image_url('assets/images/obras/cat-esculturas.jpg')],
            ['tipo' => 'lateral', 'titulo' => 'Fotografia lateral', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-lateral.svg')],
            ['tipo' => 'detalhe', 'titulo' => 'Detalhe / textura', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-detalhe.svg')],
            ['tipo' => 'ambiente', 'titulo' => 'Obra em ambiente', 'imagem' => get_image_url('assets/images/obras/obra-horizontes-dourados-ambiente.svg')]
        ],
        'ordem' => 12
    ]
];

// Função auxiliar para resgatar uma obra pelo ID ou retornar a principal válida
function get_obra_by_id($id) {
    global $catalogoObras;
    $id = (int)$id;
    $deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
    
    // Se o próprio ID solicitado estiver na lista de excluídos, retorna null
    if (in_array((string)$id, $deletedIds, true)) {
        return null;
    }

    foreach ($catalogoObras as $obra) {
        if ($obra['id'] === $id) {
            return $obra;
        }
    }
    
    // Retorna a primeira obra disponível que NÃO esteja na lista de excluídos
    foreach ($catalogoObras as $obra) {
        if (!in_array((string)$obra['id'], $deletedIds, true)) {
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

